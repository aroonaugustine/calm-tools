<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use Portal\Core\Tool;
use Portal\Core\ToolRegistry;

$registry = ToolRegistry::fromConfig(__DIR__ . '/config/tools.php');
$tools = $registry->all();
$categories = $registry->categories();
$totalTools = count($tools);
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
<main>
  <section class="hero">
    <h1>CALM Admin Toolkit</h1>
    <p>Unified launchpad for <span data-tool-count><?= portal_esc((string) $totalTools); ?></span> internal tools.</p>
  </section>

  <div class="filters">
    <input type="search" placeholder="Search tools, tags or descriptions…" data-filter="search">
    <select data-filter="category">
      <option value="all">All categories</option>
      <?php foreach ($categories as $category): ?>
        <option value="<?= portal_esc($category); ?>"><?= portal_esc($category); ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <section class="tool-grid">
    <?php foreach ($tools as $tool): ?>
      <?php
        $searchBlob = strtolower($tool->name() . ' ' . $tool->description() . ' ' . implode(' ', $tool->tags()));
      ?>
      <article class="tool-card" data-tool-card data-category="<?= portal_esc($tool->category()); ?>" data-search="<?= portal_esc($searchBlob); ?>">
        <div class="badges">
          <span class="badge <?= $tool->isCli() ? 'cli' : ''; ?>"><?= $tool->isCli() ? 'CLI' : 'Web'; ?></span>
          <span class="badge"><?= portal_esc($tool->category()); ?></span>
          <?php if ($tool->requiresAuth()): ?>
            <span class="badge auth">Auth Required</span>
          <?php endif; ?>
        </div>
        <h3><?= portal_esc($tool->name()); ?></h3>
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
            <a class="button primary" href="<?= portal_esc($tool->launchUrl() ?? ''); ?>" target="_blank" rel="noreferrer">Launch</a>
          <?php else: ?>
            <span class="button secondary">CLI Only</span>
          <?php endif; ?>
          <?php if ($tool->docs()): ?>
            <a class="button secondary" href="<?= portal_esc($tool->docs() ?? ''); ?>" target="_blank" rel="noreferrer">Docs</a>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </section>

  <div class="empty-state" data-empty-state hidden>
    <h3>No tools found</h3>
    <p>Try clearing the search or picking another category.</p>
  </div>
</main>
<script src="<?= portal_asset('js/portal.js'); ?>" defer></script>
</body>
</html>
