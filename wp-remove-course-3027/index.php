<?php
/**
 * Remove Course 3027 — UI v15.09.0002.0001
 * - Updated for correct DRY-RUN handling
 * - Supports username or email
 * - Same layout as resigned processor
 */

const AUTH_TOKEN = '6714e52aed21125dd999ff7c31666c1806e033aa2cb8a14073b41ae7026ec0b0';
$portal_token = trim((string)($_GET['token'] ?? ''));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Remove Course 3027 — Launcher (v15.09.0002.0001)</title>
<meta name="robots" content="noindex,nofollow">
<link rel="stylesheet" href="/portal-assets/css/portal.css">
  <link rel="stylesheet" href="/portal-assets/css/tool.css">
</head>
<body>

<main>
  <section class="hero tool-hero">
    <div>
      <h1>Remove Course 3027</h1>
      <p class="tool-card__lede">Strip LearnDash Course 3027 enrollments via CSV input. Does not alter groups or reset progress.</p>
    </div>
    <div class="tool-hero__meta">
      <span class="tool-hero__pill">v15.09.0002.0001</span>
    </div>
  </section>

  <div class="tool-card">

<form method="post" action="launcher.php" enctype="multipart/form-data">

  <?php if ($portal_token === ''): ?>
  <fieldset>
    <legend>Auth</legend>
    <label>Launcher Token
      <input type="password" name="token" required placeholder="Enter launcher token">
      <span class="token-hint">Launch this page from the CALM Admin Toolkit portal to auto-fill the token automatically.</span>
    </label>
  </fieldset>
  <?php else: ?>
  <input type="hidden" name="token" value="<?= htmlspecialchars($portal_token, ENT_QUOTES, 'UTF-8'); ?>">
  <?php endif; ?>

  <!-- CSV Source -->
  <fieldset>
    <legend>CSV Source</legend>

    <label>
      <input type="radio" name="csv_mode" value="server" checked>
      Use CSV from server path
    </label>

    <label>CSV Path on Server
      <input type="text" name="csv_path" placeholder="/srv/admin-tools/input.csv">
    </label>

    <label>
      <input type="radio" name="csv_mode" value="upload">
      Upload CSV File
    </label>

    <label>Upload CSV File
      <input type="file" name="csv_file" accept=".csv">
    </label>

  </fieldset>

  <!-- Match Options -->
  <fieldset>
    <legend>Match Options</legend>

    <label>Match Mode
      <select name="match_mode">
        <option value="strict">strict — must match username & email</option>
        <option value="email">email → fallback username</option>
        <option value="id">id only (NIC/Passport)</option>
      </select>
    </label>

    <label>Limit (0 = unlimited)
      <input type="number" name="limit" value="0" min="0">
    </label>

    <label>
  <input type="checkbox" name="dry_run" value="1" checked>
  <strong>DRY RUN (Preview only)</strong>
</label>
<p style="color:#900;font-size:13px;">
  Untick this to run LIVE (permanent changes will be applied).
</p>

  </fieldset>

  <!-- Batching -->
  <fieldset>
    <legend>Batching (optional)</legend>

    <label>Batch Size (0 = no batching)
      <input type="number" name="batch_size" value="0" min="0">
    </label>

    <label>Delay Between Batches (ms)
      <input type="number" name="batch_delay_ms" value="0" min="0">
    </label>

    <p style="font-size:13px; color:#666;">
      Example: Batch size 500, delay 250ms → avoids timeouts & reduces DB load.
    </p>

  </fieldset>

  <button type="submit">Launch Run</button>

</form>

  </div>
</main>

  <script src="/portal-assets/js/tool.js" defer></script>
</body>
</html>
