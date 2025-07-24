<?php

namespace Tests;

use PDO;

/**
 * Database Test Case for Integration Tests
 * 
 * Provides database transaction support for tests
 */
abstract class DatabaseTestCase extends TestCase
{
    /**
     * @var PDO
     */
    protected static $pdo;
    
    /**
     * @var bool
     */
    protected static $dbInitialized = false;

    /**
     * Set up test database before all tests
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        
        if (!self::$dbInitialized) {
            self::initializeDatabase();
            self::$dbInitialized = true;
        }
    }

    /**
     * Set up before each test - start transaction
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Start transaction
        self::$pdo->beginTransaction();
        
        // Disable foreign key checks for testing
        self::$pdo->exec('PRAGMA foreign_keys = OFF');
    }

    /**
     * Tear down after each test - rollback transaction
     */
    protected function tearDown(): void
    {
        // Rollback transaction
        if (self::$pdo->inTransaction()) {
            self::$pdo->rollback();
        }
        
        // Re-enable foreign key checks
        self::$pdo->exec('PRAGMA foreign_keys = ON');
        
        parent::tearDown();
    }

    /**
     * Initialize test database
     */
    protected static function initializeDatabase(): void
    {
        // Create in-memory SQLite database
        self::$pdo = new PDO('sqlite::memory:');
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create schema
        self::createSchema();
        
        // Load fixtures
        self::loadFixtures();
    }

