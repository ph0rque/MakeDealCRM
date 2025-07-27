<?php
/**
 * Test script to debug deal link generation
 */

// Bootstrap SuiteCRM
define('sugarEntry', true);
chdir('/var/www/html');
require_once('include/entryPoint.php');
require_once('modules/Deals/Deal.php');

// Get current user
global $current_user, $db;
if (empty($current_user->id)) {
    $current_user->getSystemUser();
}

echo "=== Deal Link Generation Test ===\n\n";

// Test 1: Check Deal bean configuration
echo "1. Deal Bean Configuration:\n";
$deal = new Deal();
echo "   Module Name: " . $deal->module_name . "\n";
echo "   Object Name: " . $deal->object_name . "\n";
echo "   Module Dir: " . $deal->module_dir . "\n";
echo "   Table Name: " . $deal->table_name . "\n\n";

// Test 2: Generate a detail view link
echo "2. Generated Links:\n";
$testId = 'test-123';
$detailLink = "index.php?module={$deal->module_name}&action=DetailView&record={$testId}";
echo "   Detail View Link: " . $detailLink . "\n";

// Test 3: Check actual deals in database
echo "\n3. Actual Deals in Database:\n";
$query = "SELECT id, name FROM {$deal->table_name} WHERE deleted = 0 LIMIT 5";
$result = $db->query($query);
$count = 0;
while ($row = $db->fetchByAssoc($result)) {
    $count++;
    echo "   Deal {$count}: {$row['name']} (ID: {$row['id']})\n";
    echo "   Link: index.php?module=Deals&action=DetailView&record={$row['id']}\n";
}

// Test 4: Check if Opportunities module exists
echo "\n4. Module Check:\n";
if (file_exists('modules/Opportunities/Opportunity.php')) {
    echo "   Opportunities module exists\n";
    require_once('modules/Opportunities/Opportunity.php');
    $opp = new Opportunity();
    echo "   Opportunities table: " . $opp->table_name . "\n";
}

// Test 5: Check listviewdefs
echo "\n5. List View Configuration:\n";
if (file_exists('custom/modules/Deals/metadata/listviewdefs.php')) {
    include('custom/modules/Deals/metadata/listviewdefs.php');
    if (isset($listViewDefs['Deals']['NAME'])) {
        echo "   NAME field configuration:\n";
        echo "   - link: " . ($listViewDefs['Deals']['NAME']['link'] ? 'true' : 'false') . "\n";
        echo "   - width: " . $listViewDefs['Deals']['NAME']['width'] . "\n";
    }
}

// Test 6: Check ACL
echo "\n6. ACL Check:\n";
echo "   Can List: " . (ACLController::checkAccess('Deals', 'list', true) ? 'Yes' : 'No') . "\n";
echo "   Can View: " . (ACLController::checkAccess('Deals', 'view', true) ? 'Yes' : 'No') . "\n";
echo "   Can Edit: " . (ACLController::checkAccess('Deals', 'edit', true) ? 'Yes' : 'No') . "\n";

echo "\n=== End of Test ===\n";