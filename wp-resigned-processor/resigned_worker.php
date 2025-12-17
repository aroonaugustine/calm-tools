<?php
/**
 * Resigned Processor — Worker v15.09.0002.0001
 *
 * What it does per matched user:
 *   - (optional) Set Ultimate Member account_status=inactive
 *   - (always, LIVE) Set employment_status = "Inactive"
 *   - (optional) Switch role to 'inactive' OR strip all roles
 *   - Kill all login sessions
 *   - (optional) Remove from all LearnDash groups
 *   - (optional) Unenroll from all LearnDash courses (+ optional progress reset)
 *
 * Matching policy:
 *   --match=strict (default): email + (NIC OR Passport) must match same WP user
 *   --match=email            : email only
 *   --match=id               : NIC/Passport only (email column not required)
 *
 * Usage (spawned by launcher or batcher):
 *   php resigned_worker.php \
 *     --csv=/path/file.csv \
 *     --matched=/path/matched.csv \
 *     --unmatched=/path/unmatched.csv \
 *     [--match=strict|email|id] [--live] [--limit=100] \
 *     [--remove-groups] [--unenroll-courses] [--reset-progress] \
 *     [--um-inactive] [--strip-roles]
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
if (!defined('WP_DISABLE_FATAL_ERROR_HANDLER')) define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
register_shutdown_function(function(){
  $e = error_get_last();
  if ($e && in_array($e['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR])) {
    fwrite(STDERR, "FATAL: {$e['message']} in {$e['file']}:{$e['line']}\n");
  }
});

require('/var/www/html/wp-load.php'); // adjust if needed

/* ---------- Arg parsing ---------- */
$argv_assoc = [];
foreach (($argv ?? []) as $a) {
  if (preg_match('/^--([^=]+)=(.*)$/', $a, $m)) $argv_assoc[$m[1]] = $m[2];
  elseif (preg_match('/^--(.+)$/', $a, $m))     $argv_assoc[$m[1]] = true;
}

$CSV      = (string)($argv_assoc['csv']      ?? '');
$MATCHED  = (string)($argv_assoc['matched']  ?? '');
$UNMATCH  = (string)($argv_assoc['unmatched']?? '');
$LIVE     = !empty($argv_assoc['live']);
$LIMIT    = isset($argv_assoc['limit']) ? max(0,(int)$argv_assoc['limit']) : 0;
$MATCH_POLICY = strtolower((string)($argv_assoc['match'] ?? 'strict'));
if (!in_array($MATCH_POLICY, ['strict','email','id'], true)) $MATCH_POLICY = 'strict';

// toggles
$DO_GROUPS   = !empty($argv_assoc['remove-groups']);
$DO_UNENROLL = !empty($argv_assoc['unenroll-courses']);
$DO_RESET    = !empty($argv_assoc['reset-progress']);
$DO_UM       = !empty($argv_assoc['um-inactive']);
$DO_STRIP    = !empty($argv_assoc['strip-roles']);

/* ---------- Helpers ---------- */
function detect_delimiter($line) {
  foreach ([",","\t",";"] as $d) if (count(str_getcsv($line,$d))>1) return $d;
  return ",";
}
function norm_label($s){ return strtolower(trim(preg_replace('/[^\p{L}\p{N}]+/u','',$s))); }
function build_idx_map($header){ $m=[]; foreach($header as $i=>$h){ $m[norm_label($h)]=$i; } return $m; }
function v($row,$idx){ return ($idx!==null && $idx!==false && isset($row[$idx])) ? trim((string)$row[$idx]) : ''; }

function clean_id_like($s){
  $raw = (string)$s;
  $s = trim($raw);
  if ($s === '') return '';
  if (preg_match('/^\s*no\s*nic\s*$/i', $s)) return '';
  $s = strtolower($s);
  return preg_replace('/[^a-z0-9]/i', '', $s);
}
function eq_id_like($a,$b){
  $an = clean_id_like($a);
  $bn = clean_id_like($b);
  if ($an === '' || $bn === '') return false;
  if (ctype_digit($an) && ctype_digit($bn)) {
    $an = ltrim($an,'0'); if ($an==='') $an='0';
    $bn = ltrim($bn,'0'); if ($bn==='') $bn='0';
    return $an === $bn;
  }
  return $an === $bn;
}
function get_meta_multi_raw($uid, array $keys){
  foreach ($keys as $k){
    $v = get_user_meta($uid, $k, true);
    if ($v !== '' && $v !== null) return (string)$v;
  }
  return '';
}
function ld_all_user_courses($user_id){
  if (function_exists('learndash_user_get_enrolled_courses')) $ids = (array)learndash_user_get_enrolled_courses($user_id);
  elseif (function_exists('ld_get_mycourses'))                $ids = (array)ld_get_mycourses($user_id);
  else $ids = [];
  return array_values(array_unique(array_map('intval',$ids)));
}
function touch_marker(string $matchedPath, string $marker){
  $dir = dirname($matchedPath);
  if (is_dir($dir)) @touch($dir . '/' . $marker);
}

