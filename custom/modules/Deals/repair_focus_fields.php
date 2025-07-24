<?php
/**
 * Repair script to add focus flag fields to opportunities_cstm table
 * Run this script after deploying the focus flag functionality
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('modules/Administration/QuickRepairAndRebuild.php');

// Check if running from command line or web
$isCLI = (php_sapi_name() === 'cli');

if (!$isCLI && !is_admin($current_user)) {
    die('Access denied. Only administrators can run this script.');
}

echo $isCLI ? "Starting focus fields repair...\n" : "<h3>Starting focus fields repair...</h3>";

// Perform Quick Repair and Rebuild
$repair = new RepairAndClear();
$repair->repairAndClearAll(['clearAll'], ['Opportunities'], true, false);

// Check if fields exist and add them if they don't
global $db;

// Check if opportunities_cstm table exists
$tableCheck = "SHOW TABLES LIKE 'opportunities_cstm'";
$result = $db->query($tableCheck);

if (!$db->fetchByAssoc($result)) {
    // Create opportunities_cstm table if it doesn't exist
    echo $isCLI ? "Creating opportunities_cstm table...\n" : "<p>Creating opportunities_cstm table...</p>";
    
    $createTable = "CREATE TABLE opportunities_cstm (
        id_c char(36) NOT NULL,
        PRIMARY KEY (id_c)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
    
    $db->query($createTable);
}

// Check and add focus_flag_c field
$fieldCheck = "SHOW COLUMNS FROM opportunities_cstm LIKE 'focus_flag_c'";
$result = $db->query($fieldCheck);

if (!$db->fetchByAssoc($result)) {
    echo $isCLI ? "Adding focus_flag_c field...\n" : "<p>Adding focus_flag_c field...</p>";
    
    $addField = "ALTER TABLE opportunities_cstm 
                 ADD COLUMN focus_flag_c tinyint(1) DEFAULT '0'";
    
    $db->query($addField);
}

// Check and add focus_order_c field
$fieldCheck = "SHOW COLUMNS FROM opportunities_cstm LIKE 'focus_order_c'";
$result = $db->query($fieldCheck);

if (!$db->fetchByAssoc($result)) {
    echo $isCLI ? "Adding focus_order_c field...\n" : "<p>Adding focus_order_c field...</p>";
    
    $addField = "ALTER TABLE opportunities_cstm 
                 ADD COLUMN focus_order_c int(11) DEFAULT '0'";
    
    $db->query($addField);
}

// Check and add focus_date_c field
$fieldCheck = "SHOW COLUMNS FROM opportunities_cstm LIKE 'focus_date_c'";
$result = $db->query($fieldCheck);

if (!$db->fetchByAssoc($result)) {
    echo $isCLI ? "Adding focus_date_c field...\n" : "<p>Adding focus_date_c field...</p>";
    
    $addField = "ALTER TABLE opportunities_cstm 
                 ADD COLUMN focus_date_c datetime DEFAULT NULL";
    
    $db->query($addField);
}

// Add indexes for performance
echo $isCLI ? "Adding indexes...\n" : "<p>Adding indexes...</p>";

// Index for focus_flag_c
$indexCheck = "SHOW INDEX FROM opportunities_cstm WHERE Key_name = 'idx_focus_flag'";
$result = $db->query($indexCheck);

if (!$db->fetchByAssoc($result)) {
    $addIndex = "ALTER TABLE opportunities_cstm 
                 ADD INDEX idx_focus_flag (focus_flag_c)";
    
    $db->query($addIndex);
}

// Composite index for focus queries
$indexCheck = "SHOW INDEX FROM opportunities_cstm WHERE Key_name = 'idx_focus_stage_order'";
$result = $db->query($indexCheck);

if (!$db->fetchByAssoc($result)) {
    $addIndex = "ALTER TABLE opportunities_cstm 
                 ADD INDEX idx_focus_stage_order (pipeline_stage_c, focus_flag_c, focus_order_c)";
    
    $db->query($addIndex);
}

// Clear caches
echo $isCLI ? "Clearing caches...\n" : "<p>Clearing caches...</p>";

// Clear vardefs cache
if (is_dir('cache/modules/Opportunities')) {
    $files = glob('cache/modules/Opportunities/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
}

// Clear language cache
if (is_dir('cache/modules/Opportunities/language')) {
    $files = glob('cache/modules/Opportunities/language/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
}

echo $isCLI ? "\nFocus fields repair completed successfully!\n" : "<h3>Focus fields repair completed successfully!</h3>";
echo $isCLI ? "Please run 'Quick Repair and Rebuild' from Admin panel for best results.\n" : "<p>Please run 'Quick Repair and Rebuild' from Admin panel for best results.</p>";

if (!$isCLI) {
    echo '<p><a href="index.php?module=Administration&action=index">Return to Admin Panel</a></p>';
}