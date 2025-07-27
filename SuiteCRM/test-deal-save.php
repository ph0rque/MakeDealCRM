<?php
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');
require_once('modules/Users/User.php');

// Set current user as admin
global $current_user;
$current_user = BeanFactory::getBean('Users', '1');

echo "Testing Deal Save Process\n";
echo "========================\n\n";

try {
    // Create a new deal
    $deal = BeanFactory::newBean('Deals');
    
    echo "1. Creating new deal bean...\n";
    
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
    
    echo "2. Saving deal...\n";
    $dealId = $deal->save();
    
    if ($dealId) {
        echo "✓ Deal saved successfully with ID: {$dealId}\n";
        
        // Verify custom fields were saved
        echo "\n3. Retrieving deal to verify custom fields...\n";
        $savedDeal = BeanFactory::getBean('Deals', $dealId);
        
        echo "   Name: " . $savedDeal->name . "\n";
        echo "   Amount: $" . number_format($savedDeal->amount, 2) . "\n";
        echo "   Pipeline Stage: " . ($savedDeal->pipeline_stage_c ?? 'NOT SET') . "\n";
        echo "   Deal Source: " . ($savedDeal->deal_source_c ?? 'NOT SET') . "\n";
        
        // Check if custom table record exists
        global $db;
        $query = "SELECT * FROM deals_cstm WHERE id_c = '{$dealId}'";
        $result = $db->query($query);
        $customRow = $db->fetchByAssoc($result);
        
        if ($customRow) {
            echo "\n✓ Custom table record found:\n";
            foreach ($customRow as $field => $value) {
                if ($value !== null && $value !== '') {
                    echo "   {$field}: {$value}\n";
                }
            }
        } else {
            echo "\n✗ No custom table record found!\n";
        }
        
    } else {
        echo "✗ Failed to save deal\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\nDone.\n";