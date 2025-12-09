<?php
const LOG_DIR = '/srv/admin-tools/_mailer-logs';
$id = preg_replace('/[^A-Za-z0-9_:-]/', '', $_GET['id'] ?? '');
$path = LOG_DIR . '/' . $id . '/stdout.log';
header('Content-Type: text/plain; charset=utf-8');
if (!is_file($path)) { http_response_code(404); echo "Not found"; exit; }
$size = filesize($path); $max = 200*1024;
if ($size > $max) { $fp=fopen($path,'r'); fseek($fp,-$max,SEEK_END); echo stream_get_contents($fp); fclose($fp); }
else { readfile($path); }
