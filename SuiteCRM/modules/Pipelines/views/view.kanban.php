<?php
/**
 * Pipeline Kanban View Controller
 */

require_once('include/MVC/View/SugarView.php');

class PipelinesViewKanban extends SugarView
{
    public function display()
    {
        global $db, $current_user;
        
        // Get pipeline stages
        $stages = $this->getPipelineStages();
        
        // Get deals with related data
        $deals = $this->getDealsWithRelatedData();
        
        // Get WIP limits and other configuration
        $wipLimits = $this->getWIPLimits();
        
        // Get user permissions
        $permissions = $this->getUserPermissions();
        
        // Pass data to JavaScript
        echo '<script type="text/javascript">';
        echo 'window.pipelineStages = ' . json_encode($stages) . ';';
        echo 'window.pipelineDeals = ' . json_encode($deals) . ';';
        echo 'window.wipLimits = ' . json_encode($wipLimits) . ';';
        echo 'window.currentUser = ' . json_encode(['id' => $current_user->id, 'name' => $current_user->full_name]) . ';';
        echo 'window.pipelinePermissions = ' . json_encode($permissions) . ';';
        echo '</script>';
        
        // Include CSS
        echo '<link rel="stylesheet" type="text/css" href="custom/modules/Pipelines/views/pipeline-kanban.css">';
        
        // Include JavaScript
        echo '<script type="text/javascript" src="custom/modules/Pipelines/views/PipelineKanbanView.js"></script>';
        
        // Render container
        echo '<div id="pipeline-container"></div>';
        
        // Initialize the view
        echo '<script type="text/javascript">
            document.addEventListener("DOMContentLoaded", function() {
                // Pipeline view is automatically initialized by PipelineKanbanView.js
            });
        </script>';
    }
    
    protected function getPipelineStages()
    {
        global $db;
        
        $stages = [];
        $query = "SELECT * FROM pipeline_stages 
                  WHERE deleted = 0 AND is_active = 1 
                  ORDER BY sort_order ASC";
        
        $result = $db->query($query);
        while ($row = $db->fetchByAssoc($result)) {
            $stages[] = $row;
        }
        
        // If no stages in database, use defaults
        if (empty($stages)) {
            $stages = $this->getDefaultStages();
        }
        
        return $stages;
    }
    
    protected function getDefaultStages()
    {
        return [
            ['name' => 'sourcing', 'display_name' => 'Sourcing', 'sort_order' => 1, 'wip_limit' => 50, 
             'probability_default' => 10, 'warning_days' => 30, 'critical_days' => 60],
            ['name' => 'screening', 'display_name' => 'Screening', 'sort_order' => 2, 'wip_limit' => 25,
             'probability_default' => 20, 'warning_days' => 14, 'critical_days' => 30],
            ['name' => 'analysis_outreach', 'display_name' => 'Analysis & Outreach', 'sort_order' => 3, 'wip_limit' => 15,
             'probability_default' => 30, 'warning_days' => 21, 'critical_days' => 45],
            ['name' => 'term_sheet', 'display_name' => 'Term Sheet', 'sort_order' => 4, 'wip_limit' => 10,
             'probability_default' => 50, 'warning_days' => 30, 'critical_days' => 60],
            ['name' => 'due_diligence', 'display_name' => 'Due Diligence', 'sort_order' => 5, 'wip_limit' => 8,
             'probability_default' => 70, 'warning_days' => 45, 'critical_days' => 90],
            ['name' => 'final_negotiation', 'display_name' => 'Final Negotiation', 'sort_order' => 6, 'wip_limit' => 5,
             'probability_default' => 85, 'warning_days' => 30, 'critical_days' => 60],
            ['name' => 'closing', 'display_name' => 'Closing', 'sort_order' => 7, 'wip_limit' => 5,
             'probability_default' => 95, 'warning_days' => 21, 'critical_days' => 45],
            ['name' => 'closed_won', 'display_name' => 'Closed Won', 'sort_order' => 8, 'wip_limit' => null,
             'probability_default' => 100, 'warning_days' => null, 'critical_days' => null],
            ['name' => 'closed_lost', 'display_name' => 'Closed Lost', 'sort_order' => 9, 'wip_limit' => null,
             'probability_default' => 0, 'warning_days' => null, 'critical_days' => null],
            ['name' => 'unavailable', 'display_name' => 'Unavailable', 'sort_order' => 10, 'wip_limit' => null,
             'probability_default' => 5, 'warning_days' => 180, 'critical_days' => 365]
        ];
    }
    
