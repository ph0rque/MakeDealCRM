<?php
/**
 * Simple debug of pipeline issues
 */

if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');

global $db;

echo "=== Simple Pipeline Debug ===\n\n";

// 1. Count deals
echo "1. Total deals in system:\n";
$query = "SELECT COUNT(*) as count FROM opportunities WHERE deleted = 0";
$result = $db->query($query);
$row = $db->fetchByAssoc($result);
echo "   Total: {$row['count']} deals\n\n";

// 2. Show first 5 deals with all relevant fields
echo "2. First 5 deals with complete data:\n";
$query = "SELECT o.*, oc.* 
          FROM opportunities o
          LEFT JOIN opportunities_cstm oc ON o.id = oc.id_c
          WHERE o.deleted = 0
          LIMIT 5";
$result = $db->query($query);
while ($row = $db->fetchByAssoc($result)) {
    echo "\nDeal: {$row['name']} (ID: {$row['id']})\n";
    echo "- sales_stage: '{$row['sales_stage']}'\n";
    echo "- amount: '{$row['amount']}'\n";
    echo "- date_closed: '{$row['date_closed']}'\n";
    echo "- pipeline_stage_c: '{$row['pipeline_stage_c']}'\n";
    echo "- deleted: {$row['deleted']}\n";
}

// 3. Check if the issue is with the LEFT JOINs
echo "\n3. Testing without user/account joins:\n";
$query = "SELECT 
            o.id,
            o.name,
            o.amount,
            o.sales_stage,
            oc.pipeline_stage_c
          FROM opportunities o
          LEFT JOIN opportunities_cstm oc ON o.id = oc.id_c
          WHERE o.deleted = 0";
$result = $db->query($query);
$count = 0;
while ($row = $db->fetchByAssoc($result)) {
    $count++;
    if ($count <= 3) {
        echo "- {$row['name']} (stage: {$row['pipeline_stage_c']})\n";
    }
}
echo "Found $count deals without user/account joins\n";

// 4. Check table structure
echo "\n4. Checking table structure:\n";
$tables = ['opportunities', 'opportunities_cstm'];
foreach ($tables as $table) {
    $query = "DESCRIBE $table";
    $result = $db->query($query);
    echo "\n$table columns:\n";
    $count = 0;
    while ($row = $db->fetchByAssoc($result)) {
        $count++;
        if ($count <= 5) {
            echo "- {$row['Field']} ({$row['Type']})\n";
        }
    }
    echo "... and " . ($count - 5) . " more columns\n";
}

echo "\n=== End Debug ===\n";