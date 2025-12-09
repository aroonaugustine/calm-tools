<?php
/**
 * send_never_logged_in-7.php — v2.6.3-calmweb
 *
 * Fix: The “Please log in and complete…” paragraph now appears ONLY for INCOMPLETE users.
 *      It has been removed from the common footer and placed in the INCOMPLETE block.
 *
 * What’s in this build:
 * - Web actions: ?action=self_test and ?action=send_one&user=...
 * - Non-expiring “View in browser” links for 5 languages (en, zh, id, vi, si)
 * - Robust mail diagnostics (wp_mail_failed + PHPMailer debug)
 * - Optional --log-file=... to control CSV output path
 * - %COURSE_LIST% only appears in one wrapper and is always replaced
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

/* ===== WordPress bootstrap (adjust if needed) ===== */
require('/var/www/html/wp-load.php');

/* ===== Mail diagnostics ===== */
if (!defined('CALM_MAIL_DEBUG')) define('CALM_MAIL_DEBUG', true);

if (CALM_MAIL_DEBUG) {
  add_action('wp_mail_failed', function($wp_error){
    $msg = 'wp_mail_failed: ' . (is_wp_error($wp_error) ? $wp_error->get_error_message() : 'unknown');
    $data = is_wp_error($wp_error) ? $wp_error->get_error_data() : null;
    if ($data) $msg .= ' | data=' . json_encode($data);
    echo "⚠️  {$msg}\n";
    error_log($msg);
  });

  add_action('phpmailer_init', function($phpmailer){
    $phpmailer->SMTPDebug   = 2; // 0=off, 1=client msgs, 2=client+server
    $phpmailer->Debugoutput = function($str,$level){ echo "PHPMailer[$level]: $str\n"; };
  });
}

define('CALM_MAILER_VERSION','v2.6.3-calmweb');

/* ===== Parse CLI args; fall back to GET in web mode ===== */
$argv_assoc = [];
if (php_sapi_name()==='cli' && isset($argv) && is_array($argv)) {
  foreach ($argv as $arg) {
    if (preg_match('/^--([^=]+)=(.*)$/', $arg, $m)) $argv_assoc[$m[1]] = $m[2];
    elseif (preg_match('/^--(.+)$/', $arg, $m))     $argv_assoc[$m[1]] = true;
  }
} else {
  if (isset($_GET['campaign']))          $argv_assoc['campaign']   = (string)$_GET['campaign'];
  if (!empty($_GET['live']))             $argv_assoc['live']       = true; else $argv_assoc['dry-run'] = true;
  if (!empty($_GET['action']))           $argv_assoc['web-action'] = $_GET['action'];
  if (!empty($_GET['user']))             $argv_assoc['web-user']   = $_GET['user'];
  if (!empty($_GET['limit']))            $argv_assoc['limit']      = (int)$_GET['limit'];
  if (!empty($_GET['only_role']))        $argv_assoc['only-role']  = (string)$_GET['only_role'];
  if (!empty($_GET['batch_size']))       $argv_assoc['batch-size'] = (int)$_GET['batch_size'];
  if (!empty($_GET['batch_sleep']))      $argv_assoc['batch-sleep']= (int)$_GET['batch_sleep'];
}

$dryRun     = !empty($argv_assoc['dry-run']) && empty($argv_assoc['live']);
$limit      = isset($argv_assoc['limit'])       ? max(0,(int)$argv_assoc['limit'])       : 0;
$onlyRole   = isset($argv_assoc['only-role'])   ? (string)$argv_assoc['only-role']       : null;
$selfTest   = !empty($argv_assoc['self-test']) || (!empty($argv_assoc['web-action']) && $argv_assoc['web-action']==='self_test');
$batchSize  = isset($argv_assoc['batch-size'])  ? max(1,(int)$argv_assoc['batch-size'])  : 50;
$batchSleep = isset($argv_assoc['batch-sleep']) ? max(0,(int)$argv_assoc['batch-sleep']) : 2;
$campaign   = isset($argv_assoc['campaign']) ? preg_replace('/[^A-Za-z0-9_\-\.]/','',$argv_assoc['campaign']) : date('Ymd');
$logFileArg = isset($argv_assoc['log-file']) ? (string)$argv_assoc['log-file'] : '';

