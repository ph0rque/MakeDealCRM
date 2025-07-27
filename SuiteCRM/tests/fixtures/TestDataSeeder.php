<?php
/**
 * Test Data Seeder for MakeDealCRM
 * 
 * This script seeds the database with comprehensive test data
 * for E2E testing purposes
 * 
 * Usage: php TestDataSeeder.php [action] [scenario]
 * Actions: seed, cleanup, reset
 * Scenarios: default, pipeline, at_risk, financial, checklist, edge_cases, all
 * 
 * @package MakeDealCRM
 * @subpackage Tests
 */

if (!defined('sugarEntry')) define('sugarEntry', true);

require_once(dirname(__FILE__) . '/../../include/entryPoint.php');
require_once(dirname(__FILE__) . '/TestDataFactory.php');

class TestDataSeeder
{
    private $factory;
    private $configFile;
    private $scenarios = [
        'default', 'pipeline', 'at_risk', 'financial', 'checklist', 'edge_cases'
    ];
    
    public function __construct()
    {
        $this->factory = new TestDataFactory();
        $this->configFile = dirname(__FILE__) . '/data/test_data_config.json';
    }
    
    /**
     * Seed test data
     */
    public function seed($scenario = 'default')
    {
        echo "==========================================\n";
        echo "MakeDealCRM Test Data Seeder\n";
        echo "==========================================\n";
        echo "Seeding scenario: $scenario\n";
        echo "Start time: " . date('Y-m-d H:i:s') . "\n";
        echo "------------------------------------------\n";
        
        if ($scenario === 'all') {
            $this->seedAllScenarios();
        } else {
            $this->seedScenario($scenario);
        }
        
        // Export configuration
        $this->factory->exportConfiguration($this->configFile);
        
        // Display summary
        $this->displaySummary();
        
        echo "\nSeeding completed successfully!\n";
    }
    
    /**
     * Seed a specific scenario
     */
    private function seedScenario($scenario)
    {
        if (!in_array($scenario, $this->scenarios)) {
            throw new Exception("Unknown scenario: $scenario");
        }
        
        echo "\nCreating $scenario scenario...\n";
        
        $data = $this->factory->createFullTestScenario($scenario);
        
        // Display what was created
        foreach ($data as $type => $items) {
            if (is_array($items) && count($items) > 0) {
                echo "- Created " . count($items) . " $type\n";
            }
        }
    }
    
    /**
     * Seed all scenarios
     */
    private function seedAllScenarios()
    {
        foreach ($this->scenarios as $scenario) {
            $this->seedScenario($scenario);
            echo "\n";
        }
    }
    
    /**
     * Cleanup test data
     */
    public function cleanup()
    {
        echo "==========================================\n";
        echo "MakeDealCRM Test Data Cleanup\n";
        echo "==========================================\n";
        echo "Start time: " . date('Y-m-d H:i:s') . "\n";
        echo "------------------------------------------\n";
        
        // Load configuration if exists
        if (file_exists($this->configFile)) {
            $config = json_decode(file_get_contents($this->configFile), true);
            
            if (isset($config['records'])) {
                echo "Found " . count($config['records']) . " records to clean up.\n\n";
                
                // Recreate factory with same test run ID
                $this->factory = new TestDataFactory($config['test_run_id']);
                
                // Restore records list
                $reflection = new ReflectionClass($this->factory);
                $property = $reflection->getProperty('createdRecords');
                $property->setAccessible(true);
                $property->setValue($this->factory, $config['records']);
            }
        }
        
        $this->factory->cleanup();
        
        // Remove config file
        if (file_exists($this->configFile)) {
            unlink($this->configFile);
            echo "Removed configuration file.\n";
        }
        
        echo "\nCleanup completed successfully!\n";
    }
    
    /**
     * Reset - cleanup and reseed
     */
    public function reset($scenario = 'default')
    {
        echo "Resetting test data...\n\n";
        
        // Cleanup existing data
        $this->cleanup();
        
        echo "\n";
        
        // Seed new data
        $this->seed($scenario);
    }
    
    /**
     * Display summary of created data
     */
    private function displaySummary()
    {
        echo "\n------------------------------------------\n";
        echo "Summary of created test data:\n";
        echo "------------------------------------------\n";
        
        $summary = $this->factory->getSummary();
        $total = 0;
        
        foreach ($summary as $module => $count) {
            echo sprintf("%-20s: %d records\n", $module, $count);
            $total += $count;
        }
        
        echo "------------------------------------------\n";
        echo sprintf("%-20s: %d records\n", "TOTAL", $total);
        echo "------------------------------------------\n";
    }
    
    /**
     * List available scenarios
     */
    public function listScenarios()
    {
        echo "==========================================\n";
        echo "Available Test Scenarios\n";
        echo "==========================================\n\n";
        
        $scenarioDescriptions = [
            'default' => 'General test data with variety of records in all modules',
            'pipeline' => 'Deals distributed across pipeline stages for drag-drop testing',
            'at_risk' => 'Deals with various at-risk statuses (normal, warning, alert)',
            'financial' => 'Deals with different financial scenarios and metrics',
            'checklist' => 'Deals with checklist templates and items applied',
            'edge_cases' => 'Edge cases including zero values, special characters, etc.',
            'all' => 'Seed all scenarios (comprehensive test data)'
        ];
        
        foreach ($scenarioDescriptions as $scenario => $description) {
            echo sprintf("%-15s: %s\n", $scenario, $description);
        }
        
        echo "\n";
    }
}

// CLI execution
if (php_sapi_name() === 'cli') {
    try {
        $seeder = new TestDataSeeder();
        
        // Parse command line arguments
        $action = isset($argv[1]) ? $argv[1] : 'help';
        $scenario = isset($argv[2]) ? $argv[2] : 'default';
        
        switch ($action) {
            case 'seed':
                $seeder->seed($scenario);
                break;
                
            case 'cleanup':
                $seeder->cleanup();
                break;
                
            case 'reset':
                $seeder->reset($scenario);
                break;
                
            case 'list':
                $seeder->listScenarios();
                break;
                
            case 'help':
            default:
                echo "Usage: php TestDataSeeder.php [action] [scenario]\n\n";
                echo "Actions:\n";
                echo "  seed [scenario]    - Create test data for specified scenario\n";
                echo "  cleanup            - Remove all test data\n";
                echo "  reset [scenario]   - Cleanup and reseed with specified scenario\n";
                echo "  list               - List available scenarios\n";
                echo "  help               - Show this help message\n";
                echo "\nScenarios: default, pipeline, at_risk, financial, checklist, edge_cases, all\n";
                echo "\nExamples:\n";
                echo "  php TestDataSeeder.php seed default\n";
                echo "  php TestDataSeeder.php seed all\n";
                echo "  php TestDataSeeder.php cleanup\n";
                echo "  php TestDataSeeder.php reset pipeline\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}