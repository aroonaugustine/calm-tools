#!/bin/bash
# Version: 3.0
# Purpose: Count users with non-empty _um_last_login using wp eval (1 load only)
# Author: CALM Platform

WP_PATH="/var/www/html"
WP_BIN=$(which wp)

if ! command -v wp &> /dev/null; then
    echo "❌ WP-CLI is not installed"
    exit 1
fi

$WP_BIN eval --path="$WP_PATH" '
$count = 0;
$users = get_users(array("fields" => "ID"));

foreach ($users as $user_id) {
    $login = get_user_meta($user_id, "_um_last_login", true);
    if (!empty($login)) {
        $count++;
    }
}

echo "✅ Number of users who have logged in at least once (_um_last_login): $count\n";
'
