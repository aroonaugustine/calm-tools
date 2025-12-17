<?php
/**
 * LearnDash — Finish remaining steps for a user (verbose, token-protected)
 * Version: v15.09.0002.0001 (broader quiz discovery + robust 100% writeback)
 *
 * Browser usage (token required):
 *   /admin-tools/wp-ld-tools/finish_user.php?token=...&user=<login_or_email>&dry_run=1
 *   /admin-tools/wp-ld-tools/finish_user.php?token=...&user=<login_or_email>&course_id=2869
 *
 * What’s new in v15.09.0002.0001
 * - Discovers quizzes at the course level and under ALL lessons/topics.
 * - Writes a full 100% record via ld_update_quiz_data() if available,
 *   otherwise falls back to legacy _sfwd-quizzes meta + activity rows.
 * - Verifies completion via learndash_is_item_complete() OR activity rows.
 */

declare(strict_types=1);

// ==========================
// AUTH — set your tokens here
// ==========================
const LD_FINISHER_TOKENS = [
  // label         => token value
  'owner'        => '6714e52aed21125dd999ff7c31666c1806e033aa2cb8a14073b41ae7026ec0b0',
  // 'ops'        => 'CHANGE_ME_OPS_TOKEN',
];

// ==========================
// PATH to wp-load.php
// ==========================
const WP_LOAD = '/var/www/html/wp-load.php'; // adjust if needed

// ==========================
// Minimal auth helper
// ==========================
function finisher_auth_ok(string $provided): array {
  $candidate = $provided !== '' ? $provided : (string)($_SERVER['HTTP_X_LD_FINISHER_TOKEN'] ?? '');
  if ($candidate === '') return [false, ''];
  foreach (LD_FINISHER_TOKENS as $label => $secret) {
    if (hash_equals($secret, $candidate)) return [true, (string)$label];
  }
  return [false, ''];
}

header('Content-Type: text/plain; charset=utf-8');

$token  = (string)($_GET['token'] ?? '');
[$ok, $who] = finisher_auth_ok($token);
if (!$ok) { http_response_code(401); echo "Unauthorized\n"; exit; }

if (!is_file(WP_LOAD)) {
  http_response_code(500);
  echo "wp-load.php not found at ".WP_LOAD."\n";
  exit;
}

require WP_LOAD;

// Be verbose + resilient
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
@ini_set('memory_limit', '512M');
if (function_exists('set_time_limit')) @set_time_limit(0);

// Print last fatal if we die
register_shutdown_function(function(){
  $e = error_get_last();
  if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
    echo "\n---\nFATAL: {$e['message']} in {$e['file']}:{$e['line']}\n";
  }
});

// ==========================
// Inputs
// ==========================
$user_str = trim((string)($_GET['user'] ?? ''));
$dry_run  = isset($_GET['dry_run']) ? (int)$_GET['dry_run'] : 0;
$courseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

if ($user_str === '') { http_response_code(400); echo "Missing user param (login or email)\n"; exit; }

// Resolve user by email first, then login
$u = is_email($user_str) ? get_user_by('email', $user_str) : null;
if (!$u) $u = get_user_by('login', $user_str);
if (!$u) { echo "❌ User not found: {$user_str}\n"; exit; }

// ==========================
// Header
// ==========================
echo "🚀 LD Finisher v15.09.0002.0001\n";
echo "🔐 Token label: {$who}\n";
echo "👤 User: {$u->user_login} (ID {$u->ID}) | Email: {$u->user_email}\n";
echo $dry_run ? "👀 DRY RUN (no writes)\n" : "✍️ LIVE MODE (writes enabled)\n";

// ==========================
// Helpers
// ==========================
function total_questions_for_quiz(int $quiz_post_id): int {
  global $wpdb;
  $pro_id = (int) get_post_meta($quiz_post_id, 'quiz_pro_id', true);
  if ($pro_id <= 0) $pro_id = $quiz_post_id;

  $table = $wpdb->prefix . 'wp_pro_quiz_question';
  $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
  if (!$exists) return 1;

  $cnt = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE quiz_id = %d", $pro_id));
  return max(1, $cnt);
}

/** Is quiz complete either by LD API or activity table? */
function is_quiz_complete_any(int $user_id, int $quiz_id, int $course_id): bool {
  if (function_exists('learndash_is_item_complete')) {
    if (learndash_is_item_complete($user_id, $quiz_id, $course_id)) return true;
  }
  if (function_exists('learndash_get_user_activity')) {
    $act = learndash_get_user_activity([
      'user_id'       => $user_id,
      'post_id'       => $quiz_id,
      'course_id'     => $course_id,
      'activity_type' => 'quiz',
    ]);
    if ($act && !is_wp_error($act)) {
      // On LD4, completed = 1
      if (!empty($act->activity_status)) return true;
    }
  }
  return false;
}

