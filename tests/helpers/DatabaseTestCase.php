<?php
/**
 * Database Test Case for MakeDeal CRM
 * 
 * Provides database transaction support and fixtures for integration tests
 */

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base class for tests requiring database access
 */
abstract class DatabaseTestCase extends BaseTestCase
{
    /**
     * @var \mysqli|PDO
     */
    protected static $db;
    
    /**
     * @var bool
     */
    protected static $transactionStarted = false;
    
    /**
     * @var array
     */
    protected $createdRecords = [];
    
    /**
     * Set up test class
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        
        // Initialize database connection
        self::initializeDatabase();
    }
    
    /**
     * Tear down test class
     */
    public static function tearDownAfterClass(): void
    {
        // Close database connection
        if (self::$db) {
            self::$db = null;
        }
        
        parent::tearDownAfterClass();
    }
    
    /**
     * Set up before each test
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Start transaction
        $this->beginTransaction();
        
        // Load base fixtures
        $this->loadBaseFixtures();
    }
    
    /**
     * Tear down after each test
     */
    protected function tearDown(): void
    {
        // Rollback transaction
        $this->rollbackTransaction();
        
        // Clear created records tracking
        $this->createdRecords = [];
        
        parent::tearDown();
    }
    
    /**
     * Initialize database connection
     */
    protected static function initializeDatabase(): void
    {
        $config = self::getTestDatabaseConfig();
        
        // Create connection based on config
        if ($config['type'] === 'mysql') {
            self::$db = new \mysqli(
                $config['host'],
                $config['user'],
                $config['password'],
                $config['database'],
                $config['port'] ?? 3306
            );
            
            if (self::$db->connect_error) {
                throw new \Exception('Database connection failed: ' . self::$db->connect_error);
            }
            
            // Set charset
            self::$db->set_charset('utf8mb4');
        }
    }
    
    /**
     * Get test database configuration
     */
    protected static function getTestDatabaseConfig(): array
    {
        return [
            'type' => getenv('TEST_DB_TYPE') ?: 'mysql',
            'host' => getenv('TEST_DB_HOST') ?: 'localhost',
            'user' => getenv('TEST_DB_USER') ?: 'test_user',
            'password' => getenv('TEST_DB_PASSWORD') ?: 'test_password',
            'database' => getenv('TEST_DB_NAME') ?: 'makedeal_test',
            'port' => getenv('TEST_DB_PORT') ?: 3306
        ];
    }
    
    /**
     * Begin database transaction
     */
    protected function beginTransaction(): void
    {
        if (!self::$transactionStarted) {
            self::$db->autocommit(false);
            self::$db->begin_transaction();
            self::$transactionStarted = true;
        }
    }
    
    /**
     * Rollback database transaction
     */
    protected function rollbackTransaction(): void
    {
        if (self::$transactionStarted) {
            self::$db->rollback();
            self::$db->autocommit(true);
            self::$transactionStarted = false;
        }
    }
    
    /**
     * Get database connection
     */
    protected function getDatabase()
    {
        return self::$db;
    }
    
    /**
     * Load base fixtures required for most tests
     */
    protected function loadBaseFixtures(): void
    {
        // Load default users
        $this->insertTestRecords('users', [
            [
                'id' => 'test-user-1',
                'user_name' => 'testuser1',
                'first_name' => 'Test',
                'last_name' => 'User One',
                'status' => 'Active',
                'is_admin' => 0
            ],
            [
                'id' => 'test-admin-1',
                'user_name' => 'testadmin1',
                'first_name' => 'Test',
                'last_name' => 'Admin One',
                'status' => 'Active',
                'is_admin' => 1
            ]
        ]);
        
        // Load default teams
        $this->insertTestRecords('teams', [
            [
                'id' => 'test-team-1',
                'name' => 'Test Sales Team',
                'description' => 'Test sales team for integration tests'
            ]
        ]);
    }
    
    /**
     * Insert test records into database
     */
    protected function insertTestRecords(string $table, array $records): void
    {
        foreach ($records as $record) {
            $fields = array_keys($record);
            $values = array_values($record);
            $placeholders = array_fill(0, count($fields), '?');
            
            $sql = sprintf(
                "INSERT INTO %s (%s) VALUES (%s)",
                $table,
                implode(', ', $fields),
                implode(', ', $placeholders)
            );
            
            $stmt = self::$db->prepare($sql);
            
            // Bind parameters dynamically
            $types = str_repeat('s', count($values));
            $stmt->bind_param($types, ...$values);
            
            if (!$stmt->execute()) {
                throw new \Exception("Failed to insert test record: " . $stmt->error);
            }
            
            // Track created records for cleanup if needed
            $this->createdRecords[] = [
                'table' => $table,
                'id' => $record['id'] ?? self::$db->insert_id
            ];
            
            $stmt->close();
        }
    }
    