/* ===== Config ===== */
$FROM_NAME  = 'CALM Team';
$FROM_EMAIL = 'calm@portcitybpo.lk';
$SUBJECT    = 'CALM | Your course progress & next steps';

$ALLOWED_ROLES    = array('subscriber');        // restrict or []
$COURSE_WHITELIST = array(2871,4216,2869,3027); // or [] to include all enrollments

$LOG_FILE = $logFileArg !== '' ? $logFileArg
          : sprintf('/srv/admin-tools/wp-mailer/_mailer-logs/standalone_%s.csv', date('Ymd-His'));
@mkdir(dirname($LOG_FILE), 0755, true);

/* ===== Self-test routing ===== */
$SELFTEST_MAP = array(
  'aa'       => array('to' => 'aroonaugustine+complete@gmail.com',   'expect' => 'complete'),
  'gary7497' => array('to' => 'aroonaugustine+incomplete@gmail.com', 'expect' => 'incomplete'),
);

/* ===== “View in browser” links (5 languages) ===== */
function calm_view_links_assoc($user_login, $campaign){
  $base = site_url('/wp-mailer/view.php'); // public path you exposed
  $langs = ['en','zh','id','vi','si'];
  $out = [];
  foreach ($langs as $code) {
    $out[$code] = add_query_arg(['login'=>$user_login, 'campaign'=>$campaign, 'lang'=>$code], $base);
  }
  return $out; // assoc: ['en'=>url, 'zh'=>url, 'id'=>url, 'vi'=>url, 'si'=>url]
}

/* ===== Email templates (English; other languages via browser links) ===== */
$HTML_SHELL_TOP = <<<HTML
<div style="font-family: Helvetica, Arial, sans-serif; color:#333; line-height:1.6; max-width:800px; margin:auto; padding:30px 20px; border:1px solid #ddd; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,.05); background:#fff;">
  <div style="text-align:center; margin-bottom:24px;">
    <img style="max-width:100px; width:100%; height:auto; display:block; margin:0 auto; border-radius:12px;"
         src="https://portcalm.portcitybpo.lk/wp-content/uploads/2025/03/Port-City-BPO-logo-hd-1.webp"
         alt="Port City BPO" />
  </div>
  <div style="text-align:center; font-size:13px; color:#666; margin:-10px 0 18px 0;">
    View in browser:
    <a href="%VIEW_EN%" target="_blank" rel="noopener">English</a> ·
    <a href="%VIEW_ZH%" target="_blank" rel="noopener">中文</a> ·
    <a href="%VIEW_ID%" target="_blank" rel="noopener">Bahasa</a> ·
    <a href="%VIEW_VI%" target="_blank" rel="noopener">Tiếng Việt</a> ·
    <a href="%VIEW_SI%" target="_blank" rel="noopener">සිංහල</a>
  </div>
  <h2 style="text-align:center; color:#004b6b; margin-bottom:12px;">CALM – Your Course Progress</h2>
  <p>Dear <strong>%FIRST_NAME%</strong>,</p>
HTML;

/* COMPLETE intro (no course list; that’s inserted by the wrapper) */
$HTML_BLOCK_CONGRATS = <<<HTML
  <p>Fantastic news — you’ve completed all your assigned compliance courses on the <strong>CALM Platform</strong>!</p>
HTML;

/* INCOMPLETE intro (no course list here either) */
$HTML_BLOCK_INCOMPLETE_INTRO = <<<HTML
  <p>We’d like to share your current progress on the <strong>CALM Platform</strong> (Compliance And Learning Management). Completing these courses is <strong>mandatory</strong> to stay aligned with company standards.</p>
  <div style="margin: 18px 0; padding: 16px; background:#f0f8ff; border-left:4px solid #0077b6;">
    <p style="margin:0 0 8px 0;"><strong>Login email:</strong> %EMAIL%</p>
    <p style="margin:0;">If you need to reset your password, please use this link:<br>
      <a href="%RESET_LINK%" target="_blank" rel="noopener">%RESET_LINK%</a>
    </p>
  </div>
HTML;

