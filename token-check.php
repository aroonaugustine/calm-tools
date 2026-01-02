<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Method Not Allowed.'], JSON_UNESCAPED_SLASHES);
    exit;
}

$token = trim((string)($_POST['token'] ?? ''));
if ($token === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Token is required.'], JSON_UNESCAPED_SLASHES);
    exit;
}

$type = 'invalid';
if (portal_is_master_token($token)) {
    $type = 'master';
} elseif (portal_is_view_token($token)) {
    $type = 'viewer';
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['type' => $type], JSON_UNESCAPED_SLASHES);
