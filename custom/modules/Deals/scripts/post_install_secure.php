<?php
/**
 * Secure Post-install script for Deals module
 * Creates necessary database tables and initializes data with security improvements
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('include/database/DBManagerFactory.php');

/**
 * Create pipeline stage history table using prepared statements
 */
function create_pipeline_history_table()
{
    global $db;
    
    $table_name = 'pipeline_stage_history';
    
    // Check if table already exists using prepared statement
    $stmt = $db->getConnection()->prepare("SHOW TABLES LIKE ?");
    $stmt->bind_param('s', $table_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        // Use the migration SQL file instead of inline SQL
        $sql_file = dirname(__FILE__) . '/../sql/001_create_pipeline_stage_history.sql';
        if (file_exists($sql_file)) {
            $sql = file_get_contents($sql_file);
            // Split and execute statements
            $statements = preg_split('/;\s*\n/', $sql);
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    $db->query($statement);
                }
            }
            echo "Created table: $table_name\n";
        } else {
            echo "Migration file not found: $sql_file\n";
        }
    } else {
        echo "Table $table_name already exists\n";
    }
    $stmt->close();
}

/**
 * Add custom fields to opportunities table if they don't exist
 */
function add_custom_fields()
{
    global $db;
    
    $fields = array(
        'pipeline_stage_c' => "VARCHAR(50) DEFAULT 'sourcing'",
        'stage_entered_date_c' => "DATETIME",
        'expected_close_date_c' => "DATE",
        'deal_source_c' => "VARCHAR(50)",
        'pipeline_notes_c' => "TEXT"
    );
    
    // Prepare statement for checking columns
    $check_stmt = $db->getConnection()->prepare("SHOW COLUMNS FROM opportunities LIKE ?");
    
    foreach ($fields as $field_name => $field_def) {
        // Check if column exists
        $check_stmt->bind_param('s', $field_name);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows == 0) {
            // Use dynamic SQL but validate field names against whitelist
            if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $field_name)) {
                $alter_sql = "ALTER TABLE opportunities ADD COLUMN `$field_name` $field_def";
                $db->query($alter_sql);
                echo "Added column: $field_name to opportunities table\n";
            } else {
                echo "Invalid field name: $field_name\n";
            }
        } else {
            echo "Column $field_name already exists in opportunities table\n";
        }
    }
    $check_stmt->close();
}

/**
 * Add indices for performance optimization
 */
function add_indices()
{
    global $db;
    
    $indices = array(
        'idx_deals_pipeline_stage' => array('pipeline_stage_c', 'deleted'),
        'idx_deals_stage_date' => array('stage_entered_date_c')
    );
    
    // Prepare statement for checking indices
    $check_stmt = $db->getConnection()->prepare("SHOW INDEX FROM opportunities WHERE Key_name = ?");
    
    foreach ($indices as $index_name => $fields) {
        // Check if index exists
        $check_stmt->bind_param('s', $index_name);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows == 0) {
            // Validate index and field names
            if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $index_name)) {
                $validated_fields = array();
                foreach ($fields as $field) {
                    if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $field)) {
                        $validated_fields[] = "`$field`";
                    }
                }
                
                if (count($validated_fields) == count($fields)) {
                    $fields_str = implode(', ', $validated_fields);
                    $create_sql = "CREATE INDEX `$index_name` ON opportunities ($fields_str)";
                    $db->query($create_sql);
                    echo "Created index: $index_name\n";
                } else {
                    echo "Invalid field names for index: $index_name\n";
                }
            } else {
                echo "Invalid index name: $index_name\n";
            }
        } else {
            echo "Index $index_name already exists\n";
        }
    }
    $check_stmt->close();
}

/**
 * Initialize existing opportunities with pipeline stages using prepared statements
 */
function initialize_pipeline_stages()
{
    global $db;
    
    // Map existing sales stages to pipeline stages
    $stage_mapping = array(
        'Prospecting' => 'sourcing',
        'Qualification' => 'screening',
        'Needs Analysis' => 'analysis_outreach',
        'Value Proposition' => 'valuation_structuring',
        'Id. Decision Makers' => 'due_diligence',
        'Perception Analysis' => 'due_diligence',
        'Proposal/Price Quote' => 'loi_negotiation',
        'Negotiation/Review' => 'loi_negotiation',
        'Closed Won' => 'closed_owned_stable',
        'Closed Lost' => 'unavailable'
    );
    
    // Prepare update statement
    $update_stmt = $db->getConnection()->prepare(
        "UPDATE opportunities 
         SET pipeline_stage_c = ?,
             stage_entered_date_c = date_modified
         WHERE sales_stage = ? 
         AND pipeline_stage_c IS NULL
         AND deleted = 0"
    );
    
    foreach ($stage_mapping as $sales_stage => $pipeline_stage) {
        $update_stmt->bind_param('ss', $pipeline_stage, $sales_stage);
        $update_stmt->execute();
    }
    
    $update_stmt->close();
    echo "Initialized pipeline stages for existing opportunities\n";
}

/**
 * Create ACL actions for Deals module using prepared statements
 */
function create_acl_actions()
{
    global $db;
    
    // Check if module entry exists
    $check_stmt = $db->getConnection()->prepare(
        "SELECT id FROM acl_actions WHERE name = ? AND category = ? AND acltype = ?"
    );
    $name = 'Deals';
    $category = 'Deals';
    $acltype = 'module';
    $check_stmt->bind_param('sss', $name, $category, $acltype);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows == 0) {
        // Create module entry
        $module_id = create_guid();
        $insert_stmt = $db->getConnection()->prepare(
            "INSERT INTO acl_actions (id, date_entered, date_modified, modified_user_id, created_by, name, category, acltype, aclaccess, deleted)
             VALUES (?, NOW(), NOW(), '1', '1', ?, ?, ?, 89, 0)"
        );
        $insert_stmt->bind_param('ssss', $module_id, $name, $category, $acltype);
        $insert_stmt->execute();
        $insert_stmt->close();
        
        // Create standard actions
        $actions = array('access', 'view', 'list', 'edit', 'delete', 'import', 'export');
        $action_stmt = $db->getConnection()->prepare(
            "INSERT INTO acl_actions (id, date_entered, date_modified, modified_user_id, created_by, name, category, acltype, aclaccess, deleted)
             VALUES (?, NOW(), NOW(), '1', '1', ?, ?, 'module', 89, 0)"
        );
        
        foreach ($actions as $action) {
            $action_id = create_guid();
            $action_stmt->bind_param('sss', $action_id, $action, $category);
            $action_stmt->execute();
        }
        $action_stmt->close();
        
        echo "Created ACL actions for Deals module\n";
    } else {
        echo "ACL actions for Deals module already exist\n";
    }
    $check_stmt->close();
}

// Run post-install tasks
echo "Running Deals module post-install script...\n";
create_pipeline_history_table();
add_custom_fields();
add_indices();
initialize_pipeline_stages();
create_acl_actions();
echo "Post-install script completed successfully!\n";