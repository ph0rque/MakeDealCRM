<?php
/**
 * Database Optimization Script for Pipeline Performance
 * 
 * Creates optimized indexes and database structures for large datasets
 * Run this script to improve query performance for pipeline operations
 */

if (!defined('sugarEntry') || !sugarEntry) {
    define('sugarEntry', true);
}

require_once('include/entryPoint.php');

class PipelineDatabaseOptimizer
{
    private $db;
    private $logFile;
    
    public function __construct()
    {
        global $db;
        $this->db = $db;
        $this->logFile = 'logs/pipeline_optimization_' . date('Y-m-d_H-i-s') . '.log';
    }
    
    /**
     * Run all optimizations
     */
    public function runOptimizations()
    {
        $this->log("Starting Pipeline Database Optimization");
        
        try {
            // Create performance tracking tables
            $this->createPerformanceTables();
            
            // Create optimized indexes
            $this->createOptimizedIndexes();
            
            // Update table statistics
            $this->updateTableStatistics();
            
            // Create materialized views for reporting
            $this->createMaterializedViews();
            
            // Setup automated maintenance
            $this->setupAutomatedMaintenance();
            
            $this->log("Pipeline Database Optimization completed successfully");
            
        } catch (Exception $e) {
            $this->log("ERROR: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Create performance tracking tables
     */
    private function createPerformanceTables()
    {
        $this->log("Creating performance tracking tables...");
        
        // Pipeline stage history table
        $stageHistoryQuery = "
            CREATE TABLE IF NOT EXISTS pipeline_stage_history (
                id VARCHAR(36) PRIMARY KEY,
                deal_id VARCHAR(36) NOT NULL,
                old_stage VARCHAR(50),
                new_stage VARCHAR(50) NOT NULL,
                changed_by VARCHAR(36) NOT NULL,
                date_changed DATETIME NOT NULL,
                created_by VARCHAR(36) NOT NULL,
                date_entered DATETIME NOT NULL,
                INDEX idx_stage_history_deal (deal_id),
                INDEX idx_stage_history_date (date_changed),
                INDEX idx_stage_history_stages (old_stage, new_stage),
                INDEX idx_stage_history_user (changed_by),
                FOREIGN KEY (deal_id) REFERENCES opportunities(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        // Pipeline change log table
        $changeLogQuery = "
            CREATE TABLE IF NOT EXISTS pipeline_change_log (
                id VARCHAR(36) PRIMARY KEY,
                deal_id VARCHAR(36) NOT NULL,
                change_type VARCHAR(50) NOT NULL,
                details TEXT,
                user_id VARCHAR(36) NOT NULL,
                timestamp DATETIME NOT NULL,
                INDEX idx_change_log_deal (deal_id),
                INDEX idx_change_log_type (change_type),
                INDEX idx_change_log_user (user_id),
                INDEX idx_change_log_timestamp (timestamp),
                FOREIGN KEY (deal_id) REFERENCES opportunities(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        // Pipeline sync log table
        $syncLogQuery = "
            CREATE TABLE IF NOT EXISTS pipeline_sync_log (
                id VARCHAR(36) PRIMARY KEY,
                user_id VARCHAR(36) NOT NULL,
                session_id VARCHAR(100),
                changes_applied INT DEFAULT 0,
                conflicts_found INT DEFAULT 0,
                timestamp DATETIME NOT NULL,
                INDEX idx_sync_log_user (user_id),
                INDEX idx_sync_log_timestamp (timestamp),
                INDEX idx_sync_log_session (session_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        // State storage tables
        $stateStoreQuery = "
            CREATE TABLE IF NOT EXISTS pipeline_state_store (
                id VARCHAR(36) PRIMARY KEY,
                user_id VARCHAR(36) NOT NULL,
                state_data LONGTEXT,
                version INT NOT NULL DEFAULT 1,
                timestamp DATETIME NOT NULL,
                INDEX idx_state_store_user (user_id),
                INDEX idx_state_store_version (version)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $stateChangesQuery = "
            CREATE TABLE IF NOT EXISTS pipeline_state_changes (
                id VARCHAR(36) PRIMARY KEY,
                user_id VARCHAR(36) NOT NULL,
                session_id VARCHAR(100),
                action_data TEXT,
                version INT NOT NULL,
                timestamp DATETIME NOT NULL,
                INDEX idx_state_changes_user (user_id),
                INDEX idx_state_changes_version (version),
                INDEX idx_state_changes_timestamp (timestamp)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $stateVersionsQuery = "
            CREATE TABLE IF NOT EXISTS pipeline_state_versions (
                id VARCHAR(36) PRIMARY KEY,
                version INT NOT NULL,
                timestamp DATETIME NOT NULL,
                INDEX idx_state_versions_version (version)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        // Performance metrics table
        $metricsQuery = "
            CREATE TABLE IF NOT EXISTS pipeline_performance_metrics (
                id VARCHAR(36) PRIMARY KEY,
                metric_name VARCHAR(100) NOT NULL,
                metric_value DECIMAL(15,4),
                metric_data TEXT,
                recorded_at DATETIME NOT NULL,
                INDEX idx_metrics_name (metric_name),
                INDEX idx_metrics_date (recorded_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $tables = [
            'pipeline_stage_history' => $stageHistoryQuery,
            'pipeline_change_log' => $changeLogQuery,
            'pipeline_sync_log' => $syncLogQuery,
            'pipeline_state_store' => $stateStoreQuery,
            'pipeline_state_changes' => $stateChangesQuery,
            'pipeline_state_versions' => $stateVersionsQuery,
            'pipeline_performance_metrics' => $metricsQuery
        ];
        
        foreach ($tables as $tableName => $query) {
            try {
                $this->db->query($query);
                $this->log("Created table: $tableName");
            } catch (Exception $e) {
                $this->log("Failed to create table $tableName: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Create optimized indexes
     */
    private function createOptimizedIndexes()
    {
        $this->log("Creating optimized indexes...");
        
        // Opportunities table indexes
        $opportunityIndexes = [
            'idx_opp_pipeline_stage' => 'CREATE INDEX IF NOT EXISTS idx_opp_pipeline_stage ON opportunities_cstm (pipeline_stage_c, focus_flag_c)',
            'idx_opp_focus_order' => 'CREATE INDEX IF NOT EXISTS idx_opp_focus_order ON opportunities_cstm (focus_flag_c, focus_order_c)',
            'idx_opp_stage_date' => 'CREATE INDEX IF NOT EXISTS idx_opp_stage_date ON opportunities_cstm (stage_entered_date_c)',
            'idx_opp_composite' => 'CREATE INDEX IF NOT EXISTS idx_opp_composite ON opportunities_cstm (pipeline_stage_c, focus_flag_c, focus_order_c)',
            'idx_opp_sales_stage_deleted' => 'CREATE INDEX IF NOT EXISTS idx_opp_sales_stage_deleted ON opportunities (sales_stage, deleted)',
            'idx_opp_amount_probability' => 'CREATE INDEX IF NOT EXISTS idx_opp_amount_probability ON opportunities (amount, probability)',
            'idx_opp_assigned_user' => 'CREATE INDEX IF NOT EXISTS idx_opp_assigned_user ON opportunities (assigned_user_id, deleted)',
            'idx_opp_account_id' => 'CREATE INDEX IF NOT EXISTS idx_opp_account_id ON opportunities (account_id, deleted)',
            'idx_opp_date_modified' => 'CREATE INDEX IF NOT EXISTS idx_opp_date_modified ON opportunities (date_modified DESC)'
        ];
        
        foreach ($opportunityIndexes as $indexName => $query) {
            try {
                $this->db->query($query);
                $this->log("Created index: $indexName");
            } catch (Exception $e) {
                $this->log("Failed to create index $indexName: " . $e->getMessage());
            }
        }
        
        // Accounts table indexes for joins
        $accountIndexes = [
            'idx_accounts_name_deleted' => 'CREATE INDEX IF NOT EXISTS idx_accounts_name_deleted ON accounts (name, deleted)'
        ];
        
        foreach ($accountIndexes as $indexName => $query) {
            try {
                $this->db->query($query);
                $this->log("Created index: $indexName");
            } catch (Exception $e) {
                $this->log("Failed to create index $indexName: " . $e->getMessage());
            }
        }
        
        // Users table indexes for joins
        $userIndexes = [
            'idx_users_name_deleted' => 'CREATE INDEX IF NOT EXISTS idx_users_name_deleted ON users (first_name, last_name, deleted)'
        ];
        
        foreach ($userIndexes as $indexName => $query) {
            try {
                $this->db->query($query);
                $this->log("Created index: $indexName");
            } catch (Exception $e) {
                $this->log("Failed to create index $indexName: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Update table statistics for query optimizer
     */
    private function updateTableStatistics()
    {
        $this->log("Updating table statistics...");
        
        $tables = [
            'opportunities',
            'opportunities_cstm',
            'accounts',
            'users',
            'pipeline_stage_history',
            'pipeline_change_log'
        ];
        
        foreach ($tables as $table) {
            try {
                // MySQL - ANALYZE TABLE
                $this->db->query("ANALYZE TABLE $table");
                $this->log("Updated statistics for table: $table");
            } catch (Exception $e) {
                $this->log("Failed to update statistics for $table: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Create materialized views for reporting
     */
    private function createMaterializedViews()
    {
        $this->log("Creating materialized views...");
        
        // Since MySQL doesn't have materialized views, we'll use tables with scheduled updates
        
        // Daily pipeline metrics view
        $dailyMetricsView = "
            CREATE TABLE IF NOT EXISTS pipeline_daily_metrics (
                metric_date DATE PRIMARY KEY,
                total_deals INT,
                total_value DECIMAL(15,2),
                avg_deal_size DECIMAL(15,2),
                deals_by_stage TEXT,
                conversion_rates TEXT,
                updated_at DATETIME,
                INDEX idx_daily_metrics_date (metric_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";
        
        // Weekly performance summary
        $weeklyPerformanceView = "
            CREATE TABLE IF NOT EXISTS pipeline_weekly_performance (
                week_start DATE PRIMARY KEY,
                deals_moved INT,
                avg_time_in_stage DECIMAL(8,2),
                bottleneck_stages TEXT,
                top_performers TEXT,
                updated_at DATETIME,
                INDEX idx_weekly_performance_date (week_start)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";
        
        // User performance metrics
        $userPerformanceView = "
            CREATE TABLE IF NOT EXISTS pipeline_user_performance (
                user_id VARCHAR(36),
                metric_date DATE,
                deals_count INT,
                deals_value DECIMAL(15,2),
                avg_close_time DECIMAL(8,2),
                conversion_rate DECIMAL(5,2),
                updated_at DATETIME,
                PRIMARY KEY (user_id, metric_date),
                INDEX idx_user_performance_date (metric_date),
                INDEX idx_user_performance_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";
        
        $views = [
            'pipeline_daily_metrics' => $dailyMetricsView,
            'pipeline_weekly_performance' => $weeklyPerformanceView,
            'pipeline_user_performance' => $userPerformanceView
        ];
        
        foreach ($views as $viewName => $query) {
            try {
                $this->db->query($query);
                $this->log("Created materialized view: $viewName");
            } catch (Exception $e) {
                $this->log("Failed to create view $viewName: " . $e->getMessage());
            }
        }
        
        // Create initial data population procedures
        $this->createViewUpdateProcedures();
    }
    
    /**
     * Create procedures to update materialized views
     */
    private function createViewUpdateProcedures()
    {
        $this->log("Creating view update procedures...");
        
        // Daily metrics update procedure
        $dailyMetricsProc = "
            CREATE PROCEDURE IF NOT EXISTS UpdateDailyMetrics()
            BEGIN
                DECLARE done INT DEFAULT FALSE;
                DECLARE current_date DATE DEFAULT CURDATE();
                
                -- Update or insert daily metrics
                INSERT INTO pipeline_daily_metrics (
                    metric_date, total_deals, total_value, avg_deal_size,
                    deals_by_stage, conversion_rates, updated_at
                )
                SELECT 
                    current_date,
                    COUNT(*) as total_deals,
                    SUM(COALESCE(amount, 0)) as total_value,
                    AVG(COALESCE(amount, 0)) as avg_deal_size,
                    JSON_OBJECT() as deals_by_stage,
                    JSON_OBJECT() as conversion_rates,
                    NOW() as updated_at
                FROM opportunities o
                INNER JOIN opportunities_cstm c ON o.id = c.id_c
                WHERE o.deleted = 0
                AND o.sales_stage NOT IN ('Closed Won', 'Closed Lost')
                ON DUPLICATE KEY UPDATE
                    total_deals = VALUES(total_deals),
                    total_value = VALUES(total_value),
                    avg_deal_size = VALUES(avg_deal_size),
                    updated_at = NOW();
            END
        ";
        
        try {
            $this->db->query("DROP PROCEDURE IF EXISTS UpdateDailyMetrics");
            $this->db->query($dailyMetricsProc);
            $this->log("Created procedure: UpdateDailyMetrics");
        } catch (Exception $e) {
            $this->log("Failed to create UpdateDailyMetrics procedure: " . $e->getMessage());
        }
    }
    
    /**
     * Setup automated maintenance tasks
     */
    private function setupAutomatedMaintenance()
    {
        $this->log("Setting up automated maintenance...");
        
        // Create maintenance configuration
        $maintenanceConfig = [
            'daily_stats_update' => '0 1 * * *', // 1 AM daily
            'weekly_cleanup' => '0 2 * * 0', // 2 AM on Sundays
            'index_optimization' => '0 3 * * 1', // 3 AM on Mondays
            'cache_cleanup' => '*/30 * * * *' // Every 30 minutes
        ];
        
        // Insert maintenance schedule
        foreach ($maintenanceConfig as $task => $schedule) {
            $query = "
                INSERT INTO schedulers (
                    id, name, job, status, catch_up, date_time_start, date_time_end,
                    interval_type, time_from, time_to, advanced_options, created_by,
                    date_entered, date_modified
                ) VALUES (
                    '" . create_guid() . "',
                    'pipeline_$task',
                    'function::PipelineMaintenanceTask::$task',
                    'Active',
                    0,
                    NOW(),
                    NULL,
                    'cron',
                    NULL,
                    NULL,
                    '$schedule',
                    '1',
                    NOW(),
                    NOW()
                ) ON DUPLICATE KEY UPDATE
                    advanced_options = '$schedule',
                    date_modified = NOW()
            ";
            
            try {
                $this->db->query($query);
                $this->log("Created maintenance task: $task");
            } catch (Exception $e) {
                $this->log("Failed to create maintenance task $task: " . $e->getMessage());
            }
        }
        
        // Create maintenance task file
        $this->createMaintenanceTaskFile();
    }
    
    /**
     * Create maintenance task file
     */
    private function createMaintenanceTaskFile()
    {
        $taskFile = 'custom/modules/Deals/scripts/PipelineMaintenanceTask.php';
        
        $taskContent = '<?php
/**
 * Automated Pipeline Maintenance Tasks
 */

class PipelineMaintenanceTask
{
    public static function daily_stats_update()
    {
        global $db;
        try {
            $db->query("CALL UpdateDailyMetrics()");
            error_log("Pipeline: Daily stats updated successfully");
        } catch (Exception $e) {
            error_log("Pipeline: Daily stats update failed - " . $e->getMessage());
        }
    }
    
    public static function weekly_cleanup()
    {
        global $db;
        try {
            // Clean up old logs (older than 90 days)
            $db->query("DELETE FROM pipeline_change_log WHERE timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY)");
            $db->query("DELETE FROM pipeline_sync_log WHERE timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY)");
            
            // Clean up old state changes (older than 30 days)
            $db->query("DELETE FROM pipeline_state_changes WHERE timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            
            error_log("Pipeline: Weekly cleanup completed successfully");
        } catch (Exception $e) {
            error_log("Pipeline: Weekly cleanup failed - " . $e->getMessage());
        }
    }
    
    public static function index_optimization()
    {
        global $db;
        try {
            $tables = [
                "opportunities", "opportunities_cstm", "accounts", "users",
                "pipeline_stage_history", "pipeline_change_log"
            ];
            
            foreach ($tables as $table) {
                $db->query("OPTIMIZE TABLE $table");
            }
            
            error_log("Pipeline: Index optimization completed successfully");
        } catch (Exception $e) {
            error_log("Pipeline: Index optimization failed - " . $e->getMessage());
        }
    }
    
    public static function cache_cleanup()
    {
        try {
            $cacheManager = new PipelineCacheManager();
            $stats = $cacheManager->getStats();
            
            if ($stats["expired_count"] > 100) {
                $cacheManager->clearPattern("*");
                error_log("Pipeline: Cache cleanup completed - cleared " . $stats["expired_count"] . " expired entries");
            }
        } catch (Exception $e) {
            error_log("Pipeline: Cache cleanup failed - " . $e->getMessage());
        }
    }
}';
        
        if (!file_exists(dirname($taskFile))) {
            mkdir(dirname($taskFile), 0755, true);
        }
        
        file_put_contents($taskFile, $taskContent);
        $this->log("Created maintenance task file: $taskFile");
    }
    
    /**
     * Log optimization activities
     */
    private function log($message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        
        // Write to log file
        if (!file_exists(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0755, true);
        }
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        // Also output to console if running from CLI
        if (php_sapi_name() === 'cli') {
            echo $logMessage;
        }
    }
    
    /**
     * Get optimization status report
     */
    public function getOptimizationReport()
    {
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'tables_created' => [],
            'indexes_created' => [],
            'procedures_created' => [],
            'maintenance_tasks' => [],
            'performance_impact' => []
        ];
        
        // Check if tables exist
        $tables = [
            'pipeline_stage_history',
            'pipeline_change_log',
            'pipeline_sync_log',
            'pipeline_state_store',
            'pipeline_state_changes',
            'pipeline_state_versions',
            'pipeline_performance_metrics',
            'pipeline_daily_metrics',
            'pipeline_weekly_performance',
            'pipeline_user_performance'
        ];
        
        foreach ($tables as $table) {
            $result = $this->db->query("SHOW TABLES LIKE '$table'");
            $report['tables_created'][$table] = (bool)$this->db->fetchByAssoc($result);
        }
        
        // Check indexes
        $indexQueries = [
            'idx_opp_pipeline_stage' => "SHOW INDEX FROM opportunities_cstm WHERE Key_name = 'idx_opp_pipeline_stage'",
            'idx_opp_focus_order' => "SHOW INDEX FROM opportunities_cstm WHERE Key_name = 'idx_opp_focus_order'",
            'idx_opp_composite' => "SHOW INDEX FROM opportunities_cstm WHERE Key_name = 'idx_opp_composite'"
        ];
        
        foreach ($indexQueries as $index => $query) {
            try {
                $result = $this->db->query($query);
                $report['indexes_created'][$index] = (bool)$this->db->fetchByAssoc($result);
            } catch (Exception $e) {
                $report['indexes_created'][$index] = false;
            }
        }
        
        return $report;
    }
}

// Run optimization if called directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    try {
        $optimizer = new PipelineDatabaseOptimizer();
        $optimizer->runOptimizations();
        
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "OPTIMIZATION REPORT\n";
        echo str_repeat("=", 50) . "\n";
        
        $report = $optimizer->getOptimizationReport();
        print_r($report);
        
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>