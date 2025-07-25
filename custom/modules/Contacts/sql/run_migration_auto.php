<?php
/**
 * Stakeholder Tracking Migration Runner - PHP Version
 * Runs database migrations using SuiteCRM's database connection
 */

if (!defined('sugarEntry')) define('sugarEntry', true);

// Try to find the SuiteCRM root
$suitecrm_root = realpath(__DIR__ . '/../../../../SuiteCRM');
if (!$suitecrm_root || !file_exists($suitecrm_root . '/config.php')) {
    die("Error: Could not find SuiteCRM installation. Please run from correct directory.\n");
}

// Change to SuiteCRM root for proper includes
chdir($suitecrm_root);
require_once('include/entryPoint.php');

global $db, $sugar_config;

echo "==============================================\n";
echo "Stakeholder Tracking Migration Runner\n";
echo "==============================================\n\n";

// Check database connection
if (!$db) {
    die("Error: No database connection available\n");
}

echo "Database: " . $sugar_config['dbconfig']['db_name'] . "@" . $sugar_config['dbconfig']['db_host'] . "\n\n";

// Migration files
$migration_dir = __DIR__;
$migrations = array(
    '001_add_stakeholder_tracking_fields.sql',
    '002_create_communication_history_tables.sql',
    '003_enhance_deals_contacts_relationship.sql',
    '004_create_stakeholder_integration_views.sql'
);

// Check if migration tracking table exists
$check_table = "SHOW TABLES LIKE 'stakeholder_migrations'";
$result = $db->query($check_table);
if ($db->getRowCount($result) == 0) {
    echo "Creating migration tracking table...\n";
    $create_tracking = "
    CREATE TABLE IF NOT EXISTS stakeholder_migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration_name VARCHAR(255) NOT NULL UNIQUE,
        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        execution_time_ms INT,
        status ENUM('success', 'failed', 'partial') DEFAULT 'success',
        error_message TEXT,
        INDEX idx_migration_name (migration_name),
        INDEX idx_executed_at (executed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    $db->query($create_tracking);
    echo "✓ Migration tracking table created\n\n";
}

// Process each migration
$total_migrations = count($migrations);
$executed = 0;
$failed = 0;

foreach ($migrations as $index => $migration_file) {
    $migration_path = $migration_dir . '/' . $migration_file;
    
    echo "[" . ($index + 1) . "/$total_migrations] Processing: $migration_file\n";
    
    // Check if already executed
    $check_query = "SELECT * FROM stakeholder_migrations WHERE migration_name = ?";
    $check_result = $db->pQuery($check_query, array($migration_file));
    
    if ($row = $db->fetchByAssoc($check_result)) {
        echo "  ⚠ Already executed on " . $row['executed_at'] . " - Skipping\n\n";
        continue;
    }
    
    // Read migration file
    if (!file_exists($migration_path)) {
        echo "  ✗ Migration file not found: $migration_path\n\n";
        $failed++;
        continue;
    }
    
    $sql_content = file_get_contents($migration_path);
    if (empty($sql_content)) {
        echo "  ✗ Migration file is empty\n\n";
        $failed++;
        continue;
    }
    
    // Execute migration
    $start_time = microtime(true);
    
    // Split into individual statements (basic split by semicolon)
    // Remove comments and empty lines
    $sql_content = preg_replace('/--.*$/m', '', $sql_content);
    $sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content);
    
    // Split by semicolon but be careful with stored procedures
    $delimiter = ';';
    $delimiter_changed = false;
    
    // Check for DELIMITER commands
    if (preg_match('/DELIMITER\s+(\S+)/i', $sql_content, $matches)) {
        $delimiter_changed = true;
        // For simplicity, we'll handle DELIMITER commands specially
        echo "  ⚠ Migration contains DELIMITER commands - executing as single transaction\n";
        
        // Execute the entire content as one query
        try {
            // For complex scripts with procedures, we might need to use multi_query
            $mysqli = mysqli_connect(
                $sugar_config['dbconfig']['db_host_name'],
                $sugar_config['dbconfig']['db_user_name'],
                $sugar_config['dbconfig']['db_password'],
                $sugar_config['dbconfig']['db_name']
            );
            
            if (!$mysqli) {
                throw new Exception("Failed to create MySQLi connection");
            }
            
            // Execute multi-statement query
            if (mysqli_multi_query($mysqli, $sql_content)) {
                do {
                    if ($result = mysqli_store_result($mysqli)) {
                        mysqli_free_result($result);
                    }
                } while (mysqli_next_result($mysqli));
                
                if (mysqli_errno($mysqli)) {
                    throw new Exception(mysqli_error($mysqli));
                }
                
                echo "  ✓ Migration executed successfully\n";
                $executed++;
            } else {
                throw new Exception(mysqli_error($mysqli));
            }
            
            mysqli_close($mysqli);
            
        } catch (Exception $e) {
            echo "  ✗ Migration failed: " . $e->getMessage() . "\n";
            $failed++;
            
            // Log failure
            $log_query = "INSERT INTO stakeholder_migrations (migration_name, status, error_message, execution_time_ms) 
                          VALUES (?, 'failed', ?, ?)";
            $execution_time = round((microtime(true) - $start_time) * 1000);
            $db->pQuery($log_query, array($migration_file, $e->getMessage(), $execution_time));
            
            echo "\n";
            continue;
        }
        
    } else {
        // Simple migrations without stored procedures
        $statements = array_filter(array_map('trim', explode(';', $sql_content)));
        
        $statement_count = count($statements);
        $executed_statements = 0;
        $migration_errors = array();
        
        foreach ($statements as $statement) {
            if (empty($statement)) continue;
            
            try {
                $db->query($statement);
                $executed_statements++;
            } catch (Exception $e) {
                $migration_errors[] = substr($statement, 0, 100) . "... - " . $e->getMessage();
            }
        }
        
        if ($executed_statements == $statement_count) {
            echo "  ✓ All $statement_count statements executed successfully\n";
            $executed++;
        } else {
            echo "  ⚠ Executed $executed_statements of $statement_count statements\n";
            if (!empty($migration_errors)) {
                echo "  Errors:\n";
                foreach ($migration_errors as $error) {
                    echo "    - $error\n";
                }
            }
            $failed++;
        }
    }
    
    // Log successful migration
    $execution_time = round((microtime(true) - $start_time) * 1000);
    $log_query = "INSERT INTO stakeholder_migrations (migration_name, status, execution_time_ms) 
                  VALUES (?, 'success', ?)";
    $db->pQuery($log_query, array($migration_file, $execution_time));
    
    echo "  Execution time: {$execution_time}ms\n\n";
}

