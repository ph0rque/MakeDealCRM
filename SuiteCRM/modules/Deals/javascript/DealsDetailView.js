/**
 * Deals Detail View JavaScript
 * Handles quick actions and activity timeline
 */

var DealsDetailView = {
    
    /**
     * Log a call for this deal
     */
    logCall: function(dealId) {
        var url = 'index.php?module=Calls&action=EditView&return_module=Deals&return_id=' + dealId;
        url += '&parent_type=Deals&parent_id=' + dealId + '&parent_name=' + encodeURIComponent(document.getElementById('name').innerHTML);
        window.location.href = url;
    },
    
    /**
     * Schedule a meeting for this deal
     */
    scheduleMeeting: function(dealId) {
        var url = 'index.php?module=Meetings&action=EditView&return_module=Deals&return_id=' + dealId;
        url += '&parent_type=Deals&parent_id=' + dealId + '&parent_name=' + encodeURIComponent(document.getElementById('name').innerHTML);
        window.location.href = url;
    },
    
    /**
     * Create a task for this deal
     */
    createTask: function(dealId) {
        var url = 'index.php?module=Tasks&action=EditView&return_module=Deals&return_id=' + dealId;
        url += '&parent_type=Deals&parent_id=' + dealId + '&parent_name=' + encodeURIComponent(document.getElementById('name').innerHTML);
        window.location.href = url;
    },
    
    /**
     * Send email related to this deal
     */
    sendEmail: function(dealId) {
        var url = 'index.php?module=Emails&action=Compose&return_module=Deals&return_id=' + dealId;
        url += '&parent_type=Deals&parent_id=' + dealId;
        
        // If account has email, pre-populate
        var accountEmail = $('#account_email').val();
        if (accountEmail) {
            url += '&to_email_addrs=' + encodeURIComponent(accountEmail);
        }
        
        openQuickCreateModal(url);
    },
    
    /**
     * Convert deal to quote
     */
    convertToQuote: function(dealId) {
        if (confirm('Are you sure you want to create a quote from this deal?')) {
            $.ajax({
                url: 'index.php?module=Deals&action=ConvertToQuote&to_pdf=1',
                type: 'POST',
                data: {
                    record: dealId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Quote created successfully!');
                        window.location.href = 'index.php?module=AOS_Quotes&action=DetailView&record=' + response.quote_id;
                    } else {
                        alert('Error creating quote: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error creating quote. Please try again.');
                }
            });
        }
    },
    
    /**
     * Load activity timeline
     */
    loadActivityTimeline: function(dealId) {
        $.ajax({
            url: 'index.php?module=Deals&action=GetActivityTimeline&to_pdf=1',
            type: 'GET',
            data: {
                record: dealId
            },
            dataType: 'json',
            success: function(response) {
                DealsDetailView.renderTimeline(response);
            },
            error: function() {
                $('#timelineContent').html('<div class="error">Error loading activities</div>');
            }
        });
    },
    
    /**
     * Render the activity timeline
     */
    renderTimeline: function(activities) {
        if (!activities || activities.length === 0) {
            $('#timelineContent').html('<div class="no-activities">No activities found</div>');
            return;
        }
        
        var html = '';
        $.each(activities, function(index, activity) {
            html += '<div class="timeline-item">';
            html += '<div class="timeline-content">';
            html += '<div class="timeline-header">';
            html += '<strong>' + activity.type + '</strong> - ';
            html += '<span class="timeline-date">' + activity.date_entered + '</span>';
            html += '</div>';
            html += '<div class="timeline-body">';
            html += '<p>' + activity.description + '</p>';
            if (activity.assigned_user_name) {
                html += '<small>By: ' + activity.assigned_user_name + '</small>';
            }
            html += '</div>';
            html += '</div>';
            html += '</div>';
        });
        
        $('#timelineContent').html(html);
    },
    
    /**
     * Update deal stage
     */
    updateStage: function(dealId, newStage) {
        if (confirm('Are you sure you want to update the sales stage?')) {
            $.ajax({
                url: 'index.php?module=Deals&action=UpdateStage&to_pdf=1',
                type: 'POST',
                data: {
                    record: dealId,
                    sales_stage: newStage
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error updating stage: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error updating stage. Please try again.');
                }
            });
        }
    },
    
    /**
     * Calculate ROI for the deal
     */
    calculateROI: function(dealId) {
        // This could open a modal or inline calculator
        var amount = parseFloat($('#amount').text().replace(/[^0-9.-]+/g, ''));
        var cost = prompt('Enter the estimated cost for this deal:');
        
        if (cost && !isNaN(cost)) {
            cost = parseFloat(cost);
            var roi = ((amount - cost) / cost) * 100;
            alert('Estimated ROI: ' + roi.toFixed(2) + '%');
        }
    },
    
    /**
     * Show deal analytics
     */
    showAnalytics: function(dealId) {
        var url = 'index.php?module=Deals&action=Analytics&record=' + dealId;
        window.open(url, 'deal_analytics', 'width=1000,height=700,resizable=yes,scrollbars=yes');
    },
    
    /**
     * Clone this deal
     */
    cloneDeal: function(dealId) {
        if (confirm('Are you sure you want to create a copy of this deal?')) {
            window.location.href = 'index.php?module=Deals&action=EditView&isDuplicate=true&record=' + dealId;
        }
    },
    
    /**
     * Print deal summary
     */
    printSummary: function(dealId) {
        window.open('index.php?module=Deals&action=PrintView&record=' + dealId, '_blank');
    }
};

// Initialize on page load
$(document).ready(function() {
    // Add keyboard shortcuts
    $(document).keydown(function(e) {
        if (e.ctrlKey || e.metaKey) {
            switch (e.which) {
                case 69: // Ctrl/Cmd + E = Edit
                    e.preventDefault();
                    $('#edit_button').click();
                    break;
                case 68: // Ctrl/Cmd + D = Duplicate
                    e.preventDefault();
                    $('#duplicate_button').click();
                    break;
            }
        }
    });
    
    // Auto-refresh timeline every 60 seconds
    if ($('#activityTimeline').length > 0) {
        setInterval(function() {
            var dealId = $('input[name="record"]').val();
            if (dealId) {
                DealsDetailView.loadActivityTimeline(dealId);
            }
        }, 60000);
    }
});