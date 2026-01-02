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

$isFullAccess = portal_is_master_token($token);
$isViewerMode = portal_is_view_token($token) && trim((string)($_GET['mode'] ?? '')) === 'viewer';

if (!$isFullAccess && !$isViewerMode) {
    http_response_code(401);
    echo '<h1>Unauthorized</h1><p>Provide a valid session token to open this tool.</p>';
    exit;
}

if ($isFullAccess) {
    $toolToken = portal_tool_default_token($tool->slug());
    if ($toolToken === null) {
        http_response_code(500);
        echo '<h1>Misconfigured tool</h1><p>This tool has no configured token.</p>';
        exit;
    }
} else {
    $toolToken = null;
}

$entryUrl = portal_tool_url($tool->entry());
$entryTarget = $entryUrl;
if ($toolToken !== null) {
    $separator = str_contains($entryUrl, '?') ? '&' : '?';
    $entryTarget = $entryUrl . "{$separator}token=" . rawurlencode($toolToken);
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
      <section class="runner-panel runner-tool-panel<?= $isViewerMode ? ' runner-is-viewer' : ''; ?>">
        <header class="runner-panel__header">
          <h2>Tool workspace</h2>
          <p class="muted">
            <?= $isViewerMode ? 'Viewer mode — functionality is disabled.' : 'This tab runs the tool with your saved session token.'; ?>
          </p>
        </header>
        <iframe class="runner-tool-frame" src="<?= portal_esc($entryTarget); ?>" title="<?= portal_esc($tool->name()); ?> workspace" loading="lazy" scrolling="no"></iframe>
        <?php if ($isViewerMode): ?>
          <div class="runner-viewer-overlay">
            <p>Viewer access only — actions are blocked.</p>
          </div>
        <?php endif; ?>
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
  <script>
    (function () {
      const toolFrame = document.querySelector('.runner-tool-frame');
      const statusFrame = document.querySelector('.runner-status-frame');

      if (!toolFrame) {
        return;
      }

      const measure = (frame) => {
        if (!frame) {
          return;
        }
        try {
          const doc = frame.contentDocument;
          if (!doc) {
            return;
          }
          const { scrollHeight: docScroll } = doc.documentElement;
          const bodyScroll = doc.body ? doc.body.scrollHeight : 0;
          const targetHeight = Math.max(docScroll, bodyScroll, 420);
          frame.style.height = `${targetHeight}px`;
        } catch (error) {
          console.warn('Resize blocked', error);
        }
      };

      const resize = () => {
        measure(toolFrame);
        measure(statusFrame);
      };

      toolFrame.addEventListener('load', resize);
      statusFrame?.addEventListener('load', resize);

      const interval = window.setInterval(resize, 1000);
      window.addEventListener('resize', resize);
      window.addEventListener('beforeunload', () => {
        window.clearInterval(interval);
      });
    })();
  </script>
</body>
</html>
