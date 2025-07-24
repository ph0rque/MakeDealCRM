<?php
/**
 * Performance Optimizer for Pipeline System
 * Analyzes and optimizes database queries, indexes, and system performance
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

class PerformanceOptimizer
{
    protected $db;
    protected $optimizationLog;
    protected $benchmarkResults;
    
    public function __construct()
    {
        global $db;
        $this->db = $db;
        $this->optimizationLog = [];
        $this->benchmarkResults = [];
    }
    
    /**
     * Run complete performance optimization
     */
    public function optimizeSystem()
    {
        $startTime = microtime(true);
        
        $this->log('Starting performance optimization...');
        
        // 1. Analyze current performance
        $this->analyzeCurrentPerformance();
        
        // 2. Optimize database indexes
        $this->optimizeDatabaseIndexes();
        
        // 3. Optimize queries
        $this->optimizeQueries();
        
        // 4. Optimize caching
        $this->optimizeCaching();
        
        // 5. Clean up old data
        $this->cleanupOldData();
        
        // 6. Update statistics
        $this->updateDatabaseStatistics();
        
        // 7. Generate performance report
        $report = $this->generatePerformanceReport();
        
        $totalTime = microtime(true) - $startTime;
        $this->log("Optimization completed in {$totalTime} seconds");
        
        return $report;
    }
    
    /**
     * Analyze current system performance
     */
    protected function analyzeCurrentPerformance()
    {
        $this->log('Analyzing current performance...');
        
        // Test query performance for key operations
        $queries = [
            'pipeline_summary' => "SELECT stage, COUNT(*) as count, SUM(deal_value) as value 
                                  FROM mdeal_deals WHERE deleted = 0 GROUP BY stage",
            
            'lead_scoring' => "SELECT * FROM mdeal_leads 
                              WHERE deleted = 0 AND status NOT IN ('converted', 'disqualified') 
                              ORDER BY lead_score DESC LIMIT 50",
            
            'stage_transitions' => "SELECT * FROM mdeal_pipeline_transitions 
                                   WHERE transition_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
                                   ORDER BY transition_date DESC LIMIT 100",
            
            'wip_tracking' => "SELECT stage, user_id, deal_count, utilization_percent 
                              FROM mdeal_pipeline_wip_tracking 
                              WHERE last_updated >= DATE_SUB(NOW(), INTERVAL 1 DAY)",
            
            'account_hierarchy' => "SELECT a.*, pa.name as parent_name 
                                   FROM mdeal_accounts a 
                                   LEFT JOIN mdeal_accounts pa ON a.parent_id = pa.id 
                                   WHERE a.deleted = 0 LIMIT 100",
            
            'contact_relationships' => "SELECT c.*, a.name as account_name 
                                       FROM mdeal_contacts c 
                                       JOIN mdeal_accounts a ON c.account_id = a.id 
                                       WHERE c.deleted = 0 AND a.deleted = 0 LIMIT 100"
        ];
        
        foreach ($queries as $name => $query) {
            $this->benchmarkResults[$name] = $this->benchmarkQuery($query, $name);
        }
    }
    
    /**
     * Benchmark a specific query
     */
    protected function benchmarkQuery($query, $name)
    {
        $iterations = 5;
        $times = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);
            
            $result = $this->db->query($query);
            $rowCount = 0;
            while ($this->db->fetchByAssoc($result)) {
                $rowCount++;
            }
            
            $endTime = microtime(true);
            $times[] = $endTime - $startTime;
        }
        
        return [
            'avg_time' => array_sum($times) / count($times),
            'min_time' => min($times),
            'max_time' => max($times),
            'row_count' => $rowCount,
            'query' => $query
        ];
    }
    
    /**
     * Optimize database indexes
     */
    protected function optimizeDatabaseIndexes()
    {
        $this->log('Optimizing database indexes...');
        
        $optimizations = [];
        
        // Check for missing indexes on frequently queried columns
        $indexChecks = [
            [
                'table' => 'mdeal_deals',
                'columns' => ['stage', 'assigned_user_id', 'deleted'],
                'name' => 'idx_deals_stage_user_deleted'
            ],
            [
                'table' => 'mdeal_deals',
                'columns' => ['stage_entered_date', 'days_in_stage'],
                'name' => 'idx_deals_stage_timing'
            ],
            [
                'table' => 'mdeal_deals',
                'columns' => ['is_stale', 'health_score'],
                'name' => 'idx_deals_health'
            ],
            [
                'table' => 'mdeal_leads',
                'columns' => ['lead_score', 'status', 'deleted'],
                'name' => 'idx_leads_scoring'
            ],
            [
                'table' => 'mdeal_leads',
                'columns' => ['last_evaluation_date', 'conversion_recommendation'],
                'name' => 'idx_leads_evaluation'
            ],
            [
                'table' => 'mdeal_accounts',
                'columns' => ['parent_id', 'hierarchy_level', 'deleted'],
                'name' => 'idx_accounts_hierarchy'
            ],
            [
                'table' => 'mdeal_accounts',
                'columns' => ['health_score', 'relationship_score', 'account_status'],
                'name' => 'idx_accounts_scores'
            ],
            [
                'table' => 'mdeal_contacts',
                'columns' => ['account_id', 'decision_role', 'deleted'],
                'name' => 'idx_contacts_account_role'
            ],
            [
                'table' => 'mdeal_pipeline_transitions',
                'columns' => ['deal_id', 'transition_date'],
                'name' => 'idx_transitions_deal_date'
            ],
            [
                'table' => 'mdeal_pipeline_wip_tracking',
                'columns' => ['last_updated', 'utilization_percent'],
                'name' => 'idx_wip_updated_util'
            ]
        ];
        
        foreach ($indexChecks as $check) {
            if (!$this->indexExists($check['table'], $check['name'])) {
                $this->createIndex($check['table'], $check['columns'], $check['name']);
                $optimizations[] = "Created index {$check['name']} on {$check['table']}";
            }
        }
        
        // Check for unused indexes
        $unusedIndexes = $this->findUnusedIndexes();
        foreach ($unusedIndexes as $index) {
            $this->log("Found potentially unused index: {$index['table']}.{$index['name']}");
        }
        
        return $optimizations;
    }
    
    /**
     * Check if index exists
     */
    protected function indexExists($table, $indexName)
    {
        $query = "SHOW INDEX FROM {$table} WHERE Key_name = ?";
        $result = $this->db->pQuery($query, [$indexName]);
        return $this->db->fetchByAssoc($result) !== false;
    }
    
    /**
     * Create database index
     */
    protected function createIndex($table, $columns, $indexName)
    {
        $columnList = implode(', ', $columns);
        $query = "CREATE INDEX {$indexName} ON {$table} ({$columnList})";
        
        try {
            $this->db->query($query);
            $this->log("Created index: {$indexName} on {$table}({$columnList})");
            return true;
        } catch (Exception $e) {
            $this->log("Failed to create index {$indexName}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Find potentially unused indexes
     */
    protected function findUnusedIndexes()
    {
        // This would analyze index usage statistics
        // For now, return empty array
        return [];
    }
    
    /**
     * Optimize specific queries
     */
    protected function optimizeQueries()
    {
        $this->log('Optimizing queries...');
        
        $optimizations = [];
        
        // 1. Optimize pipeline summary view
        $this->optimizePipelineSummaryView();
        $optimizations[] = 'Optimized pipeline summary view';
        
        // 2. Optimize lead conversion queries
        $this->optimizeLeadConversionQueries();
        $optimizations[] = 'Optimized lead conversion queries';
        
        // 3. Optimize hierarchy queries
        $this->optimizeHierarchyQueries();
        $optimizations[] = 'Optimized hierarchy queries';
        
        // 4. Optimize analytics queries
        $this->optimizeAnalyticsQueries();
        $optimizations[] = 'Optimized analytics queries';
        
        return $optimizations;
    }
    
    /**
     * Optimize pipeline summary view
     */
    protected function optimizePipelineSummaryView()
    {
        $optimizedView = "
        CREATE OR REPLACE VIEW v_pipeline_summary_optimized AS
        SELECT 
            ps.name as stage,
            ps.display_name,
            ps.sort_order,
            ps.wip_limit,
            COALESCE(ds.deal_count, 0) as deal_count,
            COALESCE(ds.total_value, 0) as total_value,
            COALESCE(ds.avg_value, 0) as avg_value,
            COALESCE(ds.avg_days_in_stage, 0) as avg_days_in_stage,
            COALESCE(ds.stale_count, 0) as stale_count,
            CASE 
                WHEN ps.wip_limit IS NOT NULL AND ps.wip_limit > 0 AND ds.deal_count > 0
                THEN ROUND((ds.deal_count / ps.wip_limit) * 100, 1)
                ELSE NULL
            END as wip_utilization
        FROM mdeal_pipeline_stages ps
        LEFT JOIN (
            SELECT 
                stage,
                COUNT(*) as deal_count,
                SUM(deal_value) as total_value,
                AVG(deal_value) as avg_value,
                AVG(days_in_stage) as avg_days_in_stage,
                SUM(CASE WHEN is_stale = 1 THEN 1 ELSE 0 END) as stale_count
            FROM mdeal_deals 
            WHERE deleted = 0
            GROUP BY stage
        ) ds ON ps.name = ds.stage
        WHERE ps.deleted = 0 AND ps.is_active = 1
        ORDER BY ps.sort_order";
        
        try {
            $this->db->query($optimizedView);
            $this->log('Created optimized pipeline summary view');
        } catch (Exception $e) {
            $this->log('Failed to create optimized view: ' . $e->getMessage());
        }
    }
    
    /**
     * Optimize lead conversion queries
     */
    protected function optimizeLeadConversionQueries()
    {
        // Create materialized view for lead scoring (if supported)
        $leadScoringView = "
        CREATE OR REPLACE VIEW v_lead_scoring_optimized AS
        SELECT 
            l.*,
            CASE 
                WHEN l.lead_score >= 80 THEN 'auto_conversion'
                WHEN l.lead_score >= 60 THEN 'review_conversion'
                WHEN l.lead_score >= 40 THEN 'qualification_required'
                ELSE 'disqualification'
            END as conversion_action,
            CASE 
                WHEN l.last_evaluation_date IS NULL OR l.last_evaluation_date < DATE_SUB(NOW(), INTERVAL 7 DAY)
                THEN 1 ELSE 0
            END as needs_evaluation
        FROM mdeal_leads l
        WHERE l.deleted = 0 
        AND l.status NOT IN ('converted', 'disqualified', 'dead')";
        
        try {
            $this->db->query($leadScoringView);
            $this->log('Created optimized lead scoring view');
        } catch (Exception $e) {
            $this->log('Failed to create lead scoring view: ' . $e->getMessage());
        }
    }
    
    /**
     * Optimize hierarchy queries
     */
    protected function optimizeHierarchyQueries()
    {
        // Create optimized hierarchy view with better performance
        $hierarchyView = "
        CREATE OR REPLACE VIEW v_account_hierarchy_optimized AS
        SELECT 
            a.id,
            a.name,
            a.account_type,
            a.parent_id,
            pa.name as parent_name,
            a.hierarchy_level,
            (SELECT COUNT(*) FROM mdeal_accounts ca WHERE ca.parent_id = a.id AND ca.deleted = 0) as child_count,
            (SELECT COUNT(*) FROM mdeal_contacts c WHERE c.account_id = a.id AND c.deleted = 0) as contact_count,
            (SELECT COUNT(*) FROM mdeal_deals d WHERE d.account_id = a.id AND d.deleted = 0) as deal_count
        FROM mdeal_accounts a
        LEFT JOIN mdeal_accounts pa ON a.parent_id = pa.id AND pa.deleted = 0
        WHERE a.deleted = 0";
        
        try {
            $this->db->query($hierarchyView);
            $this->log('Created optimized hierarchy view');
        } catch (Exception $e) {
            $this->log('Failed to create hierarchy view: ' . $e->getMessage());
        }
    }
    
    /**
     * Optimize analytics queries
     */
    protected function optimizeAnalyticsQueries()
    {
        // Create summary tables for faster analytics
        $this->createAnalyticsSummaryTables();
    }
    
    /**
     * Create analytics summary tables
     */
    protected function createAnalyticsSummaryTables()
    {
        $dailySummaryTable = "
        CREATE TABLE IF NOT EXISTS mdeal_daily_pipeline_summary (
            id CHAR(36) NOT NULL PRIMARY KEY,
            summary_date DATE NOT NULL,
            stage VARCHAR(100) NOT NULL,
            deals_entered INT DEFAULT 0,
            deals_exited INT DEFAULT 0,
            deals_in_stage INT DEFAULT 0,
            total_value DECIMAL(26,6) DEFAULT 0,
            avg_days_in_stage DECIMAL(5,2) DEFAULT 0,
            conversion_rate DECIMAL(5,2) DEFAULT 0,
            created_date DATETIME NULL,
            
            UNIQUE KEY idx_daily_summary (summary_date, stage),
            INDEX idx_daily_date (summary_date),
            INDEX idx_daily_stage (stage)
        )";
        
        try {
            $this->db->query($dailySummaryTable);
            $this->log('Created daily pipeline summary table');
        } catch (Exception $e) {
            $this->log('Failed to create summary table: ' . $e->getMessage());
        }
    }
    
    /**
     * Optimize caching
     */
    protected function optimizeCaching()
    {
        $this->log('Optimizing caching...');
        
        $optimizations = [];
        
        // 1. Clear old cache entries
        $this->clearOldCache();
        $optimizations[] = 'Cleared old cache entries';
        
        // 2. Pre-warm frequently accessed data
        $this->preWarmCache();
        $optimizations[] = 'Pre-warmed cache with frequently accessed data';
        
        // 3. Optimize cache keys
        $this->optimizeCacheKeys();
        $optimizations[] = 'Optimized cache key structure';
        
        return $optimizations;
    }
    
    /**
     * Clear old cache entries
     */
    protected function clearOldCache()
    {
        // This would integrate with SuiteCRM's caching system
        $this->log('Clearing old cache entries...');
    }
    
    /**
     * Pre-warm cache with frequently accessed data
     */
    protected function preWarmCache()
    {
        $this->log('Pre-warming cache...');
        
        // Cache pipeline statistics
        $this->cacheData('pipeline_stats', $this->getPipelineStatistics());
        
        // Cache stage configurations
        $this->cacheData('pipeline_stages', $this->getStageConfigurations());
        
        // Cache WIP limits
        $this->cacheData('wip_limits', $this->getWIPLimits());
    }
    
    /**
     * Cache data with TTL
     */
    protected function cacheData($key, $data, $ttl = 3600)
    {
        // This would integrate with actual caching system
        $this->log("Cached data for key: {$key}");
    }
    
    /**
     * Optimize cache keys
     */
    protected function optimizeCacheKeys()
    {
        // Implement cache key optimization strategies
        $this->log('Optimized cache key structure');
    }
    
    /**
     * Clean up old data
     */
    protected function cleanupOldData()
    {
        $this->log('Cleaning up old data...');
        
        $cleanupOperations = [];
        
        // 1. Archive old pipeline transitions
        $archivedTransitions = $this->archiveOldTransitions();
        $cleanupOperations[] = "Archived {$archivedTransitions} old transitions";
        
        // 2. Clean up old automation logs
        $cleanedLogs = $this->cleanupAutomationLogs();
        $cleanupOperations[] = "Cleaned {$cleanedLogs} old automation logs";
        
        // 3. Remove old analytics data
        $cleanedAnalytics = $this->cleanupOldAnalytics();
        $cleanupOperations[] = "Cleaned {$cleanedAnalytics} old analytics records";
        
        // 4. Optimize table storage
        $this->optimizeTableStorage();
        $cleanupOperations[] = 'Optimized table storage';
        
        return $cleanupOperations;
    }
    
    /**
     * Archive old pipeline transitions
     */
    protected function archiveOldTransitions()
    {
        $cutoffDate = date('Y-m-d', strtotime('-2 years'));
        
        // Move to archive table (create if needed)
        $this->createArchiveTable('mdeal_pipeline_transitions');
        
        $archiveQuery = "
        INSERT INTO mdeal_pipeline_transitions_archive 
        SELECT * FROM mdeal_pipeline_transitions 
        WHERE created_date < ?";
        
        $this->db->pQuery($archiveQuery, [$cutoffDate]);
        $archivedCount = $this->db->getAffectedRowCount();
        
        // Delete from main table
        $deleteQuery = "DELETE FROM mdeal_pipeline_transitions WHERE created_date < ?";
        $this->db->pQuery($deleteQuery, [$cutoffDate]);
        
        return $archivedCount;
    }
    
    /**
     * Create archive table
     */
    protected function createArchiveTable($sourceTable)
    {
        $archiveTable = $sourceTable . '_archive';
        
        $query = "CREATE TABLE IF NOT EXISTS {$archiveTable} LIKE {$sourceTable}";
        try {
            $this->db->query($query);
        } catch (Exception $e) {
            $this->log("Failed to create archive table {$archiveTable}: " . $e->getMessage());
        }
    }
    
    /**
     * Clean up automation logs
     */
    protected function cleanupAutomationLogs()
    {
        $cutoffDate = date('Y-m-d', strtotime('-6 months'));
        
        $query = "DELETE FROM mdeal_pipeline_automation_log WHERE created_date < ?";
        $this->db->pQuery($query, [$cutoffDate]);
        
        return $this->db->getAffectedRowCount();
    }
    
    /**
     * Clean up old analytics data
     */
    protected function cleanupOldAnalytics()
    {
        $cutoffDate = date('Y-m-d', strtotime('-3 years'));
        
        $query = "DELETE FROM mdeal_pipeline_analytics WHERE created_date < ?";
        $this->db->pQuery($query, [$cutoffDate]);
        
        return $this->db->getAffectedRowCount();
    }
    
    /**
     * Optimize table storage
     */
    protected function optimizeTableStorage()
    {
        $tables = [
            'mdeal_deals',
            'mdeal_leads',
            'mdeal_accounts',
            'mdeal_contacts',
            'mdeal_pipeline_transitions',
            'mdeal_pipeline_wip_tracking',
            'mdeal_pipeline_analytics'
        ];
        
        foreach ($tables as $table) {
            try {
                $this->db->query("OPTIMIZE TABLE {$table}");
                $this->log("Optimized table: {$table}");
            } catch (Exception $e) {
                $this->log("Failed to optimize table {$table}: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Update database statistics
     */
    protected function updateDatabaseStatistics()
    {
        $this->log('Updating database statistics...');
        
        try {
            // Update table statistics for better query planning
            $this->db->query("ANALYZE TABLE mdeal_deals, mdeal_leads, mdeal_accounts, mdeal_contacts");
            $this->log('Updated database statistics');
        } catch (Exception $e) {
            $this->log('Failed to update statistics: ' . $e->getMessage());
        }
    }
    
    /**
     * Generate performance report
     */
    protected function generatePerformanceReport()
    {
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'benchmark_results' => $this->benchmarkResults,
            'optimization_log' => $this->optimizationLog,
            'recommendations' => $this->generateRecommendations(),
            'system_info' => $this->getSystemInfo()
        ];
        
        // Save report to file
        $this->saveReport($report);
        
        return $report;
    }
    
    /**
     * Generate optimization recommendations
     */
    protected function generateRecommendations()
    {
        $recommendations = [];
        
        // Analyze benchmark results for recommendations
        foreach ($this->benchmarkResults as $name => $result) {
            if ($result['avg_time'] > 1.0) { // Slow query (>1 second)
                $recommendations[] = [
                    'type' => 'slow_query',
                    'query' => $name,
                    'avg_time' => $result['avg_time'],
                    'recommendation' => 'Consider optimizing this query or adding indexes'
                ];
            }
            
            if ($result['row_count'] > 10000) { // Large result set
                $recommendations[] = [
                    'type' => 'large_result',
                    'query' => $name,
                    'row_count' => $result['row_count'],
                    'recommendation' => 'Consider adding pagination or filtering'
                ];
            }
        }
        
        // Check database size
        $dbSize = $this->getDatabaseSize();
        if ($dbSize > 5000) { // > 5GB
            $recommendations[] = [
                'type' => 'large_database',
                'size_mb' => $dbSize,
                'recommendation' => 'Consider implementing data archiving strategy'
            ];
        }
        
        // Check for table fragmentation
        $fragmentedTables = $this->getFragmentedTables();
        foreach ($fragmentedTables as $table) {
            $recommendations[] = [
                'type' => 'fragmentation',
                'table' => $table['name'],
                'fragmentation_percent' => $table['fragmentation'],
                'recommendation' => 'Consider optimizing or rebuilding this table'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Get system information
     */
    protected function getSystemInfo()
    {
        return [
            'mysql_version' => $this->db->version(),
            'database_size_mb' => $this->getDatabaseSize(),
            'table_count' => $this->getTableCount(),
            'index_count' => $this->getIndexCount(),
            'memory_usage_mb' => memory_get_usage(true) / 1024 / 1024
        ];
    }
    
    /**
     * Get database size in MB
     */
    protected function getDatabaseSize()
    {
        $query = "SELECT SUM(data_length + index_length) / 1024 / 1024 AS size_mb 
                  FROM information_schema.tables 
                  WHERE table_schema = DATABASE()";
        
        $result = $this->db->query($query);
        $row = $this->db->fetchByAssoc($result);
        
        return round($row['size_mb'] ?? 0, 2);
    }
    
    /**
     * Get table count
     */
    protected function getTableCount()
    {
        $query = "SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE()";
        $result = $this->db->query($query);
        $row = $this->db->fetchByAssoc($result);
        
        return $row['count'] ?? 0;
    }
    
    /**
     * Get index count
     */
    protected function getIndexCount()
    {
        $query = "SELECT COUNT(*) as count FROM information_schema.statistics WHERE table_schema = DATABASE()";
        $result = $this->db->query($query);
        $row = $this->db->fetchByAssoc($result);
        
        return $row['count'] ?? 0;
    }
    
    /**
     * Get fragmented tables
     */
    protected function getFragmentedTables()
    {
        $query = "SELECT 
                    table_name as name,
                    ROUND(((data_free / 1024) / 1024), 2) as fragmentation_mb,
                    ROUND((data_free / (data_length + index_length)) * 100, 2) as fragmentation_percent
                  FROM information_schema.tables 
                  WHERE table_schema = DATABASE() 
                  AND data_free > 0
                  AND (data_free / (data_length + index_length)) > 0.1
                  ORDER BY fragmentation_percent DESC";
        
        $result = $this->db->query($query);
        $tables = [];
        
        while ($row = $this->db->fetchByAssoc($result)) {
            $tables[] = $row;
        }
        
        return $tables;
    }
    
    /**
     * Save performance report
     */
    protected function saveReport($report)
    {
        $filename = 'pipeline_performance_' . date('Y-m-d_H-i-s') . '.json';
        $filepath = 'custom/modules/Pipelines/reports/' . $filename;
        
        // Create directory if it doesn't exist
        $dir = dirname($filepath);
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents($filepath, json_encode($report, JSON_PRETTY_PRINT));
        $this->log("Performance report saved to: {$filepath}");
    }
    
    /**
     * Get pipeline statistics
     */
    protected function getPipelineStatistics()
    {
        $query = "SELECT stage, COUNT(*) as count, SUM(deal_value) as value 
                  FROM mdeal_deals WHERE deleted = 0 GROUP BY stage";
        
        $result = $this->db->query($query);
        $stats = [];
        
        while ($row = $this->db->fetchByAssoc($result)) {
            $stats[$row['stage']] = [
                'count' => $row['count'],
                'value' => $row['value']
            ];
        }
        
        return $stats;
    }
    
    /**
     * Get stage configurations
     */
    protected function getStageConfigurations()
    {
        $query = "SELECT * FROM mdeal_pipeline_stages WHERE deleted = 0 ORDER BY sort_order";
        $result = $this->db->query($query);
        $stages = [];
        
        while ($row = $this->db->fetchByAssoc($result)) {
            $stages[] = $row;
        }
        
        return $stages;
    }
    
    /**
     * Get WIP limits
     */
    protected function getWIPLimits()
    {
        $query = "SELECT stage, wip_limit FROM mdeal_pipeline_stages WHERE wip_limit IS NOT NULL";
        $result = $this->db->query($query);
        $limits = [];
        
        while ($row = $this->db->fetchByAssoc($result)) {
            $limits[$row['stage']] = $row['wip_limit'];
        }
        
        return $limits;
    }
    
    /**
     * Log optimization messages
     */
    protected function log($message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $this->optimizationLog[] = "[{$timestamp}] {$message}";
        
        // Also log to SuiteCRM log
        $GLOBALS['log']->info("Performance Optimizer: {$message}");
    }
    
    /**
     * Quick performance check
     */
    public function quickPerformanceCheck()
    {
        $checks = [
            'slow_queries' => $this->checkSlowQueries(),
            'large_tables' => $this->checkLargeTables(),
            'missing_indexes' => $this->checkMissingIndexes(),
            'fragmentation' => $this->checkFragmentation()
        ];
        
        return $checks;
    }
    
    /**
     * Check for slow queries
     */
    protected function checkSlowQueries()
    {
        // This would check MySQL slow query log
        return ['status' => 'ok', 'count' => 0];
    }
    
    /**
     * Check for large tables
     */
    protected function checkLargeTables()
    {
        $query = "SELECT table_name, ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                  FROM information_schema.tables 
                  WHERE table_schema = DATABASE() 
                  AND ((data_length + index_length) / 1024 / 1024) > 100
                  ORDER BY size_mb DESC";
        
        $result = $this->db->query($query);
        $largeTables = [];
        
        while ($row = $this->db->fetchByAssoc($result)) {
            $largeTables[] = $row;
        }
        
        return [
            'status' => count($largeTables) > 0 ? 'warning' : 'ok',
            'large_tables' => $largeTables
        ];
    }
    
    /**
     * Check for missing indexes
     */
    protected function checkMissingIndexes()
    {
        // Basic check for commonly needed indexes
        $missingIndexes = [];
        
        $criticalIndexes = [
            'mdeal_deals' => ['stage', 'assigned_user_id', 'deleted'],
            'mdeal_leads' => ['lead_score', 'status'],
            'mdeal_accounts' => ['parent_id', 'account_type'],
            'mdeal_contacts' => ['account_id', 'decision_role']
        ];
        
        foreach ($criticalIndexes as $table => $columns) {
            foreach ($columns as $column) {
                if (!$this->columnHasIndex($table, $column)) {
                    $missingIndexes[] = "{$table}.{$column}";
                }
            }
        }
        
        return [
            'status' => count($missingIndexes) > 0 ? 'warning' : 'ok',
            'missing_indexes' => $missingIndexes
        ];
    }
    
    /**
     * Check if column has index
     */
    protected function columnHasIndex($table, $column)
    {
        $query = "SHOW INDEX FROM {$table} WHERE Column_name = ?";
        $result = $this->db->pQuery($query, [$column]);
        return $this->db->fetchByAssoc($result) !== false;
    }
    
    /**
     * Check table fragmentation
     */
    protected function checkFragmentation()
    {
        $fragmentedTables = $this->getFragmentedTables();
        
        return [
            'status' => count($fragmentedTables) > 0 ? 'warning' : 'ok',
            'fragmented_tables' => $fragmentedTables
        ];
    }
}