<?php
/**
 * Universal CSV Downloader v15.09.0002.0001
 *
 * Supports:
 *   - resigned_* processors
 *   - remove3027_* processors
 *
 * Files downloaded:
 *   - matched_done.csv
 *   - unmatched_skipped.csv
 */

declare(strict_types=1);

const LOG_BASE = '/srv/admin-tools/_mailer-logs';

/* ---------- Validate Input ---------- */
$id = $_GET['id'] ?? '';
$t  = $_GET['t']  ?? '';

/* sanitize folder name */
$id = preg_replace('/[^A-Za-z0-9_:-]/', '', $id);
$t  = preg_replace('/[^A-Za-z]/', '', $t);

if ($id === '' || $t === '') {
  http_response_code(400);
  echo "Invalid request.";
  exit;
}

/* ---------- Determine File Path ---------- */
$run_dir = LOG_BASE . '/' . $id;

if (!is_dir($run_dir)) {
  http_response_code(404);
  echo "Run not found.";
  exit;
}

switch ($t) {
  case 'matched':
    $file = $run_dir . '/matched_done.csv';
    break;

  case 'unmatched':
    $file = $run_dir . '/unmatched_skipped.csv';
    break;

  default:
    http_response_code(400);
    echo "Invalid file type.";
    exit;
}

/* ---------- Validate File ---------- */
if (!is_file($file)) {
  http_response_code(404);
  echo "File not found: " . htmlspecialchars(basename($file));
  exit;
}

/* ---------- Stream CSV Download ---------- */
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . basename($file) . '"');
header('Content-Length: ' . filesize($file));
header('X-Content-Type-Options: nosniff');

$fh = fopen($file, 'rb');
fpassthru($fh);
fclose($fh);

exit;
