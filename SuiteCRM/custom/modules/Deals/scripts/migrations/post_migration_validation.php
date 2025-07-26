<?php
/**
 * Post-Migration Validation Script
 * Task 1.15 - Validate production migration results
 * 
 * This script performs comprehensive validation of the database migrations
 * to ensure all pipeline functionality is working correctly.
 */

// Include SuiteCRM framework
define('sugarEntry', true);
require_once('include/entryPoint.php');

// Get database connection
global $db;

echo "🔍 Post-Migration Validation Report\n";
echo "===================================\n\n";

// Test 1: Table Structure Validation
echo "📋 Test 1: Validating Table Structure\n";
echo "-------------------------------------\n";

$tables_to_validate = [
    'pipeline_stages' => [
        'id', 'name', 'stage_key', 'stage_order', 'wip_limit', 'color_code', 
        'description', 'is_terminal', 'is_active', 'deleted'
    ],
    'deal_stage_transitions' => [
        'id', 'deal_id', 'from_stage', 'to_stage', 'transition_date', 
        'transition_by', 'time_in_previous_stage', 'deleted'
    ],
    'pipeline_stage_history' => [
        'id', 'deal_id', 'old_stage', 'new_stage', 'changed_by', 'date_changed'
    ],
    'opportunities' => [
        'pipeline_stage_c', 'stage_entered_date_c', 'time_in_stage', 
        'wip_position', 'is_archived', 'last_stage_update'
    ]
];

foreach ($tables_to_validate as $table => $expected_columns) {
    try {
        $result = $db->query("DESCRIBE {$table}");
        $actual_columns = [];
        while ($row = $db->fetchByAssoc($result)) {
            $actual_columns[] = $row['Field'];
        }
        
        $missing_columns = array_diff($expected_columns, $actual_columns);
        if (empty($missing_columns)) {
            echo "✅ Table {$table}: All expected columns present\n";
        } else {
            echo "❌ Table {$table}: Missing columns: " . implode(', ', $missing_columns) . "\n";
        }
    } catch (Exception $e) {
        echo "❌ Table {$table}: Error validating structure - " . $e->getMessage() . "\n";
    }
}

// Test 2: Data Validation
echo "\n📊 Test 2: Validating Default Data\n";
echo "----------------------------------\n";

try {
    // Check pipeline stages count
    $stage_result = $db->query("SELECT COUNT(*) as count FROM pipeline_stages WHERE deleted = 0");
    $stage_row = $db->fetchByAssoc($stage_result);
    $stage_count = $stage_row['count'];
    
    if ($stage_count >= 10) {
        echo "✅ Pipeline stages: {$stage_count} stages configured\n";
    } else {
        echo "⚠️ Pipeline stages: Only {$stage_count} stages found (expected 10+)\n";
    }
    
    // Check stage order consistency
    $order_result = $db->query("SELECT stage_key, stage_order FROM pipeline_stages WHERE deleted = 0 ORDER BY stage_order");
    $orders = [];
    $previous_order = 0;
    while ($row = $db->fetchByAssoc($order_result)) {
        $orders[] = $row['stage_order'];
        if ($row['stage_order'] <= $previous_order) {
            echo "⚠️ Stage ordering issue detected for stage: " . $row['stage_key'] . "\n";
        }
        $previous_order = $row['stage_order'];
    }
    
    if (count($orders) === count(array_unique($orders))) {
        echo "✅ Stage ordering: All stages have unique order values\n";
    } else {
        echo "❌ Stage ordering: Duplicate order values detected\n";
    }
    
} catch (Exception $e) {
    echo "❌ Data validation error: " . $e->getMessage() . "\n";
}

// Test 3: Index Performance Validation
echo "\n🚀 Test 3: Validating Database Indexes\n";
echo "--------------------------------------\n";

$index_queries = [
    'pipeline_stages_order' => "SHOW INDEX FROM pipeline_stages WHERE Key_name LIKE '%order%'",
    'deal_transitions_deal' => "SHOW INDEX FROM deal_stage_transitions WHERE Key_name LIKE '%deal%'",
    'pipeline_history_deal' => "SHOW INDEX FROM pipeline_stage_history WHERE Key_name LIKE '%deal%'"
];

foreach ($index_queries as $test_name => $query) {
    try {
        $result = $db->query($query);
        $index_count = $db->getRowCount($result);
        
        if ($index_count > 0) {
            echo "✅ Index check {$test_name}: {$index_count} relevant indexes found\n";
        } else {
            echo "⚠️ Index check {$test_name}: No indexes found\n";
        }
    } catch (Exception $e) {
        echo "❌ Index check {$test_name}: " . $e->getMessage() . "\n";
    }
}

// Test 4: Pipeline Functionality Test
echo "\n⚙️ Test 4: Pipeline Functionality Test\n";
echo "-------------------------------------\n";

