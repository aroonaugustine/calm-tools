<?php
/**
 * wp-lookup.php
 *
 * Read-only lookup against WordPress for:
 * - existing usernames
 * - existing emails
 * - existing NIC numbers (user meta: nic_no)
 * - existing passport numbers (user meta: passport_no)
 *
 * Used by CSV Column Mapper v15.11 for collision detection.
 */

declare(strict_types=1);

// ====== CONFIG ======
const AUTH_TOKEN = '6714e52aed21125dd999ff7c31666c1806e033aa2cb8a14073b41ae7026ec0b0'; // change if needed
const WP_ROOT    = '/var/www/html'; // path that contains wp-load.php
// ====================

header('Content-Type: application/json; charset=utf-8');

// Only POST with JSON
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed. Use POST.']);
    exit;
}

$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Empty request body.']);
    exit;
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON.']);
    exit;
}

// Auth
$token = (string)($data['token'] ?? '');
if (!hash_equals(AUTH_TOKEN, $token)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized. Invalid token.']);
    exit;
}

// Normalize arrays
$emails    = array_values(array_unique(array_filter(array_map('strval', $data['emails']    ?? []))));
$usernames = array_values(array_unique(array_filter(array_map('strval', $data['usernames'] ?? []))));
$nics      = array_values(array_unique(array_filter(array_map('strval', $data['nics']      ?? []))));
$passports = array_values(array_unique(array_filter(array_map('strval', $data['passports'] ?? []))));

// Soft caps (same as frontend intent)
$emails    = array_slice($emails, 0, 20000);
$usernames = array_slice($usernames, 0, 20000);
$nics      = array_slice($nics, 0, 20000);
$passports = array_slice($passports, 0, 20000);

// Bootstrap WordPress
require_once WP_ROOT . '/wp-load.php';

/** @var wpdb $wpdb */
global $wpdb;

$result = [
    'usernames' => [],
    'emails'    => [],
    'nics'      => [],
    'passports' => []
];

// Helper: IN (...) query builder
function wp_lookup_in_clause(array $items, string $column, string $table, wpdb $wpdb): array {
    if (empty($items)) return [];

    // Deduplicate lowercase where appropriate
    $items = array_values(array_unique($items));

    $placeholders = implode(',', array_fill(0, count($items), '%s'));
    $sql = "SELECT DISTINCT {$column} FROM {$table} WHERE {$column} IN ($placeholders)";

    // Prepare with all items
    $prepared = $wpdb->prepare($sql, $items);
    $rows = $wpdb->get_col($prepared);

    return array_values(array_unique(array_map('strval', $rows ?? [])));
}

// ---- usernames (wp_users.user_login) ----
if (!empty($usernames)) {
    // Lowercase everything to match normalised comparison in JS
    $normalized = array_map(static function($u) {
        return strtolower(trim((string)$u));
    }, $usernames);

    // Query exactly what's sent; store and compare lowercase in JS
    $result['usernames'] = wp_lookup_in_clause($normalized, 'user_login', $wpdb->users, $wpdb);
}

// ---- emails (wp_users.user_email) ----
if (!empty($emails)) {
    $normalized = array_map(static function($e) {
        return strtolower(trim((string)$e));
    }, $emails);

    $result['emails'] = wp_lookup_in_clause($normalized, 'user_email', $wpdb->users, $wpdb);
}

// ---- NIC meta (wp_usermeta.nic_no) ----
if (!empty($nics)) {
    $meta_key = 'nic_no';
    $placeholders = implode(',', array_fill(0, count($nics), '%s'));
    $sql = "
        SELECT DISTINCT meta_value
        FROM {$wpdb->usermeta}
        WHERE meta_key = %s
          AND meta_value IN ($placeholders)
    ";
    $params = array_merge([$meta_key], $nics);
    $prepared = $wpdb->prepare($sql, $params);
    $rows = $wpdb->get_col($prepared);
    $result['nics'] = array_values(array_unique(array_map('strval', $rows ?? [])));
}

// ---- Passport meta (wp_usermeta.passport_no) ----
if (!empty($passports)) {
    $meta_key = 'passport_no';
    $placeholders = implode(',', array_fill(0, count($passports), '%s'));
    $sql = "
        SELECT DISTINCT meta_value
        FROM {$wpdb->usermeta}
        WHERE meta_key = %s
          AND meta_value IN ($placeholders)
    ";
    $params = array_merge([$meta_key], $passports);
    $prepared = $wpdb->prepare($sql, $params);
    $rows = $wpdb->get_col($prepared);
    $result['passports'] = array_values(array_unique(array_map('strval', $rows ?? [])));
}

// Done
echo json_encode($result, JSON_UNESCAPED_SLASHES);