/* Single, centralized place for the course list placeholder */
$HTML_COURSE_LIST_WRAPPER = <<<HTML
  <h3 style="color:#004b6b; margin-top:18px;">Your Course Progress</h3>
  %COURSE_LIST%
HTML;

/* This paragraph must appear ONLY for INCOMPLETE users */
$HTML_INCOMPLETE_NOTE = <<<HTML
  <p>Please log in and complete any outstanding modules at your earliest convenience. The platform includes training modules, policy documents, and progress tracking to support your development.</p>
HTML;

/* Common footer (safe for both COMPLETE and INCOMPLETE) — NO reminder text here */
$HTML_COMMON_FOOTER = <<<HTML
  <h3 style="color:#004b6b; margin-top:18px;">Helpful Links</h3>
  <ul>
    <li>Quick Start Guide: <a href="https://portcalm.portcitybpo.lk/get-started/" target="_blank" rel="noopener">Get Started</a></li>
    <li>Change Password: <a href="https://portcalm.portcitybpo.lk/account/password/" target="_blank" rel="noopener">Change Password</a></li>
    <li>Forgot Password: <a href="https://portcalm.portcitybpo.lk/en/password-reset/" target="_blank" rel="noopener">Password Reset</a></li>
  </ul>
  <p>If you require any assistance:</p>
  <ul style="list-style:none; padding-left:0;">
    <li><strong>Contact Support:</strong> <a href="mailto:calm@portcitybpo.lk">calm@portcitybpo.lk</a></li>
    <li><strong>Help Center:</strong> <a href="https://portcalm.portcitybpo.lk/faqs/" target="_blank" rel="noopener">CALM FAQs</a></li>
  </ul>
  <p style="margin-top:18px;">Warm regards,</p>
  <strong>The CALM Team</strong>
</div>
HTML;

/* ===== Mail setup ===== */
add_filter('wp_mail_content_type', function(){ return 'text/html; charset=UTF-8'; });
add_filter('wp_mail_from',        function() use ($FROM_EMAIL){ return $FROM_EMAIL; });
add_filter('wp_mail_from_name',   function() use ($FROM_NAME){  return $FROM_NAME; });

