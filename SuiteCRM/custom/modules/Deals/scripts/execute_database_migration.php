<?php
/**
 * Database Migration Executor for Deals Module
 * Purpose: Execute comprehensive database schema changes with proper error handling
 * Agent: Database Migration Specialist
 * Date: 2025-07-24
 */

if (!defined('sugarEntry')) define('sugarEntry', true);

// Include SuiteCRM bootstrap
require_once('config.php');
require_once('include/utils.php');
require_once('include/database/DBManagerFactory.php');

class DealsDatabaseMigration {
    private $db;
    private $migrationLog = [];
    private $rollbackLog = [];
    
    public function __construct() {
        $this->db = DBManagerFactory::getInstance();
        $this->log("Database Migration Executor initialized");
    }
    
    public function execute() {
        try {
            $this->log("Starting Deals Module Database Migration");
            
            // Read the migration SQL file
            $migrationFile = dirname(__FILE__) . '/deals_database_migration.sql';
            if (!file_exists($migrationFile)) {
                throw new Exception("Migration file not found: {$migrationFile}");
            }
            
            $sql = file_get_contents($migrationFile);
            $this->log("Loaded migration SQL file: " . strlen($sql) . " characters");
            
            // Split SQL into individual statements
            $statements = $this->parseSQLStatements($sql);
            $this->log("Parsed " . count($statements) . " SQL statements");
            
            // Execute each statement with error handling
            $successCount = 0;
            $errorCount = 0;
            
            foreach ($statements as $index => $statement) {
                $statement = trim($statement);
                if (empty($statement) || $this->isComment($statement)) {
                    continue;
                }
                
                try {
                    $this->log("Executing statement " . ($index + 1) . ": " . substr($statement, 0, 100) . "...");
                    
                    // Execute the statement
                    $result = $this->db->query($statement);
                    
                    if ($result === false) {
                        $error = $this->db->lastError();
                        $this->log("ERROR in statement " . ($index + 1) . ": " . $error, 'ERROR');
                        $errorCount++;
                        
                        // Check if it's a benign error (like column already exists)
                        if ($this->isBenignError($error)) {
                            $this->log("Benign error - continuing migration", 'WARN');
                            continue;
                        } else {
                            throw new Exception("Critical error: {$error}");
                        }
                    } else {
                        $this->log("Statement " . ($index + 1) . " executed successfully");
                        $successCount++;
                    }
                    
                } catch (Exception $e) {
                    $this->log("Exception in statement " . ($index + 1) . ": " . $e->getMessage(), 'ERROR');
                    $errorCount++;
                    
                    // Decide whether to continue or abort
                    if ($this->isCriticalStatement($statement)) {
                        throw $e;
                    }
                }
            }
            
            // Verify migration results
            $this->verifyMigration();
            
            $this->log("Migration completed - Success: {$successCount}, Errors: {$errorCount}");
            
            // Generate migration report
            $this->generateMigrationReport();
            
        } catch (Exception $e) {
            $this->log("CRITICAL ERROR: " . $e->getMessage(), 'ERROR');
            $this->generateErrorReport($e);
            throw $e;
        }
    }
    
    private function parseSQLStatements($sql) {
        // Remove comments and split by semicolon
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        
        // Split by semicolon but preserve semicolons in quoted strings
        $statements = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = '';
        
        for ($i = 0; $i < strlen($sql); $i++) {
            $char = $sql[$i];
            
            if (!$inQuotes && ($char === '"' || $char === "'")) {
                $inQuotes = true;
                $quoteChar = $char;
            } elseif ($inQuotes && $char === $quoteChar) {
                $inQuotes = false;
                $quoteChar = '';
            } elseif (!$inQuotes && $char === ';') {
                $statements[] = trim($current);
                $current = '';
                continue;
            }
            
            $current .= $char;
        }
        
        if (!empty(trim($current))) {
            $statements[] = trim($current);
        }
        
        return array_filter($statements, function($stmt) {
            return !empty(trim($stmt));
        });
    }
    
