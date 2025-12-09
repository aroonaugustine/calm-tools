<?php
/**
 * LearnDash Duplicate User Batch Check Tool with Refined Name Match & Compact Output
 * ----------------------------------------------------------------
 * Save as: /var/www/html/tools/ld-duplicate-batch.php
 * Visit: http://yourdomain/tools/ld-duplicate-batch.php
 *
 * Paste a CSV (username,division). You get a compact report for each username/division,
 * matches on similar names in that division, optimized for saving as PDF.
 */

define('WP_USE_THEMES', false);
require_once('/var/www/html/wp-load.php'); // MODIFY path if WP in other folder!

$courses = array(
    'Port City BPO Induction' => 2871,
    'Anti-Bribery and Anti-Corruption' => 4216,
    'Prevention of Sexual Harassment' => 2869,
    'Pre-Arrival and Essential Information' => 3027
);

// --------- Refined name match --------
function name_word_match($name1, $name2) {
    $w1 = array_filter(explode(' ', strtolower(trim($name1))));
    $w2 = array_filter(explode(' ', strtolower(trim($name2))));
    if (!$w1 || !$w2) return false;
    $common = array_intersect($w1, $w2);
    $min_words = min(count($w1), count($w2));
    return count($common) >= 2 && count($common) >= ceil($min_words / 2);
}

/**
 * Detect if two users are likely the SAME human.
 */
function is_same_human($userA, $userB) {

    // ------------ Normalize fields ------------
    $a_first = strtolower(trim($userA->first_name));
    $b_first = strtolower(trim($userB->first_name));

    $a_last  = strtolower(trim($userA->last_name));
    $b_last  = strtolower(trim($userB->last_name));

    $a_disp = strtolower(trim($userA->display_name));
    $b_disp = strtolower(trim($userB->display_name));

    $a_email = strtolower(trim($userA->user_email));
    $b_email = strtolower(trim($userB->user_email));

    $a_login = strtolower(trim($userA->user_login));
    $b_login = strtolower(trim($userB->user_login));

    // ------------ Quick full name logic ------------
    if ($a_first && $b_first && $a_first === $b_first) {

        // If last name missing on both OR matching
        if ((!$a_last && !$b_last) || ($a_last && $b_last && $a_last === $b_last)) {
            return true;
        }

        // If display names share 80% similarity
        if (similar_text($a_disp, $b_disp, $pct) && $pct >= 80) {
            return true;
        }
    }

    // ------------ Username similarity ------------
    $strip = fn($x) => preg_replace('/[^a-z]/', '', strtolower($x));

    $rootA = $strip($a_login);
    $rootB = $strip($b_login);

    if ($rootA && $rootB) {
        if (similar_text($rootA, $rootB, $pct) && $pct >= 70) {
            return true;
        }
    }

    // ------------ Email similarity ------------
    $normalize_email = function($email) {
        // remove dots & +extensions from local part
        if (!str_contains($email, '@')) return $email;

        list($local, $domain) = explode('@', $email, 2);
        $local = str_replace('.', '', $local);
        $local = preg_replace('/\+.*/', '', $local);

        // fix common domain typos
        $domain = str_replace(
            ['gmai.com','gmal.com','gmial.com','outloo.com','hotmial.com'],
            ['gmail.com','gmail.com','gmail.com','outlook.com','hotmail.com'],
            $domain
        );

        return $local . '@' . $domain;
    };

    $normA = $normalize_email($a_email);
    $normB = $normalize_email($b_email);

    if ($normA === $normB) return true;

    // Fuzzy match email local parts
    list($la,) = explode('@', $normA);
    list($lb,) = explode('@', $normB);

    if (similar_text($la, $lb, $pct) && $pct >= 70) {
        return true;
    }

    // ------------ ALL checks failed ------------
    return false;
}

// ------------------ HTML Form ------------------
if (empty($_POST['csv_data'])) {
    echo <<<HTML
<form method="post" style="font-size:1em">
    <label for="csv_data">Paste CSV (username,division):<br>
        <textarea name="csv_data" rows="12" cols="46"></textarea>
    </label>
    <br><br>
    <button type="submit" style="font-size:1em">Generate Report</button>
</form>
HTML;
    exit;
}

// --------- Utility functions ----------
function get_meta($user_id) {
    $meta = array();
    $meta['passport_no'] = get_user_meta($user_id, 'passport_no', true);
    $meta['nic_no'] = get_user_meta($user_id, 'nic_no', true);
    $meta['company'] = get_user_meta($user_id, 'company', true);
    $meta['division'] = get_user_meta($user_id, 'division', true);
    return $meta;
}

