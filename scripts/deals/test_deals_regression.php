<?php
/**
 * Regression Tests for Deals Module
 * Run this to ensure all basic functionality is working
 */

// Set up SuiteCRM environment
define('sugarEntry', true);
chdir('SuiteCRM');
require_once('include/entryPoint.php');
require_once('modules/Opportunities/Opportunity.php');

echo "Running Deals Module Regression Tests...\n\n";

$tests_passed = 0;
$tests_failed = 0;

// Test 1: Check if Deals module exists
echo "Test 1: Checking if Deals module exists... ";
if (file_exists('modules/Deals')) {
    echo "PASSED\n";
    $tests_passed++;
} else {
    echo "FAILED\n";
    $tests_failed++;
}

// Test 2: Check if Deal bean can be instantiated
echo "Test 2: Checking Deal bean instantiation... ";
try {
    $deal = BeanFactory::newBean('Opportunities');
    if ($deal) {
        echo "PASSED\n";
        $tests_passed++;
    } else {
        echo "FAILED\n";
        $tests_failed++;
    }
} catch (Exception $e) {
    echo "FAILED - " . $e->getMessage() . "\n";
    $tests_failed++;
}

// Test 3: Check if custom fields exist
echo "Test 3: Checking custom fields... ";
$custom_fields = array('pipeline_stage_c', 'stage_entered_date_c', 'focus_flag_c');
$all_fields_exist = true;
$missing_fields = array();

$db = DBManagerFactory::getInstance();
$query = "DESCRIBE opportunities_cstm";
$result = $db->query($query);
$existing_fields = array();

while ($row = $db->fetchByAssoc($result)) {
    $existing_fields[] = $row['Field'];
}

foreach ($custom_fields as $field) {
    if (!in_array($field, $existing_fields)) {
        $all_fields_exist = false;
        $missing_fields[] = $field;
    }
}

if ($all_fields_exist) {
    echo "PASSED\n";
    $tests_passed++;
} else {
    echo "FAILED - Missing fields: " . implode(', ', $missing_fields) . "\n";
    $tests_failed++;
}

// Test 4: Check if list view is accessible
echo "Test 4: Checking list view... ";
if (file_exists('modules/Deals/views/view.list.php')) {
    require_once('modules/Deals/views/view.list.php');
    if (class_exists('DealsViewList')) {
        echo "PASSED\n";
        $tests_passed++;
    } else {
        echo "FAILED - Class not found\n";
        $tests_failed++;
    }
} else {
    echo "FAILED - File not found\n";
    $tests_failed++;
}

// Test 5: Check if pipeline view exists
echo "Test 5: Checking pipeline view... ";
if (file_exists('custom/modules/Deals/views/view.pipeline.php')) {
    require_once('custom/modules/Deals/views/view.pipeline.php');
    if (class_exists('DealsViewPipeline')) {
        echo "PASSED\n";
        $tests_passed++;
    } else {
        echo "FAILED - Class not found\n";
        $tests_failed++;
    }
} else {
    echo "FAILED - File not found\n";
    $tests_failed++;
}

// Test 6: Check if controller exists and has required actions
echo "Test 6: Checking controller... ";
if (file_exists('custom/modules/Deals/controller.php')) {
    require_once('custom/modules/Deals/controller.php');
    if (class_exists('DealsController')) {
        $controller = new DealsController();
        $required_methods = array('action_updatePipelineStage', 'action_toggleFocus', 'action_Pipeline');
        $all_methods_exist = true;
        $missing_methods = array();
        
        foreach ($required_methods as $method) {
            if (!method_exists($controller, $method)) {
                $all_methods_exist = false;
                $missing_methods[] = $method;
            }
        }
        
        if ($all_methods_exist) {
            echo "PASSED\n";
            $tests_passed++;
        } else {
            echo "FAILED - Missing methods: " . implode(', ', $missing_methods) . "\n";
            $tests_failed++;
        }
    } else {
        echo "FAILED - Class not found\n";
        $tests_failed++;
    }
} else {
    echo "FAILED - File not found\n";
    $tests_failed++;
}

// Test 7: Check if JavaScript files exist
echo "Test 7: Checking JavaScript files... ";
$js_files = array(
    'custom/modules/Deals/js/pipeline.js',
    'modules/Deals/javascript/DealsListView.js'
);
$all_js_exist = true;
$missing_js = array();

foreach ($js_files as $js_file) {
    if (!file_exists($js_file)) {
        $all_js_exist = false;
        $missing_js[] = $js_file;
    }
}

if ($all_js_exist) {
    echo "PASSED\n";
    $tests_passed++;
} else {
    echo "FAILED - Missing files: " . implode(', ', $missing_js) . "\n";
    $tests_failed++;
}

// Test 8: Check if CSS file exists
echo "Test 8: Checking CSS file... ";
if (file_exists('custom/modules/Deals/tpls/deals.css')) {
    echo "PASSED\n";
    $tests_passed++;
} else {
    echo "FAILED\n";
    $tests_failed++;
}

// Test 9: Check if template file exists
echo "Test 9: Checking template file... ";
if (file_exists('custom/modules/Deals/tpls/pipeline.tpl')) {
    echo "PASSED\n";
    $tests_passed++;
} else {
    echo "FAILED\n";
    $tests_failed++;
}

// Test 10: Test basic CRUD operations
echo "Test 10: Testing CRUD operations... ";
try {
    // Create
    $test_deal = BeanFactory::newBean('Opportunities');
    $test_deal->name = 'Regression Test Deal ' . time();
    $test_deal->amount = 10000;
    $test_deal->sales_stage = 'Prospecting';
    $test_deal->pipeline_stage_c = 'sourcing';
    $test_deal->save();
    
    if (!empty($test_deal->id)) {
        // Read
        $read_deal = BeanFactory::getBean('Opportunities', $test_deal->id);
        if ($read_deal->name == $test_deal->name) {
            // Update
            $read_deal->amount = 20000;
            $read_deal->save();
            
            // Delete
            $read_deal->mark_deleted($read_deal->id);
            
            echo "PASSED\n";
            $tests_passed++;
        } else {
            echo "FAILED - Read operation failed\n";
            $tests_failed++;
        }
    } else {
        echo "FAILED - Create operation failed\n";
        $tests_failed++;
    }
} catch (Exception $e) {
    echo "FAILED - " . $e->getMessage() . "\n";
    $tests_failed++;
}

// Summary
echo "\n========================================\n";
echo "REGRESSION TEST SUMMARY\n";
echo "========================================\n";
echo "Tests Passed: $tests_passed\n";
echo "Tests Failed: $tests_failed\n";
echo "Total Tests: " . ($tests_passed + $tests_failed) . "\n";
echo "Success Rate: " . round(($tests_passed / ($tests_passed + $tests_failed)) * 100, 2) . "%\n";

if ($tests_failed == 0) {
    echo "\nAll tests passed! The Deals module is working correctly.\n";
} else {
    echo "\nSome tests failed. Please check the errors above.\n";
}
?>