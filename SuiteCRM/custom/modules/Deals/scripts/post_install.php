<?php
/**
 * Post-install script for Deals module
 * Creates necessary database tables and initializes data
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

/**
 * Create pipeline stage history table
 */
function create_pipeline_history_table()
{
    global $db;
    
    $table_name = 'pipeline_stage_history';
    
    // Check if table already exists
    $tableCheck = "SHOW TABLES LIKE '$table_name'";
    $result = $db->query($tableCheck);
    
    if (!$db->fetchByAssoc($result)) {
        $sql = "CREATE TABLE $table_name (
            id VARCHAR(36) NOT NULL PRIMARY KEY,
            deal_id VARCHAR(36) NOT NULL,
            old_stage VARCHAR(50),
            new_stage VARCHAR(50),
            changed_by VARCHAR(36),
            date_changed DATETIME,
            deleted TINYINT(1) DEFAULT 0,
            KEY idx_deal_id (deal_id),
            KEY idx_date_changed (date_changed),
            KEY idx_changed_by (changed_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        
        $db->query($sql);
        echo "Created table: $table_name\n";
    } else {
        echo "Table $table_name already exists\n";
    }
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
    
    foreach ($fields as $field_name => $field_def) {
        // Check if column exists
        $check_sql = "SHOW COLUMNS FROM opportunities LIKE '$field_name'";
        $result = $db->query($check_sql);
        
        if (!$db->fetchByAssoc($result)) {
            $alter_sql = "ALTER TABLE opportunities ADD COLUMN $field_name $field_def";
            $db->query($alter_sql);
            echo "Added column: $field_name to opportunities table\n";
        } else {
            echo "Column $field_name already exists in opportunities table\n";
        }
    }
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
    
    foreach ($indices as $index_name => $fields) {
        // Check if index exists
        $check_sql = "SHOW INDEX FROM opportunities WHERE Key_name = '$index_name'";
        $result = $db->query($check_sql);
        
        if (!$db->fetchByAssoc($result)) {
            $fields_str = implode(', ', $fields);
            $create_sql = "CREATE INDEX $index_name ON opportunities ($fields_str)";
            $db->query($create_sql);
            echo "Created index: $index_name\n";
        } else {
            echo "Index $index_name already exists\n";
        }
    }
}

/**
 * Initialize existing opportunities with pipeline stages
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
    
    foreach ($stage_mapping as $sales_stage => $pipeline_stage) {
        $update_sql = "UPDATE opportunities 
                       SET pipeline_stage_c = '$pipeline_stage',
                           stage_entered_date_c = date_modified
                       WHERE sales_stage = '$sales_stage' 
                       AND pipeline_stage_c IS NULL
                       AND deleted = 0";
        
        $db->query($update_sql);
    }
    
    echo "Initialized pipeline stages for existing opportunities\n";
}

/**
 * Create ACL actions for Deals module
 */
function create_acl_actions()
{
    global $db;
    
    // Get the Deals module ID from acl_actions
    $module_check = "SELECT id FROM acl_actions WHERE name = 'Deals' AND category = 'Deals' AND acltype = 'module'";
    $result = $db->query($module_check);
    
    if (!$db->fetchByAssoc($result)) {
        // Create module entry
        $module_id = create_guid();
        $insert_sql = "INSERT INTO acl_actions (id, date_entered, date_modified, modified_user_id, created_by, name, category, acltype, aclaccess, deleted)
                       VALUES ('$module_id', NOW(), NOW(), '1', '1', 'Deals', 'Deals', 'module', 89, 0)";
        $db->query($insert_sql);
        
        // Create standard actions
        $actions = array('access', 'view', 'list', 'edit', 'delete', 'import', 'export');
        foreach ($actions as $action) {
            $action_id = create_guid();
            $insert_action = "INSERT INTO acl_actions (id, date_entered, date_modified, modified_user_id, created_by, name, category, acltype, aclaccess, deleted)
                              VALUES ('$action_id', NOW(), NOW(), '1', '1', '$action', 'Deals', 'module', 89, 0)";
            $db->query($insert_action);
        }
        
        echo "Created ACL actions for Deals module\n";
    } else {
        echo "ACL actions for Deals module already exist\n";
    }
}

// Run post-install tasks
echo "Running Deals module post-install script...\n";
create_pipeline_history_table();
add_custom_fields();
add_indices();
initialize_pipeline_stages();
create_acl_actions();
echo "Post-install script completed successfully!\n";