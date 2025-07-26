<?php
/**
 * Repair script to add all pipeline-related fields to opportunities_cstm table
 * This ensures all custom fields required for the pipeline view are present
 */

if (!defined('sugarEntry')) define('sugarEntry', true);

// Bootstrap SuiteCRM
chdir(dirname(__FILE__) . '/../../../../SuiteCRM');
require_once('include/entryPoint.php');
require_once('modules/Administration/QuickRepairAndRebuild.php');

// Check if running from command line or web
$isCLI = (php_sapi_name() === 'cli');

if (!$isCLI && !is_admin($current_user)) {
    die('Access denied. Only administrators can run this script.');
}

echo $isCLI ? "Starting pipeline fields repair...\n" : "<h3>Starting pipeline fields repair...</h3>";

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

// Array of fields to check and create
$fields = [
    'pipeline_stage_c' => [
        'sql' => "ADD COLUMN pipeline_stage_c varchar(50) DEFAULT 'sourcing'",
        'description' => 'Pipeline stage field'
    ],
    'stage_entered_date_c' => [
        'sql' => "ADD COLUMN stage_entered_date_c datetime DEFAULT NULL",
        'description' => 'Stage entered date field'
    ],
    'expected_close_date_c' => [
        'sql' => "ADD COLUMN expected_close_date_c date DEFAULT NULL",
        'description' => 'Expected close date field'
    ],
    'deal_source_c' => [
        'sql' => "ADD COLUMN deal_source_c varchar(50) DEFAULT NULL",
        'description' => 'Deal source field'
    ],
    'pipeline_notes_c' => [
        'sql' => "ADD COLUMN pipeline_notes_c text",
        'description' => 'Pipeline notes field'
    ],
    'focus_flag_c' => [
        'sql' => "ADD COLUMN focus_flag_c tinyint(1) DEFAULT '0'",
        'description' => 'Focus flag field'
    ],
    'focus_order_c' => [
        'sql' => "ADD COLUMN focus_order_c int(11) DEFAULT '0'",
        'description' => 'Focus order field'
    ],
    'focus_date_c' => [
        'sql' => "ADD COLUMN focus_date_c datetime DEFAULT NULL",
        'description' => 'Focus date field'
    ]
];

// Check and add each field
foreach ($fields as $fieldName => $fieldInfo) {
    $fieldCheck = "SHOW COLUMNS FROM opportunities_cstm LIKE '$fieldName'";
    $result = $db->query($fieldCheck);
    
    if (!$db->fetchByAssoc($result)) {
        echo $isCLI ? "Adding {$fieldInfo['description']}...\n" : "<p>Adding {$fieldInfo['description']}...</p>";
        
        $addField = "ALTER TABLE opportunities_cstm {$fieldInfo['sql']}";
        $db->query($addField);
    } else {
        echo $isCLI ? "{$fieldInfo['description']} already exists.\n" : "<p>{$fieldInfo['description']} already exists.</p>";
    }
}

// Add indexes for performance
echo $isCLI ? "\nAdding indexes...\n" : "<p>Adding indexes...</p>";

$indexes = [
    'idx_pipeline_stage' => [
        'columns' => '(pipeline_stage_c)',
        'description' => 'Index for pipeline stage'
    ],
    'idx_focus_flag' => [
        'columns' => '(focus_flag_c)',
        'description' => 'Index for focus flag'
    ],
    'idx_stage_entered' => [
        'columns' => '(stage_entered_date_c)',
        'description' => 'Index for stage entered date'
    ],
    'idx_pipeline_composite' => [
        'columns' => '(pipeline_stage_c, focus_flag_c, focus_order_c)',
        'description' => 'Composite index for pipeline queries'
    ]
];

foreach ($indexes as $indexName => $indexInfo) {
    $indexCheck = "SHOW INDEX FROM opportunities_cstm WHERE Key_name = '$indexName'";
    $result = $db->query($indexCheck);
    
    if (!$db->fetchByAssoc($result)) {
        echo $isCLI ? "Adding {$indexInfo['description']}...\n" : "<p>Adding {$indexInfo['description']}...</p>";
        
        $addIndex = "ALTER TABLE opportunities_cstm ADD INDEX $indexName {$indexInfo['columns']}";
        $db->query($addIndex);
    } else {
        echo $isCLI ? "{$indexInfo['description']} already exists.\n" : "<p>{$indexInfo['description']} already exists.</p>";
    }
}

