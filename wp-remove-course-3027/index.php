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
<link rel="stylesheet" href="/portal-assets/css/portal.css">
<style>
  body { margin: 0; }
  main { padding: 32px 24px; max-width: 920px; }
  .tool-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: var(--radius); padding: 28px; }
  .tool-card h1 { margin-bottom: 8px; }
  .muted { color: var(--muted); }
  fieldset { border: 1px solid var(--border); border-radius: var(--radius); padding: 20px; margin: 22px 0; background: rgba(15,23,42,.015); }
  legend { font-weight: 700; padding: 0 8px; }
  label { display: block; margin: 12px 0; font-weight: 600; }
  input[type=text], input[type=number], input[type=password], select, input[type=file] { width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 10px; background: white; }
  input[type=radio], input[type=checkbox] { margin-right: 8px; }
  button { padding: 12px 24px; border-radius: 999px; border: 1px solid var(--border); background: var(--accent); color: white; font-weight: 600; cursor: pointer; }
  button:hover { background: #1d4ed8; }
  .token-hint { color: #b91c1c; font-size: 12px; margin-top: 6px; }
</style>
</head>
<body>

<main>
  <div class="tool-card">
    <h1>Remove Course 3027</h1>
    <p class="muted">Strip LearnDash Course 3027 enrollments via CSV input. Does not alter groups or reset progress.</p>

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

</body>
</html>
