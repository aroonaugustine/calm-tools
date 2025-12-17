<?php
/**
 * Welcome Sender CLI — v15.09.0002.0001
 * Usage:
 *   php welcome_sender.php --csv-file=/path/to.csv [--log-file=/path/to.csv] [--dry-run] [--limit=N]
 *
 * CSV can include ANY of these header variants (case/punctuation-insensitive):
 *   Email:  user_email, email, email address, e-mail
 *   Login:  user_login, username, login, user name
 *   Name:   name, full_name, first_name, first name
 *
 * Changes in v15.09.0002.0001:
 *  - Detects delimiter automatically (comma / tab / semicolon)
 *  - Strips UTF-8 BOM
 *  - Tolerant header matching
 *  - Per-row logging with timestamps
 *  - Touches DONE file in the run folder (derived from --log-file)
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

/* ====== EDIT if WP path differs ====== */
require('/var/www/html/wp-load.php'); // adjust if needed
/* ==================================== */

/* ---------- Helpers ---------- */
function ts(){ return date('H:i:s'); }
function detect_delimiter_from_sample(string $line): string {
  $cands = ["," => 0, "\t" => 0, ";" => 0];
  foreach ($cands as $d => $_) $cands[$d] = substr_count($line, $d);
  arsort($cands);
  $top = array_key_first($cands);
  return $top ?: ",";
}
function strip_bom(string $s): string {
  return preg_replace('/^\xEF\xBB\xBF/u', '', $s);
}
function norm_label(string $s): string {
  // lower + remove all non letters/numbers
  $s = mb_strtolower($s, 'UTF-8');
  return preg_replace('/[^\p{L}\p{N}]+/u', '', $s);
}
function map_indices(array $header): array {
  $map = [];
  foreach ($header as $i => $h) {
    $map[norm_label((string)$h)] = (int)$i;
  }
  return $map;
}
function idx_for(array $idxmap, array $aliases): int {
  foreach ($aliases as $a) {
    $k = norm_label($a);
    if (array_key_exists($k, $idxmap)) return (int)$idxmap[$k];
  }
  return -1;
}
function first_token(string $s): string {
  $s = trim($s);
  if ($s === '') return '';
  $parts = preg_split('/\s+/', $s);
  return $parts[0] ?? $s;
}
function run_dir_from_logfile(string $logFile): string {
  // e.g. /srv/.../_mailer-logs/welcome_20250921_125629_53b3f7/results.csv → dirname(dirname(logFile))
  return dirname($logFile);
}

/* ---------- Args ---------- */
$argv_assoc = [];
foreach ($argv ?? [] as $arg) {
  if (preg_match('/^--([^=]+)=(.*)$/', $arg, $m)) $argv_assoc[$m[1]] = $m[2];
  elseif (preg_match('/^--(.+)$/', $arg, $m))     $argv_assoc[$m[1]] = true;
}
$csvFile = (string)($argv_assoc['csv-file'] ?? '');
$logFile = (string)($argv_assoc['log-file'] ?? '');
$dryRun  = !empty($argv_assoc['dry-run']);
$limit   = isset($argv_assoc['limit']) ? max(0, (int)$argv_assoc['limit']) : 0;

if ($csvFile === '' || !is_file($csvFile)) { fwrite(STDERR, "[".ts()."] ❌ CSV not found: $csvFile\n"); exit(2); }
if ($logFile === '') $logFile = sprintf('/srv/admin-tools/_mailer-logs/welcome_standalone_%s.csv', date('Ymd-His'));

// Mail identity
$FROM_NAME  = 'CALM Team';
$FROM_EMAIL = 'calm@portcitybpo.lk';
$SUBJECT    = 'Welcome to CALM';

// Mail setup
add_filter('wp_mail_content_type', function(){ return 'text/html; charset=UTF-8'; });
add_filter('wp_mail_from',        function() use ($FROM_EMAIL){ return $FROM_EMAIL; });
add_filter('wp_mail_from_name',   function() use ($FROM_NAME){  return $FROM_NAME; });

