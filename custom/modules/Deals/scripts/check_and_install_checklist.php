<?php
/**
 * Script to check and install checklist database tables
 * Run this to ensure all checklist tables are properly created
 */

// Define sugarEntry for SuiteCRM
if (!defined('sugarEntry')) define('sugarEntry', true);

// Determine the correct path to SuiteCRM root
$currentDir = dirname(__FILE__);
$suitecrmRoot = dirname(dirname(dirname(dirname($currentDir)))) . '/SuiteCRM';

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

echo "{$colorBlue}=== Checklist Database Installation Check ==={$colorReset}\n\n";

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

echo "\n";

// If tables are missing, offer to create them
if (!empty($missingTables)) {
    echo "{$colorYellow}Found " . count($missingTables) . " missing table(s).{$colorReset}\n";
    echo "Would you like to create the missing tables? (y/n): ";
    
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    $answer = trim(strtolower($line));
    fclose($handle);
    
    if ($answer === 'y' || $answer === 'yes') {
        echo "\n{$colorBlue}Creating missing tables...{$colorReset}\n\n";
        
        // Create checklist_templates table
        if (in_array('checklist_templates', $missingTables)) {
            $sql = "CREATE TABLE IF NOT EXISTS checklist_templates (
                id VARCHAR(36) NOT NULL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                category VARCHAR(100),
                is_active TINYINT(1) DEFAULT 1,
                template_version VARCHAR(20) DEFAULT '1.0',
                estimated_duration_days INT DEFAULT 0,
                created_by VARCHAR(36),
                date_entered DATETIME,
                modified_user_id VARCHAR(36),
                date_modified DATETIME,
                deleted TINYINT(1) DEFAULT 0,
                INDEX idx_template_active (is_active, deleted),
                INDEX idx_template_category (category, deleted),
                INDEX idx_template_name (name, deleted)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            if ($db->query($sql)) {
                echo "{$colorGreen}✓{$colorReset} Created table 'checklist_templates'\n";
            } else {
                echo "{$colorRed}✗{$colorReset} Failed to create table 'checklist_templates': " . $db->lastError() . "\n";
            }
        }
        
        // Create checklist_items table
        if (in_array('checklist_items', $missingTables)) {
            $sql = "CREATE TABLE IF NOT EXISTS checklist_items (
                id VARCHAR(36) NOT NULL PRIMARY KEY,
                template_id VARCHAR(36),
                name VARCHAR(255) NOT NULL,
                description TEXT,
                sort_order INT DEFAULT 0,
                is_required TINYINT(1) DEFAULT 1,
                estimated_hours DECIMAL(5,2) DEFAULT 0.00,
                requires_document TINYINT(1) DEFAULT 0,
                document_description TEXT,
                prerequisite_items TEXT,
                created_by VARCHAR(36),
                date_entered DATETIME,
                modified_user_id VARCHAR(36),
                date_modified DATETIME,
                deleted TINYINT(1) DEFAULT 0,
                INDEX idx_item_template (template_id, deleted),
                INDEX idx_item_sort (template_id, sort_order),
                INDEX idx_item_required (is_required, deleted)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            if ($db->query($sql)) {
                echo "{$colorGreen}✓{$colorReset} Created table 'checklist_items'\n";
            } else {
                echo "{$colorRed}✗{$colorReset} Failed to create table 'checklist_items': " . $db->lastError() . "\n";
            }
        }
        
        // Create deals_checklist_templates table
        if (in_array('deals_checklist_templates', $missingTables)) {
            $sql = "CREATE TABLE IF NOT EXISTS deals_checklist_templates (
                id VARCHAR(36) NOT NULL PRIMARY KEY,
                deal_id VARCHAR(36) NOT NULL,
                template_id VARCHAR(36) NOT NULL,
                applied_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                completion_percentage DECIMAL(5,2) DEFAULT 0.00,
                status ENUM('active', 'completed', 'paused', 'cancelled') DEFAULT 'active',
                due_date DATE,
                assigned_user_id VARCHAR(36),
                notes TEXT,
                date_modified DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                date_entered DATETIME DEFAULT CURRENT_TIMESTAMP,
                modified_user_id VARCHAR(36),
                created_by VARCHAR(36),
                deleted TINYINT(1) DEFAULT 0,
                UNIQUE KEY idx_deal_template (deal_id, template_id),
                INDEX idx_deal_id_del (deal_id, deleted),
                INDEX idx_template_id_del (template_id, deleted),
                INDEX idx_completion_status (completion_percentage, status),
                INDEX idx_due_date (due_date, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            if ($db->query($sql)) {
                echo "{$colorGreen}✓{$colorReset} Created table 'deals_checklist_templates'\n";
            } else {
                echo "{$colorRed}✗{$colorReset} Failed to create table 'deals_checklist_templates': " . $db->lastError() . "\n";
            }
        }
        
        // Create deals_checklist_items table
        if (in_array('deals_checklist_items', $missingTables)) {
            $sql = "CREATE TABLE IF NOT EXISTS deals_checklist_items (
                id VARCHAR(36) NOT NULL PRIMARY KEY,
                deal_id VARCHAR(36) NOT NULL,
                item_id VARCHAR(36) NOT NULL,
                template_instance_id VARCHAR(36),
                completion_status ENUM('pending', 'in_progress', 'completed', 'not_applicable', 'blocked') DEFAULT 'pending',
                completion_date DATETIME,
                due_date DATE,
                priority ENUM('high', 'medium', 'low') DEFAULT 'medium',
                notes TEXT,
                document_requested TINYINT(1) DEFAULT 0,
                document_received TINYINT(1) DEFAULT 0,
                assigned_user_id VARCHAR(36),
                estimated_hours DECIMAL(5,2) DEFAULT 0.00,
                actual_hours DECIMAL(5,2) DEFAULT 0.00,
                date_modified DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                date_entered DATETIME DEFAULT CURRENT_TIMESTAMP,
                modified_user_id VARCHAR(36),
                created_by VARCHAR(36),
                deleted TINYINT(1) DEFAULT 0,
                UNIQUE KEY idx_deal_item (deal_id, item_id),
                INDEX idx_deal_status (deal_id, completion_status, deleted),
                INDEX idx_template_instance (template_instance_id, deleted),
                INDEX idx_due_date_priority (due_date, priority),
                INDEX idx_completion_date (completion_date),
                INDEX idx_assigned_user (assigned_user_id, completion_status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            if ($db->query($sql)) {
                echo "{$colorGreen}✓{$colorReset} Created table 'deals_checklist_items'\n";
            } else {
                echo "{$colorRed}✗{$colorReset} Failed to create table 'deals_checklist_items': " . $db->lastError() . "\n";
            }
        }
        
        echo "\n{$colorBlue}Checking for default templates...{$colorReset}\n";
        
        // Check if we need to create default templates
        $sql = "SELECT COUNT(*) as count FROM checklist_templates WHERE deleted = 0";
        $result = $db->query($sql);
        $row = $db->fetchByAssoc($result);
        
        if ($row['count'] == 0) {
            echo "{$colorYellow}No templates found. Creating default templates...{$colorReset}\n";
            
            // Include the installer to create default templates
            $installerPath = dirname(__FILE__) . '/install_checklist_relationships.php';
            if (file_exists($installerPath)) {
                require_once($installerPath);
                $installer = new ChecklistRelationshipInstaller();
                
                // Create default templates using reflection to call private method
                $reflection = new ReflectionClass($installer);
                $method = $reflection->getMethod('createDefaultTemplates');
                $method->setAccessible(true);
                $method->invoke($installer);
                
                echo "{$colorGreen}✓{$colorReset} Default templates created\n";
            } else {
                echo "{$colorYellow}Note: Could not find installer to create default templates{$colorReset}\n";
            }
        } else {
            echo "{$colorGreen}✓{$colorReset} Found {$row['count']} existing template(s)\n";
        }
        
        echo "\n{$colorGreen}Installation complete!{$colorReset}\n";
        
        // Clear caches
        echo "\n{$colorBlue}Clearing caches...{$colorReset}\n";
        if (function_exists('sugar_cache_clear')) {
            sugar_cache_clear('app_list_strings');
            sugar_cache_clear('app_strings');
        }
        
        // Remove cached files
        $cacheFiles = [
            'cache/modules/Deals/Dealvardefs.php',
            'cache/modules/Opportunities/Opportunityvardefs.php',
        ];
        
        foreach ($cacheFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
                echo "{$colorGreen}✓{$colorReset} Cleared cache file: {$file}\n";
            }
        }
        
    } else {
        echo "\n{$colorYellow}Installation cancelled.{$colorReset}\n";
    }
} else {
    echo "{$colorGreen}All required tables are already present!{$colorReset}\n";
    
    // Check template count
    $sql = "SELECT COUNT(*) as count FROM checklist_templates WHERE deleted = 0";
    $result = $db->query($sql);
    $row = $db->fetchByAssoc($result);
    echo "\nFound {$row['count']} checklist template(s)\n";
    
    // Check deal checklist items
    $sql = "SELECT COUNT(DISTINCT deal_id) as deal_count, COUNT(*) as item_count 
            FROM deals_checklist_items WHERE deleted = 0";
    $result = $db->query($sql);
    $row = $db->fetchByAssoc($result);
    echo "Found {$row['item_count']} checklist items across {$row['deal_count']} deal(s)\n";
}

echo "\n{$colorBlue}=== Check Complete ==={$colorReset}\n";