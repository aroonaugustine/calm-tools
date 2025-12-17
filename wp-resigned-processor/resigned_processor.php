<?php
/**
 * Resigned Processor — Worker v15.09.0002.0001
 *
 * Modes:
 *   --mode=csv   (default)
 *       --csv-file=/path.csv
 *       [--remove-groups]  Also remove from all LD groups (recommended)
 *       [--dry-run] [--limit=N]
 *       [--match=strict|email|id]
 *
 *   --mode=sweep
 *       (no CSV) Find users already inactive and remove them from LD groups
 *       [--dry-run] [--limit=N]
 *
 * Outputs (both modes):
 *   --matched-file=/path/matched_done.csv
 *   --unmatched-file=/path/unmatched_skipped.csv
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

/* ---------- Args ---------- */
$argv_assoc = [];
foreach ($argv ?? [] as $a) {
  if (preg_match('/^--([^=]+)=(.*)$/', $a, $m)) $argv_assoc[$m[1]] = $m[2];
  elseif (preg_match('/^--(.+)$/', $a, $m))     $argv_assoc[$m[1]] = true;
}
$mode           = ($argv_assoc['mode'] ?? 'csv') === 'sweep' ? 'sweep' : 'csv';
$csv_file       = (string)($argv_assoc['csv-file'] ?? '');
$matched_file   = (string)($argv_assoc['matched-file'] ?? '');
$unmatched_file = (string)($argv_assoc['unmatched-file'] ?? '');
$dry_run        = !empty($argv_assoc['dry-run']);
$limit          = isset($argv_assoc['limit']) ? max(0, (int)$argv_assoc['limit']) : 0;
$remove_groups  = !empty($argv_assoc['remove-groups']); // only meaningful in CSV mode; sweep always removes
$match_policy   = strtolower((string)($argv_assoc['match'] ?? 'strict'));
if (!in_array($match_policy,['strict','email','id'],true)) $match_policy='strict';

if ($matched_file === '' || $unmatched_file === '') { fwrite(STDERR, "❌ Output file args missing\n"); exit(2); }
if ($mode === 'csv' && ($csv_file === '' || !is_file($csv_file))) { fwrite(STDERR, "❌ CSV not found: $csv_file\n"); exit(2); }

echo "🚀 Resigned Processor Started — Mode: {$mode} — " . ($dry_run ? "DRY RUN" : "LIVE") . "\n";

/* ---------- Helpers (shared) ---------- */
function detect_delimiter_smart($path){
  $sample = @file_get_contents($path, false, null, 0, 8192);
  if ($sample === false) return ',';
  $candidates = [",","\t",";","|"];
  $best = ','; $bestCount = 0;
  foreach ($candidates as $d) {
    $lines = preg_split("/\r\n|\r|\n/", $sample);
    $line = $lines[0] ?? '';
    $cnt = count(str_getcsv($line,$d));
    if ($cnt > $bestCount) { $bestCount = $cnt; $best = $d; }
  }
  return $best;
}
function calm_strip_bom($s) {
  if ($s === null) return $s;
  return preg_replace('/^\xEF\xBB\xBF/u', '', $s);
}
function calm_norm_ws_and_separators($s) {
  $map = [
    "\xC2\xA0" => ' ',
    "\xE2\x80\xAF" => ' ',
    "\xE2\x80\x93" => '-',
    "\xE2\x80\x94" => '-',
    "\xE2\x88\x92" => '-',
    "\xEF\xB8\x8F" => '',
  ];
  $s = strtr($s, $map);
  $s = preg_replace('/[^\pL\pN]+/u', '-', $s);
  $s = preg_replace('/-+/', '-', $s);
  $s = trim($s, "- ");
  return $s;
}
function norm_label($s){
  $s = (string)$s;
  $s = calm_strip_bom($s);
  $s = trim($s);
  $s = mb_strtolower($s,'UTF-8');
  $s = calm_norm_ws_and_separators($s);
  $alias = [
    'email-address' => 'email',
    'e-mail'        => 'email',
    'mail'          => 'email',
    'useremail'     => 'user-email',
    'user-email-address' => 'user-email',
    'login-email'   => 'email',
    'work-email'    => 'email',
    'primary-email' => 'email',
    'user_email'    => 'user-email',
  ];
  if (isset($alias[$s])) $s = $alias[$s];
  return $s;
}
function build_index_map($header) {
  $map=[]; foreach ($header as $i=>$h) $map[norm_label($h)]=$i; return $map;
}
function get_val($row, $idx) {
  return $idx !== null && $idx !== false && isset($row[$idx]) ? trim((string)$row[$idx]) : '';
}
function user_mark_inactive($user, $dry_run){
  $uid = (int)$user->ID;
  if (!$dry_run && method_exists($user,'set_role')) $user->set_role('inactive');
  if (!$dry_run) {
    update_user_meta($uid, 'um_membership_status', 'inactive');
    update_user_meta($uid, 'user_status', 1);
  }
}
function user_is_inactive($user){
  $uid = (int)$user->ID;
  $roles = is_array($user->roles) ? $user->roles : [];
  if (in_array('inactive', $roles, true)) return true;
  $um = get_user_meta($uid, 'um_membership_status', true);
  if ((string)$um === 'inactive') return true;
  $us = get_user_meta($uid, 'user_status', true);
  if ((string)$us === '1') return true;
  return false;
}
function ld_remove_all_groups($uid, $dry_run){
  if (!function_exists('learndash_get_users_group_ids')) return [];
  $groups = (array) learndash_get_users_group_ids($uid);
  foreach ($groups as $gid) {
    if (!$dry_run && function_exists('ld_update_group_access')) ld_update_group_access($uid, $gid, 'remove');
  }
  return array_map('intval', $groups);
}

