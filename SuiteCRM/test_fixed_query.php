<?php
/**
 * Test the fixed pipeline query
 */

if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');

global $db;

echo "Testing fixed pipeline query...\n\n";

$query = "SELECT 
            d.id,
            d.name,
            d.amount,
            d.sales_stage,
            d.date_modified,
            d.assigned_user_id,
            d.probability,
            c.pipeline_stage_c,
            c.stage_entered_date_c,
            c.expected_close_date_c,
            c.focus_flag_c,
            c.focus_order_c,
            c.focus_date_c,
            u.first_name,
            u.last_name,
            a.name as account_name
        FROM opportunities d
        LEFT JOIN opportunities_cstm c ON d.id = c.id_c
        LEFT JOIN users u ON d.assigned_user_id = u.id AND u.deleted = 0
        LEFT JOIN accounts_opportunities ao ON d.id = ao.opportunity_id AND ao.deleted = 0
        LEFT JOIN accounts a ON ao.account_id = a.id AND a.deleted = 0
        WHERE d.deleted = 0
        AND (d.sales_stage NOT IN ('Closed Won', 'Closed Lost') OR d.sales_stage IS NULL)
        ORDER BY IFNULL(c.focus_flag_c, 0) DESC, IFNULL(c.focus_order_c, 999) ASC, d.date_modified DESC
        LIMIT 10";

$result = $db->query($query);

if (!$result) {
    echo "Query failed!\n";
    echo "Error: " . $db->last_error . "\n";
    exit;
}

$count = 0;
$stages = [];

while ($row = $db->fetchByAssoc($result)) {
    $count++;
    
    // Combine names
    $assigned_user_name = trim($row['first_name'] . ' ' . $row['last_name']);
    
    // Map stage
    $stage = $row['pipeline_stage_c'] ?: 'sourcing';
    
    if (!isset($stages[$stage])) {
        $stages[$stage] = 0;
    }
    $stages[$stage]++;
    
    if ($count <= 3) {
        echo "Deal $count:\n";
        echo "  Name: " . $row['name'] . "\n";
        echo "  Sales Stage: " . ($row['sales_stage'] ?: 'NULL') . "\n";
        echo "  Pipeline Stage: " . $stage . "\n";
        echo "  Assigned To: " . ($assigned_user_name ?: 'Not assigned') . "\n";
        echo "  Account: " . ($row['account_name'] ?: 'No account') . "\n";
        echo "  ---\n";
    }
}

echo "\nTotal deals found: $count\n";
echo "\nDeals by stage:\n";
foreach ($stages as $stage => $cnt) {
    echo "  - $stage: $cnt\n";
}

echo "\nQuery executed successfully!\n";
?>