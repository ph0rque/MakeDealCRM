<?php
/**
 * Secure Pipeline Kanban View for Deals Module
 * 
 * Displays deals in a Kanban board format with drag-and-drop functionality
 * and time-in-stage tracking - with security enhancements
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('include/MVC/View/views/view.detail.php');
require_once('include/database/DBManagerFactory.php');
require_once('include/utils.php');
require_once('custom/modules/Deals/DealsSecurityHelper.php');

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
        
        // Security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\';');
        
        // Add CSS and JavaScript with versioning for cache busting
        $version = isset($sugar_config['js_custom_version']) ? $sugar_config['js_custom_version'] : '1.0';
        echo '<link rel="stylesheet" type="text/css" href="custom/modules/Deals/css/pipeline.css?v=' . htmlspecialchars($version, ENT_QUOTES, 'UTF-8') . '">';
        echo '<script type="text/javascript" src="custom/modules/Deals/js/pipeline.js?v=' . htmlspecialchars($version, ENT_QUOTES, 'UTF-8') . '"></script>';
        
        // Get deals grouped by stage
        $deals_by_stage = $this->getDealsByStage();
        
        // Calculate time in stage for each deal
        $this->calculateTimeInStage($deals_by_stage);
        
        // Sanitize all data before assigning to template
        $this->sanitizeDealData($deals_by_stage);
        
        // Prepare data for template
        $this->ss->assign('stages', $this->stages);
        $this->ss->assign('wip_limits', $this->wip_limits);
        $this->ss->assign('deals_by_stage', $deals_by_stage);
        $this->ss->assign('current_user_id', htmlspecialchars($current_user->id, ENT_QUOTES, 'UTF-8'));
        $this->ss->assign('is_mobile', $this->isMobileDevice());
        $this->ss->assign('csrf_token', $this->generateCSRFToken());
        
        // Display the template
        $this->ss->display('custom/modules/Deals/tpls/pipeline.tpl');
    }

    /**
     * Get all deals grouped by stage using prepared statements
     */
    private function getDealsByStage()
    {
        global $db;
        
        $deals_by_stage = [];
        foreach ($this->stages as $stage_key => $stage_name) {
            $deals_by_stage[$stage_key] = [];
        }
        
        // Use prepared statement for security
        $query = "SELECT 
                    d.id,
                    d.name,
                    d.amount,
                    d.sales_stage,
                    d.pipeline_stage_c,
                    d.stage_entered_date_c,
                    d.date_modified,
                    d.assigned_user_id,
                    CONCAT(IFNULL(u.first_name, ''), ' ', IFNULL(u.last_name, '')) as assigned_user_name,
                    d.account_id,
                    a.name as account_name,
                    d.expected_close_date_c,
                    d.probability
                FROM opportunities d
                LEFT JOIN users u ON d.assigned_user_id = u.id AND u.deleted = 0
                LEFT JOIN accounts a ON d.account_id = a.id AND a.deleted = 0
                WHERE d.deleted = 0
                AND d.sales_stage NOT IN ('Closed Won', 'Closed Lost')
                ORDER BY d.date_modified DESC
                LIMIT 500"; // Add limit to prevent performance issues
                
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
                try {
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
                } catch (Exception $e) {
                    // Handle date parsing errors gracefully
                    $deal['days_in_stage'] = 0;
                    $deal['stage_color_class'] = 'stage-normal';
                    $GLOBALS['log']->error('Pipeline View: Error calculating days in stage - ' . $e->getMessage());
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
    
    /**
     * Sanitize all deal data for XSS prevention
     */
    private function sanitizeDealData(&$deals_by_stage)
    {
        foreach ($deals_by_stage as $stage => &$deals) {
            foreach ($deals as &$deal) {
                // Sanitize all string fields
                $deal['id'] = htmlspecialchars($deal['id'], ENT_QUOTES, 'UTF-8');
                $deal['name'] = htmlspecialchars($deal['name'] ?? '', ENT_QUOTES, 'UTF-8');
                $deal['assigned_user_name'] = htmlspecialchars($deal['assigned_user_name'] ?? '', ENT_QUOTES, 'UTF-8');
                $deal['account_name'] = htmlspecialchars($deal['account_name'] ?? '', ENT_QUOTES, 'UTF-8');
                $deal['sales_stage'] = htmlspecialchars($deal['sales_stage'] ?? '', ENT_QUOTES, 'UTF-8');
                $deal['pipeline_stage_c'] = htmlspecialchars($deal['pipeline_stage_c'] ?? '', ENT_QUOTES, 'UTF-8');
                
                // Format numeric values safely
                $deal['amount'] = number_format(floatval($deal['amount'] ?? 0), 2);
                $deal['probability'] = intval($deal['probability'] ?? 0);
                
                // Sanitize dates
                $deal['expected_close_date_c'] = htmlspecialchars($deal['expected_close_date_c'] ?? '', ENT_QUOTES, 'UTF-8');
                $deal['stage_entered_date_c'] = htmlspecialchars($deal['stage_entered_date_c'] ?? '', ENT_QUOTES, 'UTF-8');
            }
        }
    }
    
    /**
     * Generate CSRF token for form submissions
     */
    private function generateCSRFToken()
    {
        if (!isset($_SESSION['pipeline_csrf_token'])) {
            $_SESSION['pipeline_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['pipeline_csrf_token'];
    }
}