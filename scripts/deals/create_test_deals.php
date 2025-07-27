<?php
/**
 * Create test deals for the pipeline
 */

// Set up SuiteCRM environment
if (!defined('sugarEntry')) define('sugarEntry', true);
// Check if we need to change directory
if (file_exists('SuiteCRM/include/entryPoint.php')) {
    chdir('SuiteCRM');
}
require_once('include/entryPoint.php');
require_once('modules/Deals/Deal.php');

global $db, $current_user;

echo "=== CREATING TEST DEALS ===\n\n";

// Test deals data
$test_deals = [
    [
        'name' => 'Acme Manufacturing Co',
        'amount' => 5000000,
        'sales_stage' => 'Prospecting',
        'pipeline_stage' => 'sourcing',
        'account_name' => 'Acme Corp',
        'probability' => 10
    ],
    [
        'name' => 'Tech Innovations Ltd',
        'amount' => 3500000,
        'sales_stage' => 'Qualification',
        'pipeline_stage' => 'screening',
        'account_name' => 'Tech Innovations',
        'probability' => 20
    ],
    [
        'name' => 'Global Logistics Inc',
        'amount' => 8000000,
        'sales_stage' => 'Needs Analysis',
        'pipeline_stage' => 'analysis_outreach',
        'account_name' => 'Global Logistics',
        'probability' => 30
    ],
    [
        'name' => 'Healthcare Solutions',
        'amount' => 4500000,
        'sales_stage' => 'Value Proposition',
        'pipeline_stage' => 'due_diligence',
        'account_name' => 'Healthcare Corp',
        'probability' => 40
    ],
    [
        'name' => 'Retail Chain Acquisition',
        'amount' => 12000000,
        'sales_stage' => 'Id. Decision Makers',
        'pipeline_stage' => 'valuation_structuring',
        'account_name' => 'Retail Chain LLC',
        'probability' => 50
    ]
];

foreach ($test_deals as $deal_data) {
    // Create new Deal bean
    $deal = new Deal();
    
    // Set basic fields
    $deal->name = $deal_data['name'];
    $deal->amount = $deal_data['amount'];
    $deal->sales_stage = $deal_data['sales_stage'];
    $deal->probability = $deal_data['probability'];
    $deal->assigned_user_id = $current_user->id ?: '1';
    $deal->date_entered = date('Y-m-d H:i:s');
    $deal->date_modified = date('Y-m-d H:i:s');
    
    // Save the deal (this inserts into opportunities table)
    $deal->save();
    
    // Now add custom fields to opportunities_cstm
    if ($deal->id) {
        // Check if custom record exists
        $check_query = "SELECT id_c FROM opportunities_cstm WHERE id_c = '{$deal->id}'";
        $check_result = $db->query($check_query);
        
        if ($db->fetchByAssoc($check_result)) {
            // Update existing
            $update_query = "UPDATE opportunities_cstm 
                           SET pipeline_stage_c = '{$deal_data['pipeline_stage']}',
                               stage_entered_date_c = NOW()
                           WHERE id_c = '{$deal->id}'";
            $db->query($update_query);
        } else {
            // Insert new
            $insert_query = "INSERT INTO opportunities_cstm (id_c, pipeline_stage_c, stage_entered_date_c)
                           VALUES ('{$deal->id}', '{$deal_data['pipeline_stage']}', NOW())";
            $db->query($insert_query);
        }
        
        echo "✓ Created deal: {$deal_data['name']} (Stage: {$deal_data['pipeline_stage']})\n";
    } else {
        echo "✗ Failed to create deal: {$deal_data['name']}\n";
    }
}

echo "\n=== TEST DEALS CREATED ===\n";
echo "\nNow refresh your pipeline view to see the deals!\n";
echo "URL: http://localhost:8080/index.php?module=Deals&action=pipeline\n";