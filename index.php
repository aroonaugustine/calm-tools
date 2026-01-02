<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use Portal\Core\Tool;
use Portal\Core\ToolRegistry;

$registry = ToolRegistry::fromConfig(__DIR__ . '/config/tools.php');
$tools = $registry->all();
$webTools = array_values(array_filter($tools, static fn (Tool $tool): bool => !$tool->isCli()));

/**
 * @param Tool[] $list
 */
function render_tool_cards(array $list): void
{
    foreach ($list as $tool) {
        $searchBlob = strtolower($tool->name() . ' ' . $tool->description() . ' ' . implode(' ', $tool->tags()));
        ?>
        <article class="tool-card"
                 data-tool-card
                 data-mode="<?= $tool->isCli() ? 'cli' : 'web'; ?>"
                 data-search="<?= portal_esc($searchBlob); ?>">
          <div class="badges">
            <span class="badge <?= $tool->isCli() ? 'cli' : ''; ?>"><?= $tool->isCli() ? 'CLI' : 'Web'; ?></span>
            <span class="badge"><?= portal_esc($tool->category()); ?></span>
            <?php if ($tool->requiresAuth()): ?>
              <span class="badge auth">Access Token</span>
            <?php endif; ?>
          </div>
          <h3><?= portal_esc($tool->name()); ?></h3>
          <?php if ($tool->version()): ?>
            <span class="badge version"><?= portal_esc($tool->version()); ?></span>
          <?php endif; ?>
          <p><?= portal_esc($tool->description()); ?></p>
          <?php if ($tool->notes()): ?>
            <p class="notes"><?= portal_esc($tool->notes() ?? ''); ?></p>
          <?php endif; ?>
          <div class="badges">
            <?php foreach ($tool->tags() as $tag): ?>
              <span class="badge">#<?= portal_esc($tag); ?></span>
            <?php endforeach; ?>
          </div>
          <div class="card-actions">
            <?php if ($tool->launchUrl()): ?>
              <button type="button"
                      class="button primary"
                      data-tool-slug="<?= portal_esc($tool->slug()); ?>">
                Launch
              </button>
            <?php else: ?>
              <span class="button secondary">CLI Only</span>
            <?php endif; ?>
            <?php if ($tool->docs()): ?>
              <a class="button secondary" href="<?= portal_esc($tool->docs() ?? ''); ?>" target="_blank" rel="noreferrer">
                Docs
              </a>
            <?php endif; ?>
          </div>
        </article>
        <?php
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>CALM Admin Toolkit</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= portal_asset('css/portal.css'); ?>">
</head>
<body>
<main data-runner-endpoint="<?= portal_base_uri(); ?>tool-runner.php">
  <section class="hero">
    <div>
      <h1>CALM Admin Toolkit</h1>
      <p>Launchpad for <span data-tool-count><?= portal_esc((string) count($webTools)); ?></span> internal tools.</p>
    </div>
    <form class="token-form" data-token-form>
      <label for="access-token">Session access token</label>
      <div class="token-controls">
        <input type="password"
               id="access-token"
               placeholder="Paste your access token"
               autocomplete="off"
               data-token-input
               required>
        <button type="submit" class="button primary token-save">Save Token</button>
        <button type="button" class="button secondary token-clear" data-token-clear>Clear</button>
      </div>
      <small class="token-hint" data-token-msg>Required for launching every tool. Stored only in this browser session.</small>
    </form>
  </section>

  <div class="filters">
    <input type="search" placeholder="Search tools, tags or descriptions…" data-filter="search">
  </div>

  <section class="tool-section">
    <div class="section-header">
      <h2>Web Tools</h2>
      <span class="pill" data-section-count="web"><?= count($webTools); ?></span>
    </div>
    <div class="tool-grid">
      <?php render_tool_cards($webTools); ?>
    </div>
    <div class="empty-state" data-empty="web" hidden>
      <h3>No web tools match your filters</h3>
      <p>Clear the search above to see all web tools again.</p>
    </div>
  </section>

</main>
<script src="<?= portal_asset('js/portal.js'); ?>" defer></script>
</body>
</html>