/* ---------- Validate inputs ---------- */
if ($CSV==='' || !is_file($CSV)) { fwrite(STDERR,"❌ CSV not found: $CSV\n"); exit(2); }
if ($MATCHED==='')   { fwrite(STDERR,"❌ Missing --matched path\n"); exit(2); }
if ($UNMATCH==='')   { fwrite(STDERR,"❌ Missing --unmatched path\n"); exit(2); }

$run_dir = dirname($MATCHED);
@mkdir($run_dir,0755,true);
touch_marker($MATCHED, 'STARTED');

/* ---------- Open CSV ---------- */
$fh = fopen($CSV,'r'); if(!$fh){ fwrite(STDERR,"❌ Cannot open CSV\n"); exit(2); }
$first = fgets($fh); if($first===false){ fwrite(STDERR,"❌ Empty CSV\n"); exit(2); }
$delim  = detect_delimiter($first);
$header = str_getcsv($first,$delim);
$idxmap = build_idx_map($header);

// Key columns (robust, case/spacing/punct insensitive)
$idx_email = $idxmap[norm_label('user_email')] ??
             ($idxmap[norm_label('email')] ??
             ($idxmap[norm_label('email address')] ??
             ($idxmap[norm_label('e-mail')] ?? null)));

$idx_nic = $idxmap[norm_label('nic_no')] ??
           ($idxmap[norm_label('nic number')] ??
           ($idxmap[norm_label('nic #')] ??
           ($idxmap[norm_label('nic')] ??
           ($idxmap[norm_label('national id')] ??
           ($idxmap[norm_label('national id number')] ??
           ($idxmap[norm_label('nric')] ??
           ($idxmap[norm_label('nric no')] ?? null)))))));

$idx_passport = $idxmap[norm_label('passport_no')] ??
                ($idxmap[norm_label('passport number')] ??
                ($idxmap[norm_label('passport #')] ??
                ($idxmap[norm_label('passport')] ??
                ($idxmap[norm_label('pp no')] ??
                ($idxmap[norm_label('pp_no')] ?? null)))));

$require_email = ($MATCH_POLICY !== 'id');
if ($require_email && $idx_email === null) {
  fwrite(STDERR, "❌ CSV must contain an email column. Tried: user_email, email, email address, e-mail\n");
  exit(2);
}
if ($MATCH_POLICY === 'strict' && ($idx_nic===null && $idx_passport===null)) {
  fwrite(STDERR, "❌ Strict mode requires NIC or Passport column.\n");
  exit(2);
}
if ($MATCH_POLICY === 'id' && ($idx_nic===null && $idx_passport===null)) {
  fwrite(STDERR, "❌ NIC/Passport-only mode requires NIC and/or Passport column.\n");
  exit(2);
}

/* ---------- Meta snapshot columns (optional read) ---------- */
$meta_fields = [
  'employee_number'          => 'Employee Number',
  'employment_status'        => 'Employment Status',
  'passport_no'              => 'Passport Number',
  'passport_exp'             => 'Passport Expiry Date (dd/mm/yyyy)',
  'nationality'              => 'Nationality',
  'expat_local'              => 'Expat/Local',
  'visa_issue_date'          => 'Visa Issue Date (dd/mm/yyyy)',
  'visa_exp'                 => 'Visa Expiry Date (dd/mm/yyyy)',
  'nic_no'                   => 'NIC Number',
  'date_of_birth'            => 'Date Of Birth',
  'mobile_no'                => 'Mobile Number',
  'gender'                   => 'Gender',
  'company'                  => 'Company',
  'division'                 => 'Division',
  'designation'              => 'Designation',
  'home_address'             => 'Local Home Address',
  'perm_address'             => 'Permanent Address',
  'office_address'           => 'Office Address',
  'emergency_contact_email'  => 'Emergency Contact – Email',
  'emergency_contact_phone'  => 'Emergency Contact – Phone',
  'emergency_contact_who'    => 'Emergency Contact – Relationship',
  'join_date'                => 'Joining Date',
  'resign_date'              => 'Resignation Date',
  'profile_picture'          => 'Profile Picture',
];
$meta_idx = [];
foreach ($meta_fields as $k=>$label) $meta_idx[$k] = $idxmap[norm_label($label)] ?? null;

