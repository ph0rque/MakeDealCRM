<?php
/**
 * Direct test of Deals module functionality
 */

// Bootstrap SuiteCRM
define('sugarEntry', true);
chdir('/var/www/html');
require_once('include/entryPoint.php');
require_once('modules/Deals/Deal.php');

// Get admin user
global $current_user, $db;
$current_user->retrieve('1');

echo "=== Direct Deals Module Test ===\n\n";

// Test 1: Create a test deal
echo "1. Creating test deal...\n";
$deal = new Deal();
$deal->name = 'Test Deal ' . time();
$deal->amount = 1000000;
$deal->sales_stage = 'Prospecting';
$deal->assigned_user_id = $current_user->id;
$deal->pipeline_stage_c = 'sourcing';
$dealId = $deal->save();
echo "   Created deal with ID: $dealId\n";

// Test 2: Generate detail view URL
$detailUrl = "index.php?module=Deals&action=DetailView&record=$dealId";
echo "   Detail URL: $detailUrl\n\n";

// Test 3: Check if detail view file exists
echo "2. Checking detail view files...\n";
$viewFiles = [
    'modules/Deals/views/view.detail.php',
    'custom/modules/Deals/views/view.detail.php',
    'modules/Deals/metadata/detailviewdefs.php',
    'custom/modules/Deals/metadata/detailviewdefs.php'
];

foreach ($viewFiles as $file) {
    echo "   $file: " . (file_exists($file) ? "EXISTS" : "NOT FOUND") . "\n";
}

// Test 4: Check controller actions
echo "\n3. Checking controller...\n";
if (file_exists('custom/modules/Deals/controller.php')) {
    require_once('custom/modules/Deals/controller.php');
    $controller = new DealsController();
    $methods = get_class_methods($controller);
    echo "   Available actions:\n";
    foreach ($methods as $method) {
        if (strpos($method, 'action_') === 0) {
            echo "   - $method\n";
        }
    }
}

// Test 5: Test direct DetailView access
echo "\n4. Testing DetailView class...\n";
if (file_exists('modules/Deals/views/view.detail.php')) {
    require_once('modules/Deals/views/view.detail.php');
    echo "   DealsViewDetail class loaded successfully\n";
    
    // Try to instantiate
    try {
        $_REQUEST['module'] = 'Deals';
        $_REQUEST['action'] = 'DetailView';
        $_REQUEST['record'] = $dealId;
        
        $view = new DealsViewDetail();
        echo "   View instantiated successfully\n";
        
        // Check if bean can be loaded
        $view->bean = BeanFactory::getBean('Deals', $dealId);
        if ($view->bean && $view->bean->id) {
            echo "   Bean loaded: " . $view->bean->name . "\n";
        } else {
            echo "   ERROR: Could not load bean\n";
        }
    } catch (Exception $e) {
        echo "   ERROR: " . $e->getMessage() . "\n";
    }
}

// Test 6: Check for JavaScript errors
echo "\n5. Checking JavaScript files...\n";
$jsFiles = [
    'modules/Deals/Deal.js',
    'modules/Deals/javascript/DealsDetailView.js',
    'custom/modules/Deals/Deal.js'
];

foreach ($jsFiles as $file) {
    if (file_exists($file)) {
        echo "   $file: EXISTS (" . filesize($file) . " bytes)\n";
        
        // Check for syntax errors
        $content = file_get_contents($file);
        if (strpos($content, 'syntax error') !== false || strpos($content, 'SyntaxError') !== false) {
            echo "     WARNING: Possible syntax error in file\n";
        }
    } else {
        echo "   $file: NOT FOUND\n";
    }
}

// Test 7: Check ACL for detail view
echo "\n6. Testing ACL...\n";
echo "   Module access: " . (ACLController::checkAccess('Deals', 'view', true) ? "ALLOWED" : "DENIED") . "\n";
echo "   Record access: " . ($deal->ACLAccess('view') ? "ALLOWED" : "DENIED") . "\n";

// Test 8: Check URL routing
echo "\n7. Testing URL routing...\n";
echo "   Module in moduleList: " . (in_array('Deals', $moduleList) ? "YES" : "NO") . "\n";
echo "   Bean registered: " . (isset($beanList['Deals']) ? "YES ({$beanList['Deals']})" : "NO") . "\n";

// Clean up
$deal->mark_deleted($dealId);
echo "\n=== Test Complete ===\n";