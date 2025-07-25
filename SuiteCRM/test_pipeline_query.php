<?php
/**
 * Test the pipeline query step by step
 */

if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');

global $db;

echo "Testing pipeline query step by step...\n\n";

// Step 1: Basic query
echo "1. Basic query (no joins):\n";
$query1 = "SELECT COUNT(*) as count FROM opportunities d WHERE d.deleted = 0";
$result1 = $db->query($query1);
$row1 = $db->fetchByAssoc($result1);
echo "   Count: " . $row1['count'] . "\n\n";

// Step 2: With sales stage filter
echo "2. With sales stage filter:\n";
$query2 = "SELECT COUNT(*) as count FROM opportunities d 
           WHERE d.deleted = 0 
           AND d.sales_stage NOT IN ('Closed Won', 'Closed Lost')";
$result2 = $db->query($query2);
$row2 = $db->fetchByAssoc($result2);
echo "   Count: " . $row2['count'] . "\n\n";

// Step 3: With custom table join
echo "3. With custom table join:\n";
$query3 = "SELECT COUNT(*) as count 
           FROM opportunities d
           LEFT JOIN opportunities_cstm c ON d.id = c.id_c
           WHERE d.deleted = 0 
           AND d.sales_stage NOT IN ('Closed Won', 'Closed Lost')";
$result3 = $db->query($query3);
$row3 = $db->fetchByAssoc($result3);
echo "   Count: " . $row3['count'] . "\n\n";

// Step 4: Check for SQL errors
echo "4. Running full query and checking for errors:\n";
$fullQuery = "SELECT 
            d.id,
            d.name,
            d.amount,
            d.sales_stage,
            d.date_modified,
            d.assigned_user_id,
            d.account_id,
            d.probability,
            c.pipeline_stage_c,
            c.stage_entered_date_c,
            c.expected_close_date_c,
            c.focus_flag_c,
            c.focus_order_c,
            c.focus_date_c,
            CONCAT(u.first_name, ' ', u.last_name) as assigned_user_name,
            a.name as account_name
        FROM opportunities d
        LEFT JOIN opportunities_cstm c ON d.id = c.id_c
        LEFT JOIN users u ON d.assigned_user_id = u.id AND u.deleted = 0
        LEFT JOIN accounts a ON d.account_id = a.id AND a.deleted = 0
        WHERE d.deleted = 0
        AND d.sales_stage NOT IN ('Closed Won', 'Closed Lost')
        ORDER BY c.focus_flag_c DESC, c.focus_order_c ASC, d.date_modified DESC
        LIMIT 10";

// Run query with error checking
$result = $db->query($fullQuery, true, "Error in pipeline query: ");

if (!$result) {
    echo "   Query failed!\n";
    echo "   Last error: " . $db->last_error . "\n";
} else {
    $count = 0;
    while ($row = $db->fetchByAssoc($result)) {
        $count++;
        if ($count == 1) {
            echo "   First row data:\n";
            echo "   - ID: " . $row['id'] . "\n";
            echo "   - Name: " . $row['name'] . "\n";
            echo "   - Pipeline Stage: " . $row['pipeline_stage_c'] . "\n";
        }
    }
    echo "   Total rows returned: " . $count . "\n";
}

// Step 5: Check if it's a case sensitivity issue
echo "\n5. Checking case sensitivity:\n";
$query5 = "SELECT DISTINCT sales_stage FROM opportunities WHERE deleted = 0";
$result5 = $db->query($query5);
while ($row = $db->fetchByAssoc($result5)) {
    echo "   - '" . $row['sales_stage'] . "'\n";
}

echo "\nDone!\n";
?>