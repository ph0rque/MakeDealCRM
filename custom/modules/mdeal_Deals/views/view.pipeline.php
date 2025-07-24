<?php
/**
 * Pipeline View for Deals Module
 * This provides the pipeline view within the Deals module context
 */

require_once('include/MVC/View/SugarView.php');

class mdeal_DealsViewPipeline extends SugarView
{
    public function display()
    {
        global $db, $current_user;
        
        // Set page title
        $this->ss->assign('title', 'M&A Deal Pipeline');
        
        // Get pipeline stages
        $stages = $this->getPipelineStages();
        
        // Get deals with related data
        $deals = $this->getDealsWithRelatedData();
        
        // Get WIP limits
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
        
        // Add page header
        echo '<div class="moduleTitle">
                <h2>M&A Deal Pipeline</h2>
                <div class="clear"></div>
              </div>';
        
        // Render container
        echo '<div id="pipeline-container"></div>';
    }
    
    protected function getPipelineStages()
    {
        global $db;
        
        // Use default stages for now
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
             'probability_default' => 0, 'warning_days' => null, 'critical_days' => null]
        ];
    }
    
    protected function getDealsWithRelatedData()
    {
        global $db, $current_user;
        
        $deals = [];
        
        // Check if deals table exists
        $tableCheck = $db->query("SHOW TABLES LIKE 'mdeal_deals'");
        if ($db->fetchByAssoc($tableCheck)) {
            $query = "SELECT 
                        d.*,
                        a.name as account_name,
                        u.user_name as assigned_user_name,
                        CONCAT(u.first_name, ' ', u.last_name) as assigned_user_full_name
                      FROM mdeal_deals d
                      LEFT JOIN mdeal_accounts a ON d.account_id = a.id AND a.deleted = 0
                      LEFT JOIN users u ON d.assigned_user_id = u.id AND u.deleted = 0
                      WHERE d.deleted = 0
                      ORDER BY d.date_modified DESC
                      LIMIT 100";
            
            $result = $db->query($query);
            while ($row = $db->fetchByAssoc($result)) {
                $row['health_score'] = $row['health_score'] ?? 75;
                $row['is_stale'] = $row['is_stale'] ?? 0;
                $deals[] = $row;
            }
        }
        
        // If no deals, return sample data
        if (empty($deals)) {
            $deals = $this->getSampleDeals();
        }
        
        return $deals;
    }
    
    protected function getSampleDeals()
    {
        return [
            [
                'id' => 'sample-001',
                'name' => 'Sample TechCorp Acquisition',
                'company_name' => 'TechCorp Inc',
                'stage' => 'sourcing',
                'deal_value' => 25000000,
                'probability' => 10,
                'days_in_stage' => 15,
                'health_score' => 75,
                'is_stale' => 0,
                'assigned_user_name' => 'Demo User'
            ],
            [
                'id' => 'sample-002',
                'name' => 'Sample DataSystems Merger',
                'company_name' => 'DataSystems LLC',
                'stage' => 'screening',
                'deal_value' => 45000000,
                'probability' => 20,
                'days_in_stage' => 8,
                'health_score' => 82,
                'is_stale' => 0,
                'assigned_user_name' => 'Demo User'
            ],
            [
                'id' => 'sample-003',
                'name' => 'Sample CloudTech Deal',
                'company_name' => 'CloudTech Solutions',
                'stage' => 'term_sheet',
                'deal_value' => 75000000,
                'probability' => 50,
                'days_in_stage' => 12,
                'health_score' => 88,
                'is_stale' => 0,
                'assigned_user_name' => 'Demo User'
            ]
        ];
    }
    
    protected function getWIPLimits()
    {
        return [
            'sourcing' => 50,
            'screening' => 25,
            'analysis_outreach' => 15,
            'term_sheet' => 10,
            'due_diligence' => 8,
            'final_negotiation' => 5,
            'closing' => 5
        ];
    }
    
    protected function getUserPermissions()
    {
        global $current_user;
        
        return [
            'canCreate' => true,
            'canEdit' => true,
            'canDelete' => true,
            'canExport' => true,
            'isAdmin' => $current_user->isAdmin()
        ];
    }
}