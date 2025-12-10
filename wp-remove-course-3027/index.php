<?php
/**
 * Remove Course 3027 — UI v1.0.3
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
<title>Remove Course 3027 — Launcher</title>
<meta name="robots" content="noindex,nofollow">
<style>
  body { font-family: system-ui, Arial, sans-serif; margin: 30px; max-width: 900px; }
  fieldset { border: 1px solid #ddd; border-radius: 10px; padding: 16px; margin-bottom: 20px; }
  legend { font-weight: bold; padding: 0 6px; }
  label { display: block; margin: 8px 0; }
  input[type=text], input[type=number], input[type=password], select {
    width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px;
  }
  button { padding: 10px 16px; border: 1px solid #333; background: #f7f7f7; border-radius: 6px; cursor: pointer; }
</style>
</head>
<body>

<h1>Remove Course 3027</h1>
<p>This tool removes <strong>LearnDash Course 3027</strong> from existing users, based on CSV input.<br>
It does <strong>NOT</strong> remove groups or reset progress.</p>

<form method="post" action="launcher.php" enctype="multipart/form-data">

  <!-- Auth -->
  <fieldset>
    <legend>Auth</legend>
    <?php if ($portal_token === ''): ?>
      <label>Launcher Token
        <input type="password" name="token" required placeholder="Enter launcher token">
      </label>
      <p style="color:#900;font-size:13px;margin:6px 0 0;">Launch this page from the CALM Admin Toolkit portal to auto-fill the token.</p>
    <?php else: ?>
      <input type="hidden" name="token" value="<?= htmlspecialchars($portal_token, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
  </fieldset>

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

</body>
</html>
