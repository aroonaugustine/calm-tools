<?php
/**
 * WP Bulk Column Updater — v15.09.0002.0001 launcher
 */
declare(strict_types=1);

// Shared bootstrap
require_once __DIR__ . '/_wp_bootstrap.php';
bcu141a_bootstrap_or_die();

// Optional: your shared admin-tools auth (kept as-is)
$auth_bootstrap = __DIR__ . '/../auth_bootstrap.php';
if (is_file($auth_bootstrap)) {
  require_once $auth_bootstrap;
  if (function_exists('require_login')) require_login('admin');
}

// === CONFIG ===
$REQUIRED_TOKEN = getenv('ADMIN_TOOLS_TOKEN') ?: 'CHANGE_ME_TOKEN';
$RUN_ROOT = __DIR__ . '/runs';
if (!is_dir($RUN_ROOT)) @mkdir($RUN_ROOT, 0755, true);

// Must be WP admin
if (!is_user_logged_in() || !current_user_can('manage_options')) {
  auth_redirect();
  exit;
}

// ---------------- existing v15.09.0002.0001 code continues unchanged ----------------
$csv_mode = $_POST['csv_mode'] ?? 'upload';
$limit    = max(0, (int)($_POST['limit'] ?? 0));
$live     = !empty($_POST['live']);
$primary  = $_POST['primary_key'] ?? '';
$secondary= $_POST['secondary_key'] ?? '';
$map_keys = $_POST['map_key'] ?? [];
$map_cols = $_POST['map_col'] ?? [];

if (!$primary) { echo "Primary key is required."; exit; }

// Persist CSV
if ($csv_mode === 'upload') {
  if (!isset($_FILES['csv_file']) || !is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
    echo "Please upload a CSV file."; exit;
  }
  $csv_path = $RUN_ROOT . '/csv-' . time() . '-' . wp_generate_password(6,false) . '.csv';
  @move_uploaded_file($_FILES['csv_file']['tmp_name'], $csv_path);
} else {
  $csv_path = trim((string)($_POST['csv_path'] ?? ''));
  if ($csv_path === '' || !is_file($csv_path)) { echo "Server CSV path not found."; exit; }
}

// Build mappings
$mappings = [];
for ($i=0; $i<count($map_keys); $i++){
  $k = trim((string)$map_keys[$i] ?? '');
  $c = trim((string)$map_cols[$i] ?? '');
  if ($k !== '' && $c !== '') {
    $mappings[] = ['target'=>$k, 'column'=>$c];
  }
}
if (empty($mappings)) { echo "Please add at least one target mapping."; exit; }

// Create run folder
$run_id = 'run-' . date('Ymd-His') . '-' . wp_generate_password(4,false);
$run_dir = $RUN_ROOT . '/' . $run_id;
@mkdir($run_dir, 0755, true);

// Save config
$config = [
  'version'   => '1.4.3',
  'created_at'=> date('c'),
  'user'      => get_current_user_id(),
  'csv_path'  => $csv_path,
  'primary'   => $primary,
  'secondary' => $secondary,
  'limit'     => $limit,
  'live'      => $live,
  'mappings'  => $mappings
];
file_put_contents($run_dir.'/config.json', json_encode($config, JSON_PRETTY_PRINT));

// Launch worker inline
require_once __DIR__ . '/wp-bulk-column-updater.php';
[$summaryPath, $logPath] = wpbcu144_run($config, $run_dir);

// Output
echo "<h2>Run launched: {$run_id}</h2>";
echo "<p>Mode: ".($live?'LIVE':'DRY RUN')."</p>";
echo "<p><a href=\"status.php\">View Runs & Logs</a></p>";
echo "<p>Summary: <a href=\"runs/{$run_id}/summary.json\">download</a></p>";
echo "<p>Log (NDJSON): <a href=\"runs/{$run_id}/log.ndjson\">download</a></p>";
