<?php
/**
 * Download ZIP of results (matched + unmatched) for a given run
 * Path: /srv/admin-tools/wp-resigned-processor/zip.php
 *
 * Usage: zip.php?id=resigned_YYYYmmdd_HHMMSS_xxxxxx
 */

const LOG_DIR = '/srv/admin-tools/_mailer-logs';

$id = preg_replace('/[^A-Za-z0-9_:-]/', '', $_GET['id'] ?? '');
if ($id === '') { http_response_code(400); echo "Missing id"; exit; }

$runDir = LOG_DIR . '/' . $id;
$matched = $runDir . '/matched_done.csv';
$unmatched = $runDir . '/unmatched_skipped.csv';
$stdout = $runDir . '/stdout.log'; // optional

if (!is_dir($runDir)) { http_response_code(404); echo "Run not found"; exit; }
if (!is_file($matched) && !is_file($unmatched)) { http_response_code(404); echo "No CSVs for this run yet"; exit; }

$tmpZip = tempnam(sys_get_temp_dir(), 'resigned_zip_');
$zipPath = $tmpZip . '.zip';
@unlink($tmpZip);

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
  http_response_code(500); echo "Cannot create zip"; exit;
}

// Always try to add both CSVs (if present)
if (is_file($matched))   $zip->addFile($matched, 'matched_done.csv');
if (is_file($unmatched)) $zip->addFile($unmatched, 'unmatched_skipped.csv');
// Nice to include log for debugging
if (is_file($stdout))    $zip->addFile($stdout, 'stdout.log');

$zip->close();

$downloadName = 'resigned_results_' . $id . '.zip';
header('Content-Type: application/zip');
header('Content-Length: ' . filesize($zipPath));
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
readfile($zipPath);
@unlink($zipPath);
