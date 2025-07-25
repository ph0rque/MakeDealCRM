{*
 * Pipeline Kanban Board Template - SuiteCRM Theme Integrated
 * Displays deals in a horizontal scrollable Kanban board with drag-and-drop
 * Compatible with SuiteCRM Smarty template system and theme engine
 *}

{* Skip link for accessibility *}
<a href="#pipeline-board" class="skip-link">Skip to pipeline board</a>

{* Theme detection and CSS variables setup *}
<script type="text/javascript">
{literal}
    // Detect current theme and subtheme for CSS variables
    document.addEventListener('DOMContentLoaded', function() {
        var body = document.body;
        var themeClass = '';
        
        // Detect SuiteCRM theme from body classes or global variables
        if (typeof SUGAR !== 'undefined' && SUGAR.themes && SUGAR.themes.theme_name) {
            themeClass = SUGAR.themes.theme_name;
        } else if (body.className.includes('SuiteP')) {
            themeClass = 'SuiteP';
        } else if (body.className.includes('Suite7')) {
            themeClass = 'Suite7';
        } else {
            themeClass = 'default';
        }
        
        // Add theme class to body if not present
        if (!body.classList.contains(themeClass)) {
            body.classList.add(themeClass);
        }
        
        // Detect subtheme for SuiteP
        if (themeClass === 'SuiteP' && typeof SUGAR !== 'undefined' && SUGAR.themes && SUGAR.themes.sub_theme) {
            body.setAttribute('data-subtheme', SUGAR.themes.sub_theme);
        }
        
        // Apply device-specific classes
{/literal}
        body.classList.add({if $is_mobile}'mobile-device'{else}'desktop-device'{/if});
{literal}
    });
{/literal}
</script>

