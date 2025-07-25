<?php
/**
 * Debug script to check why deals aren't showing in pipeline
 */

if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');

global $db;

echo "=== Pipeline Debug Report ===\n\n";

// 1. Check total opportunities
$query1 = "SELECT COUNT(*) as total FROM opportunities WHERE deleted = 0";
$result1 = $db->query($query1);
$row1 = $db->fetchByAssoc($result1);
echo "1. Total opportunities (not deleted): " . $row1['total'] . "\n\n";

// 2. Check opportunities with custom data
$query2 = "SELECT COUNT(*) as total 
           FROM opportunities o
           LEFT JOIN opportunities_cstm c ON o.id = c.id_c
           WHERE o.deleted = 0";
$result2 = $db->query($query2);
$row2 = $db->fetchByAssoc($result2);
echo "2. Opportunities with custom table join: " . $row2['total'] . "\n\n";

// 3. Check sales stages distribution
echo "3. Sales stages distribution:\n";
$query3 = "SELECT sales_stage, COUNT(*) as count 
           FROM opportunities 
           WHERE deleted = 0 
           GROUP BY sales_stage";
$result3 = $db->query($query3);
while ($row = $db->fetchByAssoc($result3)) {
    echo "   - " . ($row['sales_stage'] ?: 'NULL') . ": " . $row['count'] . "\n";
}
echo "\n";

// 4. Check pipeline stages distribution
echo "4. Pipeline stages distribution:\n";
$query4 = "SELECT c.pipeline_stage_c, COUNT(*) as count 
           FROM opportunities o
           LEFT JOIN opportunities_cstm c ON o.id = c.id_c
           WHERE o.deleted = 0 
           GROUP BY c.pipeline_stage_c";
$result4 = $db->query($query4);
while ($row = $db->fetchByAssoc($result4)) {
    echo "   - " . ($row['pipeline_stage_c'] ?: 'NULL') . ": " . $row['count'] . "\n";
}
echo "\n";

// 5. Check deals not in closed stages
$query5 = "SELECT COUNT(*) as count 
           FROM opportunities o
           WHERE o.deleted = 0 
           AND o.sales_stage NOT IN ('Closed Won', 'Closed Lost')";
$result5 = $db->query($query5);
$row5 = $db->fetchByAssoc($result5);
echo "5. Opportunities NOT in Closed Won/Lost: " . $row5['count'] . "\n\n";

// 6. Sample opportunities with details
echo "6. Sample opportunities (first 5):\n";
$query6 = "SELECT o.id, o.name, o.sales_stage, c.pipeline_stage_c, o.deleted
           FROM opportunities o
           LEFT JOIN opportunities_cstm c ON o.id = c.id_c
           WHERE o.deleted = 0
           LIMIT 5";
$result6 = $db->query($query6);
while ($row = $db->fetchByAssoc($result6)) {
    echo "   ID: " . $row['id'] . "\n";
    echo "   Name: " . $row['name'] . "\n";
    echo "   Sales Stage: " . ($row['sales_stage'] ?: 'NULL') . "\n";
    echo "   Pipeline Stage: " . ($row['pipeline_stage_c'] ?: 'NULL') . "\n";
    echo "   ---\n";
}

// 7. Check the exact query used in the view
echo "\n7. Testing the exact query from pipeline view:\n";
$query7 = "SELECT 
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

$result7 = $db->query($query7);
$count7 = 0;
while ($row = $db->fetchByAssoc($result7)) {
    $count7++;
    if ($count7 == 1) {
        echo "First result:\n";
        echo "   ID: " . $row['id'] . "\n";
        echo "   Name: " . $row['name'] . "\n";
        echo "   Sales Stage: " . ($row['sales_stage'] ?: 'NULL') . "\n";
        echo "   Pipeline Stage: " . ($row['pipeline_stage_c'] ?: 'NULL') . "\n";
    }
}
echo "Total results from pipeline query: " . $count7 . "\n\n";

// 8. Check stage mapping
echo "8. Testing stage mapping:\n";
$testStages = ['Prospecting', 'Qualification', 'Needs Analysis', 'Value Proposition'];
$stageMapping = [
    'Prospecting' => 'sourcing',
    'Qualification' => 'screening',
    'Needs Analysis' => 'analysis_outreach',
    'Value Proposition' => 'valuation_structuring',
    'Id. Decision Makers' => 'due_diligence',
    'Perception Analysis' => 'due_diligence',
    'Proposal/Price Quote' => 'loi_negotiation',
    'Negotiation/Review' => 'loi_negotiation',
    'Closed Won' => 'closed_owned_stable',
    'Closed Lost' => 'unavailable'
];
foreach ($testStages as $stage) {
    echo "   $stage => " . (isset($stageMapping[$stage]) ? $stageMapping[$stage] : 'sourcing') . "\n";
}

echo "\n=== End Debug Report ===\n";
?>