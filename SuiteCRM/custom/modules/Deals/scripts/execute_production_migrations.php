<?php
/**
 * Production Database Migration Executor
 * Task 1.15 - Execute Database Migrations for Production
 * 
 * This script safely executes all pending database migrations for the pipeline system
 * with proper error handling, rollback capabilities, and validation.
 */

// Prevent direct web access
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

// Include SuiteCRM framework
$GLOBALS['installing'] = true;
define('sugarEntry', true);

// Change to SuiteCRM directory for proper initialization
chdir(dirname(__FILE__) . '/../../../../SuiteCRM');
require_once('include/entryPoint.php');

class ProductionMigrationExecutor {
    private $db;
    private $migrations = [];
    private $executed_migrations = [];
    private $rollback_scripts = [];
    private $log_file;
    
    public function __construct() {
        global $db;
        $this->db = $db;
        $this->log_file = 'custom/modules/Deals/logs/migration_' . date('Y-m-d_H-i-s') . '.log';
        
        // Ensure log directory exists
        if (!is_dir('custom/modules/Deals/logs')) {
            mkdir('custom/modules/Deals/logs', 0755, true);
        }
        
        $this->loadMigrations();
        $this->log("=== Production Migration Execution Started ===");
    }
    
    private function loadMigrations() {
        // Define migration files in execution order
        $this->migrations = [
            '001_pipeline_stages' => [
                'file' => '../custom/database/migrations/001_create_pipeline_stages_table.sql',
                'description' => 'Create pipeline stages configuration table',
                'rollback' => 'DROP TABLE IF EXISTS pipeline_stages;'
            ],
            '002_deal_transitions' => [
                'file' => '../custom/database/migrations/002_create_deals_pipeline_tracking_table.sql',
                'description' => 'Create deal stage transitions tracking tables',
                'rollback' => 'DROP TABLE IF EXISTS deal_stage_transitions; DROP TABLE IF EXISTS pipeline_stage_history;'
            ],
            '003_opportunities_fields' => [
                'file' => '../custom/database/migrations/003_add_pipeline_stage_to_deals.sql',
                'description' => 'Add pipeline tracking fields to opportunities table',
                'rollback' => 'ALTER TABLE opportunities DROP COLUMN IF EXISTS pipeline_stage_c, DROP COLUMN IF EXISTS stage_entered_date_c, DROP COLUMN IF EXISTS time_in_stage, DROP COLUMN IF EXISTS wip_position, DROP COLUMN IF EXISTS is_archived, DROP COLUMN IF EXISTS last_stage_update;'
            ],
            '004_pipeline_history' => [
                'file' => '../custom/modules/Deals/sql/001_create_pipeline_stage_history.sql',
                'description' => 'Create enhanced pipeline stage history with foreign keys',
                'rollback' => 'DROP TABLE IF EXISTS pipeline_stage_history;'
            ],
            '005_comprehensive_pipeline' => [
                'file' => '../custom/modules/Pipelines/install/pipeline_tables.sql',
                'description' => 'Create comprehensive pipeline system tables',
                'rollback' => 'DROP TABLE IF EXISTS mdeal_pipeline_stages, mdeal_pipeline_transitions, mdeal_pipeline_wip_tracking, mdeal_pipeline_stage_metrics, mdeal_lead_conversions, mdeal_lead_scoring_history, mdeal_pipeline_automation_rules, mdeal_pipeline_automation_log, mdeal_pipeline_checklist_templates, mdeal_pipeline_checklist_progress, mdeal_pipeline_workflow_triggers, mdeal_pipeline_alerts, mdeal_pipeline_analytics; DROP VIEW IF EXISTS v_pipeline_summary, v_conversion_funnel, v_pipeline_performance; DROP PROCEDURE IF EXISTS sp_update_pipeline_analytics, sp_update_wip_tracking;'
            ]
        ];
        
        // Load executed migrations from database
        $this->loadExecutedMigrations();
    }
    
