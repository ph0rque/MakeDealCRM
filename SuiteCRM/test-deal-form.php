<?php
// Test Deal Form Submission
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Deal Form Submission</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-section { margin: 20px 0; padding: 20px; background: #f5f5f5; border: 1px solid #ddd; }
        .success { color: green; }
        .error { color: red; }
        h2 { color: #333; }
    </style>
</head>
<body>
    <h1>Test Deal Form Submission</h1>
    
    <div class="test-section">
        <h2>Direct Save Test</h2>
        <form method="POST" action="index.php">
            <input type="hidden" name="module" value="Deals">
            <input type="hidden" name="action" value="Save">
            <input type="hidden" name="return_module" value="Deals">
            <input type="hidden" name="return_action" value="Pipeline">
            
            <p><label>Deal Name: <input type="text" name="name" value="Test Deal <?php echo date('Y-m-d H:i:s'); ?>" size="50"></label></p>
            <p><label>Amount: <input type="text" name="amount" value="100000"></label></p>
            <p><label>Sales Stage: 
                <select name="sales_stage">
                    <option value="Prospecting">Prospecting</option>
                    <option value="Qualification">Qualification</option>
                    <option value="Needs Analysis">Needs Analysis</option>
                </select>
            </label></p>
            <p><label>Pipeline Stage: 
                <select name="pipeline_stage_c">
                    <option value="sourcing">Sourcing</option>
                    <option value="screening">Screening</option>
                    <option value="analysis_outreach">Analysis & Outreach</option>
                </select>
            </label></p>
            <p><label>Close Date: <input type="text" name="date_closed" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>"></label></p>
            
            <p><button type="submit">Save Deal</button></p>
        </form>
    </div>
    
    <div class="test-section">
        <h2>Links to Test</h2>
        <ul>
            <li><a href="index.php?module=Deals&action=EditView">Create New Deal (EditView)</a></li>
            <li><a href="index.php?module=Deals&action=Pipeline">View Pipeline</a></li>
            <li><a href="index.php?module=Deals&action=index">Deals Index</a></li>
        </ul>
    </div>
    
    <div class="test-section">
        <h2>Current Configuration</h2>
        <?php
        global $current_user;
        echo "<p>Current User: " . ($current_user->name ?? 'Not logged in') . "</p>";
        
        // Check if Deal bean can be loaded
        $testBean = BeanFactory::newBean('Deals');
        echo "<p>Deal Bean Table: " . $testBean->table_name . "</p>";
        echo "<p>Deal Bean Module: " . $testBean->module_name . "</p>";
        echo "<p>Custom Fields Enabled: " . ($testBean->custom_fields ? 'Yes' : 'No') . "</p>";
        
        // Check if tables exist
        global $db;
        $tables = ['opportunities', 'opportunities_cstm', 'deals', 'deals_cstm'];
        foreach ($tables as $table) {
            $result = $db->query("SHOW TABLES LIKE '{$table}'");
            $exists = $db->fetchByAssoc($result) ? true : false;
            echo "<p>Table '{$table}': " . ($exists ? '<span class="success">EXISTS</span>' : '<span class="error">NOT FOUND</span>') . "</p>";
        }
        ?>
    </div>
    
</body>
</html>