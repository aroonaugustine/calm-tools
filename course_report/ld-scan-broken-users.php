#!/usr/bin/env php
<?php
/**
 * LearnDash Broken Progress Scanner — CRASH-PROTECTED VERSION
 */

define('SHORTINIT', true);
define('WP_DISABLE_FATAL_ERROR_HANDLER', true);


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);
ini_set('memory_limit', '1024M');
set_time_limit(0);

// Load minimal WordPress config
require_once '/var/www/html/wp-config.php';

global $table_prefix;
if (empty($table_prefix)) {
    $table_prefix = 'wp_';
}

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if ($mysqli->connect_errno) {
    fwrite(STDERR, "MySQL connect error: " . $mysqli->connect_error . "\n");
    exit(1);
}
$mysqli->set_charset('utf8mb4');

$users_table    = $table_prefix . 'users';
$usermeta_table = $table_prefix . 'usermeta';
$ld_table       = $table_prefix . 'learndash_user_activity';

$cap_key = $table_prefix . 'capabilities';

$COURSE_IDS = [2871,2869,3027,4216];

// Count subscribers
$stmt_total = $mysqli->prepare("
    SELECT COUNT(*) FROM {$users_table} u
    JOIN {$usermeta_table} m ON u.ID = m.user_id
    WHERE m.meta_key = ?
      AND m.meta_value LIKE '%subscriber%'
");
$stmt_total->bind_param('s', $cap_key);
$stmt_total->execute();
$stmt_total->bind_result($total_subs);
$stmt_total->fetch();
$stmt_total->close();

echo "[DEBUG] Total subscribers: {$total_subs}\n";

// Output CSV
$csv_file = '/tmp/ld-broken-users.csv';
if (!is_dir(dirname($csv_file))) {
    mkdir(dirname($csv_file), 0755, true);
}
$csv = fopen($csv_file, 'w');
fputcsv($csv, ['user_id','user_login','email','course_id','ld_steps','actual_steps']);

$user_sql = "
    SELECT u.ID, u.user_login, u.user_email
    FROM {$users_table} u
    JOIN {$usermeta_table} m ON u.ID = m.user_id
    WHERE m.meta_key = ?
      AND m.meta_value LIKE '%subscriber%'
    ORDER BY u.ID
    LIMIT ? OFFSET ?
";
$stmt_users = $mysqli->prepare($user_sql);

$stmt_meta = $mysqli->prepare("
    SELECT meta_value
    FROM {$usermeta_table}
    WHERE user_id = ?
      AND meta_key = '_sfwd-course_progress'
    LIMIT 1
");

$stmt_act = $mysqli->prepare("
    SELECT COUNT(*)
    FROM {$ld_table}
    WHERE user_id = ?
      AND course_id = ?
      AND activity_type IN ('lesson','topic','quiz')
      AND activity_status = 1
");

$offset = 0;
$batch = 300;
$broken_count = 0;

while ($offset < $total_subs) {

    $stmt_users->bind_param('sii', $cap_key, $batch, $offset);
    $stmt_users->execute();
    $res = $stmt_users->get_result();

    if ($res->num_rows === 0) break;

    while ($u = $res->fetch_assoc()) {

        $uid   = (int)$u['ID'];
        $login = $u['user_login'];
        $email = $u['user_email'];

        $progress_raw = '';
        $stmt_meta->bind_param('i', $uid);
        $stmt_meta->execute();
        $stmt_meta->bind_result($progress_raw);
        $stmt_meta->fetch();
        $stmt_meta->free_result();

        // safe unserialize
        $progress = [];
        if ($progress_raw) {

            $tmp = @unserialize($progress_raw, ['allowed_classes'=>false]);

            if (is_array($tmp)) {
                $progress = $tmp;
            } else {
                $tmp = json_decode($progress_raw, true);
                if (is_array($tmp)) $progress = $tmp;
            }
        }

        foreach ($COURSE_IDS as $cid) {

            $ld_steps = 0;

            if (isset($progress[$cid])) {
                if (isset($progress[$cid]['steps'])) {
                    $ld_steps = (int)$progress[$cid]['steps'];
                } elseif (isset($progress[$cid]['completed']) && is_array($progress[$cid]['completed'])) {
                    $ld_steps = count($progress[$cid]['completed']);
                }
            }

            $stmt_act->bind_param('ii', $uid, $cid);
            $stmt_act->execute();
            $stmt_act->bind_result($actual_steps);
            $stmt_act->fetch();
            $stmt_act->free_result();

            $actual_steps = (int)$actual_steps;

            if ($actual_steps > 0 && $ld_steps === 0) {
                $broken_count++;
                fputcsv($csv, [$uid,$login,$email,$cid,$ld_steps,$actual_steps]);
            }
        }
    }

    $offset += $batch;
}

fclose($csv);
echo "DONE. Broken: {$broken_count} → {$csv_file}\n";