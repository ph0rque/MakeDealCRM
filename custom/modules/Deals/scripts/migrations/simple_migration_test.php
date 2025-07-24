<?php
/**
 * Simple Migration Test and Execution
 * Task 1.15 - Execute Database Migrations for Production
 */

// Include SuiteCRM framework
define('sugarEntry', true);
require_once('include/entryPoint.php');

// Get database connection
global $db;
if (!$db) {
    require_once('include/database/DBManagerFactory.php');
    $db = DBManagerFactory::getInstance();
}

echo "🚀 Simple Migration Test for Pipeline Tables\n";
echo "=============================================\n\n";

// Test database connection
echo "🔌 Testing database connection...\n";
try {
    $result = $db->query("SELECT 1 as test");
    if ($result) {
        echo "✅ Database connection successful\n\n";
    } else {
        echo "❌ Database connection failed\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
}

// Check existing tables
echo "📋 Checking existing pipeline tables...\n";
$pipeline_tables = [
    'pipeline_stages' => false,
    'deal_stage_transitions' => false,
    'pipeline_stage_history' => false
];

foreach ($pipeline_tables as $table => $exists) {
    try {
        $result = $db->query("SHOW TABLES LIKE '{$table}'");
        $exists = $db->getRowCount($result) > 0;
        $pipeline_tables[$table] = $exists;
        echo ($exists ? "✅" : "ℹ️") . " Table {$table}: " . ($exists ? "exists" : "needs creation") . "\n";
    } catch (Exception $e) {
        echo "❌ Error checking {$table}: " . $e->getMessage() . "\n";
    }
}

// Check opportunities table structure
echo "\n🔍 Checking opportunities table structure...\n";
try {
    $result = $db->query("DESCRIBE opportunities");
    $columns = [];
    while ($row = $db->fetchByAssoc($result)) {
        $columns[] = $row['Field'];
    }
    
    $required_columns = ['pipeline_stage_c', 'stage_entered_date_c', 'time_in_stage'];
    foreach ($required_columns as $col) {
        $exists = in_array($col, $columns);
        echo ($exists ? "✅" : "ℹ️") . " Column {$col}: " . ($exists ? "exists" : "needs creation") . "\n";
    }
} catch (Exception $e) {
    echo "❌ Error checking opportunities table: " . $e->getMessage() . "\n";
}

// Run basic migrations if needed
echo "\n🔧 Executing basic pipeline table migrations...\n";

// Create pipeline_stages table
if (!$pipeline_tables['pipeline_stages']) {
    echo "Creating pipeline_stages table...\n";
    $sql = "
    CREATE TABLE IF NOT EXISTS pipeline_stages (
        id char(36) NOT NULL PRIMARY KEY,
        name varchar(255) NOT NULL,
        stage_key varchar(50) NOT NULL UNIQUE,
        stage_order int(11) NOT NULL DEFAULT 0,
        wip_limit int(11) DEFAULT NULL COMMENT 'Work In Progress limit for this stage',
        color_code varchar(7) DEFAULT '#1976d2' COMMENT 'Hex color for stage column',
        description text,
        is_terminal tinyint(1) DEFAULT 0 COMMENT 'Terminal stages like Closed/Unavailable',
        is_active tinyint(1) DEFAULT 1,
        date_entered datetime DEFAULT NULL,
        date_modified datetime DEFAULT NULL,
        created_by char(36) DEFAULT NULL,
        modified_user_id char(36) DEFAULT NULL,
        deleted tinyint(1) DEFAULT 0,
        KEY idx_stage_order (stage_order, deleted),
        KEY idx_stage_key (stage_key, deleted),
        KEY idx_is_active (is_active, deleted)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    try {
        $db->query($sql);
        echo "✅ pipeline_stages table created successfully\n";
    } catch (Exception $e) {
        echo "❌ Error creating pipeline_stages: " . $e->getMessage() . "\n";
    }
}

// Create deal_stage_transitions table
if (!$pipeline_tables['deal_stage_transitions']) {
    echo "Creating deal_stage_transitions table...\n";
    $sql = "
    CREATE TABLE IF NOT EXISTS deal_stage_transitions (
        id char(36) NOT NULL PRIMARY KEY,
        deal_id char(36) NOT NULL,
        from_stage varchar(50) COMMENT 'NULL for initial stage entry',
        to_stage varchar(50) NOT NULL,
        transition_date datetime NOT NULL,
        transition_by char(36) NOT NULL COMMENT 'User who made the transition',
        time_in_previous_stage int DEFAULT 0 COMMENT 'Time spent in previous stage (minutes)',
        transition_reason varchar(255),
        notes text,
        created_by char(36),
        date_created datetime,
        deleted tinyint(1) DEFAULT 0,
        KEY idx_deal_transitions (deal_id, transition_date),
        KEY idx_stage_tracking (to_stage, transition_date),
        KEY idx_from_stage (from_stage, transition_date),
        KEY idx_transition_by (transition_by, transition_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    try {
        $db->query($sql);
        echo "✅ deal_stage_transitions table created successfully\n";
    } catch (Exception $e) {
        echo "❌ Error creating deal_stage_transitions: " . $e->getMessage() . "\n";
    }
}

// Create pipeline_stage_history table
if (!$pipeline_tables['pipeline_stage_history']) {
    echo "Creating pipeline_stage_history table...\n";
    $sql = "
    CREATE TABLE IF NOT EXISTS pipeline_stage_history (
        id CHAR(36) NOT NULL PRIMARY KEY,
        deal_id CHAR(36) NOT NULL,
        old_stage VARCHAR(50),
        new_stage VARCHAR(50) NOT NULL,
        changed_by CHAR(36) NOT NULL,
        date_changed DATETIME NOT NULL,
        date_created DATETIME DEFAULT CURRENT_TIMESTAMP,
        deleted TINYINT(1) DEFAULT 0,
        KEY idx_deal_id (deal_id),
        KEY idx_changed_by (changed_by),
        KEY idx_date_changed (date_changed),
        KEY idx_deleted (deleted)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    try {
        $db->query($sql);
        echo "✅ pipeline_stage_history table created successfully\n";
    } catch (Exception $e) {
        echo "❌ Error creating pipeline_stage_history: " . $e->getMessage() . "\n";
    }
}

// Add columns to opportunities table
echo "\nAdding pipeline fields to opportunities table...\n";

$opportunity_fields = [
    'pipeline_stage_c' => "varchar(50) DEFAULT 'sourcing'",
    'stage_entered_date_c' => "datetime DEFAULT NULL",
    'time_in_stage' => "int DEFAULT 0",
    'wip_position' => "int DEFAULT NULL",
    'is_archived' => "tinyint(1) DEFAULT 0",
    'last_stage_update' => "datetime DEFAULT NULL"
];

foreach ($opportunity_fields as $field => $definition) {
    try {
        // Check if column exists first
        $check_result = $db->query("SHOW COLUMNS FROM opportunities LIKE '{$field}'");
        if ($db->getRowCount($check_result) == 0) {
            $db->query("ALTER TABLE opportunities ADD COLUMN {$field} {$definition}");
            echo "✅ Added column {$field} to opportunities table\n";
        } else {
            echo "ℹ️  Column {$field} already exists in opportunities table\n";
        }
    } catch (Exception $e) {
        echo "❌ Error adding {$field}: " . $e->getMessage() . "\n";
    }
}

// Insert default pipeline stages
echo "\n📊 Inserting default pipeline stages...\n";

$default_stages = [
    ['sourcing', 'Sourcing', 1, 50, '#e3f2fd', 'Initial deal sourcing and identification'],
    ['screening', 'Screening', 2, 25, '#bbdefb', 'Initial screening and qualification'],
    ['analysis_outreach', 'Analysis & Outreach', 3, 15, '#90caf9', 'Detailed analysis and stakeholder outreach'],
    ['term_sheet', 'Term Sheet', 4, 10, '#64b5f6', 'Term sheet negotiation and preparation'],
    ['due_diligence', 'Due Diligence', 5, 8, '#42a5f5', 'Comprehensive due diligence process'],
    ['final_negotiation', 'Final Negotiation', 6, 5, '#2196f3', 'Final terms negotiation and documentation'],
    ['closing', 'Closing', 7, 5, '#1e88e5', 'Final closing and transaction completion'],
    ['closed_won', 'Closed Won', 8, null, '#4caf50', 'Successfully completed acquisition'],
    ['closed_lost', 'Closed Lost', 9, null, '#f44336', 'Lost or terminated deal'],
    ['unavailable', 'Unavailable', 10, null, '#9e9e9e', 'Deal on hold or timing not right']
];

foreach ($default_stages as $stage_data) {
    list($key, $name, $order, $wip_limit, $color, $description) = $stage_data;
    
    try {
        // Check if stage already exists
        $check_result = $db->query("SELECT id FROM pipeline_stages WHERE stage_key = '{$key}' AND deleted = 0");
        if ($db->getRowCount($check_result) == 0) {
            $id = create_guid();
            $now = date('Y-m-d H:i:s');
            $wip_sql = $wip_limit ? $wip_limit : 'NULL';
            
            $insert_sql = "
                INSERT INTO pipeline_stages 
                (id, name, stage_key, stage_order, wip_limit, color_code, description, is_active, date_entered, date_modified, deleted) 
                VALUES 
                ('{$id}', '{$name}', '{$key}', {$order}, {$wip_sql}, '{$color}', '{$description}', 1, '{$now}', '{$now}', 0)
            ";
            
            $db->query($insert_sql);
            echo "✅ Inserted stage: {$name}\n";
        } else {
            echo "ℹ️  Stage {$name} already exists\n";
        }
    } catch (Exception $e) {
        echo "❌ Error inserting stage {$name}: " . $e->getMessage() . "\n";
    }
}

// Final validation
echo "\n🔍 Final validation...\n";

try {
    $stage_count_result = $db->query("SELECT COUNT(*) as count FROM pipeline_stages WHERE deleted = 0");
    $stage_count_row = $db->fetchByAssoc($stage_count_result);
    $stage_count = $stage_count_row['count'];
    
    echo "✅ Pipeline stages table has {$stage_count} active stages\n";
    
    $opportunities_count_result = $db->query("SELECT COUNT(*) as count FROM opportunities");
    $opportunities_count_row = $db->fetchByAssoc($opportunities_count_result);
    $opportunities_count = $opportunities_count_row['count'];
    
    echo "✅ Opportunities table has {$opportunities_count} records\n";
    
    // Test a join query
    $test_query = "
        SELECT o.name, o.pipeline_stage_c, ps.name as stage_name 
        FROM opportunities o 
        LEFT JOIN pipeline_stages ps ON o.pipeline_stage_c = ps.stage_key 
        WHERE o.deleted = 0 
        LIMIT 5
    ";
    $test_result = $db->query($test_query);
    echo "✅ Pipeline join query successful\n";
    
} catch (Exception $e) {
    echo "❌ Validation error: " . $e->getMessage() . "\n";
}

echo "\n🎉 Migration execution completed!\n";
echo "\nNext steps:\n";
echo "1. Run Quick Repair and Rebuild in SuiteCRM Admin\n";
echo "2. Test pipeline functionality in the application\n";
echo "3. Verify stage transitions are working correctly\n";
echo "4. Monitor system performance\n";

?>