<?php

require_once '/var/www/html/wp-load.php';

/**
 * STEP 1:
 * Group users by company name (case-insensitive)
 */
function batch_company_get_groups() {
    global $wpdb;

    // Get all company meta values
    $rows = $wpdb->get_results("
        SELECT user_id, meta_value AS company
        FROM {$wpdb->usermeta}
        WHERE meta_key = 'company'
    ");

    $groups_raw = [];

    foreach ($rows as $r) {
        $name = trim($r->company);

        if ($name === '' || strtolower($name) === '(blank)') {
            continue; // ignore blank companies
        }

        $key = strtolower($name); // normalize grouping
        if (!isset($groups_raw[$key])) {
            $groups_raw[$key] = [];
        }
        $groups_raw[$key][$name][] = get_user_by('ID', $r->user_id);
    }

    // Convert grouped structure into simple list
    $result = [];

    foreach ($groups_raw as $low => $variant_set) {

        // Suggest the most common variant
        $suggested = '';
        $max = 0;

        foreach ($variant_set as $name => $users) {
            if (count($users) > $max) {
                $max = count($users);
                $suggested = $name;
            }
        }

        $result[] = [
            'suggested' => $suggested,
            'variants'  => $variant_set,
        ];
    }

    return $result;
}


/**
 * STEP 2:
 * Collect all users in a multi-variant group
 */
function batch_company_collect_all_users($variants) {
    $out = [];

    foreach ($variants as $name => $users) {
        foreach ($users as $u) {
            $out[$u->ID] = $u;
        }
    }

    return array_values($out);
}


/**
 * STEP 3:
 * Update all users in variant group
 */
function batch_company_apply($variants, $new_value) {

    $all = batch_company_collect_all_users($variants);

    $count = 0;
    foreach ($all as $u) {
        update_user_meta($u->ID, 'company', $new_value);
        $count++;
    }

    return $count;
}