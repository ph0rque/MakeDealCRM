<?php
/**
 * Optimized Pipeline API for Large Datasets
 * 
 * Provides high-performance endpoints with:
 * - Query optimization and indexing
 * - Intelligent caching
 * - Pagination and lazy loading
 * - Aggregated metrics
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once 'include/api/SugarApi.php';
require_once 'include/SugarQuery/SugarQuery.php';

class OptimizedPipelineApi extends SugarApi
{
    private $cacheManager;
    
    public function __construct()
    {
        parent::__construct();
        $this->cacheManager = new PipelineCacheManager();
    }
    
    /**
     * Register optimized API endpoints
     */
    public function registerApiRest()
    {
        return array(
            'getOptimizedDeals' => array(
                'reqType' => 'GET',
                'path' => array('Deals', 'optimized', 'deals'),
                'pathVars' => array('module', 'optimized', 'deals'),
                'method' => 'getOptimizedDeals',
                'shortHelp' => 'Get deals with performance optimizations',
                'longHelp' => 'Returns paginated deals with caching and query optimization',
            ),
            'getStageMetrics' => array(
                'reqType' => 'GET',
                'path' => array('Deals', 'optimized', 'metrics'),
                'pathVars' => array('module', 'optimized', 'metrics'),
                'method' => 'getStageMetrics',
                'shortHelp' => 'Get aggregated stage metrics',
                'longHelp' => 'Returns cached aggregated metrics for all pipeline stages',
            ),
            'batchUpdateDeals' => array(
                'reqType' => 'POST',
                'path' => array('Deals', 'optimized', 'batch'),
                'pathVars' => array('module', 'optimized', 'batch'),
                'method' => 'batchUpdateDeals',
                'shortHelp' => 'Batch update multiple deals',
                'longHelp' => 'Efficiently update multiple deals in a single transaction',
            ),
            'preloadStageData' => array(
                'reqType' => 'POST',
                'path' => array('Deals', 'optimized', 'preload'),
                'pathVars' => array('module', 'optimized', 'preload'),
                'method' => 'preloadStageData',
                'shortHelp' => 'Preload data for multiple stages',
                'longHelp' => 'Preload and cache data for multiple pipeline stages',
            ),
        );
    }

    /**
     * Get optimized deals with pagination and caching
     */
    public function getOptimizedDeals($api, $args)
    {
        $this->requireArgs($args, array('module'));
        
        // Check ACL
        if (!ACLController::checkAccess('Deals', 'list', true)) {
            throw new SugarApiExceptionNotAuthorized('No access to view Deals');
        }

        // Parse parameters
        $stage = $args['stage'] ?? null;
        $offset = max(0, (int)($args['offset'] ?? 0));
        $limit = min(100, max(1, (int)($args['limit'] ?? 20)));
        $sortBy = $args['sort_by'] ?? 'focus_order';
        $sortOrder = strtolower($args['sort_order'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
        $useCache = !isset($args['no_cache']) || $args['no_cache'] !== '1';
        
        // Build filters
        $filters = $this->parseFilters($args);
        
        // Generate cache key
        $cacheKey = $this->generateCacheKey('deals', $stage, $offset, $limit, $sortBy, $sortOrder, $filters);
        
        // Check cache first
        if ($useCache && $cachedData = $this->cacheManager->get($cacheKey)) {
            return $this->formatSuccessResponse($cachedData, true);
        }

        try {
            // Get optimized deals
            $result = $this->fetchOptimizedDeals($stage, $offset, $limit, $sortBy, $sortOrder, $filters);
            
            // Cache the result
            if ($useCache) {
                $this->cacheManager->set($cacheKey, $result, 300); // 5 minutes
            }
            
            return $this->formatSuccessResponse($result, false);
            
        } catch (Exception $e) {
            $GLOBALS['log']->error("OptimizedPipelineApi::getOptimizedDeals - Error: " . $e->getMessage());
            throw new SugarApiExceptionError('Failed to fetch deals');
        }
    }
    
    /**
     * Fetch optimized deals using efficient queries
     */
    private function fetchOptimizedDeals($stage, $offset, $limit, $sortBy, $sortOrder, $filters)
    {
        global $db;
        
        // Build optimized query with proper indexing hints
        $query = $this->buildOptimizedQuery($stage, $sortBy, $sortOrder, $filters);
        
        // Get total count (with LIMIT for safety)
        $countQuery = $this->buildCountQuery($stage, $filters);
        $countResult = $db->query($countQuery);
        $countRow = $db->fetchByAssoc($countResult);
        $total = min(10000, (int)$countRow['total']); // Cap at 10k for performance
        
        // Get paginated results
        $dataQuery = $query . " LIMIT $limit OFFSET $offset";
        $result = $db->query($dataQuery);
        
        $deals = array();
        $batchIds = array();
        
        while ($row = $db->fetchByAssoc($result)) {
            $batchIds[] = "'" . $row['id'] . "'";
            $deals[] = $this->formatDealRecord($row);
        }
        
        // Batch load additional data if needed
        if (!empty($batchIds) && !empty($filters['include_related'])) {
            $this->loadRelatedData($deals, $batchIds, $filters['include_related']);
        }
        
        return array(
            'records' => $deals,
            'total' => $total,
            'offset' => $offset,
            'limit' => $limit,
            'has_more' => ($offset + $limit) < $total,
            'stage' => $stage,
            'query_time' => microtime(true) - $GLOBALS['query_start_time'] ?? 0
        );
    }
    
    /**
     * Build optimized query with proper indexes
     */
    private function buildOptimizedQuery($stage, $sortBy, $sortOrder, $filters)
    {
        global $db;
        
        // Use index hints for better performance
        $query = "SELECT /*+ USE_INDEX(opportunities, idx_opp_pipeline_stage) */
                    d.id,
                    d.name,
                    d.amount,
                    d.sales_stage,
                    d.date_modified,
                    d.assigned_user_id,
                    c.pipeline_stage_c,
                    c.stage_entered_date_c,
                    c.focus_flag_c,
                    c.focus_order_c,
                    c.expected_close_date_c,
                    d.probability,
                    a.name as account_name,
                    CONCAT(u.first_name, ' ', u.last_name) as assigned_user_name,
                    TIMESTAMPDIFF(DAY, 
                        COALESCE(c.stage_entered_date_c, d.date_modified), 
                        NOW()
                    ) as days_in_stage,
                    CASE 
                        WHEN TIMESTAMPDIFF(DAY, COALESCE(c.stage_entered_date_c, d.date_modified), NOW()) > 30 
                        THEN 'stage-red'
                        WHEN TIMESTAMPDIFF(DAY, COALESCE(c.stage_entered_date_c, d.date_modified), NOW()) > 14 
                        THEN 'stage-orange'
                        ELSE 'stage-normal'
                    END as stage_color_class
                  FROM opportunities d
                  INNER JOIN opportunities_cstm c ON d.id = c.id_c
                  LEFT JOIN accounts a ON d.account_id = a.id AND a.deleted = 0
                  LEFT JOIN users u ON d.assigned_user_id = u.id AND u.deleted = 0
                  WHERE d.deleted = 0
                  AND d.sales_stage NOT IN ('Closed Won', 'Closed Lost')";
        
        // Add stage filter
        if ($stage !== null && $stage !== '') {
            $query .= " AND c.pipeline_stage_c = " . $db->quoted($stage);
        }
        
        // Add additional filters
        $query .= $this->buildFilterConditions($filters);
        
        // Add sorting with proper index usage
        $query .= $this->buildSortClause($sortBy, $sortOrder);
        
        return $query;
    }
    
    /**
     * Build count query for pagination
     */
    private function buildCountQuery($stage, $filters)
    {
        global $db;
        
        $query = "SELECT COUNT(*) as total
                  FROM opportunities d
                  INNER JOIN opportunities_cstm c ON d.id = c.id_c
                  WHERE d.deleted = 0
                  AND d.sales_stage NOT IN ('Closed Won', 'Closed Lost')";
        
        if ($stage !== null && $stage !== '') {
            $query .= " AND c.pipeline_stage_c = " . $db->quoted($stage);
        }
        
        $query .= $this->buildFilterConditions($filters);
        
        return $query;
    }
    
    /**
     * Build filter conditions
     */
    private function buildFilterConditions($filters)
    {
        global $db;
        $conditions = '';
        
        if (!empty($filters['focus_only'])) {
            $conditions .= " AND c.focus_flag_c = 1";
        }
        
        if (!empty($filters['assigned_user'])) {
            $conditions .= " AND d.assigned_user_id = " . $db->quoted($filters['assigned_user']);
        }
        
        if (!empty($filters['amount_min'])) {
            $conditions .= " AND d.amount >= " . (float)$filters['amount_min'];
        }
        
        if (!empty($filters['amount_max'])) {
            $conditions .= " AND d.amount <= " . (float)$filters['amount_max'];
        }
        
        if (!empty($filters['probability_min'])) {
            $conditions .= " AND d.probability >= " . (int)$filters['probability_min'];
        }
        
        if (!empty($filters['days_in_stage_max'])) {
            $conditions .= " AND TIMESTAMPDIFF(DAY, COALESCE(c.stage_entered_date_c, d.date_modified), NOW()) <= " . 
                          (int)$filters['days_in_stage_max'];
        }
        
        if (!empty($filters['search'])) {
            $searchTerm = $db->quoted('%' . $filters['search'] . '%');
            $conditions .= " AND (d.name LIKE $searchTerm OR a.name LIKE $searchTerm)";
        }
        
        return $conditions;
    }
    
    /**
     * Build sort clause with index optimization
     */
    private function buildSortClause($sortBy, $sortOrder)
    {
        $sortMap = array(
            'focus_order' => 'c.focus_flag_c DESC, c.focus_order_c',
            'name' => 'd.name',
            'amount' => 'd.amount',
            'probability' => 'd.probability',
            'days_in_stage' => 'TIMESTAMPDIFF(DAY, COALESCE(c.stage_entered_date_c, d.date_modified), NOW())',
            'date_modified' => 'd.date_modified',
            'assigned_user' => 'assigned_user_name'
        );
        
        $sortField = $sortMap[$sortBy] ?? $sortMap['focus_order'];
        
        return " ORDER BY $sortField $sortOrder, d.date_modified DESC";
    }
    
    /**
     * Format deal record for response
     */
    private function formatDealRecord($row)
    {
        return array(
            'id' => $row['id'],
            'name' => $row['name'],
            'amount' => (float)$row['amount'],
            'probability' => (int)$row['probability'],
            'sales_stage' => $row['sales_stage'],
            'pipeline_stage_c' => $row['pipeline_stage_c'],
            'stage_entered_date_c' => $row['stage_entered_date_c'],
            'focus_flag_c' => (bool)$row['focus_flag_c'],
            'focus_order_c' => (int)$row['focus_order_c'],
            'expected_close_date_c' => $row['expected_close_date_c'],
            'account_name' => $row['account_name'],
            'assigned_user_id' => $row['assigned_user_id'],
            'assigned_user_name' => $row['assigned_user_name'],
            'date_modified' => $row['date_modified'],
            'days_in_stage' => (int)$row['days_in_stage'],
            'stage_color_class' => $row['stage_color_class']
        );
    }
    
    /**
     * Get aggregated stage metrics with caching
     */
    public function getStageMetrics($api, $args)
    {
        $this->requireArgs($args, array('module'));
        
        if (!ACLController::checkAccess('Deals', 'list', true)) {
            throw new SugarApiExceptionNotAuthorized('No access to view Deals');
        }

        $useCache = !isset($args['no_cache']) || $args['no_cache'] !== '1';
        $cacheKey = 'stage_metrics_' . md5(serialize($args));
        
        // Check cache
        if ($useCache && $cachedData = $this->cacheManager->get($cacheKey)) {
            return $this->formatSuccessResponse($cachedData, true);
        }

        try {
            $metrics = $this->calculateStageMetrics();
            
            // Cache for 10 minutes
            if ($useCache) {
                $this->cacheManager->set($cacheKey, $metrics, 600);
            }
            
            return $this->formatSuccessResponse($metrics, false);
            
        } catch (Exception $e) {
            $GLOBALS['log']->error("OptimizedPipelineApi::getStageMetrics - Error: " . $e->getMessage());
            throw new SugarApiExceptionError('Failed to calculate metrics');
        }
    }
    
    /**
     * Calculate stage metrics efficiently
     */
    private function calculateStageMetrics()
    {
        global $db;
        
        // Single query to get all stage metrics
        $query = "SELECT 
                    c.pipeline_stage_c as stage,
                    COUNT(*) as deal_count,
                    SUM(d.amount) as total_value,
                    AVG(d.amount) as avg_value,
                    MIN(d.amount) as min_value,
                    MAX(d.amount) as max_value,
                    AVG(d.probability) as avg_probability,
                    SUM(CASE WHEN c.focus_flag_c = 1 THEN 1 ELSE 0 END) as focused_deals,
                    AVG(TIMESTAMPDIFF(DAY, COALESCE(c.stage_entered_date_c, d.date_modified), NOW())) as avg_days_in_stage,
                    COUNT(CASE WHEN TIMESTAMPDIFF(DAY, COALESCE(c.stage_entered_date_c, d.date_modified), NOW()) > 30 THEN 1 END) as overdue_deals
                  FROM opportunities d
                  INNER JOIN opportunities_cstm c ON d.id = c.id_c
                  WHERE d.deleted = 0
                  AND d.sales_stage NOT IN ('Closed Won', 'Closed Lost')
                  GROUP BY c.pipeline_stage_c
                  ORDER BY c.pipeline_stage_c";
        
        $result = $db->query($query);
        $stageMetrics = array();
        $totals = array(
            'deal_count' => 0,
            'total_value' => 0,
            'focused_deals' => 0,
            'overdue_deals' => 0
        );
        
        while ($row = $db->fetchByAssoc($result)) {
            $stage = $row['stage'];
            $metrics = array(
                'deal_count' => (int)$row['deal_count'],
                'total_value' => (float)$row['total_value'],
                'avg_value' => (float)$row['avg_value'],
                'min_value' => (float)$row['min_value'],
                'max_value' => (float)$row['max_value'],
                'avg_probability' => round((float)$row['avg_probability'], 1),
                'focused_deals' => (int)$row['focused_deals'],
                'avg_days_in_stage' => round((float)$row['avg_days_in_stage'], 1),
                'overdue_deals' => (int)$row['overdue_deals']
            );
            
            // Calculate percentages
            $metrics['overdue_percentage'] = $metrics['deal_count'] > 0 ? 
                round(($metrics['overdue_deals'] / $metrics['deal_count']) * 100, 1) : 0;
            $metrics['focus_percentage'] = $metrics['deal_count'] > 0 ? 
                round(($metrics['focused_deals'] / $metrics['deal_count']) * 100, 1) : 0;
            
            $stageMetrics[$stage] = $metrics;
            
            // Add to totals
            $totals['deal_count'] += $metrics['deal_count'];
            $totals['total_value'] += $metrics['total_value'];
            $totals['focused_deals'] += $metrics['focused_deals'];
            $totals['overdue_deals'] += $metrics['overdue_deals'];
        }
        
        return array(
            'stages' => $stageMetrics,
            'totals' => $totals,
            'generated_at' => date('Y-m-d H:i:s')
        );
    }
    
    /**
     * Batch update multiple deals
     */
    public function batchUpdateDeals($api, $args)
    {
        $this->requireArgs($args, array('module', 'updates'));
        
        if (!ACLController::checkAccess('Deals', 'edit', true)) {
            throw new SugarApiExceptionNotAuthorized('No access to edit Deals');
        }

        $updates = $args['updates'];
        if (!is_array($updates) || empty($updates)) {
            throw new SugarApiExceptionMissingParameter('No updates provided');
        }
        
        if (count($updates) > 100) {
            throw new SugarApiExceptionRequestTooLarge('Too many updates (max 100)');
        }

        global $db;
        $results = array(
            'success' => array(),
            'failed' => array(),
            'total_processed' => 0
        );
        
        // Start transaction
        $db->query('START TRANSACTION');
        
        try {
            foreach ($updates as $update) {
                $dealId = $update['id'] ?? null;
                $changes = $update['changes'] ?? array();
                
                if (!$dealId || empty($changes)) {
                    $results['failed'][] = array(
                        'id' => $dealId,
                        'error' => 'Missing deal ID or changes'
                    );
                    continue;
                }
                
                try {
                    // Load deal
                    $deal = BeanFactory::getBean('Opportunities', $dealId);
                    if (empty($deal->id) || $deal->deleted) {
                        $results['failed'][] = array(
                            'id' => $dealId,
                            'error' => 'Deal not found'
                        );
                        continue;
                    }
                    
                    // Check ACL
                    if (!$deal->ACLAccess('save')) {
                        $results['failed'][] = array(
                            'id' => $dealId,
                            'error' => 'Access denied'
                        );
                        continue;
                    }
                    
                    // Apply changes
                    $applied = $this->applyBatchChanges($deal, $changes);
                    if (!empty($applied)) {
                        $deal->save();
                        $results['success'][] = array(
                            'id' => $dealId,
                            'changes' => $applied
                        );
                    }
                    
                    $results['total_processed']++;
                    
                } catch (Exception $e) {
                    $results['failed'][] = array(
                        'id' => $dealId,
                        'error' => $e->getMessage()
                    );
                }
            }
            
            // Commit transaction
            $db->query('COMMIT');
            
            // Clear relevant caches
            $this->cacheManager->clearPattern('deals_*');
            $this->cacheManager->clearPattern('stage_metrics_*');
            
            return array(
                'success' => true,
                'results' => $results
            );
            
        } catch (Exception $e) {
            $db->query('ROLLBACK');
            throw new SugarApiExceptionError('Batch update failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Apply batch changes to a deal
     */
    private function applyBatchChanges($deal, $changes)
    {
        $allowedFields = array(
            'name', 'amount', 'probability', 'sales_stage',
            'pipeline_stage_c', 'focus_flag_c', 'focus_order_c',
            'expected_close_date_c', 'description'
        );
        
        $applied = array();
        
        foreach ($changes as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $deal->$field = $value;
                $applied[$field] = $value;
                
                // Special handling for stage changes
                if ($field === 'pipeline_stage_c') {
                    $deal->stage_entered_date_c = date('Y-m-d H:i:s');
                    $applied['stage_entered_date_c'] = $deal->stage_entered_date_c;
                }
            }
        }
        
        return $applied;
    }
    
    /**
     * Preload and cache stage data
     */
    public function preloadStageData($api, $args)
    {
        $this->requireArgs($args, array('module', 'stages'));
        
        if (!ACLController::checkAccess('Deals', 'list', true)) {
            throw new SugarApiExceptionNotAuthorized('No access to view Deals');
        }

        $stages = $args['stages'];
        if (!is_array($stages)) {
            throw new SugarApiExceptionMissingParameter('Stages must be an array');
        }
        
        $limit = min(50, (int)($args['limit'] ?? 20));
        $results = array();
        
        foreach ($stages as $stage) {
            try {
                $cacheKey = $this->generateCacheKey('deals', $stage, 0, $limit, 'focus_order', 'ASC', array());
                
                // Skip if already cached
                if ($this->cacheManager->get($cacheKey)) {
                    $results[$stage] = 'already_cached';
                    continue;
                }
                
                // Fetch and cache
                $data = $this->fetchOptimizedDeals($stage, 0, $limit, 'focus_order', 'ASC', array());
                $this->cacheManager->set($cacheKey, $data, 300);
                
                $results[$stage] = 'cached';
                
            } catch (Exception $e) {
                $results[$stage] = 'failed: ' . $e->getMessage();
            }
        }
        
        return array(
            'success' => true,
            'results' => $results
        );
    }
    
    /**
     * Parse filter parameters
     */
    private function parseFilters($args)
    {
        $filters = array();
        
        if (!empty($args['focus_only'])) {
            $filters['focus_only'] = true;
        }
        
        if (!empty($args['assigned_user'])) {
            $filters['assigned_user'] = $args['assigned_user'];
        }
        
        if (isset($args['amount_min']) && is_numeric($args['amount_min'])) {
            $filters['amount_min'] = (float)$args['amount_min'];
        }
        
        if (isset($args['amount_max']) && is_numeric($args['amount_max'])) {
            $filters['amount_max'] = (float)$args['amount_max'];
        }
        
        if (isset($args['probability_min']) && is_numeric($args['probability_min'])) {
            $filters['probability_min'] = (int)$args['probability_min'];
        }
        
        if (isset($args['days_in_stage_max']) && is_numeric($args['days_in_stage_max'])) {
            $filters['days_in_stage_max'] = (int)$args['days_in_stage_max'];
        }
        
        if (!empty($args['search'])) {
            $filters['search'] = trim($args['search']);
        }
        
        if (!empty($args['include_related'])) {
            $filters['include_related'] = explode(',', $args['include_related']);
        }
        
        return $filters;
    }
    
    /**
     * Load related data in batches
     */
    private function loadRelatedData(&$deals, $dealIds, $relations)
    {
        // This could be extended to load related accounts, contacts, etc.
        // For now, we skip it as the main query already includes the most common relations
    }
    
    /**
     * Generate cache key
     */
    private function generateCacheKey($type, $stage, $offset, $limit, $sortBy, $sortOrder, $filters)
    {
        $key = "{$type}_{$stage}_{$offset}_{$limit}_{$sortBy}_{$sortOrder}";
        if (!empty($filters)) {
            $key .= '_' . md5(serialize($filters));
        }
        return $key;
    }
    
    /**
     * Format success response
     */
    private function formatSuccessResponse($data, $cached = false)
    {
        return array(
            'success' => true,
            'cached' => $cached,
            'timestamp' => time(),
            ...$data
        );
    }
}

/**
 * Pipeline Cache Manager
 */
class PipelineCacheManager
{
    private $cacheDir;
    private $defaultTtl = 300; // 5 minutes
    
    public function __construct()
    {
        $this->cacheDir = rtrim($GLOBALS['sugar_config']['cache_dir'] ?? 'cache', '/') . '/pipeline/';
        $this->ensureCacheDir();
    }
    
    /**
     * Ensure cache directory exists
     */
    private function ensureCacheDir()
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * Get cached data
     */
    public function get($key)
    {
        $file = $this->getCacheFile($key);
        
        if (!file_exists($file)) {
            return null;
        }
        
        $data = unserialize(file_get_contents($file));
        
        if (!$data || !isset($data['expires']) || $data['expires'] < time()) {
            $this->delete($key);
            return null;
        }
        
        return $data['data'];
    }
    
    /**
     * Set cached data
     */
    public function set($key, $data, $ttl = null)
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $file = $this->getCacheFile($key);
        
        $cacheData = array(
            'data' => $data,
            'expires' => time() + $ttl,
            'created' => time()
        );
        
        return file_put_contents($file, serialize($cacheData), LOCK_EX) !== false;
    }
    
    /**
     * Delete cached data
     */
    public function delete($key)
    {
        $file = $this->getCacheFile($key);
        
        if (file_exists($file)) {
            return unlink($file);
        }
        
        return true;
    }
    
    /**
     * Clear cache by pattern
     */
    public function clearPattern($pattern)
    {
        $pattern = str_replace('*', '.*', $pattern);
        $files = glob($this->cacheDir . '*');
        $cleared = 0;
        
        foreach ($files as $file) {
            $key = basename($file);
            if (preg_match("/$pattern/", $key)) {
                unlink($file);
                $cleared++;
            }
        }
        
        return $cleared;
    }
    
    /**
     * Clear all cache
     */
    public function clearAll()
    {
        $files = glob($this->cacheDir . '*');
        $cleared = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                $cleared++;
            }
        }
        
        return $cleared;
    }
    
    /**
     * Get cache file path
     */
    private function getCacheFile($key)
    {
        return $this->cacheDir . md5($key) . '.cache';
    }
    
    /**
     * Get cache statistics
     */
    public function getStats()
    {
        $files = glob($this->cacheDir . '*');
        $totalSize = 0;
        $expiredCount = 0;
        $validCount = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $totalSize += filesize($file);
                
                $data = unserialize(file_get_contents($file));
                if ($data && isset($data['expires'])) {
                    if ($data['expires'] < time()) {
                        $expiredCount++;
                    } else {
                        $validCount++;
                    }
                }
            }
        }
        
        return array(
            'total_files' => count($files),
            'total_size' => $totalSize,
            'valid_count' => $validCount,
            'expired_count' => $expiredCount,
            'cache_dir' => $this->cacheDir
        );
    }
}