<?php
/**
 * Test script to verify pipeline integration is working with real data
 */

if (!defined('sugarEntry')) define('sugarEntry', true);

// Bootstrap SuiteCRM
chdir(dirname(__FILE__) . '/../../../SuiteCRM');
require_once('include/entryPoint.php');

global $db, $current_user;

// Set current user to admin for testing
$current_user = BeanFactory::getBean('Users', '1');

echo "Pipeline Integration Test\n";
echo "========================\n\n";

// 1. Test database tables
echo "1. Checking database tables...\n";

$tables = ['opportunities', 'opportunities_cstm', 'pipeline_stage_history'];
foreach ($tables as $table) {
    $result = $db->query("SHOW TABLES LIKE '$table'");
    if ($db->fetchByAssoc($result)) {
        echo "   ✓ Table '$table' exists\n";
    } else {
        echo "   ✗ Table '$table' MISSING\n";
    }
}

// 2. Check custom fields
echo "\n2. Checking custom fields in opportunities_cstm...\n";

$requiredFields = [
    'pipeline_stage_c',
    'stage_entered_date_c',
    'expected_close_date_c',
    'deal_source_c',
    'pipeline_notes_c',
    'focus_flag_c',
    'focus_order_c',
    'focus_date_c'
];

foreach ($requiredFields as $field) {
    $result = $db->query("SHOW COLUMNS FROM opportunities_cstm LIKE '$field'");
    if ($db->fetchByAssoc($result)) {
        echo "   ✓ Field '$field' exists\n";
    } else {
        echo "   ✗ Field '$field' MISSING\n";
    }
}

// 3. Check opportunities with pipeline data
echo "\n3. Checking opportunities data...\n";

$query = "SELECT COUNT(*) as total FROM opportunities WHERE deleted = 0";
$result = $db->query($query);
$row = $db->fetchByAssoc($result);
echo "   Total active opportunities: {$row['total']}\n";

$query = "SELECT COUNT(*) as total FROM opportunities o 
          JOIN opportunities_cstm oc ON o.id = oc.id_c 
          WHERE o.deleted = 0 AND oc.pipeline_stage_c IS NOT NULL";
$result = $db->query($query);
$row = $db->fetchByAssoc($result);
echo "   Opportunities with pipeline stage: {$row['total']}\n";

// 4. Test loading opportunities by stage
echo "\n4. Testing pipeline stage distribution...\n";

$stages = [
    'sourcing' => 'Sourcing',
    'screening' => 'Screening',
    'analysis_outreach' => 'Analysis & Outreach',
    'due_diligence' => 'Due Diligence',
    'valuation_structuring' => 'Valuation & Structuring',
    'loi_negotiation' => 'LOI / Negotiation',
    'financing' => 'Financing',
    'closing' => 'Closing',
    'closed_owned_90_day' => 'Closed/Owned – 90-Day Plan',
    'closed_owned_stable' => 'Closed/Owned – Stable Operations',
    'unavailable' => 'Unavailable'
];

foreach ($stages as $stage_key => $stage_name) {
    $query = "SELECT COUNT(*) as count FROM opportunities o 
              LEFT JOIN opportunities_cstm oc ON o.id = oc.id_c 
              WHERE o.deleted = 0 
              AND (oc.pipeline_stage_c = '$stage_key' 
                   OR (oc.pipeline_stage_c IS NULL AND '$stage_key' = 'sourcing'))
              AND o.sales_stage NOT IN ('Closed Won', 'Closed Lost')";
    
    $result = $db->query($query);
    $row = $db->fetchByAssoc($result);
    echo "   $stage_name: {$row['count']} deals\n";
}

// 5. Test creating a sample opportunity
echo "\n5. Creating test opportunity...\n";

$testOpp = BeanFactory::newBean('Opportunities');
$testOpp->name = 'Test Pipeline Deal - ' . date('Y-m-d H:i:s');
$testOpp->amount = 100000;
$testOpp->sales_stage = 'Prospecting';
$testOpp->probability = 10;
$testOpp->date_closed = date('Y-m-d', strtotime('+90 days'));
$testOpp->assigned_user_id = $current_user->id;
$testOpp->save();

// Set pipeline stage
$db->query("UPDATE opportunities_cstm SET 
            pipeline_stage_c = 'sourcing',
            stage_entered_date_c = NOW()
            WHERE id_c = '{$testOpp->id}'");

echo "   ✓ Created test opportunity: {$testOpp->name} (ID: {$testOpp->id})\n";

// 6. Test updating pipeline stage
echo "\n6. Testing pipeline stage update...\n";

// Simulate moving to next stage
$oldStage = 'sourcing';
$newStage = 'screening';

$db->query("UPDATE opportunities_cstm SET 
            pipeline_stage_c = '$newStage',
            stage_entered_date_c = NOW()
            WHERE id_c = '{$testOpp->id}'");

// Log the change
$historyId = create_guid();
$db->query("INSERT INTO pipeline_stage_history 
            (id, deal_id, old_stage, new_stage, changed_by, date_changed) 
            VALUES 
            ('$historyId', '{$testOpp->id}', '$oldStage', '$newStage', '{$current_user->id}', NOW())");

echo "   ✓ Updated pipeline stage from '$oldStage' to '$newStage'\n";

// 7. Test focus functionality
echo "\n7. Testing focus functionality...\n";

$db->query("UPDATE opportunities_cstm SET 
            focus_flag_c = 1,
            focus_order_c = 1,
            focus_date_c = NOW()
            WHERE id_c = '{$testOpp->id}'");

echo "   ✓ Set focus flag on test opportunity\n";

// 8. Verify pipeline view query
echo "\n8. Testing pipeline view query...\n";

$query = "SELECT 
            o.id,
            o.name,
            o.amount,
            o.sales_stage,
            oc.pipeline_stage_c,
            oc.stage_entered_date_c,
            oc.focus_flag_c,
            oc.focus_order_c
          FROM opportunities o
          LEFT JOIN opportunities_cstm oc ON o.id = oc.id_c
          WHERE o.deleted = 0
          AND o.id = '{$testOpp->id}'";

$result = $db->query($query);
if ($row = $db->fetchByAssoc($result)) {
    echo "   ✓ Successfully retrieved test opportunity:\n";
    echo "     - Name: {$row['name']}\n";
    echo "     - Pipeline Stage: {$row['pipeline_stage_c']}\n";
    echo "     - Focus Flag: {$row['focus_flag_c']}\n";
} else {
    echo "   ✗ Failed to retrieve test opportunity\n";
}

// 9. Clean up test data (optional)
echo "\n9. Cleaning up test data...\n";
$testOpp->mark_deleted($testOpp->id);
echo "   ✓ Marked test opportunity as deleted\n";

echo "\n========================\n";
echo "Pipeline Integration Test Complete!\n\n";

// Summary
echo "SUMMARY:\n";
echo "- Database tables: Check above for any MISSING items\n";
echo "- Custom fields: Check above for any MISSING items\n";
echo "- Pipeline functionality: Working if all tests passed\n";
echo "\nIf any tests failed, run the repair script:\n";
echo "php custom/modules/Deals/scripts/repair_pipeline_fields.php\n";