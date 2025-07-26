<?php
/**
 * Create test deals directly in database
 */

// Set up SuiteCRM environment
if (!defined('sugarEntry')) define('sugarEntry', true);
// Check if we need to change directory
if (file_exists('SuiteCRM/include/entryPoint.php')) {
    chdir('SuiteCRM');
}
require_once('include/entryPoint.php');
require_once('include/utils.php');

global $db;

echo "=== CREATING TEST DEALS DIRECTLY ===\n\n";

// Test deals data
$test_deals = [
    [
        'name' => 'Acme Manufacturing Co',
        'amount' => 5000000,
        'sales_stage' => 'Prospecting',
        'pipeline_stage' => 'sourcing'
    ],
    [
        'name' => 'Tech Innovations Ltd',
        'amount' => 3500000,
        'sales_stage' => 'Qualification',
        'pipeline_stage' => 'screening'
    ],
    [
        'name' => 'Global Logistics Inc',
        'amount' => 8000000,
        'sales_stage' => 'Needs Analysis',
        'pipeline_stage' => 'analysis_outreach'
    ],
    [
        'name' => 'Healthcare Solutions',
        'amount' => 4500000,
        'sales_stage' => 'Value Proposition',
        'pipeline_stage' => 'due_diligence'
    ],
    [
        'name' => 'Retail Chain Acquisition',
        'amount' => 12000000,
        'sales_stage' => 'Id. Decision Makers',
        'pipeline_stage' => 'valuation_structuring'
    ]
];

foreach ($test_deals as $deal_data) {
    // Generate UUID
    $id = create_guid();
    $now = date('Y-m-d H:i:s');
    
    // Insert into deals table
    $insert_deal = "INSERT INTO deals (
        id, name, date_entered, date_modified, modified_user_id, created_by,
        deleted, assigned_user_id, amount, sales_stage
    ) VALUES (
        '$id',
        '{$db->quote($deal_data['name'])}',
        '$now',
        '$now',
        '1',
        '1',
        0,
        '1',
        {$deal_data['amount']},
        '{$deal_data['sales_stage']}'
    )";
    
    $result = $db->query($insert_deal);
    
    if ($result) {
        // Insert custom fields
        $insert_custom = "INSERT INTO deals_cstm (id_c, pipeline_stage_c, stage_entered_date_c)
                         VALUES ('$id', '{$deal_data['pipeline_stage']}', '$now')";
        $db->query($insert_custom);
        
        echo "✓ Created deal: {$deal_data['name']} (Stage: {$deal_data['pipeline_stage']})\n";
    } else {
        echo "✗ Failed to create deal: {$deal_data['name']}\n";
    }
}

// Verify creation
echo "\n=== VERIFICATION ===\n";
$query = "SELECT d.name, c.pipeline_stage_c 
          FROM deals d 
          LEFT JOIN deals_cstm c ON d.id = c.id_c 
          WHERE d.deleted = 0";
$result = $db->query($query);

$count = 0;
while ($row = $db->fetchByAssoc($result)) {
    $count++;
    echo "- {$row['name']} → {$row['pipeline_stage_c']}\n";
}

echo "\nTotal deals created: $count\n";
echo "\n=== COMPLETE ===\n";
echo "Now refresh your pipeline view: http://localhost:8080/index.php?module=Deals&action=pipeline\n";