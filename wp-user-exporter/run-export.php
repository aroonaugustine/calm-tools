<?php
/**
 * WP User Exporter v15.09.0002.0001
 * - Streams CSV to the browser
 * - Optional: also saves a copy under /srv/admin-tools/wp-user-exporter/_exports/
 * - NEW: role filtering using POST roles[]
 *
 * SECURITY:
 *   - Behind Basic Auth (/admin-tools/)
 *   - Requires AUTH_TOKEN posted via form
 */

declare(strict_types=1);

// ====== EDIT THESE TWO CONSTANTS ======
const AUTH_TOKEN = '6714e52aed21125dd999ff7c31666c1806e033aa2cb8a14073b41ae7026ec0b0';
const WP_ROOT    = '/var/www/html';
// ======================================

// Basic configuration
@set_time_limit(0);
@ini_set('memory_limit', '512M');

// CSRF-ish: require POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  header('Content-Type: text/plain; charset=utf-8');
  echo "Method not allowed";
  exit;
}

// Token check
$token = (string)($_POST['token'] ?? '');
if (!hash_equals(AUTH_TOKEN, $token)) {
  http_response_code(401);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['error'=>'Unauthorized (bad token)'], JSON_UNESCAPED_SLASHES);
  exit;
}

// Role filter from POST
$requested_roles = isset($_POST['roles']) ? (array)$_POST['roles'] : [];
$requested_roles = array_filter($requested_roles); // remove empty

// Determine if filter is active
$filter_active = !empty($requested_roles);

// Locate WordPress
$wpLoad = rtrim((string)WP_ROOT,'/').'/wp-load.php';
if (!is_file($wpLoad)) {
  http_response_code(500);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['error'=>'Cannot locate wp-load.php at '.WP_ROOT], JSON_UNESCAPED_SLASHES);
  exit;
}
require_once $wpLoad;

// Columns to extract (header mapping)
$meta_fields = [
  'user_login'               => 'Username',
  'first_name'               => 'First Name',
  'last_name'                => 'Last Name',
  'nickname'                 => 'Nickname',
  'user_email'               => 'Email',
  'passport_no'              => 'Passport Number',
  'nic_no'                   => 'NIC Number',
  'nationality'              => 'Nationality',
  'mobile_no'                => 'Mobile Number',
  'visa_exp'                 => 'Visa Expiry Date (dd/mm/yyyy)',
  'passport_exp'             => 'Passport Expiry Date (dd/mm/yyyy)',
  'company'                  => 'Company',
  'division'                 => 'Division',
  'designation'              => 'Designation',
  'visa_issue_date'          => 'Visa Issue Date (dd/mm/yyyy)',
  'home_address'             => 'Home Address',
  'office_address'           => 'Office Address',
  'employee_number'          => 'Employee Number',
  'emergency_contact_email'  => 'Emergency Contact – Email',
  'emergency_contact_phone'  => 'Emergency Contact – Phone',
  'employment_status'        => 'Employment Status',
  'gender'                   => 'Gender',
  'expat_local'              => 'Expat/Local',
  'date_of_birth'            => 'Date Of Birth',
  'user_roles'               => 'User Roles',
  'learndash_courses'        => 'LearnDash Courses (IDs)',
  'learndash_groups'         => 'LearnDash Groups (IDs)',
  'learndash_leader_groups'  => 'Leader of Groups (IDs)',
];

// Helper: prettify role names
function prettify_roles(array $roles): array {
  $map = [
    'administrator' => 'Administrator',
    'editor'        => 'Editor',
    'author'        => 'Author',
    'contributor'   => 'Contributor',
    'subscriber'    => 'Subscriber',
    'group_leader'  => 'Group Leader',
    'ld_instructor' => 'Instructor',
    'customer'      => 'Customer',
    'shop_manager'  => 'Shop Manager',
  ];
  return array_map(function($r) use ($map) {
    return $map[$r] ?? ucwords(str_replace('_',' ', $r));
  }, $roles);
}

// Handle "save a server copy"
$also_save = isset($_POST['save']) && $_POST['save'] === '1';
$save_dir  = '/srv/admin-tools/wp-user-exporter/_exports';
$ts        = gmdate('Ymd_His');
$filename  = "user_data_export_{$ts}.csv";
$save_path = $save_dir . '/' . $filename;

