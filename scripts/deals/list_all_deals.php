<?php
/**
 * List all deals in the system
 */

// Set up SuiteCRM environment
if (!defined('sugarEntry')) define('sugarEntry', true);
// Check if we need to change directory
if (file_exists('SuiteCRM/include/entryPoint.php')) {
    chdir('SuiteCRM');
}
require_once('include/entryPoint.php');

global $db;

echo "=== ALL DEALS IN DATABASE ===\n\n";

// Check all deals (including deleted)
$query = "SELECT id, name, sales_stage, deleted, date_entered, date_modified 
          FROM deals 
          ORDER BY date_entered DESC";

$result = $db->query($query);
$total = 0;
$active = 0;

while ($row = $db->fetchByAssoc($result)) {
    $total++;
    if ($row['deleted'] == 0) {
        $active++;
    }
    
    $status = $row['deleted'] == 0 ? 'Active' : 'Deleted';
    echo "ID: {$row['id']}\n";
    echo "Name: {$row['name']}\n";
    echo "Sales Stage: " . ($row['sales_stage'] ?: 'None') . "\n";
    echo "Status: $status\n";
    echo "Created: {$row['date_entered']}\n";
    echo "Modified: {$row['date_modified']}\n";
    echo str_repeat("-", 50) . "\n";
}

echo "\nSummary:\n";
echo "Total deals: $total\n";
echo "Active deals: $active\n";
echo "Deleted deals: " . ($total - $active) . "\n";

// Also check if there are any records without proper IDs
echo "\n\nChecking for malformed records:\n";
$query2 = "SELECT COUNT(*) as count FROM deals WHERE id IS NULL OR id = ''";
$result2 = $db->query($query2);
$row2 = $db->fetchByAssoc($result2);
echo "Records with NULL or empty ID: " . $row2['count'] . "\n";