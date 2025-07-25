<?php
define('sugarEntry', true);
require_once('config.php');

$db = mysqli_connect(
    $sugar_config['dbconfig']['db_host_name'],
    $sugar_config['dbconfig']['db_user_name'],
    $sugar_config['dbconfig']['db_password'],
    $sugar_config['dbconfig']['db_name']
);

if (!$db) {
    die('Could not connect: ' . mysqli_error($db));
}

echo "<h2>Checking focus field issue:</h2><pre>";

// Check if focus_c column exists
$result = mysqli_query($db, "SHOW COLUMNS FROM opportunities LIKE '%focus%'");
echo "Focus-related columns in opportunities table:\n";
while ($row = mysqli_fetch_array($result)) {
    echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
}

// The issue is the Deal bean is trying to save focus_c but the column is focus_flag_c
// We have two options:
// 1. Add focus_c column
// 2. Remove focus_c from being saved

echo "\nAdding focus_c column to match the bean property...\n";
$query = "ALTER TABLE opportunities ADD COLUMN focus_c tinyint(1) DEFAULT 0";
if (mysqli_query($db, $query)) {
    echo "✓ Added focus_c column\n";
} else {
    echo "✗ Error adding focus_c: " . mysqli_error($db) . "\n";
}

echo "\nDone!</pre>";
echo "<p><a href='index.php?module=Deals&action=EditView'>Try creating a deal again</a></p>";

mysqli_close($db);
?>