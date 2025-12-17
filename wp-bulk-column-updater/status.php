<?php
/**
 * WP Bulk Column Updater — v15.09.0002.0001 status
 */
declare(strict_types=1);

// Shared bootstrap (so we can auth + show user info if needed)
require_once __DIR__ . '/_wp_bootstrap.php';
bcu141a_bootstrap_or_die();

// Optional shared auth
$auth_bootstrap = __DIR__ . '/../auth_bootstrap.php';
if (is_file($auth_bootstrap)) {
  require_once $auth_bootstrap;
  if (function_exists('require_login')) require_login('admin');
}

$RUN_ROOT = __DIR__ . '/runs';
@mkdir($RUN_ROOT, 0755, true);

$items = [];
if ($dh = opendir($RUN_ROOT)) {
  while (($e = readdir($dh)) !== false) {
    if ($e==='.'||$e==='..') continue;
    $path = $RUN_ROOT.'/'.$e;
    if (is_dir($path)) {
      $cfg = @file_get_contents($path.'/config.json');
      $sum = @file_get_contents($path.'/summary.json');
      $items[] = [
        'id'=>$e,
        'cfg'=>$cfg?json_decode($cfg,true):null,
        'sum'=>$sum?json_decode($sum,true):null,
        'ctime'=>@filectime($path)
      ];
    }
  }
  closedir($dh);
}
usort($items, fn($a,$b)=>($b['ctime']??0) <=> ($a['ctime']??0));

?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>WP Bulk Column Updater — Runs & Logs (v15.09.0002.0001)</title>
<meta name="robots" content="noindex,nofollow">
<style>
  body{font-family:system-ui,Arial,sans-serif;margin:32px;max-width:980px}
  table{width:100%;border-collapse:collapse}
  th,td{border:1px solid #e2e8f0;padding:8px;text-align:left}
  .muted{color:#64748b}
  a.button{display:inline-block;padding:8px 12px;border:1px solid #111;border-radius:8px;text-decoration:none;background:#f8fafc;margin-right:8px}
</style>
</head>
<body>
  <h1>WP Bulk Column Updater — Runs & Logs <span class="muted">v15.09.0002.0001</span></h1>
  <p><a class="button" href="index.html">← Back to Updater</a></p>
  <table>
    <thead>
      <tr><th>Run</th><th>Created</th><th>Mode</th><th>Processed</th><th>Updated</th><th>Links</th></tr>
    </thead>
    <tbody>
      <?php foreach ($items as $it):
        $id = htmlspecialchars($it['id'] ?? '');
        $cfg = $it['cfg'] ?? [];
        $sum = $it['sum'] ?? [];
        $created = htmlspecialchars($cfg['created_at'] ?? '');
        $live = !empty($cfg['live']);
        $proc = htmlspecialchars((string)($sum['rows_processed'] ?? ''));
        $upd  = htmlspecialchars((string)($sum['updated_count'] ?? ''));
      ?>
      <tr>
        <td><?= $id ?></td>
        <td><?= $created ?></td>
        <td><?= $live ? 'LIVE' : 'DRY' ?></td>
        <td><?= $proc ?></td>
        <td><?= $upd ?></td>
        <td>
          <a href="runs/<?= $id ?>/summary.json">summary.json</a> |
          <a href="runs/<?= $id ?>/log.ndjson">log.ndjson</a> |
          <a href="runs/<?= $id ?>/config.json">config.json</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</body>
</html>
