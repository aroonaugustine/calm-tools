<?php

declare(strict_types=1);

const PORTAL_BASE_PATH = __DIR__ . '/..';
const PORTAL_NAMESPACE_PREFIX = 'Portal\\';

require_once PORTAL_BASE_PATH . '/app/Support/helpers.php';
require_once PORTAL_BASE_PATH . '/app/Support/token-manager.php';

spl_autoload_register(function (string $class): void {
    if (strncmp($class, PORTAL_NAMESPACE_PREFIX, strlen(PORTAL_NAMESPACE_PREFIX)) !== 0) {
        return;
    }

    $relative = substr($class, strlen(PORTAL_NAMESPACE_PREFIX));
    $relativePath = PORTAL_BASE_PATH . '/app/' . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

    if (is_file($relativePath)) {
        require_once $relativePath;
    }
});
