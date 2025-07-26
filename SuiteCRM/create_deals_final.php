<?php
/**
 * Create test deals with all required fields
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

echo "=== CREATING DEALS ===\n\n";

// Test deals data
$test_deals = [
    [
        'name' => 'Acme Manufacturing Co',
        'deal_value' => 5000000,
        'status' => 'active',
        'source' => 'broker',
        'pipeline_stage_c' => 'sourcing',
        'ttm_revenue_c' => 10000000,
        'ttm_ebitda_c' => 2000000,
        'asking_price_c' => 5000000
    ],
    [
        'name' => 'Tech Innovations Ltd',
        'deal_value' => 3500000,
        'status' => 'active',
        'source' => 'direct',
        'pipeline_stage_c' => 'screening',
        'ttm_revenue_c' => 7000000,
        'ttm_ebitda_c' => 1400000,
        'asking_price_c' => 3500000
    ],
    [
        'name' => 'Global Logistics Inc',
        'deal_value' => 8000000,
        'status' => 'active',
        'source' => 'referral',
        'pipeline_stage_c' => 'analysis_outreach',
        'ttm_revenue_c' => 15000000,
        'ttm_ebitda_c' => 3000000,
        'asking_price_c' => 8000000
    ],
    [
        'name' => 'Healthcare Solutions',
        'deal_value' => 4500000,
        'status' => 'active',
        'source' => 'broker',
        'pipeline_stage_c' => 'due_diligence',
        'ttm_revenue_c' => 9000000,
        'ttm_ebitda_c' => 1800000,
        'asking_price_c' => 4500000
    ],
    [
        'name' => 'Retail Chain Acquisition',
        'deal_value' => 12000000,
        'status' => 'active',
        'source' => 'direct',
        'pipeline_stage_c' => 'valuation_structuring',
        'ttm_revenue_c' => 25000000,
        'ttm_ebitda_c' => 5000000,
        'asking_price_c' => 12000000
    ]
];

$created = 0;
foreach ($test_deals as $deal_data) {
    // Generate UUID
    $id = create_guid();
    $now = date('Y-m-d H:i:s');
    
    // Build INSERT query with all fields properly quoted
    $insert_query = "INSERT INTO deals (
        id, 
        name, 
        date_entered, 
        date_modified, 
        modified_user_id, 
        created_by,
        deleted, 
        assigned_user_id, 
        deal_value,
        status,
        source,
        pipeline_stage_c,
        stage_entered_date_c,
        ttm_revenue_c,
        ttm_ebitda_c,
        asking_price_c,
        focus_c,
        at_risk_status,
        is_archived
    ) VALUES (
        '$id',
        '" . $db->quote($deal_data['name']) . "',
        '$now',
        '$now',
        '1',
        '1',
        0,
        '1',
        {$deal_data['deal_value']},
        '{$deal_data['status']}',
        '{$deal_data['source']}',
        '{$deal_data['pipeline_stage_c']}',
        '$now',
        {$deal_data['ttm_revenue_c']},
        {$deal_data['ttm_ebitda_c']},
        {$deal_data['asking_price_c']},
        0,
        'on_track',
        0
    )";
    
    $result = $db->query($insert_query);
    
    if ($result) {
        $created++;
        echo "✓ Created deal: {$deal_data['name']} (Stage: {$deal_data['pipeline_stage_c']})\n";
    } else {
        echo "✗ Failed to create deal: {$deal_data['name']}\n";
        echo "  Error: " . $db->last_error . "\n";
    }
}

// Verify creation
echo "\n=== VERIFICATION ===\n";
$query = "SELECT id, name, pipeline_stage_c, deal_value 
          FROM deals 
          WHERE deleted = 0
          ORDER BY date_entered DESC";
$result = $db->query($query);

$count = 0;
while ($row = $db->fetchByAssoc($result)) {
    $count++;
    echo sprintf("%d. %s → %s ($%s)\n", 
        $count, 
        $row['name'], 
        $row['pipeline_stage_c'],
        number_format($row['deal_value'], 0)
    );
}

echo "\nTotal active deals: $count\n";
echo "\n=== COMPLETE ===\n";
echo "\nRefresh your pipeline view to see the deals:\n";
echo "http://localhost:8080/index.php?module=Deals&action=pipeline\n";
echo "\nOr go to Deals list view:\n";
echo "http://localhost:8080/index.php?module=Deals&action=index\n";