/* ---------- Output CSVs ---------- */
$mf  = fopen($MATCHED,'w');   if(!$mf){ fwrite(STDERR,"❌ Cannot open matched path\n"); exit(2); }
$uf  = fopen($UNMATCH,'w');   if(!$uf){ fwrite(STDERR,"❌ Cannot open unmatched path\n"); exit(2); }

fputcsv($mf, array_merge(
  ['user_login','user_email','actions'],
  array_keys($meta_fields),
  ['removed_groups','unenrolled_courses','progress_reset']
));
fputcsv($uf, ['reason','user_email','NIC','Passport']);

/* ---------- Print WP env diag ---------- */
global $wpdb;
$users_table = $wpdb->users;
echo "🧭 WP ENV:\n";
echo "  ABSPATH: " . ABSPATH . "\n";
echo "  site_url: " . site_url() . "\n";
echo "  home_url: " . home_url() . "\n";
echo "  DB_NAME: " . DB_NAME . "\n";
echo "  table prefix: " . $wpdb->prefix . "\n";
echo "  users table: " . $users_table . "\n";
$cnt = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$users_table}");
echo "  users count: {$cnt}\n";

echo "Resigned Processor v15.09.0002.0001 — ".($LIVE?'LIVE':'DRY RUN')."\n";
echo "    Policy: " . ($MATCH_POLICY==='email' ? "email only" : ($MATCH_POLICY==='id' ? "NIC/Passport only" : "email + (NIC OR Passport)")) . "\n";
echo "    Options: groups=".($DO_GROUPS?'on':'off')." unenroll=".($DO_UNENROLL?'on':'off')." reset=".($DO_RESET?'on':'off')
   ." UM=".($DO_UM?'on':'off')." strip_roles=".($DO_STRIP?'on':'off')."\n";

/* ---------- Process rows ---------- */
$done=0; $matched=0; $unmatched=0;