// HTML template
$HTML_TEMPLATE = <<<HTML
<div style="font-family: Helvetica, Arial, sans-serif; color: #333; line-height: 1.6; max-width: 800px; width: 100%; margin: auto; padding: 30px 20px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); background: #ffffff;">
  <div style="text-align: center; margin-bottom: 30px;">
    <img style="max-width: 100px; width: 100%; height: auto; display: block; margin: 0 auto; border-radius: 12px;" src="https://portcalm.portcitybpo.lk/wp-content/uploads/2025/03/Port-City-BPO-logo-hd-1.webp" alt="Port City BPO" />
  </div>
  <h2 style="text-align: center; color: #004b6b; margin-bottom: 20px;">Welcome to CALM</h2>
  Dear <strong>%FirstName%</strong>,<br>
  <p>Your access to the <strong>CALM Platform</strong> (Compliance And Learning Management) has been set up.</p>
  <div style="margin: 18px 0; padding: 16px; background: #f0f8ff; border-left: 4px solid #0077b6;">
    <strong>Login email:</strong> %Email%
  </div>
  <div style="margin: 18px 0; padding: 16px; background: #fdf4e3; border-left: 4px solid #b65b00;">
    You can change your password after logging in
    <a href="https://portcalm.portcitybpo.lk/account/password/" target="_blank" rel="noopener">here</a>.<br>
    Forgot your password? Reset it
    <a href="%ResetLink%" target="_blank" rel="noopener">here</a>.
  </div>
  <h3 style="margin-top: 18px; color: #004b6b;">Getting Started</h3>
  <ul style="padding-left: 20px;">
    <li>Review your assigned Courses in the Courses menu</li>
    <li>Explore the various resources on the CALM Platform</li>
  </ul>
  Quick Start Guide:
  <a href="https://portcalm.portcitybpo.lk/get-started/" target="_blank" rel="noopener">Get Started</a>
  <p style="margin-top: 16px;">Need help?</p>
  <ul style="list-style: none; padding-left: 0;">
    <li><strong>Support:</strong> <a href="mailto:calm@portcitybpo.lk">calm@portcitybpo.lk</a></li>
    <li><strong>Help Center:</strong> <a href="https://portcalm.portcitybpo.lk/faqs/" target="_blank" rel="noopener">CALM FAQs</a></li>
  </ul>
  <p style="margin-top: 24px;">Warm regards,</p>
  <strong>The CALM Team</strong>
</div>
HTML;

// Open CSV with delimiter detection + BOM strip
$fh = fopen($csvFile, 'r');
if (!$fh) { fwrite(STDERR, "[".ts()."] ❌ Cannot open CSV\n"); exit(2); }

$firstLineRaw = fgets($fh);
if ($firstLineRaw === false) { fwrite(STDERR, "[".ts()."] ❌ Empty CSV\n"); exit(2); }
$firstLine = strip_bom($firstLineRaw);
$delim = detect_delimiter_from_sample($firstLine);
$header = str_getcsv($firstLine, $delim);
if (!$header || count($header) === 0) { fwrite(STDERR, "[".ts()."] ❌ Could not parse header\n"); exit(2); }
$idxmap = map_indices($header);

// tolerant header indices
$email_idx = idx_for($idxmap, ['user_email','email','email address','e-mail']);
$login_idx = idx_for($idxmap, ['user_login','username','login','user name']);
$name_idx  = idx_for($idxmap, ['name','full_name','first_name','first name']);

if ($email_idx < 0 && $login_idx < 0) {
  fwrite(STDERR, "[".ts()."] ❌ CSV must include either an email column or a login column.\n");
  exit(2);
}

// Open LOG CSV
$lf = fopen($logFile, 'w');
if (!$lf) { fwrite(STDERR, "[".ts()."] ❌ Cannot open log file for writing: $logFile\n"); exit(2); }
fputcsv($lf, ['lookup_value','lookup_via','user_login','user_email','status','note']);

// Echo start
echo "[".ts()."] ".($dryRun ? "👀 Preview only (no emails)" : "🚀 Sending…")."\n";
echo "[".ts()."] CSV: $csvFile | LOG: $logFile | Delimiter: ".($delim==="\t"?"TAB":$delim)."\n";

$sent=0; $skipped=0; $errors=0; $processed=0;

