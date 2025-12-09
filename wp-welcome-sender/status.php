<?php
// Align with LAUNCHER/WORKER path
const LOG_DIR = '/srv/admin-tools/wp-welcome-sender/_mailer-logs';

header('Content-Type: text/html; charset=utf-8');
// avoid stale caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$items = [];
if ($dh = @opendir(LOG_DIR)) {
  while (false !== ($e = readdir($dh))) {
    if ($e === '.' || $e === '..') continue;
    if (strpos($e, 'welcome_') !== 0) continue;
    $path = LOG_DIR . '/' . $e;
    if (!is_dir($path)) continue;
    $meta = @file_get_contents($path . '/run.json');
    $data = $meta ? json_decode($meta, true) : null;
    $items[] = ['id'=>$e, 'dir'=>$path, 'meta'=>$data];
  }
  closedir($dh);
}
usort($items, fn($a,$b) => strcmp($b['id'],$a['id']));
?>
<!doctype html>
<meta charset="utf-8">
<title>Welcome Sender — Runs & Logs</title>
<meta http-equiv="refresh" content="5">
<style>
  body{font-family:system-ui,Arial;margin:40px;max-width:1100px}
  table{border-collapse:collapse;width:100%}
  th,td{border:1px solid #ddd;padding:8px;text-align:left;vertical-align:top}
  th{background:#f6f6f6}
  .mono{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px;white-space:pre-wrap}
  .pill{display:inline-block;padding:2px 8px;border:1px solid #ddd;border-radius:999px;background:#f7f7f7;margin-right:6px}
</style>
<h1>Welcome Sender — Runs & Logs</h1>
<p><a href="./">← back to launcher</a></p>
<table>
  <tr><th>Run</th><th>Started (GMT)</th><th>PID</th><th>Status</th><th>Args</th><th>Files</th></tr>
  <?php foreach ($items as $it):
    $m = $it['meta'] ?: [];
    $stdout = $it['dir'] . '/stdout.log';
    $csv    = $it['dir'] . '/results.csv';
    $started = is_file($it['dir'].'/STARTED');
    $done    = is_file($it['dir'].'/DONE');
    $pid     = (string)($m['pid'] ?? '');
    $alive   = ($pid !== '' && ctype_digit($pid) && file_exists("/proc/$pid"));
  ?>
  <tr>
    <td><b><?=htmlspecialchars($it['id'])?></b></td>
    <td><?=htmlspecialchars($m['started_gmt'] ?? '')?></td>
    <td><?=htmlspecialchars($pid)?></td>
    <td>
      <?php if ($started): ?><span class="pill">STARTED</span><?php endif; ?>
      <?php if ($done): ?><span class="pill">DONE</span>
      <?php else: ?><span class="pill"><?= $alive ? 'RUNNING' : 'NOT RUNNING' ?></span><?php endif; ?>
    </td>
    <td class="mono"><?=htmlspecialchars(implode(' ', $m['args'] ?? []))?></td>
    <td>
      <?php if (is_file($stdout)): ?>
        <div>Stdout: <a href="tail.php?id=<?=urlencode($it['id'])?>">view tail</a> &nbsp;|&nbsp; <a href="raw.php?id=<?=urlencode($it['id'])?>">download</a></div>
      <?php else: ?>
        <div>Stdout: (pending)</div>
      <?php endif; ?>
      <?php if (is_file($csv)): ?>
        <div>Results CSV: <a href="csv.php?id=<?=urlencode($it['id'])?>">download</a></div>
      <?php else: ?>
        <div>Results CSV: (pending)</div>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>