<?php
/**
 * Multi-Identifier → LearnDash Completion Report
 *
 * Accepts CSV with any of these columns:
 *   passport_no
 *   username
 *   email
 *   target_date   (required)
 *
 * Output CSV:
 *   identifier, date, user_login, email, division, status
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

// ---- LOAD WORDPRESS ----
require_once '/var/www/html/wp-load.php';   // <-- adjust if needed

global $wpdb;

// Required LearnDash Course IDs
$COURSE_IDS = [2871, 2869, 3027, 4216];
$manual_tmp = null;

// -------------------------------------------------------------
// PROCESS UPLOAD
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $input_mode = $_POST['input_mode'] ?? 'csv';
    $csvPath = '';

    if ($input_mode === 'manual') {
        $manual_raw = trim((string)($_POST['manual_list'] ?? ''));
        $manual_date = trim((string)($_POST['manual_date'] ?? ''));

        $lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $manual_raw)));
        $lines = array_slice($lines, 0, 10);

        if (empty($lines)) {
            exit("Please provide up to 10 email addresses or usernames (one per line).");
        }
        if ($manual_date === '') {
            exit("Target date is required for manual entries.");
        }

        $manual_tmp = tempnam(sys_get_temp_dir(), 'ccr_manual_');
        $fh = fopen($manual_tmp, 'w');
        if (!$fh) {
            exit("Failed to create temporary CSV.");
        }

        fputcsv($fh, ['username', 'email', 'target_date']);
        foreach ($lines as $line) {
            $isEmail = filter_var($line, FILTER_VALIDATE_EMAIL);
            $username = $isEmail ? '' : $line;
            $email    = $isEmail ? $line : '';
            fputcsv($fh, [$username, $email, $manual_date]);
        }
        fclose($fh);
        $csvPath = $manual_tmp;
        register_shutdown_function(function () use ($manual_tmp) {
            @unlink($manual_tmp);
        });

    } else {
        if (!isset($_FILES['csv'])) {
            exit("Upload failed or file missing.");
        }
        $tmp = $_FILES['csv']['tmp_name'];

        if (!file_exists($tmp)) {
            exit("Upload failed or file missing.");
        }
        $csvPath = $tmp;
    }

    // Parse CSV
    $rows = array_map('str_getcsv', file($csvPath));
    $header = array_map('trim', array_shift($rows));

    // Detect columns
    $idx_passport = array_search('passport_no', $header);
    $idx_username = array_search('username', $header);
    $idx_email    = array_search('email', $header);
    $idx_date     = array_search('target_date', $header);

    if ($idx_date === false) {
        exit("CSV MUST contain column: target_date");
    }

    // Normalize input rows
    $input = [];
    foreach ($rows as $r) {

        $passport = $idx_passport !== false ? trim($r[$idx_passport] ?? '') : '';
        $username = $idx_username !== false ? trim($r[$idx_username] ?? '') : '';
        $email    = $idx_email    !== false ? trim($r[$idx_email] ?? '') : '';
        $date     = trim($r[$idx_date] ?? '');

        $identifier = $passport ?: $username ?: $email;

        if (!$identifier) continue;

        $input[] = [
            'identifier' => $identifier,
            'passport'   => $passport,
            'username'   => $username,
            'email'      => $email,
            'date'       => $date
        ];
    }

    $results = [];

    // -------------------------------------------------------------
    // LOOKUP EACH IDENTIFIER
    // -------------------------------------------------------------
    foreach ($input as $row) {

        $id   = $row['identifier'];
        $date = $row['date'];

        $user_id = null;

        // 1. Search by passport_no (custom usermeta)
        if ($row['passport'] !== '') {
            $passport = $row['passport'];

            $user_id = $wpdb->get_var($wpdb->prepare(
                "SELECT user_id FROM {$wpdb->usermeta}
                 WHERE meta_key = 'passport_no'
                   AND meta_value = %s",
                $passport
            ));
        }

        // 2. Search by username
        if (!$user_id && $row['username'] !== '') {
            $user = get_user_by('login', $row['username']);
            if ($user) $user_id = $user->ID;
        }

        // 3. Search by email
        if (!$user_id && $row['email'] !== '') {
            $user = get_user_by('email', $row['email']);
            if ($user) $user_id = $user->ID;
        }

        // If still not found → return "User not found"
        if (!$user_id) {
            $results[] = [$id, $date, '', '', '', 'User not found'];
            continue;
        }

        // Fetch user object
        $user = get_user_by('id', $user_id);

        $login    = $user->user_login;
        $email    = $user->user_email;
        $division = get_user_meta($user_id, 'division', true);

        // -------------------------------------------------------------
        // CHECK LEARNDASH COURSE COMPLETION
        // -------------------------------------------------------------
        $complete = 0;

        foreach ($COURSE_IDS as $cid) {

            $status = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(activity_status)
                 FROM {$wpdb->prefix}learndash_user_activity
                 WHERE user_id = %d
                   AND activity_type = 'course'
                   AND course_id = %d",
                $user_id, $cid
            ));

            if (intval($status) === 1) {
                $complete++;
            }
        }

        if ($complete === count($COURSE_IDS)) {
            $statusLabel = 'Completed all courses';
        } elseif ($complete === 0) {
            $statusLabel = 'Not started any courses';
        } else {
            $statusLabel = 'Partially completed';
        }

        // Save row
        $results[] = [$id, $date, $login, $email, $division, $statusLabel];
    }

    // -------------------------------------------------------------
    // OUTPUT CSV
    // -------------------------------------------------------------
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="learndash-identifier-report.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['identifier', 'date', 'user_login', 'email', 'division', 'status']);

    foreach ($results as $r) {
        fputcsv($out, $r);
    }

    fclose($out);
    exit;
}

?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Identifier → LearnDash Report — v15.09.0002.0001</title>
    <meta name="robots" content="noindex,nofollow">
    <link rel="stylesheet" href="../portal-assets/css/portal.css">
  <link rel="stylesheet" href="../portal-assets/css/tool.css">
  <script>
    document.addEventListener('DOMContentLoaded', function(){
      const radios = document.querySelectorAll('input[name="input_mode"]');
      const csvWrap = document.getElementById('csv_wrap');
      const manualWrap = document.getElementById('manual_wrap');
      function sync(mode){
        csvWrap.style.display = mode === 'csv' ? '' : 'none';
        manualWrap.style.display = mode === 'manual' ? '' : 'none';
      }
      radios.forEach(r => r.addEventListener('change', e => sync(e.target.value)));
      const checked = document.querySelector('input[name="input_mode"]:checked');
      if (checked) sync(checked.value);
    });
  </script>
</head>
<body class="portal-tool-body">
    <main class="portal-tool-shell">
        <section class="hero tool-hero">
            <div>
                <h1>Passport / Username / Email → LearnDash Completion Report</h1>
                <p class="tool-card__lede">Upload identifiers plus a target date and download an aggregated LearnDash completion report for the standard compliance courses.</p>
            </div>
            <div class="tool-hero__meta">
                <span class="tool-hero__pill">v15.09.0002.0001</span>
            </div>
        </section>
        <div class="tool-card">
            <div class="callout">
                <strong>CSV columns supported</strong>
                <ul>
                    <li><code>passport_no</code> — custom user meta</li>
                    <li><code>username</code> — WordPress login</li>
                    <li><code>email</code> — WordPress account email</li>
                    <li><code>target_date</code> — required for each row</li>
                </ul>
            </div>

            <form method="post" enctype="multipart/form-data">
                <fieldset>
                    <legend>Input</legend>
                    <label class="row">
                      <input type="radio" name="input_mode" value="csv" checked> Upload CSV
                    </label>
                    <div id="csv_wrap">
                      <label>Choose CSV File
                          <input type="file" name="csv" accept=".csv">
                      </label>
                      <div class="small muted">CSV requires a <code>target_date</code> column plus at least one of <code>passport_no</code>, <code>username</code>, or <code>email</code>.</div>
                    </div>
                    <div style="height:12px"></div>
                    <label class="row">
                      <input type="radio" name="input_mode" value="manual"> Manual list (up to 10 usernames/emails)
                    </label>
                    <div id="manual_wrap" style="display:none">
                      <label>Identifiers (one per line, max 10)
                        <textarea name="manual_list" rows="6" placeholder="e.g. alice@example.com&#10;or username123"></textarea>
                      </label>
                      <label>Target date for all entries
                        <input type="date" name="manual_date">
                      </label>
                      <div class="small muted">We’ll generate a tiny CSV for this run and apply the date above to every identifier.</div>
                    </div>
                    <div class="tool-actions">
                      <button type="submit">Generate Report</button>
                    </div>
                </fieldset>
            </form>
        </div>
    </main>
  <script src="../portal-assets/js/tool.js" defer></script>
</body>
</html>
