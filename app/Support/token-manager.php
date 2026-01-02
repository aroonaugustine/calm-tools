<?php

declare(strict_types=1);

function portal_load_token_config(): array
{
    static $config;

    if ($config !== null) {
        return $config;
    }

    $config = require PORTAL_BASE_PATH . '/config/tokens.php';
    return $config;
}

function portal_is_master_token(string $value): bool
{
    $config = portal_load_token_config();
    $master = $config['master'] ?? '';
    return $master !== '' && hash_equals($master, $value);
}

function portal_tool_token_set(string $slug): array
{
    $config = portal_load_token_config();
    $tool = $config['tools'][$slug] ?? [];

    if (isset($tool['tokens']) && is_array($tool['tokens'])) {
        return $tool['tokens'];
    }

    if (isset($tool['token'])) {
        return ['default' => $tool['token']];
    }

    return [];
}

function portal_tool_default_token(string $slug): ?string
{
    $tokens = portal_tool_token_set($slug);
    if (isset($tokens['default'])) {
        return $tokens['default'];
    }
    return array_values($tokens)[0] ?? null;
}

function portal_tool_token_valid(string $slug, string $provided): bool
{
    if ($provided === '') {
        return false;
    }
    $tokens = portal_tool_token_set($slug);
    foreach ($tokens as $candidate) {
        if (hash_equals((string)$candidate, $provided)) {
            return true;
        }
    }
    return false;
}

function portal_tool_tokens(string $slug): array
{
    return portal_tool_token_set($slug);
}
