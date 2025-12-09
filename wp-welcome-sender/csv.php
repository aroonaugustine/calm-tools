<?php
const LOG_DIR = '/srv/admin-tools/wp-welcome-sender/_mailer-logs';

$id = preg_replace('/[^A-Za-z0-9_:-]/', '', $_GET['id'] ?? '');
$path = LOG_DIR . '/' . $id . '/results.csv';
if (!is_file($path)) { http_response_code(404); echo "Not found"; exit; }
header('Content-Type: text/csv; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Content-Disposition: attachment; filename="welcome_' . $id . '.csv"');
readfile($path);