    /**
     * Create database schema
     */
    protected static function createSchema(): void
    {
        // Create deals table
        self::$pdo->exec('
            CREATE TABLE deals (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL,
                stage VARCHAR(50) NOT NULL,
                amount DECIMAL(10,2),
                probability INTEGER DEFAULT 0,
                expected_close_date DATE,
                owner_id INTEGER,
                account_id INTEGER,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                stage_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                time_in_stage INTEGER DEFAULT 0,
                is_stale BOOLEAN DEFAULT 0
            )
        ');
        
        // Create deal_stage_history table
        self::$pdo->exec('
            CREATE TABLE deal_stage_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                deal_id INTEGER NOT NULL,
                from_stage VARCHAR(50),
                to_stage VARCHAR(50) NOT NULL,
                changed_by INTEGER NOT NULL,
                changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                time_in_previous_stage INTEGER,
                notes TEXT,
                FOREIGN KEY (deal_id) REFERENCES deals(id)
            )
        ');
        
        // Create pipeline_stages table
        self::$pdo->exec('
            CREATE TABLE pipeline_stages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(50) NOT NULL UNIQUE,
                display_name VARCHAR(100) NOT NULL,
                order_index INTEGER NOT NULL,
                color VARCHAR(7) DEFAULT "#808080",
                wip_limit INTEGER DEFAULT NULL,
                is_active BOOLEAN DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ');
        
        // Create users table
        self::$pdo->exec('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(50) NOT NULL UNIQUE,
                email VARCHAR(255) NOT NULL UNIQUE,
                role VARCHAR(50) NOT NULL,
                is_active BOOLEAN DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ');
        
        // Create indexes
        self::$pdo->exec('CREATE INDEX idx_deals_stage ON deals(stage)');
        self::$pdo->exec('CREATE INDEX idx_deals_owner ON deals(owner_id)');
        self::$pdo->exec('CREATE INDEX idx_deal_history_deal ON deal_stage_history(deal_id)');
        self::$pdo->exec('CREATE INDEX idx_deal_history_date ON deal_stage_history(changed_at)');
    }

    /**
     * Load test fixtures
     */
    protected static function loadFixtures(): void
    {
        // Insert default pipeline stages
        $stages = [
            ['name' => 'lead', 'display_name' => 'Lead', 'order_index' => 1, 'color' => '#6B7280', 'wip_limit' => 50],
            ['name' => 'contacted', 'display_name' => 'Contacted', 'order_index' => 2, 'color' => '#60A5FA', 'wip_limit' => 40],
            ['name' => 'qualified', 'display_name' => 'Qualified', 'order_index' => 3, 'color' => '#34D399', 'wip_limit' => 30],
            ['name' => 'proposal', 'display_name' => 'Proposal', 'order_index' => 4, 'color' => '#FBBF24', 'wip_limit' => 20],
            ['name' => 'negotiation', 'display_name' => 'Negotiation', 'order_index' => 5, 'color' => '#F87171', 'wip_limit' => 15],
            ['name' => 'won', 'display_name' => 'Won', 'order_index' => 6, 'color' => '#10B981', 'wip_limit' => null],
            ['name' => 'lost', 'display_name' => 'Lost', 'order_index' => 7, 'color' => '#EF4444', 'wip_limit' => null],
        ];
        
        $stmt = self::$pdo->prepare('
            INSERT INTO pipeline_stages (name, display_name, order_index, color, wip_limit)
            VALUES (:name, :display_name, :order_index, :color, :wip_limit)
        ');
        
        foreach ($stages as $stage) {
            $stmt->execute($stage);
        }
        
        // Insert test users
        $users = [
            ['username' => 'admin', 'email' => 'admin@test.com', 'role' => 'admin'],
            ['username' => 'manager', 'email' => 'manager@test.com', 'role' => 'manager'],
            ['username' => 'sales1', 'email' => 'sales1@test.com', 'role' => 'sales'],
            ['username' => 'sales2', 'email' => 'sales2@test.com', 'role' => 'sales'],
        ];
        
        $stmt = self::$pdo->prepare('
            INSERT INTO users (username, email, role)
            VALUES (:username, :email, :role)
        ');
        
        foreach ($users as $user) {
            $stmt->execute($user);
        }
    }

    /**
     * Get PDO instance
     */
    protected function getPdo(): PDO
    {
        return self::$pdo;
    }

    /**
     * Insert test deal
     */
    protected function insertDeal(array $data): int
    {
        $defaults = [
            'name' => 'Test Deal',
            'stage' => 'lead',
            'amount' => 10000,
            'probability' => 20,
            'owner_id' => 1,
            'account_id' => 1,
        ];
        
        $data = array_merge($defaults, $data);
        
        $stmt = self::$pdo->prepare('
            INSERT INTO deals (name, stage, amount, probability, owner_id, account_id)
            VALUES (:name, :stage, :amount, :probability, :owner_id, :account_id)
        ');
        
        $stmt->execute($data);
        
        return (int) self::$pdo->lastInsertId();
    }

    /**
     * Get deal by ID
     */
    protected function getDeal(int $id): ?array
    {
        $stmt = self::$pdo->prepare('SELECT * FROM deals WHERE id = :id');
        $stmt->execute(['id' => $id]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Assert database has record
     */
    protected function assertDatabaseHas(string $table, array $conditions): void
    {
        $where = [];
        $params = [];
        
        foreach ($conditions as $column => $value) {
            $where[] = "{$column} = :{$column}";
            $params[$column] = $value;
        }
        
        $sql = "SELECT COUNT(*) FROM {$table} WHERE " . implode(' AND ', $where);
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        
        $count = (int) $stmt->fetchColumn();
        
        $this->assertGreaterThan(0, $count, "Failed asserting that table [{$table}] contains the expected data.");
    }

    /**
     * Assert database missing record
     */
    protected function assertDatabaseMissing(string $table, array $conditions): void
    {
        $where = [];
        $params = [];
        
        foreach ($conditions as $column => $value) {
            $where[] = "{$column} = :{$column}";
            $params[$column] = $value;
        }
        
        $sql = "SELECT COUNT(*) FROM {$table} WHERE " . implode(' AND ', $where);
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        
        $count = (int) $stmt->fetchColumn();
        
        $this->assertEquals(0, $count, "Failed asserting that table [{$table}] does not contain the expected data.");
    }

    /**
     * Assert database count
     */
    protected function assertDatabaseCount(string $table, int $expected, array $conditions = []): void
    {
        $sql = "SELECT COUNT(*) FROM {$table}";
        $params = [];
        
        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $column => $value) {
                $where[] = "{$column} = :{$column}";
                $params[$column] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        
        $count = (int) $stmt->fetchColumn();
        
        $this->assertEquals($expected, $count, "Failed asserting that table [{$table}] contains {$expected} records.");
    }
}