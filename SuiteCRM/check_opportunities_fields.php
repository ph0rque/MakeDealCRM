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

echo "<h2>Opportunities Table Structure:</h2><pre>";
$result = mysqli_query($db, 'DESCRIBE opportunities');
while ($row = mysqli_fetch_array($result)) {
    echo str_pad($row['Field'], 30) . " | " . $row['Type'] . "\n";
}
echo "</pre>";

// Check accounts_opportunities relationship table
echo "<h2>Accounts-Opportunities Relationship:</h2><pre>";
$result = mysqli_query($db, "SHOW TABLES LIKE '%accounts_opportunities%'");
while ($row = mysqli_fetch_array($result)) {
    echo "Found table: " . $row[0] . "\n";
    
    // Show structure
    $desc_result = mysqli_query($db, "DESCRIBE " . $row[0]);
    while ($desc_row = mysqli_fetch_array($desc_result)) {
        echo "  - " . $desc_row['Field'] . " (" . $desc_row['Type'] . ")\n";
    }
}
echo "</pre>";

mysqli_close($db);
?>