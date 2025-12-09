<?php
/**
 * ONE-USER LEARNDASH PROGRESS REPAIR
 * User: saengkaewsom (ID 5276)
 */

require_once '/var/www/html/wp-load.php';

$user_id = 5276;

// === Step counts per course ===
$progress = [
    2869 => 7,
    2871 => 20,
    3027 => 11,
    4216 => 10,
];

// Use latest quiz completion timestamp as completion time
global $wpdb;
$completion_times = [];

foreach ($progress as $cid => $_) {
    $ts = $wpdb->get_var($wpdb->prepare(
        "SELECT MAX(activity_completed)
         FROM {$wpdb->prefix}learndash_user_activity
         WHERE user_id=%d
           AND course_id=%d
           AND activity_type='quiz'
           AND activity_completed IS NOT NULL
           AND activity_completed > 0",
        $user_id, $cid
    ));
    if (!$ts) {
        // fallback: now
        $ts = time();
    }
    $completion_times[$cid] = intval($ts);
}

// -------------------------------
// Build _sfwd-course_progress
// -------------------------------
$course_progress = [];

foreach ($progress as $cid => $steps_completed) {
    $course_progress[$cid] = [
        'course'  => $cid,
        'steps'   => $steps_completed,
        'completed' => true,
        'timestamp' => $completion_times[$cid],
        'last_id' => $cid,   // last step ID (best effort)
    ];
}

update_user_meta($user_id, '_sfwd-course_progress', $course_progress);

// -------------------------------
// Add completion meta per course
// -------------------------------
foreach ($completion_times as $cid => $ts) {

    update_user_meta($user_id, "course_completed_{$cid}", $ts);
    update_user_meta($user_id, "learndash_course_completed_{$cid}", $ts);
    update_user_meta($user_id, "completed_{$cid}", 1);   // legacy
}

// -------------------------------
// Cleanup LD caches
// -------------------------------
delete_user_meta($user_id, 'learndash-last-check');
delete_user_meta($user_id, '_sfwd-course_progress_cache');

// -------------------------------
echo "Repair complete for user_id={$user_id}\n";
echo "You can now view the LearnDash profile.\n";