// Create pipeline_stage_history table if it doesn't exist
$tableCheck = "SHOW TABLES LIKE 'pipeline_stage_history'";
$result = $db->query($tableCheck);

if (!$db->fetchByAssoc($result)) {
    echo $isCLI ? "\nCreating pipeline_stage_history table...\n" : "<p>Creating pipeline_stage_history table...</p>";
    
    $createHistoryTable = "CREATE TABLE pipeline_stage_history (
        id char(36) NOT NULL,
        deal_id char(36) NOT NULL,
        old_stage varchar(50),
        new_stage varchar(50),
        changed_by char(36),
        date_changed datetime,
        PRIMARY KEY (id),
        KEY idx_deal_id (deal_id),
        KEY idx_date_changed (date_changed)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
    
    $db->query($createHistoryTable);
}

// Clear caches
echo $isCLI ? "\nClearing caches...\n" : "<p>Clearing caches...</p>";

// Clear vardefs cache
$cacheDirs = [
    'cache/modules/Opportunities',
    'cache/modules/Deals',
    'custom/modules/Deals/Ext'
];

foreach ($cacheDirs as $dir) {
    if (is_dir($dir)) {
        $files = glob($dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}

// Update existing opportunities with default pipeline_stage_c if null
echo $isCLI ? "\nUpdating existing opportunities with default pipeline stage...\n" : "<p>Updating existing opportunities with default pipeline stage...</p>";

$updateQuery = "UPDATE opportunities o 
                LEFT JOIN opportunities_cstm oc ON o.id = oc.id_c 
                SET oc.pipeline_stage_c = CASE 
                    WHEN o.sales_stage = 'Prospecting' THEN 'sourcing'
                    WHEN o.sales_stage = 'Qualification' THEN 'screening'
                    WHEN o.sales_stage = 'Needs Analysis' THEN 'analysis_outreach'
                    WHEN o.sales_stage = 'Value Proposition' THEN 'valuation_structuring'
                    WHEN o.sales_stage IN ('Id. Decision Makers', 'Perception Analysis') THEN 'due_diligence'
                    WHEN o.sales_stage IN ('Proposal/Price Quote', 'Negotiation/Review') THEN 'loi_negotiation'
                    WHEN o.sales_stage = 'Closed Won' THEN 'closed_owned_stable'
                    WHEN o.sales_stage = 'Closed Lost' THEN 'unavailable'
                    ELSE 'sourcing'
                END,
                oc.stage_entered_date_c = IFNULL(oc.stage_entered_date_c, o.date_modified)
                WHERE o.deleted = 0 AND (oc.pipeline_stage_c IS NULL OR oc.pipeline_stage_c = '')";

$db->query($updateQuery);

// Ensure all opportunities have a corresponding _cstm record
echo $isCLI ? "\nEnsuring all opportunities have custom table records...\n" : "<p>Ensuring all opportunities have custom table records...</p>";

$insertMissingQuery = "INSERT INTO opportunities_cstm (id_c, pipeline_stage_c, stage_entered_date_c)
                      SELECT o.id, 'sourcing', o.date_modified
                      FROM opportunities o
                      LEFT JOIN opportunities_cstm oc ON o.id = oc.id_c
                      WHERE o.deleted = 0 AND oc.id_c IS NULL";

$db->query($insertMissingQuery);

echo $isCLI ? "\nPipeline fields repair completed successfully!\n" : "<h3>Pipeline fields repair completed successfully!</h3>";
echo $isCLI ? "Please run 'Quick Repair and Rebuild' from Admin panel for best results.\n" : "<p>Please run 'Quick Repair and Rebuild' from Admin panel for best results.</p>";

if (!$isCLI) {
    echo '<p><a href="index.php?module=Administration&action=index">Return to Admin Panel</a></p>';
}