    /**
     * Find record in database
     */
    protected function findInDatabase(string $table, array $conditions): ?array
    {
        $where = [];
        $values = [];
        
        foreach ($conditions as $field => $value) {
            $where[] = "$field = ?";
            $values[] = $value;
        }
        
        $sql = sprintf(
            "SELECT * FROM %s WHERE %s LIMIT 1",
            $table,
            implode(' AND ', $where)
        );
        
        $stmt = self::$db->prepare($sql);
        $types = str_repeat('s', count($values));
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $stmt->close();
        
        return $row ?: null;
    }
    
    /**
     * Count records in database
     */
    protected function countInDatabase(string $table, array $conditions = []): int
    {
        if (empty($conditions)) {
            $sql = "SELECT COUNT(*) as count FROM $table";
            $result = self::$db->query($sql);
        } else {
            $where = [];
            $values = [];
            
            foreach ($conditions as $field => $value) {
                $where[] = "$field = ?";
                $values[] = $value;
            }
            
            $sql = sprintf(
                "SELECT COUNT(*) as count FROM %s WHERE %s",
                $table,
                implode(' AND ', $where)
            );
            
            $stmt = self::$db->prepare($sql);
            $types = str_repeat('s', count($values));
            $stmt->bind_param($types, ...$values);
            $stmt->execute();
            $result = $stmt->get_result();
        }
        
        $row = $result->fetch_assoc();
        return (int) $row['count'];
    }
    
    /**
     * Update record in database
     */
    protected function updateInDatabase(string $table, array $conditions, array $updates): bool
    {
        $set = [];
        $values = [];
        
        foreach ($updates as $field => $value) {
            $set[] = "$field = ?";
            $values[] = $value;
        }
        
        $where = [];
        foreach ($conditions as $field => $value) {
            $where[] = "$field = ?";
            $values[] = $value;
        }
        
        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s",
            $table,
            implode(', ', $set),
            implode(' AND ', $where)
        );
        
        $stmt = self::$db->prepare($sql);
        $types = str_repeat('s', count($values));
        $stmt->bind_param($types, ...$values);
        
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Delete records from database
     */
    protected function deleteFromDatabase(string $table, array $conditions): bool
    {
        $where = [];
        $values = [];
        
        foreach ($conditions as $field => $value) {
            $where[] = "$field = ?";
            $values[] = $value;
        }
        
        $sql = sprintf(
            "DELETE FROM %s WHERE %s",
            $table,
            implode(' AND ', $where)
        );
        
        $stmt = self::$db->prepare($sql);
        $types = str_repeat('s', count($values));
        $stmt->bind_param($types, ...$values);
        
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Execute raw SQL query
     */
    protected function executeQuery(string $sql, array $params = []): bool
    {
        if (empty($params)) {
            return self::$db->query($sql) !== false;
        }
        
        $stmt = self::$db->prepare($sql);
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
        
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Assert record exists in database
     */
    protected function assertDatabaseHas(string $table, array $conditions, string $message = ''): void
    {
        $record = $this->findInDatabase($table, $conditions);
        
        $this->assertNotNull(
            $record,
            $message ?: "Failed asserting that table [$table] contains record matching: " . json_encode($conditions)
        );
    }
    
    /**
     * Assert record does not exist in database
     */
    protected function assertDatabaseMissing(string $table, array $conditions, string $message = ''): void
    {
        $record = $this->findInDatabase($table, $conditions);
        
        $this->assertNull(
            $record,
            $message ?: "Failed asserting that table [$table] does not contain record matching: " . json_encode($conditions)
        );
    }
    
    /**
     * Assert count of records in database
     */
    protected function assertDatabaseCount(string $table, int $expected, array $conditions = [], string $message = ''): void
    {
        $actual = $this->countInDatabase($table, $conditions);
        
        $this->assertEquals(
            $expected,
            $actual,
            $message ?: "Failed asserting that table [$table] contains [$expected] records, found [$actual]"
        );
    }
}