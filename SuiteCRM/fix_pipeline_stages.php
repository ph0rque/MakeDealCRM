<?php
/**
 * Fix Pipeline Stages to match expected values
 */

if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');

global $db;

echo "Fixing pipeline stages to match expected values...\n\n";

// Mapping of current stages to expected stages
$stageMapping = [
    'sourcing' => 'sourcing',           // correct
    'qualifying' => 'screening',         // needs update
    'pitching' => 'analysis_outreach',   // needs update
    'negotiating' => 'term_sheet',       // needs update
    'closing' => 'closing',              // correct
    'won' => 'closed_won',               // needs update
    'lost' => 'closed_lost'              // needs update
];

// Update pipeline stages
foreach ($stageMapping as $current => $expected) {
    if ($current !== $expected) {
        $query = "UPDATE opportunities_cstm SET pipeline_stage_c = '$expected' WHERE pipeline_stage_c = '$current'";
        $result = $db->query($query);
        // Use mysqli_affected_rows directly
        $affected = mysqli_affected_rows($db->database);
        echo "Updated $affected deals from '$current' to '$expected'\n";
    }
}

// Also ensure all deals have a pipeline stage
$query = "UPDATE opportunities_cstm oc
          INNER JOIN opportunities o ON o.id = oc.id_c
          SET oc.pipeline_stage_c = CASE
              WHEN o.sales_stage = 'Prospecting' THEN 'sourcing'
              WHEN o.sales_stage = 'Qualification' THEN 'screening'
              WHEN o.sales_stage = 'Needs Analysis' THEN 'analysis_outreach'
              WHEN o.sales_stage = 'Value Proposition' THEN 'due_diligence'
              WHEN o.sales_stage = 'Id. Decision Makers' THEN 'due_diligence'
              WHEN o.sales_stage = 'Perception Analysis' THEN 'term_sheet'
              WHEN o.sales_stage = 'Proposal/Price Quote' THEN 'term_sheet'
              WHEN o.sales_stage = 'Negotiation/Review' THEN 'final_negotiation'
              WHEN o.sales_stage = 'Closed Won' THEN 'closed_won'
              WHEN o.sales_stage = 'Closed Lost' THEN 'closed_lost'
              ELSE 'sourcing'
          END
          WHERE (oc.pipeline_stage_c IS NULL OR oc.pipeline_stage_c = '')
          AND o.deleted = 0";
$result = $db->query($query);
// Use mysqli_affected_rows directly
$affected = mysqli_affected_rows($db->database);
echo "\nSet pipeline stage for $affected deals based on sales stage\n";

// Clear cache
echo "\nClearing cache...\n";
$cacheDirectories = [
    'cache/modules/Deals/',
    'cache/themes/',
    'cache/smarty/templates_c/'
];

foreach ($cacheDirectories as $dir) {
    if (is_dir($dir)) {
        $files = glob($dir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
}

// Show summary
$query = "SELECT oc.pipeline_stage_c, COUNT(*) as count 
          FROM opportunities o
          LEFT JOIN opportunities_cstm oc ON o.id = oc.id_c
          WHERE o.deleted = 0
          GROUP BY oc.pipeline_stage_c
          ORDER BY oc.pipeline_stage_c";
$result = $db->query($query);

echo "\nCurrent pipeline stage distribution:\n";
echo "-----------------------------------\n";
while ($row = $db->fetchByAssoc($result)) {
    $stage = $row['pipeline_stage_c'] ?: '(no stage)';
    echo sprintf("%-20s: %d deals\n", $stage, $row['count']);
}

echo "\nPipeline stages fixed successfully!\n";
echo "Refresh the pipeline view to see the changes.\n";