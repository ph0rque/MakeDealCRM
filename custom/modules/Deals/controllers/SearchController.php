<?php
/**
 * Enhanced Search Controller for Deals Module
 * Provides comprehensive search functionality using SuiteCRM patterns
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('include/MVC/Controller/SugarController.php');
require_once('modules/ACL/ACLController.php');

class DealsSearchController extends SugarController
{
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * Advanced search with multiple criteria
     */
    public function action_advancedSearch()
    {
        global $current_user;
        
        try {
            // Check permissions
            if (!$current_user->id || !ACLController::checkAccess('Deals', 'list', true)) {
                $this->sendJsonResponse(['success' => false, 'message' => 'Unauthorized']);
                return;
            }
            
            $searchCriteria = $this->buildSearchCriteria();
            $results = $this->performAdvancedSearch($searchCriteria);
            
            $this->sendJsonResponse([
                'success' => true,
                'count' => count($results),
                'results' => $results,
                'criteria' => $searchCriteria
            ]);
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('Advanced search failed: ' . $e->getMessage());
            $this->sendJsonResponse(['success' => false, 'message' => 'Search failed']);
        }
    }
    
    /**
     * Quick search for autocomplete
     */
    public function action_quickSearch()
    {
        global $current_user, $db;
        
        try {
            // Check permissions
            if (!$current_user->id || !ACLController::checkAccess('Deals', 'list', true)) {
                $this->sendJsonResponse(['success' => false, 'message' => 'Unauthorized']);
                return;
            }
            
            $query = $db->quote($_GET['query'] ?? '');
            $limit = min((int)($_GET['limit'] ?? 10), 50);
            
            if (strlen($query) < 2) {
                $this->sendJsonResponse(['results' => []]);
                return;
            }
            
            $sql = "SELECT 
                        o.id,
                        o.name,
                        o.amount,
                        o.sales_stage,
                        oc.pipeline_stage_c,
                        a.name as account_name,
                        u.user_name as assigned_user_name
                    FROM opportunities o
                    LEFT JOIN opportunities_cstm oc ON o.id = oc.id_c
                    LEFT JOIN accounts a ON o.account_id = a.id AND a.deleted = 0
                    LEFT JOIN users u ON o.assigned_user_id = u.id AND u.deleted = 0
                    WHERE o.deleted = 0
                    AND (
                        o.name LIKE '%{$query}%' OR
                        a.name LIKE '%{$query}%' OR
                        o.description LIKE '%{$query}%'
                    )
                    ORDER BY o.name
                    LIMIT {$limit}";
            
            $result = $db->query($sql);
            $deals = [];
            
            while ($row = $db->fetchByAssoc($result)) {
                $deals[] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'amount' => number_format((float)$row['amount'], 2),
                    'account_name' => $row['account_name'],
                    'pipeline_stage' => $row['pipeline_stage_c'],
                    'assigned_user' => $row['assigned_user_name']
                ];
            }
            
            $this->sendJsonResponse(['results' => $deals]);
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('Quick search failed: ' . $e->getMessage());
            $this->sendJsonResponse(['results' => []]);
        }
    }
    
    /**
     * Pipeline stage filter
     */
    public function action_filterByStage()
    {
        global $current_user, $db;
        
        try {
            // Check permissions
            if (!$current_user->id || !ACLController::checkAccess('Deals', 'list', true)) {
                $this->sendJsonResponse(['success' => false, 'message' => 'Unauthorized']);
                return;
            }
            
            $stage = $db->quote($_GET['stage'] ?? '');
            $includeSubstages = $_GET['include_substages'] ?? false;
            
            $whereClause = "o.deleted = 0";
            
            if (!empty($stage)) {
                if ($includeSubstages) {
                    // Include related stages (example: all closing stages)
                    $stageGroups = $this->getStageGroups();
                    $stages = $stageGroups[$stage] ?? [$stage];
                    $stageList = "'" . implode("','", $stages) . "'";
                    $whereClause .= " AND oc.pipeline_stage_c IN ({$stageList})";
                } else {
                    $whereClause .= " AND oc.pipeline_stage_c = '{$stage}'";
                }
            }
            
            $sql = "SELECT 
                        o.id,
                        o.name,
                        o.amount,
                        o.probability,
                        o.date_closed,
                        oc.pipeline_stage_c,
                        oc.stage_entered_date_c,
                        oc.expected_close_date_c,
                        oc.deal_source_c,
                        a.name as account_name,
                        u.user_name as assigned_user_name,
                        DATEDIFF(NOW(), oc.stage_entered_date_c) as days_in_stage
                    FROM opportunities o
                    LEFT JOIN opportunities_cstm oc ON o.id = oc.id_c
                    LEFT JOIN accounts a ON o.account_id = a.id AND a.deleted = 0
                    LEFT JOIN users u ON o.assigned_user_id = u.id AND u.deleted = 0
                    WHERE {$whereClause}
                    ORDER BY oc.stage_entered_date_c DESC";
            
            $result = $db->query($sql);
            $deals = [];
            
            while ($row = $db->fetchByAssoc($result)) {
                $deals[] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'amount' => (float)$row['amount'],
                    'probability' => (int)$row['probability'],
                    'pipeline_stage' => $row['pipeline_stage_c'],
                    'days_in_stage' => (int)$row['days_in_stage'],
                    'account_name' => $row['account_name'],
                    'assigned_user' => $row['assigned_user_name'],
                    'expected_close_date' => $row['expected_close_date_c'],
                    'deal_source' => $row['deal_source_c']
                ];
            }
            
            $this->sendJsonResponse([
                'success' => true,
                'stage' => $stage,
                'count' => count($deals),
                'deals' => $deals
            ]);
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('Filter by stage failed: ' . $e->getMessage());
            $this->sendJsonResponse(['success' => false, 'message' => 'Filter failed']);
        }
    }
    
    /**
     * Build search criteria from request parameters
     */
    private function buildSearchCriteria()
    {
        global $db;
        
        $criteria = [];
        
        // Text search
        if (!empty($_GET['name'])) {
            $criteria['name'] = $db->quote($_GET['name']);
        }
        
        if (!empty($_GET['account_name'])) {
            $criteria['account_name'] = $db->quote($_GET['account_name']);
        }
        
        // Pipeline criteria
        if (!empty($_GET['pipeline_stage'])) {
            $criteria['pipeline_stage'] = $db->quote($_GET['pipeline_stage']);
        }
        
        // Amount range
        if (!empty($_GET['min_amount']) && is_numeric($_GET['min_amount'])) {
            $criteria['min_amount'] = (float)$_GET['min_amount'];
        }
        
        if (!empty($_GET['max_amount']) && is_numeric($_GET['max_amount'])) {
            $criteria['max_amount'] = (float)$_GET['max_amount'];
        }
        
        // Date ranges
        if (!empty($_GET['date_entered_from'])) {
            $criteria['date_entered_from'] = $db->quote(date('Y-m-d', strtotime($_GET['date_entered_from'])));
        }
        
        if (!empty($_GET['date_entered_to'])) {
            $criteria['date_entered_to'] = $db->quote(date('Y-m-d', strtotime($_GET['date_entered_to'])));
        }
        
        if (!empty($_GET['expected_close_from'])) {
            $criteria['expected_close_from'] = $db->quote(date('Y-m-d', strtotime($_GET['expected_close_from'])));
        }
        
        if (!empty($_GET['expected_close_to'])) {
            $criteria['expected_close_to'] = $db->quote(date('Y-m-d', strtotime($_GET['expected_close_to'])));
        }
        
        // User filters
        if (!empty($_GET['assigned_user_id'])) {
            $criteria['assigned_user_id'] = $db->quote($_GET['assigned_user_id']);
        }
        
        // Source filter
        if (!empty($_GET['deal_source'])) {
            $criteria['deal_source'] = $db->quote($_GET['deal_source']);
        }
        
        // Probability range
        if (!empty($_GET['min_probability']) && is_numeric($_GET['min_probability'])) {
            $criteria['min_probability'] = (int)$_GET['min_probability'];
        }
        
        if (!empty($_GET['max_probability']) && is_numeric($_GET['max_probability'])) {
            $criteria['max_probability'] = (int)$_GET['max_probability'];
        }
        
        // Stage duration filter
        if (!empty($_GET['min_days_in_stage']) && is_numeric($_GET['min_days_in_stage'])) {
            $criteria['min_days_in_stage'] = (int)$_GET['min_days_in_stage'];
        }
        
        // Limit and sorting
        $criteria['limit'] = min((int)($_GET['limit'] ?? 100), 500);
        $criteria['sort_by'] = in_array($_GET['sort_by'] ?? '', ['name', 'amount', 'date_entered', 'stage_entered_date_c']) 
            ? $_GET['sort_by'] : 'date_modified';
        $criteria['sort_order'] = ($_GET['sort_order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
        
        return $criteria;
    }
    
    /**
     * Perform advanced search with complex criteria
     */
    private function performAdvancedSearch($criteria)
    {
        global $db;
        
        $whereClause = ['o.deleted = 0'];
        $joinClause = "LEFT JOIN opportunities_cstm oc ON o.id = oc.id_c
                       LEFT JOIN accounts a ON o.account_id = a.id AND a.deleted = 0
                       LEFT JOIN users u ON o.assigned_user_id = u.id AND u.deleted = 0";
        
        // Build WHERE conditions
        if (!empty($criteria['name'])) {
            $whereClause[] = "o.name LIKE '%{$criteria['name']}%'";
        }
        
        if (!empty($criteria['account_name'])) {
            $whereClause[] = "a.name LIKE '%{$criteria['account_name']}%'";
        }
        
        if (!empty($criteria['pipeline_stage'])) {
            $whereClause[] = "oc.pipeline_stage_c = {$criteria['pipeline_stage']}";
        }
        
        if (!empty($criteria['min_amount'])) {
            $whereClause[] = "o.amount >= {$criteria['min_amount']}";
        }
        
        if (!empty($criteria['max_amount'])) {
            $whereClause[] = "o.amount <= {$criteria['max_amount']}";
        }
        
        if (!empty($criteria['date_entered_from'])) {
            $whereClause[] = "DATE(o.date_entered) >= {$criteria['date_entered_from']}";
        }
        
        if (!empty($criteria['date_entered_to'])) {
            $whereClause[] = "DATE(o.date_entered) <= {$criteria['date_entered_to']}";
        }
        
        if (!empty($criteria['expected_close_from'])) {
            $whereClause[] = "DATE(oc.expected_close_date_c) >= {$criteria['expected_close_from']}";
        }
        
        if (!empty($criteria['expected_close_to'])) {
            $whereClause[] = "DATE(oc.expected_close_date_c) <= {$criteria['expected_close_to']}";
        }
        
        if (!empty($criteria['assigned_user_id'])) {
            $whereClause[] = "o.assigned_user_id = {$criteria['assigned_user_id']}";
        }
        
        if (!empty($criteria['deal_source'])) {
            $whereClause[] = "oc.deal_source_c = {$criteria['deal_source']}";
        }
        
        if (!empty($criteria['min_probability'])) {
            $whereClause[] = "o.probability >= {$criteria['min_probability']}";
        }
        
        if (!empty($criteria['max_probability'])) {
            $whereClause[] = "o.probability <= {$criteria['max_probability']}";
        }
        
        if (!empty($criteria['min_days_in_stage'])) {
            $whereClause[] = "DATEDIFF(NOW(), oc.stage_entered_date_c) >= {$criteria['min_days_in_stage']}";
        }
        
        // Build the complete query
        $sql = "SELECT 
                    o.id,
                    o.name,
                    o.amount,
                    o.probability,
                    o.sales_stage,
                    o.date_closed,
                    o.date_entered,
                    o.date_modified,
                    oc.pipeline_stage_c,
                    oc.stage_entered_date_c,
                    oc.expected_close_date_c,
                    oc.deal_source_c,
                    oc.pipeline_notes_c,
                    a.name as account_name,
                    u.user_name as assigned_user_name,
                    CONCAT(u.first_name, ' ', u.last_name) as assigned_user_full_name,
                    DATEDIFF(NOW(), oc.stage_entered_date_c) as days_in_stage
                FROM opportunities o
                {$joinClause}
                WHERE " . implode(' AND ', $whereClause) . "
                ORDER BY o.{$criteria['sort_by']} {$criteria['sort_order']}
                LIMIT {$criteria['limit']}";
        
        $result = $db->query($sql);
        $deals = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            $deals[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'amount' => (float)$row['amount'],
                'probability' => (int)$row['probability'],
                'sales_stage' => $row['sales_stage'],
                'pipeline_stage' => $row['pipeline_stage_c'],
                'days_in_stage' => (int)$row['days_in_stage'],
                'account_name' => $row['account_name'],
                'assigned_user' => $row['assigned_user_name'],
                'assigned_user_full_name' => $row['assigned_user_full_name'],
                'date_entered' => $row['date_entered'],
                'date_modified' => $row['date_modified'],
                'expected_close_date' => $row['expected_close_date_c'],
                'deal_source' => $row['deal_source_c'],
                'pipeline_notes' => $row['pipeline_notes_c']
            ];
        }
        
        return $deals;
    }
    
    /**
     * Get stage groups for related stage filtering
     */
    private function getStageGroups()
    {
        return [
            'early' => ['sourcing', 'screening', 'analysis_outreach'],
            'middle' => ['due_diligence', 'valuation_structuring'],
            'late' => ['loi_negotiation', 'financing', 'closing'],
            'closed' => ['closed_owned_90_day', 'closed_owned_stable'],
            'lost' => ['unavailable']
        ];
    }
    
    /**
     * Send JSON response
     */
    private function sendJsonResponse($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        sugar_cleanup(true);
    }
}