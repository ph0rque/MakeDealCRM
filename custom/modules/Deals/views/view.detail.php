<?php
/**
 * Deal Detail View with Export Functionality
 * Extends the standard detail view to include export buttons
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once('modules/Opportunities/views/view.detail.php');

class DealsViewDetail extends OpportunitiesViewDetail
{
    public function __construct()
    {
        parent::__construct();
        $this->useForSubpanel = true;
        $this->useModuleQuickCreateTemplate = true;
    }

    public function display()
    {
        // Add export CSS and JavaScript
        $this->includeExportAssets();
        
        // Call parent display
        parent::display();
        
        // Add export buttons to the detail view
        $this->addExportButtons();
    }
    
    /**
     * Include CSS and JavaScript for export functionality
     */
    protected function includeExportAssets()
    {
        global $sugar_config;
        
        $css_url = $sugar_config['site_url'] . '/custom/modules/Deals/css/export-styles.css';
        $js_url = $sugar_config['site_url'] . '/custom/modules/Deals/js/export-manager.js';
        
        echo "<link rel='stylesheet' type='text/css' href='{$css_url}' />\n";
        echo "<script type='text/javascript' src='{$js_url}'></script>\n";
    }
    
    /**
     * Add export buttons to the detail view
     */
    protected function addExportButtons()
    {
        $dealId = $this->bean->id;
        $dealName = htmlspecialchars($this->bean->name ?? 'Deal');
        
        $exportButtonsHtml = $this->generateExportButtonsHTML($dealId, $dealName);
        
        // Inject the buttons via JavaScript to ensure they appear in the right place
        echo "<script type='text/javascript'>
            $(document).ready(function() {
                // Find the best location to insert export buttons
                var targetContainer = $('.detail-view .panel-content').first();
                if (targetContainer.length === 0) {
                    targetContainer = $('.detail-view').first();
                }
                if (targetContainer.length === 0) {
                    targetContainer = $('#content').first();
                }
                
                if (targetContainer.length > 0) {
                    targetContainer.prepend('{$exportButtonsHtml}');
                }
            });
        </script>";
    }
    
    /**
     * Generate HTML for export buttons
     */
    protected function generateExportButtonsHTML($dealId, $dealName)
    {
        $html = '<div class="export-buttons detailView" style="margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 4px; border-top: 1px solid #dee2e6;">';
        $html .= '<h4 style="margin: 0 0 15px 0; color: #007cba; font-size: 16px;">📊 Due Diligence Reports</h4>';
        $html .= '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';
        
        // PDF Export Button
        $html .= '<button type="button" class="export-pdf-btn" data-deal-id="' . $dealId . '" ';
        $html .= 'title="Export due diligence report as PDF">';
        $html .= 'Export PDF Report</button>';
        
        // Excel Export Button  
        $html .= '<button type="button" class="export-excel-btn" data-deal-id="' . $dealId . '" ';
        $html .= 'title="Export due diligence data as Excel spreadsheet">';
        $html .= 'Export Excel Data</button>';
        
        // Export History Button
        $html .= '<button type="button" class="btn btn-secondary" onclick="DueDiligenceExportManager.showExportHistory(\'' . $dealId . '\')" ';
        $html .= 'title="View export history for this deal">';
        $html .= '📋 Export History</button>';
        
        $html .= '</div>';
        $html .= '<p style="margin: 10px 0 0 0; font-size: 12px; color: #6c757d;">';
        $html .= 'Export comprehensive due diligence reports including progress analysis, task details, and file request status.';
        $html .= '</p>';
        $html .= '</div>';
        
        // Escape for JavaScript insertion
        return str_replace(["\n", "\r", "'", '"'], ['', '', "\\'", '\\"'], $html);
    }
    
    /**
     * Override to add export permissions check
     */
    public function preDisplay()
    {
        parent::preDisplay();
        
        // Check if user has export permissions
        if (!$this->checkExportPermissions()) {
            // Hide export functionality for users without permissions
            echo "<style>.export-buttons { display: none !important; }</style>";
        }
    }
    
    /**
     * Check if current user has export permissions
     */
    protected function checkExportPermissions()
    {
        global $current_user, $sugar_config;
        
        // Check if exports are globally disabled
        if (!empty($sugar_config['disable_export'])) {
            return false;
        }
        
        // Check admin-only restriction
        if (!empty($sugar_config['admin_export_only']) && !is_admin($current_user)) {
            return false;
        }
        
        // Check module-specific permissions
        if (!$this->bean->ACLAccess('export')) {
            return false;
        }
        
        return true;
    }
}
?>