<?php
/**
 * Resigned Processor Launcher — v15.09.0002.0001
 * - Immediate 303 redirect to status.php after spawning a detached background process
 * - Works with batching (batch_runner.php) or single worker
 */

const LAUNCHER_AUTH_TOKEN = '6714e52aed21125dd999ff7c31666c1806e033aa2cb8a14073b41ae7026ec0b0';
const TOOL_DIR = '/srv/admin-tools/wp-resigned-processor';
const LOG_DIR  = '/srv/admin-tools/_mailer-logs';
const PHP_CLI  = '/usr/bin/php';
const WORKER   = TOOL_DIR . '/resigned_worker.php';
const BATCHER  = TOOL_DIR . '/batch_runner.php';
const WP_LOAD  = '/var/www/html/wp-load.php';

function normalize_manual_list(string $raw, int $max = 10): array {
  $lines = preg_split('/\r\n|\r|\n/', trim($raw)) ?: [];
  $out = [];
  foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '') continue;
    $out[] = $line;
    if (count($out) >= $max) break;
  }
  return $out;
}

function bail($code, $msg){
  http_response_code($code);
  header('Content-Type: text/plain; charset=utf-8');
  echo $msg;
  exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') bail(405, 'Method Not Allowed');

$token = trim((string)($_POST['token'] ?? ''));
if (!hash_equals(LAUNCHER_AUTH_TOKEN, $token)) bail(401, 'Unauthorized');

foreach ([WORKER => 'Worker', BATCHER => 'Batch runner', WP_LOAD => 'wp-load.php'] as $p=>$label) {
  if (!is_file($p)) bail(500, "$label missing: $p");
}
if (!is_dir(LOG_DIR))  { @mkdir(LOG_DIR,0755,true); }

$run_id  = gmdate('Ymd_His') . '_' . bin2hex(random_bytes(3));
$run_dir = LOG_DIR . '/resigned_' . $run_id;
@mkdir($run_dir,0755,true);

// ---------- CSV acquisition ----------
$csv_mode = $_POST['csv_mode'] ?? 'server';
$manual_tmp = null;
if ($csv_mode === 'server') {
  $csv_path = trim((string)($_POST['csv_path'] ?? ''));
  if ($csv_path==='' || !is_file($csv_path)) bail(400, 'CSV path invalid');
  $csv = $csv_path;
} elseif ($csv_mode === 'manual') {
  $list = normalize_manual_list((string)($_POST['manual_list'] ?? ''), 10);
  if (empty($list)) bail(400, 'No manual usernames/emails provided (max 10).');
  $manual_tmp = $run_dir . '/input_manual.csv';
  $fh = @fopen($manual_tmp, 'w');
  if (!$fh) bail(500, 'Failed to create temporary CSV');
  fputcsv($fh, ['user_login','user_email']);
  foreach ($list as $item) {
    $isEmail = filter_var($item, FILTER_VALIDATE_EMAIL);
    $login = $isEmail ? '' : $item;
    $email = $isEmail ? $item : '';
    fputcsv($fh, [$login, $email]);
  }
  fclose($fh);
  $csv = $manual_tmp;
} else {
  if (!isset($_FILES['csv_file']) || ($_FILES['csv_file']['error'] ?? 4) !== 0) bail(400, 'No CSV uploaded');
  $dest = $run_dir.'/input.csv';
  if (!@move_uploaded_file($_FILES['csv_file']['tmp_name'], $dest)) bail(500, 'Failed to save upload');
  $csv = $dest;
}

// ---------- Outputs & meta ----------
$matched  = $run_dir.'/matched_done.csv';
$unmatched= $run_dir.'/unmatched_skipped.csv';
$stdout   = $run_dir.'/stdout.log';
@touch($run_dir.'/STARTED');

// ---------- Options ----------
$limit    = max(0, (int)($_POST['limit'] ?? 0));
$live     = !empty($_POST['live']);
$remove_groups    = !empty($_POST['remove_groups']);
$unenroll_courses = !empty($_POST['unenroll_courses']);
$reset_progress   = !empty($_POST['reset_progress']);
$um_inactive      = !empty($_POST['um_inactive']);
$strip_roles      = !empty($_POST['strip_roles']);

$match_mode = strtolower((string)($_POST['match_mode'] ?? 'strict'));
if ($csv_mode === 'manual') $match_mode = 'email'; // manual list supports username/email matching
if (!in_array($match_mode, ['strict','email','id'], true)) $match_mode = 'strict';

$batch_size   = max(0, (int)($_POST['batch_size'] ?? 0));      // 0 = no batching
$batch_delay  = max(0, (int)($_POST['batch_delay_ms'] ?? 0));  // ms

// Common flags that go to worker
$common = [];
$common[] = '--match='.$match_mode;
if ($live)               $common[] = '--live';
if ($limit>0)            $common[] = '--limit='.$limit;
if ($remove_groups)      $common[] = '--remove-groups';
if ($unenroll_courses)   $common[] = '--unenroll-courses';
if ($reset_progress)     $common[] = '--reset-progress';
if ($um_inactive)        $common[] = '--um-inactive';
if ($strip_roles)        $common[] = '--strip-roles';

// ---------- Build final command (DETACHED) ----------
if ($batch_size > 0) {
  // Use batch runner
  $args = [];
  $args[] = '--csv='.$csv;
  $args[] = '--matched='.$matched;
  $args[] = '--unmatched='.$unmatched;
  $args[] = '--batch-size='.$batch_size;
  $args[] = '--batch-delay-ms='.$batch_delay;
  $args[] = '--worker='.WORKER;
  foreach ($common as $c) $args[] = '--pass='.base64_encode($c);

  $cmd = sprintf(
    'cd %s && setsid nohup %s %s %s >> %s 2>&1 < /dev/null & echo $!',
    escapeshellarg(TOOL_DIR),
    escapeshellarg(PHP_CLI),
    escapeshellarg(BATCHER),
    implode(' ', array_map('escapeshellarg',$args)),
    escapeshellarg($stdout)
  );
} else {
  // Single shot worker
  $wargs = [];
  $wargs[] = '--csv='.$csv;
  $wargs[] = '--matched='.$matched;
  $wargs[] = '--unmatched='.$unmatched;
  foreach ($common as $c) $wargs[] = $c;

  $cmd = sprintf(
    'cd %s && setsid nohup %s %s %s >> %s 2>&1 < /dev/null & echo $!',
    escapeshellarg(TOOL_DIR),
    escapeshellarg(PHP_CLI),
    escapeshellarg(WORKER),
    implode(' ', array_map('escapeshellarg',$wargs)),
    escapeshellarg($stdout)
  );
}

// Spawn
$pid = trim((string)shell_exec($cmd)); if (!ctype_digit($pid)) $pid='';

// Persist meta for status screen
file_put_contents($run_dir.'/run.json', json_encode([
  'pid'             => $pid,
  'started_gmt'     => gmdate('c'),
  'stdout'          => $stdout,
  'matched'         => $matched,
  'unmatched'       => $unmatched,
  'csv'             => $csv,
  'batch_size'      => $batch_size,
  'batch_delay_ms'  => $batch_delay,
  'match_mode'      => $match_mode,
  'args'            => $common,        // keep key 'args' for status.php
], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

// ---------- REDIRECT ----------
$location = 'status.php?id=' . rawurlencode(basename($run_dir));
header('Location: '.$location, true, 303);
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><meta charset="utf-8"><title>Launching…</title>';
echo '<p>Launching background job… Redirecting to <a href="'.htmlspecialchars($location).'">status</a>.</p>';
echo '<meta http-equiv="refresh" content="0;url='.htmlspecialchars($location).'">';

if (function_exists('fastcgi_finish_request')) fastcgi_finish_request(); else { @ob_flush(); @flush(); }
exit;
