<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * WP Meta Bulk Updater — v2.2.0
 * - Adds LearnDash-aware condition operators:
 *   - ld_group_member      => user is member of a single LearnDash Group ID
 *   - ld_in_any_groups     => user is member of ANY of the given Group IDs (comma-separated)
 * - Adds LearnDash Groups helper UI (dropdown + copy ID into Condition Value).
 */

require_once '/var/www/html/wp-load.php';

$token = '6714e52aed21125dd999ff7c31666c1806e033aa2cb8a14073b41ae7026ec0b0'; // change this to your own
$portal_token = trim((string)($_GET['token'] ?? ''));

// Preload LearnDash groups for helper UI (if available)
$ld_groups = [];
if (post_type_exists('groups')) {
    $ld_groups = get_posts([
        'post_type'      => 'groups',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'title',
        'order'          => 'ASC',
        'fields'         => 'all',
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['token']) || $_POST['token'] !== $token) {
        die('Invalid token.');
    }

    // Target object type: user or post
    $target_type = $_POST['target_type'] ?? 'user';

    // Field to change (meta or core)
    $field_name = trim($_POST['field_name'] ?? '');
    $field_type = $_POST['field_type'] ?? 'meta'; // 'meta' or 'core'

    // Value to search for in the target field (case-insensitive substring).
    // Empty means match empty values.
    // If ignore_search is set, we will ignore this filter and update all objects that meet the condition.
    $old_value     = trim($_POST['old_value'] ?? '');
    $ignore_search = !empty($_POST['ignore_search']);

    $new_value = trim($_POST['new_value'] ?? '');

    // Condition definition: only change target when this condition matches
    $condition_field        = trim($_POST['condition_field'] ?? '');
    $condition_field_type   = $_POST['condition_field_type'] ?? 'meta'; // 'meta' or 'core'
    $condition_operator     = $_POST['condition_operator'] ?? 'equals'; // equals, contains, empty, not_empty, ld_group_member, ld_in_any_groups
    $condition_value        = trim($_POST['condition_value'] ?? '');

    $mode    = $_POST['mode'] ?? 'dry';
    $dry_run = ($mode === 'dry');

    // Validation
    if ($field_name === '' || $new_value === '') {
        die('Target field and new value are required.');
    }
    $allowed_ops = ['equals', 'contains', 'empty', 'not_empty', 'ld_group_member', 'ld_in_any_groups'];
    if (!in_array($condition_operator, $allowed_ops, true)) {
        die('Invalid condition operator.');
    }

    $run_dir = __DIR__ . '/logs';
    if (!is_dir($run_dir)) {
        mkdir($run_dir, 0775, true);
    }

    require_once __DIR__ . '/worker.php';
    [$summary_path, $log_path, $csv_path] = wpmbu220_run([
        'target_type'  => $target_type,
        'field_name'   => $field_name,
        'field_type'   => $field_type,
        'old_value'    => $old_value,
        'ignore_search'=> $ignore_search,
        'new_value'    => $new_value,
        'condition'    => [
            'field'      => $condition_field,
            'field_type' => $condition_field_type,
            'operator'   => $condition_operator,
            'value'      => $condition_value,
        ],
        'live' => !$dry_run,
    ], $run_dir);

    echo "<h3>Process complete</h3>";
    echo "<p><strong>Summary:</strong> <a href='logs/" . htmlspecialchars(basename($summary_path)) . "' target='_blank'>summary.json</a></p>";
    echo "<p><strong>Log:</strong> <a href='logs/" . htmlspecialchars(basename($log_path)) . "' target='_blank'>log.ndjson</a></p>";
    echo "<p><strong>Changes (CSV):</strong> <a href='logs/" . htmlspecialchars(basename($csv_path)) . "' target='_blank'>changes.csv</a></p>";
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>WP Meta Bulk Updater — v2.2.0</title>
<meta name="robots" content="noindex,nofollow">
<link rel="stylesheet" href="/portal-assets/css/portal.css">
  <link rel="stylesheet" href="/portal-assets/css/tool.css">
</head>
<body>
  <main>
    <div class="tool-card">
      <header class="tool-card__header">
        <h1>WP Meta Bulk Updater</h1>
        <span class="tool-card__version">v2.2.0</span>
        <p class="tool-card__lede">Supports users &amp; posts, meta &amp; core fields, conditional updates, and LearnDash-aware operators. Dry-run or go live once ready.</p>
      </header>

      <form method="post">
        <fieldset>
          <legend>Auth</legend>
          <?php if ($portal_token === ''): ?>
            <label>Access Token
              <input type="password" name="token" placeholder="Enter access token" required>
              <span class="token-hint">Launch this tool via the CALM Admin Toolkit portal to auto-fill the access token.</span>
            </label>
          <?php else: ?>
            <input type="hidden" name="token" value="<?= htmlspecialchars($portal_token, ENT_QUOTES, 'UTF-8'); ?>">
            <p class="muted">Access token supplied via portal link.</p>
          <?php endif; ?>
        </fieldset>

        <fieldset>
          <legend>Target Object</legend>
          <label>Object Type
            <select name="target_type">
              <option value="user">User</option>
              <option value="post">Post</option>
            </select>
          </label>

          <label>Field To Change</label>
          <div class="row">
            <div class="col">
              <input type="text" name="field_name" placeholder="e.g. division, display_name" required>
            </div>
            <div class="col">
              <select name="field_type">
                <option value="meta">Meta</option>
                <option value="core">Core</option>
              </select>
            </div>
          </div>

          <label>Search For (case-insensitive)
            <input type="text" name="old_value" placeholder="Leave blank to match empty values">
          </label>
          <label class="inline">
            <input type="checkbox" name="ignore_search" value="1" id="ignore_search">
            Ignore search filter — apply to all objects that meet the Condition
          </label>
          <p class="muted">
            When "Ignore search filter" is checked the Search For field is ignored and the updater considers all objects that satisfy the Condition.
            Leaving Search For blank while unchecked matches empty values.
          </p>

          <label>Replace With (exact case)
            <input type="text" name="new_value" placeholder="e.g. NETWORK WIZARD SDN BHD" required>
          </label>
        </fieldset>

        <fieldset>
          <legend>Condition (Only update when this is true)</legend>
          <label>Condition Field</label>
          <div class="row">
            <div class="col">
              <input type="text" name="condition_field" placeholder="e.g. learndash_group_id or leave blank for LD operators">
            </div>
            <div class="col">
              <select name="condition_field_type">
                <option value="meta">Meta</option>
                <option value="core">Core</option>
              </select>
            </div>
          </div>

          <label>Condition Operator
            <select name="condition_operator" id="condition_operator">
              <option value="equals">equals (case-insensitive exact)</option>
              <option value="contains">contains (case-insensitive substring)</option>
              <option value="empty">is empty</option>
              <option value="not_empty">is not empty</option>
              <option value="ld_group_member">LearnDash: user is in Group ID (Condition Value)</option>
              <option value="ld_in_any_groups">LearnDash: user is in ANY of these Group IDs (comma-separated)</option>
            </select>
          </label>

          <label>Condition Value
            <input type="text" name="condition_value" id="condition_value" placeholder="Value used with equals/contains or group IDs">
          </label>
          <p class="muted">LearnDash operators still work if Condition Field is empty. For other operators, leaving it blank means no additional condition.</p>

          <?php if (!empty($ld_groups)): ?>
            <div class="helper-box">
              <div class="helper-title">LearnDash Groups Helper</div>
              <p class="muted" style="margin:0 0 8px;">Pick a group and copy its ID into the Condition Value field.</p>
              <div class="row">
                <div class="col">
                  <select id="ld_group_select">
                    <option value="">— Select a LearnDash Group —</option>
                    <?php foreach ($ld_groups as $g): ?>
                      <option value="<?= (int)$g->ID; ?>"><?= (int)$g->ID; ?> — <?= esc_html(get_the_title($g)); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col" style="max-width:220px;">
                  <button type="button" id="ld_copy_btn">Copy ID → Condition Value</button>
                </div>
              </div>
              <p class="muted" style="margin:8px 0 0;">For <strong>ld_in_any_groups</strong>, click multiple times to build a comma-separated list (<code>5284,5290,5310</code>).</p>
            </div>
          <?php else: ?>
            <div class="helper-box">
              <div class="helper-title">LearnDash Groups Helper</div>
              <p class="muted" style="margin:0;">No <code>groups</code> post type found. Manually type Group IDs into Condition Value when needed.</p>
            </div>
          <?php endif; ?>
        </fieldset>

        <fieldset>
          <legend>Mode</legend>
          <label>Run Mode
            <select name="mode">
              <option value="dry">Dry Run (no DB changes)</option>
              <option value="live">Live Update</option>
            </select>
          </label>
        </fieldset>

        <div class="button-row">
          <button type="submit">Run Bulk Update</button>
        </div>
      </form>
    </div>
  </main>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var ldSelect = document.getElementById('ld_group_select');
  var ldBtn    = document.getElementById('ld_copy_btn');
  var condVal  = document.getElementById('condition_value');
  if (ldSelect && ldBtn && condVal) {
    ldBtn.addEventListener('click', function () {
      var v = ldSelect.value;
      if (!v) {
        alert('Please select a LearnDash group first.');
        return;
      }
      var opSel = document.getElementById('condition_operator');
      var op    = opSel ? opSel.value : '';
      if (op === 'ld_in_any_groups' && condVal.value.trim() !== '') {
        var existing = condVal.value.trim().replace(/\s+/g, '');
        condVal.value = existing ? (existing + ',' + v) : v;
      } else {
        condVal.value = v;
      }
    });
  }
});
</script>
  <script src="/portal-assets/js/tool.js" defer></script>
</body>
</html>
