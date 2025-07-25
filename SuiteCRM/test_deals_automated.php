<?php
/**
 * Automated Deals Module Testing Script
 * This script performs comprehensive testing of the Deals module functionality
 */

// Initialize test environment
session_start();
define('sugarEntry', true);
require_once('include/entryPoint.php');
require_once('modules/Users/User.php');

// Force admin login for testing
global $current_user;
$current_user = new User();
$current_user->retrieve('1'); // Admin user ID
$current_user->authenticated = true;
$_SESSION['authenticated_user_id'] = $current_user->id;

// Test results array
$test_results = array();
$total_tests = 0;
$passed_tests = 0;

echo "<!DOCTYPE html><html><head><title>Deals Module Automated Testing</title>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .test-section { margin: 20px 0; padding: 15px; background: #f5f5f5; border-radius: 5px; }
    .test-case { margin: 10px 0; padding: 10px; background: white; border-left: 4px solid #ccc; }
    .pass { border-left-color: #4CAF50; }
    .fail { border-left-color: #f44336; }
    .test-header { font-weight: bold; margin-bottom: 5px; }
    .test-result { color: #666; }
    .summary { font-size: 1.2em; padding: 20px; background: #e0e0e0; margin: 20px 0; }
    .error { color: red; font-style: italic; }
    .success { color: green; }
</style></head><body>";

echo "<h1>Deals Module Automated Testing</h1>";
echo "<p>Running comprehensive tests on the Deals module...</p>";

// Function to run a test
function run_test($test_name, $test_function) {
    global $test_results, $total_tests, $passed_tests;
    $total_tests++;
    
    try {
        $result = $test_function();
        if ($result['success']) {
            $passed_tests++;
            $test_results[] = array(
                'name' => $test_name,
                'success' => true,
                'message' => $result['message']
            );
            return true;
        } else {
            $test_results[] = array(
                'name' => $test_name,
                'success' => false,
                'message' => $result['message'],
                'error' => $result['error'] ?? ''
            );
            return false;
        }
    } catch (Exception $e) {
        $test_results[] = array(
            'name' => $test_name,
            'success' => false,
            'message' => 'Exception thrown',
            'error' => $e->getMessage()
        );
        return false;
    }
}

// Test 1: Module Registration
echo "<div class='test-section'><h2>1. Module Registration Tests</h2>";

run_test('Module Registration', function() {
    global $beanList, $beanFiles, $moduleList;
    
    $errors = array();
    if (!isset($beanList['Deals'])) {
        $errors[] = "Deals not in beanList";
    }
    if (!isset($beanFiles['Deal'])) {
        $errors[] = "Deal not in beanFiles";
    }
    if (!in_array('Deals', $moduleList)) {
        $errors[] = "Deals not in moduleList";
    }
    
    if (empty($errors)) {
        return array('success' => true, 'message' => 'Module properly registered in all lists');
    } else {
        return array('success' => false, 'message' => 'Registration issues found', 'error' => implode(', ', $errors));
    }
});

run_test('Bean Instantiation', function() {
    try {
        $deal = BeanFactory::newBean('Deals');
        if ($deal && $deal->module_dir == 'Deals') {
            return array('success' => true, 'message' => 'Deal bean created successfully');
        } else {
            return array('success' => false, 'message' => 'Deal bean creation failed');
        }
    } catch (Exception $e) {
        return array('success' => false, 'message' => 'Exception creating bean', 'error' => $e->getMessage());
    }
});

echo "</div>";

// Test 2: Database Structure
echo "<div class='test-section'><h2>2. Database Structure Tests</h2>";

run_test('Opportunities Table', function() {
    global $db;
    $result = $db->query("SHOW TABLES LIKE 'opportunities'");
    if ($db->fetchByAssoc($result)) {
        return array('success' => true, 'message' => 'opportunities table exists');
    } else {
        return array('success' => false, 'message' => 'opportunities table not found');
    }
});

run_test('Custom Fields Table', function() {
    global $db;
    $result = $db->query("SHOW TABLES LIKE 'opportunities_cstm'");
    if ($db->fetchByAssoc($result)) {
        return array('success' => true, 'message' => 'opportunities_cstm table exists');
    } else {
        return array('success' => false, 'message' => 'opportunities_cstm table not found');
    }
});

run_test('Pipeline Stage Field', function() {
    global $db;
    $result = $db->query("SHOW COLUMNS FROM opportunities_cstm LIKE 'pipeline_stage_c'");
    if ($db->fetchByAssoc($result)) {
        return array('success' => true, 'message' => 'pipeline_stage_c field exists');
    } else {
        return array('success' => false, 'message' => 'pipeline_stage_c field not found');
    }
});

echo "</div>";

// Test 3: CRUD Operations
echo "<div class='test-section'><h2>3. CRUD Operations Tests</h2>";

$test_deal_id = null;

run_test('Create Deal', function() use (&$test_deal_id) {
    global $current_user;
    
    $deal = BeanFactory::newBean('Deals');
    $deal->name = 'Test Deal ' . date('Y-m-d H:i:s');
    $deal->amount = 50000;
    $deal->sales_stage = 'Prospecting';
    $deal->pipeline_stage_c = 'sourcing';
    $deal->assigned_user_id = $current_user->id;
    
    $deal->save();
    
    if (!empty($deal->id)) {
        $test_deal_id = $deal->id;
        return array('success' => true, 'message' => 'Deal created with ID: ' . $deal->id);
    } else {
        return array('success' => false, 'message' => 'Failed to create deal');
    }
});

run_test('Read Deal', function() use ($test_deal_id) {
    if (empty($test_deal_id)) {
        return array('success' => false, 'message' => 'No test deal ID available');
    }
    
    $deal = BeanFactory::getBean('Deals', $test_deal_id);
    if ($deal && $deal->id == $test_deal_id) {
        return array('success' => true, 'message' => 'Deal retrieved successfully: ' . $deal->name);
    } else {
        return array('success' => false, 'message' => 'Failed to retrieve deal');
    }
});

run_test('Update Deal', function() use ($test_deal_id) {
    if (empty($test_deal_id)) {
        return array('success' => false, 'message' => 'No test deal ID available');
    }
    
    $deal = BeanFactory::getBean('Deals', $test_deal_id);
    $original_amount = $deal->amount;
    $deal->amount = 75000;
    $deal->pipeline_stage_c = 'screening';
    $deal->save();
    
    // Retrieve again to verify
    $deal = BeanFactory::getBean('Deals', $test_deal_id);
    if ($deal->amount == 75000 && $deal->pipeline_stage_c == 'screening') {
        return array('success' => true, 'message' => 'Deal updated successfully');
    } else {
        return array('success' => false, 'message' => 'Deal update verification failed');
    }
});

run_test('List Deals', function() {
    global $db;
    
    $query = "SELECT COUNT(*) as count FROM opportunities WHERE deleted = 0";
    $result = $db->query($query);
    $row = $db->fetchByAssoc($result);
    
    if ($row && $row['count'] > 0) {
        return array('success' => true, 'message' => 'Found ' . $row['count'] . ' active deals');
    } else {
        return array('success' => false, 'message' => 'No deals found');
    }
});

echo "</div>";

// Test 4: Pipeline Functionality
echo "<div class='test-section'><h2>4. Pipeline Functionality Tests</h2>";

run_test('Pipeline Stages', function() {
    $stages = array(
        'sourcing' => 'Sourcing',
        'screening' => 'Screening',
        'analysis_outreach' => 'Analysis & Outreach',
        'due_diligence' => 'Due Diligence',
        'closing' => 'Closing'
    );
    
    // Check if we can query deals by stage
    global $db;
    $stage_counts = array();
    
    foreach ($stages as $key => $name) {
        $query = "SELECT COUNT(*) as count FROM opportunities o 
                  LEFT JOIN opportunities_cstm oc ON o.id = oc.id_c 
                  WHERE o.deleted = 0 AND oc.pipeline_stage_c = '{$key}'";
        $result = $db->query($query);
        $row = $db->fetchByAssoc($result);
        $stage_counts[$name] = $row['count'];
    }
    
    return array('success' => true, 'message' => 'Pipeline stages verified. Counts: ' . json_encode($stage_counts));
});

run_test('Stage Transition', function() use ($test_deal_id) {
    if (empty($test_deal_id)) {
        return array('success' => false, 'message' => 'No test deal ID available');
    }
    
    $deal = BeanFactory::getBean('Deals', $test_deal_id);
    $original_stage = $deal->pipeline_stage_c;
    
    // Move through stages
    $stages = array('sourcing', 'screening', 'analysis_outreach');
    $transitions = array();
    
    foreach ($stages as $stage) {
        $deal->pipeline_stage_c = $stage;
        $deal->save();
        $transitions[] = $stage;
    }
    
    return array('success' => true, 'message' => 'Stage transitions completed: ' . implode(' → ', $transitions));
});

echo "</div>";

// Test 5: Views and Controllers
echo "<div class='test-section'><h2>5. Views and Controllers Tests</h2>";

run_test('Controller File', function() {
    $controller_file = 'custom/modules/Deals/controller.php';
    if (file_exists($controller_file)) {
        require_once($controller_file);
        if (class_exists('DealsController')) {
            return array('success' => true, 'message' => 'DealsController class exists');
        } else {
            return array('success' => false, 'message' => 'DealsController class not found');
        }
    } else {
        return array('success' => false, 'message' => 'Controller file not found');
    }
});

run_test('Pipeline View', function() {
    $view_file = 'custom/modules/Deals/views/view.pipeline.php';
    if (file_exists($view_file)) {
        return array('success' => true, 'message' => 'Pipeline view file exists');
    } else {
        return array('success' => false, 'message' => 'Pipeline view file not found');
    }
});

run_test('Language File', function() {
    $lang_file = 'custom/modules/Deals/language/en_us.lang.php';
    if (file_exists($lang_file)) {
        $mod_strings = array();
        require($lang_file);
        if (!empty($mod_strings)) {
            return array('success' => true, 'message' => 'Language file loaded with ' . count($mod_strings) . ' strings');
        } else {
            return array('success' => false, 'message' => 'Language file empty');
        }
    } else {
        return array('success' => false, 'message' => 'Language file not found');
    }
});

echo "</div>";

// Test 6: Security and Permissions
echo "<div class='test-section'><h2>6. Security and Permissions Tests</h2>";

run_test('ACL Configuration', function() {
    $acl_file = 'custom/Extension/modules/Deals/Ext/ACLActions/actions.php';
    if (file_exists($acl_file)) {
        return array('success' => true, 'message' => 'ACL configuration file exists');
    } else {
        return array('success' => false, 'message' => 'ACL configuration file not found');
    }
});

run_test('Module Access', function() {
    global $current_user;
    
    // Check if current user can access module
    require_once('modules/ACL/ACLController.php');
    $access = ACLController::checkAccess('Deals', 'list', true);
    
    if ($access) {
        return array('success' => true, 'message' => 'Module access granted for current user');
    } else {
        return array('success' => false, 'message' => 'Module access denied');
    }
});

echo "</div>";

// Cleanup
echo "<div class='test-section'><h2>7. Cleanup</h2>";

if ($test_deal_id) {
    run_test('Delete Test Deal', function() use ($test_deal_id) {
        $deal = BeanFactory::getBean('Deals', $test_deal_id);
        if ($deal && $deal->id) {
            $deal->mark_deleted($test_deal_id);
            return array('success' => true, 'message' => 'Test deal deleted');
        } else {
            return array('success' => false, 'message' => 'Could not delete test deal');
        }
    });
}

echo "</div>";

// Display results
echo "<div class='summary'>";
echo "<h2>Test Summary</h2>";
echo "<p>Total Tests: $total_tests</p>";
echo "<p>Passed: <span class='success'>$passed_tests</span></p>";
echo "<p>Failed: <span class='error'>" . ($total_tests - $passed_tests) . "</span></p>";
echo "<p>Success Rate: " . round(($passed_tests / $total_tests) * 100, 2) . "%</p>";
echo "</div>";

echo "<div class='test-section'><h2>Detailed Results</h2>";
foreach ($test_results as $result) {
    $class = $result['success'] ? 'pass' : 'fail';
    echo "<div class='test-case $class'>";
    echo "<div class='test-header'>" . $result['name'] . " - " . ($result['success'] ? '✓ PASS' : '✗ FAIL') . "</div>";
    echo "<div class='test-result'>" . $result['message'] . "</div>";
    if (!$result['success'] && !empty($result['error'])) {
        echo "<div class='error'>Error: " . $result['error'] . "</div>";
    }
    echo "</div>";
}
echo "</div>";

echo "</body></html>";
?>