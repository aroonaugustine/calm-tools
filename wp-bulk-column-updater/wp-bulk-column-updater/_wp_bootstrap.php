<?php
/**
 * Shared WP bootstrap for admin-tools
 * Version: 1.4.1a
 *
 * How it locates wp-load.php (in this order):
 *  1) If env var WP_LOAD_PATH is set to an absolute file, use it.
 *  2) If you define() MANUAL_WP_LOAD to an absolute file, use it.
 *  3) Try walking upward from several "hint" roots and append:
 *       - /wp-load.php
 *       - /public_html/wp-load.php
 *       - /wordpress/wp-load.php
 *     (walk up to 12 levels from each hint)
 *
 * If not found, shows a clear error with instructions.
 */

declare(strict_types=1);

if (!function_exists('bcu141a_find_wp_load')) {
function bcu141a_find_wp_load(): ?string {
    // 1) Environment override (best for unusual layouts)
    $env = getenv('WP_LOAD_PATH');
    if ($env && is_file($env)) return $env;

    // 2) Manual constant (optional config)
    if (defined('MANUAL_WP_LOAD') && is_string(MANUAL_WP_LOAD) && is_file(MANUAL_WP_LOAD)) {
        return MANUAL_WP_LOAD;
    }

    // 3) Hints to try (both Linux/DA common roots)
    $hints = array_unique(array_filter([
        __DIR__,                            // /admin-tools/wp-bulk-column-updater
        dirname(__DIR__, 1),                // /admin-tools
        dirname(__DIR__, 2),                // site root candidate (e.g. /var/www/html or /home/.../public_html)
        @realpath($_SERVER['DOCUMENT_ROOT'] ?? ''), // web docroot if set
        @realpath(__DIR__ . '/../../'),     // hop two up
        @realpath(__DIR__ . '/../../../'),  // hop three up
    ]));

    $suffixes = [
        '/wp-load.php',
        '/public_html/wp-load.php',   // DirectAdmin pattern
        '/wordpress/wp-load.php',     // subfolder installs
    ];

    $tried = [];

    foreach ($hints as $start) {
        $dir = $start;
        for ($depth = 0; $depth < 12; $depth++) {
            foreach ($suffixes as $sfx) {
                $candidate = rtrim($dir, '/\\') . $sfx;
                $tried[] = $candidate;
                if (is_file($candidate)) return $candidate;
            }
            $parent = dirname($dir);
            if ($parent === $dir) break;
            $dir = $parent;
        }
    }

    // As a last-ditch: try common absolute roots explicitly
    $common = [
        '/var/www/html/wp-load.php',
        '/var/www/public_html/wp-load.php',
    ];
    foreach ($common as $c) {
        $tried[] = $c;
        if (is_file($c)) return $c;
    }

    // Not found — print helpful error
    http_response_code(500);
    echo "<h2>Cannot locate wp-load.php</h2>";
    echo "<p><strong>Fix one of the following:</strong></p>";
    echo "<ol>";
    echo "<li><code>export WP_LOAD_PATH=/absolute/path/to/wp-load.php</code> (env var)</li>";
    echo "<li>Create a small config above this file:<br>";
    echo "<code>&lt;?php define('MANUAL_WP_LOAD','/absolute/path/to/wp-load.php');</code></li>";
    echo "<li>Ensure this folder is under your WordPress tree, e.g.:<br>";
    echo "<code>/var/www/html/admin-tools/wp-bulk-column-updater/</code> (then <code>../../wp-load.php</code> exists)</li>";
    echo "</ol>";
    echo "<details><summary>Paths tried</summary><pre>" . htmlspecialchars(implode("\n", $tried), ENT_QUOTES) . "</pre></details>";
    return null;
}

function bcu141a_bootstrap_or_die(): void {
    $wp = bcu141a_find_wp_load();
    if (!$wp) {
        exit; // message already printed
    }
    require_once $wp;
}
}
