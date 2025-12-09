<?php
// Streams last ~256KB of a stdout.log for a run.
const LOG_BASE='/srv/admin-tools/wp-mailer/_mailer-logs';
$id = preg_replace('/[^A-Za-z0-9_:-]/','', $_GET['id'] ?? '');
$path = LOG_BASE.'/'.$id.'/stdout.log';
header('Content-Type: text/plain; charset=utf-8');
if (!is_file($path)) { http_response_code(404); echo "Not found"; exit; }
$max=256*1024; $sz=filesize($path); $fh=fopen($path,'r'); if(!$fh){echo "open fail"; exit;}
if ($sz>$max) fseek($fh,-$max,SEEK_END);
fpassthru($fh); fclose($fh);
