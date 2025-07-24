<?php
/**
 * Pipeline Kanban View for Deals Module
 * 
 * Displays deals in a Kanban board format with drag-and-drop functionality
 * and time-in-stage tracking
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('include/MVC/View/views/view.detail.php');

class DealsViewPipeline extends SugarView
{
    private $stages = [
        'sourcing' => 'Sourcing',
        'screening' => 'Screening',
        'analysis_outreach' => 'Analysis & Outreach',
        'due_diligence' => 'Due Diligence',
        'valuation_structuring' => 'Valuation & Structuring',
        'loi_negotiation' => 'LOI / Negotiation',
        'financing' => 'Financing',
        'closing' => 'Closing',
        'closed_owned_90_day' => 'Closed/Owned – 90-Day Plan',
        'closed_owned_stable' => 'Closed/Owned – Stable Operations',
        'unavailable' => 'Unavailable'
    ];

    private $wip_limits = [
        'sourcing' => 20,
        'screening' => 15,
        'analysis_outreach' => 10,
        'due_diligence' => 8,
        'valuation_structuring' => 6,
        'loi_negotiation' => 5,
        'financing' => 5,
        'closing' => 5,
        'closed_owned_90_day' => 10,
        'closed_owned_stable' => null, // No limit
        'unavailable' => null // No limit
    ];

    public function __construct()
    {
        parent::__construct();
    }

    public function display()
    {
        global $app_strings, $mod_strings, $current_user, $sugar_config;
        
        // Add CSS and JavaScript
        echo '<link rel="stylesheet" type="text/css" href="custom/modules/Deals/css/pipeline.css">';
        echo '<script type="text/javascript" src="custom/modules/Deals/js/pipeline.js"></script>';
        
        // Get deals grouped by stage
        $deals_by_stage = $this->getDealsByStage();
        
        // Calculate time in stage for each deal
        $this->calculateTimeInStage($deals_by_stage);
        
        // Prepare data for template
        $this->ss->assign('stages', $this->stages);
        $this->ss->assign('wip_limits', $this->wip_limits);
        $this->ss->assign('deals_by_stage', $deals_by_stage);
        $this->ss->assign('current_user_id', $current_user->id);
        $this->ss->assign('is_mobile', $this->isMobileDevice());
        
        // Display the template
        $this->ss->display('custom/modules/Deals/tpls/pipeline.tpl');
    }

    /**
     * Get all deals grouped by stage
     */
    private function getDealsByStage()
    {
        global $db;
        
        $deals_by_stage = [];
        foreach ($this->stages as $stage_key => $stage_name) {
            $deals_by_stage[$stage_key] = [];
        }
        
        // Query for active deals
        $query = "SELECT 
                    d.id,
                    d.name,
                    d.amount,
                    d.sales_stage,
                    d.pipeline_stage_c,
                    d.stage_entered_date_c,
                    d.date_modified,
                    d.assigned_user_id,
                    CONCAT(u.first_name, ' ', u.last_name) as assigned_user_name,
                    d.account_id,
                    a.name as account_name,
                    d.expected_close_date_c,
                    d.probability
                FROM opportunities d
                LEFT JOIN users u ON d.assigned_user_id = u.id
                LEFT JOIN accounts a ON d.account_id = a.id
                WHERE d.deleted = 0
                AND d.sales_stage NOT IN ('Closed Won', 'Closed Lost')
                ORDER BY d.date_modified DESC";
                
        $result = $db->query($query);
        
        while ($row = $db->fetchByAssoc($result)) {
            // Use custom pipeline stage if available, otherwise map from sales_stage
            $stage = $this->mapSalesStageToStage($row['pipeline_stage_c'] ?: $row['sales_stage']);
            
            if (isset($deals_by_stage[$stage])) {
                $deals_by_stage[$stage][] = $row;
            }
        }
        
        return $deals_by_stage;
    }

    /**
     * Calculate time in current stage for each deal
     */
    private function calculateTimeInStage(&$deals_by_stage)
    {
        foreach ($deals_by_stage as $stage => &$deals) {
            foreach ($deals as &$deal) {
                $stage_date = $deal['stage_entered_date_c'] ?: $deal['date_modified'];
                $now = new DateTime();
                $stage_entered = new DateTime($stage_date);
                $interval = $now->diff($stage_entered);
                
                $deal['days_in_stage'] = $interval->days;
                
                // Determine color class based on days in stage
                if ($deal['days_in_stage'] > 30) {
                    $deal['stage_color_class'] = 'stage-red';
                } elseif ($deal['days_in_stage'] > 14) {
                    $deal['stage_color_class'] = 'stage-orange';
                } else {
                    $deal['stage_color_class'] = 'stage-normal';
                }
            }
        }
    }

    /**
     * Map sales stage to pipeline stage
     */
    private function mapSalesStageToStage($sales_stage)
    {
        $mapping = [
            'Prospecting' => 'sourcing',
            'Qualification' => 'screening',
            'Needs Analysis' => 'analysis_outreach',
            'Value Proposition' => 'valuation_structuring',
            'Id. Decision Makers' => 'due_diligence',
            'Perception Analysis' => 'due_diligence',
            'Proposal/Price Quote' => 'loi_negotiation',
            'Negotiation/Review' => 'loi_negotiation',
            'Closed Won' => 'closed_owned_stable',
            'Closed Lost' => 'unavailable'
        ];
        
        // Check if it's already a pipeline stage
        if (isset($this->stages[$sales_stage])) {
            return $sales_stage;
        }
        
        return isset($mapping[$sales_stage]) ? $mapping[$sales_stage] : 'sourcing';
    }

    /**
     * Check if user is on mobile device
     */
    private function isMobileDevice()
    {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return preg_match('/(android|webos|iphone|ipad|ipod|blackberry|iemobile|opera mini)/i', $user_agent);
    }
}