<?php
/**
 * division-mapping-load.php
 *
 * Loads division mapping JSON from disk.
 * Called via GET from the CSV Column Mapper (v15.09.0002.0001).
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// Only GET allowed (but we won't be super strict here)
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed. Use GET.']);
    exit;
}

$target = __DIR__ . '/division-mapping.json';

if (!is_file($target)) {
    // No mapping file yet → send empty mapping so JS can show a friendly message
    echo json_encode([
        'version'  => null,
        'mapping'  => new stdClass(), // {} instead of []
        'message'  => 'No mapping file yet.'
    ]);
    exit;
}

$raw = @file_get_contents($target);
if ($raw === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to read division-mapping.json']);
    exit;
}

// Just echo the file contents if valid JSON; otherwise wrap it
$json = json_decode($raw, true);
if ($json === null) {
    echo json_encode([
        'version' => null,
        'mapping' => new stdClass(),
        'error'   => 'division-mapping.json is invalid JSON, returning empty mapping.'
    ]);
    exit;
}

echo json_encode($json, JSON_UNESCAPED_SLASHES);