/** Fallback: update legacy _sfwd-quizzes array for this user */
function legacy_sfwd_quizzes_append(
  int $user_id, int $quiz_post_id, int $course_id, int $score, int $count,
  int $points, int $total_points, int $percentage, bool $dry_run
): void {
  $pro_id = (int) get_post_meta($quiz_post_id, 'quiz_pro_id', true);
  if ($pro_id <= 0) $pro_id = $quiz_post_id;

  $entry = [
    'quiz'          => $quiz_post_id,
    'pro_quizid'    => $pro_id,
    'score'         => $score,          // # correct
    'count'         => $count,          // # answered
    'pass'          => true,
    'rank'          => '-',
    'time'          => time(),
    'percentage'    => $percentage,
    'points'        => $points,
    'total_points'  => $total_points,
    'course'        => $course_id,
  ];

  if ($dry_run) {
    echo "      (dry) legacy _sfwd-quizzes append: " . json_encode($entry) . "\n";
    return;
  }

  $arr = get_user_meta($user_id, '_sfwd-quizzes', true);
  if (!is_array($arr)) $arr = [];
  $arr[] = $entry;
  update_user_meta($user_id, '_sfwd-quizzes', $arr);
}

/** Write a 100% pass using best available methods */
function record_quiz_pass_100(int $user_id, int $quiz_post_id, int $course_id, int $total_questions, bool $dry_run): bool {
  $now = time();
  $points = $total_questions;
  $pro_id = (int) get_post_meta($quiz_post_id, 'quiz_pro_id', true);
  if ($pro_id <= 0) $pro_id = $quiz_post_id;

  $quizdata = [
    'quiz'          => $quiz_post_id,
    'score'         => $total_questions,
    'count'         => $total_questions,
    'pass'          => true,
    'rank'          => '-',
    'time'          => $now,
    'pro_quizid'    => $pro_id,
    'points'        => $points,
    'total_points'  => $points,
    'percentage'    => 100,
  ];

  $ok = false;

  if ($dry_run) {
    echo "      (dry) ld_update_quiz_data payload: " . json_encode($quizdata) . "\n";
    $ok = true;
  } else {
    if (function_exists('ld_update_quiz_data')) {
      ld_update_quiz_data($user_id, $quizdata, $course_id);
      $ok = true;
    } else {
      // Fallback: legacy user meta + activity rows
      legacy_sfwd_quizzes_append($user_id, $quiz_post_id, $course_id, $total_questions, $total_questions, $points, $points, 100, false);
      $ok = true;
    }
  }

  // Ensure quiz step is marked complete
  if ($dry_run) {
    echo "      (dry) learndash_process_mark_complete quiz #{$quiz_post_id}\n";
  } else {
    if (function_exists('learndash_process_mark_complete')) {
      learndash_process_mark_complete($user_id, $quiz_post_id, false, $course_id);
    }
  }

  // Ensure activity row reflects completion & stats
  if ($dry_run) {
    echo "      (dry) learndash_update_user_activity + meta (quiz stats)\n";
  } else {
    if (function_exists('learndash_update_user_activity')) {
      $act_id = learndash_update_user_activity([
        'user_id'            => $user_id,
        'post_id'            => $quiz_post_id,
        'course_id'          => $course_id,
        'activity_type'      => 'quiz',
        'activity_status'    => 1,
        'activity_started'   => $now,
        'activity_completed' => $now,
        'activity_meta'      => [],
      ]);
      if ($act_id && function_exists('learndash_update_user_activity_meta')) {
        learndash_update_user_activity_meta($act_id, 'quiz_score',          $total_questions);
        learndash_update_user_activity_meta($act_id, 'quiz_count',          $total_questions);
        learndash_update_user_activity_meta($act_id, 'quiz_points_scored',  $points);
        learndash_update_user_activity_meta($act_id, 'quiz_total_points',   $points);
        learndash_update_user_activity_meta($act_id, 'quiz_percentage',     100);
      }
    }
  }

  return $ok;
}

