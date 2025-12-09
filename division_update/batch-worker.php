<?php

require_once '/var/www/html/wp-load.php';

/**
 * Get users in LearnDash group
 */
function batch_get_users_in_group($gid) {
    if (!function_exists('learndash_get_groups_user_ids')) return [];

    $user_ids = learndash_get_groups_user_ids($gid);
    if (!$user_ids) return [];

    return array_map('get_user_by_id', $user_ids);
}

function get_user_by_id($id) {
    return get_user_by('ID', $id);
}

/**
 * Update users in group
 */
function batch_apply_division($gid, $division) {

    $users = batch_get_users_in_group($gid);
    if (!$users) return 0;

    $count = 0;

    foreach ($users as $u) {
        update_user_meta($u->ID, 'division', $division);
        $count++;
    }

    return $count;
}