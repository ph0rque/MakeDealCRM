<?php
/**
 * Test script to verify Deals module functionality
 */

// SuiteCRM bootstrap
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');

global $current_user, $db;

// Set current user to admin
$current_user = new User();
$current_user->getSystemUser();

echo "=== Testing Deals Module Functionality ===\n\n";

// 1. Test module registration
echo "1. Module Registration:\n";
echo "   - Deals in beanList: " . (isset($GLOBALS['beanList']['Deals']) ? 'YES' : 'NO') . "\n";
echo "   - Deal bean file exists: " . (file_exists('modules/Deals/Deal.php') ? 'YES' : 'NO') . "\n";
echo "   - Deals in moduleList: " . (in_array('Deals', $GLOBALS['moduleList']) ? 'YES' : 'NO') . "\n";

// 2. Test bean creation
echo "\n2. Bean Creation:\n";
try {
    $deal = BeanFactory::newBean('Deals');
    echo "   - Deal bean created: YES\n";
    echo "   - Module name: " . $deal->module_name . "\n";
    echo "   - Table name: " . $deal->table_name . "\n";
    echo "   - Object name: " . $deal->object_name . "\n";
} catch (Exception $e) {
    echo "   - ERROR creating bean: " . $e->getMessage() . "\n";
}

// 3. Test ACL permissions
echo "\n3. ACL Permissions:\n";
$actions = ['list', 'view', 'edit', 'delete', 'import', 'export'];
foreach ($actions as $action) {
    $hasAccess = ACLController::checkAccess('Deals', $action, true);
    echo "   - $action: " . ($hasAccess ? 'ALLOWED' : 'DENIED') . "\n";
}

// 4. Test views
echo "\n4. View Files:\n";
$views = ['edit', 'detail', 'list', 'pipeline'];
foreach ($views as $view) {
    $customPath = "custom/modules/Deals/views/view.$view.php";
    $corePath = "modules/Deals/views/view.$view.php";
    
    if (file_exists($customPath)) {
        echo "   - $view view: EXISTS (custom)\n";
    } elseif (file_exists($corePath)) {
        echo "   - $view view: EXISTS (core)\n";
    } else {
        echo "   - $view view: MISSING\n";
    }
}

// 5. Test metadata
echo "\n5. Metadata Files:\n";
$metadataFiles = ['editviewdefs', 'detailviewdefs', 'listviewdefs'];
foreach ($metadataFiles as $file) {
    $customPath = "custom/modules/Deals/metadata/$file.php";
    $corePath = "modules/Deals/metadata/$file.php";
    
    if (file_exists($customPath)) {
        echo "   - $file: EXISTS (custom)\n";
    } elseif (file_exists($corePath)) {
        echo "   - $file: EXISTS (core)\n";
    } else {
        echo "   - $file: MISSING\n";
    }
}

// 6. Test database
echo "\n6. Database Status:\n";
$tableCheck = $db->query("SHOW TABLES LIKE 'opportunities'");
if ($db->fetchByAssoc($tableCheck)) {
    echo "   - opportunities table: EXISTS\n";
    
    // Count deals
    $countQuery = "SELECT COUNT(*) as count FROM opportunities WHERE deleted = 0";
    $result = $db->query($countQuery);
    $row = $db->fetchByAssoc($result);
    echo "   - Active deals count: " . $row['count'] . "\n";
    
    // Check for sample deals
    $sampleQuery = "SELECT COUNT(*) as count FROM opportunities WHERE name LIKE 'Sample%' AND deleted = 0";
    $result = $db->query($sampleQuery);
    $row = $db->fetchByAssoc($result);
    echo "   - Sample deals count: " . $row['count'] . "\n";
} else {
    echo "   - opportunities table: NOT FOUND\n";
}

// 7. Test module in database
echo "\n7. Module Database Entry:\n";
$moduleQuery = "SELECT * FROM modules WHERE name = 'Deals' AND deleted = 0";
$result = $db->query($moduleQuery);
if ($row = $db->fetchByAssoc($result)) {
    echo "   - Module registered: YES\n";
    echo "   - Tab enabled: " . ($row['tab'] ? 'YES' : 'NO') . "\n";
} else {
    echo "   - Module registered: NO\n";
}

// 8. Test menu file
echo "\n8. Menu Configuration:\n";
$menuFile = "custom/modules/Deals/Menu.php";
if (file_exists($menuFile)) {
    echo "   - Menu file exists: YES\n";
    // Check if Create Deals is in menu
    $menuContent = file_get_contents($menuFile);
    if (strpos($menuContent, 'CreateDeals') !== false) {
        echo "   - Create Deals menu item: CONFIGURED\n";
    } else {
        echo "   - Create Deals menu item: NOT FOUND\n";
    }
} else {
    echo "   - Menu file exists: NO\n";
}

echo "\n=== Test Complete ===\n";
echo "\nRecommendations:\n";
echo "1. Clear browser cache and cookies\n";
echo "2. Logout and login again\n";
echo "3. Navigate to: http://localhost:8080/index.php?module=Deals&action=Pipeline\n";
echo "4. Test 'Add Deal' button in pipeline columns\n";
echo "5. Test 'Create Deals' from main menu\n";