/** Gather ALL quizzes for a course: course-level, lesson-level, topic-level */
function gather_all_quizzes_for_course(int $course_id, int $user_id): array {
  $quizzes = [];

  // (A) Course-level quizzes
  if (function_exists('learndash_get_course_quiz_list')) {
    $qs = (array) learndash_get_course_quiz_list($course_id, $user_id);
    foreach ($qs as $q) {
      $post = is_array($q) && isset($q['post']) ? $q['post'] : (is_object($q) ? $q : null);
      if ($post && $post instanceof WP_Post) $quizzes[$post->ID] = $post;
    }
  }

  // Collect lessons (LD4 & LD3-safe)
  $lesson_ids = [];
  if (function_exists('learndash_get_course_lessons_list')) {
    $ls = (array) learndash_get_course_lessons_list($course_id, $user_id);
    foreach ($ls as $l) {
      $lp = is_array($l) && isset($l['post']) ? $l['post'] : (is_object($l) ? $l : null);
      if ($lp && $lp instanceof WP_Post) $lesson_ids[] = (int) $lp->ID;
    }
  }

  // (B) Lesson-level quizzes
  foreach ($lesson_ids as $lid) {
    if (function_exists('learndash_get_lesson_quiz_list')) {
      $lqs = (array) learndash_get_lesson_quiz_list($lid, $user_id, $course_id);
      foreach ($lqs as $q) {
        $post = is_array($q) && isset($q['post']) ? $q['post'] : (is_object($q) ? $q : null);
        if ($post && $post instanceof WP_Post) $quizzes[$post->ID] = $post;
      }
    }
  }

  // (C) Topic-level quizzes: get topics for each lesson, then topic quizzes
  if (function_exists('learndash_get_topic_list')) {
    foreach ($lesson_ids as $lid) {
      $topics = (array) learndash_get_topic_list($lid, $course_id);
      foreach ($topics as $t) {
        $tp = is_array($t) && isset($t['post']) ? $t['post'] : (is_object($t) ? $t : null);
        if (!$tp || !($tp instanceof WP_Post)) continue;

        $tid = (int) $tp->ID;
        if (function_exists('learndash_get_topic_quiz_list')) {
          $tqs = (array) learndash_get_topic_quiz_list($tid, $user_id, $course_id);
          foreach ($tqs as $q) {
            $post = is_array($q) && isset($q['post']) ? $q['post'] : (is_object($q) ? $q : null);
            if ($post && $post instanceof WP_Post) $quizzes[$post->ID] = $post;
          }
        }
      }
    }
  } else {
    // Fallback older LD: learndash_get_lesson_topics_list
    if (function_exists('learndash_get_lesson_topics_list')) {
      foreach ($lesson_ids as $lid) {
        $topics = (array) learndash_get_lesson_topics_list($lid, $course_id);
        foreach ($topics as $tp) {
          $tid = is_object($tp) && isset($tp->ID) ? (int)$tp->ID : (is_array($tp) && isset($tp['post']->ID) ? (int)$tp['post']->ID : 0);
          if ($tid > 0 && function_exists('learndash_get_topic_quiz_list')) {
            $tqs = (array) learndash_get_topic_quiz_list($tid, $user_id, $course_id);
            foreach ($tqs as $q) {
              $post = is_array($q) && isset($q['post']) ? $q['post'] : (is_object($q) ? $q : null);
              if ($post && $post instanceof WP_Post) $quizzes[$post->ID] = $post;
            }
          }
        }
      }
    }
  }

  // Final array of WP_Post quizzes
  return array_values($quizzes);
}

// ==========================
// Collect user courses (direct + via groups)
// ==========================
$courses = [];
if (function_exists('learndash_user_get_enrolled_courses')) {
  $courses = (array) learndash_user_get_enrolled_courses($u->ID);
} elseif (function_exists('ld_get_mycourses')) {
  $courses = (array) ld_get_mycourses($u->ID);
}
if (function_exists('learndash_get_users_group_ids') && function_exists('learndash_group_enrolled_courses')) {
  foreach ((array) learndash_get_users_group_ids($u->ID) as $gid) {
    $courses = array_merge($courses, (array) learndash_group_enrolled_courses($gid));
  }
}
$courses = array_values(array_unique(array_map('intval', $courses)));

if ($courseId) {
  if (!in_array($courseId, $courses, true)) {
    echo "⚠️ Course {$courseId} not in user’s enrollments; will try anyway.\n";
  }
  $courses = [$courseId];
}

if (empty($courses)) {
  echo "ℹ️ No courses found for user.\n";
  exit;
}

echo "📚 Courses to process: " . implode(', ', $courses) . "\n\n";

