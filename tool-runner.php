<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use Portal\Core\ToolRegistry;

$slug = preg_replace('/[^A-Za-z0-9_-]/', '', $_GET['tool'] ?? '');
$token = trim((string)($_GET['token'] ?? ''));

$registry = ToolRegistry::fromConfig(__DIR__ . '/config/tools.php');
$tool = null;
foreach ($registry->all() as $candidate) {
    if ($candidate->slug() === $slug && !$candidate->isCli()) {
        $tool = $candidate;
        break;
    }
}

if ($tool === null) {
    http_response_code(404);
    echo '<h1>Tool not found</h1>';
    exit;
}

$entryUrl = portal_tool_url($tool->entry());
$entryTarget = $entryUrl;
if ($token !== '') {
    $separator = str_contains($entryUrl, '?') ? '&' : '?';
    $entryTarget = $entryUrl . "{$separator}token=" . rawurlencode($token);
}

$statusPath = portal_tool_status_path($tool->entry());
$statusUrl = $statusPath ? portal_tool_url($statusPath) : null;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= portal_esc($tool->name()); ?> — Runner</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= portal_asset('css/portal.css'); ?>">
  <link rel="stylesheet" href="<?= portal_asset('css/runner.css'); ?>">
</head>
<body>
  <main class="runner-shell">
    <header class="runner-header">
      <div>
        <h1><?= portal_esc($tool->name()); ?></h1>
        <p class="muted runner-header__meta"><?= portal_esc($tool->description()); ?></p>
      </div>
      <div>
        <?php if ($tool->version()): ?>
          <span class="badge version"><?= portal_esc($tool->version()); ?></span>
        <?php endif; ?>
      </div>
    </header>
    <div class="runner-layout">
      <section class="runner-panel runner-tool-panel">
        <header class="runner-panel__header">
          <h2>Tool workspace</h2>
          <p class="muted">This tab runs the tool with your saved session token.</p>
        </header>
        <iframe class="runner-tool-frame" src="<?= portal_esc($entryTarget); ?>" title="<?= portal_esc($tool->name()); ?> workspace" loading="lazy" scrolling="no"></iframe>
      </section>
      <section class="runner-panel runner-status-panel">
        <header class="runner-panel__header">
          <h2>Status &amp; logs</h2>
          <p class="muted">Live stdout, run metadata, and summaries appear here.</p>
        </header>
        <?php if ($statusUrl): ?>
          <iframe class="runner-status-frame" src="<?= portal_esc($statusUrl); ?>" title="Live status and logs" loading="lazy"></iframe>
          <div class="runner-panel__actions">
            <a class="button secondary" href="<?= portal_esc($statusUrl); ?>" target="_blank" rel="noreferrer noopener">View previous runs &amp; logs</a>
          </div>
        <?php else: ?>
          <div class="runner-panel__empty">
            No inline status page detected for this tool, but logs are still saved under its logging directory.
          </div>
          <div class="runner-panel__actions">
            <span class="button secondary" aria-disabled="true">View previous runs &amp; logs</span>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </main>
</body>
</html>
