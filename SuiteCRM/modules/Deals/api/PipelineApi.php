<?php
/**
 * Pipeline API for SuiteCRM Deals Module
 * 
 * Provides RESTful endpoints for pipeline operations including:
 * - Stage management and deal retrieval
 * - Deal movement between stages
 * - Focus flag toggling
 * - Pipeline metrics and analytics
 * 
 * @category  API
 * @package   Deals
 * @author    MakeDealCRM
 * @license   GPL-3.0+
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once 'include/api/SugarApi.php';
require_once 'include/SugarQuery/SugarQuery.php';
require_once 'modules/ACL/ACLController.php';

class PipelineApi extends SugarApi
{
    /**
     * Register API endpoints
     */
    public function registerApiRest()
    {
        return array(
            'getPipelineStages' => array(
                'reqType' => 'GET',
                'path' => array('Deals', 'pipeline', 'stages'),
                'pathVars' => array('module', 'pipeline', 'stages'),
                'method' => 'getPipelineStages',
                'shortHelp' => 'Get all pipeline stages with deal counts',
                'longHelp' => 'Returns all pipeline stages with associated deal counts and metadata',
            ),
            'getPipelineDeals' => array(
                'reqType' => 'GET',
                'path' => array('Deals', 'pipeline', 'deals'),
                'pathVars' => array('module', 'pipeline', 'deals'),
                'method' => 'getPipelineDeals',
                'shortHelp' => 'Get deals by stage with pagination',
                'longHelp' => 'Returns deals filtered by stage with pagination support',
            ),
            'moveDeal' => array(
                'reqType' => 'POST',
                'path' => array('Deals', 'pipeline', 'move'),
                'pathVars' => array('module', 'pipeline', 'move'),
                'method' => 'moveDeal',
                'shortHelp' => 'Move deal to different stage',
                'longHelp' => 'Moves a deal to a different pipeline stage and logs the change',
            ),
            'toggleFocus' => array(
                'reqType' => 'POST',
                'path' => array('Deals', 'pipeline', 'focus'),
                'pathVars' => array('module', 'pipeline', 'focus'),
                'method' => 'toggleFocus',
                'shortHelp' => 'Toggle focus flag on a deal',
                'longHelp' => 'Toggles the focus flag for a deal to highlight it in the pipeline',
            ),
            'getPipelineMetrics' => array(
                'reqType' => 'GET',
                'path' => array('Deals', 'pipeline', 'metrics'),
                'pathVars' => array('module', 'pipeline', 'metrics'),
                'method' => 'getPipelineMetrics',
                'shortHelp' => 'Get pipeline metrics',
                'longHelp' => 'Returns pipeline metrics including average time in stage and conversion rates',
            ),
        );
    }

    /**
     * Get all pipeline stages with deal counts
     * 
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function getPipelineStages($api, $args)
    {
        $this->requireArgs($args, array('module'));
        
        // Check ACL
        if (!ACLController::checkAccess('Deals', 'list', true)) {
            throw new SugarApiExceptionNotAuthorized('No access to view Deals');
        }

        global $db, $app_list_strings;
        
        $stages = array();
        $stageOptions = $app_list_strings['pipeline_stage_dom'] ?? array();
        
        // Get deal counts by stage
        $query = "SELECT pipeline_stage, COUNT(*) as count 
                  FROM deals 
                  WHERE deleted = 0 
                  GROUP BY pipeline_stage";
        
        $result = $db->query($query);
        $counts = array();
        
        while ($row = $db->fetchByAssoc($result)) {
            $counts[$row['pipeline_stage']] = (int)$row['count'];
        }
        
        // Build stage data
        foreach ($stageOptions as $key => $label) {
            $stages[] = array(
                'id' => $key,
                'name' => $label,
                'count' => $counts[$key] ?? 0,
                'order' => array_search($key, array_keys($stageOptions)),
            );
        }
        
        return array(
            'success' => true,
            'stages' => $stages,
            'total_deals' => array_sum($counts),
        );
    }

    /**
     * Get deals by stage with pagination
     * 
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function getPipelineDeals($api, $args)
    {
        $this->requireArgs($args, array('module'));
        
        // Check ACL
        if (!ACLController::checkAccess('Deals', 'list', true)) {
            throw new SugarApiExceptionNotAuthorized('No access to view Deals');
        }

        $stage = $args['stage'] ?? null;
        $offset = (int)($args['offset'] ?? 0);
        $limit = (int)($args['limit'] ?? 20);
        $limit = min($limit, 100); // Cap at 100 records
        
        global $db;
        
        // Build query
        $where = "d.deleted = 0";
        if ($stage !== null && $stage !== '') {
            $where .= " AND d.pipeline_stage = " . $db->quoted($stage);
        }
        
        // Get total count
        $countQuery = "SELECT COUNT(*) as total FROM deals d WHERE $where";
        $countResult = $db->query($countQuery);
        $countRow = $db->fetchByAssoc($countResult);
        $total = (int)$countRow['total'];
        
        // Get deals with related data
        $query = "SELECT d.*, 
                         a.name as account_name,
                         u.user_name as assigned_user_name,
                         u.first_name as assigned_user_first_name,
                         u.last_name as assigned_user_last_name
                  FROM deals d
                  LEFT JOIN accounts a ON d.account_id = a.id AND a.deleted = 0
                  LEFT JOIN users u ON d.assigned_user_id = u.id AND u.deleted = 0
                  WHERE $where
                  ORDER BY d.pipeline_focus DESC, d.amount DESC
                  LIMIT $limit OFFSET $offset";
        
        $result = $db->query($query);
        $deals = array();
        
        while ($row = $db->fetchByAssoc($result)) {
            $deals[] = $this->formatDealData($row);
        }
        
        return array(
            'success' => true,
            'records' => $deals,
            'total' => $total,
            'offset' => $offset,
            'limit' => $limit,
            'has_more' => ($offset + $limit) < $total,
        );
    }

    /**
     * Move deal to different stage
     * 
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function moveDeal($api, $args)
    {
        $this->requireArgs($args, array('module', 'deal_id', 'new_stage'));
        
        $dealId = $args['deal_id'];
        $newStage = $args['new_stage'];
        
        // Load deal
        $deal = BeanFactory::getBean('Deals', $dealId);
        if (empty($deal->id)) {
            throw new SugarApiExceptionNotFound('Deal not found');
        }
        
        // Check ACL
        if (!$deal->ACLAccess('save')) {
            throw new SugarApiExceptionNotAuthorized('No access to modify this Deal');
        }
        
        // Store old stage for history
        $oldStage = $deal->pipeline_stage;
        
        // Update stage
        $deal->pipeline_stage = $newStage;
        $deal->save();
        
        // Log stage change to history
        $this->logStageChange($dealId, $oldStage, $newStage);
        
        return array(
            'success' => true,
            'deal_id' => $dealId,
            'old_stage' => $oldStage,
            'new_stage' => $newStage,
            'message' => 'Deal moved successfully',
        );
    }

    /**
     * Toggle focus flag on a deal
     * 
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function toggleFocus($api, $args)
    {
        $this->requireArgs($args, array('module', 'deal_id'));
        
        $dealId = $args['deal_id'];
        
        // Load deal
        $deal = BeanFactory::getBean('Deals', $dealId);
        if (empty($deal->id)) {
            throw new SugarApiExceptionNotFound('Deal not found');
        }
        
        // Check ACL
        if (!$deal->ACLAccess('save')) {
            throw new SugarApiExceptionNotAuthorized('No access to modify this Deal');
        }
        
        // Toggle focus
        $deal->pipeline_focus = !$deal->pipeline_focus;
        $deal->save();
        
        return array(
            'success' => true,
            'deal_id' => $dealId,
            'focus' => (bool)$deal->pipeline_focus,
            'message' => $deal->pipeline_focus ? 'Deal marked as focus' : 'Focus removed from deal',
        );
    }

    /**
     * Get pipeline metrics
     * 
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function getPipelineMetrics($api, $args)
    {
        $this->requireArgs($args, array('module'));
        
        // Check ACL
        if (!ACLController::checkAccess('Deals', 'list', true)) {
            throw new SugarApiExceptionNotAuthorized('No access to view Deals');
        }
        
        global $db, $app_list_strings;
        
        $metrics = array(
            'conversion_rates' => array(),
            'average_time_in_stage' => array(),
            'total_pipeline_value' => 0,
            'average_deal_size' => 0,
            'deals_by_stage' => array(),
        );
        
        // Get pipeline stages
        $stages = array_keys($app_list_strings['pipeline_stage_dom'] ?? array());
        
        // Calculate conversion rates between stages
        $conversionRates = $this->calculateConversionRates($stages);
        $metrics['conversion_rates'] = $conversionRates;
        
        // Calculate average time in each stage
        $avgTimeInStage = $this->calculateAverageTimeInStage($stages);
        $metrics['average_time_in_stage'] = $avgTimeInStage;
        
        // Calculate total pipeline value and average deal size
        $valueQuery = "SELECT 
                        SUM(amount) as total_value,
                        AVG(amount) as avg_deal_size,
                        COUNT(*) as total_deals,
                        pipeline_stage
                       FROM deals
                       WHERE deleted = 0 AND amount > 0
                       GROUP BY pipeline_stage";
        
        $result = $db->query($valueQuery);
        
        while ($row = $db->fetchByAssoc($result)) {
            $metrics['total_pipeline_value'] += (float)$row['total_value'];
            $metrics['deals_by_stage'][$row['pipeline_stage']] = array(
                'count' => (int)$row['total_deals'],
                'total_value' => (float)$row['total_value'],
                'avg_value' => (float)$row['avg_deal_size'],
            );
        }
        
        // Calculate overall average deal size
        $avgQuery = "SELECT AVG(amount) as avg_deal_size 
                     FROM deals 
                     WHERE deleted = 0 AND amount > 0";
        $avgResult = $db->query($avgQuery);
        $avgRow = $db->fetchByAssoc($avgResult);
        $metrics['average_deal_size'] = (float)$avgRow['avg_deal_size'];
        
        return array(
            'success' => true,
            'metrics' => $metrics,
            'generated_at' => date('Y-m-d H:i:s'),
        );
    }

    /**
     * Format deal data for API response
     * 
     * @param array $row
     * @return array
     */
    private function formatDealData($row)
    {
        return array(
            'id' => $row['id'],
            'name' => $row['name'],
            'amount' => (float)$row['amount'],
            'pipeline_stage' => $row['pipeline_stage'],
            'pipeline_focus' => (bool)$row['pipeline_focus'],
            'account_id' => $row['account_id'],
            'account_name' => $row['account_name'],
            'assigned_user_id' => $row['assigned_user_id'],
            'assigned_user_name' => trim($row['assigned_user_first_name'] . ' ' . $row['assigned_user_last_name']),
            'date_entered' => $row['date_entered'],
            'date_modified' => $row['date_modified'],
            'description' => $row['description'],
        );
    }

    /**
     * Log stage change to history table
     * 
     * @param string $dealId
     * @param string $oldStage
     * @param string $newStage
     */
    private function logStageChange($dealId, $oldStage, $newStage)
    {
        global $db, $current_user;
        
        $historyId = create_guid();
        $query = "INSERT INTO pipeline_stage_history 
                  (id, deal_id, old_stage, new_stage, changed_by, date_changed, created_by, date_entered)
                  VALUES (?, ?, ?, ?, ?, NOW(), ?, NOW())";
        
        $db->pQuery($query, array(
            $historyId,
            $dealId,
            $oldStage,
            $newStage,
            $current_user->id,
            $current_user->id,
        ));
    }

    /**
     * Calculate conversion rates between pipeline stages
     * 
     * @param array $stages
     * @return array
     */
    private function calculateConversionRates($stages)
    {
        global $db;
        
        $rates = array();
        
        // For each stage pair, calculate conversion rate
        for ($i = 0; $i < count($stages) - 1; $i++) {
            $currentStage = $stages[$i];
            $nextStage = $stages[$i + 1];
            
            // Count deals that moved from current to next stage
            $query = "SELECT COUNT(DISTINCT h.deal_id) as converted
                      FROM pipeline_stage_history h
                      WHERE h.old_stage = ? AND h.new_stage = ?";
            
            $result = $db->pQuery($query, array($currentStage, $nextStage));
            $row = $db->fetchByAssoc($result);
            $converted = (int)$row['converted'];
            
            // Count total deals that were ever in current stage
            $totalQuery = "SELECT COUNT(DISTINCT deal_id) as total
                           FROM (
                               SELECT id as deal_id FROM deals WHERE pipeline_stage = ? AND deleted = 0
                               UNION
                               SELECT deal_id FROM pipeline_stage_history WHERE old_stage = ? OR new_stage = ?
                           ) as stage_deals";
            
            $totalResult = $db->pQuery($totalQuery, array($currentStage, $currentStage, $currentStage));
            $totalRow = $db->fetchByAssoc($totalResult);
            $total = (int)$totalRow['total'];
            
            $rate = $total > 0 ? ($converted / $total) * 100 : 0;
            
            $rates[] = array(
                'from_stage' => $currentStage,
                'to_stage' => $nextStage,
                'conversion_rate' => round($rate, 2),
                'converted_count' => $converted,
                'total_count' => $total,
            );
        }
        
        return $rates;
    }

    /**
     * Calculate average time spent in each stage
     * 
     * @param array $stages
     * @return array
     */
    private function calculateAverageTimeInStage($stages)
    {
        global $db;
        
        $avgTimes = array();
        
        foreach ($stages as $stage) {
            // Calculate time between entering and leaving stage
            $query = "SELECT 
                        AVG(TIMESTAMPDIFF(HOUR, h1.date_changed, h2.date_changed)) as avg_hours
                      FROM pipeline_stage_history h1
                      INNER JOIN pipeline_stage_history h2 
                        ON h1.deal_id = h2.deal_id 
                        AND h1.new_stage = ? 
                        AND h2.old_stage = ?
                        AND h2.date_changed > h1.date_changed
                      WHERE h1.new_stage = ?";
            
            $result = $db->pQuery($query, array($stage, $stage, $stage));
            $row = $db->fetchByAssoc($result);
            
            $avgHours = (float)($row['avg_hours'] ?? 0);
            $avgDays = $avgHours > 0 ? round($avgHours / 24, 1) : 0;
            
            $avgTimes[$stage] = array(
                'hours' => round($avgHours, 1),
                'days' => $avgDays,
            );
        }
        
        return $avgTimes;
    }
}