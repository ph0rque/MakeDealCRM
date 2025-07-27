#!/usr/bin/env php
<?php
/**
 * Comprehensive End-to-End Test Suite for Deals Module
 * 
 * This script tests all major functionality of the Deals module
 * including creation, viewing, editing, and checklist features.
 */

// Setup environment
define('sugarEntry', true);
chdir('/var/www/html');

// Bootstrap SuiteCRM
require_once('include/entryPoint.php');
require_once('modules/Deals/Deal.php');

// Set admin user
global $current_user, $db;
$current_user->retrieve('1');

// Color codes for output
$red = "\033[0;31m";
$green = "\033[0;32m";
$yellow = "\033[0;33m";
$reset = "\033[0m";

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function runTest($testName, $testFunction) {
    global $totalTests, $passedTests, $failedTests, $green, $red, $reset;
    
    $totalTests++;
    echo "\n  Testing: $testName... ";
    
    try {
        $result = $testFunction();
        if ($result === true) {
            echo "{$green}✓ PASSED{$reset}";
            $passedTests++;
        } else {
            echo "{$red}✗ FAILED{$reset}: $result";
            $failedTests++;
        }
    } catch (Exception $e) {
        echo "{$red}✗ ERROR{$reset}: " . $e->getMessage();
        $failedTests++;
    }
}

echo "\n{$yellow}=== Comprehensive Deals Module Test Suite ==={$reset}\n";

// Test 1: Deal Creation
echo "\n{$yellow}1. Deal Creation Tests{$reset}";
$testDealId = null;

runTest("Create new deal", function() use (&$testDealId) {
    $deal = new Deal();
    $deal->name = 'Test Deal ' . time();
    $deal->amount = 1000000;
    $deal->sales_stage = 'Prospecting';
    $deal->assigned_user_id = $GLOBALS['current_user']->id;
    $deal->pipeline_stage_c = 'sourcing';
    $deal->description = 'Automated test deal';
    $testDealId = $deal->save();
    
    return !empty($testDealId);
});

runTest("Verify deal was saved to database", function() use ($testDealId) {
    $deal = BeanFactory::getBean('Deals', $testDealId);
    return ($deal && !empty($deal->id));
});

// Test 2: Deal Retrieval
echo "\n\n{$yellow}2. Deal Retrieval Tests{$reset}";

runTest("Load deal by ID", function() use ($testDealId) {
    $deal = new Deal();
    $deal->retrieve($testDealId);
    return ($deal->id == $testDealId);
});

runTest("Verify deal fields", function() use ($testDealId) {
    $deal = BeanFactory::getBean('Deals', $testDealId);
    return (
        strpos($deal->name, 'Test Deal') !== false &&
        $deal->amount == 1000000 &&
        $deal->pipeline_stage_c == 'sourcing'
    );
});

// Test 3: Deal Update
echo "\n\n{$yellow}3. Deal Update Tests{$reset}";

runTest("Update deal description", function() use ($testDealId) {
    $deal = BeanFactory::getBean('Deals', $testDealId);
    $deal->description = 'Updated description';
    $deal->save();
    
    // Reload to verify
    $verifyDeal = BeanFactory::getBean('Deals', $testDealId);
    return ($verifyDeal->description == 'Updated description');
});

runTest("Update pipeline stage", function() use ($testDealId) {
    $deal = BeanFactory::getBean('Deals', $testDealId);
    $oldStage = $deal->pipeline_stage_c;
    $deal->pipeline_stage_c = 'screening';
    $deal->save();
    
    // Reload to verify
    $verifyDeal = BeanFactory::getBean('Deals', $testDealId);
    return ($verifyDeal->pipeline_stage_c == 'screening' && $oldStage != 'screening');
});

// Test 4: Logic Hooks
echo "\n\n{$yellow}4. Logic Hook Tests{$reset}";

runTest("Check if logic hooks file exists", function() {
    $hookFile = 'custom/modules/Deals/logic_hooks.php';
    return file_exists($hookFile);
});

runTest("Verify ChecklistLogicHook method exists", function() {
    $hookFile = 'custom/modules/Deals/ChecklistLogicHook.php';
    if (!file_exists($hookFile)) {
        return "ChecklistLogicHook file not found";
    }
    
    require_once($hookFile);
    $hook = new ChecklistLogicHook();
    return method_exists($hook, 'updateChecklistCompletion');
});

