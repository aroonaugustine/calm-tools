<?php
/**
 * User Modification Tool — v2.1.5.001
 * Search & edit WP users (token-gated). Requires min search length 4 chars.
 * - Search by partial username / email / NIC / passport / employee_number
 * - Edit core profile (first/last/nickname/email) + custom meta
 * - Username is NOT editable (WordPress limitation)
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', 1);

/* =========================
   CONFIG
   ========================= */
const WP_LOAD   = '/var/www/html/wp-load.php'; // adjust if needed
const TOKENS    = [
  'owner' => '6714e52aed21125dd999ff7c31666c1806e033aa2cb8a14073b41ae7026ec0b0',
  'ops'   => 'e546ffe239d986aeaa4f8f936acdcf2d0af40de4d547c43de784a02d63843211',
];
const MIN_LEN   = 4;

/* =========================
   LOAD WORDPRESS
   ========================= */
require WP_LOAD;

/* =========================
   AUTH (POST: token / Header: X-UserMod-Token)
   ========================= */
function token_ok(?string $supplied): array {
  $candidate = $supplied ?: (string)($_SERVER['HTTP_X_USERMOD_TOKEN'] ?? '');
  if ($candidate === '') return [false,''];
  foreach (TOKENS as $label => $secret) {
    if (hash_equals($secret, $candidate)) return [true,(string)$label];
  }
  return [false,''];
}

/* =========================
   FIELDS
   ========================= */
function meta_field_map(): array {
  return [
    'employee_number'           => 'Employee Number',
    'employment_status'         => 'Employment Status',
    'passport_no'               => 'Passport Number',
    'passport_exp'              => 'Passport Expiry Date (dd/mm/yyyy)',
    'nationality'               => 'Nationality',
    'expat_local'               => 'Expat/Local',
    'visa_issue_date'           => 'Visa Issue Date (dd/mm/yyyy)',
    'visa_exp'                  => 'Visa Expiry Date (dd/mm/yyyy)',
    'nic_no'                    => 'NIC Number',
    'date_of_birth'             => 'Date Of Birth',
    'mobile_no'                 => 'Mobile Number',
    'gender'                    => 'Gender',
    'company'                   => 'Company',
    'division'                  => 'Division',
    'designation'               => 'Designation',
    'home_address'              => 'Local Home Address',
    'perm_address'              => 'Permanent Address',
    'office_address'            => 'Office Address',
    'emergency_contact_email'   => 'Emergency Contact – Email',
    'emergency_contact_phone'   => 'Emergency Contact – Phone',
    'emergency_contact_who'     => 'Emergency Contact – Relationship',
    'join_date'                 => 'Joining Date',
    'resign_date'               => 'Resignation Date',
  ];
}

