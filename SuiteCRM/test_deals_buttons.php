<?php
/**
 * Test page for Deals module buttons
 */

if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');

global $current_user, $app_strings, $mod_strings;

// Set current user to admin
$current_user = new User();
$current_user->getSystemUser();

// Load language files
$mod_strings = return_module_language($GLOBALS['current_language'], 'Deals');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Deals Buttons</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .test-section { margin: 20px 0; padding: 20px; border: 1px solid #ccc; background: #f5f5f5; }
        .button { padding: 10px 20px; margin: 10px; background: #007bff; color: white; text-decoration: none; display: inline-block; }
        .button:hover { background: #0056b3; }
        .result { margin: 10px 0; padding: 10px; background: white; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>Test Deals Module Buttons</h1>
    
    <div class="test-section">
        <h2>1. Test Direct Links</h2>
        <div class="result">
            <p>Click these links to test if they work:</p>
            <a href="index.php?module=Deals&action=EditView" class="button">Create New Deal (Direct)</a>
            <a href="index.php?module=Deals&action=EditView&pipeline_stage_c=sourcing" class="button">Create Deal in Sourcing</a>
            <a href="index.php?module=Deals&action=Pipeline" class="button">View Pipeline</a>
        </div>
    </div>
    
    <div class="test-section">
        <h2>2. ACL Permission Check</h2>
        <div class="result">
            <?php
            $actions = ['access', 'view', 'list', 'edit', 'delete', 'import', 'export'];
            foreach ($actions as $action) {
                $hasAccess = ACLController::checkAccess('Deals', $action, true);
                echo "<p>Deals $action permission: <span class='" . ($hasAccess ? 'success' : 'error') . "'>" . ($hasAccess ? 'ALLOWED' : 'DENIED') . "</span></p>";
            }
            ?>
        </div>
    </div>
    
    <div class="test-section">
        <h2>3. Module Registration Check</h2>
        <div class="result">
            <?php
            echo "<p>Deals in beanList: <span class='" . (isset($GLOBALS['beanList']['Deals']) ? 'success' : 'error') . "'>" . (isset($GLOBALS['beanList']['Deals']) ? 'YES' : 'NO') . "</span></p>";
            echo "<p>Deals in moduleList: <span class='" . (in_array('Deals', $GLOBALS['moduleList']) ? 'success' : 'error') . "'>" . (in_array('Deals', $GLOBALS['moduleList']) ? 'YES' : 'NO') . "</span></p>";
            
            // Check Deal bean
            try {
                $deal = BeanFactory::newBean('Deals');
                echo "<p>Deal bean creation: <span class='success'>SUCCESS</span></p>";
                echo "<p>Bean module_name: " . $deal->module_name . "</p>";
                echo "<p>Bean acl_category: " . $deal->acl_category . "</p>";
            } catch (Exception $e) {
                echo "<p>Deal bean creation: <span class='error'>FAILED - " . $e->getMessage() . "</span></p>";
            }
            ?>
        </div>
    </div>
    
    <div class="test-section">
        <h2>4. View Files Check</h2>
        <div class="result">
            <?php
            $views = ['edit', 'detail', 'list', 'pipeline'];
            foreach ($views as $view) {
                $customPath = "custom/modules/Deals/views/view.$view.php";
                $corePath = "modules/Deals/views/view.$view.php";
                
                if (file_exists($customPath)) {
                    echo "<p>$view view: <span class='success'>EXISTS (custom)</span></p>";
                } elseif (file_exists($corePath)) {
                    echo "<p>$view view: <span class='success'>EXISTS (core)</span></p>";
                } else {
                    echo "<p>$view view: <span class='error'>MISSING</span></p>";
                }
            }
            ?>
        </div>
    </div>
    
    <div class="test-section">
        <h2>5. Test JavaScript Callback</h2>
        <div class="result">
            <p>Simulating "Add Deal" button click for different stages:</p>
            <button onclick="testAddDeal('sourcing')">Add Deal - Sourcing</button>
            <button onclick="testAddDeal('screening')">Add Deal - Screening</button>
            <button onclick="testAddDeal('term_sheet')">Add Deal - Term Sheet</button>
            
            <script>
            function testAddDeal(stage) {
                const stageMapping = {
                    'sourcing': 'Prospecting',
                    'screening': 'Qualification',
                    'analysis_outreach': 'Needs Analysis',
                    'term_sheet': 'Proposal/Price Quote',
                    'due_diligence': 'Value Proposition',
                    'final_negotiation': 'Negotiation/Review',
                    'closing': 'Negotiation/Review',
                    'closed_won': 'Closed Won',
                    'closed_lost': 'Closed Lost'
                };
                const salesStage = stageMapping[stage] || 'Prospecting';
                const url = 'index.php?module=Deals&action=EditView&return_module=Deals&return_action=Pipeline&sales_stage=' + encodeURIComponent(salesStage) + '&pipeline_stage_c=' + stage;
                console.log('Navigating to:', url);
                window.location.href = url;
            }
            </script>
        </div>
    </div>
    
    <div class="test-section">
        <h2>6. Recommendations</h2>
        <div class="result">
            <p>If the buttons still don't work:</p>
            <ol>
                <li>Clear browser cache (Ctrl+Shift+Delete)</li>
                <li>Check browser console for JavaScript errors (F12)</li>
                <li>Run the repair script: <a href="repair_deals_module.php" class="button">Run Repair</a></li>
                <li>Logout and login again</li>
            </ol>
        </div>
    </div>
</body>
</html>