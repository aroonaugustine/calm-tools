<?php
/**
 * CALM Mailer — Status viewer v15.09.0002.0001
 * - Lists runs if no ?id
 * - With ?id=RUN shows live stdout tail, PID status, CSV link
 */
declare(strict_types=1);
const LOG_BASE = '/srv/admin-tools/wp-mailer/_mailer-logs';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function runs(): array { $d=@glob(LOG_BASE.'/run_*', GLOB_ONLYDIR)?:[]; rsort($d); return $d; }

$id = preg_replace('/[^A-Za-z0-9_:-]/','', $_GET['id'] ?? '');

if ($id==='') {
  header('Content-Type: text/html; charset=utf-8'); ?>
  <!doctype html><meta charset="utf-8"><title>CALM Mailer — Runs</title>
  <style>body{font-family:system-ui,Arial;margin:40px;max-width:900px} code{background:#f7f7f7;padding:2px 4px;border-radius:4px} ul{line-height:1.7}</style>
  <h2>CALM Mailer — Runs</h2>
  <p>Base: <code><?=h(LOG_BASE)?></code></p>
  <ul>
  <?php foreach (runs() as $d): $bn=basename($d); ?>
    <li><a href="?id=<?=h($bn)?>"><?=h($bn)?></a></li>
  <?php endforeach; if (!runs()): ?><li><em>No runs yet.</em></li><?php endif; ?>
  </ul>
  <?php exit;
}

$run_dir = LOG_BASE.'/'.$id;
$stdout  = $run_dir.'/stdout.log';
$done    = is_file($run_dir.'/DONE');
$meta    = @json_decode((string)@file_get_contents($run_dir.'/run.json'), true) ?: [];
$pid     = (string)($meta['pid'] ?? '');
$alive   = ($pid!=='' && ctype_digit($pid) && file_exists("/proc/$pid"));
$csv     = (string)($meta['log_csv'] ?? '');

header('Content-Type: text/html; charset=utf-8'); ?>
<!doctype html>
<meta charset="utf-8">
<title>Status — <?=h($id)?></title>
<?php if(!$done): ?><meta http-equiv="refresh" content="3"><?php endif; ?>
<style>
  body{font-family:system-ui,Arial;margin:24px;max-width:1100px}
  .row{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:10px 0}
  pre{background:#111;color:#eee;padding:12px;border-radius:8px;overflow:auto;max-height:70vh}
  .pill{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid #ddd;margin-right:8px;background:#f7f7f7}
  a.btn{display:inline-block;padding:8px 12px;border:1px solid #333;border-radius:8px;background:#fafafa;margin-right:8px;text-decoration:none;color:#222}
  .muted{color:#666} code{background:#f7f7f7;padding:2px 4px;border-radius:4px}
</style>

<p><a class="btn" href="<?=h($_SERVER['PHP_SELF'])?>">← Back to Runs</a></p>
<h2>Run: <?=h($id)?></h2>
<div class="muted">Folder: <code><?=h($run_dir)?></code></div>

<div class="row">
  <?php if (is_file($csv)): ?>
    <a class="btn" href="<?=h(str_replace($_SERVER['DOCUMENT_ROOT'],'',$csv))?>" download>Download log.csv</a>
  <?php else: ?>
    <span class="pill">log.csv (pending)</span>
  <?php endif; ?>
  <span class="pill"><?= $done ? 'DONE' : ($alive ? "RUNNING (pid ".h($pid).")" : "NOT RUNNING") ?></span>
  <?php if (isset($meta['campaign'])): ?><span class="pill">Campaign: <?=h($meta['campaign'])?></span><?php endif; ?>
</div>

<h3>Live stdout</h3>
<pre><?php
if (is_file($stdout)) {
  $max = 512*1024; $sz = filesize($stdout); $fh = fopen($stdout,'r');
  if ($fh){ if ($sz>$max) fseek($fh, -$max, SEEK_END); fpassthru($fh); fclose($fh); }
  else echo h("Unable to open stdout.log");
} else echo h("No stdout yet. Waiting…");
?></pre>
