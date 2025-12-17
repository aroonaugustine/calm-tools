<?php
/**
 * Welcome Sender — Launcher v15.09.0002.0001-web (multi-token + manual emails)
 * Spawns background CLI job to send welcome emails (CSV / server path / manual emails).
 */

declare(strict_types=1);

/* =========================
   AUTH (multiple tokens)
   - Add as many tokens as you need.
   ========================= */
const LAUNCHER_AUTH_TOKENS = [
  // label            => token string (keep these secret)
  'owner' => '6714e52aed21125dd999ff7c31666c1806e033aa2cb8a14073b41ae7026ec0b0',
  'ops'   => 'e546ffe239d986aeaa4f8f936acdcf2d0af40de4d547c43de784a02d63843211',
];

/* =========================
   Paths
   ========================= */
const TOOL_DIR = '/srv/admin-tools/wp-welcome-sender';
const LOG_DIR  = '/srv/admin-tools/wp-welcome-sender/_mailer-logs';
const PHP_CLI  = '/usr/bin/php';
const MAILER   = TOOL_DIR . '/welcome_sender.php';
const WP_LOAD  = '/var/www/html/wp-load.php';

/* =========================
   Helpers
   ========================= */
function auth_ok_and_label(string $provided): array {
  // Accept via POST "token" or HTTP header "X-Launcher-Token"
  $candidate = $provided !== '' ? $provided : (string)($_SERVER['HTTP_X_LAUNCHER_TOKEN'] ?? '');
  if ($candidate === '') return [false, ''];
  foreach (LAUNCHER_AUTH_TOKENS as $label => $secret) {
    if (hash_equals($secret, $candidate)) return [true, (string)$label];
  }
  return [false, ''];
}

function normalize_manual_emails(string $raw, int $max = 5): array {
  // Split by commas, whitespace, semicolons
  $parts = preg_split('/[,\s;]+/u', trim($raw)) ?: [];
  $seen = [];
  $out  = [];
  foreach ($parts as $p) {
    $p = strtolower(trim($p));
    if ($p === '' || isset($seen[$p])) continue;
    $seen[$p] = true;
    if (filter_var($p, FILTER_VALIDATE_EMAIL)) {
      $out[] = $p;
      if (count($out) >= $max) break;
    }
  }
  return $out;
}

/* =========================
   Request method + auth
   ========================= */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405); echo "Method Not Allowed"; exit;
}

$provided_token = (string)($_POST['token'] ?? '');
[$auth_ok, $token_label] = auth_ok_and_label($provided_token);
if (!$auth_ok) { http_response_code(401); echo "Unauthorized"; exit; }

/* =========================
   Preconditions
   ========================= */
if (!is_file(MAILER)) { http_response_code(500); echo "Mailer missing"; exit; }
if (!is_file(WP_LOAD)) { http_response_code(500); echo "wp-load.php not found at ".WP_LOAD; exit; }
if (!is_dir(LOG_DIR))  { @mkdir(LOG_DIR, 0755, true); }

/* =========================
   CSV handling
   ========================= */
$csvMode = $_POST['csv_mode'] ?? 'upload';
$run_id  = gmdate('Ymd_His') . '_' . bin2hex(random_bytes(3));
$run_dir = LOG_DIR . '/welcome_' . $run_id;
@mkdir($run_dir, 0755, true);

if ($csvMode === 'server') {
  $p = trim((string)($_POST['csv_path'] ?? ''));
  if ($p === '' || !is_file($p)) { http_response_code(400); echo "CSV path invalid or not found"; exit; }
  $csvPath = $p;

} elseif ($csvMode === 'manual') {
  $raw = (string)($_POST['manual_emails'] ?? '');
  $emails = normalize_manual_emails($raw, 5);
  if (empty($emails)) { http_response_code(400); echo "No valid email addresses provided (max 5)."; exit; }
  // Build a tiny CSV at runtime
  $dest = $run_dir . '/input.csv';
  $fh = @fopen($dest, 'w');
  if (!$fh) { http_response_code(500); echo "Failed to create temp CSV"; exit; }
  // header: user_email (mailer accepts email or user_login)
  fputcsv($fh, ['user_email']);
  foreach ($emails as $e) fputcsv($fh, [$e]);
  fclose($fh);
  $csvPath = $dest;

} else { // upload
  if (!isset($_FILES['csv_file']) || ($_FILES['csv_file']['error'] ?? 4) !== 0) {
    http_response_code(400); echo "No CSV uploaded"; exit;
  }
  $tmp  = $_FILES['csv_file']['tmp_name'] ?? '';
  $dest = $run_dir . '/input.csv';
  if (!@move_uploaded_file($tmp, $dest)) { http_response_code(500); echo "Failed to save upload"; exit; }
  $csvPath = $dest;
}

