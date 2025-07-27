<?php
/**
 * Verify and fix Deals module database configuration
 */

if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');

global $db;

echo "=== Verifying Deals Database Configuration ===\n\n";

// 1. Check opportunities table
echo "1. Checking opportunities table:\n";
$tableCheck = $db->query("SHOW TABLES LIKE 'opportunities'");
if ($db->fetchByAssoc($tableCheck)) {
    echo "   ✓ opportunities table exists\n";
    
    // Check columns
    $columnsQuery = "SHOW COLUMNS FROM opportunities";
    $result = $db->query($columnsQuery);
    $columns = [];
    while ($row = $db->fetchByAssoc($result)) {
        $columns[] = $row['Field'];
    }
    echo "   - Found " . count($columns) . " columns\n";
} else {
    echo "   ✗ opportunities table NOT FOUND\n";
}

// 2. Check opportunities_cstm table
echo "\n2. Checking opportunities_cstm table:\n";
$tableCheck = $db->query("SHOW TABLES LIKE 'opportunities_cstm'");
if ($db->fetchByAssoc($tableCheck)) {
    echo "   ✓ opportunities_cstm table exists\n";
    
    // Check columns
    $columnsQuery = "SHOW COLUMNS FROM opportunities_cstm";
    $result = $db->query($columnsQuery);
    echo "   - Custom fields:\n";
    while ($row = $db->fetchByAssoc($result)) {
        echo "     • " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} else {
    echo "   ✗ opportunities_cstm table NOT FOUND - Creating it...\n";
    
    $createQuery = "CREATE TABLE IF NOT EXISTS opportunities_cstm (
        id_c CHAR(36) NOT NULL PRIMARY KEY,
        pipeline_stage_c VARCHAR(100) DEFAULT NULL,
        stage_entered_date_c DATETIME DEFAULT NULL,
        health_score_c INT DEFAULT NULL,
        is_stale_c TINYINT(1) DEFAULT 0,
        days_in_stage_c INT DEFAULT NULL,
        at_risk_status_c VARCHAR(50) DEFAULT NULL,
        focus_flag_c TINYINT(1) DEFAULT 0,
        asking_price_c DECIMAL(26,6) DEFAULT NULL,
        ttm_revenue_c DECIMAL(26,6) DEFAULT NULL,
        ttm_ebitda_c DECIMAL(26,6) DEFAULT NULL,
        multiple_c DECIMAL(10,2) DEFAULT NULL,
        strategic_fit_score_c INT DEFAULT NULL,
        financial_health_score_c INT DEFAULT NULL,
        market_position_score_c INT DEFAULT NULL,
        KEY idx_opportunities_cstm_id_c (id_c)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
    
    if ($db->query($createQuery)) {
        echo "   ✓ Created opportunities_cstm table successfully\n";
    } else {
        echo "   ✗ Failed to create opportunities_cstm table\n";
    }
}

// 3. Check fields_meta_data
echo "\n3. Checking custom field definitions:\n";
$query = "SELECT name, type, len FROM fields_meta_data WHERE custom_module = 'Opportunities' AND deleted = 0";
$result = $db->query($query);
$count = 0;
while ($row = $db->fetchByAssoc($result)) {
    echo "   - " . $row['name'] . " (" . $row['type'] . ", " . $row['len'] . ")\n";
    $count++;
}
if ($count == 0) {
    echo "   ! No custom fields defined in fields_meta_data\n";
    echo "   ! You may need to create them through Studio\n";
}

// 4. Test Deal bean save
echo "\n4. Testing Deal bean save:\n";
try {
    $deal = BeanFactory::newBean('Deals');
    $deal->name = 'Test Deal ' . time();
    $deal->sales_stage = 'Prospecting';
    $deal->amount = 100000;
    $deal->date_closed = date('Y-m-d');
    $deal->pipeline_stage_c = 'sourcing';
    
    $deal_id = $deal->save();
    echo "   ✓ Deal saved successfully with ID: $deal_id\n";
    
    // Try to retrieve it
    $test_deal = BeanFactory::getBean('Deals', $deal_id);
    if ($test_deal && !empty($test_deal->id)) {
        echo "   ✓ Deal retrieved successfully\n";
        echo "   - Name: " . $test_deal->name . "\n";
        echo "   - Pipeline Stage: " . $test_deal->pipeline_stage_c . "\n";
        
        // Clean up
        $test_deal->mark_deleted($deal_id);
        echo "   ✓ Test deal cleaned up\n";
    } else {
        echo "   ✗ Failed to retrieve deal\n";
    }
} catch (Exception $e) {
    echo "   ✗ Error testing deal save: " . $e->getMessage() . "\n";
}

// 5. Check ACL
echo "\n5. Checking ACL permissions:\n";
$actions = ['access', 'view', 'list', 'edit', 'delete', 'import', 'export'];
foreach ($actions as $action) {
    $hasAccess = ACLController::checkAccess('Deals', $action, true);
    echo "   - $action: " . ($hasAccess ? '✓' : '✗') . "\n";
}

echo "\n=== Verification Complete ===\n";
echo "\nRecommendations:\n";
echo "1. If opportunities_cstm table was created, run Quick Repair and Rebuild\n";
echo "2. Clear browser cache and try creating a deal again\n";
echo "3. Check error logs if save still fails\n";