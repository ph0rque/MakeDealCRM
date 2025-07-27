<?php
/**
 * Fix deals with empty fields
 */

if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');

global $db;

echo "=== Fixing Empty Deal Fields ===\n\n";

// 1. Update empty sales_stage
echo "1. Updating empty sales_stage fields:\n";
$query = "UPDATE opportunities 
          SET sales_stage = 'Prospecting',
              date_modified = date_modified
          WHERE (sales_stage IS NULL OR sales_stage = '') 
          AND deleted = 0";
$result = $db->query($query);
echo "   ✓ Updated deals with default sales_stage\n";

// 2. Update empty date_closed
echo "\n2. Updating empty date_closed fields:\n";
$query = "UPDATE opportunities 
          SET date_closed = DATE_ADD(CURDATE(), INTERVAL 30 DAY),
              date_modified = date_modified
          WHERE (date_closed IS NULL OR date_closed = '0000-00-00') 
          AND deleted = 0";
$result = $db->query($query);
echo "   ✓ Updated deals with default date_closed\n";

// 3. Update empty amounts
echo "\n3. Updating empty amount fields:\n";
$query = "UPDATE opportunities 
          SET amount = 100000,
              date_modified = date_modified
          WHERE (amount IS NULL OR amount = 0 OR amount = '') 
          AND deleted = 0";
$result = $db->query($query);
echo "   ✓ Updated deals with default amount\n";

// 4. Update pipeline stages
echo "\n4. Updating pipeline stages to match sales stages:\n";
$stageMapping = [
    'Prospecting' => 'sourcing',
    'Qualification' => 'screening',
    'Needs Analysis' => 'analysis_outreach',
    'Value Proposition' => 'due_diligence',
    'Id. Decision Makers' => 'due_diligence',
    'Perception Analysis' => 'analysis_outreach',
    'Proposal/Price Quote' => 'term_sheet',
    'Negotiation/Review' => 'final_negotiation',
    'Closed Won' => 'closed_won',
    'Closed Lost' => 'closed_lost'
];

foreach ($stageMapping as $salesStage => $pipelineStage) {
    $query = "UPDATE opportunities_cstm oc
              INNER JOIN opportunities o ON o.id = oc.id_c
              SET oc.pipeline_stage_c = '$pipelineStage'
              WHERE o.sales_stage = '$salesStage'
              AND (oc.pipeline_stage_c IS NULL OR oc.pipeline_stage_c = '')
              AND o.deleted = 0";
    $db->query($query);
}
echo "   ✓ Updated pipeline stages\n";

// 5. Show updated deals
echo "\n5. Verifying updated deals:\n";
$query = "SELECT o.id, o.name, o.sales_stage, o.amount, o.date_closed, oc.pipeline_stage_c
          FROM opportunities o
          LEFT JOIN opportunities_cstm oc ON o.id = oc.id_c
          WHERE o.deleted = 0
          ORDER BY o.date_entered DESC
          LIMIT 5";
$result = $db->query($query);
while ($row = $db->fetchByAssoc($result)) {
    echo "\n   Deal: {$row['name']}\n";
    echo "   - sales_stage: {$row['sales_stage']}\n";
    echo "   - amount: {$row['amount']}\n";
    echo "   - date_closed: {$row['date_closed']}\n";
    echo "   - pipeline_stage_c: {$row['pipeline_stage_c']}\n";
}

echo "\n=== Fix Complete! ===\n";
echo "The deals should now appear in the pipeline view.\n";
echo "Refresh: http://localhost:8080/index.php?module=Deals&action=Pipeline\n";