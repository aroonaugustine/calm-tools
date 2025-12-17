<?php
/**
 * CALM Mailer — Launcher v15.09.0002.0001
 * Spawns send_never_logged_in-7.php in background, writes a run folder with live stdout + JSON.
 */
const AUTH_TOKEN = '6714e52aed21125dd999ff7c31666c1806e033aa2cb8a14073b41ae7026ec0b0';
const TOOL_DIR   = '/srv/admin-tools/wp-mailer';
const LOG_DIR    = '/srv/admin-tools/wp-mailer/_mailer-logs';
const PHP_CLI    = '/usr/bin/php';
const SCRIPT     = TOOL_DIR . '/send_never_logged_in-7.php';
const WP_LOAD    = '/var/www/html/wp-load.php'; // existence check only

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo "Method Not Allowed"; exit; }
if (!hash_equals(AUTH_TOKEN, (string)($_POST['token'] ?? ''))) { http_response_code(401); echo "Unauthorized"; exit; }

if (!is_file(SCRIPT))  { http_response_code(500); echo "Script missing"; exit; }
if (!is_file(WP_LOAD)) { http_response_code(500); echo "wp-load.php missing"; exit; }
if (!is_dir(LOG_DIR))  { @mkdir(LOG_DIR, 0755, true); }

$run_id  = gmdate('Ymd_His') . '_' . bin2hex(random_bytes(3));
$run_dir = LOG_DIR . '/run_' . $run_id;
@mkdir($run_dir, 0755, true);

$campaign    = preg_replace('/[^A-Za-z0-9_.-]/','', (string)($_POST['campaign'] ?? ''));
$limit       = max(0, (int)($_POST['limit'] ?? 0));
$only_role   = trim((string)($_POST['only_role'] ?? ''));
$batch_size  = max(1, (int)($_POST['batch_size'] ?? 50));
$batch_sleep = max(0, (int)($_POST['batch_sleep'] ?? 2));
$dry_run     = !empty($_POST['dry_run']);
$self_test   = !empty($_POST['self_test']);

$stdout = $run_dir . '/stdout.log';
$matched_csv = $run_dir . '/log.csv'; // we’ll log per-run CSV here (send script writes this path)
file_put_contents($run_dir.'/STARTED', gmdate('c'));

$args = [];
if ($campaign !== '') $args[] = '--campaign='.$campaign;
if ($limit>0)         $args[] = '--limit='.$limit;
if ($only_role!=='')  $args[] = '--only-role='.$only_role;
if ($batch_size)      $args[] = '--batch-size='.$batch_size;
if ($batch_sleep>=0)  $args[] = '--batch-sleep='.$batch_sleep;
if ($dry_run)         $args[] = '--dry-run';
if ($self_test)       $args[] = '--self-test';
// force log file path into script:
$args[] = '--log-file='.$matched_csv;

// Wrap to ensure DONE file is touched
$cmd = sprintf(
  'cd %s && (echo $$ > %s/pid) && %s %s %s > %s 2>&1; echo DONE > %s/DONE',
  escapeshellarg(TOOL_DIR),
  escapeshellarg($run_dir),
  escapeshellarg(PHP_CLI),
  escapeshellarg(SCRIPT),
  implode(' ', array_map('escapeshellarg', $args)),
  escapeshellarg($stdout),
  escapeshellarg($run_dir)
);
// run in background via sh -c
$bg = sprintf('sh -c %s > /dev/null 2>&1 & echo $!', escapeshellarg($cmd));
$pid = trim(shell_exec($bg));

file_put_contents($run_dir.'/run.json', json_encode([
  'pid'      => $pid,
  'args'     => $args,
  'stdout'   => $stdout,
  'log_csv'  => $matched_csv,
  'campaign' => $campaign,
  'started_gmt' => gmdate('c'),
], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

header('Content-Type: text/html; charset=utf-8'); ?>
<!doctype html>
<meta charset="utf-8">
<title>Launched</title>
<style>body{font-family:system-ui,Arial;margin:40px;max-width:900px}</style>
<h3>Launched background run</h3>
<p>Run ID: <b><?=htmlspecialchars(basename($run_dir))?></b></p>
<p>Logs folder: <code><?=htmlspecialchars($run_dir)?></code></p>
<p><a href="status.php">View Runs & Logs</a></p>
