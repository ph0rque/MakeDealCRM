<?php
/**
 * Test simpler version of pipeline query
 */

if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');

global $db;

echo "Testing simplified pipeline queries...\n\n";

// Test 1: Remove ORDER BY
echo "1. Query without ORDER BY:\n";
$query1 = "SELECT 
            d.id,
            d.name,
            d.sales_stage,
            c.pipeline_stage_c
        FROM opportunities d
        LEFT JOIN opportunities_cstm c ON d.id = c.id_c
        WHERE d.deleted = 0
        AND d.sales_stage NOT IN ('Closed Won', 'Closed Lost')
        LIMIT 5";

$result1 = $db->query($query1);
$count1 = 0;
while ($row = $db->fetchByAssoc($result1)) {
    $count1++;
    echo "   - " . $row['name'] . " (stage: " . $row['pipeline_stage_c'] . ")\n";
}
echo "   Total: $count1\n\n";

// Test 2: With simple ORDER BY
echo "2. Query with simple ORDER BY:\n";
$query2 = "SELECT 
            d.id,
            d.name,
            d.sales_stage,
            c.pipeline_stage_c
        FROM opportunities d
        LEFT JOIN opportunities_cstm c ON d.id = c.id_c
        WHERE d.deleted = 0
        AND d.sales_stage NOT IN ('Closed Won', 'Closed Lost')
        ORDER BY d.date_modified DESC
        LIMIT 5";

$result2 = $db->query($query2);
$count2 = 0;
while ($row = $db->fetchByAssoc($result2)) {
    $count2++;
    echo "   - " . $row['name'] . " (stage: " . $row['pipeline_stage_c'] . ")\n";
}
echo "   Total: $count2\n\n";

// Test 3: With COALESCE in ORDER BY
echo "3. Query with COALESCE in ORDER BY:\n";
$query3 = "SELECT 
            d.id,
            d.name,
            d.sales_stage,
            c.pipeline_stage_c,
            c.focus_flag_c,
            c.focus_order_c
        FROM opportunities d
        LEFT JOIN opportunities_cstm c ON d.id = c.id_c
        WHERE d.deleted = 0
        AND d.sales_stage NOT IN ('Closed Won', 'Closed Lost')
        ORDER BY COALESCE(c.focus_flag_c, 0) DESC, COALESCE(c.focus_order_c, 999) ASC, d.date_modified DESC
        LIMIT 5";

$result3 = $db->query($query3);
$count3 = 0;
while ($row = $db->fetchByAssoc($result3)) {
    $count3++;
    echo "   - " . $row['name'] . " (stage: " . $row['pipeline_stage_c'] . ", focus: " . $row['focus_flag_c'] . ")\n";
}
echo "   Total: $count3\n\n";

echo "Done!\n";
?>