// Summary
echo "==============================================\n";
echo "Migration Summary:\n";
echo "  Total migrations: $total_migrations\n";
echo "  Executed: $executed\n";
echo "  Failed: $failed\n";
echo "  Skipped: " . ($total_migrations - $executed - $failed) . "\n";
echo "==============================================\n\n";

// Verification queries
echo "Running verification queries...\n\n";

// Check if custom fields were added
echo "1. Checking contact custom fields:\n";
$check_fields = "SHOW COLUMNS FROM contacts_cstm LIKE '%stakeholder%'";
$result = $db->query($check_fields);
$field_count = $db->getRowCount($result);
echo "   Found $field_count stakeholder-related fields\n\n";

// Check communication history table
echo "2. Checking communication history table:\n";
$check_comm = "SHOW TABLES LIKE 'contact_communication_history'";
$result = $db->query($check_comm);
if ($db->getRowCount($result) > 0) {
    echo "   ✓ Communication history table exists\n";
    
    // Check for indexes
    $check_indexes = "SHOW INDEX FROM contact_communication_history";
    $result = $db->query($check_indexes);
    $index_count = $db->getRowCount($result);
    echo "   ✓ Found $index_count indexes\n\n";
} else {
    echo "   ✗ Communication history table not found\n\n";
}

// Check deals_contacts enhancements
echo "3. Checking deals_contacts enhancements:\n";
$check_deals = "SHOW COLUMNS FROM deals_contacts WHERE Field LIKE '%stakeholder%'";
$result = $db->query($check_deals);
$field_count = $db->getRowCount($result);
echo "   Found $field_count stakeholder-related fields\n\n";

// Check views
echo "4. Checking integration views:\n";
$views = array('v_stakeholder_overview', 'v_deal_stakeholder_summary', 'v_contact_activity_metrics');
foreach ($views as $view) {
    $check_view = "SHOW TABLES LIKE '$view'";
    $result = $db->query($check_view);
    if ($db->getRowCount($result) > 0) {
        echo "   ✓ View $view exists\n";
    } else {
        echo "   ✗ View $view not found\n";
    }
}

echo "\n==============================================\n";
echo "Migration complete!\n\n";
echo "Next steps:\n";
echo "1. Clear SuiteCRM cache: Admin → Repair → Quick Repair and Rebuild\n";
echo "2. Rebuild relationships: Admin → Repair → Rebuild Relationships\n";
echo "3. Test the stakeholder features in Contacts and Deals modules\n";
echo "==============================================\n";