#!/usr/bin/env php
<?php
/**
 * Simple test runner for Checklist tests
 * This can run without full SuiteCRM bootstrap
 */

// Define constants needed by tests
define('sugarEntry', true);

// Mock classes that tests depend on
class DBManager {
    public function quote($value) { return $value; }
    public function query($sql) { return true; }
    public function fetchByAssoc($result) { return []; }
}

class User {
    public $id;
    public $is_admin;
    public $full_name;
}

class SugarBean {
    public $id;
    public $name;
    public $deleted = 0;
    
    public function save() { return $this->id; }
    public function retrieve($id) { return $this; }
    public function mark_deleted($id) { return true; }
}

class ChecklistTemplates extends SugarBean {
    public $description;
    public $category;
    public $is_active;
    public $is_public;
    public $version;
    public $created_by;
}

class ChecklistItems extends SugarBean {
    public $title;
    public $description;
    public $category;
    public $priority;
    public $status;
    public $estimated_hours;
    public $actual_hours;
    public $checklist_id;
    public $assigned_to;
    public $due_date;
}

class DealChecklists extends SugarBean {
    public $deal_id;
    public $template_id;
    public $status;
    public $created_by;
    public $assigned_users = [];
    public $inherit_permissions;
    public $contains_pii;
}

class ChecklistSecurity {
    // Mock implementation
}

class ChecklistApi {
    // Mock implementation
}

class RestRequest {
    public function getParameter($name) { return null; }
    public function getBody() { return []; }
}

class RestResponse {
    public function setStatus($code) { return $this; }
    public function setBody($data) { return $this; }
}

// Set up autoloader for PHPUnit
require_once __DIR__ . '/../../../../../vendor/autoload.php';

// Color output helper
function colorize($text, $color) {
    $colors = [
        'green' => "\033[32m",
        'red' => "\033[31m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'reset' => "\033[0m"
    ];
    return $colors[$color] . $text . $colors['reset'];
}

// Run tests
echo colorize("\n=== Running Checklist Test Suite ===\n", 'blue');

$testFiles = [
    'ChecklistTemplateTest.php',
    'ChecklistItemTest.php', 
    'ChecklistSecurityTest.php',
    'ChecklistApiTest.php'
];

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

foreach ($testFiles as $testFile) {
    if (!file_exists(__DIR__ . '/' . $testFile)) {
        echo colorize("Skipping $testFile - file not found\n", 'yellow');
        continue;
    }
    
    echo colorize("\nRunning $testFile...\n", 'blue');
    
    // Include the test file
    require_once __DIR__ . '/' . $testFile;
    
    // Get the test class name
    $className = str_replace('.php', '', $testFile);
    
    if (!class_exists($className)) {
        echo colorize("Test class $className not found\n", 'red');
        continue;
    }
    
    // Create test instance
    $testInstance = new $className();
    
    // Get all test methods
    $methods = get_class_methods($className);
    $testMethods = array_filter($methods, function($method) {
        return strpos($method, 'test') === 0;
    });
    
    foreach ($testMethods as $method) {
        $totalTests++;
        echo "  - $method: ";
        
        try {
            // Use reflection to call protected methods
            $reflectionClass = new ReflectionClass($testInstance);
            
            // Set up
            $setUpMethod = $reflectionClass->getMethod('setUp');
            $setUpMethod->setAccessible(true);
            $setUpMethod->invoke($testInstance);
            
            // Run test
            $testMethod = $reflectionClass->getMethod($method);
            $testMethod->setAccessible(true);
            $testMethod->invoke($testInstance);
            
            // Tear down
            $tearDownMethod = $reflectionClass->getMethod('tearDown');
            $tearDownMethod->setAccessible(true);
            $tearDownMethod->invoke($testInstance);
            
            echo colorize("PASSED", 'green') . "\n";
            $passedTests++;
        } catch (Exception $e) {
            echo colorize("FAILED", 'red') . " - " . $e->getMessage() . "\n";
            $failedTests++;
        } catch (Error $e) {
            echo colorize("ERROR", 'red') . " - " . $e->getMessage() . "\n";
            $failedTests++;
        }
    }
}

// Summary
echo colorize("\n=== Test Summary ===\n", 'blue');
echo "Total tests: $totalTests\n";
echo colorize("Passed: $passedTests\n", 'green');
if ($failedTests > 0) {
    echo colorize("Failed: $failedTests\n", 'red');
}

$percentage = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 2) : 0;
echo "\nSuccess rate: $percentage%\n";

// Exit code
exit($failedTests > 0 ? 1 : 0);