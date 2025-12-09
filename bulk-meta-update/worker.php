<?php
/**
 * Worker for WP Meta Bulk Updater v2.2.1
 *
 * Exposes function:
 *   wpmbu220_run(array $opts, string $run_dir): array
 *
 * Now supports:
 *   - Process all subscribers (even dual-role)
 *   - Exclude all admins
 *   - Exclude specific usernames
 */

function wpmbu220_run(array $opts, string $run_dir): array {

    global $wpdb;

    $timestamp     = date('Ymd-His');
    $summary_path  = $run_dir . '/summary.json';
    $log_path      = $run_dir . '/log.ndjson';
    $csv_path      = $run_dir . '/changes.csv';

    if (!is_dir($run_dir)) {
        mkdir($run_dir, 0775, true);
    }

    $fh_log = fopen($log_path, 'w');
    $fh_csv = fopen($csv_path, 'w');
    fputcsv($fh_csv, ['object_type','object_id','identifier','field_changed','old_value','new_value','condition_field','condition_operator','condition_matched']);

    $counts = [
        'scanned'           => 0,
        'matched_old'       => 0,
        'condition_matched' => 0,
        'updated'           => 0,
        'errors'            => 0,
    ];

    $target_type    = $opts['target_type']   ?? 'user';
    $field_name     = $opts['field_name']    ?? '';
    $field_type     = $opts['field_type']    ?? 'meta';
    $old_value      = $opts['old_value']     ?? '';
    $ignore_search  = !empty($opts['ignore_search']);
    $new_value      = $opts['new_value']     ?? '';
    $condition      = $opts['condition']     ?? [];
    $live           = !empty($opts['live']);

    // Users to exclude manually
    $excluded_usernames = [
        'saravanan673V',
        'yudesh535V',
        'edison',
        'calm',
        'tesla',
    ];

    // Helper: get field value
    $get_field_value = function($object, string $object_type, string $name, string $type) {
        if ($object_type === 'user') {
            $uid = $object->ID;
            if ($type === 'meta') {
                $v = get_user_meta($uid, $name, true);
                return is_array($v) ? (string)reset($v) : (string)$v;
            }
            if (property_exists($object, $name)) {
                return (string)$object->{$name};
            }
            return match ($name) {
                'display_name' => (string)$object->display_name,
                'user_email'   => (string)$object->user_email,
                'user_login'   => (string)$object->user_login,
                default        => '',
            };
        }

        // post values
        $pid = $object->ID;
        if ($type === 'meta') {
            $v = get_post_meta($pid, $name, true);
            return is_array($v) ? (string)reset($v) : (string)$v;
        }
        if (property_exists($object, $name)) {
            return (string)$object->{$name};
        }
        return match ($name) {
            'post_title'   => (string)$object->post_title,
            'post_content' => (string)$object->post_content,
            default        => '',
        };
    };

    // Helper: write new value
    $set_field_value = function($object, string $object_type, string $name, string $type, $value) use (&$counts) {
        try {
            if ($object_type === 'user') {
                $uid = $object->ID;

                if ($type === 'meta') {
                    return update_user_meta($uid, $name, $value);
                }

                $result = wp_update_user(['ID' => $uid, $name => $value]);
                return !is_wp_error($result);
            }

            // Post
            $pid = $object->ID;
            if ($type === 'meta') {
                return update_post_meta($pid, $name, $value);
            }

            $result = wp_update_post(['ID' => $pid, $name => $value], true);
            return !is_wp_error($result);

        } catch (Exception $e) {
            $counts['errors']++;
            return false;
        }
    };

    // -------------------------------------------------------------
    // LOAD USERS (SUBSCRIBER ROLE ONLY) BUT ALLOW DUAL-ROLE
    // -------------------------------------------------------------
    if ($target_type === 'user') {

        $potential_users = get_users([
            'number'   => 0,
            'role__in' => ['subscriber'],
        ]);

        $all_objects = [];

        foreach ($potential_users as $u) {

            // Exclude usernames
            if (in_array($u->user_login, $excluded_usernames, true)) {
                continue;
            }

            // Exclude any type of admin
            if (user_can($u->ID, 'administrator') || user_can($u->ID, 'manage_options')) {
                continue;
            }

            // ACCEPT all subscribers even if dual-role
            $all_objects[] = $u;
        }

    } else {

        // Posts
        $all_objects = get_posts([
            'numberposts' => -1,
            'post_status' => 'any',
        ]);
    }

    // -------------------------------------------------------------
    // MAIN LOOP
    // -------------------------------------------------------------
    foreach ($all_objects as $obj) {

        $counts['scanned']++;

        $identifier = ($target_type === 'user')
            ? ($obj->user_login ?: "user_{$obj->ID}")
            : ($obj->post_title ?: "post_{$obj->ID}");

        $current_value = $get_field_value($obj, $target_type, $field_name, $field_type);

        // OLD VALUE MATCH
        if ($ignore_search) {
            $old_matches = true;
        } elseif ($old_value === '') {
            $old_matches = ($current_value === '' || $current_value === null);
        } else {
            $old_matches = (mb_stripos((string)$current_value, (string)$old_value) !== false);
        }

        if (!$old_matches) {
            continue;
        }

        $counts['matched_old']++;

        // -------------------------------------------------------------
        // CONDITION CHECK (LearnDash aware)
        // -------------------------------------------------------------
        $cond_field    = $condition['field']      ?? '';
        $cond_type     = $condition['field_type'] ?? 'meta';
        $cond_operator = $condition['operator']   ?? '';
        $cond_val      = $condition['value']      ?? '';

        $is_ld_operator = in_array($cond_operator, [
            'ld_group_member',
            'ld_in_any_groups'
        ], true);

        $condition_matched = true;

        if ($is_ld_operator) {

            if ($target_type !== 'user' || !function_exists('learndash_is_user_in_group')) {
                $condition_matched = false;

            } else {

                $uid = $obj->ID;

                if ($cond_operator === 'ld_group_member') {
                    $gid = intval($cond_val);
                    $condition_matched = ($gid > 0 && learndash_is_user_in_group($uid, $gid));
                }

                if ($cond_operator === 'ld_in_any_groups') {
                    $ids_raw = preg_split('/[,\s]+/', $cond_val, -1, PREG_SPLIT_NO_EMPTY);
                    $group_ids = array_filter(array_map('intval', $ids_raw));

                    $condition_matched = false;
                    foreach ($group_ids as $gid) {
                        if (learndash_is_user_in_group($uid, $gid)) {
                            $condition_matched = true;
                            break;
                        }
                    }
                }
            }

        } elseif ($cond_field !== '') {

            // Classic comparison
            $cond_current = $get_field_value($obj, $target_type, $cond_field, $cond_type);

            $condition_matched = match ($cond_operator) {
                'equals'     => mb_strtolower((string)$cond_current) === mb_strtolower((string)$cond_val),
                'contains'   => mb_stripos((string)$cond_current, (string)$cond_val) !== false,
                'empty'      => ($cond_current === '' || $cond_current === null),
                'not_empty'  => !($cond_current === '' || $cond_current === null),
                default      => true,   // No condition = always true
            };
        }

        if (!$condition_matched) {
            fwrite($fh_log, json_encode([
                'time'       => date('c'),
                'action'     => 'skipped_condition',
                'user_login' => $identifier,
                'condition'  => $condition,
            ]) . PHP_EOL);
            continue;
        }

        $counts['condition_matched']++;

        // UPDATE
        if ($live) {
            $success = $set_field_value($obj, $target_type, $field_name, $field_type, $new_value);
            if ($success) {
                $counts['updated']++;
            } else {
                $counts['errors']++;
            }
        }

        fwrite($fh_log, json_encode([
            'time'          => date('c'),
            'object_id'     => $obj->ID,
            'identifier'    => $identifier,
            'field_changed' => $field_name,
            'old_value'     => $current_value,
            'new_value'     => $new_value,
            'condition'     => $condition,
            'applied'       => $live,
        ]) . PHP_EOL);

        fputcsv($fh_csv, [
            $target_type,
            $obj->ID,
            $identifier,
            $field_name,
            $current_value,
            $new_value,
            $cond_field,
            $cond_operator,
            $condition_matched ? 'yes' : 'no',
        ]);
    }

    fclose($fh_log);
    fclose($fh_csv);

    $summary = [
        'run_at'        => date('c'),
        'version'       => '2.2.1',
        'target_type'   => $target_type,
        'field_name'    => $field_name,
        'field_type'    => $field_type,
        'old_value'     => $old_value,
        'ignore_search' => $ignore_search,
        'new_value'     => $new_value,
        'condition'     => $condition,
        'live'          => $live,
        'counts'        => $counts,
        'log'           => basename($log_path),
        'changes_csv'   => basename($csv_path),
    ];

    file_put_contents($summary_path, json_encode($summary, JSON_PRETTY_PRINT));

    return [$summary_path, $log_path, $csv_path];
}