    protected function getDealsWithRelatedData()
    {
        global $db, $current_user;
        
        $deals = [];
        
        // Get filter from request
        $filter = $_REQUEST['filter'] ?? 'all';
        
        // Build query based on filter
        $where = "d.deleted = 0";
        
        switch ($filter) {
            case 'my':
                $where .= " AND d.assigned_user_id = '{$current_user->id}'";
                break;
            case 'team':
                // Get team members
                $teamIds = $this->getTeamMemberIds($current_user->id);
                if (!empty($teamIds)) {
                    $where .= " AND d.assigned_user_id IN ('" . implode("','", $teamIds) . "')";
                }
                break;
            case 'stale':
                $where .= " AND d.is_stale = 1";
                break;
            case 'high-value':
                $where .= " AND d.deal_value >= 10000000";
                break;
        }
        
        $query = "SELECT 
                    d.*,
                    a.name as account_name,
                    u.user_name as assigned_user_name,
                    CONCAT(u.first_name, ' ', u.last_name) as assigned_user_full_name
                  FROM deals d
                  LEFT JOIN accounts a ON d.account_id = a.id AND a.deleted = 0
                  LEFT JOIN users u ON d.assigned_user_id = u.id AND u.deleted = 0
                  WHERE {$where}
                  ORDER BY d.date_modified DESC
                  LIMIT 500"; // Limit for performance
        
        $result = $db->query($query);
        while ($row = $db->fetchByAssoc($result)) {
            // Calculate additional metrics
            $row['is_stale'] = $this->checkIfStale($row);
            $row['health_score'] = $this->calculateHealthScore($row);
            
            $deals[] = $row;
        }
        
        return $deals;
    }
    
    protected function getWIPLimits()
    {
        global $db, $current_user;
        
        $limits = [];
        
        $query = "SELECT stage, wip_limit FROM mdeal_pipeline_stages WHERE deleted = 0";
        $result = $db->query($query);
        
        while ($row = $db->fetchByAssoc($result)) {
            if ($row['wip_limit']) {
                $limits[$row['stage']] = (int)$row['wip_limit'];
            }
        }
        
        return $limits;
    }
    
    protected function getUserPermissions()
    {
        global $current_user;
        
        return [
            'canCreate' => ACLController::checkAccess('mdeal_Deals', 'edit', true),
            'canEdit' => ACLController::checkAccess('mdeal_Deals', 'edit', true),
            'canDelete' => ACLController::checkAccess('mdeal_Deals', 'delete', true),
            'canExport' => ACLController::checkAccess('mdeal_Deals', 'export', true),
            'isAdmin' => $current_user->isAdmin()
        ];
    }
    
    protected function getTeamMemberIds($userId)
    {
        // This would fetch actual team members
        // For now, return empty array
        return [];
    }
    
    protected function checkIfStale($deal)
    {
        $daysInStage = (int)($deal['days_in_stage'] ?? 0);
        $stage = $deal['stage'];
        
        // Get stage thresholds
        $thresholds = [
            'sourcing' => 60,
            'screening' => 30,
            'analysis_outreach' => 45,
            'term_sheet' => 60,
            'due_diligence' => 90,
            'final_negotiation' => 60,
            'closing' => 45
        ];
        
        $threshold = $thresholds[$stage] ?? 30;
        
        return $daysInStage > $threshold ? 1 : 0;
    }
    
    protected function calculateHealthScore($deal)
    {
        $score = 50; // Base score
        
        // Stage progression bonus
        $stageScores = [
            'sourcing' => 5,
            'screening' => 10,
            'analysis_outreach' => 15,
            'term_sheet' => 20,
            'due_diligence' => 25,
            'final_negotiation' => 30,
            'closing' => 35
        ];
        
        $score += $stageScores[$deal['stage']] ?? 0;
        
        // Recent activity bonus
        if (!empty($deal['last_activity_date'])) {
            $daysSinceActivity = (time() - strtotime($deal['last_activity_date'])) / 86400;
            if ($daysSinceActivity <= 7) {
                $score += 10;
            }
        }
        
        // Deal value bonus
        if ($deal['deal_value'] > 10000000) {
            $score += 10;
        }
        
        // Stale penalty
        if ($deal['is_stale']) {
            $score -= 15;
        }
        
        return max(0, min(100, $score));
    }
}
?>