    private function loadExecutedMigrations() {
        // Create migrations table if it doesn't exist
        $create_migrations_table = "
            CREATE TABLE IF NOT EXISTS pipeline_migrations (
                id char(36) NOT NULL PRIMARY KEY,
                migration_name varchar(100) NOT NULL UNIQUE,
                executed_at datetime NOT NULL,
                execution_time_ms int DEFAULT NULL,
                status enum('success', 'failed', 'rolled_back') DEFAULT 'success',
                notes text,
                KEY idx_executed_at (executed_at),
                KEY idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        
        try {
            $this->db->query($create_migrations_table);
            $this->log("✓ Migrations tracking table ready");
        } catch (Exception $e) {
            $this->log("ERROR creating migrations table: " . $e->getMessage());
            throw $e;
        }
        
        // Load executed migrations
        $result = $this->db->query("SELECT migration_name FROM pipeline_migrations WHERE status = 'success'");
        while ($row = $this->db->fetchByAssoc($result)) {
            $this->executed_migrations[] = $row['migration_name'];
        }
        
        $this->log("Found " . count($this->executed_migrations) . " previously executed migrations");
    }
    
    public function executeMigrations() {
        $success_count = 0;
        $error_count = 0;
        
        foreach ($this->migrations as $migration_name => $migration_config) {
            if (in_array($migration_name, $this->executed_migrations)) {
                $this->log("⏭ Skipping {$migration_name} - already executed");
                continue;
            }
            
            $this->log("\n🔄 Executing migration: {$migration_name}");
            $this->log("   Description: {$migration_config['description']}");
            
            $start_time = microtime(true);
            
            try {
                // Validate file exists
                $file_path = $migration_config['file'];
                if (!file_exists($file_path)) {
                    throw new Exception("Migration file not found: {$file_path}");
                }
                
                // Read and execute SQL
                $sql_content = file_get_contents($file_path);
                if (!$sql_content) {
                    throw new Exception("Could not read migration file: {$file_path}");
                }
                
                // Execute migration with transaction
                $this->db->query('START TRANSACTION');
                
                $this->executeSQLStatements($sql_content);
                
                // Record successful migration
                $execution_time = round((microtime(true) - $start_time) * 1000);
                $this->recordMigration($migration_name, 'success', $execution_time);
                
                $this->db->query('COMMIT');
                
                $this->log("✅ Migration {$migration_name} completed successfully ({$execution_time}ms)");
                $success_count++;
                
                // Store rollback script
                $this->rollback_scripts[$migration_name] = $migration_config['rollback'];
                
            } catch (Exception $e) {
                $this->db->query('ROLLBACK');
                $execution_time = round((microtime(true) - $start_time) * 1000);
                $this->recordMigration($migration_name, 'failed', $execution_time, $e->getMessage());
                
                $this->log("❌ Migration {$migration_name} FAILED: " . $e->getMessage());
                $error_count++;
                
                // Stop on error for production safety
                break;
            }
        }
        
        $this->log("\n=== Migration Execution Summary ===");
        $this->log("✅ Successful migrations: {$success_count}");
        $this->log("❌ Failed migrations: {$error_count}");
        
        if ($error_count > 0) {
            $this->log("\n🚨 ERRORS OCCURRED - Check migration status and consider rollback if needed");
            return false;
        }
        
        $this->log("🎉 All migrations executed successfully!");
        return true;
    }
    
    private function executeSQLStatements($sql_content) {
        // Split SQL content into individual statements
        $statements = $this->parseSQLStatements($sql_content);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement) || substr($statement, 0, 2) === '--') {
                continue; // Skip comments and empty statements
            }
            
            $this->log("  📝 Executing: " . substr($statement, 0, 100) . "...");
            $result = $this->db->query($statement);
            
