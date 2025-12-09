<?php
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok'=>true,'ping'=>'pong','time'=>gmdate('c')], JSON_UNESCAPED_SLASHES);