// ==========================
// Process each course
// ==========================
foreach ($courses as $cid) {
  $ctitle = get_the_title($cid) ?: "(Course {$cid})";
  echo "=== 📘 {$ctitle} (ID {$cid}) ===\n";

  // 1) Steps
  $step_ids = [];
  if (function_exists('learndash_get_course_steps')) {
    $step_ids = (array) learndash_get_course_steps($cid);
    $step_ids = array_map('intval', $step_ids);
  } else {
    if (function_exists('learndash_get_course_lessons_list')) {
      foreach ((array) learndash_get_course_lessons_list($cid, $u->ID) as $lesson) {
        if (is_array($lesson) && isset($lesson['post']->ID)) $step_ids[] = (int) $lesson['post']->ID;
      }
    }
    if (function_exists('learndash_get_course_topics_list')) {
      foreach ((array) learndash_get_course_topics_list($cid, 0, $u->ID) as $topic) {
        if (is_object($topic) && isset($topic->ID)) $step_ids[] = (int) $topic->ID;
      }
    }
    $step_ids = array_values(array_unique($step_ids));
  }

  // 2) Quizzes (course + lessons + topics)
  $quiz_posts = gather_all_quizzes_for_course($cid, $u->ID);

  echo "   • Steps found: ".count($step_ids)."\n";
  echo "   • Quizzes found (all levels): ".count($quiz_posts)."\n";

  // 3) Mark incomplete steps complete
  $completed_steps = 0; $skipped_steps = 0;
  foreach ($step_ids as $pid) {
    $ptitle = get_the_title($pid) ?: "(Post {$pid})";
    $is_done = function_exists('learndash_is_item_complete')
      ? (bool) learndash_is_item_complete($u->ID, $pid, $cid)
      : false;

    if ($is_done) {
      $skipped_steps++;
      continue;
    }

    echo "      ➕ Mark complete: {$ptitle} (#{$pid})\n";
    if (!$dry_run && function_exists('learndash_process_mark_complete')) {
      learndash_process_mark_complete($u->ID, $pid, false, $cid);
    }
    $completed_steps++;
  }

  // 4) Record quiz passes
  $completed_quizzes = 0; $skipped_quizzes = 0;
  foreach ($quiz_posts as $qp) {
    if (!($qp instanceof WP_Post)) continue;
    $qid = (int) $qp->ID;
    $qtitle = $qp->post_title ?: "(Quiz {$qid})";

    if (is_quiz_complete_any($u->ID, $qid, $cid)) {
      $skipped_quizzes++;
      continue;
    }

    $total_questions = total_questions_for_quiz($qid);
    echo "      📝 Record 100% pass: {$qtitle} (#{$qid}) — total questions: {$total_questions}\n";
    $ok_pass = record_quiz_pass_100($u->ID, $qid, $cid, $total_questions, (bool)$dry_run);

    // Re-check completion from both angles
    $now_complete = is_quiz_complete_any($u->ID, $qid, $cid);
    if ($ok_pass || $now_complete) {
      $completed_quizzes++;
    } else {
      echo "      ⚠️ Could not verify completion for quiz #{$qid}\n";
    }
  }

  // 5) Nudge course completion if needed
  $course_is_done = function_exists('learndash_course_completed')
    ? (bool) learndash_course_completed($u->ID, $cid)
    : false;

  if (!$course_is_done && !$dry_run && function_exists('learndash_process_mark_complete')) {
    echo "   • Course not yet complete; nudging…\n";
    learndash_process_mark_complete($u->ID, $cid, true, $cid);
    $course_is_done = function_exists('learndash_course_completed')
      ? (bool) learndash_course_completed($u->ID, $cid)
      : false;
  }

  // Progress %
  $pct = 0;
  if (function_exists('learndash_course_progress')) {
    $p = learndash_course_progress(['user_id'=>$u->ID,'course_id'=>$cid,'array'=>true]);
    if (is_array($p) && isset($p['percentage'])) {
      $pct = is_numeric($p['percentage'])
        ? (int) round((float)$p['percentage'])
        : (int) round((float) str_replace('%','', (string)$p['percentage']));
    } elseif (is_array($p) && isset($p['completed'], $p['total']) && (int)$p['total'] > 0) {
      $pct = (int) round(((int)$p['completed'] / (int)$p['total']) * 100);
    }
  }

  echo "   ✅ Steps completed this run: {$completed_steps}, quizzes passed: {$completed_quizzes}\n";
  echo "   ⏭️ Steps already complete: {$skipped_steps}, quizzes already passed: {$skipped_quizzes}\n";
  echo "   📊 Progress now: {$pct}% | Course complete? ".($course_is_done ? 'YES' : 'NO')."\n\n";
}

echo $dry_run ? "🏁 DRY RUN done (no writes).\n" : "🎉 Finished (writes applied where needed).\n";
