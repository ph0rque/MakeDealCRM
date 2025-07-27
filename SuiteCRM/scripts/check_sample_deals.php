<?php
/**
 * Script to check for sample deals and deals with sample IDs
 */

// SuiteCRM bootstrap
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');

global $db;

echo "Checking for sample deals in database...\n\n";

// Check for deals with 'Sample' in name
echo "1. Checking for deals with 'Sample' in name:\n";
$query1 = "SELECT id, name FROM opportunities WHERE deleted = 0 AND name LIKE '%Sample%'";
$result1 = $db->query($query1);
$count1 = 0;
while ($row = $db->fetchByAssoc($result1)) {
    echo "   - ID: {$row['id']}, Name: {$row['name']}\n";
    $count1++;
}
echo "   Found: $count1 deals\n\n";

// Check for deals with 'sample-' IDs
echo "2. Checking for deals with 'sample-' IDs:\n";
$query2 = "SELECT id, name FROM opportunities WHERE deleted = 0 AND id LIKE 'sample-%'";
$result2 = $db->query($query2);
$count2 = 0;
while ($row = $db->fetchByAssoc($result2)) {
    echo "   - ID: {$row['id']}, Name: {$row['name']}\n";
    $count2++;
}
echo "   Found: $count2 deals\n\n";

// Check ALL deals
echo "3. All deals in database:\n";
$query3 = "SELECT id, name FROM opportunities WHERE deleted = 0 LIMIT 20";
$result3 = $db->query($query3);
$count3 = 0;
while ($row = $db->fetchByAssoc($result3)) {
    echo "   - ID: {$row['id']}, Name: {$row['name']}\n";
    $count3++;
}
echo "   Total shown: $count3 (limited to 20)\n\n";

// Check total count
$query4 = "SELECT COUNT(*) as total FROM opportunities WHERE deleted = 0";
$result4 = $db->query($query4);
$row4 = $db->fetchByAssoc($result4);
echo "Total deals in database: {$row4['total']}\n";