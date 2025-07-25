<?php
/**
 * Database Schema Creation for File Request System
 * 
 * Creates the necessary database tables for file request management:
 * - deal_file_requests: Main file request records
 * - deal_file_request_items: Individual file items within requests
 * - deal_file_request_history: Status change history
 * - deal_file_request_emails: Email sending log
 * 
 * @category  Database
 * @package   Deals
 * @author    MakeDealCRM
 * @license   GPL-3.0+
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once 'include/database/DBManagerFactory.php';

class FileRequestTablesCreator
{
    private $db;
    
    public function __construct()
    {
        $this->db = DBManagerFactory::getInstance();
    }
    
    /**
     * Create all file request tables
     */
    public function createTables()
    {
        $results = array();
        
        try {
            $results[] = $this->createFileRequestsTable();
            $results[] = $this->createFileRequestItemsTable();
            $results[] = $this->createFileRequestHistoryTable();
            $results[] = $this->createFileRequestEmailsTable();
            $results[] = $this->createIndexes();
            
            return array(
                'success' => true,
                'results' => $results,
                'message' => 'All file request tables created successfully'
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to create file request tables'
            );
        }
    }
    
    /**
     * Create main file requests table
     */
    private function createFileRequestsTable()
    {
        $tableName = 'deal_file_requests';
        
        // Check if table already exists
        if ($this->tableExists($tableName)) {
            return "Table $tableName already exists";
        }
        
        $sql = "CREATE TABLE $tableName (
            id VARCHAR(36) NOT NULL PRIMARY KEY,
            deal_id VARCHAR(36) NOT NULL,
            request_type VARCHAR(50) NOT NULL DEFAULT 'general',
            recipient_email VARCHAR(255) NOT NULL,
            recipient_name VARCHAR(255) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            requested_files TEXT DEFAULT NULL,
            due_date DATE DEFAULT NULL,
            priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
            status ENUM('pending', 'sent', 'received', 'completed', 'cancelled') DEFAULT 'pending',
            upload_token VARCHAR(64) NOT NULL UNIQUE,
            created_by VARCHAR(36) NOT NULL,
            date_created DATETIME DEFAULT CURRENT_TIMESTAMP,
            status_updated_by VARCHAR(36) DEFAULT NULL,
            status_updated_date DATETIME DEFAULT NULL,
            completion_date DATETIME DEFAULT NULL,
            last_email_sent DATETIME DEFAULT NULL,
            deleted TINYINT(1) DEFAULT 0,
            
            INDEX idx_deal_id (deal_id),
            INDEX idx_status (status),
            INDEX idx_upload_token (upload_token),
            INDEX idx_created_by (created_by),
            INDEX idx_date_created (date_created),
            INDEX idx_deleted (deleted),
            
            FOREIGN KEY (deal_id) REFERENCES opportunities(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
        
        $this->db->query($sql);
        return "Created table: $tableName";
    }
    
    /**
     * Create file request items table
     */
    private function createFileRequestItemsTable()
    {
        $tableName = 'deal_file_request_items';
        
        if ($this->tableExists($tableName)) {
            return "Table $tableName already exists";
        }
        
        $sql = "CREATE TABLE $tableName (
            id VARCHAR(36) NOT NULL PRIMARY KEY,
            file_request_id VARCHAR(36) NOT NULL,
            file_type VARCHAR(100) NOT NULL,
            file_description TEXT DEFAULT NULL,
            is_required TINYINT(1) DEFAULT 1,
            status ENUM('pending', 'received', 'approved', 'rejected') DEFAULT 'pending',
            file_id VARCHAR(36) DEFAULT NULL,
            date_received DATETIME DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            deleted TINYINT(1) DEFAULT 0,
            
            INDEX idx_file_request_id (file_request_id),
            INDEX idx_status (status),
            INDEX idx_file_type (file_type),
            INDEX idx_is_required (is_required),
            INDEX idx_deleted (deleted),
            
            FOREIGN KEY (file_request_id) REFERENCES deal_file_requests(id) ON DELETE CASCADE,
            FOREIGN KEY (file_id) REFERENCES documents(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
        
        $this->db->query($sql);
        return "Created table: $tableName";
    }
    
    /**
     * Create file request history table
     */
    private function createFileRequestHistoryTable()
    {
        $tableName = 'deal_file_request_history';
        
        if ($this->tableExists($tableName)) {
            return "Table $tableName already exists";
        }
        
        $sql = "CREATE TABLE $tableName (
            id VARCHAR(36) NOT NULL PRIMARY KEY,
            file_request_id VARCHAR(36) NOT NULL,
            old_status VARCHAR(50) DEFAULT NULL,
            new_status VARCHAR(50) NOT NULL,
            notes TEXT DEFAULT NULL,
            changed_by VARCHAR(36) NOT NULL,
            date_changed DATETIME DEFAULT CURRENT_TIMESTAMP,
            
            INDEX idx_file_request_id (file_request_id),
            INDEX idx_changed_by (changed_by),
            INDEX idx_date_changed (date_changed),
            
            FOREIGN KEY (file_request_id) REFERENCES deal_file_requests(id) ON DELETE CASCADE,
            FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
        
        $this->db->query($sql);
        return "Created table: $tableName";
    }
    
    /**
     * Create file request emails log table
     */
    private function createFileRequestEmailsTable()
    {
        $tableName = 'deal_file_request_emails';
        
        if ($this->tableExists($tableName)) {
            return "Table $tableName already exists";
        }
        
        $sql = "CREATE TABLE $tableName (
            id VARCHAR(36) NOT NULL PRIMARY KEY,
            file_request_id VARCHAR(36) NOT NULL,
            email_address VARCHAR(255) NOT NULL,
            email_subject VARCHAR(500) DEFAULT NULL,
            email_body TEXT DEFAULT NULL,
            sent_by VARCHAR(36) NOT NULL,
            date_sent DATETIME DEFAULT CURRENT_TIMESTAMP,
            delivery_status ENUM('sent', 'delivered', 'bounced', 'failed') DEFAULT 'sent',
            
            INDEX idx_file_request_id (file_request_id),
            INDEX idx_email_address (email_address),
            INDEX idx_sent_by (sent_by),
            INDEX idx_date_sent (date_sent),
            INDEX idx_delivery_status (delivery_status),
            
            FOREIGN KEY (file_request_id) REFERENCES deal_file_requests(id) ON DELETE CASCADE,
            FOREIGN KEY (sent_by) REFERENCES users(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
        
        $this->db->query($sql);
        return "Created table: $tableName";
    }
    
    /**
     * Create additional indexes for performance
     */
    private function createIndexes()
    {
        $indexes = array(
            "CREATE INDEX idx_deal_file_requests_composite ON deal_file_requests (deal_id, status, deleted)",
            "CREATE INDEX idx_file_request_items_composite ON deal_file_request_items (file_request_id, status, is_required, deleted)",
            "CREATE INDEX idx_file_request_history_composite ON deal_file_request_history (file_request_id, date_changed)",
            "CREATE INDEX idx_file_request_emails_composite ON deal_file_request_emails (file_request_id, date_sent)"
        );
        
        $results = array();
        foreach ($indexes as $sql) {
            try {
                $this->db->query($sql);
                $results[] = "Created index: " . substr($sql, 13, 50) . "...";
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Duplicate key name') === false) {
                    throw $e;
                }
                $results[] = "Index already exists: " . substr($sql, 13, 50) . "...";
            }
        }
        
        return implode(', ', $results);
    }
    
    /**
     * Check if table exists
     */
    private function tableExists($tableName)
    {
        $sql = "SHOW TABLES LIKE '$tableName'";
        $result = $this->db->query($sql);
        return $this->db->fetchByAssoc($result) !== false;
    }
    
    /**
     * Drop all file request tables (for cleanup/testing)
     */
    public function dropTables()
    {
        $tables = array(
            'deal_file_request_emails',
            'deal_file_request_history', 
            'deal_file_request_items',
            'deal_file_requests'
        );
        
        $results = array();
        
        // Disable foreign key checks temporarily
        $this->db->query("SET FOREIGN_KEY_CHECKS = 0");
        
        foreach ($tables as $table) {
            try {
                $this->db->query("DROP TABLE IF EXISTS $table");
                $results[] = "Dropped table: $table";
            } catch (Exception $e) {
                $results[] = "Error dropping $table: " . $e->getMessage();
            }
        }
        
        // Re-enable foreign key checks
        $this->db->query("SET FOREIGN_KEY_CHECKS = 1");
        
        return array(
            'success' => true,
            'results' => $results,
            'message' => 'File request tables cleanup completed'
        );
    }
}

// CLI execution
if (php_sapi_name() === 'cli') {
    define('sugarEntry', true);
    require_once 'config.php';
    require_once 'include/entryPoint.php';
    
    $creator = new FileRequestTablesCreator();
    
    if (isset($argv[1]) && $argv[1] === 'drop') {
        $result = $creator->dropTables();
    } else {
        $result = $creator->createTables();
    }
    
    echo "File Request Tables Management\n";
    echo "==============================\n";
    echo "Success: " . ($result['success'] ? 'Yes' : 'No') . "\n";
    echo "Message: " . $result['message'] . "\n";
    
    if (isset($result['results'])) {
        echo "\nDetails:\n";
        foreach ($result['results'] as $detail) {
            echo "- $detail\n";
        }
    }
    
    if (isset($result['error'])) {
        echo "\nError: " . $result['error'] . "\n";
    }
}