while (($row = fgetcsv($fh, 0, $delim)) !== false) {
  // skip fully empty rows
  $nonEmpty = false; foreach ($row as $cell){ if (trim((string)$cell) !== '') { $nonEmpty=true; break; } }
  if (!$nonEmpty) continue;

  $processed++;
  if ($limit>0 && $processed>$limit) { echo "[".ts()."] ⏹️ Reached limit {$limit}\n"; break; }

  $lookupVia=''; $lookupValue=''; $user=null; $email='';

  // Prefer email lookup when present
  if ($email_idx >= 0) {
    $email = strtolower(trim((string)($row[$email_idx] ?? '')));
    if ($email !== '') {
      $user = get_user_by('email', $email);
      $lookupVia='email';
      $lookupValue=$email;
    }
  }
  if (!$user && $login_idx >= 0) {
    $login = trim((string)($row[$login_idx] ?? ''));
    if ($login !== '') {
      $user = get_user_by('login', $login);
      $lookupVia='login';
      $lookupValue=$login;
    }
  }

  if (!$user) {
    $hint = $lookupVia ?: ($email_idx>=0 ? 'email' : 'login');
    echo "[".ts()."] ❌ Not found for {$hint}: ".($lookupValue ?: '(blank)')."\n";
    fputcsv($lf, [$lookupValue, $hint, '', '', 'SKIPPED', 'not found']);
    $skipped++;
    continue;
  }

  // Derive email/login
  $email = strtolower(trim((string)$user->user_email));
  $ulogin = (string)$user->user_login;

  // Greeting name: CSV name first token > WP first_name > display_name > login
  $csvName = ($name_idx >= 0) ? trim((string)($row[$name_idx] ?? '')) : '';
  $firstName = $csvName !== '' ? first_token($csvName)
                               : ((string)get_user_meta($user->ID,'first_name',true) ?: ($user->display_name ?: $ulogin));

  // Password reset link
  if (!function_exists('get_password_reset_key')) require_once ABSPATH . WPINC . '/pluggable.php';
  $key = get_password_reset_key($user);
  if (is_wp_error($key)) {
    $msg = $key->get_error_message();
    echo "[".ts()."] ⚠️ Cannot create reset key for {$ulogin} <{$email}>: {$msg}\n";
    fputcsv($lf, [$lookupValue, $lookupVia, $ulogin, $email, 'ERROR', 'reset key']);
    $errors++; continue;
  }
  $reset_link = network_site_url('wp-login.php?action=rp&key='.rawurlencode($key).'&login='.rawurlencode($ulogin), 'login');

  // Build HTML
  $repl = [
    '%FirstName%' => esc_html($firstName),
    '%Email%'     => esc_html($email),
    '%ResetLink%' => esc_url($reset_link),
  ];
  $html = strtr($HTML_TEMPLATE, $repl);

  if ($dryRun) {
    echo "[".ts()."] 👁️ PREVIEW: {$ulogin} <{$email}> — First='{$firstName}'\n";
    echo "           Reset: {$reset_link}\n";
    fputcsv($lf, [$lookupValue, $lookupVia, $ulogin, $email, 'PREVIEW', '']);
  } else {
    $ok = wp_mail($email, $SUBJECT, $html);
    if ($ok) {
      echo "[".ts()."] ✅ SENT: {$ulogin} <{$email}>\n";
      fputcsv($lf, [$lookupValue, $lookupVia, $ulogin, $email, 'SENT', '']);
      $sent++;
    } else {
      echo "[".ts()."] ⚠️ wp_mail FAILED: {$ulogin} <{$email}>\n";
      fputcsv($lf, [$lookupValue, $lookupVia, $ulogin, $email, 'ERROR', 'wp_mail failed']);
      $errors++;
    }
  }
}

fclose($fh);
fclose($lf);

// Touch DONE marker in the run folder (if logFile is inside it)
$run_dir = run_dir_from_logfile($logFile);
@touch($run_dir . '/DONE');

echo "[".ts()."] 📄 Log: $logFile\n";
echo "[".ts()."] 📊 Summary: processed=$processed, sent=$sent, skipped=$skipped, errors=$errors\n";
