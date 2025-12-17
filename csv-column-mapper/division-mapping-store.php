<?php
/**
 * division-mapping-store.php
 *
 * Stores division mapping JSON to disk.
 * Called via POST from the CSV Column Mapper (v15.09.0002.0001).
 *
 * SECURITY:
 * - Assumes this tool lives behind Basic Auth or a protected admin area.
 * - No token required here (only internal tool uses it).
 */

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

// Only POST allowed
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo "Method Not Allowed. Use POST.\n";
    exit;
}

$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') {
    http_response_code(400);
    echo "No JSON body received.\n";
    exit;
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo "Invalid JSON.\n";
    exit;
}

$mapping = $data['mapping'] ?? null;
if (!is_array($mapping) && !is_object($mapping)) {
    http_response_code(400);
    echo "Missing or invalid 'mapping' key.\n";
    exit;
}

// Normalize to array
if ($mapping instanceof stdClass) {
    $mapping = (array) $mapping;
}

$out = [
    'version'     => $data['version'] ?? 'unknown',
    'saved_at'    => gmdate('c'),
    'mapping'     => $mapping,
];

$target = __DIR__ . '/division-mapping.json';

if (@file_put_contents($target, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
    http_response_code(500);
    echo "Failed to write division-mapping.json\n";
    exit;
}

echo "Saved mapping to division-mapping.json (" . count($mapping) . " entries).\n";
