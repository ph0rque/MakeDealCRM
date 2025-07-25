<?php
/**
 * Deal List View with Batch Export Functionality
 * Extends the standard list view to include batch export capabilities
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once('modules/Opportunities/views/view.list.php');

class DealsViewList extends OpportunitiesViewList
{
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * Override preDisplay - no longer needed since controller handles redirect
     */
    public function preDisplay()
    {
        // The controller now handles the redirect to pipeline view
        // This method is kept for compatibility but does nothing
        parent::preDisplay();
    }

    public function listViewProcess()
    {
        // This should not be reached due to preDisplay redirect
        // But keeping the original functionality just in case
        
        // Add export CSS and JavaScript
        $this->includeExportAssets();
        
        // Call parent process
        parent::listViewProcess();
        
        // Add batch export functionality
        $this->addBatchExportControls();
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
     * Add batch export controls to the list view
     */
    protected function addBatchExportControls()
    {
        if (!$this->checkExportPermissions()) {
            return;
        }
        
        $exportControlsHtml = $this->generateBatchExportControlsHTML();
        
        // Inject the controls via JavaScript
        echo "<script type='text/javascript'>
            $(document).ready(function() {
                // Find the mass update form or create export controls container
                var targetContainer = $('.list-view-rounded-corners').first();
                if (targetContainer.length === 0) {
                    targetContainer = $('.listViewBody').first();
                }
                if (targetContainer.length === 0) {
                    targetContainer = $('#content').first();
                }
                
                if (targetContainer.length > 0) {
                    targetContainer.before('{$exportControlsHtml}');
                }
                
                // Initialize export functionality
                if (typeof DueDiligenceExportManager !== 'undefined') {
                    DueDiligenceExportManager.init();
                }
            });
        </script>";
    }
    
    /**
     * Generate HTML for batch export controls
     */
    protected function generateBatchExportControlsHTML()
    {
        $html = '<div class="export-buttons listViewBody" style="margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 4px; border: 1px solid #dee2e6;">';
        $html .= '<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">';
        
        // Title and description
        $html .= '<div>';
        $html .= '<h4 style="margin: 0 0 5px 0; color: #007cba; font-size: 16px;">📊 Batch Export Due Diligence Reports</h4>';
        $html .= '<p style="margin: 0; font-size: 12px; color: #6c757d;">Select deals from the list below and export comprehensive due diligence reports in batch.</p>';
        $html .= '</div>';
        
        // Export controls
        $html .= '<div style="display: flex; gap: 10px; align-items: center;">';
        
        // Selection info
        $html .= '<div id="export-selection-info" style="font-size: 12px; color: #495057; margin-right: 10px;">';
        $html .= '<span id="selected-count">0</span> deals selected';
        $html .= '</div>';
        
        // Export format selector
        $html .= '<select id="batch-export-format" style="padding: 5px 10px; border: 1px solid #ced4da; border-radius: 4px; font-size: 12px;">';
        $html .= '<option value="pdf">PDF Reports</option>';
        $html .= '<option value="excel">Excel Data</option>';
        $html .= '</select>';
        
        // Batch export button
        $html .= '<button type="button" class="batch-export-btn" ';
        $html .= 'title="Export selected deals as batch">';
        $html .= 'Batch Export</button>';
        
        // Quick actions
        $html .= '<div style="margin-left: 15px; padding-left: 15px; border-left: 1px solid #dee2e6;">';
        $html .= '<button type="button" class="btn btn-sm btn-secondary" onclick="DueDiligenceExportManager.selectAllDeals()" ';
        $html .= 'title="Select all deals on current page" style="font-size: 11px; padding: 4px 8px; margin-right: 5px;">';
        $html .= '✓ Select All</button>';
        $html .= '<button type="button" class="btn btn-sm btn-secondary" onclick="DueDiligenceExportManager.clearSelection()" ';
        $html .= 'title="Clear current selection" style="font-size: 11px; padding: 4px 8px;">';
        $html .= '✗ Clear</button>';
        $html .= '</div>';
        
        $html .= '</div>';
        $html .= '</div>';
        
        // Export instructions
        $html .= '<div style="margin-top: 10px; padding: 10px; background: #e7f3ff; border-radius: 4px; border-left: 4px solid #007cba;">';
        $html .= '<small style="color: #0c5460;"><strong>Instructions:</strong> ';
        $html .= '1) Use checkboxes in the list to select deals for export. ';
        $html .= '2) Choose export format (PDF for reports, Excel for data analysis). ';
        $html .= '3) Click "Batch Export" to generate reports. ';
        $html .= '4) Download links will be provided after processing. ';
        $html .= '<strong>Note:</strong> Batch export is limited to 50 deals maximum for performance.</small>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        // Add JavaScript for selection tracking
        $html .= '<script type="text/javascript">';
        $html .= '$(document).ready(function() {';
        $html .= '  // Track checkbox changes to update selection count';
        $html .= '  $(document).on("change", "input[name=\\"mass[]\\"]:checkbox", function() {';
        $html .= '    var selectedCount = $("input[name=\\"mass[]\\"]:checked").length;';
        $html .= '    $("#selected-count").text(selectedCount);';
        $html .= '    $(".batch-export-btn").prop("disabled", selectedCount === 0);';
        $html .= '  });';
        $html .= '  ';
        $html .= '  // Initially disable batch export button';
        $html .= '  $(".batch-export-btn").prop("disabled", true);';
        $html .= '});';
        $html .= '</script>';
        
        // Escape for JavaScript insertion
        return str_replace(["\n", "\r", "'", '"'], ['', '', "\\'", '\\"'], $html);
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
        
        return true;
    }
    
    /**
     * Override to add individual export buttons to each row
     */
    public function processListNavigation()
    {
        parent::processListNavigation();
        
        // Add JavaScript to inject individual export buttons
        if ($this->checkExportPermissions()) {
            $this->addIndividualExportButtons();
        }
    }
    
    /**
     * Add individual export buttons to each deal row
     */
    protected function addIndividualExportButtons()
    {
        echo "<script type='text/javascript'>
            $(document).ready(function() {
                // Add individual export buttons to each row
                $('.listViewBody table tbody tr').each(function() {
                    var row = $(this);
                    var dealId = '';
                    
                    // Try to find the deal ID from various possible locations
                    var checkbox = row.find('input[name=\"mass[]\"]');
                    if (checkbox.length > 0) {
                        dealId = checkbox.val();
                    } else {
                        // Try to find ID in data attributes or links
                        var link = row.find('a[href*=\"record=\"]').first();
                        if (link.length > 0) {
                            var href = link.attr('href');
                            var match = href.match(/record=([a-f0-9-]+)/i);
                            if (match) {
                                dealId = match[1];
                            }
                        }
                    }
                    
                    if (dealId) {
                        // Find the last cell and add export buttons
                        var lastCell = row.find('td').last();
                        if (lastCell.length > 0) {
                            var exportButtons = '<div style=\"white-space: nowrap; margin-top: 5px;\">' +
                                '<button type=\"button\" class=\"export-pdf-btn\" data-deal-id=\"' + dealId + '\" ' +
                                'style=\"font-size: 10px; padding: 2px 6px; margin-right: 3px;\" title=\"Export PDF\">PDF</button>' +
                                '<button type=\"button\" class=\"export-excel-btn\" data-deal-id=\"' + dealId + '\" ' +
                                'style=\"font-size: 10px; padding: 2px 6px;\" title=\"Export Excel\">Excel</button>' +
                                '</div>';
                            lastCell.append(exportButtons);
                        }
                    }
                });
            });
        </script>";
    }
}

