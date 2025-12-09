<?php
/**
 * Remove Course 3027 — Launcher v1.0.3
 * - Correct DRY-RUN handling
 * - Same behaviour as resigned processor launcher
 */

const LAUNCHER_AUTH_TOKEN = '6714e52aed21125dd999ff7c31666c1806e033aa2cb8a14073b41ae7026ec0b0';

const TOOL_DIR = '/srv/admin-tools/wp-remove-course-3027';
const LOG_DIR  = '/srv/admin-tools/_mailer-logs';
const PHP_CLI  = '/usr/bin/php';

const WORKER  = TOOL_DIR . '/remove_course_3027_worker.php';
const BATCHER = TOOL_DIR . '/batch_runner.php';
const WP_LOAD = '/var/www/html/wp-load.php';

function bail($code, $msg){
  http_response_code($code);
  header('Content-Type: text/plain; charset=utf-8');
  echo $msg;
  exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') bail(405,'Method Not Allowed');

$token = trim((string)($_POST['token'] ?? ''));
if (!hash_equals(LAUNCHER_AUTH_TOKEN, $token)) bail(401,'Unauthorized');

/* Check files */
foreach ([WORKER => 'Worker', BATCHER => 'Batch runner', WP_LOAD => 'wp-load.php'] as $p => $label) {
  if (!is_file($p)) bail(500,"$label missing: $p");
}

if (!is_dir(LOG_DIR)) @mkdir(LOG_DIR,0755,true);

/* Create run folder */
$run_id  = gmdate('Ymd_His') . '_' . bin2hex(random_bytes(3));
$run_dir = LOG_DIR . '/remove3027_' . $run_id;
@mkdir($run_dir,0755,true);

/* CSV acquisition */
$csv_mode = $_POST['csv_mode'] ?? 'server';

if ($csv_mode === 'server') {
  $csv_path = trim((string)($_POST['csv_path'] ?? ''));
  if ($csv_path==='' || !is_file($csv_path)) bail(400,'CSV path invalid');
  $csv = $csv_path;
} else {
  if (!isset($_FILES['csv_file']) || ($_FILES['csv_file']['error'] ?? 4) !== 0) bail(400,'No CSV uploaded');
  $dest = $run_dir.'/input.csv';
  if (!@move_uploaded_file($_FILES['csv_file']['tmp_name'], $dest)) bail(500,'Failed to save upload');
  $csv = $dest;
}

/* Outputs */
$matched   = $run_dir.'/matched_done.csv';
$unmatched = $run_dir.'/unmatched_skipped.csv';
$stdout    = $run_dir.'/stdout.log';
@touch($run_dir.'/STARTED');

/* Options */
$limit      = max(0,(int)($_POST['limit'] ?? 0));
$dry_run    = !empty($_POST['dry_run']);
$live       = !empty($_POST['live']);
$match_mode = strtolower((string)($_POST['match_mode'] ?? 'strict'));
$batch_size = max(0,(int)($_POST['batch_size'] ?? 0));
$batch_delay= max(0,(int)($_POST['batch_delay_ms'] ?? 0));

if (!in_array($match_mode,['strict','email','id'],true)) $match_mode='strict';

/* Flags passed to worker */
$common = [];
$common[] = '--match='.$match_mode;

if ($dry_run && !$live) {
  $common[] = '--dry-run';
}
if ($live) {
  $common[] = '--live';
}
if ($limit > 0) {
  $common[] = '--limit='.$limit;
}

/* Build background command */
if ($batch_size > 0) {

  $args = [];
  $args[]='--csv='.$csv;
  $args[]='--matched='.$matched;
  $args[]='--unmatched='.$unmatched;
  $args[]='--batch-size='.$batch_size;
  $args[]='--batch-delay-ms='.$batch_delay;
  $args[]='--worker='.WORKER;

  foreach ($common as $c) $args[]='--pass='.base64_encode($c);

  $cmd = sprintf(
    'cd %s && setsid nohup %s %s %s >> %s 2>&1 < /dev/null & echo $!',
    escapeshellarg(TOOL_DIR),
    escapeshellarg(PHP_CLI),
    escapeshellarg(BATCHER),
    implode(' ', array_map('escapeshellarg',$args)),
    escapeshellarg($stdout)
  );

} else {

  $wargs=[];
  $wargs[]='--csv='.$csv;
  $wargs[]='--matched='.$matched;
  $wargs[]='--unmatched='.$unmatched;

  foreach ($common as $c) $wargs[]=$c;

  $cmd = sprintf(
    'cd %s && setsid nohup %s %s %s >> %s 2>&1 < /dev/null & echo $!',
    escapeshellarg(TOOL_DIR),
    escapeshellarg(PHP_CLI),
    escapeshellarg(WORKER),
    implode(' ', array_map('escapeshellarg',$wargs)),
    escapeshellarg($stdout)
  );
}

/* Execute */
$pid = trim((string)shell_exec($cmd));
if (!ctype_digit($pid)) $pid='';

/* Save meta */
file_put_contents($run_dir.'/run.json', json_encode([
  'pid'            => $pid,
  'started_gmt'    => gmdate('c'),
  'stdout'         => $stdout,
  'matched'        => $matched,
  'unmatched'      => $unmatched,
  'csv'            => $csv,
  'batch_size'     => $batch_size,
  'batch_delay_ms' => $batch_delay,
  'match_mode'     => $match_mode,
  'args'           => $common
], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

/* Redirect */
$loc = 'status.php?id=' . rawurlencode(basename($run_dir));
header('Location: '.$loc, true, 303);

echo '<!doctype html><meta charset="utf-8"><title>Launching…</title>';
echo '<p>Launching background job… Redirecting to <a href="'.htmlspecialchars($loc).'">status</a>.</p>';
echo '<meta http-equiv="refresh" content="0;url='.htmlspecialchars($loc).'">';
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
exit;