{* Main pipeline container with responsive and theme-aware classes *}
<div id="pipeline-container" class="pipeline-container theme-aware {if $is_mobile}mobile-view{/if}" 
     role="main" aria-label="Deal Pipeline" 
     data-theme="{$current_theme|default:'SuiteP'}" 
     data-subtheme="{$current_subtheme|default:'Dawn'}"
     data-mobile="{if $is_mobile}true{else}false{/if}">
    <div class="pipeline-header">
        <h2 id="pipeline-title">Deal Pipeline</h2>
        <div class="pipeline-actions" role="toolbar" aria-label="Pipeline actions">
            <button class="btn btn-primary btn-sm" onclick="PipelineView.refreshBoard()" aria-label="Refresh pipeline board" title="Refresh pipeline board">
                <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span> Refresh
            </button>
            <button class="btn btn-default btn-sm" onclick="PipelineView.toggleCompactView()" aria-label="Toggle compact view" title="Toggle compact view">
                <span class="glyphicon glyphicon-resize-small" aria-hidden="true"></span> Compact View
            </button>
            <button class="btn btn-info btn-sm" onclick="PipelineView.toggleFocusFilter()" id="focus-filter-btn" aria-label="Toggle focus filter" title="Toggle focus filter">
                <span class="glyphicon glyphicon-star" aria-hidden="true"></span> <span id="focus-filter-text">Show Focused</span>
            </button>
            <button class="btn btn-warning btn-sm" onclick="PipelineView.openBulkStakeholders()" aria-label="Manage stakeholders" title="Bulk stakeholder management">
                <span class="glyphicon glyphicon-user" aria-hidden="true"></span> Manage Stakeholders
            </button>
        </div>
    </div>

    <div class="pipeline-board-wrapper" role="region" aria-label="Pipeline board">
        <div class="pipeline-board" id="pipeline-board">
            {foreach from=$stages key=stage_key item=stage_name}
                <div class="pipeline-stage" data-stage="{$stage_key}" role="list" aria-label="{$stage_name} stage">
                    <div class="stage-header">
                        <h3 id="stage-{$stage_key}-title">{$stage_name}</h3>
                        <div class="stage-stats">
                            <span class="deal-count">{if isset($deals_by_stage.$stage_key) && is_array($deals_by_stage.$stage_key)}{$deals_by_stage.$stage_key|@count}{else}0{/if}</span>
                            {if $wip_limits.$stage_key}
                                {assign var="stage_count" value=0}
                                {if isset($deals_by_stage.$stage_key) && is_array($deals_by_stage.$stage_key)}
                                    {assign var="stage_count" value=$deals_by_stage.$stage_key|@count}
                                {/if}
                                <span class="wip-limit-indicator {if $stage_count >= $wip_limits.$stage_key}over-limit{elseif $stage_count >= $wip_limits.$stage_key * 0.8}near-limit{/if}">
                                    / {$wip_limits.$stage_key}
                                </span>
                            {/if}
                        </div>
                    </div>
                    
                    <div class="stage-body droppable" data-stage="{$stage_key}" data-wip-limit="{$wip_limits.$stage_key|default:999}" 
                         role="group" aria-labelledby="stage-{$stage_key}-title">
                        {if isset($deals_by_stage.$stage_key) && is_array($deals_by_stage.$stage_key)}
                        {foreach from=$deals_by_stage.$stage_key item=deal}
                            <div class="deal-card draggable theme-aware {$deal.stage_color_class} {if $deal.focus_flag_c}focused-deal{/if}" 
                                 data-deal-id="{$deal.id}" 
                                 data-stage="{$stage_key}"
                                 data-focused="{if $deal.focus_flag_c}true{else}false{/if}"
                                 data-focus-order="{$deal.focus_order_c|default:0}"
                                 draggable="true"
                                 role="listitem"
                                 tabindex="0"
                                 aria-label="Deal: {$deal.name|escape}"
                                 aria-describedby="deal-{$deal.id}-details"
                                 aria-grabbed="false">
                                
                                <div class="deal-card-header">
                                    <h4 class="deal-name">
                                        <a href="index.php?module=Opportunities&action=DetailView&record={$deal.id}" 
                                           target="_blank" 
                                           onclick="event.stopPropagation();">
                                            {$deal.name|truncate:50}
                                        </a>
                                    </h4>
                                    <div class="deal-card-actions">
                                        <button class="focus-toggle-btn theme-aware {if $deal.focus_flag_c}active{/if}" 
                                                onclick="PipelineView.toggleFocus('{$deal.id}', {if $deal.focus_flag_c}false{else}true{/if}); event.stopPropagation();" 
                                                title="{if $deal.focus_flag_c}Remove focus{else}Mark as focused{/if}"
                                                aria-label="{if $deal.focus_flag_c}Remove focus from {$deal.name|escape}{else}Mark {$deal.name|escape} as focused{/if}">
                                            <span class="glyphicon glyphicon-star{if !$deal.focus_flag_c}-empty{/if}" aria-hidden="true"></span>
                                        </button>
                                        <div class="deal-days-indicator" title="Days in current stage">
                                            <span class="glyphicon glyphicon-time"></span> {$deal.days_in_stage}d
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="deal-card-body" id="deal-{$deal.id}-details">
                                    {if isset($deal.account_name) && $deal.account_name}
                                        <div class="deal-account">
                                            <span class="glyphicon glyphicon-briefcase" aria-hidden="true"></span> 
                                            <span class="sr-only">Account: </span>{$deal.account_name|truncate:30}
                                        </div>
                                    {/if}
                                    
                                    {if $deal.amount}
                                        <div class="deal-amount">
                                            <span class="glyphicon glyphicon-usd" aria-hidden="true"></span> 
                                            <span class="sr-only">Amount: $</span>{$deal.amount|number_format:0}
                                        </div>
                                    {/if}
                                    
                                    {if $deal.assigned_user_name}
                                        <div class="deal-assigned">
                                            <span class="glyphicon glyphicon-user" aria-hidden="true"></span> 
                                            <span class="sr-only">Assigned to: </span>{$deal.assigned_user_name}
                                        </div>
                                    {/if}
                                    
                                    {if $deal.expected_close_date_c}
                                        <div class="deal-close-date">
                                            <span class="glyphicon glyphicon-calendar" aria-hidden="true"></span> 
                                            <span class="sr-only">Expected close date: </span>{$deal.expected_close_date_c}
                                        </div>
                                    {/if}
                                </div>
                                
                                {if $deal.probability}
                                    <div class="deal-probability">
                                        <div class="probability-bar" style="width: {$deal.probability}%"></div>
                                        <span class="probability-text">{$deal.probability}%</span>
                                    </div>
                                {/if}
                                
                                <!-- Progress Indicators will be dynamically inserted here by JavaScript -->
                                <div class="deal-progress-placeholder" data-deal-id="{$deal.id}" style="display: none;">
                                    <!-- Progress indicators loaded via ProgressIndicators.js -->
                                </div>
                            </div>
                        {/foreach}
                        {/if}
                        
                        {if !isset($deals_by_stage.$stage_key) || !is_array($deals_by_stage.$stage_key) || $deals_by_stage.$stage_key|@count == 0}
                            <div class="empty-stage-placeholder" role="listitem">
                                <span class="glyphicon glyphicon-inbox" aria-hidden="true"></span>
                                <p>Drop deals here</p>
                            </div>
                        {/if}
                    </div>
                </div>
            {/foreach}
        </div>
    </div>
    
    <!-- Mobile swipe hint -->
    {if $is_mobile}
        <div class="mobile-swipe-hint" aria-live="polite">
            <span class="glyphicon glyphicon-hand-left" aria-hidden="true"></span> Swipe to see more stages
        </div>
    {/if}
    
    <!-- Keyboard navigation hint -->
    <div class="keyboard-nav-hint" aria-live="polite">
        Use Tab to navigate, Enter to select, Space to focus
    </div>
</div>

<!-- Drag ghost preview -->
<div id="drag-ghost" class="deal-card drag-ghost" style="display: none;"></div>

<!-- Loading overlay -->
<div id="pipeline-loading" class="pipeline-loading" style="display: none;">
    <div class="loading-spinner">
        <span class="glyphicon glyphicon-refresh glyphicon-spin"></span>
        <p>Updating pipeline...</p>
    </div>
</div>

<script type="text/javascript">
{literal}
    // Initialize Pipeline View
    jQuery(document).ready(function() {
        PipelineView.init({
{/literal}
            currentUserId: '{$current_user_id}',
            isMobile: {if $is_mobile}true{else}false{/if},
            updateUrl: 'index.php?module=Deals&action=updatePipelineStage',
            refreshUrl: 'index.php?module=Deals&action=Pipeline&sugar_body_only=1'
{literal}
        });
    });
{/literal}
</script>