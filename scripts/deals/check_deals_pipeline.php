<?php
/**
 * Check and Fix Deals Pipeline Data
 */

// Set up SuiteCRM environment
if (!defined('sugarEntry')) define('sugarEntry', true);
// Check if we need to change directory
if (file_exists('SuiteCRM/include/entryPoint.php')) {
    chdir('SuiteCRM');
}
require_once('include/entryPoint.php');

global $db;

echo "=== CHECKING DEALS PIPELINE DATA ===\n\n";

// 1. Check deals table
echo "1. Checking deals in database:\n";
$query = "SELECT d.id, d.name, d.sales_stage, d.amount, d.deleted, 
          c.pipeline_stage_c, c.stage_entered_date_c
          FROM deals d
          LEFT JOIN deals_cstm c ON d.id = c.id_c
          WHERE d.deleted = 0";

$result = $db->query($query);
$count = 0;
$deals_without_pipeline = [];

echo "\nActive Deals:\n";
echo str_repeat("-", 100) . "\n";
echo sprintf("%-36s %-30s %-20s %-20s\n", "ID", "Name", "Sales Stage", "Pipeline Stage");
echo str_repeat("-", 100) . "\n";

while ($row = $db->fetchByAssoc($result)) {
    $count++;
    echo sprintf("%-36s %-30s %-20s %-20s\n", 
        $row['id'], 
        substr($row['name'], 0, 30), 
        $row['sales_stage'] ?: 'None',
        $row['pipeline_stage_c'] ?: 'None'
    );
    
    if (empty($row['pipeline_stage_c'])) {
        $deals_without_pipeline[] = $row;
    }
}

echo str_repeat("-", 100) . "\n";
echo "Total active deals: $count\n";

// 2. Check if deals_cstm records exist
echo "\n2. Checking deals_cstm table:\n";
$query = "SELECT COUNT(*) as count FROM deals_cstm";
$result = $db->query($query);
$row = $db->fetchByAssoc($result);
echo "   - Records in deals_cstm: " . $row['count'] . "\n";

// 3. Fix deals without pipeline stage
if (count($deals_without_pipeline) > 0) {
    echo "\n3. Found " . count($deals_without_pipeline) . " deals without pipeline stage.\n";
    echo "Would you like to set them to 'sourcing' stage? (yes/no): ";
    
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($line) === 'yes') {
        foreach ($deals_without_pipeline as $deal) {
            // Check if custom record exists
            $check_query = "SELECT id_c FROM deals_cstm WHERE id_c = '{$deal['id']}'";
            $check_result = $db->query($check_query);
            
            if ($db->fetchByAssoc($check_result)) {
                // Update existing record
                $update_query = "UPDATE deals_cstm 
                               SET pipeline_stage_c = 'sourcing', 
                                   stage_entered_date_c = NOW() 
                               WHERE id_c = '{$deal['id']}'";
                $db->query($update_query);
            } else {
                // Insert new record
                $insert_query = "INSERT INTO deals_cstm (id_c, pipeline_stage_c, stage_entered_date_c) 
                               VALUES ('{$deal['id']}', 'sourcing', NOW())";
                $db->query($insert_query);
            }
            echo "   - Updated: {$deal['name']}\n";
        }
        echo "   - Pipeline stages updated!\n";
    }
}

// 4. Show pipeline stage distribution
echo "\n4. Pipeline Stage Distribution:\n";
$query = "SELECT 
          COALESCE(c.pipeline_stage_c, 'No Stage') as stage, 
          COUNT(*) as count
          FROM deals d
          LEFT JOIN deals_cstm c ON d.id = c.id_c
          WHERE d.deleted = 0
          GROUP BY c.pipeline_stage_c";

$result = $db->query($query);
while ($row = $db->fetchByAssoc($result)) {
    echo "   - " . $row['stage'] . ": " . $row['count'] . " deals\n";
}

// 5. Check table structure
echo "\n5. Checking deals_cstm table structure:\n";
$query = "SHOW COLUMNS FROM deals_cstm WHERE Field = 'pipeline_stage_c'";
$result = $db->query($query);
if ($row = $db->fetchByAssoc($result)) {
    echo "   - pipeline_stage_c column exists: YES\n";
    echo "   - Type: " . $row['Type'] . "\n";
} else {
    echo "   - pipeline_stage_c column exists: NO (This is the problem!)\n";
    echo "\n   Creating pipeline_stage_c column...\n";
    
    $create_column = "ALTER TABLE deals_cstm ADD COLUMN pipeline_stage_c VARCHAR(255) DEFAULT NULL";
    $db->query($create_column);
    
    $create_date_column = "ALTER TABLE deals_cstm ADD COLUMN stage_entered_date_c DATETIME DEFAULT NULL";
    $db->query($create_date_column);
    
    echo "   - Columns created!\n";
}

echo "\n=== CHECK COMPLETE ===\n";
echo "\nIf deals are still not showing in the pipeline:\n";
echo "1. Clear browser cache (Ctrl+F5)\n";
echo "2. Try creating a new deal to test\n";
echo "3. Check browser console for JavaScript errors\n";