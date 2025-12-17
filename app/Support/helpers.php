<?php

declare(strict_types=1);

if (!\defined('PORTAL_BASE_PATH')) {
    \define('PORTAL_BASE_PATH', __DIR__ . '/..');
}

function portal_path(string $path = ''): string
{
    $normalized = ltrim($path, '/');
    return PORTAL_BASE_PATH . ($normalized !== '' ? '/' . $normalized : '');
}

function portal_base_uri(): string
{
    static $base;

    if ($base !== null) {
        return $base;
    }

    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $directory = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    $base = $directory === '' || $directory === '.' ? '/' : $directory . '/';

    return $base;
}

function portal_asset(string $path): string
{
    return portal_base_uri() . 'portal-assets/' . ltrim($path, '/');
}

function portal_tool_url(string $relativePath): string
{
    return portal_base_uri() . ltrim($relativePath, '/');
}

function portal_esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