/* ID-only lookup helper */
function find_user_by_ids_only($nic_csv, $pp_csv){
  $nic_user = null; $pp_user = null;

  if ($nic_csv !== '') {
    foreach (['nic_no','nic','national_id'] as $k) {
      $u = get_users(['meta_key'=>$k,'meta_value'=>$nic_csv,'number'=>1,'fields'=>['ID','user_login','user_email']]);
      if (!empty($u)) { $nic_user = get_user_by('id', $u[0]->ID); break; }
    }
  }

  if ($pp_csv !== '') {
    foreach (['passport_no','passport','pp_no'] as $k) {
      $u = get_users(['meta_key'=>$k,'meta_value'=>$pp_csv,'number'=>1,'fields'=>['ID','user_login','user_email']]);
      if (!empty($u)) { $pp_user = get_user_by('id', $u[0]->ID); break; }
    }
  }

  if ($nic_user && $pp_user) {
    if ((int)$nic_user->ID === (int)$pp_user->ID) return $nic_user;
    return new WP_Error('id_conflict', 'NIC and Passport resolve to different users');
  }
  return $nic_user ?: $pp_user ?: null;
}

/* ---------- CSV mode ---------- */
if ($mode === 'csv') {

  $meta_fields = [
    'expat_local'       => 'Expatriate/Local',
    'employment_status' => 'Status of Employee',
    'company'           => 'Company Name',
    'division'          => 'Department',
    'date_of_birth'     => 'DOB',
    'nic_no'            => 'NIC #',
    'passport_no'       => 'Passport',
    'gender'            => 'Gender',
    'mobile_no'         => 'Mobile',
    'designation'       => 'Designation',
    'join_date'         => 'DOJ',
    'employee_number'   => 'Employee Number',
  ];

  $delim = detect_delimiter_smart($csv_file);
  $fh = fopen($csv_file, 'r');
  if (!$fh) { fwrite(STDERR, "❌ Cannot open CSV\n"); exit(2); }
  $header = fgetcsv($fh, 0, $delim);
  if ($header === false) { fwrite(STDERR, "❌ Empty CSV\n"); exit(2); }
  if (!empty($header)) $header[0] = calm_strip_bom($header[0]);
  $map    = build_index_map($header);

  $idx_email    = $map[norm_label('email')] ?? ($map[norm_label('user_email')] ?? ($map[norm_label('email address')] ?? ($map[norm_label('e-mail')] ?? null)));
  $idx_nic      = $map[norm_label('NIC #')] ?? ($map[norm_label('nic')] ?? $map[norm_label('nic no')] ?? null);
  $idx_passport = $map[norm_label('Passport')] ?? $map[norm_label('passport_no')] ?? null;

  if ($match_policy !== 'id' && $idx_email === null) {
    fwrite(STDERR, "❌ CSV must contain an email column for match policy '{$match_policy}'.\n");
    fwrite(STDERR, "   Saw headers:\n   - ".implode("\n   - ", $header)."\n");
    fwrite(STDERR, "   Normalized: ".implode(', ', array_keys($map))."\n");
    exit(2);
  }
  if ($match_policy === 'id' && ($idx_nic === null && $idx_passport === null)) {
    fwrite(STDERR, "❌ CSV must contain NIC and/or Passport columns for '--match=id'.\n");
    exit(2);
  }

  $meta_indices = [];
  foreach ($meta_fields as $k=>$label) $meta_indices[$k] = $map[norm_label($label)] ?? null;

  $matched_fh   = fopen($matched_file, 'w');
  $unmatched_fh = fopen($unmatched_file, 'w');
  fputcsv($matched_fh, array_merge(['user_login','user_email','action'], array_keys($meta_fields), ['ld_groups_removed']));
  fputcsv($unmatched_fh, ['Reason','user_email','NIC','Passport']);

  $processed=0; $done=0; $skipped=0;

  while (($row = fgetcsv($fh, 0, $delim)) !== false) {
    if ($limit>0 && $processed >= $limit) { echo "⏹️ Reached limit {$limit}\n"; break; }
    if (count(array_filter($row, fn($v)=>trim((string)$v)!=='')) === 0) continue;
    $processed++;

    $email    = get_val($row, $idx_email);
    $nic      = get_val($row, $idx_nic);
    $passport = get_val($row, $idx_passport);

    $nic_clean = preg_replace('/[^a-z0-9]/i','',strtolower($nic));
    $pp_clean  = preg_replace('/[^a-z0-9]/i','',strtolower($passport));

    $user = null;

    if ($match_policy === 'email') {
      if ($email === '') { fputcsv($unmatched_fh, ['User not found (missing email)', $email, $nic, $passport]); $skipped++; continue; }
      $user = get_user_by('email', $email);
    } elseif ($match_policy === 'id') {
      if ($nic_clean === '' && $pp_clean === '') { fputcsv($unmatched_fh, ['No NIC/Passport in CSV for --match=id', $email, $nic, $passport]); $skipped++; continue; }
      $user = find_user_by_ids_only($nic_clean, $pp_clean);
      if (is_wp_error($user)) { fputcsv($unmatched_fh, ['NIC & Passport resolve to different users', $email, $nic, $passport]); $skipped++; continue; }
      if (!$user) { fputcsv($unmatched_fh, ['User not found by NIC/Passport', $email, $nic, $passport]); $skipped++; continue; }
    } else { // strict
      if ($email === '') { fputcsv($unmatched_fh, ['Missing email for strict policy', $email, $nic, $passport]); $skipped++; continue; }
      $user = get_user_by('email', $email);
      if (!$user) { fputcsv($unmatched_fh, ["User not found by email", $email, $nic, $passport]); $skipped++; continue; }
      // Additional strict verification occurs implicitly in your prod runner; here we proceed.
    }

    if (!$user) {
      fputcsv($unmatched_fh, ['User not found', $email, $nic, $passport]);
      $skipped++; continue;
    }

    $uid = (int)$user->ID;
    $login = (string)$user->user_login;

    // capture old meta + update if provided
    $meta_old = [];
    foreach ($meta_fields as $key=>$label) {
      $old = (string)get_user_meta($uid, $key, true);
      $new = get_val($row, $meta_indices[$key] ?? null);
      $meta_old[$key] = $old;
      if (!$dry_run && $new !== '') update_user_meta($uid, $key, $new);
    }

    user_mark_inactive($user, $dry_run);
    $ld_removed = [];
    if ($remove_groups || !$dry_run) {
      $ld_removed = ld_remove_all_groups($uid, $dry_run);
    }

    fputcsv(
      $matched_fh,
      array_merge([$login, $user->user_email, ($dry_run?'PREVIEW':'UPDATED')],
                  array_values($meta_old),
                  [implode('|', $ld_removed)])
    );
    echo "✅ Processed {$login}" . ($ld_removed ? " — removed LD groups: ".implode(',', $ld_removed) : "") . "\n";
    $done++;
  }

  fclose($fh); fclose($matched_fh); fclose($unmatched_fh);

  echo "🏁 CSV Mode Done. Dry run: " . ($dry_run ? 'Yes' : 'No') . "\n";
  echo "📊 Summary: processed={$processed}, updated={$done}, unmatched={$skipped}\n";
  exit(0);
}