            if (!$result) {
                throw new Exception("SQL execution failed: " . $this->db->lastDbError());
            }
        }
    }
    
    private function parseSQLStatements($sql_content) {
        // Handle DELIMITER statements and procedures properly
        $statements = [];
        $current_statement = '';
        $in_delimiter_block = false;
        $current_delimiter = ';';
        
        $lines = explode("\n", $sql_content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip comments and empty lines
            if (empty($line) || substr($line, 0, 2) === '--') {
                continue;
            }
            
            // Handle DELIMITER statements
            if (stripos($line, 'DELIMITER') === 0) {
                if (!$in_delimiter_block) {
                    $current_delimiter = trim(substr($line, 9));
                    $in_delimiter_block = true;
                } else {
                    $current_delimiter = ';';
                    $in_delimiter_block = false;
                }
                continue;
            }
            
            $current_statement .= $line . "\n";
            
            // Check if statement is complete
            if (substr(rtrim($line), -strlen($current_delimiter)) === $current_delimiter) {
                // Remove the delimiter and add to statements
                $statement = substr($current_statement, 0, -strlen($current_delimiter) - 1);
                if (trim($statement)) {
                    $statements[] = trim($statement);
                }
                $current_statement = '';
            }
        }
        
        // Add any remaining statement
        if (trim($current_statement)) {
            $statements[] = trim($current_statement);
        }
        
        return $statements;
    }
    
    private function recordMigration($migration_name, $status, $execution_time = null, $notes = null) {
        $id = create_guid();
        $executed_at = date('Y-m-d H:i:s');
        
        $sql = "INSERT INTO pipeline_migrations (id, migration_name, executed_at, execution_time_ms, status, notes) 
                VALUES ('{$id}', '{$migration_name}', '{$executed_at}', {$execution_time}, '{$status}', " . 
                ($notes ? "'" . $this->db->quote($notes) . "'" : 'NULL') . ")";
        
        $this->db->query($sql);
    }
    
    public function validateMigrations() {
        $this->log("\n🔍 Validating migration results...");
        
        $validation_results = [];
        
        // Check if core tables exist
        $tables_to_check = [
            'pipeline_stages',
            'deal_stage_transitions', 
            'pipeline_stage_history',
            'mdeal_pipeline_stages',
            'mdeal_pipeline_transitions'
        ];
        
        foreach ($tables_to_check as $table) {
            $exists = $this->db->tableExists($table);
            $validation_results[$table] = $exists;
            $this->log($exists ? "✅ Table {$table} exists" : "❌ Table {$table} missing");
        }
        
        // Check if opportunity table has new columns
        $columns_to_check = [
            'opportunities.pipeline_stage_c',
            'opportunities.stage_entered_date_c',
            'opportunities.time_in_stage'
        ];
        
        foreach ($columns_to_check as $column) {
            list($table, $col) = explode('.', $column);
            $exists = $this->columnExists($table, $col);
            $validation_results[$column] = $exists;
            $this->log($exists ? "✅ Column {$column} exists" : "❌ Column {$column} missing");
        }
        
        // Test basic functionality
        try {
            $test_query = "SELECT COUNT(*) as count FROM pipeline_stages WHERE deleted = 0";
            $result = $this->db->query($test_query);
            $row = $this->db->fetchByAssoc($result);
            $stage_count = $row['count'];
            
            $this->log("✅ Pipeline stages query successful - found {$stage_count} stages");
            $validation_results['basic_query'] = true;
            
        } catch (Exception $e) {
            $this->log("❌ Basic query failed: " . $e->getMessage());
            $validation_results['basic_query'] = false;
        }
        
        $all_valid = !in_array(false, $validation_results);
        $this->log($all_valid ? "\n🎉 All validations passed!" : "\n🚨 Some validations failed!");
        
        return $all_valid;
    }
    
    private function columnExists($table, $column) {
        try {
            $result = $this->db->query("SHOW COLUMNS FROM {$table} LIKE '{$column}'");
            return $this->db->getRowCount($result) > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function generateRollbackScript() {
        $rollback_file = 'custom/modules/Deals/scripts/rollback_' . date('Y-m-d_H-i-s') . '.sql';
        
        $rollback_content = "-- ROLLBACK SCRIPT\n";
        $rollback_content .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $rollback_content .= "-- WARNING: This script will reverse all migrations. Use with caution!\n\n";
        
        foreach (array_reverse($this->rollback_scripts) as $migration_name => $rollback_sql) {
            $rollback_content .= "-- Rollback for: {$migration_name}\n";
            $rollback_content .= $rollback_sql . "\n\n";
        }
        
        file_put_contents($rollback_file, $rollback_content);
        $this->log("💾 Rollback script saved to: {$rollback_file}");
        
        return $rollback_file;
    }
    
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[{$timestamp}] {$message}\n";
        
        echo $log_message;
        file_put_contents($this->log_file, $log_message, FILE_APPEND | LOCK_EX);
    }
    
    public function getLogFile() {
        return $this->log_file;
    }
}

// Main execution
try {
    echo "🚀 Production Database Migration Executor\n";
    echo "=========================================\n\n";
    
    $migrator = new ProductionMigrationExecutor();
    
    // Execute migrations
    $success = $migrator->executeMigrations();
    
    if ($success) {
        // Validate results
        $migrator->validateMigrations();
        
        // Generate rollback script for safety
        $rollback_file = $migrator->generateRollbackScript();
        
        echo "\n✅ Migration execution completed successfully!\n";
        echo "📋 Log file: " . $migrator->getLogFile() . "\n";
        echo "🔄 Rollback script: " . $rollback_file . "\n";
        
        exit(0);
    } else {
        echo "\n❌ Migration execution failed!\n";
        echo "📋 Check log file: " . $migrator->getLogFile() . "\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "\n💥 FATAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}