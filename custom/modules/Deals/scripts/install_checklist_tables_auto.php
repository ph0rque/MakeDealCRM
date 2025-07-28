<?php
/**
 * Non-interactive script to install checklist database tables
 * This version automatically creates missing tables without prompting
 */

// Define sugarEntry for SuiteCRM
if (!defined('sugarEntry')) define('sugarEntry', true);

// Determine the correct path to SuiteCRM root
$currentDir = dirname(__FILE__);
$suitecrmRoot = '/var/www/html';

// Change to SuiteCRM root directory
chdir($suitecrmRoot);

// Include SuiteCRM bootstrap
require_once('include/entryPoint.php');
require_once('include/utils/db_utils.php');

// Color codes for output
$colorRed = "\033[31m";
$colorGreen = "\033[32m";
$colorYellow = "\033[33m";
$colorBlue = "\033[34m";
$colorReset = "\033[0m";

echo "{$colorBlue}=== Automatic Checklist Database Installation ==={$colorReset}\n\n";

// Tables to check
$tables = [
    'checklist_templates' => 'Checklist template definitions',
    'checklist_items' => 'Individual checklist items', 
    'deals_checklist_templates' => 'Deal-template relationships',
    'deals_checklist_items' => 'Deal checklist item tracking'
];

$missingTables = [];

// Check which tables exist
echo "{$colorYellow}Checking existing tables...{$colorReset}\n";
foreach ($tables as $table => $description) {
    $sql = "SHOW TABLES LIKE '{$table}'";
    $result = $db->query($sql);
    
    if ($db->fetchByAssoc($result)) {
        echo "{$colorGreen}✓{$colorReset} Table '{$table}' exists ({$description})\n";
    } else {
        echo "{$colorRed}✗{$colorReset} Table '{$table}' is missing ({$description})\n";
        $missingTables[] = $table;
    }
}

if (empty($missingTables)) {
    echo "\n{$colorGreen}All checklist tables already exist!{$colorReset}\n";
    exit(0);
}

// Automatically create missing tables
echo "\n{$colorYellow}Found " . count($missingTables) . " missing table(s).{$colorReset}\n";
echo "{$colorYellow}Creating missing tables automatically...{$colorReset}\n\n";

// Table creation SQL
$createStatements = [
    'checklist_templates' => "
        CREATE TABLE checklist_templates (
            id varchar(36) NOT NULL,
            name varchar(255) NOT NULL,
            description text,
            category varchar(100),
            is_active tinyint(1) DEFAULT 1,
            created_by varchar(36),
            date_created datetime,
            modified_by varchar(36),
            date_modified datetime,
            deleted tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_checklist_templates_name (name),
            KEY idx_checklist_templates_category (category),
            KEY idx_checklist_templates_deleted (deleted)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",
    
    'checklist_items' => "
        CREATE TABLE checklist_items (
            id varchar(36) NOT NULL,
            template_id varchar(36) NOT NULL,
            name varchar(255) NOT NULL,
            description text,
            category varchar(100),
            priority varchar(20) DEFAULT 'medium',
            required tinyint(1) DEFAULT 0,
            sort_order int DEFAULT 0,
            assignee_type varchar(50),
            created_by varchar(36),
            date_created datetime,
            modified_by varchar(36),
            date_modified datetime,
            deleted tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_checklist_items_template (template_id),
            KEY idx_checklist_items_category (category),
            KEY idx_checklist_items_deleted (deleted),
            CONSTRAINT fk_checklist_items_template FOREIGN KEY (template_id) 
                REFERENCES checklist_templates(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",
    
    'deals_checklist_templates' => "
        CREATE TABLE deals_checklist_templates (
            id varchar(36) NOT NULL,
            deal_id varchar(36) NOT NULL,
            template_id varchar(36) NOT NULL,
            applied_by varchar(36),
            date_applied datetime,
            PRIMARY KEY (id),
            KEY idx_deals_checklist_deal (deal_id),
            KEY idx_deals_checklist_template (template_id),
            UNIQUE KEY idx_deal_template_unique (deal_id, template_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",
    
    'deals_checklist_items' => "
        CREATE TABLE deals_checklist_items (
            id varchar(36) NOT NULL,
            deal_id varchar(36) NOT NULL,
            item_id varchar(36) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            assigned_to varchar(36),
            completed_by varchar(36),
            date_completed datetime,
            notes text,
            created_by varchar(36),
            date_created datetime,
            modified_by varchar(36),
            date_modified datetime,
            deleted tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_deals_items_deal (deal_id),
            KEY idx_deals_items_item (item_id),
            KEY idx_deals_items_status (status),
            KEY idx_deals_items_assigned (assigned_to),
            KEY idx_deals_items_deleted (deleted),
            UNIQUE KEY idx_deal_item_unique (deal_id, item_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    "
];

// Create each missing table
foreach ($missingTables as $table) {
    if (isset($createStatements[$table])) {
        echo "{$colorYellow}Creating table '{$table}'...{$colorReset} ";
        
        try {
            $db->query($createStatements[$table]);
            echo "{$colorGreen}✓ Success{$colorReset}\n";
        } catch (Exception $e) {
            echo "{$colorRed}✗ Failed: " . $e->getMessage() . "{$colorReset}\n";
        }
    }
}

// Verify all tables now exist
echo "\n{$colorYellow}Verifying installation...{$colorReset}\n";
$allGood = true;
foreach ($tables as $table => $description) {
    $sql = "SHOW TABLES LIKE '{$table}'";
    $result = $db->query($sql);
    
    if ($db->fetchByAssoc($result)) {
        echo "{$colorGreen}✓{$colorReset} Table '{$table}' verified\n";
    } else {
        echo "{$colorRed}✗{$colorReset} Table '{$table}' still missing!\n";
        $allGood = false;
    }
}

if ($allGood) {
    echo "\n{$colorGreen}=== All checklist tables successfully installed! ==={$colorReset}\n";
} else {
    echo "\n{$colorRed}=== Some tables failed to install. Check error messages above. ==={$colorReset}\n";
}