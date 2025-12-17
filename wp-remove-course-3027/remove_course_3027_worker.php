<?php
/**
 * Remove Course 3027 — Worker v15.09.0002.0001
 *
 * Modes:
 *   CSV mode (only mode)
 *      --csv=/path.csv
 *      --match=strict|email|id   (default: email)
 *      --dry-run                 (preview only)
 *      --limit=N                 (max rows to process; 0 = all)
 *
 * Output files:
 *      --matched=/path/matched.csv
 *      --unmatched=/path/unmatched.csv
 *
 * Matching Rules (per your choices):
 *   A = A3 (strict):
 *       - username AND email must both match
 *       - if one is missing, or they point to different users => unmatched
 *
 *   B = B2 (email):
 *       - try email first
 *       - if not found, fallback to username
 *
 *   C = C2 (id):
 *       - try PASSPORT first (passport_no / passport / pp_no)
 *       - if not found, fallback to NIC (nic_no / nic / national_id)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!defined('WP_DISABLE_FATAL_ERROR_HANDLER')) {
    define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
}

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        fwrite(STDERR, "FATAL: {$e['message']} in {$e['file']}:{$e['line']}\n");
    }
});

require '/var/www/html/wp-load.php'; // adjust if needed

// ====== CONSTANTS ======
const COURSE_ID_3027 = 3027;

// ====== ARG PARSING ======
$argv_assoc = [];
foreach ($argv ?? [] as $a) {
    if (preg_match('/^--([^=]+)=(.*)$/', $a, $m)) {
        $argv_assoc[$m[1]] = $m[2];
    } elseif (preg_match('/^--(.+)$/', $a, $m)) {
        $argv_assoc[$m[1]] = true;
    }
}

$csv_file       = (string)($argv_assoc['csv'] ?? '');
$matched_file   = (string)($argv_assoc['matched'] ?? '');
$unmatched_file = (string)($argv_assoc['unmatched'] ?? '');
$dry_run        = !empty($argv_assoc['dry-run']);
$limit          = max(0, (int)($argv_assoc['limit'] ?? 0));
$match_policy   = strtolower((string)($argv_assoc['match'] ?? 'email'));

if (!in_array($match_policy, ['strict', 'email', 'id'], true)) {
    $match_policy = 'email';
}

if ($csv_file === '' || !is_file($csv_file)) {
    fwrite(STDERR, "❌ CSV missing or not found: {$csv_file}\n");
    exit(2);
}
if ($matched_file === '' || $unmatched_file === '') {
    fwrite(STDERR, "❌ Output file paths (--matched, --unmatched) are required\n");
    exit(2);
}

echo "🚀 Remove Course 3027 Worker — DRY RUN: " . ($dry_run ? 'YES' : 'NO') . " — Match: {$match_policy}\n";

// ====== HELPERS ======

/**
 * Detect CSV delimiter roughly (comma vs tab).
 */
function detect_delim_smart(string $path): string {
    $sample = @file_get_contents($path, false, null, 0, 8192);
    if ($sample === false || $sample === '') {
        return ',';
    }
    $line = strtok($sample, "\r\n");
    if ($line === false) {
        return ',';
    }
    $cComma = substr_count($line, ',');
    $cTab   = substr_count($line, "\t");
    return ($cTab > $cComma) ? "\t" : ',';
}

/**
 * Normalize header labels: lowercase, collapse spaces, remove weird chars.
 */