// Test 5: Checklist Functionality
echo "\n\n{$yellow}5. Checklist Tests{$reset}";

runTest("Create checklist template", function() {
    require_once('custom/modules/ChecklistTemplates/ChecklistTemplate.php');
    $template = new ChecklistTemplate();
    $template->name = 'Test Template ' . time();
    $template->stage_c = 'sourcing';
    $template->is_active_c = 1;
    $templateId = $template->save();
    
    return !empty($templateId);
});

runTest("Create checklist item", function() {
    require_once('custom/modules/ChecklistItems/ChecklistItem.php');
    $item = new ChecklistItem();
    $item->name = 'Test Checklist Item';
    $item->description_c = 'Test description';
    $item->order_c = 1;
    $itemId = $item->save();
    
    return !empty($itemId);
});

// Test 6: Deal List View
echo "\n\n{$yellow}6. Deal List View Tests{$reset}";

runTest("List view definition exists", function() {
    $listViewDef = 'custom/modules/Deals/metadata/listviewdefs.php';
    return file_exists($listViewDef);
});

runTest("Query deals from database", function() {
    $query = "SELECT COUNT(*) as count FROM opportunities WHERE deleted = 0";
    $result = $GLOBALS['db']->query($query);
    $row = $GLOBALS['db']->fetchByAssoc($result);
    
    return ($row['count'] > 0);
});

// Test 7: Deal Detail View
echo "\n\n{$yellow}7. Deal Detail View Tests{$reset}";

runTest("Detail view definition exists", function() {
    $paths = [
        'modules/Deals/metadata/detailviewdefs.php',
        'custom/modules/Deals/metadata/detailviewdefs.php'
    ];
    
    foreach ($paths as $path) {
        if (file_exists($path)) {
            return true;
        }
    }
    return false;
});

runTest("Check DetailView class", function() {
    $viewFile = 'modules/Deals/views/view.detail.php';
    return file_exists($viewFile);
});

// Test 8: Controller Tests
echo "\n\n{$yellow}8. Controller Tests{$reset}";

runTest("Controller file exists", function() {
    $controllerFile = 'custom/modules/Deals/controller.php';
    return file_exists($controllerFile);
});

runTest("Controller can be instantiated", function() {
    $controllerFile = 'custom/modules/Deals/controller.php';
    if (!file_exists($controllerFile)) {
        return "Controller file not found";
    }
    
    require_once('include/MVC/Controller/SugarController.php');
    require_once($controllerFile);
    
    $controller = new DealsController();
    return ($controller instanceof SugarController);
});

// Test 9: Pipeline Functionality
echo "\n\n{$yellow}9. Pipeline Tests{$reset}";

runTest("Pipeline stage hook file exists", function() {
    return file_exists('SuiteCRM/custom/modules/Deals/logic_hooks/PipelineStageHook.php');
});

runTest("Pipeline stages are defined", function() {
    // Check if pipeline stages dropdown is defined
    $dropdownFile = 'custom/Extension/application/Ext/Language/en_us.pipeline_stages.php';
    return file_exists($dropdownFile);
});

// Test 10: ACL/Security
echo "\n\n{$yellow}10. Security Tests{$reset}";

runTest("Deal module ACL check", function() {
    return ACLController::checkAccess('Deals', 'view', true);
});

runTest("Can create deals", function() {
    return ACLController::checkAccess('Deals', 'edit', true);
});

// Cleanup
echo "\n\n{$yellow}11. Cleanup{$reset}";

runTest("Delete test deal", function() use ($testDealId) {
    if (empty($testDealId)) {
        return "No test deal to delete";
    }
    
    $deal = BeanFactory::getBean('Deals', $testDealId);
    if ($deal) {
        $deal->mark_deleted($testDealId);
        return true;
    }
    return false;
});

// Summary
echo "\n\n{$yellow}=== Test Summary ==={$reset}";
echo "\n  Total Tests: $totalTests";
echo "\n  {$green}Passed: $passedTests{$reset}";
echo "\n  {$red}Failed: $failedTests{$reset}";

$successRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 2) : 0;
echo "\n  Success Rate: {$successRate}%";

if ($failedTests == 0) {
    echo "\n\n{$green}✓ ALL TESTS PASSED!{$reset}\n";
    exit(0);
} else {
    echo "\n\n{$red}✗ SOME TESTS FAILED!{$reset}\n";
    exit(1);
}