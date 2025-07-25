{*
 * Stakeholder Detail View Template
 * Enhanced detail view with stakeholder tracking features
 *}

{* Include CSS and JS files *}
<link rel="stylesheet" type="text/css" href="custom/modules/Contacts/css/stakeholder-badges.css">
<link rel="stylesheet" type="text/css" href="custom/modules/Contacts/css/quick-access.css">
<link rel="stylesheet" type="text/css" href="custom/modules/Contacts/css/stakeholder-grid.css">
<script type="text/javascript" src="custom/modules/Contacts/js/stakeholder-badges.js"></script>
<script type="text/javascript" src="custom/modules/Contacts/js/quick-actions.js"></script>

{* Stakeholder Status Panel *}
<div class="stakeholder-status-panel panel">
    <div class="panel-heading">
        <h4>
            Stakeholder Status
            <span class="stakeholder-badge-container" 
                  data-last-contact="{$bean->last_stakeholder_contact_c}" 
                  data-frequency="{$bean->contact_frequency_c}">
            </span>
        </h4>
    </div>
    <div class="panel-body">
        <div class="stakeholder-metrics">
            <div class="metric">
                <label>Role</label>
                <div class="value">
                    {if $bean->stakeholder_role_c}
                        <span class="stakeholder-role {$bean->stakeholder_role_c|lower|replace:' ':'-'}">
                            {$bean->stakeholder_role_c}
                        </span>
                    {else}
                        <span class="text-muted">Not assigned</span>
                    {/if}
                </div>
            </div>
            <div class="metric">
                <label>Contact Frequency</label>
                <div class="value">
                    {if $bean->contact_frequency_c}
                        Every {$bean->contact_frequency_c} days
                    {else}
                        <span class="text-muted">Not set</span>
                    {/if}
                </div>
            </div>
            <div class="metric">
                <label>Last Contact</label>
                <div class="value">
                    {if $bean->last_stakeholder_contact_c}
                        {$bean->last_stakeholder_contact_c|date_format:"%B %d, %Y"}
                        <small class="text-muted">({$bean->last_stakeholder_contact_c|relative_date})</small>
                    {else}
                        <span class="text-muted">Never contacted</span>
                    {/if}
                </div>
            </div>
            <div class="metric">
                <label>Next Due</label>
                <div class="value" id="next-due-date">
                    <span class="text-muted">Calculating...</span>
                </div>
            </div>
        </div>
        
        <div class="stakeholder-actions">
            <button class="btn btn-primary" onclick="StakeholderQuickActions.sendQuickEmail('{$bean->id}')">
                <i class="fa fa-envelope"></i> Send Email
            </button>
            <button class="btn btn-default" onclick="StakeholderQuickActions.logQuickCall('{$bean->id}')">
                <i class="fa fa-phone"></i> Log Call
            </button>
            <button class="btn btn-default" onclick="StakeholderQuickActions.scheduleFollowup('{$bean->id}')">
                <i class="fa fa-calendar"></i> Schedule Follow-up
            </button>
        </div>
    </div>
</div>

{* Contact History Timeline *}
<div class="contact-history-panel panel">
    <div class="panel-heading">
        <h4>Recent Stakeholder Interactions</h4>
    </div>
    <div class="panel-body">
        <div id="contact-timeline" class="loading">
            <div class="stakeholder-grid-spinner"></div>
        </div>
    </div>
</div>

{* Related Deals with Stakeholder Roles *}
{if $bean->deals}
<div class="related-deals-panel panel">
    <div class="panel-heading">
        <h4>Stakeholder in Deals</h4>
    </div>
    <div class="panel-body">
        <div class="deals-list">
            {foreach from=$bean->deals item=deal}
            <div class="deal-item">
                <div class="deal-info">
                    <a href="index.php?module=Deals&action=DetailView&record={$deal.id}">
                        {$deal.name}
                    </a>
                    <span class="deal-stage">{$deal.sales_stage}</span>
                </div>
                <div class="deal-role">
                    {if $deal.stakeholder_role}
                        <span class="stakeholder-role {$deal.stakeholder_role|lower|replace:' ':'-'}">
                            {$deal.stakeholder_role}
                        </span>
                    {/if}
                </div>
            </div>
            {/foreach}
        </div>
    </div>
