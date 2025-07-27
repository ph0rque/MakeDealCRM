<?php
/**
 * Debug why deals aren't showing in pipeline
 */

if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');

global $db;

echo "=== Debugging Pipeline Deals ===\n\n";

// 1. Check all deals in opportunities table
echo "1. All deals in opportunities table:\n";
$query = "SELECT id, name, sales_stage, amount, deleted, date_entered 
          FROM opportunities 
          WHERE deleted = 0 
          ORDER BY date_entered DESC 
          LIMIT 10";
$result = $db->query($query);
$count = 0;
while ($row = $db->fetchByAssoc($result)) {
    $count++;
    echo "   - {$row['name']} (ID: {$row['id']})\n";
    echo "     Sales Stage: {$row['sales_stage']}, Amount: {$row['amount']}\n";
    echo "     Created: {$row['date_entered']}\n";
}
echo "   Total active deals: $count\n\n";

// 2. Check custom fields
echo "2. Checking opportunities_cstm table:\n";
$query = "SELECT oc.*, o.name 
          FROM opportunities_cstm oc
          LEFT JOIN opportunities o ON o.id = oc.id_c
          WHERE o.deleted = 0
          LIMIT 10";
$result = $db->query($query);
$count = 0;
while ($row = $db->fetchByAssoc($result)) {
    $count++;
    echo "   - Deal: {$row['name']} (ID: {$row['id_c']})\n";
    echo "     Pipeline Stage: " . ($row['pipeline_stage_c'] ?? 'NULL') . "\n";
    echo "     Stage Entered Date: " . ($row['stage_entered_date_c'] ?? 'NULL') . "\n";
}
if ($count == 0) {
    echo "   ! No records found in opportunities_cstm for active deals\n";
}
echo "\n";

// 3. Check for orphaned custom records
echo "3. Checking for missing custom records:\n";
$query = "SELECT o.id, o.name 
          FROM opportunities o
          LEFT JOIN opportunities_cstm oc ON o.id = oc.id_c
          WHERE o.deleted = 0 AND oc.id_c IS NULL
          LIMIT 10";
$result = $db->query($query);
$orphaned = [];
while ($row = $db->fetchByAssoc($result)) {
    $orphaned[] = $row;
    echo "   ! Deal missing custom record: {$row['name']} (ID: {$row['id']})\n";
}

// 4. Fix missing custom records
if (!empty($orphaned)) {
    echo "\n4. Creating missing custom records:\n";
    foreach ($orphaned as $deal) {
        $insert = "INSERT INTO opportunities_cstm (id_c, pipeline_stage_c, stage_entered_date_c) 
                   VALUES ('{$deal['id']}', 'sourcing', NOW())";
        if ($db->query($insert)) {
            echo "   ✓ Created custom record for: {$deal['name']}\n";
        } else {
            echo "   ✗ Failed to create custom record for: {$deal['name']}\n";
        }
    }
}

// 5. Test the pipeline query
echo "\n5. Testing pipeline query:\n";
$query = "SELECT 
            o.*,
            oc.pipeline_stage_c,
            oc.stage_entered_date_c,
            a.name as account_name,
            u.user_name as assigned_user_name,
            CONCAT(u.first_name, ' ', u.last_name) as assigned_user_full_name
          FROM opportunities o
          LEFT JOIN opportunities_cstm oc ON o.id = oc.id_c
          LEFT JOIN accounts a ON o.account_id = a.id AND a.deleted = 0
          LEFT JOIN users u ON o.assigned_user_id = u.id AND u.deleted = 0
          WHERE o.deleted = 0
          ORDER BY o.date_modified DESC
          LIMIT 10";

$result = $db->query($query);
$count = 0;
while ($row = $db->fetchByAssoc($result)) {
    $count++;
    echo "   - {$row['name']}\n";
    echo "     Pipeline Stage: " . ($row['pipeline_stage_c'] ?? 'NULL') . "\n";
    echo "     Sales Stage: {$row['sales_stage']}\n";
}
echo "   Total deals found by pipeline query: $count\n";

echo "\n=== Recommendations ===\n";
echo "1. If deals have NULL pipeline_stage_c, they won't show in the pipeline\n";
echo "2. Run this script to create missing custom records\n";
echo "3. Clear cache and refresh the pipeline view\n";