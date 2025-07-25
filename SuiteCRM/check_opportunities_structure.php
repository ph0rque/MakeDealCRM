<?php
/**
 * Check opportunities table structure
 */

if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');

global $db;

echo "Checking opportunities table structure...\n\n";

// Get all columns
$query = "SHOW COLUMNS FROM opportunities";
$result = $db->query($query);

echo "Columns in opportunities table:\n";
while ($row = $db->fetchByAssoc($result)) {
    echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
}

// Check for account relationship
echo "\n\nChecking for account relationships...\n";
$query2 = "SHOW TABLES LIKE '%account%opportunit%'";
$result2 = $db->query($query2);
while ($row = $db->fetchByAssoc($result2)) {
    $tableName = array_values($row)[0];
    echo "Found relationship table: $tableName\n";
    
    // Show its structure
    $query3 = "SHOW COLUMNS FROM $tableName";
    $result3 = $db->query($query3);
    echo "  Columns:\n";
    while ($col = $db->fetchByAssoc($result3)) {
        echo "    - " . $col['Field'] . "\n";
    }
}

echo "\nDone!\n";
?>