<?php
/**
 * Universal Status Viewer v1.0.4
 *
 * Supports:
 *   - resigned_* runs
 *   - remove3027_* runs
 *
 * Fixes:
 *   - CSV download “file not found”
 *   - Proper run directory matching
 */

declare(strict_types=1);

const LOG_BASE = '/srv/admin-tools/_mailer-logs';

/* ---------- Helpers ---------- */
function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function run_dirs(): array {
  // Support both patterns
  $dirs1 = @glob(LOG_BASE . '/resigned_*',   GLOB_ONLYDIR) ?: [];
  $dirs2 = @glob(LOG_BASE . '/remove3027_*', GLOB_ONLYDIR) ?: [];
  
  $dirs = array_merge($dirs1, $dirs2);
  rsort($dirs);
  
  return $dirs;
}

/* ---------- Route ---------- */
$id = preg_replace('/[^A-Za-z0-9_:-]/', '', $_GET['id'] ?? '');

/* ---------- List View ---------- */
if ($id === '') {
  header('Content-Type: text/html; charset=utf-8'); ?>
  <!doctype html>
  <meta charset="utf-8">
  <title>Processor — Runs</title>
  <style>
    body { font-family: system-ui, Arial, sans-serif; margin: 40px; max-width: 900px }
    code { background: #f7f7f7; padding: 2px 4px; border-radius: 4px }
    ul { line-height: 1.7 }
  </style>

  <h2>Processor — Runs</h2>
  <p>Logs base: <code><?=h(LOG_BASE)?></code></p>

  <ul>
    <?php foreach (run_dirs() as $d): $bn = basename($d); ?>
      <li><a href="?id=<?=h($bn)?>"><?=h($bn)?></a></li>
    <?php endforeach; ?>
    <?php if (!run_dirs()) : ?>
      <li><em>No runs yet.</em></li>
    <?php endif; ?>
  </ul>

  <?php exit;
}

/* ---------- Single Run View ---------- */

$run_dir = LOG_BASE . '/' . $id;
$stdout  = $run_dir . '/stdout.log';
$matched = $run_dir . '/matched_done.csv';
$unmatch = $run_dir . '/unmatched_skipped.csv';

$started = is_file($run_dir . '/STARTED');
$done    = is_file($run_dir . '/DONE');

/* load run.json metadata */
$pid = '';
$meta = [];
$run_json = $run_dir . '/run.json';

if (is_file($run_json)) {
  $raw = @file_get_contents($run_json);
  $meta = json_decode((string)$raw, true) ?: [];
  $pid = (string)($meta['pid'] ?? '');
}

$alive = ($pid !== '' && ctype_digit($pid) && file_exists("/proc/$pid"));

header('Content-Type: text/html; charset=utf-8'); ?>

<!doctype html>
<meta charset="utf-8">
<title>Status — <?=h($id)?></title>
<?php if (!$done): ?><meta http-equiv="refresh" content="3"><?php endif; ?>

<style>
  body{font-family:system-ui,Arial,sans-serif;margin:24px;max-width:1100px}
  .row{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:10px 0}
  pre{background:#111;color:#eee;padding:12px;border-radius:8px;overflow:auto;max-height:70vh}
  .pill{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid #ddd;margin-right:8px;background:#f7f7f7}
  a.btn{display:inline-block;padding:8px 12px;border:1px solid #333;border-radius:8px;background:#fafafa;margin-right:8px;text-decoration:none;color:#222}
  .muted{color:#666}
  code{background:#f7f7f7;padding:2px 4px;border-radius:4px}
  table.meta{border-collapse:collapse;margin:10px 0}
  table.meta td{border:1px solid #ddd;padding:6px 8px}
  .hdr{background:#f7f7f7;font-weight:700}
</style>

<p><a class="btn" href="<?=h($_SERVER['PHP_SELF'])?>">← Back to Runs</a></p>

<h2>Run: <?=h($id)?></h2>
<div class="muted">Folder: <code><?=h($run_dir)?></code></div>

<div class="row">
  <?php if (is_file($matched)): ?>
    <a class="btn" href="csv.php?id=<?=urlencode($id)?>&t=matched">
      Download matched_done.csv
    </a>
  <?php else: ?>
    <span class="pill">matched_done.csv (pending)</span>
  <?php endif; ?>

  <?php if (is_file($unmatch)): ?>
    <a class="btn" href="csv.php?id=<?=urlencode($id)?>&t=unmatched">
      Download unmatched_skipped.csv
    </a>
  <?php else: ?>
    <span class="pill">unmatched_skipped.csv (pending)</span>
  <?php endif; ?>

  <?php if ($started): ?><span class="pill">STARTED</span><?php endif; ?>
  <?php if ($done): ?>
     <span class="pill">DONE</span>
  <?php else: ?>
     <span class="pill"><?= $alive ? "RUNNING (pid ".h($pid).")" : "NOT RUNNING" ?></span>
  <?php endif; ?>
</div>

<?php if ($meta): ?>
  <h3>Metadata</h3>
  <table class="meta">
    <tr><td class="hdr">PID</td><td><?=h($pid ?: '(unknown)')?></td></tr>
    <tr><td class="hdr">Started (GMT)</td><td><?=h($meta['started_gmt'] ?? '(unknown)')?></td></tr>
    <tr><td class="hdr">CSV</td><td><code><?=h($meta['csv'] ?? '(unknown)')?></code></td></tr>
    <tr><td class="hdr">Stdout</td><td><code><?=h($meta['stdout'] ?? $stdout)?></code></td></tr>
    <tr><td class="hdr">Match mode</td><td><code><?=h($meta['match_mode'] ?? '(unknown)')?></code></td></tr>
    <tr><td class="hdr">Batching</td>
      <td>size=<code><?=h((string)($meta['batch_size'] ?? 0))?></code>,
          delay_ms=<code><?=h((string)($meta['batch_delay_ms'] ?? 0))?></code></td></tr>
    <tr><td class="hdr">Args</td>
      <td><code><?=h(implode(' ', $meta['args'] ?? []))?></code></td></tr>
  </table>
<?php endif; ?>

<h3>Live stdout</h3>
<pre><?php
$stdout = $meta['stdout'] ?? $stdout;
if (is_file($stdout)) {
  $max = 512 * 1024;
  $sz  = filesize($stdout);
  $fh  = fopen($stdout, 'r');
  if ($fh) {
    if ($sz > $max) fseek($fh, -$max, SEEK_END);
    fpassthru($fh);
    fclose($fh);
  } else {
    echo h("Unable to open stdout.log");
  }
} else {
  echo h("No stdout yet. Waiting…");
}
?></pre>