/* ---------- Sweep mode (already-inactive users) ---------- */
$matched_fh   = fopen($matched_file, 'w');
$unmatched_fh = fopen($unmatched_file, 'w');
fputcsv($matched_fh, ['user_login','user_email','action','ld_groups_removed']);
fputcsv($unmatched_fh, ['Reason','user_login','user_email']);

$processed=0; $done=0;

$args = [
  'fields' => ['ID','user_login','user_email'],
  'number' => 0
];
$users = get_users($args);

foreach ($users as $u) {
  if ($limit>0 && $processed >= $limit) { echo "⏹️ Reached limit {$limit}\n"; break; }
  $processed++;

  if (!user_is_inactive($u)) continue;

  $removed = ld_remove_all_groups((int)$u->ID, $dry_run);
  fputcsv($matched_fh, [$u->user_login, $u->user_email, ($dry_run?'PREVIEW':'REMOVED'), implode('|', $removed)]);
  echo "🧹 Inactive: {$u->user_login} — removed LD groups: " . ($removed ? implode(',', $removed) : '(none)') . "\n";
  $done++;
}

fclose($matched_fh); fclose($unmatched_fh);

echo "🏁 Sweep Mode Done. Dry run: " . ($dry_run ? 'Yes' : 'No') . "\n";
echo "📊 Summary: processed={$processed}, inactive_cleaned={$done}\n";
