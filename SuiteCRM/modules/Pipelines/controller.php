<?php
/**
 * Pipelines Module Controller
 */

require_once('include/MVC/Controller/SugarController.php');
require_once('data/BeanFactory.php');
require_once('include/utils.php');

class PipelinesController extends SugarController
{
    public function action_kanbanview()
    {
        $this->view = 'kanban';
    }
    
    public function action_kanban() 
    {
        $this->view = 'kanban';
    }
    
    /**
     * AJAX handler for pipeline operations
     */
    public function action_AjaxHandler()
    {
        global $db, $current_user;
        
        // Get the JSON payload
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['action'])) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Invalid request']);
            return;
        }
        
        $action = $input['action'];
        $data = $input['data'] ?? [];
        
        switch ($action) {
            case 'getPipelineData':
                $this->handleGetPipelineData($data);
                break;
                
            case 'executeStageTransition':
                $this->handleExecuteStageTransition($data);
                break;
                
            default:
                $this->sendJsonResponse(['success' => false, 'message' => 'Unknown action: ' . $action]);
        }
    }
    
    /**
     * Get pipeline data including deals and metrics
     */
    private function handleGetPipelineData($data)
    {
        global $db, $current_user;
        
        try {
            // Define pipeline stages as an array for JavaScript compatibility
            $stages = [
                ['key' => 'sourcing', 'name' => 'Sourcing', 'order' => 1],
                ['key' => 'screening', 'name' => 'Screening', 'order' => 2],
                ['key' => 'analysis_outreach', 'name' => 'Analysis & Outreach', 'order' => 3],
                ['key' => 'due_diligence', 'name' => 'Due Diligence', 'order' => 4],
                ['key' => 'valuation_structuring', 'name' => 'Valuation & Structuring', 'order' => 5],
                ['key' => 'loi_negotiation', 'name' => 'LOI & Negotiation', 'order' => 6],
                ['key' => 'financing', 'name' => 'Financing', 'order' => 7],
                ['key' => 'closing', 'name' => 'Closing', 'order' => 8],
                ['key' => 'closed_owned_90_day', 'name' => 'Closed/Owned (90 Day)', 'order' => 9],
                ['key' => 'closed_owned_stable', 'name' => 'Closed/Owned (Stable)', 'order' => 10],
                ['key' => 'unavailable', 'name' => 'Unavailable', 'order' => 11]
            ];
            
            // Create a map for easy lookup
            $stagesMap = [];
            foreach ($stages as $stage) {
                $stagesMap[$stage['key']] = $stage;
            }
            
            $deals = [];
            
            if (!empty($data['includeDeals'])) {
                // Get deals with their pipeline stages
                $query = "SELECT 
                    d.id,
                    d.name,
                    d.amount,
                    d.date_closed,
                    d.probability,
                    d.sales_stage,
                    d.account_id,
                    d.assigned_user_id,
                    IFNULL(c.pipeline_stage_c, 'sourcing') as pipeline_stage,
                    c.expected_close_date_c,
                    c.pipeline_notes_c,
                    c.days_in_stage_c,
                    c.focus_flag_c,
                    c.focus_order_c,
                    a.name as account_name,
                    CONCAT(u.first_name, ' ', u.last_name) as assigned_user_name
                FROM deals d
                LEFT JOIN deals_cstm c ON d.id = c.id_c
                LEFT JOIN accounts a ON d.account_id = a.id AND a.deleted = 0
                LEFT JOIN users u ON d.assigned_user_id = u.id AND u.deleted = 0
                WHERE d.deleted = 0
                AND (d.sales_stage NOT IN ('Closed Won', 'Closed Lost') OR d.sales_stage IS NULL)
                ORDER BY c.pipeline_stage_c, c.focus_flag_c DESC, c.focus_order_c, d.amount DESC";
                
                $result = $db->query($query);
                
                while ($row = $db->fetchByAssoc($result)) {
                    $deals[] = [
                        'id' => $row['id'],
                        'name' => $row['name'],
                        'amount' => floatval($row['amount'] ?? 0),
                        'date_closed' => $row['date_closed'],
                        'probability' => intval($row['probability'] ?? 0),
                        'sales_stage' => $row['sales_stage'],
                        'pipeline_stage' => $row['pipeline_stage'],
                        'account_name' => $row['account_name'] ?? '',
                        'assigned_user_name' => trim($row['assigned_user_name'] ?? ''),
                        'expected_close_date' => $row['expected_close_date_c'],
                        'pipeline_notes' => $row['pipeline_notes_c'],
                        'days_in_stage' => intval($row['days_in_stage_c'] ?? 0),
                        'focus_flag' => (bool)$row['focus_flag_c'],
                        'focus_order' => intval($row['focus_order_c'] ?? 0)
                    ];
                }
            }
            
            $metrics = [];
            
            if (!empty($data['includeMetrics'])) {
                // Calculate metrics for each stage
                foreach ($stages as $stageInfo) {
                    $stageKey = $stageInfo['key'];
                    $stageQuery = "SELECT 
                        COUNT(*) as count,
                        SUM(d.amount) as total_amount
                    FROM deals d
                    LEFT JOIN deals_cstm c ON d.id = c.id_c
                    WHERE d.deleted = 0
                    AND (d.sales_stage NOT IN ('Closed Won', 'Closed Lost') OR d.sales_stage IS NULL)
                    AND IFNULL(c.pipeline_stage_c, 'sourcing') = '{$stageKey}'";
                    
                    $stageResult = $db->query($stageQuery);
                    $stageData = $db->fetchByAssoc($stageResult);
                    
                    $metrics[$stageKey] = [
                        'count' => intval($stageData['count'] ?? 0),
                        'total_amount' => floatval($stageData['total_amount'] ?? 0)
                    ];
                }
            }
            
            $this->sendJsonResponse([
                'success' => true,
                'stages' => $stages,
                'deals' => $deals,
                'metrics' => $metrics
            ]);
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('getPipelineData failed: ' . $e->getMessage());
            $this->sendJsonResponse(['success' => false, 'message' => 'Failed to load pipeline data']);
        }
    }
    
    /**
     * Handle stage transition for a deal
     */
    private function handleExecuteStageTransition($data)
    {
        global $db, $current_user;
        
        try {
            $dealId = $db->quote($data['dealId'] ?? '');
            $fromStage = $db->quote($data['fromStage'] ?? '');
            $toStage = $db->quote($data['toStage'] ?? '');
            $reason = $db->quote($data['reason'] ?? '');
            $override = !empty($data['override']);
            
            if (!$dealId || !$toStage) {
                $this->sendJsonResponse(['success' => false, 'message' => 'Missing required parameters: dealId=' . $dealId . ', toStage=' . $toStage]);
                return;
            }
            
            // Remove quotes from parameters
            $dealId = str_replace("'", "", $dealId);
            $fromStage = str_replace("'", "", $fromStage);
            $toStage = str_replace("'", "", $toStage);
            
            // Load the deal
            $deal = BeanFactory::getBean('Deals', $dealId);
            if (!$deal || $deal->deleted) {
                $this->sendJsonResponse(['success' => false, 'message' => 'Deal not found']);
                return;
            }
            
            // Check permissions
            if (!$deal->ACLAccess('edit')) {
                $this->sendJsonResponse(['success' => false, 'message' => 'Access denied']);
                return;
            }
            
            // Update the stage
            $deal->pipeline_stage_c = $toStage;
            
            // Update sales stage mapping
            $salesStageMap = [
                'sourcing' => 'Prospecting',
                'screening' => 'Qualification',
                'analysis_outreach' => 'Needs Analysis',
                'due_diligence' => 'Id. Decision Makers',
                'valuation_structuring' => 'Value Proposition',
                'loi_negotiation' => 'Negotiation/Review',
                'financing' => 'Proposal/Price Quote',
                'closing' => 'Negotiation/Review',
                'closed_owned_90_day' => 'Closed Won',
                'closed_owned_stable' => 'Closed Won',
                'unavailable' => 'Closed Lost'
            ];
            
            if (isset($salesStageMap[$toStage])) {
                $deal->sales_stage = $salesStageMap[$toStage];
            }
            
            // Reset days in stage
            $deal->days_in_stage_c = 0;
            
            // Save the deal
            $deal->save();
            
            // Log the transition
            $this->logStageTransition($dealId, $fromStage, $toStage, $current_user->id, $reason);
            
            $this->sendJsonResponse([
                'success' => true,
                'message' => 'Deal moved successfully',
                'dealId' => $dealId,
                'newStage' => $toStage
            ]);
            
        } catch (Exception $e) {
            $GLOBALS['log']->error('executeStageTransition failed: ' . $e->getMessage());
            $this->sendJsonResponse(['success' => false, 'message' => 'Failed to move deal']);
        }
    }
    
    /**
     * Log stage transition
     */
    private function logStageTransition($dealId, $fromStage, $toStage, $userId, $reason = '')
    {
        global $db;
        
        try {
            $id = create_guid();
            $query = "INSERT INTO pipeline_stage_history 
                (id, deal_id, old_stage, new_stage, changed_by, change_reason, date_changed) 
                VALUES 
                ('{$id}', '{$dealId}', '{$fromStage}', '{$toStage}', '{$userId}', '{$reason}', NOW())";
            
            // Only execute if table exists
            $tableCheck = "SHOW TABLES LIKE 'pipeline_stage_history'";
            $result = $db->query($tableCheck);
            if ($db->fetchByAssoc($result)) {
                $db->query($query);
            }
        } catch (Exception $e) {
            // Log error but don't fail the transition
            $GLOBALS['log']->error('Failed to log stage transition: ' . $e->getMessage());
        }
    }
    
    /**
     * Send JSON response
     */
    private function sendJsonResponse($data)
    {
        // Clean any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        
        echo json_encode($data);
        
        if (function_exists('sugar_cleanup')) {
            sugar_cleanup(true);
        }
        exit();
    }
}