</div>
{/if}

{literal}
<script type="text/javascript">
$(document).ready(function() {
    // Calculate next due date
    var lastContact = '{/literal}{$bean->last_stakeholder_contact_c}{literal}';
    var frequency = parseInt('{/literal}{$bean->contact_frequency_c}{literal}') || 0;
    
    if (lastContact && frequency) {
        var nextDue = StakeholderBadges.calculateNextDue(lastContact, frequency);
        if (nextDue) {
            var nextDueDate = new Date(nextDue);
            var formattedDate = nextDueDate.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            
            var daysUntil = Math.ceil((nextDueDate - new Date()) / (1000 * 60 * 60 * 24));
            var daysText = daysUntil === 1 ? '1 day' : daysUntil + ' days';
            
            if (daysUntil < 0) {
                $('#next-due-date').html('<span class="text-danger">' + formattedDate + ' (Overdue by ' + Math.abs(daysUntil) + ' days)</span>');
            } else if (daysUntil === 0) {
                $('#next-due-date').html('<span class="text-warning">' + formattedDate + ' (Due today)</span>');
            } else {
                $('#next-due-date').html(formattedDate + ' <small class="text-muted">(in ' + daysText + ')</small>');
            }
        }
    } else {
        $('#next-due-date').html('<span class="text-muted">Not scheduled</span>');
    }
    
    // Load contact timeline
    $.ajax({
        url: 'index.php?module=Contacts&action=GetStakeholderTimeline',
        data: { contact_id: '{/literal}{$bean->id}{literal}' },
        success: function(data) {
            var timelineHtml = '';
            
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    var iconClass = StakeholderQuickActions.getHistoryIcon(item.type);
                    timelineHtml += `
                        <div class="timeline-item">
                            <div class="timeline-icon ${item.type}">
                                <i class="fa fa-${iconClass}"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-header">
                                    <strong>${item.subject}</strong>
                                    <span class="timeline-date">${StakeholderQuickActions.formatRelativeDate(item.date)}</span>
                                </div>
                                ${item.description ? '<div class="timeline-body">' + item.description + '</div>' : ''}
                            </div>
                        </div>
                    `;
                });
            } else {
                timelineHtml = '<div class="empty-timeline">No stakeholder interactions recorded yet.</div>';
            }
            
            $('#contact-timeline').removeClass('loading').html(timelineHtml);
        },
        error: function() {
            $('#contact-timeline').removeClass('loading').html('<div class="error-message">Failed to load timeline</div>');
        }
    });
    
    // Initialize badges
    StakeholderBadges.updateAllBadges();
});
</script>
{/literal}

<style>
/* Detail view styles */
.stakeholder-status-panel {
    margin-bottom: 20px;
}

.stakeholder-metrics {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.metric {
    padding: 16px;
    background-color: #f9fafb;
    border-radius: 6px;
}

.metric label {
    display: block;
    font-size: 12px;
    color: #6b7280;
    text-transform: uppercase;
    margin-bottom: 4px;
}

.metric .value {
    font-size: 16px;
    color: #111827;
}

.stakeholder-actions {
    display: flex;
    gap: 10px;
}

/* Timeline styles */
.timeline-item {
    display: flex;
    gap: 16px;
    padding: 16px 0;
    border-bottom: 1px solid #e5e7eb;
}

.timeline-item:last-child {
    border-bottom: none;
}

.timeline-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.timeline-icon.email {
    background-color: #eff6ff;
    color: #3b82f6;
}

.timeline-icon.call {
    background-color: #f0fdf4;
    color: #10b981;
}

.timeline-icon.meeting {
    background-color: #fef3c7;
    color: #f59e0b;
}

.timeline-content {
    flex: 1;
}

.timeline-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4px;
}

.timeline-date {
    font-size: 12px;
    color: #6b7280;
}

.timeline-body {
    font-size: 14px;
    color: #4b5563;
}

/* Deals list */
.deals-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.deal-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    background-color: #f9fafb;
    border-radius: 6px;
}

.deal-stage {
    margin-left: 8px;
    font-size: 12px;
    color: #6b7280;
}

.loading {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 200px;
}

.empty-timeline {
    text-align: center;
    color: #6b7280;
    padding: 40px;
}
</style>