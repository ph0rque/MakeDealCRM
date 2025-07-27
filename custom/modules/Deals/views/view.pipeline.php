<?php
/**
 * Pipeline Kanban View for Deals Module
 * 
 * Displays deals in a Kanban board format with drag-and-drop functionality
 * and time-in-stage tracking
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

// Adjust paths for custom module location
$suitecrm_root = dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/SuiteCRM';
require_once($suitecrm_root . '/include/MVC/View/views/view.detail.php');

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
        
        // For AJAX requests, we need to handle the response properly
        if (!empty($_REQUEST['ajax_load']) || !empty($_REQUEST['ajaxLoad'])) {
            // Set the proper content type for AJAX
            if (!headers_sent()) {
                header('Content-Type: text/html; charset=UTF-8');
            }
        }
        
        // Load theme-integrated CSS files with caching support
        $this->loadThemeIntegratedAssets();
        
        // Add CSS files with versioning for cache busting
        $version = $this->getAssetVersion();
        echo '<link rel="stylesheet" type="text/css" href="custom/modules/Deals/css/theme-integration.css?v=' . $version . '">';
        echo '<link rel="stylesheet" type="text/css" href="custom/modules/Deals/css/pipeline.css?v=' . $version . '">';
        echo '<link rel="stylesheet" type="text/css" href="custom/modules/Deals/css/pipeline-focus.css?v=' . $version . '">';
        echo '<link rel="stylesheet" type="text/css" href="custom/modules/Deals/css/wip-limits.css?v=' . $version . '">';
        echo '<link rel="stylesheet" type="text/css" href="custom/modules/Deals/css/progress-indicators.css?v=' . $version . '">';
        echo '<link rel="stylesheet" type="text/css" href="custom/modules/Deals/css/stakeholder-badges.css?v=' . $version . '">';
        
        // Load JavaScript files with async loading and minification
        echo '<!-- DEBUG: About to load JavaScript files -->';
        $this->loadOptimizedJavaScript($version);
        echo '<!-- DEBUG: JavaScript files loaded -->';
        
        // Ensure jQuery UI is available for drag and drop
        echo '<script type="text/javascript" src="include/javascript/jquery-ui-1.12.1.min.js"></script>';
        
        // Add a simple test to ensure our JavaScript loading is working
        echo '<script type="text/javascript">';
        echo 'console.log("DEBUG: Pipeline view JavaScript section loaded");';
        echo '</script>';
        
        // Get deals grouped by stage with caching and optimization
        $deals_by_stage = $this->getDealsByStageOptimized();
        
        // Calculate time in stage for each deal (optimized)
        $this->calculateTimeInStageOptimized($deals_by_stage);
        
        // Prepare data for template with theme information and performance data
        $this->ss->assign('stages', $this->stages);
        $this->ss->assign('wip_limits', $this->wip_limits);
        $this->ss->assign('deals_by_stage', $deals_by_stage);
        $this->ss->assign('current_user_id', $current_user->id);
        $this->ss->assign('is_mobile', $this->isMobileDevice());
        $this->ss->assign('current_theme', $this->getCurrentTheme());
        $this->ss->assign('current_subtheme', $this->getCurrentSubtheme());
        $this->ss->assign('performance_mode', $this->getPerformanceMode());
        $this->ss->assign('asset_version', $version);
        
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
                    c.pipeline_stage_c,
                    c.stage_entered_date_c,
                    d.date_modified,
                    d.assigned_user_id,
                    CONCAT(u.first_name, ' ', u.last_name) as assigned_user_name,
                    d.account_id,
                    a.name as account_name,
                    c.expected_close_date_c,
                    d.probability,
                    c.focus_flag_c,
                    c.focus_order_c,
                    c.focus_date_c
                FROM opportunities d
                LEFT JOIN opportunities_cstm c ON d.id = c.id_c
                LEFT JOIN users u ON d.assigned_user_id = u.id
                LEFT JOIN accounts a ON d.account_id = a.id
                WHERE d.deleted = 0
                AND d.sales_stage NOT IN ('Closed Won', 'Closed Lost')
                ORDER BY c.focus_flag_c DESC, c.focus_order_c ASC, d.date_modified DESC";
                
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

    /**
     * Load theme-integrated assets with SuiteCRM integration
     */
    private function loadThemeIntegratedAssets()
    {
        global $sugar_config;
        
        // Enable SuiteCRM's built-in caching mechanisms
        if (!empty($sugar_config['cache_dir'])) {
            // Use SuiteCRM's cache system for asset optimization
            require_once($suitecrm_root . '/include/SugarCache/SugarCache.php');
        }
    }

    /**
     * Get asset version for cache busting
     */
    private function getAssetVersion()
    {
        $cacheKey = 'deals_asset_version';
        
        // Check cache first
        if (class_exists('SugarCache')) {
            $cached = SugarCache::sugar_cache_retrieve($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }
        
        // Generate version based on file modification times
        $files = [
            'custom/modules/Deals/css/pipeline.css',
            'custom/modules/Deals/css/theme-integration.css',
            'custom/modules/Deals/js/pipeline.js'
        ];
        
        $version = 0;
        foreach ($files as $file) {
            if (file_exists($file)) {
                $version = max($version, filemtime($file));
            }
        }
        
        // Cache the version
        if (class_exists('SugarCache')) {
            SugarCache::sugar_cache_put($cacheKey, $version, 3600); // Cache for 1 hour
        }
        
        return $version;
    }

    /**
     * Load optimized JavaScript with async loading
     */
    private function loadOptimizedJavaScript($version)
    {
        $jsFiles = [
            'performance-optimizer.js',
            'state-manager.js',
            'pipeline-state-integration.js',
            'wip-limit-manager.js',
            'progress-indicators.js',
            'stakeholder-integration.js',
            'pipeline.js'
        ];

        // Load synchronously to ensure PipelineView is available when template runs
        foreach ($jsFiles as $file) {
            echo '<script type="text/javascript" src="custom/modules/Deals/js/' . $file . '?v=' . $version . '"></script>';
        }
    }

    /**
     * Get current SuiteCRM theme
     */
    private function getCurrentTheme()
    {
        global $current_user, $sugar_config;
        
        // Try to detect theme from current user preferences
        if (!empty($current_user->getPreference('user_theme'))) {
            return $current_user->getPreference('user_theme');
        }
        
        // Fallback to system default theme
        if (!empty($sugar_config['default_theme'])) {
            return $sugar_config['default_theme'];
        }
        
        // Final fallback
        return 'SuiteP';
    }

    /**
     * Get current theme subtheme (for SuiteP)
     */
    private function getCurrentSubtheme()
    {
        global $current_user;
        
        $theme = $this->getCurrentTheme();
        if ($theme === 'SuiteP') {
            $subtheme = $current_user->getPreference('user_subtheme');
            return !empty($subtheme) ? $subtheme : 'Dawn';
        }
        
        return null;
    }

    /**
     * Get performance mode based on number of deals
     */
    private function getPerformanceMode()
    {
        global $db;
        
        // Determine performance mode based on total deals
        $query = "SELECT COUNT(*) as count FROM opportunities o 
                 WHERE o.deleted = 0 
                 AND o.sales_stage NOT IN ('Closed Won', 'Closed Lost')";
        
        $result = $db->query($query);
        $totalDeals = 0;
        if ($row = $db->fetchByAssoc($result)) {
            $totalDeals = $row['count'];
        }
        
        // Return performance mode
        if ($totalDeals > 500) {
            return 'high_performance';
        } elseif ($totalDeals > 100) {
            return 'optimized';
        } else {
            return 'standard';
        }
    }

    /**
     * Optimized version of getDealsByStage with database indexing and caching
     */
    private function getDealsByStageOptimized()
    {
        global $db;
        
        $cacheKey = 'pipeline_deals_' . md5($this->getCurrentUserId() . '_' . date('Y-m-d-H'));
        
        // Check cache first (1 hour cache)
        if (class_exists('SugarCache')) {
            $cached = SugarCache::sugar_cache_retrieve($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }
        
        $deals_by_stage = [];
        foreach ($this->stages as $stage_key => $stage_name) {
            $deals_by_stage[$stage_key] = [];
        }
        
        // Optimized query with proper indexing
        $query = "SELECT 
                    d.id,
                    d.name,
                    d.amount,
                    d.sales_stage,
                    d.date_modified,
                    d.assigned_user_id,
                    d.account_id,
                    d.probability,
                    c.pipeline_stage_c,
                    c.stage_entered_date_c,
                    c.expected_close_date_c,
                    c.focus_flag_c,
                    c.focus_order_c,
                    c.focus_date_c,
                    CONCAT(u.first_name, ' ', u.last_name) as assigned_user_name,
                    a.name as account_name
                FROM opportunities d
                LEFT JOIN opportunities_cstm c ON d.id = c.id_c
                LEFT JOIN users u ON d.assigned_user_id = u.id AND u.deleted = 0
                LEFT JOIN accounts a ON d.account_id = a.id AND a.deleted = 0
                WHERE d.deleted = 0
                AND d.sales_stage NOT IN ('Closed Won', 'Closed Lost')
                ORDER BY c.focus_flag_c DESC, c.focus_order_c ASC, d.date_modified DESC
                LIMIT 1000"; // Limit for performance
                
        $result = $db->query($query);
        
        while ($row = $db->fetchByAssoc($result)) {
            // Use custom pipeline stage if available, otherwise map from sales_stage
            $stage = $this->mapSalesStageToStage($row['pipeline_stage_c'] ?: $row['sales_stage']);
            
            if (isset($deals_by_stage[$stage])) {
                $deals_by_stage[$stage][] = $row;
            }
        }
        
        // Cache the result
        if (class_exists('SugarCache')) {
            SugarCache::sugar_cache_put($cacheKey, $deals_by_stage, 3600); // 1 hour cache
        }
        
        return $deals_by_stage;
    }

    /**
     * Optimized time calculation with batch processing
     */
    private function calculateTimeInStageOptimized(&$deals_by_stage)
    {
        $now = new DateTime();
        
        foreach ($deals_by_stage as $stage => &$deals) {
            foreach ($deals as &$deal) {
                $stage_date = $deal['stage_entered_date_c'] ?: $deal['date_modified'];
                
                try {
                    $stage_entered = new DateTime($stage_date);
                    $interval = $now->diff($stage_entered);
                    $deal['days_in_stage'] = $interval->days;
                } catch (Exception $e) {
                    $deal['days_in_stage'] = 0;
                }
                
                // Determine color class based on days in stage (optimized)
                $days = $deal['days_in_stage'];
                $deal['stage_color_class'] = $days > 30 ? 'stage-red' : 
                                           ($days > 14 ? 'stage-orange' : 'stage-normal');
            }
        }
    }

    /**
     * Get current user ID for caching purposes
     */
    private function getCurrentUserId()
    {
        global $current_user;
        return $current_user->id ?? 'anonymous';
    }
}