<?php
/**
 * Comprehensive Pipeline Diagnostic
 */

if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');

global $current_user, $db;

// Set current user to admin
$current_user = new User();
$current_user->getSystemUser();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Pipeline Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .section { margin: 20px 0; padding: 20px; border: 1px solid #ccc; background: #f5f5f5; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        pre { background: white; padding: 10px; overflow: auto; }
    </style>
</head>
<body>
    <h1>Pipeline Diagnostic Report</h1>
    
    <div class="section">
        <h2>1. Database Check</h2>
        <?php
        // Check deals in database
        $query = "SELECT COUNT(*) as total FROM opportunities WHERE deleted = 0";
        $result = $db->query($query);
        $row = $db->fetchByAssoc($result);
        echo "<p>Total active deals: <strong>{$row['total']}</strong></p>";
        
        // Check deals with valid data
        $query = "SELECT COUNT(*) as total FROM opportunities 
                  WHERE deleted = 0 
                  AND date_closed IS NOT NULL 
                  AND date_closed != '0000-00-00'
                  AND sales_stage IS NOT NULL 
                  AND sales_stage != ''";
        $result = $db->query($query);
        $row = $db->fetchByAssoc($result);
        echo "<p>Deals with valid data: <strong>{$row['total']}</strong></p>";
        
        // Show sample deals
        echo "<h3>Sample Deals:</h3>";
        $query = "SELECT o.id, o.name, o.sales_stage, o.amount, o.date_closed, oc.pipeline_stage_c
                  FROM opportunities o
                  LEFT JOIN opportunities_cstm oc ON o.id = oc.id_c
                  WHERE o.deleted = 0
                  LIMIT 3";
        $result = $db->query($query);
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Name</th><th>Sales Stage</th><th>Pipeline Stage</th><th>Amount</th><th>Close Date</th></tr>";
        while ($row = $db->fetchByAssoc($result)) {
            echo "<tr>";
            echo "<td>{$row['name']}</td>";
            echo "<td>{$row['sales_stage']}</td>";
            echo "<td>{$row['pipeline_stage_c']}</td>";
            echo "<td>{$row['amount']}</td>";
            echo "<td>{$row['date_closed']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        ?>
    </div>
    
    <div class="section">
        <h2>2. Module Configuration</h2>
        <?php
        // Check module registration
        echo "<p>Deals in beanList: ";
        if (isset($GLOBALS['beanList']['Deals'])) {
            echo "<span class='success'>✓ YES</span></p>";
        } else {
            echo "<span class='error'>✗ NO</span></p>";
        }
        
        // Check Deal bean
        echo "<p>Deal bean test: ";
        try {
            $deal = BeanFactory::newBean('Deals');
            echo "<span class='success'>✓ Works</span></p>";
            echo "<p>- Module dir: {$deal->module_dir}</p>";
            echo "<p>- Table name: {$deal->table_name}</p>";
            echo "<p>- Custom fields: " . ($deal->custom_fields ? 'Enabled' : 'Disabled') . "</p>";
        } catch (Exception $e) {
            echo "<span class='error'>✗ Error: " . $e->getMessage() . "</span></p>";
        }
        
        // Check ACL
        echo "<h3>ACL Permissions:</h3>";
        $actions = ['access', 'view', 'list', 'edit', 'delete'];
        foreach ($actions as $action) {
            $hasAccess = ACLController::checkAccess('Deals', $action, true);
            echo "<p>$action: " . ($hasAccess ? "<span class='success'>✓</span>" : "<span class='error'>✗</span>") . "</p>";
        }
        ?>
    </div>
    
    <div class="section">
        <h2>3. View Files Check</h2>
        <?php
        $views = [
            'pipeline' => 'modules/Deals/views/view.pipeline.php',
            'edit' => 'custom/modules/Deals/views/view.edit.php',
            'detail' => 'custom/modules/Deals/views/view.detail.php',
            'controller' => 'modules/Deals/controller.php'
        ];
        
        foreach ($views as $name => $path) {
            echo "<p>$name: ";
            if (file_exists($path)) {
                echo "<span class='success'>✓ Exists</span>";
                if ($name == 'pipeline') {
                    // Check if pipeline view has the correct query
                    $content = file_get_contents($path);
                    if (strpos($content, 'opportunities_cstm') !== false) {
                        echo " - <span class='success'>Has custom table join</span>";
                    } else {
                        echo " - <span class='warning'>Missing custom table join</span>";
                    }
                }
            } else {
                echo "<span class='error'>✗ Missing</span>";
            }
            echo "</p>";
        }
        ?>
    </div>
    
    <div class="section">
        <h2>4. JavaScript Check</h2>
        <?php
        $jsFile = 'custom/modules/Pipelines/views/PipelineKanbanView.js';
        echo "<p>PipelineKanbanView.js: ";
        if (file_exists($jsFile)) {
            echo "<span class='success'>✓ Exists</span></p>";
            $size = filesize($jsFile);
            echo "<p>- File size: " . number_format($size) . " bytes</p>";
        } else {
            echo "<span class='error'>✗ Missing</span></p>";
        }
        ?>
    </div>
    
    <div class="section">
        <h2>5. Test Deal Creation</h2>
        <form method="post">
            <input type="hidden" name="action" value="create_test_deal">
            <p>Click to create a test deal: <input type="submit" value="Create Test Deal"></p>
        </form>
        
        <?php
        if (isset($_POST['action']) && $_POST['action'] == 'create_test_deal') {
            echo "<h3>Creating test deal...</h3>";
            try {
                $deal = BeanFactory::newBean('Deals');
                $deal->name = 'Diagnostic Test Deal ' . time();
                $deal->sales_stage = 'Prospecting';
                $deal->amount = 50000;
                $deal->date_closed = date('Y-m-d', strtotime('+30 days'));
                $deal->assigned_user_id = $current_user->id;
                $deal->pipeline_stage_c = 'sourcing';
                
                $deal_id = $deal->save();
                
                if ($deal_id) {
                    echo "<p class='success'>✓ Deal created successfully!</p>";
                    echo "<p>Deal ID: $deal_id</p>";
                    echo "<p><a href='index.php?module=Deals&action=DetailView&record=$deal_id' target='_blank'>View Deal</a></p>";
                    
                    // Verify it's in the database
                    $check = "SELECT * FROM opportunities WHERE id = '$deal_id'";
                    $result = $db->query($check);
                    if ($row = $db->fetchByAssoc($result)) {
                        echo "<p class='success'>✓ Deal verified in database</p>";
                    } else {
                        echo "<p class='error'>✗ Deal not found in database</p>";
                    }
                } else {
                    echo "<p class='error'>✗ Failed to save deal</p>";
                }
            } catch (Exception $e) {
                echo "<p class='error'>✗ Error: " . $e->getMessage() . "</p>";
                echo "<pre>" . $e->getTraceAsString() . "</pre>";
            }
        }
        ?>
    </div>
    
    <div class="section">
        <h2>6. Quick Fixes</h2>
        <form method="post">
            <input type="hidden" name="action" value="quick_fix">
            <p><input type="submit" value="Run Quick Fixes"></p>
        </form>
        
        <?php
        if (isset($_POST['action']) && $_POST['action'] == 'quick_fix') {
            echo "<h3>Running fixes...</h3>";
            
            // Fix 1: Ensure all deals have pipeline_stage_c
            $query = "UPDATE opportunities_cstm oc
                      INNER JOIN opportunities o ON o.id = oc.id_c
                      SET oc.pipeline_stage_c = 'sourcing'
                      WHERE (oc.pipeline_stage_c IS NULL OR oc.pipeline_stage_c = '')
                      AND o.deleted = 0";
            $db->query($query);
            echo "<p>✓ Updated pipeline stages</p>";
            
            // Fix 2: Clear cache
            $dirs = ['cache/modules/Deals/', 'cache/themes/', 'cache/smarty/templates_c/'];
            foreach ($dirs as $dir) {
                if (is_dir($dir)) {
                    $files = glob($dir . '*');
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            @unlink($file);
                        }
                    }
                }
            }
            echo "<p>✓ Cleared cache</p>";
            
            echo "<p class='success'>Fixes applied! Please refresh the pipeline view.</p>";
        }
        ?>
    </div>
    
    <div class="section">
        <h2>7. Direct Links</h2>
        <p>
            <a href="index.php?module=Deals&action=Pipeline" target="_blank">Open Pipeline View</a> |
            <a href="index.php?module=Deals&action=EditView" target="_blank">Create New Deal</a> |
            <a href="index.php?module=Deals&action=index" target="_blank">Deals List View</a>
        </p>
    </div>
    
    <div class="section">
        <h2>Summary</h2>
        <?php
        $issues = [];
        
        // Check for common issues
        if ($row['total'] == 0) {
            $issues[] = "No deals in database";
        }
        
        if (!isset($GLOBALS['beanList']['Deals'])) {
            $issues[] = "Deals module not properly registered";
        }
        
        if (!file_exists('modules/Deals/views/view.pipeline.php')) {
            $issues[] = "Pipeline view file missing";
        }
        
        if (count($issues) == 0) {
            echo "<p class='success'>✓ No major issues detected</p>";
            echo "<p>If deals still don't show:</p>";
            echo "<ol>";
            echo "<li>Clear browser cache (Ctrl+Shift+Delete)</li>";
            echo "<li>Logout and login again</li>";
            echo "<li>Run the Quick Fixes above</li>";
            echo "<li>Check browser console for JavaScript errors</li>";
            echo "</ol>";
        } else {
            echo "<p class='error'>Issues found:</p>";
            echo "<ul>";
            foreach ($issues as $issue) {
                echo "<li class='error'>$issue</li>";
            }
            echo "</ul>";
        }
        ?>
    </div>
</body>
</html>