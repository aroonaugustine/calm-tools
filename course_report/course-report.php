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

// -------------------------------------------------------------
// PROCESS UPLOAD
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv'])) {

    $tmp = $_FILES['csv']['tmp_name'];

    if (!file_exists($tmp)) {
        exit("Upload failed or file missing.");
    }

    // Parse CSV
    $rows = array_map('str_getcsv', file($tmp));
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
    <title>Identifier → LearnDash Report</title>
    <meta name="robots" content="noindex,nofollow">
    <link rel="stylesheet" href="/portal-assets/css/portal.css">
    <style>
        body { margin: 0; }
        main { padding: 32px 24px; max-width: 820px; }
        .tool-card { margin-bottom: 24px; }
        .tool-card h3 { margin-bottom: 12px; }
        .muted { color: var(--muted); }
        fieldset { border: 1px solid var(--border); border-radius: var(--radius); padding: 20px; margin-top: 20px; background: var(--card-bg); }
        legend { font-weight: 700; padding: 0 8px; }
        label { display: block; margin-bottom: 12px; font-weight: 600; }
        input[type=file] { width: 100%; padding: 12px; border: 1px dashed var(--border); border-radius: 10px; background: rgba(15,23,42,.02); cursor: pointer; }
        button { margin-top: 12px; padding: 12px 24px; border-radius: 999px; border: 1px solid var(--border); background: var(--accent); color: white; font-weight: 600; cursor: pointer; }
        button:hover { background: #1d4ed8; }
        ul { padding-left: 20px; }
        ul li { margin-bottom: 6px; }
        .callout { border-left: 3px solid var(--accent); padding: 12px 16px; border-radius: 10px; background: rgba(15,23,42,.04); margin-top: 16px; }
    </style>
</head>
<body>
    <main>
        <div class="tool-card">
            <h3>Passport / Username / Email → LearnDash Completion Report</h3>
            <p class="muted">Upload identifiers plus a target date and download an aggregated LearnDash completion report for the standard compliance courses.</p>

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
                    <legend>Upload CSV</legend>
                    <label>Choose CSV File
                        <input type="file" name="csv" accept=".csv" required>
                    </label>
                    <button type="submit">Generate Report</button>
                </fieldset>
            </form>
        </div>
    </main>
</body>
</html>