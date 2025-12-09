<?php
const LOG_DIR = '/srv/admin-tools/_mailer-logs';
$id = preg_replace('/[^A-Za-z0-9_:-]/', '', $_GET['id'] ?? '');
$path = LOG_DIR . '/' . $id . '/stdout.log';
if (!is_file($path)) { http_response_code(404); echo "Not found"; exit; }
header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="stdout_' . $id . '.log"');
readfile($path);
