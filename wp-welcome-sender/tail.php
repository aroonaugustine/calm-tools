<?php
// Align with launcher/worker
const LOG_DIR = '/srv/admin-tools/wp-welcome-sender/_mailer-logs';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$id = preg_replace('/[^A-Za-z0-9_:-]/', '', $_GET['id'] ?? '');
$path = LOG_DIR . '/' . $id . '/stdout.log';
if (!is_file($path)) { http_response_code(404); echo "Not found"; exit; }

$size = filesize($path); $max = 200*1024; // last ~200KB
if ($size > $max) {
  $fp = fopen($path,'r'); fseek($fp,-$max,SEEK_END);
  echo stream_get_contents($fp); fclose($fp);
} else {
  readfile($path);
}