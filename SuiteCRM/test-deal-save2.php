<?php
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');
require_once('modules/Users/User.php');

// Set current user as admin
global $current_user;
$current_user = BeanFactory::getBean('Users', '1');

echo "Testing Deal Save Process with Opportunities Table\n";
echo "=================================================\n\n";

try {
    // Create a new deal
    $deal = BeanFactory::newBean('Deals');
    
    echo "1. Creating new deal bean...\n";
    echo "   Table name: " . $deal->table_name . "\n";
    echo "   Module: " . $deal->module_name . "\n";
    
    // Set basic fields
    $deal->name = "Test Deal " . date('Y-m-d H:i:s');
    $deal->amount = 100000;
    $deal->date_closed = date('Y-m-d', strtotime('+30 days'));
    $deal->sales_stage = 'Prospecting';
    $deal->probability = 50;
    $deal->assigned_user_id = $current_user->id;
    
    // Set custom fields
    $deal->pipeline_stage_c = 'sourcing';
    $deal->deal_source_c = 'Direct Outreach';
    $deal->expected_close_date_c = date('Y-m-d', strtotime('+60 days'));
    
    echo "\n2. Fields before save:\n";
    echo "   pipeline_stage_c: " . ($deal->pipeline_stage_c ?? 'NOT SET') . "\n";
    echo "   expected_close_date_c: " . ($deal->expected_close_date_c ?? 'NOT SET') . "\n";
    
    echo "\n3. Saving deal...\n";
    $dealId = $deal->save();
    
    if ($dealId) {
        echo "✓ Deal saved successfully with ID: {$dealId}\n";
        
        // Check if records exist in both tables
        global $db;
        
        // Check opportunities table
        $query1 = "SELECT id, name, amount, sales_stage FROM opportunities WHERE id = '{$dealId}'";
        $result1 = $db->query($query1);
        $mainRow = $db->fetchByAssoc($result1);
        
        if ($mainRow) {
            echo "\n✓ Main table record found in opportunities:\n";
            foreach ($mainRow as $field => $value) {
                echo "   {$field}: {$value}\n";
            }
        }
        
        // Check opportunities_cstm table
        $query2 = "SELECT * FROM opportunities_cstm WHERE id_c = '{$dealId}'";
        $result2 = $db->query($query2);
        $customRow = $db->fetchByAssoc($result2);
        
        if ($customRow) {
            echo "\n✓ Custom table record found in opportunities_cstm:\n";
            foreach ($customRow as $field => $value) {
                if ($value !== null && $value !== '') {
                    echo "   {$field}: {$value}\n";
                }
            }
        } else {
            echo "\n✗ No custom table record found in opportunities_cstm!\n";
            
            // Try to manually insert the custom record
            echo "\n4. Manually inserting custom record...\n";
            $insertQuery = "INSERT INTO opportunities_cstm (id_c, pipeline_stage_c, expected_close_date_c) 
                           VALUES ('{$dealId}', 'sourcing', '" . date('Y-m-d', strtotime('+60 days')) . "')";
            $db->query($insertQuery);
            echo "✓ Custom record inserted\n";
        }
        
        // Now retrieve the deal to verify
        echo "\n5. Retrieving deal to verify all fields...\n";
        $savedDeal = BeanFactory::getBean('Deals', $dealId);
        
        echo "   Name: " . $savedDeal->name . "\n";
        echo "   Amount: $" . number_format($savedDeal->amount, 2) . "\n";
        echo "   Pipeline Stage: " . ($savedDeal->pipeline_stage_c ?? 'NOT SET') . "\n";
        echo "   Expected Close Date: " . ($savedDeal->expected_close_date_c ?? 'NOT SET') . "\n";
        
    } else {
        echo "✗ Failed to save deal\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\nDone.\n";