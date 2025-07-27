<?php
/**
 * Test Deal Save Process
 */

if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');

global $current_user;

// Set current user to admin
$current_user = new User();
$current_user->getSystemUser();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Deal Save</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .test-section { margin: 20px 0; padding: 20px; border: 1px solid #ccc; background: #f5f5f5; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Test Deal Save Process</h1>
    
    <?php
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        echo "<div class='test-section'>";
        echo "<h2>Processing Deal Save...</h2>";
        
        try {
            // Create a new deal
            $deal = BeanFactory::newBean('Deals');
            
            // Set basic fields
            $deal->name = $_POST['name'] ?? 'Test Deal';
            $deal->amount = $_POST['amount'] ?? 100000;
            $deal->sales_stage = $_POST['sales_stage'] ?? 'Prospecting';
            $deal->date_closed = $_POST['date_closed'] ?? date('Y-m-d');
            $deal->assigned_user_id = $current_user->id;
            
            // Set custom fields
            $deal->pipeline_stage_c = $_POST['pipeline_stage_c'] ?? 'sourcing';
            
            // Save the deal
            $deal_id = $deal->save();
            
            if ($deal_id) {
                echo "<p class='success'>✓ Deal saved successfully!</p>";
                echo "<p>Deal ID: $deal_id</p>";
                echo "<p>Name: " . $deal->name . "</p>";
                echo "<p>Amount: $" . number_format($deal->amount, 2) . "</p>";
                echo "<p>Pipeline Stage: " . $deal->pipeline_stage_c . "</p>";
                echo "<p><a href='index.php?module=Deals&action=DetailView&record=$deal_id'>View Deal</a></p>";
            } else {
                echo "<p class='error'>✗ Failed to save deal</p>";
            }
            
        } catch (Exception $e) {
            echo "<p class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        }
        
        echo "</div>";
    }
    ?>
    
    <div class="test-section">
        <h2>Create Test Deal</h2>
        <form method="POST">
            <table>
                <tr>
                    <td>Deal Name:</td>
                    <td><input type="text" name="name" value="Test Deal <?php echo time(); ?>" size="50"></td>
                </tr>
                <tr>
                    <td>Amount:</td>
                    <td><input type="text" name="amount" value="100000"></td>
                </tr>
                <tr>
                    <td>Sales Stage:</td>
                    <td>
                        <select name="sales_stage">
                            <option value="Prospecting">Prospecting</option>
                            <option value="Qualification">Qualification</option>
                            <option value="Needs Analysis">Needs Analysis</option>
                            <option value="Value Proposition">Value Proposition</option>
                            <option value="Id. Decision Makers">Id. Decision Makers</option>
                            <option value="Perception Analysis">Perception Analysis</option>
                            <option value="Proposal/Price Quote">Proposal/Price Quote</option>
                            <option value="Negotiation/Review">Negotiation/Review</option>
                            <option value="Closed Won">Closed Won</option>
                            <option value="Closed Lost">Closed Lost</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td>Pipeline Stage:</td>
                    <td>
                        <select name="pipeline_stage_c">
                            <option value="sourcing">Sourcing</option>
                            <option value="screening">Screening</option>
                            <option value="analysis_outreach">Analysis & Outreach</option>
                            <option value="term_sheet">Term Sheet</option>
                            <option value="due_diligence">Due Diligence</option>
                            <option value="final_negotiation">Final Negotiation</option>
                            <option value="closing">Closing</option>
                            <option value="closed_won">Closed Won</option>
                            <option value="closed_lost">Closed Lost</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td>Close Date:</td>
                    <td><input type="date" name="date_closed" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>"></td>
                </tr>
                <tr>
                    <td colspan="2" style="padding-top: 10px;">
                        <input type="submit" value="Save Deal" style="padding: 5px 20px;">
                    </td>
                </tr>
            </table>
        </form>
    </div>
    
    <div class="test-section">
        <h2>System Status</h2>
        <?php
        // Check Deal bean
        echo "<p>Deal Bean Status: ";
        try {
            $test_bean = BeanFactory::newBean('Deals');
            echo "<span class='success'>✓ Working</span></p>";
            echo "<p>- Module Name: " . $test_bean->module_name . "</p>";
            echo "<p>- Table Name: " . $test_bean->table_name . "</p>";
            echo "<p>- Custom Fields: " . ($test_bean->custom_fields ? 'Enabled' : 'Disabled') . "</p>";
        } catch (Exception $e) {
            echo "<span class='error'>✗ Error</span></p>";
        }
        
        // Check database tables
        echo "<p>Database Tables:</p>";
        $tableCheck = $GLOBALS['db']->query("SHOW TABLES LIKE 'opportunities'");
        if ($GLOBALS['db']->fetchByAssoc($tableCheck)) {
            echo "<p>- opportunities: <span class='success'>✓ Exists</span></p>";
        }
        
        $tableCheck = $GLOBALS['db']->query("SHOW TABLES LIKE 'opportunities_cstm'");
        if ($GLOBALS['db']->fetchByAssoc($tableCheck)) {
            echo "<p>- opportunities_cstm: <span class='success'>✓ Exists</span></p>";
        }
        ?>
    </div>
    
    <div class="test-section">
        <h2>Other Actions</h2>
        <p>
            <a href="index.php?module=Deals&action=EditView">Regular Deal Create Form</a> |
            <a href="index.php?module=Deals&action=Pipeline">View Pipeline</a> |
            <a href="repair_deals_module.php">Run Repair Script</a>
        </p>
    </div>
</body>
</html>