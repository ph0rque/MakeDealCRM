<?php
/**
 * Test Helper for MakeDealCRM E2E Tests
 * 
 * Provides utility methods for test setup, teardown, and common operations
 * 
 * @package MakeDealCRM
 * @subpackage Tests
 */

if (!defined('sugarEntry')) define('sugarEntry', true);

require_once(dirname(__FILE__) . '/../../include/entryPoint.php');
require_once(dirname(__FILE__) . '/TestDataFactory.php');

class TestHelper
{
    private static $instance = null;
    private $factory;
    private $currentUser;
    private $testPrefix = 'TEST_';
    private $createdUsers = [];
    
    private function __construct()
    {
        $this->factory = new TestDataFactory();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Setup test environment
     */
    public function setUp($testName = 'E2E Test')
    {
        global $current_user, $db;
        
        echo "Setting up test environment for: $testName\n";
        
        // Set test user
        $current_user = BeanFactory::getBean('Users', '1');
        $this->currentUser = $current_user;
        
        // Clear cache
        $this->clearCache();
        
        // Initialize database transaction (if supported)
        if (method_exists($db, 'beginTransaction')) {
            $db->beginTransaction();
        }
        
        return $this;
    }
    
    /**
     * Tear down test environment
     */
    public function tearDown()
    {
        global $db;
        
        echo "Tearing down test environment...\n";
        
        // Cleanup test data
        $this->factory->cleanup();
        
        // Cleanup created users
        $this->cleanupTestUsers();
        
        // Rollback transaction (if supported)
        if (method_exists($db, 'rollback')) {
            $db->rollback();
        }
        
        // Clear cache
        $this->clearCache();
        
        echo "Teardown completed.\n";
    }
    
    /**
     * Create test user with specific role
     */
    public function createTestUser($role = 'Administrator', $data = [])
    {
        $user = BeanFactory::newBean('Users');
        
        $defaults = [
            'user_name' => $this->testPrefix . 'user_' . uniqid(),
            'first_name' => 'Test',
            'last_name' => 'User',
            'status' => 'Active',
            'is_admin' => ($role === 'Administrator') ? 1 : 0,
            'email1' => 'testuser_' . uniqid() . '@example.com',
            'employee_status' => 'Active',
            'user_hash' => User::getPasswordHash('testpass123'),
            'authenticate_id' => uniqid(),
            'sugar_login' => 1
        ];
        
        foreach (array_merge($defaults, $data) as $field => $value) {
            $user->$field = $value;
        }
        
        $user->save();
        
        // Assign role
        if ($role !== 'Administrator') {
            $this->assignUserRole($user, $role);
        }
        
        $this->createdUsers[] = $user->id;
        
        return $user;
    }
    
    /**
     * Assign role to user
     */
    private function assignUserRole($user, $roleName)
    {
        global $db;
        
        // Find role by name
        $query = "SELECT id FROM acl_roles WHERE name = ? AND deleted = 0 LIMIT 1";
        $result = $db->fetchOne($query, [$roleName]);
        
        if ($result && isset($result['id'])) {
            // Create role relationship
            $roleId = $result['id'];
            $relationId = create_guid();
            
            $db->query("INSERT INTO acl_roles_users (id, role_id, user_id, deleted) 
                       VALUES ('$relationId', '$roleId', '{$user->id}', 0)");
        }
    }
    
    /**
     * Login as test user
     */
    public function loginAs($user)
    {
        global $current_user;
        
        $current_user = $user;
        $_SESSION['authenticated_user_id'] = $user->id;
        
        return $this;
    }
    
    /**
     * Clear SuiteCRM cache
     */
    public function clearCache()
    {
        // Clear module cache
        if (function_exists('sugar_cache_clear')) {
            sugar_cache_clear('');
        }
        
        // Clear vardefs cache
        VardefManager::clearVardef();
        
        // Clear language cache
        LanguageManager::clearLanguageCache();
        
        // Clear theme cache
        SugarThemeRegistry::clearAllCaches();
        
        return $this;
    }
    
    /**
     * Wait for async operations
     */
    public function waitForAsync($seconds = 2)
    {
        sleep($seconds);
        return $this;
    }
    
    /**
     * Assert record exists
     */
    public function assertRecordExists($module, $id)
    {
        $bean = BeanFactory::getBean($module, $id);
        
        if (!$bean || empty($bean->id)) {
            throw new Exception("Record not found: $module/$id");
        }
        
        return $bean;
    }
    
    /**
     * Assert record field value
     */
    public function assertFieldValue($bean, $field, $expectedValue)
    {
        if (!isset($bean->$field)) {
            throw new Exception("Field '$field' not found on bean");
        }
        
        if ($bean->$field != $expectedValue) {
            throw new Exception("Field '$field' value mismatch. Expected: '$expectedValue', Got: '{$bean->$field}'");
        }
        
        return $this;
    }
    
    /**
     * Assert relationship exists
     */
    public function assertRelationshipExists($bean, $linkName, $relatedId)
    {
        if (!$bean->load_relationship($linkName)) {
            throw new Exception("Relationship '$linkName' not found");
        }
        
        $relatedIds = $bean->$linkName->get();
        
        if (!in_array($relatedId, $relatedIds)) {
            throw new Exception("Related record '$relatedId' not found in relationship '$linkName'");
        }
        
        return $this;
    }
    
    /**
     * Get test data factory
     */
    public function getFactory()
    {
        return $this->factory;
    }
    
    /**
     * Create quick test deal
     */
    public function createQuickDeal($name = null)
    {
        return $this->factory->createDeal([
            'name' => $name ?: $this->testPrefix . 'Quick Deal ' . uniqid()
        ]);
    }
    
    /**
     * Create quick test contact
     */
    public function createQuickContact($firstName = null, $lastName = null)
    {
        return $this->factory->createContact([
            'first_name' => $firstName ?: 'Test',
            'last_name' => $lastName ?: 'Contact'
        ]);
    }
    
    /**
     * Create quick test account
     */
    public function createQuickAccount($name = null)
    {
        return $this->factory->createAccount([
            'name' => $name ?: $this->testPrefix . 'Quick Account ' . uniqid()
        ]);
    }
    
    /**
     * Execute SQL query
     */
    public function executeQuery($sql, $params = [])
    {
        global $db;
        
        if (empty($params)) {
            return $db->query($sql);
        } else {
            $stmt = $db->getConnection()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        }
    }
    
    /**
     * Get record by field value
     */
    public function getRecordByField($module, $field, $value)
    {
        global $db;
        
        $table = strtolower($module);
        $query = "SELECT id FROM $table WHERE $field = ? AND deleted = 0 LIMIT 1";
        
        $result = $db->fetchOne($query, [$value]);
        
        if ($result && isset($result['id'])) {
            return BeanFactory::getBean($module, $result['id']);
        }
        
        return null;
    }
    
    /**
     * Cleanup test users
     */
    private function cleanupTestUsers()
    {
        foreach ($this->createdUsers as $userId) {
            try {
                $user = BeanFactory::getBean('Users', $userId);
                if ($user && !empty($user->id)) {
                    $user->mark_deleted($userId);
                }
            } catch (Exception $e) {
                echo "Failed to cleanup user $userId: " . $e->getMessage() . "\n";
            }
        }
        
        $this->createdUsers = [];
    }
    
    /**
     * Set module configuration
     */
    public function setModuleConfig($module, $key, $value)
    {
        global $db;
        
        $category = $module . '_config';
        
        // Check if config exists
        $query = "SELECT * FROM config WHERE category = ? AND name = ?";
        $result = $db->fetchOne($query, [$category, $key]);
        
        if ($result) {
            // Update existing
            $db->query("UPDATE config SET value = ? WHERE category = ? AND name = ?", 
                      [$value, $category, $key]);
        } else {
            // Insert new
            $db->query("INSERT INTO config (category, name, value) VALUES (?, ?, ?)",
                      [$category, $key, $value]);
        }
        
        // Clear config cache
        if (function_exists('sugar_cache_clear')) {
            sugar_cache_clear('admin_settings_cache');
        }
        
        return $this;
    }
    
    /**
     * Simulate form submission
     */
    public function simulateFormSubmission($module, $action, $data = [])
    {
        $_REQUEST['module'] = $module;
        $_REQUEST['action'] = $action;
        
        foreach ($data as $key => $value) {
            $_REQUEST[$key] = $value;
            $_POST[$key] = $value;
        }
        
        return $this;
    }
    
    /**
     * Clean request data
     */
    public function cleanRequest()
    {
        $_REQUEST = [];
        $_POST = [];
        $_GET = [];
        
        return $this;
    }
    
    /**
     * Get test summary
     */
    public function getTestSummary()
    {
        return [
            'created_records' => $this->factory->getSummary(),
            'created_users' => count($this->createdUsers),
            'test_prefix' => $this->testPrefix
        ];
    }
    
    /**
     * Run test scenario
     */
    public function runScenario($scenarioName, $callback)
    {
        echo "\n--- Running scenario: $scenarioName ---\n";
        
        try {
            $result = $callback($this);
            echo "✓ Scenario passed: $scenarioName\n";
            return $result;
        } catch (Exception $e) {
            echo "✗ Scenario failed: $scenarioName\n";
            echo "  Error: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
    
    /**
     * Benchmark operation
     */
    public function benchmark($operation, $callback)
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        $result = $callback();
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        
        $duration = round(($endTime - $startTime) * 1000, 2);
        $memoryUsed = round(($endMemory - $startMemory) / 1024 / 1024, 2);
        
        echo "Benchmark - $operation:\n";
        echo "  Duration: {$duration}ms\n";
        echo "  Memory: {$memoryUsed}MB\n";
        
        return $result;
    }
}