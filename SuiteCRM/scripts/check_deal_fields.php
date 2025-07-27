<?php
/**
 * Check why deal fields aren't being saved
 */

if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');

global $db;

echo "=== Checking Deal Field Values ===\n\n";

// 1. Show deal data
echo "1. Recent deals with their field values:\n";
$query = "SELECT id, name, sales_stage, amount, date_closed, assigned_user_id 
          FROM opportunities 
          WHERE deleted = 0 
          ORDER BY date_entered DESC 
          LIMIT 5";
$result = $db->query($query);
while ($row = $db->fetchByAssoc($result)) {
    echo "\nDeal: {$row['name']} (ID: {$row['id']})\n";
    echo "- sales_stage: " . ($row['sales_stage'] ?: 'EMPTY') . "\n";
    echo "- amount: " . ($row['amount'] ?: 'EMPTY') . "\n";
    echo "- date_closed: " . ($row['date_closed'] ?: 'EMPTY') . "\n";
    echo "- assigned_user_id: " . ($row['assigned_user_id'] ?: 'EMPTY') . "\n";
    
    // Check custom fields
    $customQuery = "SELECT * FROM opportunities_cstm WHERE id_c = '{$row['id']}'";
    $customResult = $db->query($customQuery);
    if ($customRow = $db->fetchByAssoc($customResult)) {
        echo "- pipeline_stage_c: " . ($customRow['pipeline_stage_c'] ?: 'EMPTY') . "\n";
    }
}

// 2. Update empty sales_stage fields
echo "\n2. Fixing empty sales_stage fields:\n";
$updateQuery = "UPDATE opportunities 
                SET sales_stage = 'Prospecting' 
                WHERE sales_stage IS NULL OR sales_stage = '' 
                AND deleted = 0";
$db->query($updateQuery);
$affected = $db->getAffectedRowCount();
echo "   Updated $affected deals with default sales_stage\n";

// 3. Update empty pipeline_stage_c fields
echo "\n3. Fixing empty pipeline_stage_c fields:\n";
$updateQuery = "UPDATE opportunities_cstm oc
                INNER JOIN opportunities o ON o.id = oc.id_c
                SET oc.pipeline_stage_c = 'sourcing'
                WHERE (oc.pipeline_stage_c IS NULL OR oc.pipeline_stage_c = '')
                AND o.deleted = 0";
$db->query($updateQuery);
$affected = $db->getAffectedRowCount();
echo "   Updated $affected deals with default pipeline_stage_c\n";

// 4. Test creating a deal properly
echo "\n4. Testing proper deal creation:\n";
$deal = BeanFactory::newBean('Deals');
$deal->name = 'Properly Created Deal ' . time();
$deal->sales_stage = 'Qualification';
$deal->amount = 250000;
$deal->date_closed = date('Y-m-d', strtotime('+30 days'));
$deal->assigned_user_id = 1;
$deal->pipeline_stage_c = 'screening';

// Save and immediately retrieve
$deal_id = $deal->save();
echo "   Created deal with ID: $deal_id\n";

// Verify it saved correctly
$query = "SELECT o.*, oc.pipeline_stage_c 
          FROM opportunities o
          LEFT JOIN opportunities_cstm oc ON o.id = oc.id_c
          WHERE o.id = '$deal_id'";
$result = $db->query($query);
if ($row = $db->fetchByAssoc($result)) {
    echo "   Verification:\n";
    echo "   - name: {$row['name']}\n";
    echo "   - sales_stage: {$row['sales_stage']}\n";
    echo "   - amount: {$row['amount']}\n";
    echo "   - pipeline_stage_c: {$row['pipeline_stage_c']}\n";
}

echo "\n=== Done! ===\n";
echo "Refresh the pipeline view to see the deals.\n";