<?php
/**
 * Initialize NULL pipeline stages to default 'sourcing' stage
 */

if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');

global $db;

echo "Initializing NULL pipeline stages...\n\n";

// Update all NULL pipeline stages to 'sourcing'
$updateQuery = "UPDATE opportunities_cstm 
                SET pipeline_stage_c = 'sourcing',
                    stage_entered_date_c = NOW()
                WHERE pipeline_stage_c IS NULL OR pipeline_stage_c = ''";

$result = $db->query($updateQuery);

// Count how many were updated
$countQuery = "SELECT COUNT(*) as count FROM opportunities_cstm WHERE pipeline_stage_c = 'sourcing'";
$countResult = $db->query($countQuery);
$row = $db->fetchByAssoc($countResult);
$totalInSourcing = $row['count'];

echo "✅ Deals in sourcing stage: $totalInSourcing\n\n";

// Show current distribution
echo "Current pipeline stage distribution:\n";
$distributionQuery = "SELECT 
                        COALESCE(c.pipeline_stage_c, 'sourcing') as stage,
                        COUNT(*) as count
                      FROM opportunities o
                      LEFT JOIN opportunities_cstm c ON o.id = c.id_c
                      WHERE o.deleted = 0
                      AND o.sales_stage NOT IN ('Closed Won', 'Closed Lost')
                      GROUP BY stage
                      ORDER BY count DESC";

$result = $db->query($distributionQuery);
while ($row = $db->fetchByAssoc($result)) {
    echo "- {$row['stage']}: {$row['count']} deals\n";
}

echo "\n✅ Pipeline stages initialized!\n";
?>