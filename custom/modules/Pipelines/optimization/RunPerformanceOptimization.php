<?php
/**
 * Script to run performance optimization
 * Execute this to optimize the pipeline system performance
 */

if (!defined('sugarEntry')) define('sugarEntry', true);

require_once('include/entryPoint.php');
require_once('custom/modules/Pipelines/optimization/PerformanceOptimizer.php');

// Set execution time limit for optimization
set_time_limit(600); // 10 minutes

echo "Starting Pipeline Performance Optimization...\n";
echo str_repeat('=', 60) . "\n\n";

// Initialize optimizer
$optimizer = new PerformanceOptimizer();

// Run quick performance check first
echo "Running quick performance check...\n";
$quickCheck = $optimizer->quickPerformanceCheck();

echo "\nQuick Check Results:\n";
echo "- Slow Queries: " . $quickCheck['slow_queries']['status'] . "\n";
echo "- Large Tables: " . $quickCheck['large_tables']['status'];
if ($quickCheck['large_tables']['status'] === 'warning') {
    echo " (" . count($quickCheck['large_tables']['large_tables']) . " tables > 100MB)";
}
echo "\n";
echo "- Missing Indexes: " . $quickCheck['missing_indexes']['status'];
if ($quickCheck['missing_indexes']['status'] === 'warning') {
    echo " (" . count($quickCheck['missing_indexes']['missing_indexes']) . " missing)";
}
echo "\n";
echo "- Table Fragmentation: " . $quickCheck['fragmentation']['status'];
if ($quickCheck['fragmentation']['status'] === 'warning') {
    echo " (" . count($quickCheck['fragmentation']['fragmented_tables']) . " fragmented)";
}
echo "\n\n";

// Run full optimization if issues found
$hasIssues = false;
foreach ($quickCheck as $check) {
    if ($check['status'] === 'warning') {
        $hasIssues = true;
        break;
    }
}

if ($hasIssues || isset($_GET['force'])) {
    echo "Issues detected or force flag set. Running full optimization...\n\n";
    
    // Run full optimization
    $report = $optimizer->optimizeSystem();
    
    // Display results
    echo "\nOptimization Complete!\n";
    echo str_repeat('=', 60) . "\n\n";
    
    echo "Benchmark Results:\n";
    foreach ($report['benchmark_results'] as $name => $result) {
        echo sprintf("- %-25s: %.4fs avg (%.4fs min, %.4fs max) - %d rows\n", 
            $name, 
            $result['avg_time'], 
            $result['min_time'], 
            $result['max_time'],
            $result['row_count']
        );
    }
    
    echo "\nOptimization Actions:\n";
    $actionCount = 0;
    foreach ($report['optimization_log'] as $log) {
        if (strpos($log, 'Created') !== false || strpos($log, 'Optimized') !== false) {
            echo "✓ " . $log . "\n";
            $actionCount++;
        }
    }
    
    if ($actionCount === 0) {
        echo "No optimization actions were needed.\n";
    }
    
    echo "\nRecommendations:\n";
    if (count($report['recommendations']) > 0) {
        foreach ($report['recommendations'] as $rec) {
            echo "⚠️  " . $rec['type'] . ": " . $rec['recommendation'] . "\n";
            if (isset($rec['query'])) {
                echo "   Query: " . $rec['query'] . " (Avg: " . round($rec['avg_time'], 2) . "s)\n";
            }
            if (isset($rec['size_mb'])) {
                echo "   Database Size: " . $rec['size_mb'] . " MB\n";
            }
            if (isset($rec['table'])) {
                echo "   Table: " . $rec['table'] . " (" . $rec['fragmentation_percent'] . "% fragmented)\n";
            }
        }
    } else {
        echo "No critical recommendations at this time.\n";
    }
    
    echo "\nSystem Information:\n";
    echo "- MySQL Version: " . $report['system_info']['mysql_version'] . "\n";
    echo "- Database Size: " . $report['system_info']['database_size_mb'] . " MB\n";
    echo "- Table Count: " . $report['system_info']['table_count'] . "\n";
    echo "- Index Count: " . $report['system_info']['index_count'] . "\n";
    echo "- Memory Usage: " . round($report['system_info']['memory_usage_mb'], 2) . " MB\n";
    
    echo "\nReport saved to: custom/modules/Pipelines/reports/\n";
    
} else {
    echo "No performance issues detected. System is running optimally.\n";
    echo "Use ?force=1 parameter to run optimization anyway.\n";
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "Optimization process completed.\n\n";

// Additional optimization suggestions
echo "Additional Performance Tips:\n";
echo "1. Schedule this optimization to run weekly during off-peak hours\n";
echo "2. Monitor slow query logs regularly\n";
echo "3. Archive old pipeline transition data after 2 years\n";
echo "4. Consider implementing Redis caching for frequently accessed data\n";
echo "5. Use CDN for static assets in production\n";
echo "6. Enable MySQL query cache if not already enabled\n";
echo "7. Optimize PHP opcache settings for better performance\n";

?>