// Extend the JavaScript export manager for list view functionality
echo "<script type='text/javascript'>
if (typeof DueDiligenceExportManager !== 'undefined') {
    // Add helper methods for list view
    DueDiligenceExportManager.selectAllDeals = function() {
        $('input[name=\"mass[]\"]').prop('checked', true).trigger('change');
    };
    
    DueDiligenceExportManager.clearSelection = function() {
        $('input[name=\"mass[]\"]').prop('checked', false).trigger('change');
    };
    
    DueDiligenceExportManager.showExportHistory = function(dealId) {
        $.ajax({
            url: 'index.php?module=Deals&action=export',
            method: 'POST',
            data: {
                action: 'history',
                deal_id: dealId
            },
            success: function(response) {
                if (response.success) {
                    this.displayExportHistory(response.history);
                } else {
                    this.showError(response.error);
                }
            }.bind(this),
            error: function() {
                this.showError('Failed to load export history');
            }.bind(this)
        });
    };
    
    DueDiligenceExportManager.displayExportHistory = function(history) {
        var historyHtml = '<div class=\"export-history\">';
        historyHtml += '<div class=\"export-history-header\">Export History</div>';
        
        if (history.length === 0) {
            historyHtml += '<div style=\"padding: 20px; text-align: center; color: #6c757d;\">No export history available</div>';
        } else {
            history.forEach(function(item) {
                historyHtml += '<div class=\"export-history-item\">';
                historyHtml += '<div class=\"export-history-info\">';
                historyHtml += '<div><span class=\"export-history-format\">' + item.format.toUpperCase() + '</span>';
                historyHtml += '<span class=\"export-history-size\">' + item.file_size + '</span></div>';
                historyHtml += '<div class=\"export-history-date\">Exported by ' + item.exported_by + ' on ' + item.export_date + '</div>';
                historyHtml += '</div>';
                historyHtml += '<div class=\"export-history-actions\">';
                historyHtml += '<a href=\"' + item.download_url + '\" class=\"export-history-download\">Download</a>';
                historyHtml += '</div>';
                historyHtml += '</div>';
            });
        }
        
        historyHtml += '</div>';
        
        $(historyHtml).dialog({
            title: 'Export History',
            modal: true,
            width: 600,
            height: 400,
            resizable: true,
            buttons: {
                'Close': function() {
                    $(this).dialog('close');
                }
            }
        });
    };
}
</script>";
?>