    private function isComment($statement) {
        $statement = trim($statement);
        return strpos($statement, '--') === 0 || 
               strpos($statement, '/*') === 0 ||
               strtolower(substr($statement, 0, 6)) === 'select' && 
               strpos(strtolower($statement), 'as message') !== false;
    }
    
    private function isBenignError($error) {
        $benignErrors = [
            'Duplicate column name',
            'Duplicate key name',
            'Table already exists',
            'already exists',
            'Duplicate entry',
            'Column already exists'
        ];
        
        foreach ($benignErrors as $benign) {
            if (stripos($error, $benign) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private function isCriticalStatement($statement) {
        $criticalKeywords = [
            'DROP TABLE',
            'DELETE FROM',
            'TRUNCATE',
            'ALTER TABLE ... DROP'
        ];
        
        $upperStatement = strtoupper($statement);
        foreach ($criticalKeywords as $keyword) {
            if (strpos($upperStatement, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private function verifyMigration() {
        $this->log("Verifying migration results...");
        
        $tables = [
            'pipeline_stages',
            'deal_stage_transitions', 
            'pipeline_stage_history',
            'checklist_templates',
            'checklist_items',
            'deals_checklist_templates',
            'deals_checklist_items',
            'task_generation_requests',
            'generated_tasks',
            'file_requests',
            'file_uploads',
            'email_logs',
            'template_versions',
            'contact_roles',
            'deals_contacts_relationships',
            'communication_history'
        ];
        
        $existingTables = [];
        $missingTables = [];
        
        foreach ($tables as $table) {
            if ($this->tableExists($table)) {
                $existingTables[] = $table;
                $count = $this->getTableRowCount($table);
                $this->log("✓ Table {$table} exists with {$count} rows");
            } else {
                $missingTables[] = $table;
                $this->log("✗ Table {$table} is missing", 'ERROR');
            }
        }
        
        // Verify key columns in opportunities table
        $opportunityColumns = [
            'pipeline_stage_c',
            'stage_entered_date_c',
            'checklist_completion_c',
            'active_checklists_count_c'
        ];
        
        foreach ($opportunityColumns as $column) {
            if ($this->columnExists('opportunities', $column)) {
                $this->log("✓ Column opportunities.{$column} exists");
            } else {
                $this->log("✗ Column opportunities.{$column} is missing", 'ERROR');
            }
        }
        
        // Verify contacts table columns
        $contactColumns = [
            'stakeholder_role_c',
            'last_contact_date_c',
            'is_key_stakeholder_c'
        ];
        
        foreach ($contactColumns as $column) {
            if ($this->columnExists('contacts', $column)) {
                $this->log("✓ Column contacts.{$column} exists");
            } else {
                $this->log("✗ Column contacts.{$column} is missing", 'ERROR');
            }
        }
        
        $this->log("Migration verification completed");
        $this->log("Tables created: " . count($existingTables) . "/" . count($tables));
        
        if (!empty($missingTables)) {
            throw new Exception("Migration incomplete - missing tables: " . implode(', ', $missingTables));
        }
    }
    
    private function tableExists($tableName) {
        try {
            $result = $this->db->query("SHOW TABLES LIKE '{$tableName}'");
            return $this->db->getRowCount($result) > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function columnExists($tableName, $columnName) {
        try {
            $result = $this->db->query("SHOW COLUMNS FROM {$tableName} LIKE '{$columnName}'");
            return $this->db->getRowCount($result) > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function getTableRowCount($tableName) {
        try {
            $result = $this->db->query("SELECT COUNT(*) as count FROM {$tableName}");
            $row = $this->db->fetchByAssoc($result);
            return $row['count'] ?? 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    private function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$level}] {$message}";
        $this->migrationLog[] = $logEntry;
        
        // Also output to console
        echo $logEntry . "\n";
    }
    
    private function generateMigrationReport() {
        $reportFile = dirname(__FILE__) . '/migration_report_' . date('Y-m-d_H-i-s') . '.log';
        $report = implode("\n", $this->migrationLog);
        file_put_contents($reportFile, $report);
        $this->log("Migration report saved to: {$reportFile}");
    }
    
    private function generateErrorReport($exception) {
        $errorFile = dirname(__FILE__) . '/migration_error_' . date('Y-m-d_H-i-s') . '.log';
        $errorReport = "MIGRATION ERROR REPORT\n";
        $errorReport .= "=====================\n\n";
        $errorReport .= "Error: " . $exception->getMessage() . "\n";
        $errorReport .= "File: " . $exception->getFile() . "\n";
        $errorReport .= "Line: " . $exception->getLine() . "\n\n";
        $errorReport .= "Stack Trace:\n" . $exception->getTraceAsString() . "\n\n";
        $errorReport .= "Migration Log:\n" . implode("\n", $this->migrationLog);
        
        file_put_contents($errorFile, $errorReport);
        $this->log("Error report saved to: {$errorFile}");
    }
    
    public function createRollbackScript() {
        $rollbackFile = dirname(__FILE__) . '/rollback_migration.sql';
        $rollbackSQL = "-- Rollback script for Deals Module Database Migration\n";
        $rollbackSQL .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        
        $rollbackSQL .= "START TRANSACTION;\n\n";
        
        // Drop tables in reverse dependency order
        $tablesToDrop = [
            'template_version_comparisons',
            'template_migrations',
            'template_versions',
            'email_logs',
            'file_uploads', 
            'file_requests',
            'generated_tasks',
            'task_generation_requests',
            'deals_checklist_items',
            'deals_checklist_templates',
            'checklist_items',
            'checklist_templates_audit',
            'deals_checklist_templates_audit',
            'checklist_templates',
            'communication_history',
            'deals_contacts_relationships',
            'contact_roles',
            'pipeline_stage_history',
            'deal_stage_transitions',
            'pipeline_stages'
        ];
        
        foreach ($tablesToDrop as $table) {
            $rollbackSQL .= "DROP TABLE IF EXISTS {$table};\n";
        }
        
        $rollbackSQL .= "\n-- Remove columns from opportunities table\n";
        $columnsToRemove = [
            'pipeline_stage_c',
            'stage_entered_date_c', 
            'time_in_stage',
            'wip_position',
            'is_archived',
            'last_stage_update',
            'expected_close_date_c',
            'deal_source_c',
            'pipeline_notes_c',
            'days_in_stage_c',
            'checklist_completion_c',
            'active_checklists_count_c',
            'overdue_checklist_items_c'
        ];
        
        foreach ($columnsToRemove as $column) {
            $rollbackSQL .= "ALTER TABLE opportunities DROP COLUMN IF EXISTS {$column};\n";
        }
        
        $rollbackSQL .= "\n-- Remove columns from contacts table\n";
        $contactColumnsToRemove = [
            'stakeholder_role_c',
            'relationship_strength_c',
            'last_contact_date_c',
            'next_followup_date_c',
            'communication_frequency_c',
            'is_key_stakeholder_c'
        ];
        
        foreach ($contactColumnsToRemove as $column) {
            $rollbackSQL .= "ALTER TABLE contacts DROP COLUMN IF EXISTS {$column};\n";
        }
        
        $rollbackSQL .= "\nCOMMIT;\n";
        
        file_put_contents($rollbackFile, $rollbackSQL);
        $this->log("Rollback script created: {$rollbackFile}");
        
        return $rollbackFile;
    }
}

// Execute migration if run directly
if (basename($_SERVER['PHP_SELF']) === 'execute_database_migration.php') {
    try {
        echo "===================================\n";
        echo "Deals Module Database Migration\n";
        echo "===================================\n\n";
        
        $migration = new DealsDatabaseMigration();
        
        // Create rollback script first
        $rollbackFile = $migration->createRollbackScript();
        echo "Rollback script created: {$rollbackFile}\n\n";
        
        // Execute migration
        $migration->execute();
        
        echo "\n===================================\n";
        echo "Migration completed successfully!\n";
        echo "===================================\n";
        
    } catch (Exception $e) {
        echo "\n===================================\n";
        echo "Migration failed!\n";
        echo "Error: " . $e->getMessage() . "\n";
        echo "===================================\n";
        exit(1);
    }
}
?>