<?php
/**
 * Script to delete ALL sample/demo deals from the database
 */

// SuiteCRM bootstrap
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');
require_once('modules/Deals/Deal.php');

global $db, $current_user;

// Set current user to admin for permissions
$current_user = new User();
$current_user->getSystemUser();

echo "Starting deletion of ALL sample deals...\n\n";

// Query to find ALL deals with 'Sample' in the name
$query = "SELECT id, name FROM opportunities WHERE deleted = 0 AND name LIKE '%Sample%'";
$result = $db->query($query);

$deletedCount = 0;
$deletedDeals = [];

while ($row = $db->fetchByAssoc($result)) {
    echo "Deleting: {$row['name']} (ID: {$row['id']})\n";
    $deletedDeals[] = $row['name'];
    
    // Load the deal bean and delete it properly
    $deal = new Deal();
    if ($deal->retrieve($row['id'])) {
        $deal->mark_deleted($row['id']);
        $deletedCount++;
    }
}

echo "\n----------------------------------------\n";
echo "Deleted $deletedCount sample deals:\n";
foreach ($deletedDeals as $dealName) {
    echo "  - $dealName\n";
}

// Also clean up any orphaned custom field records
echo "\n----------------------------------------\n";
echo "Cleaning up orphaned custom field records...\n";
$orphanQuery = "DELETE FROM opportunities_cstm WHERE id_c NOT IN (SELECT id FROM opportunities WHERE deleted = 0)";
$db->query($orphanQuery);

// Clear any cache
if (function_exists('sugar_cache_clear')) {
    sugar_cache_clear('Deals');
    echo "Cache cleared.\n";
}

echo "\n----------------------------------------\n";
echo "Sample deal cleanup completed successfully!\n";
echo "All deals with 'Sample' in the name have been removed.\n";