/* ===== LearnDash helpers ===== */
function ld_user_course_ids($user_id){
  $ids = [];
  if (function_exists('learndash_user_get_enrolled_courses')) $ids = (array)learndash_user_get_enrolled_courses($user_id);
  elseif (function_exists('ld_get_mycourses'))                $ids = (array)ld_get_mycourses($user_id);
  if (function_exists('learndash_get_users_group_ids') && function_exists('learndash_group_enrolled_courses')) {
    $group_ids = (array)learndash_get_users_group_ids($user_id);
    foreach ($group_ids as $gid) { $g=(array)learndash_group_enrolled_courses($gid); $ids=array_merge($ids,$g); }
  }
  $ids = array_values(array_unique(array_map('intval',$ids)));
  sort($ids,SORT_NUMERIC);
  return $ids;
}
function ld_course_is_completed($user_id,$course_id){
  if (function_exists('learndash_course_completed') && learndash_course_completed($user_id,$course_id)) return true;
  $meta = get_user_meta($user_id,'course_completed_'.$course_id,true);
  if (!empty($meta)) return true;
  if (function_exists('learndash_course_status')) {
    $status = learndash_course_status($course_id,$user_id);
    if (is_string($status) && stripos($status,'Completed')!==false) return true;
  }
  return false;
}
function ld_course_percent($user_id,$course_id){
  if (ld_course_is_completed($user_id,$course_id)) return 100;
  if (function_exists('learndash_course_progress')) {
    $p = learndash_course_progress(['user_id'=>$user_id,'course_id'=>$course_id,'array'=>true]);
    if (is_array($p) && isset($p['percentage'])) {
      $perc = is_numeric($p['percentage']) ? (float)$p['percentage'] : (float)str_replace('%','',(string)$p['percentage']);
      return (int)max(0,min(100,round($perc)));
    }
    if (is_array($p) && isset($p['completed'],$p['total']) && (int)$p['total']>0) {
      $perc = ((int)$p['completed']/(int)$p['total'])*100.0;
      return (int)max(0,min(100,round($perc)));
    }
  }
  return 0;
}
function build_reset_link_for_user($user){
  if (!function_exists('get_password_reset_key')) require_once ABSPATH.WPINC.'/pluggable.php';
  $key = get_password_reset_key($user);
  return network_site_url('wp-login.php?action=rp&key='.rawurlencode($key).'&login='.rawurlencode($user->user_login),'login');
}
function generic_reset_page(){ return 'https://portcalm.portcitybpo.lk/en/password-reset/'; }
function ld_user_group_names($user_id){
  if (!function_exists('learndash_get_users_group_ids')) return '';
  $ids = (array)learndash_get_users_group_ids($user_id);
  if (!$ids) return '';
  $names=[]; foreach($ids as $gid){ $t=get_the_title($gid); if ($t) $names[]=$t; }
  return implode('; ',$names);
}
function calm_user_progress_payload($u,$COURSE_WHITELIST,$all_course_ids,$course_titles){
  $user_course_ids = ld_user_course_ids($u->ID);
  $consider_ids = !empty($COURSE_WHITELIST) ? array_values(array_intersect($user_course_ids,$COURSE_WHITELIST)) : $user_course_ids;
  $pct_map=[]; $any_incomplete=false;
  foreach ($all_course_ids as $cid){
    if (in_array($cid,$consider_ids,true)){
      $pct = ld_course_percent($u->ID,$cid);
      if ($pct < 100) $any_incomplete=true;
      $pct_map[$cid] = $pct;
    } else $pct_map[$cid] = '';
  }
  $list=''; foreach ($consider_ids as $cid){
    $title = $course_titles[$cid] ?? ("Course {$cid}");
    $pct   = $pct_map[$cid] === '' ? 0 : (int)$pct_map[$cid];
    $list .= '<li>'.esc_html($title).' – '.intval($pct).'%</li>';
  }
  $course_list_html = '<ul style="padding-left:20px; margin-top:8px;">'.$list.'</ul>';
  return [$consider_ids,$pct_map,$any_incomplete,$course_list_html];
}
function calm_first_name($u){
  $fn=(string)get_user_meta($u->ID,'first_name',true);
  if ($fn==='') $fn=$u->display_name?:$u->user_login;
  return $fn;
}

/* ===== Start ===== */
echo "CALM Mailer ".CALM_MAILER_VERSION."\n";
echo $dryRun ? "👀 DRY RUN: No emails will be sent.\n" : "🚀 LIVE RUN: Emails WILL be sent.\n";
echo "Campaign: {$campaign}\n";
echo "Batching: size={$batchSize}, sleep={$batchSleep}s\n";

/* ===== Build global course universe ===== */
$users_for_universe = [];
if (!$selfTest && (empty($argv_assoc['web-action']) || $argv_assoc['web-action']!=='send_one')) {
  $args = ['fields'=>['ID','user_login','user_email','display_name'],'number'=>0];
  if ($onlyRole) $args['role']=$onlyRole;
  if (!empty($ALLOWED_ROLES)) $args['role__in']=$ALLOWED_ROLES;
  $users_for_universe = get_users($args);
}

$all_course_ids = !empty($COURSE_WHITELIST) ? array_values(array_unique(array_map('intval',$COURSE_WHITELIST))) : [];
if (empty($all_course_ids)){
  $set=[]; foreach ($users_for_universe as $u0){ foreach (ld_user_course_ids($u0->ID) as $cid){ $set[$cid]=true; } }
  $all_course_ids=array_keys($set); sort($all_course_ids,SORT_NUMERIC);
}
$course_titles=[]; foreach ($all_course_ids as $cid){ $t=get_the_title($cid); $course_titles[$cid]=preg_replace('/\s+/',' ',trim($t?:("Course {$cid}"))); }

