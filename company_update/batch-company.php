<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '/var/www/html/wp-load.php';
require_once __DIR__ . '/batch-company-worker.php';

echo "<h2>Company Name Cleanup (Interactive Batch Tool)</h2>";

$action = $_GET['action'] ?? '';
$idx     = intval($_GET['idx'] ?? 0);
$dry     = isset($_GET['dry']) ? boolval($_GET['dry']) : true;

// Load all grouped company variants
$groups = batch_company_get_groups();

if ($action === '') {

    echo "<p>This tool will:</p>
    <ul>
        <li>Group company names by case-insensitive match</li>
        <li>Let you choose the final canonical company name</li>
        <li>Show all users that will be updated</li>
        <li>Let you confirm (YES/NO) per group</li>
    </ul>";

    echo "<p><strong>Dry Run Mode:</strong> ".($dry ? "YES" : "NO")."</p>";

    echo "<hr>";
    echo "<a href='?action=start&idx=0&dry=1' style='font-size:20px;'>▶ START (DRY RUN)</a>";
    exit;
}


// -----------------------------------------------------------
// Start processing groups
// -----------------------------------------------------------
if ($action === 'start') {

    if (!isset($groups[$idx])) {
        echo "<h2>🎉 Completed all companies!</h2>";
        exit;
    }

    $group = $groups[$idx];

    $canonical = $group['suggested']; // default suggestion
    $variants  = $group['variants'];   // all company names detected

    echo "<h2>Company Group ".($idx+1)." of ".count($groups)."</h2>";
    echo "<h3>Detected Variants:</h3>";

    echo "<table border='1' cellpadding='6' cellspacing='0'>";
    echo "<tr><th>Company Name</th><th>User Count</th></tr>";

    foreach ($variants as $name => $users) {
        echo "<tr><td>$name</td><td>".count($users)."</td></tr>";
    }
    echo "</table>";

    echo "<hr>";

    echo "<h3>Select Canonical Name</h3>";
    echo "<form method='GET'>";
    echo "<input type='hidden' name='action' value='preview'>";
    echo "<input type='hidden' name='idx' value='$idx'>";
    echo "<input type='hidden' name='dry' value='$dry'>";

    echo "<p><strong>Choose one of the variants:</strong></p>";
    foreach ($variants as $name => $_u) {
        echo "<label><input type='radio' name='chosen' value=\"".htmlspecialchars($name)."\"> $name</label><br>";
    }

    echo "<p><strong>OR enter your own new canonical name:</strong></p>";
    echo "<input type='text' name='manual' style='width:400px;' placeholder='Type new company name'>";

    echo "<br><br><button type='submit' style='padding:10px 20px;'>Continue</button>";

    echo "</form>";
    exit;
}


// -----------------------------------------------------------
// Preview users before applying
// -----------------------------------------------------------
if ($action === 'preview') {

    $group   = $groups[$idx];
    $manual  = trim($_GET['manual'] ?? '');
    $chosen  = trim($_GET['chosen'] ?? '');
    $final   = $manual !== '' ? $manual : $chosen;

    if ($final === '') {
        echo "<p style='color:red;'>❗ You must choose or type a new canonical name.</p>";
        echo "<a href='?action=start&idx=$idx&dry=$dry'>Go Back</a>";
        exit;
    }

    $all_users = batch_company_collect_all_users($group['variants']);

    echo "<h2>Preview: Update Company Name to:</h2>";
    echo "<h2 style='color:blue;'>$final</h2>";

    echo "<h3>Total Users Affected: ".count($all_users)."</h3>";

    echo "<table border='1' cellpadding='6' cellspacing='0'>";
    echo "<tr><th>User Login</th><th>Email</th><th>Old Company</th><th>New Company</th></tr>";

    foreach ($all_users as $u) {
        $old = get_user_meta($u->ID, 'company', true);
        echo "<tr>";
        echo "<td>{$u->user_login}</td>";
        echo "<td>{$u->user_email}</td>";
        echo "<td>$old</td>";
        echo "<td>$final</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<hr>";
    echo "<h3>Apply Changes?</h3>";

    echo "<a style='color:green;font-size:20px;' 
        href='?action=apply&idx=$idx&final=".urlencode($final)."&dry=0'>
        ✔ YES — Update these ".count($all_users)." users
    </a><br><br>";

    echo "<a style='color:red;font-size:16px;' 
        href='?action=start&idx=".($idx+1)."&dry=1'>
        ✖ NO — Skip this company
    </a>";

    exit;
}


// -----------------------------------------------------------
// Apply updates
// -----------------------------------------------------------
if ($action === 'apply') {

    $group  = $groups[$idx];
    $final  = $_GET['final'];
    $count  = batch_company_apply($group['variants'], $final);

    echo "<h2>Applied Changes</h2>";
    echo "<p><strong>Updated:</strong> $count users</p>";

    echo "<hr>";
    echo "<a href='?action=start&idx=".($idx+1)."&dry=1'>Next Company ➜</a>";
    exit;
}

?>