function norm_header_label(string $s): string {
    $s = trim($s);
    // Strip UTF-8 BOM if present
    $s = preg_replace('/^\xEF\xBB\xBF/u', '', $s);
    $s = strtolower($s);
    $s = preg_replace('/\s+/', ' ', $s);
    $s = str_replace(['_', '-'], ' ', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    return trim($s);
}

/**
 * Find a column index in header array by trying a list of possible labels.
 * Returns int index or null.
 */
function find_col(?array $header, array $candidates): ?int {
    if (!is_array($header)) {
        return null;
    }
    $norm = [];
    foreach ($header as $i => $h) {
        $norm[$i] = norm_header_label((string)$h);
    }

    foreach ($candidates as $cand) {
        $target = norm_header_label($cand);
        foreach ($norm as $i => $hNorm) {
            if ($hNorm === $target) {
                return $i;
            }
        }
    }
    return null;
}

/**
 * Safe row value getter.
 */
function get_val(array $row, ?int $idx): string {
    if ($idx === null) return '';
    return isset($row[$idx]) ? trim((string)$row[$idx]) : '';
}

/**
 * Clean ID-like values (NIC / Passport) to [a-z0-9].
 */
function clean_id(string $s): string {
    return preg_replace('/[^a-z0-9]/i', '', strtolower($s));
}

/**
 * Check if user is enrolled in course 3027.
 */
function user_has_course_3027(int $user_id): bool {
    $course_id = COURSE_ID_3027;
    $courses = [];

    if (function_exists('learndash_user_get_enrolled_courses')) {
        $courses = (array) learndash_user_get_enrolled_courses($user_id);
    } elseif (function_exists('learndash_get_users_courses')) {
        $courses = (array) learndash_get_users_courses($user_id, true);
    }

    // Some LD setups store keys as ints, some as strings; cast to int for safety
    foreach ($courses as $cid) {
        if ((int)$cid === (int)$course_id) {
            return true;
        }
    }
    return false;
}

/**
 * List group IDs that include course 3027.
 *
 * @return int[]
 */
function course_3027_group_ids(): array {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = [];

    if (function_exists('learndash_get_groups_for_course')) {
        $cache = array_map('intval', (array) learndash_get_groups_for_course(COURSE_ID_3027));
        return $cache;
    }

    if (!function_exists('learndash_group_enrolled_courses')) {
        return $cache;
    }

    $group_post_type = function_exists('learndash_get_post_type_slug')
        ? learndash_get_post_type_slug('group')
        : 'groups';

    $groups = get_posts([
        'post_type'      => $group_post_type,
        'post_status'    => 'any',
        'numberposts'    => -1,
        'fields'         => 'ids',
        'suppress_filters' => true,
    ]);

    foreach ($groups as $gid) {
        $courses = (array) learndash_group_enrolled_courses((int)$gid);
        foreach ($courses as $course_id) {
            if ((int)$course_id === (int) COURSE_ID_3027) {
                $cache[] = (int)$gid;
                break;
            }
        }
    }

    $cache = array_values(array_unique($cache));
    return $cache;
}

/**
 * Remove the user from any groups that provide course 3027.
 *
 * @return int[] IDs of groups the user was (or would be) removed from.
 */
function remove_user_from_course_3027_groups(int $user_id, bool $dry_run): array {
    if (!function_exists('learndash_get_users_group_ids')) {
        return [];
    }

    $course_groups = course_3027_group_ids();
    if (empty($course_groups)) {
        return [];
    }

    $user_groups = array_map('intval', (array) learndash_get_users_group_ids($user_id));
    $targets = array_values(array_intersect($user_groups, $course_groups));

    if ($dry_run || empty($targets) || !function_exists('ld_update_group_access')) {
        return $targets;
    }

    foreach ($targets as $gid) {
        ld_update_group_access($user_id, $gid, 'remove');
    }

    return $targets;
}

/**
 * Remove access to course 3027 and detach from related groups.
 *
 * @return array{attempted:bool, removed:bool, status:string, groups_removed:int[]}
 */
function user_remove_course_3027(int $user_id, bool $dry_run): array {
    $result = [
        'attempted' => false,
        'removed'   => false,
        'status'    => 'not_enrolled',
        'groups_removed' => [],
    ];

    if (!user_has_course_3027($user_id)) {
        return $result;
    }

    $result['attempted'] = true;
    $result['groups_removed'] = remove_user_from_course_3027_groups($user_id, $dry_run);

    if ($dry_run) {
        $result['removed'] = true;
        $result['status'] = 'dry_run';
        return $result;
    }

    // If groups removal already stripped access, skip redundant unenroll.
    if (user_has_course_3027($user_id)) {
        if (!function_exists('ld_update_course_access')) {
            $result['status'] = 'ld_function_missing';
            return $result;
        }
        ld_update_course_access($user_id, COURSE_ID_3027, true);
    }

    if (!user_has_course_3027($user_id)) {
        $result['removed'] = true;
        $result['status'] = 'removed';
    } else {
        $result['status'] = empty($result['groups_removed']) ? 'still_enrolled' : 'still_enrolled_via_group';
    }

    return $result;
}

/**
 * Matching logic according to your A3 / B2 / C2 choices.
 *
 * @return WP_User|null
 */
function find_matching_user(
    string $match_policy,
    string $login,
    string $email,
    string $nic,
    string $passport,
    ?string &$reason = null
) {
    $reason = null;

    // ID-only mode (C2): Passport first → fallback NIC
    if ($match_policy === 'id') {
        $nic_clean = clean_id($nic);
        $pp_clean  = clean_id($passport);

        if ($pp_clean === '' && $nic_clean === '') {
            $reason = 'No Passport/NIC in CSV for id mode';
            return null;
        }

        $passport_user = null;
        $nic_user      = null;

        if ($pp_clean !== '') {
            foreach (['passport_no', 'passport', 'pp_no'] as $meta_key) {
                $users = get_users([
                    'meta_key'   => $meta_key,
                    'meta_value' => $pp_clean,
                    'number'     => 1,
                    'fields'     => ['ID'],
                ]);
                if (!empty($users)) {
                    $passport_user = get_user_by('id', $users[0]->ID);
                    break;
                }
            }
        }

        if ($nic_clean !== '') {
            foreach (['nic_no', 'nic', 'national_id'] as $meta_key) {
                $users = get_users([
                    'meta_key'   => $meta_key,
                    'meta_value' => $nic_clean,
                    'number'     => 1,
                    'fields'     => ['ID'],
                ]);
                if (!empty($users)) {
                    $nic_user = get_user_by('id', $users[0]->ID);
                    break;
                }
            }
        }

        if ($passport_user && $nic_user && (int)$passport_user->ID !== (int)$nic_user->ID) {
            $reason = 'NIC and Passport resolve to different users';
            return null;
        }

        $user = $passport_user ?: $nic_user;
        if (!$user) {
            $reason = 'User not found by Passport/NIC';
        }
        return $user;
    }

    // STRICT (A3): BOTH username AND email must match the SAME user
    if ($match_policy === 'strict') {
        if ($login === '' || $email === '') {
            $reason = 'Missing username or email for strict mode';
            return null;
        }

        $by_login = get_user_by('login', $login);
        $by_email = get_user_by('email', $email);

        if (!$by_login || !$by_email) {
            $reason = 'User not found by username/email (strict)';
            return null;
        }

        if ((int)$by_login->ID !== (int)$by_email->ID) {
            $reason = 'Username and email belong to different users (strict)';
            return null;
        }

        return $by_login;
    }

    // EMAIL MODE (B2): email first → fallback username
    // default mode if unknown
    // Try by email
    if ($email !== '') {
        $user = get_user_by('email', $email);
        if ($user) {
            return $user;
        }
    }

    // Fallback: username
    if ($login !== '') {
        $user = get_user_by('login', $login);
        if ($user) {
            return $user;
        }
    }

    $reason = 'User not found by email/username';
    return null;
}

// ====== LOAD CSV & HEADERS ======

$delim = detect_delim_smart($csv_file);
$fh = fopen($csv_file, 'r');
if (!$fh) {
    fwrite(STDERR, "❌ Unable to open CSV: {$csv_file}\n");
    exit(2);
}

$header = fgetcsv($fh, 0, $delim);
if ($header === false || $header === null) {
    fwrite(STDERR, "❌ CSV appears to be empty or unreadable\n");
    fclose($fh);
    exit(2);
}

// Strip BOM from first header if present
if (!empty($header[0])) {
    $header[0] = preg_replace('/^\xEF\xBB\xBF/u', '', (string)$header[0]);
}

// Detect column indices (flexible)
$idx_login = find_col($header, [
    'user_login', 'username', 'user name', 'login'
]);
$idx_email = find_col($header, [
    'user_email', 'email', 'email address', 'e-mail', 'user email'
]);
$idx_nic   = find_col($header, [
    'nic_no', 'nic', 'nic number', 'nic #', 'nic no', 'national id', 'national identity number', 'nric'
]);
$idx_pass  = find_col($header, [
    'passport_no', 'passport', 'passport number', 'passport #', 'pp_no'
]);

// Validate presence based on chosen match mode
if ($match_policy === 'strict') {
    if ($idx_login === null || $idx_email === null) {
        fwrite(STDERR, "❌ STRICT mode requires both username and email columns in CSV.\n");
        fwrite(STDERR, "   Looked for headers like: user_login / username, and user_email / email\n");
        fclose($fh);
        exit(2);
    }
}
if ($match_policy === 'email') {
    if ($idx_login === null && $idx_email === null) {
        fwrite(STDERR, "❌ EMAIL mode requires at least username OR email column.\n");
        fclose($fh);
        exit(2);
    }
}
if ($match_policy === 'id') {
    if ($idx_nic === null && $idx_pass === null) {
        fwrite(STDERR, "❌ ID mode requires NIC and/or Passport columns.\n");
        fclose($fh);
        exit(2);
    }
}

// ====== OPEN OUTPUT FILES ======

$matched_fh   = fopen($matched_file, 'w');
$unmatched_fh = fopen($unmatched_file, 'w');

if (!$matched_fh || !$unmatched_fh) {
    fwrite(STDERR, "❌ Unable to open output files for writing.\n");
    if ($fh) fclose($fh);
    if ($matched_fh) fclose($matched_fh);
    if ($unmatched_fh) fclose($unmatched_fh);
    exit(2);
}

// matched: user_login,user_email,status,course_removed,groups_removed
fputcsv($matched_fh, ['user_login', 'user_email', 'status', 'course_removed', 'groups_removed']);
// unmatched: reason,email,login,id,nic,passport
fputcsv($unmatched_fh, ['reason', 'email', 'login', 'id', 'nic', 'passport']);

$processed = 0;
$removed   = 0;
$skipped   = 0;

// ====== MAIN LOOP ======

while (($row = fgetcsv($fh, 0, $delim)) !== false) {
    // stop at limit if set
    if ($limit > 0 && $processed >= $limit) {
        echo "⏹️ Reached limit {$limit}, stopping.\n";
        break;
    }

    // skip completely empty rows
    $nonEmpty = array_filter($row, function ($v) {
        return trim((string)$v) !== '';
    });
    if (count($nonEmpty) === 0) {
        continue;
    }

    $processed++;

    $login    = get_val($row, $idx_login);
    $email    = get_val($row, $idx_email);
    $nic      = get_val($row, $idx_nic);
    $passport = get_val($row, $idx_pass);
    $csv_id   = ''; // reserved if you ever add an `id` column later

    $reason = null;
    $user   = find_matching_user($match_policy, $login, $email, $nic, $passport, $reason);

    if (!$user) {
        fputcsv($unmatched_fh, [$reason ?: 'User not found', $email, $login, $csv_id, $nic, $passport]);
        $skipped++;
        continue;
    }

    $uid   = (int)$user->ID;
    $ulog  = (string)$user->user_login;
    $uemail= (string)$user->user_email;

    $removal = user_remove_course_3027($uid, $dry_run);

    if (!$removal['attempted']) {
        $status = 'not_enrolled';
        $course_removed_flag = 'no';
    } elseif ($dry_run) {
        $status = 'DRY_RUN_would_remove';
        $course_removed_flag = 'would_remove';
    } elseif ($removal['removed']) {
        $status = 'removed';
        $course_removed_flag = 'yes';
    } elseif ($removal['status'] === 'ld_function_missing') {
        $status = 'ld_function_missing';
        $course_removed_flag = 'no';
    } elseif ($removal['status'] === 'still_enrolled_via_group') {
        $status = 'still_enrolled_via_group';
        $course_removed_flag = 'no';
    } else {
        $status = 'failed_remove';
        $course_removed_flag = 'no';
    }

    $group_note = '';
    if (!empty($removal['groups_removed'])) {
        $group_note = ' (groups: ' . implode(',', $removal['groups_removed']) . ')';
    }

    if ($removal['removed'] && !$dry_run) {
        $removed++;
        echo "✅ {$ulog} ({$uemail}) — course 3027 removed{$group_note}\n";
    } elseif ($removal['attempted'] && $dry_run) {
        echo "👀 [DRY RUN] {$ulog} ({$uemail}) — would remove course 3027{$group_note}\n";
    } elseif ($removal['attempted']) {
        echo "⚠️ {$ulog} ({$uemail}) — still enrolled in course 3027 (check LearnDash groups){$group_note}\n";
    } else {
        echo "ℹ️ {$ulog} ({$uemail}) — not enrolled in course 3027\n";
    }

    fputcsv($matched_fh, [$ulog, $uemail, $status, $course_removed_flag, implode('|', $removal['groups_removed'])]);
}

// ====== CLEANUP ======

fclose($fh);
fclose($matched_fh);
fclose($unmatched_fh);

echo "🏁 Done. Processed={$processed}, removed={$removed}, unmatched={$skipped}\n";
exit(0);
