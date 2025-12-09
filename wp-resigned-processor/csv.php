<?php
const LOG_DIR = '/srv/admin-tools/_mailer-logs';
$id = preg_replace('/[^A-Za-z0-9_:-]/', '', $_GET['id'] ?? '');
$t  = ($_GET['t'] ?? '') === 'matched' ? 'matched_done.csv' : 'unmatched_skipped.csv';
$path = LOG_DIR . '/' . $id . '/' . $t;
if (!is_file($path)) { http_response_code(404); echo "Not found"; exit; }
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . basename($t, '.csv') . '_' . $id . '.csv"');
readfile($path);