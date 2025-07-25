<?php
/**
 * Script to check and ensure pipeline fields exist in opportunities_cstm table
 */

if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');

global $db;

echo "Checking pipeline fields in opportunities_cstm table...\n\n";

// Check if opportunities_cstm table exists
$tableCheck = "SHOW TABLES LIKE 'opportunities_cstm'";
$result = $db->query($tableCheck);
if ($db->fetchByAssoc($result)) {
    echo "✅ opportunities_cstm table exists\n\n";
} else {
    echo "❌ opportunities_cstm table does NOT exist\n";
    echo "Creating table...\n";
    
    $createTable = "CREATE TABLE opportunities_cstm (
        id_c char(36) NOT NULL,
        PRIMARY KEY (id_c)
    ) CHARACTER SET utf8 COLLATE utf8_general_ci";
    
    $db->query($createTable);
    echo "✅ Table created\n\n";
}

// Define required fields
$requiredFields = [
    'pipeline_stage_c' => "VARCHAR(100) DEFAULT 'sourcing'",
    'stage_entered_date_c' => "DATETIME DEFAULT NULL",
    'expected_close_date_c' => "DATE DEFAULT NULL",
    'focus_flag_c' => "TINYINT(1) DEFAULT 0",
    'focus_order_c' => "INT DEFAULT 0",
    'focus_date_c' => "DATETIME DEFAULT NULL"
];

// Check each field
foreach ($requiredFields as $field => $definition) {
    $columnCheck = "SHOW COLUMNS FROM opportunities_cstm LIKE '$field'";
    $result = $db->query($columnCheck);
    
    if ($db->fetchByAssoc($result)) {
        echo "✅ Field $field exists\n";
    } else {
        echo "❌ Field $field does NOT exist\n";
        echo "   Adding field...\n";
        
        $addColumn = "ALTER TABLE opportunities_cstm ADD COLUMN $field $definition";
        $db->query($addColumn);
        echo "   ✅ Field added\n";
    }
}

echo "\n\nChecking sample data...\n";
$sampleQuery = "SELECT o.id, o.name, c.pipeline_stage_c, c.stage_entered_date_c 
                FROM opportunities o
                LEFT JOIN opportunities_cstm c ON o.id = c.id_c
                WHERE o.deleted = 0
                LIMIT 5";

$result = $db->query($sampleQuery);
echo "\nSample deals:\n";
while ($row = $db->fetchByAssoc($result)) {
    echo "- {$row['name']} (ID: {$row['id']})\n";
    echo "  Pipeline Stage: " . ($row['pipeline_stage_c'] ?: 'NULL') . "\n";
    echo "  Stage Entered: " . ($row['stage_entered_date_c'] ?: 'NULL') . "\n\n";
}

// Ensure all deals have entries in custom table
echo "Ensuring all deals have custom table entries...\n";
$insertMissing = "INSERT INTO opportunities_cstm (id_c)
                  SELECT o.id FROM opportunities o
                  LEFT JOIN opportunities_cstm c ON o.id = c.id_c
                  WHERE o.deleted = 0 AND c.id_c IS NULL";

$db->query($insertMissing);
echo "✅ Missing entries added\n";

echo "\nPipeline fields check complete!\n";
?>