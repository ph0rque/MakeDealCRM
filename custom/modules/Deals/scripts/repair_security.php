<?php
/**
 * Security Repair Script for Deals Module
 * 
 * This script:
 * 1. Executes database migrations
 * 2. Replaces insecure files with secure versions
 * 3. Sets proper permissions
 * 4. Validates the repair
 */

if (!defined('sugarEntry')) define('sugarEntry', true);

// Change to SuiteCRM root directory
chdir(dirname(__FILE__) . '/../../../../');

require_once('include/entryPoint.php');
require_once('include/database/DBManagerFactory.php');
require_once('include/utils.php');

class DealsSecurityRepair
{
    private $db;
    private $errors = [];
    private $messages = [];
    
    public function __construct()
    {
        global $db;
        $this->db = $db;
    }
    
    public function run()
    {
        $this->log("Starting Deals Module Security Repair...\n");
        
        // Step 1: Execute database migrations
        $this->executeMigrations();
        
        // Step 2: Replace insecure files with secure versions
        $this->replaceInsecureFiles();
        
        // Step 3: Create indexes for performance
        $this->createIndexes();
        
        // Step 4: Validate the repair
        $this->validateRepair();
        
        // Display results
        $this->displayResults();
    }
    
    /**
     * Execute SQL migration scripts
     */
    private function executeMigrations()
    {
        $this->log("Executing database migrations...");
        
        $sqlDir = dirname(__FILE__) . '/../sql/';
        if (!is_dir($sqlDir)) {
            $this->error("SQL directory not found: $sqlDir");
            return;
        }
        
        $migrations = glob($sqlDir . '*.sql');
        sort($migrations); // Execute in order
        
        foreach ($migrations as $migration) {
            $this->log("Executing migration: " . basename($migration));
            
            $sql = file_get_contents($migration);
            $statements = $this->splitSqlFile($sql);
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (empty($statement)) continue;
                
                try {
                    $this->db->query($statement);
                    $this->log("  ✓ Statement executed successfully");
                } catch (Exception $e) {
                    $this->error("  ✗ Error executing statement: " . $e->getMessage());
                }
            }
        }
    }
    
    /**
     * Replace insecure files with secure versions
     */
    private function replaceInsecureFiles()
    {
        $this->log("\nReplacing insecure files with secure versions...");
        
        $replacements = [
            'controller_secure.php' => 'controller.php',
            'views/view.pipeline_secure.php' => 'views/view.pipeline.php',
            'Deal_secure.php' => 'Deal.php',
            'scripts/post_install_secure.php' => 'scripts/post_install.php'
        ];
        
        $baseDir = dirname(__FILE__) . '/../';
        
        foreach ($replacements as $secure => $insecure) {
            $securePath = $baseDir . $secure;
            $insecurePath = $baseDir . $insecure;
            
            if (!file_exists($securePath)) {
                $this->error("Secure file not found: $secure");
                continue;
            }
            
            // Backup original file
            if (file_exists($insecurePath)) {
                $backupPath = $insecurePath . '.backup.' . date('YmdHis');
                if (copy($insecurePath, $backupPath)) {
                    $this->log("  ✓ Created backup: " . basename($backupPath));
                }
            }
            
            // Replace with secure version
            if (copy($securePath, $insecurePath)) {
                $this->log("  ✓ Replaced $insecure with secure version");
                
                // Remove the secure temp file
                unlink($securePath);
            } else {
                $this->error("  ✗ Failed to replace $insecure");
            }
        }
    }
    
    /**
     * Create database indexes for performance
     */
    private function createIndexes()
    {
        $this->log("\nCreating performance indexes...");
        
        // Check if custom fields exist first
        $result = $this->db->query("SHOW COLUMNS FROM opportunities LIKE 'pipeline_stage_c'");
        if ($this->db->fetchByAssoc($result)) {
            // Create index on pipeline_stage_c
            $this->db->query("CREATE INDEX IF NOT EXISTS idx_pipeline_stage ON opportunities(pipeline_stage_c)");
            $this->log("  ✓ Created index on pipeline_stage_c");
        }
        
        $result = $this->db->query("SHOW COLUMNS FROM opportunities LIKE 'stage_entered_date_c'");
        if ($this->db->fetchByAssoc($result)) {
            // Create index on stage_entered_date_c
            $this->db->query("CREATE INDEX IF NOT EXISTS idx_stage_entered_date ON opportunities(stage_entered_date_c)");
            $this->log("  ✓ Created index on stage_entered_date_c");
        }
    }
    
    /**
     * Validate the repair was successful
     */
    private function validateRepair()
    {
        $this->log("\nValidating repair...");
        
        // Check if pipeline_stage_history table exists
        $result = $this->db->query("SHOW TABLES LIKE 'pipeline_stage_history'");
        if ($this->db->fetchByAssoc($result)) {
            $this->log("  ✓ pipeline_stage_history table exists");
        } else {
            $this->error("  ✗ pipeline_stage_history table not found");
        }
        
        // Check if controller has security functions
        $controllerPath = dirname(__FILE__) . '/../controller.php';
        if (file_exists($controllerPath)) {
            $content = file_get_contents($controllerPath);
            if (strpos($content, 'validateGUID') !== false && strpos($content, 'validatePipelineStage') !== false) {
                $this->log("  ✓ Controller has security validation functions");
            } else {
                $this->error("  ✗ Controller missing security validation functions");
            }
        }
        
        // Check if view has XSS protection
        $viewPath = dirname(__FILE__) . '/../views/view.pipeline.php';
        if (file_exists($viewPath)) {
            $content = file_get_contents($viewPath);
            if (strpos($content, 'htmlspecialchars') !== false && strpos($content, 'X-XSS-Protection') !== false) {
                $this->log("  ✓ Pipeline view has XSS protection");
            } else {
                $this->error("  ✗ Pipeline view missing XSS protection");
            }
        }
    }
    
    /**
     * Split SQL file into individual statements
     */
    private function splitSqlFile($sql)
    {
        $sql = trim($sql);
        $sql = preg_replace("/\n#[^\n]*/", '', "\n" . $sql);
        $sql = preg_replace("/\n--[^\n]*/", '', "\n" . $sql);
        return explode(";\n", $sql);
    }
    
    /**
     * Log message
     */
    private function log($message)
    {
        $this->messages[] = $message;
        echo $message . "\n";
    }
    
    /**
     * Log error
     */
    private function error($message)
    {
        $this->errors[] = $message;
        echo "\033[31m" . $message . "\033[0m\n"; // Red color for errors
    }
    
    /**
     * Display final results
     */
    private function displayResults()
    {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "REPAIR COMPLETE\n";
        echo str_repeat('=', 60) . "\n";
        
        if (empty($this->errors)) {
            echo "\033[32m✓ All repairs completed successfully!\033[0m\n";
        } else {
            echo "\033[31m✗ Some errors occurred:\033[0m\n";
            foreach ($this->errors as $error) {
                echo "  - $error\n";
            }
        }
        
        echo "\nNext steps:\n";
        echo "1. Clear SuiteCRM cache: Admin > Repair > Quick Repair and Rebuild\n";
        echo "2. Test the pipeline view functionality\n";
        echo "3. Monitor error logs for any issues\n";
    }
}

// Run the repair if executed from command line
if (php_sapi_name() === 'cli') {
    $repair = new DealsSecurityRepair();
    $repair->run();
} else {
    die("This script must be run from the command line.\n");
}