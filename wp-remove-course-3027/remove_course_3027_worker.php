<?php
/**
 * Remove Course 3027 — Worker v1.0.3
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
 * Actually remove access to course 3027 (LIVE mode).
 */
function user_remove_course_3027(int $user_id, bool $dry_run): bool {
    $course_id = COURSE_ID_3027;

    if (!user_has_course_3027($user_id)) {
        // Nothing to remove
        return false;
    }

    if ($dry_run) {
        // Preview only
        return true;
    }

    if (function_exists('ld_update_course_access')) {
        // Third arg = true => REMOVE access
        ld_update_course_access($user_id, $course_id, true);
        return true;
    }

    // Fallback: no LD function (shouldn't happen in your environment)
    return false;
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

// matched: user_login,user_email,status,course_removed
fputcsv($matched_fh, ['user_login', 'user_email', 'status', 'course_removed']);
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

    $had_course = user_has_course_3027($uid);
    $did_remove = user_remove_course_3027($uid, $dry_run);

    if ($had_course) {
        if ($dry_run) {
            $status = 'DRY_RUN_would_remove';
        } else {
            $status = $did_remove ? 'removed' : 'failed_remove';
        }
    } else {
        $status = 'not_enrolled';
    }

    $course_removed_flag = $did_remove ? 'yes' : ($had_course ? 'would_remove' : 'no');

    if ($did_remove && !$dry_run) {
        $removed++;
        echo "✅ {$ulog} ({$uemail}) — course 3027 removed\n";
    } elseif ($had_course && $dry_run) {
        echo "👀 [DRY RUN] {$ulog} ({$uemail}) — would remove course 3027\n";
    } else {
        echo "ℹ️ {$ulog} ({$uemail}) — not enrolled in course 3027\n";
    }

    fputcsv($matched_fh, [$ulog, $uemail, $status, $course_removed_flag]);
}

// ====== CLEANUP ======

fclose($fh);
fclose($matched_fh);
fclose($unmatched_fh);

echo "🏁 Done. Processed={$processed}, removed={$removed}, unmatched={$skipped}\n";
exit(0);