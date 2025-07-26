<?php
/**
 * Check deals table structure
 */

// Set up SuiteCRM environment
if (!defined('sugarEntry')) define('sugarEntry', true);
// Check if we need to change directory
if (file_exists('SuiteCRM/include/entryPoint.php')) {
    chdir('SuiteCRM');
}
require_once('include/entryPoint.php');

global $db;

echo "=== CHECKING DEALS TABLE STRUCTURE ===\n\n";

// 1. Check if deals table exists
echo "1. Checking if 'deals' table exists:\n";
$tables = [];
$result = $db->query("SHOW TABLES LIKE 'deals%'");
while ($row = $db->fetchByAssoc($result)) {
    $table = current($row);
    $tables[] = $table;
    echo "   - Found: $table\n";
}

if (!in_array('deals', $tables)) {
    echo "\n❌ ERROR: 'deals' table does not exist!\n";
    echo "The Deals module may not be properly installed.\n";
    exit(1);
}

// 2. Show deals table structure
echo "\n2. Structure of 'deals' table:\n";
$result = $db->query("DESCRIBE deals");
echo str_repeat("-", 80) . "\n";
echo sprintf("%-20s %-20s %-10s %-10s\n", "Field", "Type", "Null", "Key");
echo str_repeat("-", 80) . "\n";
while ($row = $db->fetchByAssoc($result)) {
    echo sprintf("%-20s %-20s %-10s %-10s\n", 
        $row['Field'], 
        $row['Type'], 
        $row['Null'], 
        $row['Key']
    );
}

// 3. Check deals_cstm table
if (in_array('deals_cstm', $tables)) {
    echo "\n3. Structure of 'deals_cstm' table:\n";
    $result = $db->query("DESCRIBE deals_cstm");
    echo str_repeat("-", 80) . "\n";
    echo sprintf("%-20s %-20s %-10s %-10s\n", "Field", "Type", "Null", "Key");
    echo str_repeat("-", 80) . "\n";
    while ($row = $db->fetchByAssoc($result)) {
        echo sprintf("%-20s %-20s %-10s %-10s\n", 
            $row['Field'], 
            $row['Type'], 
            $row['Null'], 
            $row['Key']
        );
    }
}

// 4. Check for any errors
echo "\n4. Testing INSERT capability:\n";
$test_id = create_guid();
$test_query = "INSERT INTO deals (id, name, date_entered, date_modified, deleted) 
               VALUES ('$test_id', 'Test Deal', NOW(), NOW(), 0)";

if ($db->query($test_query)) {
    echo "   ✓ Successfully inserted test record\n";
    
    // Clean up
    $db->query("DELETE FROM deals WHERE id = '$test_id'");
    echo "   ✓ Test record deleted\n";
} else {
    echo "   ✗ Failed to insert test record\n";
    echo "   Error: " . $db->last_error . "\n";
}

echo "\n=== CHECK COMPLETE ===\n";