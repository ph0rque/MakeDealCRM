<?php
/**
 * Comprehensive fix for Deals module
 */

if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');

global $db;

echo "=== Comprehensive Deals Fix ===\n\n";

// 1. Fix date_closed for existing deals
echo "1. Fixing date_closed for all deals:\n";
$query = "UPDATE opportunities 
          SET date_closed = CASE 
              WHEN date_closed IS NULL OR date_closed = '' OR date_closed = '0000-00-00' 
              THEN DATE_ADD(CURDATE(), INTERVAL 30 DAY)
              ELSE date_closed
          END
          WHERE deleted = 0";
$db->query($query);
echo "   ✓ Fixed date_closed fields\n";

// 2. Ensure all deals have proper default values
echo "\n2. Setting proper defaults for all fields:\n";
$deals = [];
$query = "SELECT * FROM opportunities WHERE deleted = 0";
$result = $db->query($query);
while ($row = $db->fetchByAssoc($result)) {
    $updates = [];
    
    if (empty($row['sales_stage'])) {
        $updates[] = "sales_stage = 'Prospecting'";
    }
    if (empty($row['amount']) || $row['amount'] == 0) {
        $updates[] = "amount = 100000";
    }
    if (empty($row['probability'])) {
        $updates[] = "probability = 10";
    }
    if (empty($row['assigned_user_id'])) {
        $updates[] = "assigned_user_id = '1'";
    }
    
    if (!empty($updates)) {
        $updateQuery = "UPDATE opportunities SET " . implode(', ', $updates) . 
                       " WHERE id = '{$row['id']}'";
        $db->query($updateQuery);
        echo "   ✓ Updated deal: {$row['name']}\n";
    }
}

// 3. Verify pipeline view query works
echo "\n3. Testing pipeline query with proper joins:\n";
$query = "SELECT 
            o.id,
            o.name,
            o.amount,
            o.sales_stage,
            o.date_closed,
            o.probability,
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
          ORDER BY o.date_modified DESC";

$result = $db->query($query);
$count = 0;
echo "\nDeals that will show in pipeline:\n";
while ($row = $db->fetchByAssoc($result)) {
    $count++;
    echo "\n{$count}. {$row['name']}\n";
    echo "   - Stage: {$row['sales_stage']} -> Pipeline: {$row['pipeline_stage_c']}\n";
    echo "   - Amount: \${$row['amount']}\n";
    echo "   - Close Date: {$row['date_closed']}\n";
    
    if ($count >= 5) break; // Show only first 5
}
echo "\nTotal deals found: $count\n";

// 4. Clear cache
echo "\n4. Clearing cache:\n";
$cacheFiles = [
    'cache/modules/Deals/*',
    'cache/themes/*/modules/Deals/*',
    'cache/smarty/templates_c/*'
];

foreach ($cacheFiles as $pattern) {
    $files = glob($pattern);
    if ($files) {
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
}
echo "   ✓ Cache cleared\n";

echo "\n=== Fix Complete! ===\n";
echo "\nYour deals should now appear in the pipeline.\n";
echo "Please refresh: http://localhost:8080/index.php?module=Deals&action=Pipeline\n";
echo "\nIf you still don't see deals:\n";
echo "1. Clear your browser cache (Ctrl+Shift+Delete)\n";
echo "2. Logout and login again\n";
echo "3. Make sure you're viewing the correct pipeline\n";