if ($also_save) {
  if (!is_dir($save_dir)) {
    @mkdir($save_dir, 0755, true);
  }
  if (!is_writable($save_dir)) {
    $also_save = false; // Fail gracefully
  }
}

// Output headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('X-Exporter-Version: v15.09.0002.0001');

// Open file streams
$stream = fopen('php://output', 'w');
if ($also_save) {
  $save_fp = @fopen($save_path, 'w');
}

// Write header row
fputcsv($stream, array_values($meta_fields));
if ($also_save && $save_fp) {
  fputcsv($save_fp, array_values($meta_fields));
}

// -----------------------------------------------------------------------------
// USER QUERY PROCESSING
// -----------------------------------------------------------------------------

$collected_users = [];

/**
 * 1) If filtering on roles, load users with selected WP roles
 */
if ($filter_active) {
  $wp_roles_only = array_filter($requested_roles, fn($r) => $r !== 'none');

  if (!empty($wp_roles_only)) {
    $query = new WP_User_Query([
      'role__in' => $wp_roles_only,
      'number'   => -1,
      'orderby'  => 'ID',
      'order'    => 'ASC',
    ]);
    $role_users = $query->get_results();
    if (!empty($role_users)) {
      $collected_users = array_merge($collected_users, $role_users);
    }
  }

  /**
   * 2) If "none" requested, fetch no-role users
   */
  if (in_array('none', $requested_roles, true)) {
    global $wpdb;
    $ids_no_role = $wpdb->get_col("
      SELECT u.ID
      FROM {$wpdb->users} u
      LEFT JOIN {$wpdb->usermeta} um
      ON (u.ID = um.user_id AND um.meta_key = '{$wpdb->prefix}capabilities')
      WHERE um.meta_value IS NULL OR um.meta_value = ''
    ");

    if (!empty($ids_no_role)) {
      foreach ($ids_no_role as $uid) {
        $u = get_user_by('ID', (int)$uid);
        if ($u) $collected_users[] = $u;
      }
    }
  }

  /**
   * Remove duplicates if a user matches multiple filters.
   */
  if (!empty($collected_users)) {
    $collected_users = array_values(array_reduce(
      $collected_users,
      function($carry, $item) {
        $carry[$item->ID] = $item;
        return $carry;
      }, []
    ));
  }
}

/**
 * 3) If no filter, export ALL users (full backward compatibility)
 */
if (!$filter_active) {
  $all = get_users([
    'number' => -1,
    'orderby'=> 'ID',
    'order'  => 'ASC',
  ]);
  $collected_users = is_array($all) ? $all : [];
}

// -----------------------------------------------------------------------------
// WRITE CSV ROWS
// -----------------------------------------------------------------------------

foreach ($collected_users as $user) {

  $row = [];

  foreach ($meta_fields as $key => $label) {

    switch ($key) {

      case 'user_email':
        $value = (string)($user->user_email ?? '');
        break;

      case 'user_login':
        $value = (string)($user->user_login ?? '');
        break;

      case 'user_roles':
        $roles = (isset($user->roles) && is_array($user->roles)) ? $user->roles : [];
        $pretty = prettify_roles($roles);
        $value = $pretty ? implode(';', $pretty) : '';
        break;

      case 'learndash_courses':
        $course_ids = function_exists('ld_get_mycourses') ? (array)ld_get_mycourses($user->ID) : [];
        $value = !empty($course_ids) ? implode(';', array_map('intval', $course_ids)) : '';
        break;

      case 'learndash_groups':
        $group_ids = function_exists('learndash_get_users_group_ids') ? (array)learndash_get_users_group_ids($user->ID) : [];
        $value = !empty($group_ids) ? implode(';', array_map('intval', $group_ids)) : '';
        break;

      case 'learndash_leader_groups':
        if (function_exists('learndash_get_administrators_group_ids')) {
          $leader_ids = (array)learndash_get_administrators_group_ids($user->ID);
        } else {
          $leader_ids = [];
        }
        $value = !empty($leader_ids) ? implode(';', array_map('intval', $leader_ids)) : '';
        break;

      default:
        $value = (string)get_user_meta($user->ID, $key, true);
    }

    $row[] = $value;
  }

  fputcsv($stream, $row);
  if ($also_save && $save_fp) {
    fputcsv($save_fp, $row);
  }
}

// Close save file if needed
if (isset($save_fp) && is_resource($save_fp)) {
  @fclose($save_fp);
  header('X-Exporter-Saved-To: '.$save_path);
}

exit;