function get_ld_group_id($user_id) {
    $group_ids = function_exists('learndash_get_users_group_ids') ? (array)learndash_get_users_group_ids($user_id) : [];
    return !empty($group_ids) ? implode(',', array_map('intval', $group_ids)) : '';
}
function group_names($id_csv) {
    $ids = array_filter(array_map('intval', explode(',', $id_csv)));
    $names = [];
    foreach ($ids as $gid) {
        $gpost = get_post($gid);
        if ($gpost && !empty($gpost->post_title)) $names[] = $gpost->post_title;
    }
    return implode('; ', $names);
}
function course_percent($user_id, $course_id) {
    global $wpdb;
    $total_steps = function_exists('learndash_get_course_steps_count') ? learndash_get_course_steps_count($course_id) : 0;
    $user_steps = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}learndash_user_activity WHERE user_id = %d AND course_id = %d AND activity_status = 1 AND activity_type IN ('lesson','topic','quiz')",
        $user_id, $course_id
    ));
    $course_completed = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}learndash_user_activity WHERE user_id = %d AND course_id = %d AND activity_type = 'course' AND activity_status = 1",
        $user_id, $course_id
    ));
    if ($course_completed > 0) return 100;
    if (!$total_steps) return 0;
    return round(($user_steps / $total_steps) * 100);
}

// --------- Parse CSV input ----------
$csv_raw = $_POST['csv_data'];
$rows = array_filter(array_map('trim', explode("\n", $csv_raw)));
$user_tasks = [];
foreach ($rows as $line) {
    $fields = str_getcsv($line);
    if (count($fields) >= 2) {
        $user_tasks[] = array('username' => trim($fields[0]), 'division' => trim($fields[1]));
    }
}
if (!$user_tasks) {
    echo "<p>No valid entries found in CSV data.</p>";
    exit;
}

// --------- Process each username/division ---------
global $wpdb;
echo '<div style="font-size:0.92em;">';
foreach ($user_tasks as $task) {
    $username = $task['username'];
    $division = $task['division'];

    $user = get_user_by('login', $username);
    if (!$user) {
        echo "<h3 style='color:#c00'>Username $username not found.</h3>";
        continue;
    }
    $full_name = trim($user->first_name . ' ' . $user->last_name);
    $display_name = trim($user->display_name);

    $all_matches = [];
    $users_query = new WP_User_Query(array(
    'role'       => 'subscriber',   // 🚀 NEW: Only subscribers
    'meta_query' => array(
        array(
            'key'     => 'division',
            'value'   => $division,
            'compare' => '='
        )
    )
));
    foreach ($users_query->get_results() as $u) {
        $test_full = trim($u->first_name . ' ' . $u->last_name);
        if (
    name_word_match($u->display_name, $display_name) ||
    name_word_match($u->display_name, $full_name) ||
    name_word_match($test_full, $display_name) ||
    name_word_match($test_full, $full_name) ||
    is_same_human($user, $u)     // <-- NEW powerful check
) {
            $meta = get_meta($u->ID);
            $all_matches[] = array(
                'user_id' => $u->ID,
                'user_login' => $u->user_login,
                'full_name' => $test_full,
                'user_email' => $u->user_email,
                'company' => $meta['company'],
                'division' => $meta['division'],
                'passport_no' => $meta['passport_no'],
                'nic_no' => $meta['nic_no']
            );
        }
    }

    echo "<h3 style='font-size:1em'>Input: <span style='color:#226'>$username</span> / <span style='color:#226'>$division</span></h3>";

    // Compact table
    echo "<table border='1' cellpadding='3' style='border-collapse:collapse;margin-bottom:1.2em;width:auto;'>";
    echo "<tr style='background:#f1f1f1'>
    <th>user_login</th>
    <th>group</th>
    <th>group_name</th>
    <th>full_name</th>
    <th>user_email</th>
    <th>company</th>
    <th>division</th>
    <th>passport_no</th>
    <th>nic_no</th>";
    foreach ($courses as $ctitle => $cid) {
        echo "<th>$ctitle&nbsp;%</th>";
    }
    echo "<th>incomplete_any</th></tr>";

    foreach ($all_matches as $row) {
        $group_ids_csv = get_ld_group_id($row['user_id']);
        $group_name = group_names($group_ids_csv);

        echo "<tr>";
        echo "<td>{$row['user_login']}</td>";
        echo "<td>" . htmlspecialchars($group_ids_csv) . "</td>";
        echo "<td>" . htmlspecialchars($group_name) . "</td>";
        echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['user_email']) . "</td>";
        echo "<td>" . htmlspecialchars($row['company']) . "</td>";
        echo "<td>" . htmlspecialchars($row['division']) . "</td>";
        echo "<td>" . htmlspecialchars($row['passport_no']) . "</td>";
        echo "<td>" . htmlspecialchars($row['nic_no']) . "</td>";
        $any_incomplete = false;
        foreach ($courses as $ctitle => $cid) {
            $pct = course_percent($row['user_id'], $cid);
            echo "<td>" . ($pct === '' ? '-' : $pct) . "</td>";
            if ($pct < 100) $any_incomplete = true;
        }
        echo "<td align='center' style='color:" . ($any_incomplete ? "#c00" : "#090") . ";font-weight:bold;'>" . ($any_incomplete ? "YES" : "NO") . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    if (count($all_matches) === 0) {
        echo "<p style='margin-bottom:1.2em;font-size:1em;'><b>No users found with similar name in division '<span style='color:#c00'>$division</span>'.</b></p>";
    }
}
echo '</div>';
?>