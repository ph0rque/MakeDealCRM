<?php
/**
 * Pre-Migration Validation Script
 * Task 1.15 - Validate production readiness before executing migrations
 * 
 * This script performs comprehensive validation checks to ensure the system
 * is ready for database migrations in production.
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

class MigrationReadinessValidator {
    private $db;
    private $checks = [];
    private $warnings = [];
    private $errors = [];
    
    public function __construct() {
        global $db;
        $this->db = $db;
        echo "🔍 Migration Readiness Validation\n";
        echo "=================================\n\n";
    }
    
    public function runAllChecks() {
        $this->checkDatabaseConnection();
        $this->checkDatabasePermissions();
        $this->checkExistingTables();
        $this->checkBackupCapability();
        $this->checkDiskSpace();
        $this->checkPHPConfiguration();
        $this->checkSuiteCRMVersion();
        $this->checkMigrationFiles();
        
        $this->displayResults();
        
        return count($this->errors) === 0;
    }
    
    private function checkDatabaseConnection() {
        echo "🔌 Testing database connection...\n";
        
        try {
            // Test basic connection
            $result = $this->db->query("SELECT 1 as test");
            if ($result) {
                $this->addCheck('db_connection', true, 'Database connection successful');
                
                // Get database version
                $version_result = $this->db->query("SELECT VERSION() as version");
                $version_row = $this->db->fetchByAssoc($version_result);
                $this->addCheck('db_version', true, "Database version: " . $version_row['version']);
                
                // Test character set
                $charset_result = $this->db->query("SHOW VARIABLES LIKE 'character_set_database'");
                $charset_row = $this->db->fetchByAssoc($charset_result);
                $charset = $charset_row['Value'];
                
                if (in_array($charset, ['utf8mb4', 'utf8'])) {
                    $this->addCheck('db_charset', true, "Database charset: {$charset}");
                } else {
                    $this->addCheck('db_charset', false, "Database charset {$charset} may cause issues. Recommend utf8mb4");
                }
                
            } else {
                $this->addCheck('db_connection', false, 'Database connection failed');
            }
        } catch (Exception $e) {
            $this->addCheck('db_connection', false, 'Database connection error: ' . $e->getMessage());
        }
    }
    
    private function checkDatabasePermissions() {
        echo "🔐 Checking database permissions...\n";
        
        $permissions_to_check = [
            'CREATE' => 'CREATE TABLE test_permissions_temp (id INT)',
            'ALTER' => 'ALTER TABLE test_permissions_temp ADD COLUMN test_col VARCHAR(50)',
            'INDEX' => 'CREATE INDEX idx_test ON test_permissions_temp (id)',
            'DROP' => 'DROP TABLE test_permissions_temp'
        ];
        
        foreach ($permissions_to_check as $permission => $test_sql) {
            try {
                $this->db->query($test_sql);
                $this->addCheck("perm_{$permission}", true, "{$permission} permission available");
            } catch (Exception $e) {
                $this->addCheck("perm_{$permission}", false, "{$permission} permission failed: " . $e->getMessage());
            }
        }
        
        // Clean up test table if it exists
        try {
            $this->db->query('DROP TABLE IF EXISTS test_permissions_temp');
        } catch (Exception $e) {
            // Ignore cleanup errors
        }
    }
    
    private function checkExistingTables() {
        echo "📋 Checking existing table structure...\n";
        
        // Check core SuiteCRM tables
        $required_tables = ['opportunities', 'users', 'accounts', 'contacts'];
        
        foreach ($required_tables as $table) {
            if ($this->db->tableExists($table)) {
                $this->addCheck("table_{$table}", true, "Core table {$table} exists");
                
                // Check record count for opportunities
                if ($table === 'opportunities') {
                    $count_result = $this->db->query("SELECT COUNT(*) as count FROM {$table}");
                    $count_row = $this->db->fetchByAssoc($count_result);
                    $record_count = $count_row['count'];
                    
                    $this->addCheck('opportunities_data', true, "Opportunities table has {$record_count} records");
                    
                    if ($record_count > 10000) {
                        $this->addWarning("Large opportunities table ({$record_count} records) - migration may take longer");
                    }
                }
            } else {
                $this->addCheck("table_{$table}", false, "Required table {$table} is missing");
            }
        }
        
        // Check for existing pipeline tables
        $pipeline_tables = ['pipeline_stages', 'deal_stage_transitions', 'pipeline_stage_history'];
        
        foreach ($pipeline_tables as $table) {
            if ($this->db->tableExists($table)) {
                $this->addWarning("Pipeline table {$table} already exists - will be handled by migration script");
            } else {
                $this->addCheck("new_table_{$table}", true, "Pipeline table {$table} ready for creation");
            }
        }
    }
    
    private function checkBackupCapability() {
        echo "💾 Checking backup capabilities...\n";
        
        // Check if mysqldump is available
        $mysqldump_check = shell_exec('which mysqldump 2>/dev/null');
        if ($mysqldump_check) {
            $this->addCheck('mysqldump', true, 'mysqldump available for backups');
        } else {
            $this->addCheck('mysqldump', false, 'mysqldump not found - manual backup recommended before migration');
        }
        
        // Check database size
        try {
            $size_query = "
                SELECT 
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS db_size_mb
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
            ";
            $size_result = $this->db->query($size_query);
            $size_row = $this->db->fetchByAssoc($size_result);
            $db_size = $size_row['db_size_mb'];
            
            $this->addCheck('db_size', true, "Database size: {$db_size} MB");
            
            if ($db_size > 1000) {
                $this->addWarning("Large database ({$db_size} MB) - ensure adequate backup storage");
            }
        } catch (Exception $e) {
            $this->addCheck('db_size', false, 'Could not determine database size: ' . $e->getMessage());
        }
    }
    
    private function checkDiskSpace() {
        echo "💽 Checking disk space...\n";
        
        $suitecrm_path = getcwd();
        $free_bytes = disk_free_space($suitecrm_path);
        $total_bytes = disk_total_space($suitecrm_path);
        
        if ($free_bytes !== false && $total_bytes !== false) {
            $free_mb = round($free_bytes / 1024 / 1024, 2);
            $total_mb = round($total_bytes / 1024 / 1024, 2);
            $used_percent = round(($total_bytes - $free_bytes) / $total_bytes * 100, 1);
            
            $this->addCheck('disk_space', true, "Free disk space: {$free_mb} MB ({$used_percent}% used)");
            
            if ($free_mb < 100) {
                $this->addError("Insufficient disk space ({$free_mb} MB free). At least 100 MB recommended.");
            } elseif ($free_mb < 500) {
                $this->addWarning("Low disk space ({$free_mb} MB free). Monitor during migration.");
            }
        } else {
            $this->addCheck('disk_space', false, 'Could not determine disk space');
        }
    }
    
    private function checkPHPConfiguration() {
        echo "🐘 Checking PHP configuration...\n";
        
        // Check PHP version
        $php_version = PHP_VERSION;
        $this->addCheck('php_version', true, "PHP version: {$php_version}");
        
        if (version_compare($php_version, '7.2', '<')) {
            $this->addError("PHP version {$php_version} is too old. PHP 7.2+ recommended.");
        }
        
        // Check memory limit
        $memory_limit = ini_get('memory_limit');
        $this->addCheck('php_memory', true, "PHP memory limit: {$memory_limit}");
        
        // Check max execution time
        $max_execution_time = ini_get('max_execution_time');
        $this->addCheck('php_exec_time', true, "Max execution time: {$max_execution_time} seconds");
        
        if ($max_execution_time > 0 && $max_execution_time < 300) {
            $this->addWarning("Max execution time ({$max_execution_time}s) may be too low for large migrations");
        }
        
        // Check required extensions
        $required_extensions = ['mysqli', 'pdo', 'json'];
        foreach ($required_extensions as $ext) {
            if (extension_loaded($ext)) {
                $this->addCheck("php_ext_{$ext}", true, "PHP extension {$ext} loaded");
            } else {
                $this->addCheck("php_ext_{$ext}", false, "Required PHP extension {$ext} not loaded");
            }
        }
    }
    
    private function checkSuiteCRMVersion() {
        echo "🍬 Checking SuiteCRM version...\n";
        
        if (defined('sugarVersion')) {
            $version = sugarVersion;
            $this->addCheck('suitecrm_version', true, "SuiteCRM version: {$version}");
        } else {
            $this->addCheck('suitecrm_version', false, 'Could not determine SuiteCRM version');
        }
        
        // Check if system is in maintenance mode
        global $sugar_config;
        if (isset($sugar_config['maintenance_mode']) && $sugar_config['maintenance_mode']) {
            $this->addWarning('System is in maintenance mode');
        } else {
            $this->addCheck('maintenance_mode', true, 'System not in maintenance mode');
        }
    }
    
    private function checkMigrationFiles() {
        echo "📁 Checking migration files...\n";
        
        $migration_files = [
            '../custom/database/migrations/001_create_pipeline_stages_table.sql',
            '../custom/database/migrations/002_create_deals_pipeline_tracking_table.sql', 
            '../custom/database/migrations/003_add_pipeline_stage_to_deals.sql',
            '../custom/modules/Deals/sql/001_create_pipeline_stage_history.sql',
            '../custom/modules/Pipelines/install/pipeline_tables.sql'
        ];
        
        foreach ($migration_files as $file) {
            if (file_exists($file)) {
                $size = filesize($file);
                $this->addCheck("file_" . basename($file), true, basename($file) . " exists ({$size} bytes)");
                
                // Check if file is readable
                if (!is_readable($file)) {
                    $this->addError("Migration file {$file} exists but is not readable");
                }
            } else {
                $this->addCheck("file_" . basename($file), false, "Migration file {$file} not found");
            }
        }
        
        // Check if migration executor exists
        $executor_file = 'custom/modules/Deals/scripts/execute_production_migrations.php';
        if (file_exists($executor_file)) {
            $this->addCheck('migration_executor', true, 'Migration executor script available');
        } else {
            $this->addCheck('migration_executor', false, 'Migration executor script not found');
        }
    }
    
    private function addCheck($name, $success, $message) {
        $this->checks[$name] = [
            'success' => $success,
            'message' => $message
        ];
        
        if (!$success) {
            $this->errors[] = $message;
        }
        
        echo ($success ? "✅" : "❌") . " {$message}\n";
    }
    
    private function addWarning($message) {
        $this->warnings[] = $message;
        echo "⚠️  {$message}\n";
    }
    
    private function addError($message) {
        $this->errors[] = $message;
        echo "🚨 {$message}\n";
    }
    
    private function displayResults() {
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "📊 VALIDATION SUMMARY\n";
        echo str_repeat("=", 50) . "\n";
        
        $total_checks = count($this->checks);
        $passed_checks = count(array_filter($this->checks, function($check) { return $check['success']; }));
        $failed_checks = $total_checks - $passed_checks;
        
        echo "Total checks: {$total_checks}\n";
        echo "✅ Passed: {$passed_checks}\n";
        echo "❌ Failed: {$failed_checks}\n";
        echo "⚠️  Warnings: " . count($this->warnings) . "\n";
        echo "🚨 Errors: " . count($this->errors) . "\n\n";
        
        if (count($this->errors) > 0) {
            echo "🚨 CRITICAL ISSUES THAT MUST BE RESOLVED:\n";
            foreach ($this->errors as $error) {
                echo "   • {$error}\n";
            }
            echo "\n";
        }
        
        if (count($this->warnings) > 0) {
            echo "⚠️  WARNINGS TO CONSIDER:\n";
            foreach ($this->warnings as $warning) {
                echo "   • {$warning}\n";
            }
            echo "\n";
        }
        
        if (count($this->errors) === 0) {
            echo "🎉 SYSTEM IS READY FOR MIGRATION!\n";
            echo "You can proceed with running the migration script.\n\n";
            echo "Recommended next steps:\n";
            echo "1. Create a database backup\n";
            echo "2. Put system in maintenance mode (optional)\n";
            echo "3. Run: php custom/modules/Deals/scripts/execute_production_migrations.php\n";
        } else {
            echo "❌ SYSTEM IS NOT READY FOR MIGRATION!\n";
            echo "Please resolve the critical issues above before proceeding.\n";
        }
    }
}

// Main execution
try {
    $validator = new MigrationReadinessValidator();
    $ready = $validator->runAllChecks();
    
    exit($ready ? 0 : 1);
    
} catch (Exception $e) {
    echo "\n💥 FATAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}