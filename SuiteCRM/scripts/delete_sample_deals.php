<?php
/**
 * Script to delete sample/test deals from the database
 */

// SuiteCRM bootstrap
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');
require_once('modules/Deals/Deal.php');

global $db, $current_user;

// Set current user to admin for permissions
$current_user = new User();
$current_user->getSystemUser();

echo "Starting cleanup of sample deals...\n";

// Sample deal names to delete
$sampleDealNames = [
    'Sample TechCorp Acquisition',
    'Sample DataSystems Merger',
    'Sample CloudVentures Partnership',
    'Sample InnovateTech Deal',
    'Sample GlobalSoft Integration',
    'Test Deal%',
    'Sample%'
];

// Build query to find sample deals
$whereConditions = [];
foreach ($sampleDealNames as $name) {
    $whereConditions[] = "name LIKE '$name'";
}
$whereClause = implode(' OR ', $whereConditions);

// Query to get sample deals
$query = "SELECT id, name FROM opportunities WHERE deleted = 0 AND ($whereClause)";
$result = $db->query($query);

$deletedCount = 0;
while ($row = $db->fetchByAssoc($result)) {
    echo "Deleting deal: {$row['name']} (ID: {$row['id']})\n";
    
    // Load the deal bean and delete it properly
    $deal = new Deal();
    if ($deal->retrieve($row['id'])) {
        $deal->mark_deleted($row['id']);
        $deletedCount++;
    }
}

echo "\nDeleted $deletedCount sample deals.\n";

// Also clean up any orphaned custom field records
echo "\nCleaning up orphaned custom field records...\n";
$orphanQuery = "DELETE FROM opportunities_cstm WHERE id_c NOT IN (SELECT id FROM opportunities WHERE deleted = 0)";
$orphanResult = $db->query($orphanQuery);
echo "Cleaned up orphaned custom field records.\n";

echo "\nSample deal cleanup completed!\n";