/* ===== Helper to build a complete email body for a user ===== */
function build_email_for_user($u, $campaign, $course_titles, $all_course_ids, $COURSE_WHITELIST, $dryRun){
  global $HTML_SHELL_TOP, $HTML_BLOCK_CONGRATS, $HTML_BLOCK_INCOMPLETE_INTRO, $HTML_COURSE_LIST_WRAPPER, $HTML_INCOMPLETE_NOTE, $HTML_COMMON_FOOTER;

  [$consider_ids,$pct_map,$any_incomplete,$course_list_html] = calm_user_progress_payload($u,$COURSE_WHITELIST,$all_course_ids,$course_titles);

  $email = trim((string)$u->user_email);
  $firstName = calm_first_name($u);

  // View-in-browser links
  $links = calm_view_links_assoc($u->user_login, $campaign);
  $shell = strtr($HTML_SHELL_TOP, [
    '%VIEW_EN%' => esc_url($links['en']),
    '%VIEW_ZH%' => esc_url($links['zh']),
    '%VIEW_ID%' => esc_url($links['id']),
    '%VIEW_VI%' => esc_url($links['vi']),
    '%VIEW_SI%' => esc_url($links['si']),
    '%FIRST_NAME%' => esc_html($firstName),
  ]);

  if ($any_incomplete){
    $resetLink = $dryRun ? generic_reset_page() : build_reset_link_for_user($u);
    $body = strtr($HTML_BLOCK_INCOMPLETE_INTRO, [
              '%EMAIL%'      => esc_html($email),
              '%RESET_LINK%' => esc_url($resetLink),
            ])
          . str_replace('%COURSE_LIST%', $course_list_html, $HTML_COURSE_LIST_WRAPPER)
          . $HTML_INCOMPLETE_NOTE;  // <— ONLY for incomplete
  } else {
    $body = $HTML_BLOCK_CONGRATS
          . str_replace('%COURSE_LIST%', $course_list_html, $HTML_COURSE_LIST_WRAPPER);
    // No $HTML_INCOMPLETE_NOTE here
  }

  return $shell . $body . $HTML_COMMON_FOOTER;
}

/* ===== Self-test (aa + gary7497) ===== */
if ($selfTest){
  echo "🧪 SELF-TEST\n";
  $users_needed=[]; foreach (['aa','gary7497'] as $login){ $u=get_user_by('login',$login); if($u) $users_needed[$login]=$u; }

  if (empty($all_course_ids)){
    $set=[]; foreach($users_needed as $uX){ foreach(ld_user_course_ids($uX->ID) as $cid){ $set[$cid]=true; } }
    $all_course_ids=array_keys($set); sort($all_course_ids,SORT_NUMERIC);
    $course_titles=[]; foreach ($all_course_ids as $cid){ $t=get_the_title($cid); $course_titles[$cid]=preg_replace('/\s+/',' ',trim($t?:("Course {$cid}"))); }
  }

  foreach ($SELFTEST_MAP as $login=>$meta){
    $to = $meta['to'] ?? '';
    $u  = $users_needed[$login] ?? null;
    if (!$u){ echo "⚠️ {$login} not found\n"; continue; }

    $html = build_email_for_user($u, $campaign, $course_titles, $all_course_ids, $COURSE_WHITELIST, $dryRun);
    $subject = ($meta['expect']==='incomplete' ? '[SELF-TEST – INCOMPLETE] ' : '[SELF-TEST – COMPLETE] ') . $SUBJECT;

    if ($dryRun){ echo "👁️ PREVIEW {$login} → {$to} ({$meta['expect']})\n"; }
    else { $ok=wp_mail($to,$subject,$html); echo $ok?"✉️  SENT {$login}\n":"⚠️ SEND FAIL {$login}\n"; }
  }
  echo "🧪 Self-test complete.\n";
  exit(0);
}

/* ===== Send One (web mode) ===== */
if (!empty($argv_assoc['web-action']) && $argv_assoc['web-action']==='send_one'){
  $idOrLogin = trim((string)($argv_assoc['web-user'] ?? ''));
  $u = ctype_digit($idOrLogin) ? get_user_by('id',(int)$idOrLogin) : get_user_by('login',$idOrLogin);
  if (!$u){ echo "⚠️ user not found\n"; exit(1); }

  if (empty($all_course_ids)){
    $all_course_ids = ld_user_course_ids($u->ID);
    $course_titles=[]; foreach ($all_course_ids as $cid){ $t=get_the_title($cid); $course_titles[$cid]=preg_replace('/\s+/',' ',trim($t?:("Course {$cid}"))); }
  }

  $email = trim((string)$u->user_email);
  $html = build_email_for_user($u, $campaign, $course_titles, $all_course_ids, $COURSE_WHITELIST, $dryRun);

  if ($dryRun){ header('Content-Type: text/html; charset=utf-8'); echo $html; }
  else { $ok=wp_mail($email,$SUBJECT,$html); echo $ok?"SENT\n":"SEND FAIL\n"; }
  exit(0);
}

