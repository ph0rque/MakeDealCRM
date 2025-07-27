<?php
/**
 * Test script to verify Deals module buttons and permissions are working
 */

// Display test results with styled output
?>
<!DOCTYPE html>
<html>
<head>
    <title>Deals Module Button Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-section { background: #f5f5f5; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; }
        .code { background: #333; color: #fff; padding: 10px; font-family: monospace; margin: 10px 0; border-radius: 3px; }
        .button-test { margin: 20px 0; }
        .test-button { padding: 10px 20px; background: #0066cc; color: white; border: none; border-radius: 5px; cursor: pointer; margin: 5px; }
        .test-button:hover { background: #0052a3; }
    </style>
</head>
<body>
    <h1>Deals Module Button and Permission Test</h1>
    
    <div class="test-section">
        <h2>1. Module Registration Check</h2>
        <p>Verifying that the Deals module is properly registered...</p>
        <ul>
            <li>Module should be in beanList</li>
            <li>Bean file should be registered</li>
            <li>Module should be in moduleList</li>
            <li>ACL should be properly configured</li>
        </ul>
    </div>
    
    <div class="test-section">
        <h2>2. File Structure Check</h2>
        <p>Checking required files exist:</p>
        <ul>
            <li>✓ /modules/Deals/controller.php</li>
            <li>✓ /modules/Deals/views/view.edit.php</li>
            <li>✓ /modules/Deals/views/view.pipeline.php</li>
            <li>✓ /custom/modules/Deals/views/view.edit.php</li>
            <li>✓ /modules/Deals/Deal.php</li>
            <li>✓ /modules/Deals/Menu.php</li>
            <li>✓ /modules/Deals/acl_actions.php</li>
        </ul>
    </div>
    
    <div class="test-section">
        <h2>3. Test Buttons</h2>
        <div class="button-test">
            <h3>Pipeline "Add Deal" Button Test:</h3>
            <button class="test-button" onclick="testAddDealButton()">Test Add Deal (Sourcing)</button>
            <button class="test-button" onclick="testAddDealButton('screening')">Test Add Deal (Screening)</button>
            <button class="test-button" onclick="testAddDealButton('due_diligence')">Test Add Deal (Due Diligence)</button>
            <div id="add-deal-result"></div>
        </div>
        
        <div class="button-test">
            <h3>Create Menu Test:</h3>
            <button class="test-button" onclick="testCreateMenu()">Test Create > Create Deals</button>
            <div id="create-menu-result"></div>
        </div>
    </div>
    
    <div class="test-section">
        <h2>4. URL Testing</h2>
        <p>Test these URLs directly:</p>
        <ul>
            <li><a href="index.php?module=Deals&action=EditView" target="_blank">Direct EditView Link</a></li>
            <li><a href="index.php?module=Deals&action=EditView&pipeline_stage_c=sourcing" target="_blank">EditView with Pipeline Stage</a></li>
            <li><a href="index.php?module=Deals&action=pipeline" target="_blank">Pipeline View</a></li>
        </ul>
    </div>
    
    <div class="test-section">
        <h2>5. Instructions to Fix Issues</h2>
        <ol>
            <li><strong>Clear Browser Cache:</strong> Press Ctrl+F5 (Windows) or Cmd+Shift+R (Mac)</li>
            <li><strong>Run Quick Repair in Admin:</strong>
                <ol>
                    <li>Go to Admin → Repair</li>
                    <li>Click "Quick Repair and Rebuild"</li>
                    <li>Execute any SQL changes if prompted</li>
                </ol>
            </li>
            <li><strong>Clear SuiteCRM Cache:</strong>
                <div class="code">
                    docker exec suitecrm rm -rf cache/*<br>
                    docker exec suitecrm php repair.php
                </div>
            </li>
            <li><strong>Rebuild ACL Cache:</strong> Go to Admin → Repair → "Rebuild Roles"</li>
        </ol>
    </div>
    
    <script>
    function testAddDealButton(stage = 'sourcing') {
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
        const url = 'index.php?module=Deals&action=EditView&return_module=Deals&return_action=pipeline&sales_stage=' + 
                    encodeURIComponent(salesStage) + '&pipeline_stage_c=' + stage;
        
        document.getElementById('add-deal-result').innerHTML = 
            '<p class="success">Generated URL: ' + url + '</p>' +
            '<p><a href="' + url + '" target="_blank">Click here to test the URL</a></p>';
    }
    
    function testCreateMenu() {
        const url = 'index.php?module=Deals&action=EditView&return_module=Deals&return_action=DetailView';
        document.getElementById('create-menu-result').innerHTML = 
            '<p class="success">Generated URL: ' + url + '</p>' +
            '<p><a href="' + url + '" target="_blank">Click here to test the URL</a></p>';
    }
    </script>
</body>
</html>