while (($row = fgetcsv($fh,0,$delim)) !== false) {
  if ($LIMIT>0 && $done>=$LIMIT) { echo "⏹️ Limit {$LIMIT} reached.\n"; break; }
  if (count(array_filter($row, fn($x)=>trim((string)$x)!==''))===0) continue;

  $email    = $idx_email!==null ? v($row,$idx_email) : '';
  $nic_csv  = $idx_nic!==null      ? v($row,$idx_nic)      : '';
  $pp_csv   = $idx_passport!==null ? v($row,$idx_passport) : '';

  $nic_csv_clean = clean_id_like($nic_csv);
  $pp_csv_clean  = clean_id_like($pp_csv);

  if ($require_email && $email === '') {
    fputcsv($uf, ['Missing email', $email, $nic_csv, $pp_csv]);
    echo "⚠️  Skipped: no email. CSV(NIC='{$nic_csv}', PP='{$pp_csv}')\n";
    $unmatched++; $done++; continue;
  }

  // Find the user (by policy)
  $user = null;

  if ($MATCH_POLICY === 'email' || $MATCH_POLICY === 'strict') {
    if ($email !== '') $user = get_user_by('email',$email);
  } elseif ($MATCH_POLICY === 'id') {
    // Try NIC, then Passport
    if ($nic_csv_clean !== '') {
      $uu = get_users(['meta_key'=>'nic_no','meta_value'=>$nic_csv,'number'=>2]);
      if (count($uu) === 1) $user = $uu[0];
    }
    if (!$user && $pp_csv_clean !== '') {
      $uu = get_users(['meta_key'=>'passport_no','meta_value'=>$pp_csv,'number'=>2]);
      if (count($uu) === 1) $user = $uu[0];
    }
  }

  if (!$user) {
    fputcsv($uf, ["No user matched (policy={$MATCH_POLICY})", $email, $nic_csv, $pp_csv]);
    echo "❌ No WP user matched (email='{$email}', NIC='{$nic_csv}', PP='{$pp_csv}')\n";
    $unmatched++; $done++; continue;
  }

  $uid   = (int)$user->ID;
  $login = (string)$user->user_login;

  // Compare IDs if policy demands it
  $nic_wp_raw = get_meta_multi_raw($uid, ['nic_no','nic','national_id']);
  $pp_wp_raw  = get_meta_multi_raw($uid, ['passport_no','passport','pp_no']);

  $nic_ok = ($nic_csv_clean !== '' && $nic_wp_raw !== '') ? eq_id_like($nic_csv, $nic_wp_raw) : false;
  $pp_ok  = ($pp_csv_clean  !== '' && $pp_wp_raw  !== '') ? eq_id_like($pp_csv,  $pp_wp_raw ) : false;

  echo sprintf(
    "🔎 %s | policy=%s | NIC csv='%s' vs wp='%s' → %s | PP csv='%s' vs wp='%s' → %s\n",
    $login, $MATCH_POLICY,
    $nic_csv, $nic_wp_raw, $nic_ok ? 'MATCH' : 'NO',
    $pp_csv,  $pp_wp_raw,  $pp_ok  ? 'MATCH' : 'NO'
  );

  $ok = false;
  if ($MATCH_POLICY === 'email') {
    $ok = true; // email resolved to a user
  } elseif ($MATCH_POLICY === 'id') {
    $ok = ($nic_ok || $pp_ok); // require an actual ID match
  } else { // strict
    $ok = ($email !== '' && ($nic_ok || $pp_ok));
  }

  if (!$ok) {
    $why = ($MATCH_POLICY==='email')
      ? "Email not found"  // normally not hit
      : (($MATCH_POLICY==='id') ? "NIC/Passport mismatch" : "Email found, but NIC/Passport mismatch");
    $why_csv = sprintf(
      "%s (csv_nic='%s'→'%s', wp_nic='%s'→'%s'; csv_pp='%s'→'%s', wp_pp='%s'→'%s')",
      $why,
      $nic_csv, clean_id_like($nic_csv), $nic_wp_raw, clean_id_like($nic_wp_raw),
      $pp_csv,  clean_id_like($pp_csv),  $pp_wp_raw,  clean_id_like($pp_wp_raw)
    );
    fputcsv($uf, [$why_csv, $email, $nic_csv, $pp_csv]);
    $unmatched++; $done++; continue;
  }

  /* --- Deactivate & cleanup --- */
  $actions=[]; $removed_groups=[]; $unenrolled_courses=[]; $reset_courses=[];

  // 0) Always (LIVE) set employment_status = Inactive
  $actions[]='meta:employment_status=Inactive';
  if ($LIVE) update_user_meta($uid,'employment_status','Inactive');

  // 1) UM account_status (if requested)
  if ($DO_UM) {
    $actions[]='um:inactive';
    if ($LIVE) update_user_meta($uid,'account_status','inactive'); // Ultimate Member
  }

  // 2) Role handling
  if ($DO_STRIP) {
    $actions[]='roles:strip-all';
    if ($LIVE) { $user->set_role(''); }
  } else {
    $actions[]='role:inactive';
    if ($LIVE) {
      if (!get_role('inactive')) { $user->set_role(''); } else { $user->set_role('inactive'); }
    }
  }

  // 3) Kill sessions
  $actions[]='sessions:kill';
  if ($LIVE && class_exists('WP_Session_Tokens')) {
    $mgr = WP_Session_Tokens::get_instance($uid);
    if ($mgr) $mgr->destroy_all();
  }

  // 4) LearnDash groups
  if ($DO_GROUPS && function_exists('learndash_get_users_group_ids')) {
    $gids = (array)learndash_get_users_group_ids($uid);
    foreach ($gids as $gid) {
      $removed_groups[] = (int)$gid;
      if ($LIVE && function_exists('ld_update_group_access')) {
        ld_update_group_access($uid, (int)$gid, 'remove');
      }
    }
    $actions[] = 'ld:groups-removed('.count($removed_groups).')';
  }

  // 5) LearnDash courses
  if ($DO_UNENROLL && function_exists('ld_update_course_access')) {
    $cids = ld_all_user_courses($uid);
    foreach ($cids as $cid) {
      $unenrolled_courses[] = (int)$cid;
      if ($LIVE) ld_update_course_access($uid, (int)$cid, true);
      if ($DO_RESET && function_exists('learndash_delete_course_progress')) {
        if ($LIVE) learndash_delete_course_progress((int)$cid, (int)$uid);
        $reset_courses[] = (int)$cid;
      }
    }
    $actions[] = 'ld:unenrolled('.count($unenrolled_courses).')';
    if ($DO_RESET) $actions[] = 'ld:progress-reset('.count($reset_courses).')';
  }

  // Snapshot meta for matched CSV
  $meta_snapshot=[];
  foreach ($meta_fields as $k=>$label) $meta_snapshot[] = (string)get_user_meta($uid,$k,true);

  fputcsv($mf, array_merge(
    [$login, $user->user_email, implode('|',$actions)],
    $meta_snapshot,
    [implode('|',$removed_groups), implode('|',$unenrolled_courses), implode('|',$reset_courses)]
  ));

  echo ($LIVE ? "✅ " : "👁️ PREVIEW ")."{$login} — ".implode(', ',$actions)."\n";
  $matched++; $done++;
}

/* ---------- Close & summary ---------- */
fclose($fh); fclose($mf); fclose($uf);
touch_marker($MATCHED, 'DONE');

echo "📊 Summary: matched={$matched}, unmatched={$unmatched}, processed={$done}, mode=".($LIVE?'LIVE':'DRY')."\n";