/* =========================
   HELPERS
   ========================= */
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function method(): string { return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'); }
function post(string $k, string $def=''): string { return trim((string)($_POST[$k] ?? $def)); }
function get(string $k, string $def=''): string { return trim((string)($_GET[$k] ?? $def)); }
function is_minlen(string $s, int $n): bool { return mb_strlen($s) >= $n; }

/* =========================
   SEARCH
   ========================= */
function search_users_partial(string $q): array {
  $q = trim($q);
  if (!is_minlen($q, MIN_LEN)) return [];

  $meta_query = [
    'relation' => 'OR',
    [
      'key'     => 'nic_no',
      'value'   => $q,
      'compare' => 'LIKE',
    ],
    [
      'key'     => 'passport_no',
      'value'   => $q,
      'compare' => 'LIKE',
    ],
    [
      'key'     => 'employee_number',
      'value'   => $q,
      'compare' => 'LIKE',
    ],
  ];

  // First: use WP_User_Query for login/email/display_name (with search operator) and include meta_query
  $args = [
    'number'      => 50, // cap results
    'search'      => '*' . esc_sql($q) . '*',
    'search_columns' => ['user_login', 'user_email', 'display_name'],
    'meta_query'  => $meta_query,
    'fields'      => ['ID','user_login','user_email','display_name'],
  ];

  // Strategy: Two queries (union) because WP_User_Query treats search + meta_query as AND.
  // We want users that match EITHER search OR meta_like. We'll merge distinct IDs.
  $Q1 = new WP_User_Query($args); // search + meta
  $users1 = (array)$Q1->get_results();

  // Second: meta-only match (for cases where login/email/display_name don't match)
  $Q2 = new WP_User_Query([
    'number'      => 50,
    'meta_query'  => $meta_query,
    'fields'      => ['ID','user_login','user_email','display_name'],
  ]);
  $users2 = (array)$Q2->get_results();

  // Third: search-only (to catch non-meta matches)
  $Q3 = new WP_User_Query([
    'number'      => 50,
    'search'      => '*' . esc_sql($q) . '*',
    'search_columns' => ['user_login', 'user_email', 'display_name'],
    'fields'      => ['ID','user_login','user_email','display_name'],
  ]);
  $users3 = (array)$Q3->get_results();

  // Merge unique by ID
  $byId = [];
  foreach (array_merge($users1, $users2, $users3) as $u) { $byId[$u->ID] = $u; }

  // Build rows with extra meta
  $out = [];
  foreach ($byId as $u) {
    $out[] = [
      'ID'            => $u->ID,
      'display_name'  => $u->display_name,
      'user_login'    => $u->user_login,
      'user_email'    => $u->user_email,
      'nic_no'        => (string)get_user_meta($u->ID,'nic_no',true),
      'passport_no'   => (string)get_user_meta($u->ID,'passport_no',true),
      'employee_number'=> (string)get_user_meta($u->ID,'employee_number',true),
    ];
  }
  // Sort by display_name then login
  usort($out, function($a,$b){
    $x = strcasecmp($a['display_name'],$b['display_name']); if ($x!==0) return $x;
    return strcasecmp($a['user_login'],$b['user_login']);
  });
  return $out;
}

/* =========================
   SAVE
   ========================= */
function save_user_edit(int $uid, array $data): array {
  $user = get_user_by('id', $uid);
  if (!$user) return [false, 'User not found'];

  // Core fields
  $email      = trim((string)($data['user_email'] ?? ''));
  $first_name = trim((string)($data['first_name'] ?? ''));
  $last_name  = trim((string)($data['last_name'] ?? ''));
  $nickname   = trim((string)($data['nickname'] ?? ''));

  if ($email === '') return [false, 'Email cannot be empty'];
  if (!is_email($email)) return [false, 'Invalid email'];

  // Update core (username not editable)
  $update = [
    'ID'         => $uid,
    'user_email' => $email,
    'first_name' => $first_name,
    'last_name'  => $last_name,
    'nickname'   => $nickname,
    'display_name' => trim($first_name.' '.$last_name) ?: $user->display_name,
  ];
  $res = wp_update_user($update);
  if (is_wp_error($res)) return [false, 'Core update failed: '.$res->get_error_message()];

  // Update meta fields
  foreach (meta_field_map() as $key => $_label) {
    $val = isset($data['meta'][$key]) ? (string)$data['meta'][$key] : '';
    update_user_meta($uid, $key, $val);
  }

  return [true, 'Saved'];
}

/* =========================
   ROUTING
   ========================= */
$action = post('action') ?: get('action');

$provided_token = method()==='POST' ? post('token') : get('token');
[$auth_ok, $who] = token_ok($provided_token);
if (!$auth_ok) { http_response_code(401); echo "Unauthorized"; exit; }

header('Content-Type: text/html; charset=utf-8');

$q       = post('q') ?: get('q');
$results = [];
$msg     = '';
$err     = '';

if ($action === 'search' && $q !== '') {
  if (!is_minlen($q, MIN_LEN)) {
    $err = "Please enter at least ".MIN_LEN." characters.";
  } else {
    $results = search_users_partial($q);
    if (!$results) $msg = "No users matched “".h($q)."”.";
  }
}

if ($action === 'save' && method()==='POST') {
  $uid = (int)post('uid');
  [$ok, $note] = save_user_edit($uid, $_POST);
  if ($ok) { $msg = "✅ Saved changes for user ID {$uid}."; }
  else     { $err = "❌ ".$note; }

  // After save, show the same user in edit mode again
  $q = (string)$uid;
  $results = []; // not used in edit view
}

/* =========================
   EDIT VIEW (if uid present)
   ========================= */
$edit_uid = (int)(post('uid') ?: get('uid') ?: 0);
$edit_user = $edit_uid ? get_user_by('id', $edit_uid) : null;

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>User Modification Tool — v2.1.5.001</title>
<meta name="robots" content="noindex,nofollow">
<link rel="stylesheet" href="/portal-assets/css/portal.css">
  <link rel="stylesheet" href="/portal-assets/css/tool.css">
</head>
<body>
  <main>
    <div class="tool-card">
      <header class="tool-card__header">
        <h1>User Modification Tool</h1>
        <span class="tool-card__version">v2.1.5.001</span>
        <p class="tool-card__lede">Authenticated as token: <span class="badge"><?=h($who)?></span></p>
      </header>

      <?php if ($msg): ?><div class="ok"><?=h($msg)?></div><?php endif; ?>
      <?php if ($err): ?><div class="err"><?=h($err)?></div><?php endif; ?>

      <fieldset>
        <legend>Search</legend>
        <form method="get" action="">
          <input type="hidden" name="token" value="<?=h($provided_token)?>">
          <input type="hidden" name="action" value="search">
          <label>Query (min <?=MIN_LEN?> chars)
            <input type="text" name="q" value="<?=h($q)?>" placeholder="username / email / NIC / Passport / Employee ID">
          </label>
          <button type="submit">Search</button>
        </form>
        <div class="small muted">Partial matches across username, email, display name, NIC, passport number, and employee number.</div>
      </fieldset>

<?php if ($edit_user): ?>
      <?php
        $uid = $edit_user->ID;
        $meta = [];
        foreach (meta_field_map() as $k => $_) { $meta[$k] = (string)get_user_meta($uid, $k, true); }
        $first_name = (string)get_user_meta($uid,'first_name',true);
        $last_name  = (string)get_user_meta($uid,'last_name',true);
        $nickname   = (string)get_user_meta($uid,'nickname',true);
      ?>
      <fieldset>
        <legend>Edit User</legend>
        <form method="post" action="">
          <input type="hidden" name="token" value="<?=h($provided_token)?>">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="uid" value="<?=h((string)$uid)?>">

          <div class="stack">
            <div><strong>Username:</strong> <?=h($edit_user->user_login)?></div>
            <div><strong>User ID:</strong> <?=h((string)$uid)?></div>
          </div>

          <label>First Name
            <input type="text" name="first_name" value="<?=h($first_name)?>">
          </label>
          <label>Last Name
            <input type="text" name="last_name" value="<?=h($last_name)?>">
          </label>
          <label>Nickname
            <input type="text" name="nickname" value="<?=h($nickname)?>">
          </label>
          <label>Email
            <input type="email" name="user_email" value="<?=h($edit_user->user_email)?>">
          </label>

          <h3>Custom Fields</h3>
          <?php foreach (meta_field_map() as $key => $label): ?>
            <label><?=h($label)?>
              <input type="text" name="meta[<?=h($key)?>]" value="<?=h($meta[$key] ?? '')?>">
            </label>
          <?php endforeach; ?>

          <div class="stack" style="margin-top:16px">
            <button type="submit">Save Changes</button>
            <a href="?token=<?=h($provided_token)?>&action=search&q=<?=h($q?: (string)$uid)?>"><button type="button">Back to Results</button></a>
          </div>
        </form>
      </fieldset>

<?php elseif ($results): ?>
      <fieldset>
        <legend>Results</legend>
        <table>
          <tr>
            <th>Display Name</th>
            <th>Username</th>
            <th>Email</th>
            <th>NIC</th>
            <th>Passport</th>
            <th>Employee ID</th>
            <th>Action</th>
          </tr>
          <?php foreach ($results as $r): ?>
            <tr>
              <td><?=h($r['display_name'])?></td>
              <td><?=h($r['user_login'])?></td>
              <td><?=h($r['user_email'])?></td>
              <td><?=h($r['nic_no'])?></td>
              <td><?=h($r['passport_no'])?></td>
              <td><?=h($r['employee_number'])?></td>
              <td><a href="?token=<?=h($provided_token)?>&uid=<?=h((string)$r['ID'])?>">Edit</a></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </fieldset>
<?php endif; ?>

    </div>
  </main>
  <script src="/portal-assets/js/tool.js" defer></script>
</body>
</html>
