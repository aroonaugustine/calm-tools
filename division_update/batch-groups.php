<?php
ini_set('display_errors',1);
error_reporting(E_ALL);

require_once '/var/www/html/wp-load.php';
require_once __DIR__.'/batch-worker.php';

// --------------------------------------------------------------------------------------
// CONFIG (Group ID → Correct Division)
// --------------------------------------------------------------------------------------
$GROUP_MAP = [
    "5284" => "YCI - GSS",
    "5288" => "YCI - CW",
    "5290" => "YCI - BJ",
    "5292" => "YCI - PG",
    "5294" => "YCI - M1",
    "5296" => "YCI - TWS",
    "5298" => "YCI - CF",
    "5300" => "YCI - QQ288",
    "5302" => "YCI - SG",
    "5304" => "Diyou",
    "5306" => "Pu Cian - P5",
    "5310" => "IDN",
    "5312" => "200M(A)",
    "5314" => "BTNV",
    "5316" => "Teleperformer",
    "5318" => "KR",
    "5320" => "KRCP",
    "5322" => "KRI",
    "5324" => "KRQP",
    "5326" => "KRVIP",
    "5328" => "HO",
    "5330" => "CX",
    "5332" => "Tiong",
    "5334" => "NOVA",
    "5336" => "WSM - D7",
    "5338" => "WSM - XW",
    "5340" => "WSM - WE",
    "5342" => "WSM - AN",
    "5346" => "WSM - E-pay",
    "5348" => "WSM - WA",
    "5375" => "PortCityHQ",
    "5452" => "TKW PS",
    "5458" => "RANSWIN",
    "5460" => "PANDA",
    "5462" => "XINXUAN",
    "5465" => "YCI - BJ88",
    "5511" => "YCI - BetStar-BS",
    "5515" => "ACE",
    "5517" => "Maxview",
    "5519" => "TNG",
    "5535" => "TKW KG32",
    "5544" => "KWIT BNZ",
    "5597" => "200M(B)",
    "5671" => "NOVA-NT",
    "5673" => "200M",
    "5702" => "SureWell",
    "5705" => "YCI - PR3CS"
];

// Confirm button?
$action = $_GET['action'] ?? '';
$gid    = $_GET['gid'] ?? '';
$dry    = isset($_GET['dry']) ? boolval($_GET['dry']) : true;

// --------------------------------------------------------------------------------------
// Flow Control
// --------------------------------------------------------------------------------------

if ($action === '') {
    echo "<h2>Batch Division Updater (Dry-Run Interactive)</h2>";
    echo "<p>This tool will process each LearnDash Group ID → Division one by one.</p>";
    echo "<p><strong>Dry Run:</strong> ".($dry ? "YES" : "NO")."</p>";
    echo "<hr>";

    echo "<h3>Start the batch job:</h3>";
    echo "<a href='?action=start&index=0&dry=1' style='font-size:20px'>▶ START (DRY RUN)</a>";
    exit;
}

if ($action === 'start') {

    $index = intval($_GET['index']);
    $keys  = array_keys($GROUP_MAP);

    if (!isset($keys[$index])) {
        echo "<h2>🎉 Completed All Groups!</h2>";
        exit;
    }

    $gid = $keys[$index];
    $division = $GROUP_MAP[$gid];

    echo "<h2>Processing Group ID: $gid → $division</h2>";

    $users = batch_get_users_in_group($gid);

    if (!$users) {
        echo "<p>No users in this group.</p>";
        echo "<a href='?action=start&index=".($index+1)."&dry=1'>Next Group ➜</a>";
        exit;
    }

    echo "<h3>Users found: ".count($users)."</h3>";

    echo "<table border='1' cellpadding='6' cellspacing='0'>";
    echo "<tr><th>User Login</th><th>Email</th><th>Current Division</th><th>New Division</th></tr>";

    foreach ($users as $u) {
        $cur = get_user_meta($u->ID, 'division', true);
        echo "<tr>";
        echo "<td>{$u->user_login}</td>";
        echo "<td>{$u->user_email}</td>";
        echo "<td>{$cur}</td>";
        echo "<td>{$division}</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<hr>";
    echo "<h3>Apply changes?</h3>";

    echo "<a style='color:green;font-size:20px' 
          href='?action=apply&gid=$gid&index=$index&dry=0'>
          ✔ YES — Update Division for These Users
          </a><br><br>";

    echo "<a style='color:red;font-size:16px' 
          href='?action=start&index=".($index+1)."&dry=1'>
          ✖ NO — Skip This Group
          </a>";

    exit;
}

if ($action === 'apply') {

    $gid = intval($_GET['gid']);
    $index = intval($_GET['index']);
    $division = $GROUP_MAP[$gid];

    echo "<h2>Applying Changes for Group $gid → $division</h2>";

    $updated = batch_apply_division($gid, $division);

    echo "<p><strong>Updated:</strong> $updated users</p>";

    echo "<hr>";
    echo "<a href='?action=start&index=".($index+1)."&dry=1'>Next Group ➜</a>";
    exit;
}

?>