/* ===== Normal full run (resume-safe) ===== */
$args = ['fields'=>['ID','user_login','user_email','display_name'],'number'=>0];
if ($onlyRole) $args['role']=$onlyRole;
if (!empty($ALLOWED_ROLES)) $args['role__in']=$ALLOWED_ROLES;
$users = get_users($args);

/* open CSV */
$lf = fopen($LOG_FILE,'w');
$header=['user_login','user_email','company','division','groups'];

/* If you want the CSV to always have the same columns, keep $all_course_ids fixed.
   Otherwise this picks up titles dynamically for the universe. */
foreach($all_course_ids as $cid) $header[] = $course_titles[$cid].' %';
$header = array_merge($header,['incomplete_any','status','note']);
fputcsv($lf,$header);

$sent=0; $skipped=0; $errors=0; $processed=0; $batch_sent=0;
$meta_key_sent = 'calm_mail_sent_'.$campaign;

foreach ($users as $u){
  $processed++; if ($limit>0 && $processed>$limit){ echo "⏹️ limit {$limit}\n"; break; }
  if (!$dryRun && get_user_meta($u->ID,$meta_key_sent,true)){ echo "⏭️ Skip {$u->user_login} — already sent {$campaign}\n"; $skipped++; continue; }

  $email    = trim((string)$u->user_email);
  $company  = (string)get_user_meta($u->ID,'company',true);
  $division = (string)get_user_meta($u->ID,'division',true);
  $groups   = ld_user_group_names($u->ID);

  [$consider_ids,$pct_map,$any_incomplete,$course_list_html] = calm_user_progress_payload($u,$COURSE_WHITELIST,$all_course_ids,$course_titles);

  $row = [$u->user_login, ($email===''?'(no email)':$email), $company, $division, $groups];
  foreach ($all_course_ids as $cid) $row[] = $pct_map[$cid];

  if ($email===''){ fputcsv($lf, array_merge($row,['NO','SKIPPED','No email'])); $skipped++; continue; }
  if (empty($consider_ids)){ fputcsv($lf, array_merge($row,['NO','SKIPPED','No assigned courses'])); $skipped++; continue; }

  $html = build_email_for_user($u, $campaign, $course_titles, $all_course_ids, $COURSE_WHITELIST, $dryRun);

  if ($dryRun){
    $note = $any_incomplete ? 'Preview: Reminder' : 'Preview: Congratulations';
    fputcsv($lf, array_merge($row,[$any_incomplete?'YES':'NO','PREVIEW',$note]));
    echo "👁️ PREVIEW {$u->user_login} <{$email}> — {$note}\n";
  } else {
    $ok = wp_mail($email,$SUBJECT,$html);
    if ($ok){
      update_user_meta($u->ID,$meta_key_sent,1);
      $note = $any_incomplete ? 'Reminder email' : 'Congratulations email';
      fputcsv($lf, array_merge($row,[$any_incomplete?'YES':'NO','SENT',$note]));
      echo "✉️  SENT {$u->user_login} <{$email}>\n";
      $sent++; $batch_sent++;
    } else {
      fputcsv($lf, array_merge($row,[$any_incomplete?'YES':'NO','ERROR','wp_mail failed']));
      echo "⚠️ wp_mail FAILED {$u->user_login} <{$email}>\n";
      $errors++;
    }
    if ($batch_sent >= $batchSize){ echo "⏸ sleep {$batchSleep}s\n"; sleep($batchSleep); $batch_sent=0; }
  }
}

/* Close */
fclose($lf);
echo "📄 Log: {$LOG_FILE}\n";
echo "📊 Summary: processed={$processed}, sent={$sent}, skipped={$skipped}, errors={$errors}\n";
echo "✅ Done. Re-run with same --campaign to resume.\n";