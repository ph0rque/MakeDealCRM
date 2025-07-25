{*
 * Stakeholder List View Template
 * Enhanced list view with freshness badges and quick actions
 *}

{* Include CSS and JS files *}
<link rel="stylesheet" type="text/css" href="custom/modules/Contacts/css/stakeholder-badges.css">
<link rel="stylesheet" type="text/css" href="custom/modules/Contacts/css/quick-access.css">
<script type="text/javascript" src="custom/modules/Contacts/js/stakeholder-badges.js"></script>
<script type="text/javascript" src="custom/modules/Contacts/js/quick-actions.js"></script>

{* Enhance existing list view rows *}
{literal}
<script type="text/javascript">
$(document).ready(function() {
    // Add stakeholder badges to list view
    $('.list.view tr[data-id]').each(function() {
        var $row = $(this);
        var contactId = $row.data('id');
        
        // Get contact data from row
        var lastContact = $row.find('.last_contact_date').text();
        var frequency = $row.find('.contact_frequency').data('value');
        var role = $row.find('.stakeholder_role').text();
        
        // Add container for badge
        var $nameCell = $row.find('.name-cell, td:first-child');
        $nameCell.append('<span class="stakeholder-badge-container" data-contact-id="' + contactId + '" data-last-contact="' + lastContact + '" data-frequency="' + frequency + '"></span>');
        
        // Add quick action buttons
        var quickActionsHtml = `
            <div class="quick-action-buttons">
                <button class="quick-action-btn email" data-action="quick-email" title="Send Email">
                    <i class="fa fa-envelope"></i>
                </button>
                <button class="quick-action-btn call" data-action="quick-call" title="Log Call">
                    <i class="fa fa-phone"></i>
                </button>
                <button class="quick-action-btn note" data-action="quick-note" title="Add Note">
                    <i class="fa fa-sticky-note"></i>
                </button>
            </div>
        `;
        
        // Add to actions column or create one
        var $actionsCell = $row.find('.actions-cell, td:last-child');
        $actionsCell.prepend(quickActionsHtml);
        
        // Add tooltip for contact history
        $nameCell.addClass('stakeholder-tooltip');
        $nameCell.append('<div class="contact-history-preview"></div>');
    });
    
    // Initialize badges
    StakeholderBadges.updateAllBadges();
});
</script>
{/literal}

{* Filter for overdue stakeholders *}
{if $smarty.request.filter == 'overdue_stakeholders'}
<div class="overdue-filter-notice">
    <i class="fa fa-exclamation-triangle"></i>
    Showing only overdue stakeholder contacts
    <a href="index.php?module=Contacts&action=index" class="clear-filter">Clear Filter</a>
</div>
{/if}

{* Bulk actions toolbar *}
<div class="stakeholder-bulk-actions">
    <button class="btn btn-sm" onclick="StakeholderQuickActions.openBulkEmailDialog()">
        <i class="fa fa-envelope"></i> Bulk Email
    </button>
    <button class="btn btn-sm" onclick="StakeholderQuickActions.exportStakeholders()">
        <i class="fa fa-download"></i> Export
    </button>
</div>

<style>
/* Additional list view styles */
.overdue-filter-notice {
    background-color: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
    padding: 12px;
    margin-bottom: 16px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.clear-filter {
    margin-left: auto;
    color: #3b82f6;
    text-decoration: none;
}

.stakeholder-bulk-actions {
    margin-bottom: 16px;
    display: flex;
    gap: 8px;
}

.name-cell {
    position: relative;
}

.actions-cell {
    width: 150px;
}

/* List view badge adjustments */
.list.view .stakeholder-badge {
    vertical-align: middle;
}

.list.view .quick-action-buttons {
    display: inline-flex;
    margin-right: 8px;
}
</style>