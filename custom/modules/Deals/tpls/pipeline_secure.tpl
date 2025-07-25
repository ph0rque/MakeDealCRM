{*
 * Secure Pipeline Kanban Board Template
 * Displays deals in a horizontal scrollable Kanban board with security features
 *}

<a href="#pipeline-board" class="skip-link">Skip to pipeline board</a>

{* Include CSRF token for all AJAX requests *}
<input type="hidden" id="csrf_token" name="csrf_token" value="{$csrf_token|escape:'html'}">

<div id="pipeline-container" class="pipeline-container {if $is_mobile}mobile-view{/if}" role="main" aria-label="Deal Pipeline">
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
                <div class="pipeline-stage" data-stage="{$stage_key|escape:'html'}" role="list" aria-label="{$stage_name|escape:'html'} stage">
                    <div class="stage-header">
                        <h3 id="stage-{$stage_key|escape:'html'}-title">{$stage_name|escape:'html'}</h3>
                        <div class="stage-stats">
                            <span class="deal-count">{$deals_by_stage.$stage_key|@count}</span>
                            {if $wip_limits.$stage_key}
                                <span class="wip-limit-indicator {if $deals_by_stage.$stage_key|@count >= $wip_limits.$stage_key}over-limit{elseif $deals_by_stage.$stage_key|@count >= $wip_limits.$stage_key * 0.8}near-limit{/if}">
                                    / {$wip_limits.$stage_key|escape:'html'}
                                </span>
                            {/if}
                        </div>
                    </div>
                    
                    <div class="stage-body droppable" data-stage="{$stage_key|escape:'html'}" data-wip-limit="{$wip_limits.$stage_key|default:999}" 
                         role="group" aria-labelledby="stage-{$stage_key|escape:'html'}-title">
                        {foreach from=$deals_by_stage.$stage_key item=deal}
                            <div class="deal-card draggable {$deal.stage_color_class|escape:'html'} {if $deal.focus_flag_c}focused-deal{/if}" 
                                 data-deal-id="{$deal.id|escape:'html'}" 
                                 data-stage="{$stage_key|escape:'html'}"
                                 data-focused="{if $deal.focus_flag_c}true{else}false{/if}"
                                 data-focus-order="{$deal.focus_order_c|default:0}"
                                 role="listitem"
                                 tabindex="0"
                                 aria-label="Deal: {$deal.name|escape:'html'}">
                                
                                <div class="deal-header">
                                    <div class="deal-name">
                                        <a href="index.php?module=Deals&action=DetailView&record={$deal.id|escape:'url'}" 
                                           onclick="event.preventDefault(); PipelineView.openDealDetail('{$deal.id|escape:'javascript'}');"
                                           aria-label="View details for {$deal.name|escape:'html'}">
                                            {$deal.name|escape:'html'|truncate:50}
                                        </a>
                                    </div>
                                    <div class="deal-actions">
                                        <button class="btn-icon focus-toggle" 
                                                onclick="PipelineView.toggleFocus('{$deal.id|escape:'javascript'}')"
                                                aria-label="{if $deal.focus_flag_c}Remove focus from{else}Add focus to{/if} {$deal.name|escape:'html'}"
                                                title="{if $deal.focus_flag_c}Remove focus{else}Add focus{/if}">
                                            <span class="glyphicon glyphicon-star {if !$deal.focus_flag_c}glyphicon-star-empty{/if}" aria-hidden="true"></span>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="deal-info">
                                    {if $deal.account_name}
                                        <div class="deal-account" title="Account: {$deal.account_name|escape:'html'}">
                                            <span class="glyphicon glyphicon-briefcase" aria-hidden="true"></span>
                                            {$deal.account_name|escape:'html'|truncate:30}
                                        </div>
                                    {/if}
                                    
                                    <div class="deal-amount">
                                        <span class="glyphicon glyphicon-usd" aria-hidden="true"></span>
                                        ${$deal.amount|number_format:0}
                                    </div>
                                    
                                    <div class="deal-probability">
                                        <span class="probability-badge probability-{if $deal.probability >= 70}high{elseif $deal.probability >= 40}medium{else}low{/if}">
                                            {$deal.probability|escape:'html'}%
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="deal-footer">
                                    <div class="assigned-user" title="Assigned to: {$deal.assigned_user_name|escape:'html'}">
                                        <span class="glyphicon glyphicon-user" aria-hidden="true"></span>
                                        {$deal.assigned_user_name|escape:'html'|truncate:20}
                                    </div>
                                    
                                    <div class="days-in-stage {$deal.stage_color_class|escape:'html'}" 
                                         title="{$deal.days_in_stage|escape:'html'} days in {$stage_name|escape:'html'}">
                                        <span class="glyphicon glyphicon-time" aria-hidden="true"></span>
                                        {$deal.days_in_stage|escape:'html'}d
                                    </div>
                                </div>

                                {* Stakeholder badges *}
                                <div class="stakeholder-badges" id="stakeholder-badges-{$deal.id|escape:'html'}">
                                    {* Badges will be loaded dynamically via JavaScript *}
                                </div>
                            </div>
                        {/foreach}
                    </div>
                </div>
            {/foreach}
        </div>
    </div>
</div>

{* Loading indicator *}
<div id="pipeline-loading" class="pipeline-loading" style="display:none;" role="status" aria-live="polite">
    <div class="loading-spinner"></div>
    <span>Updating pipeline...</span>
</div>

{* JavaScript initialization with security *}
<script type="text/javascript">
{literal}
    // Initialize pipeline view with CSRF token
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof PipelineView !== 'undefined') {
            PipelineView.csrfToken = document.getElementById('csrf_token').value;
            PipelineView.currentUserId = '{/literal}{$current_user_id|escape:'javascript'}{literal}';
            PipelineView.init();
        }
    });
{/literal}
</script>