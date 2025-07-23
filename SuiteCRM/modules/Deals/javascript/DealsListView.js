/**
 * Deals List View JavaScript
 * Handles list view interactions and bulk actions
 */

var DealsListView = {
    
    /**
     * Mass update sales stage
     */
    massUpdateStage: function() {
        var selectedIds = this.getSelectedIds();
        
        if (selectedIds.length === 0) {
            alert('Please select at least one deal to update.');
            return;
        }
        
        // Create stage selection dialog
        var stages = [
            'Prospecting',
            'Qualification',
            'Needs Analysis',
            'Value Proposition',
            'Id. Decision Makers',
            'Perception Analysis',
            'Proposal/Price Quote',
            'Negotiation/Review',
            'Closed Won',
            'Closed Lost'
        ];
        
        var html = '<div id="massUpdateStageDialog" style="display:none;">';
        html += '<h3>Select New Stage</h3>';
        html += '<select id="newStageSelect" class="form-control">';
        html += '<option value="">-- Select Stage --</option>';
        
        $.each(stages, function(index, stage) {
            html += '<option value="' + stage + '">' + stage + '</option>';
        });
        
        html += '</select>';
        html += '<div style="margin-top: 15px;">';
        html += '<button type="button" onclick="DealsListView.executeMassUpdateStage();" class="button primary">Update</button> ';
        html += '<button type="button" onclick="DealsListView.closeMassUpdateDialog();" class="button">Cancel</button>';
        html += '</div>';
        html += '</div>';
        
        $('body').append(html);
        
        // Show dialog
        $('#massUpdateStageDialog').dialog({
            title: 'Mass Update Stage',
            modal: true,
            width: 400,
            close: function() {
                $(this).remove();
            }
        });
    },
    
    /**
     * Execute mass update stage
     */
    executeMassUpdateStage: function() {
        var selectedIds = this.getSelectedIds();
        var newStage = $('#newStageSelect').val();
        
        if (!newStage) {
            alert('Please select a stage.');
            return;
        }
        
        // Show loading
        SUGAR.ajaxUI.showLoadingPanel();
        
        $.ajax({
            url: 'index.php?module=Deals&action=MassUpdateStage&to_pdf=1',
            type: 'POST',
            data: {
                ids: selectedIds,
                stage: newStage
            },
            dataType: 'json',
            success: function(response) {
                SUGAR.ajaxUI.hideLoadingPanel();
                
                if (response.success) {
                    alert('Successfully updated ' + response.updated + ' deals.');
                    $('#massUpdateStageDialog').dialog('close');
                    // Refresh list view
                    $('#MassUpdate').submit();
                } else {
                    alert('Error updating deals: ' + response.message);
                }
            },
            error: function() {
                SUGAR.ajaxUI.hideLoadingPanel();
                alert('Error updating deals. Please try again.');
            }
        });
    },
    
    /**
     * Mass assign deals
     */
    massAssign: function() {
        var selectedIds = this.getSelectedIds();
        
        if (selectedIds.length === 0) {
            alert('Please select at least one deal to assign.');
            return;
        }
        
        // Open user selection popup
        open_popup('Users', 600, 400, '', true, false, {
            'call_back_function': 'DealsListView.setAssignedUser',
            'form_name': 'MassUpdate',
            'field_to_name_array': {
                'id': 'assigned_user_id',
                'user_name': 'assigned_user_name'
            }
        });
    },
    
    /**
     * Set assigned user after selection
     */
    setAssignedUser: function(popup_reply_data) {
        var selectedIds = this.getSelectedIds();
        var userId = popup_reply_data.name_to_value_array.assigned_user_id;
        var userName = popup_reply_data.name_to_value_array.assigned_user_name;
        
        if (confirm('Assign ' + selectedIds.length + ' deals to ' + userName + '?')) {
            SUGAR.ajaxUI.showLoadingPanel();
            
            $.ajax({
                url: 'index.php?module=Deals&action=MassAssign&to_pdf=1',
                type: 'POST',
                data: {
                    ids: selectedIds,
                    assigned_user_id: userId
                },
                dataType: 'json',
                success: function(response) {
                    SUGAR.ajaxUI.hideLoadingPanel();
                    
                    if (response.success) {
                        alert('Successfully assigned ' + response.updated + ' deals.');
                        // Refresh list view
                        $('#MassUpdate').submit();
                    } else {
                        alert('Error assigning deals: ' + response.message);
                    }
                },
                error: function() {
                    SUGAR.ajaxUI.hideLoadingPanel();
                    alert('Error assigning deals. Please try again.');
                }
            });
        }
    },
    
    /**
     * Export selected deals to Excel
     */
    exportToExcel: function() {
        var selectedIds = this.getSelectedIds();
        
        if (selectedIds.length === 0) {
            if (!confirm('No deals selected. Export all deals in current view?')) {
                return;
            }
        }
        
        // Build export URL
        var url = 'index.php?module=Deals&action=Export&uid=' + selectedIds.join(',');
        window.location.href = url;
    },
    
    /**
     * Generate report for selected deals
     */
    generateReport: function() {
        var selectedIds = this.getSelectedIds();
        
        if (selectedIds.length === 0) {
            alert('Please select at least one deal for the report.');
            return;
        }
        
        // Open report options dialog
        var html = '<div id="reportOptionsDialog" style="display:none;">';
        html += '<h3>Report Options</h3>';
        html += '<div class="report-options">';
        html += '<label><input type="checkbox" id="reportSummary" checked> Summary Statistics</label><br>';
        html += '<label><input type="checkbox" id="reportTimeline" checked> Sales Timeline</label><br>';
        html += '<label><input type="checkbox" id="reportActivities" checked> Activities Summary</label><br>';
        html += '<label><input type="checkbox" id="reportForecast"> Revenue Forecast</label><br>';
        html += '</div>';
        html += '<div style="margin-top: 15px;">';
        html += '<button type="button" onclick="DealsListView.executeGenerateReport();" class="button primary">Generate</button> ';
        html += '<button type="button" onclick="$(\'#reportOptionsDialog\').dialog(\'close\');" class="button">Cancel</button>';
        html += '</div>';
        html += '</div>';
        
        $('body').append(html);
        
        $('#reportOptionsDialog').dialog({
            title: 'Generate Deals Report',
            modal: true,
            width: 400,
            close: function() {
                $(this).remove();
            }
        });
    },
    
    /**
     * Execute report generation
     */
    executeGenerateReport: function() {
        var selectedIds = this.getSelectedIds();
        var options = {
            summary: $('#reportSummary').is(':checked'),
            timeline: $('#reportTimeline').is(':checked'),
            activities: $('#reportActivities').is(':checked'),
            forecast: $('#reportForecast').is(':checked')
        };
        
        // Open report in new window
        var url = 'index.php?module=Deals&action=GenerateReport';
        url += '&ids=' + selectedIds.join(',');
        url += '&options=' + encodeURIComponent(JSON.stringify(options));
        
        window.open(url, 'deals_report', 'width=1000,height=700,resizable=yes,scrollbars=yes');
        
        $('#reportOptionsDialog').dialog('close');
    },
    
    /**
     * Get selected record IDs
     */
    getSelectedIds: function() {
        var ids = [];
        $('input[name="mass[]"]:checked').each(function() {
            ids.push($(this).val());
        });
        return ids;
    },
    
    /**
     * Close mass update dialog
     */
    closeMassUpdateDialog: function() {
        $('#massUpdateStageDialog').dialog('close');
    },
    
    /**
     * Quick filter by stage
     */
    filterByStage: function(stage) {
        $('#sales_stage_advanced').val(stage);
        SUGAR.savedViews.shortcut_submit(document.getElementById('search_form'), 'Deals');
    },
    
    /**
     * Quick filter by date range
     */
    filterByDateRange: function(range) {
        var startDate, endDate;
        var today = new Date();
        
        switch(range) {
            case 'today':
                startDate = endDate = today;
                break;
            case 'yesterday':
                startDate = endDate = new Date(today.getTime() - 24*60*60*1000);
                break;
            case 'this_week':
                startDate = new Date(today.getTime() - today.getDay()*24*60*60*1000);
                endDate = today;
                break;
            case 'last_week':
                var lastWeek = new Date(today.getTime() - 7*24*60*60*1000);
                startDate = new Date(lastWeek.getTime() - lastWeek.getDay()*24*60*60*1000);
                endDate = new Date(startDate.getTime() + 6*24*60*60*1000);
                break;
            case 'this_month':
                startDate = new Date(today.getFullYear(), today.getMonth(), 1);
                endDate = today;
                break;
            case 'last_month':
                startDate = new Date(today.getFullYear(), today.getMonth()-1, 1);
                endDate = new Date(today.getFullYear(), today.getMonth(), 0);
                break;
        }
        
        // Format dates
        var formatDate = function(date) {
            return date.getFullYear() + '-' + 
                   ('0' + (date.getMonth() + 1)).slice(-2) + '-' + 
                   ('0' + date.getDate()).slice(-2);
        };
        
        $('#date_entered_advanced_range_start').val(formatDate(startDate));
        $('#date_entered_advanced_range_end').val(formatDate(endDate));
        
        SUGAR.savedViews.shortcut_submit(document.getElementById('search_form'), 'Deals');
    }
};

// Initialize on page load
$(document).ready(function() {
    // Add quick filter buttons
    var filterHtml = '<div class="quick-filters" style="margin: 10px 0;">';
    filterHtml += '<span>Quick Filters: </span>';
    filterHtml += '<button type="button" class="button" onclick="DealsListView.filterByStage(\'Prospecting\')">Prospecting</button> ';
    filterHtml += '<button type="button" class="button" onclick="DealsListView.filterByStage(\'Negotiation/Review\')">In Negotiation</button> ';
    filterHtml += '<button type="button" class="button" onclick="DealsListView.filterByStage(\'Closed Won\')">Won</button> ';
    filterHtml += '<button type="button" class="button" onclick="DealsListView.filterByDateRange(\'this_month\')">This Month</button> ';
    filterHtml += '<button type="button" class="button" onclick="DealsListView.filterByDateRange(\'last_month\')">Last Month</button>';
    filterHtml += '</div>';
    
    $('.list-view-rounded-corners').before(filterHtml);
});