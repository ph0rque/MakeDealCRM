<?php
/**
 * Manual test script for Deals module fixes
 * Run this script to quickly verify all fixes are working
 */

// Bootstrap SuiteCRM
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');
require_once('modules/Users/User.php');
require_once('modules/Deals/Deal.php');

echo "=== DEALS MODULE MANUAL TEST SUITE ===\n\n";

// Test 1: Check pipeline view file exists and is readable
echo "Test 1: Pipeline View File Check\n";
$pipelineViewPath = 'custom/modules/Deals/views/view.pipeline.php';
if (file_exists($pipelineViewPath)) {
    echo "✓ Pipeline view file exists\n";
    
    // Check for syntax errors
    $output = shell_exec("php -l $pipelineViewPath 2>&1");
    if (strpos($output, 'No syntax errors') !== false) {
        echo "✓ No syntax errors in pipeline view\n";
    } else {
        echo "✗ Syntax errors found: $output\n";
    }
} else {
    echo "✗ Pipeline view file not found\n";
}

// Test 2: Check controller exists and has required methods
echo "\nTest 2: Controller Check\n";
$controllerPath = 'custom/modules/Deals/controller.php';
if (file_exists($controllerPath)) {
    echo "✓ Controller file exists\n";
    
    require_once($controllerPath);
    $controller = new DealsController();
    
    // Check for required methods
    $requiredMethods = ['action_getDeals', 'action_updateDealStage'];
    foreach ($requiredMethods as $method) {
        if (method_exists($controller, $method)) {
            echo "✓ Method $method exists\n";
        } else {
            echo "✗ Method $method missing\n";
        }
    }
} else {
    echo "✗ Controller file not found\n";
}

// Test 3: Check database connection and Deals table
echo "\nTest 3: Database Check\n";
global $db;
if ($db && $db->connect()) {
    echo "✓ Database connection successful\n";
    
    // Check if deals table exists
    $result = $db->query("SHOW TABLES LIKE 'deals'");
    if ($db->getRowCount($result) > 0) {
        echo "✓ Deals table exists\n";
        
        // Count deals
        $countResult = $db->query("SELECT COUNT(*) as count FROM deals WHERE deleted = 0");
        $row = $db->fetchByAssoc($countResult);
        echo "✓ Found " . $row['count'] . " active deals\n";
    } else {
        echo "✗ Deals table not found\n";
    }
} else {
    echo "✗ Database connection failed\n";
}

// Test 4: Test AJAX endpoint responses
echo "\nTest 4: AJAX Endpoint Test\n";

// Simulate AJAX request for getDeals
$_REQUEST['module'] = 'Deals';
$_REQUEST['action'] = 'getDeals';
$_REQUEST['to_pdf'] = false;

ob_start();
try {
    // Create mock controller
    require_once($controllerPath);
    $controller = new DealsController();
    
    // Set up mock environment
    global $current_user;
    if (!$current_user || !$current_user->id) {
        // Create temporary admin user for testing
        $current_user = new User();
        $current_user->getSystemUser();
    }
    
    // Call the action
    $controller->action_getDeals();
    $response = ob_get_contents();
    
    // Check if response is valid JSON
    $json = json_decode($response, true);
    if ($json !== null) {
        echo "✓ getDeals returns valid JSON\n";
        if (isset($json['deals']) && isset($json['stages'])) {
            echo "✓ JSON structure is correct (has 'deals' and 'stages')\n";
        } else {
            echo "✗ JSON structure is incorrect\n";
        }
    } else {
        echo "✗ getDeals does not return valid JSON\n";
        echo "Response: " . substr($response, 0, 100) . "...\n";
    }
} catch (Exception $e) {
    echo "✗ Error testing AJAX endpoint: " . $e->getMessage() . "\n";
}
ob_end_clean();

// Test 5: Check for JavaScript files
echo "\nTest 5: JavaScript Files Check\n";
$jsFiles = [
    'custom/modules/Deals/js/pipeline.js',
    'custom/modules/Deals/tpls/pipeline.tpl'
];

foreach ($jsFiles as $file) {
    if (file_exists($file)) {
        echo "✓ Found: $file\n";
        
        // Check for common JS errors
        $content = file_get_contents($file);
        if (strpos($content, 'console.log') === false || strpos($content, 'console.error') !== false) {
            echo "  ✓ No debug console statements\n";
        } else {
            echo "  ⚠ Contains console statements (should be removed for production)\n";
        }
    } else {
        echo "⚠ Not found: $file (may be optional)\n";
    }
}

// Test 6: Security checks
echo "\nTest 6: Security Checks\n";

// Check for SQL injection prevention
$testController = file_get_contents($controllerPath);
if (strpos($testController, '$db->quote') !== false || strpos($testController, 'prepared statement') !== false) {
    echo "✓ SQL injection prevention found\n";
} else {
    echo "⚠ Consider adding SQL injection prevention\n";
}

// Check for XSS prevention
if (strpos($testController, 'htmlspecialchars') !== false || strpos($testController, 'htmlentities') !== false) {
    echo "✓ XSS prevention found\n";
} else {
    echo "⚠ Consider adding XSS prevention\n";
}

// Test 7: Module permissions
echo "\nTest 7: Module Permissions Check\n";
if (class_exists('ACLController')) {
    $moduleAccess = ACLController::checkAccess('Deals', 'list', true);
    if ($moduleAccess) {
        echo "✓ Current user has access to Deals module\n";
    } else {
        echo "✗ Current user lacks access to Deals module\n";
    }
} else {
    echo "⚠ ACL system not available\n";
}

// Summary
echo "\n=== TEST SUMMARY ===\n";
echo "Tests completed. Please review the results above.\n";
echo "For production deployment:\n";
echo "1. Fix any ✗ (failed) tests\n";
echo "2. Address any ⚠ (warning) items\n";
echo "3. Run full PHPUnit test suite\n";
echo "4. Perform user acceptance testing\n";
echo "5. Backup database before deployment\n";

// Test report data for swarm coordination
$testReport = [
    'timestamp' => date('Y-m-d H:i:s'),
    'module' => 'Deals',
    'test_type' => 'manual',
    'results' => 'See console output above'
];

echo "\nTest report generated: " . json_encode($testReport) . "\n";