/* =========================
   Options
   ========================= */
$limit      = max(0, (int)($_POST['limit'] ?? 0));
$send       = !empty($_POST['send']); // checked = LIVE, unchecked = DRY RUN

// Optional extras (supported by worker)
$campaign   = trim((string)($_POST['campaign'] ?? ''));
$batchSize  = max(1, (int)($_POST['batch_size'] ?? 50));
$batchSleep = max(0, (int)($_POST['batch_sleep'] ?? 2));
$onlyRole   = trim((string)($_POST['only_role'] ?? ''));

/* =========================
   File paths for this run
   ========================= */
$stdout_log = $run_dir . '/stdout.log';
$csv_log    = $run_dir . '/results.csv';
$meta_json  = $run_dir . '/run.json';

// Mark STARTED for the status UI
@touch($run_dir . '/STARTED');

/* =========================
   Build CLI args
   ========================= */
$args = [];
$args[] = '--csv-file=' . $csvPath;
$args[] = '--log-file=' . $csv_log;
if (!$send)            $args[] = '--dry-run';
if ($limit > 0)        $args[] = '--limit=' . $limit;
if ($campaign !== '')  $args[] = '--campaign=' . $campaign;
if ($onlyRole !== '')  $args[] = '--only-role=' . $onlyRole;
$args[] = '--batch-size=' . $batchSize;
$args[] = '--batch-sleep=' . $batchSleep;

/* =========================
   Spawn background process
   ========================= */
$cmd = sprintf(
  'cd %s && %s %s %s > %s 2>&1 & echo $!',
  escapeshellarg(TOOL_DIR),
  escapeshellarg(PHP_CLI),
  escapeshellarg(MAILER),
  implode(' ', array_map('escapeshellarg', $args)),
  escapeshellarg($stdout_log)
);
$pid = trim((string)shell_exec($cmd));
if (!ctype_digit($pid)) $pid = '';

/* =========================
   Save metadata (sanitized)
   ========================= */
$meta = [
  'tool'           => 'welcome-sender',
  'run_id'         => $run_id,
  'pid'            => $pid,
  'started_gmt'    => gmdate('c'),
  'args'           => $args,
  'csv_source'     => $csvMode,
  'csv_path'       => $csvPath,
  'stdout_log'     => $stdout_log,
  'results_csv'    => $csv_log,
  'mailer'         => MAILER,
  'php'            => PHP_CLI,
  'wp_load'        => WP_LOAD,
  'auth_token_tag' => $token_label, // who launched (label)
];
file_put_contents($meta_json, json_encode($meta, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

/* =========================
   Response
   ========================= */
header('Content-Type: text/html; charset=utf-8'); ?>
<!doctype html>
<meta charset="utf-8">
<title>Launched</title>
<style>body{font-family:system-ui,Arial;margin:40px;max-width:800px}</style>
<h3>Launched background run</h3>
<ul>
  <li><b>Run ID:</b> <?=htmlspecialchars($run_id)?></li>
  <li><b>PID:</b> <?=htmlspecialchars($pid)?></li>
  <li><b>Started (GMT):</b> <?=htmlspecialchars($meta['started_gmt'])?></li>
  <li><b>Launched by token:</b> <?=htmlspecialchars($token_label)?></li>
</ul>
<p>Logs folder: <code><?=htmlspecialchars($run_dir)?></code></p>
<p><a href="status.php">View Runs & Logs</a></p>