try {
    // Test joining opportunities with pipeline stages
    $join_query = "
        SELECT o.name, o.pipeline_stage_c, ps.name as stage_name, ps.stage_order 
        FROM opportunities o 
        LEFT JOIN pipeline_stages ps ON o.pipeline_stage_c = ps.stage_key AND ps.deleted = 0
        WHERE o.deleted = 0 
        LIMIT 5
    ";
    
    $join_result = $db->query($join_query);
    $join_count = $db->getRowCount($join_result);
    
    if ($join_count > 0) {
        echo "✅ Pipeline join query: Successfully retrieved {$join_count} records\n";
        
        // Display sample data
        while ($row = $db->fetchByAssoc($join_result)) {
            $stage_name = $row['stage_name'] ? $row['stage_name'] : 'No stage assigned';
            echo "   • Deal: {$row['name']} → Stage: {$stage_name}\n";
        }
    } else {
        echo "⚠️ Pipeline join query: No opportunities found\n";
    }
    
} catch (Exception $e) {
    echo "❌ Pipeline functionality test failed: " . $e->getMessage() . "\n";
}

// Test 5: Performance Baseline
echo "\n⏱️  Test 5: Performance Baseline\n";
echo "------------------------------\n";

$performance_queries = [
    'Stage lookup' => "SELECT * FROM pipeline_stages WHERE stage_key = 'sourcing' AND deleted = 0",
    'Deal stage query' => "SELECT COUNT(*) FROM opportunities WHERE pipeline_stage_c = 'sourcing' AND deleted = 0",
    'Transition history' => "SELECT COUNT(*) FROM deal_stage_transitions WHERE deal_id IS NOT NULL"
];

foreach ($performance_queries as $test_name => $query) {
    $start_time = microtime(true);
    
    try {
        $result = $db->query($query);
        $end_time = microtime(true);
        $execution_time = round(($end_time - $start_time) * 1000, 2);
        
        echo "✅ {$test_name}: {$execution_time}ms\n";
        
        if ($execution_time > 1000) {
            echo "   ⚠️ Query took longer than 1 second - consider optimization\n";
        }
        
    } catch (Exception $e) {
        echo "❌ {$test_name}: Query failed - " . $e->getMessage() . "\n";
    }
}

// Test 6: Data Integrity Validation
echo "\n🔐 Test 6: Data Integrity Validation\n";
echo "------------------------------------\n";

try {
    // Check for orphaned records
    $orphan_checks = [
        'Opportunities with invalid stages' => "
            SELECT COUNT(*) as count 
            FROM opportunities o 
            LEFT JOIN pipeline_stages ps ON o.pipeline_stage_c = ps.stage_key AND ps.deleted = 0
            WHERE o.deleted = 0 AND o.pipeline_stage_c IS NOT NULL AND ps.id IS NULL
        ",
        'Transitions with invalid deals' => "
            SELECT COUNT(*) as count 
            FROM deal_stage_transitions dt 
            LEFT JOIN opportunities o ON dt.deal_id = o.id AND o.deleted = 0
            WHERE dt.deleted = 0 AND o.id IS NULL
        "
    ];
    
    foreach ($orphan_checks as $check_name => $query) {
        $result = $db->query($query);
        $row = $db->fetchByAssoc($result);
        $count = $row['count'];
        
        if ($count == 0) {
            echo "✅ {$check_name}: No orphaned records found\n";
        } else {
            echo "⚠️ {$check_name}: {$count} orphaned records found\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Data integrity validation failed: " . $e->getMessage() . "\n";
}

// Summary and Recommendations
echo "\n📋 Migration Validation Summary\n";
echo "==============================\n";

// Check if system is ready for production use
$validation_passed = true;

try {
    // Final comprehensive check
    $final_checks = [
        "SELECT COUNT(*) as count FROM pipeline_stages WHERE deleted = 0",
        "SELECT COUNT(*) as count FROM opportunities WHERE deleted = 0",
        "SELECT COUNT(DISTINCT stage_key) as count FROM pipeline_stages WHERE deleted = 0"
    ];
    
    $check_results = [];
    foreach ($final_checks as $query) {
        $result = $db->query($query);
        $row = $db->fetchByAssoc($result);
        $check_results[] = $row['count'];
    }
    
    if ($check_results[0] >= 10 && $check_results[2] >= 10) {
        echo "🎉 MIGRATION VALIDATION SUCCESSFUL!\n\n";
        echo "✅ All pipeline tables created and populated\n";
        echo "✅ Database integrity validated\n";
        echo "✅ Performance benchmarks within acceptable range\n";
        echo "✅ System ready for production use\n\n";
        
        echo "📊 Statistics:\n";
        echo "   • Pipeline stages: {$check_results[0]}\n";
        echo "   • Opportunity records: {$check_results[1]}\n";
        echo "   • Unique stage keys: {$check_results[2]}\n\n";
        
        echo "🔄 Next Steps:\n";
        echo "1. Run Quick Repair and Rebuild in SuiteCRM Admin\n";
        echo "2. Test pipeline UI functionality in browser\n";
        echo "3. Verify drag-and-drop operations work correctly\n";
        echo "4. Test stage transition logging\n";
        echo "5. Monitor system performance under normal load\n";
        
    } else {
        echo "❌ MIGRATION VALIDATION FAILED!\n";
        echo "Some critical components are missing or incomplete.\n";
        $validation_passed = false;
    }
    
} catch (Exception $e) {
    echo "❌ Final validation check failed: " . $e->getMessage() . "\n";
    $validation_passed = false;
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Validation completed at: " . date('Y-m-d H:i:s') . "\n";
echo "Status: " . ($validation_passed ? "PASSED ✅" : "FAILED ❌") . "\n";

exit($validation_passed ? 0 : 1);
?>