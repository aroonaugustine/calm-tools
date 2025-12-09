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
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>WP Meta Bulk Updater — v2.2.0</title>
<style>
body { font-family: Arial, sans-serif; max-width: 880px; margin: 40px auto; }
input, select, button { padding: 8px; width: 100%; margin-bottom: 12px; box-sizing: border-box; }
label { font-weight: bold; margin-top: 10px; display:block; }
button { background:#0073aa; color:white; border:none; cursor:pointer; padding:10px; }
button:hover { background:#005f8d; }
fieldset { border:1px solid #ccc; padding:20px; margin-top:16px; }
small { color:#555; display:block; margin-top:-8px; margin-bottom:10px; }
.row { display:flex; gap:16px; }
.col { flex:1; }
@media (max-width:700px){ .row{ flex-direction:column } }
.inline { display:flex; gap:10px; align-items:center; }
.badge { display:inline-block; padding:2px 6px; border-radius:4px; font-size:11px; background:#eef2ff; color:#3730a3; margin-left:4px; }
.helper-box { font-size:13px; background:#f9fafb; border:1px dashed #cbd5e1; border-radius:8px; padding:10px 12px; margin-top:8px; }
.helper-title { font-weight:bold; margin-bottom:6px; }
</style>
</head>
<body>
<h2>WP Meta Bulk Updater — v2.2.0</h2>
<p style="color:#374151;font-size:14px;">
  Supports users & posts, meta & core fields, conditional updates, dry run & live mode.
  Now includes <strong>LearnDash group-aware conditions</strong>.
</p>

<form method="POST">
  <label>Access Token</label>
  <input type="password" name="token" required>

  <label>Target Object</label>
  <select name="target_type">
    <option value="user">User</option>
    <option value="post">Post</option>
  </select>

  <label>Field To Change (Meta or Core)</label>
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

  <label>Search For (case-insensitive)</label>
  <input type="text" name="old_value" placeholder="Leave blank to match empty values">
  <div class="inline">
    <input type="checkbox" name="ignore_search" value="1" id="ignore_search">
    <label for="ignore_search" style="font-weight:normal;">Ignore search filter — apply to all objects that meet the Condition</label>
  </div>
  <small>
    If you check "Ignore search filter" the Search For field is ignored and the updater will consider ALL objects (users/posts) that satisfy the Condition.<br>
    If unchecked, leaving Search For blank will match empty values (current behaviour).
  </small>

  <label>Replace With (exact case)</label>
  <input type="text" name="new_value" placeholder="e.g. NETWORK WIZARD SDN BHD" required>

  <fieldset>
    <legend><strong>Condition (Only update when this is true)</strong></legend>

    <label>Condition Field</label>
    <div class="row">
      <div class="col"><input type="text" name="condition_field" placeholder="e.g. learndash_group_id or leave blank for LD operators"></div>
      <div class="col">
        <select name="condition_field_type">
          <option value="meta">Meta</option>
          <option value="core">Core</option>
        </select>
      </div>
    </div>

    <label>Condition Operator</label>
    <select name="condition_operator" id="condition_operator">
      <option value="equals">equals (case-insensitive exact)</option>
      <option value="contains">contains (case-insensitive substring)</option>
      <option value="empty">is empty</option>
      <option value="not_empty">is not empty</option>
      <option value="ld_group_member">LearnDash: user is in Group ID (Condition Value)</option>
      <option value="ld_in_any_groups">LearnDash: user is in ANY of these Group IDs (comma-separated)</option>
    </select>

    <label>Condition Value</label>
    <input type="text" name="condition_value" id="condition_value" placeholder="Value used with equals/contains or LearnDash Group ID(s)">

    <small>
      If Condition Field is left empty and you use a LearnDash operator, the tool will still check group membership using LearnDash.
      For non-LearnDash operators, leaving Condition Field blank means "no additional condition".
    </small>

<?php if (!empty($ld_groups)): ?>
    <div class="helper-box">
      <div class="helper-title">
        LearnDash Groups Helper <span class="badge">Optional</span>
      </div>
      <p style="margin:0 0 6px;font-size:12px;color:#4b5563;">
        Use this to quickly copy a LearnDash Group ID into the Condition Value field.
        Works best with operators:
        <strong>LearnDash: user is in Group ID</strong> or
        <strong>LearnDash: user is in ANY of these Group IDs</strong>.
      </p>
      <div class="row">
        <div class="col">
          <select id="ld_group_select">
            <option value="">— Select a LearnDash Group —</option>
            <?php foreach ($ld_groups as $g): ?>
              <option value="<?php echo (int)$g->ID; ?>">
                <?php echo (int)$g->ID; ?> — <?php echo esc_html(get_the_title($g)); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col" style="max-width:220px;">
          <button type="button" id="ld_copy_btn" style="width:100%;background:#10b981;">Copy ID → Condition Value</button>
        </div>
      </div>
      <small>
        For <strong>ld_in_any_groups</strong>, you can select one ID at a time and manually build a comma-separated list (e.g. <code>5284,5290,5310</code>).
      </small>
    </div>
<?php else: ?>
    <div class="helper-box">
      <div class="helper-title">
        LearnDash Groups Helper
      </div>
      <p style="margin:0;font-size:12px;color:#6b7280;">
        No <code>groups</code> post type found. If LearnDash is not active or groups are not set up, the helper list will be empty.
        You can still manually type Group IDs into the Condition Value field when using LearnDash operators.
      </p>
    </div>
<?php endif; ?>

  </fieldset>

  <label>Mode</label>
  <select name="mode">
    <option value="dry">Dry Run (no DB changes)</option>
    <option value="live">Live Update</option>
  </select>

  <button type="submit">Run Bulk Update</button>
</form>

<script>
// Simple JS helper to copy selected LearnDash group ID into Condition Value
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
      // Append or replace depending on operator
      var opSel = document.getElementById('condition_operator');
      var op    = opSel ? opSel.value : '';
      if (op === 'ld_in_any_groups' && condVal.value.trim() !== '') {
        // Append to comma-separated list
        var existing = condVal.value.trim().replace(/\s+/g, '');
        condVal.value = existing ? (existing + ',' + v) : v;
      } else {
        // Replace for single group
        condVal.value = v;
      }
    });
  }
});
</script>
</body>
</html>