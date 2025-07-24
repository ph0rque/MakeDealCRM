{*
 * Pipeline Kanban Board Template
 * Displays deals in a horizontal scrollable Kanban board with drag-and-drop
 *}

<div id="pipeline-container" class="pipeline-container {if $is_mobile}mobile-view{/if}">
    <div class="pipeline-header">
        <h2>Deal Pipeline</h2>
        <div class="pipeline-actions">
            <button class="btn btn-primary" onclick="PipelineView.refreshBoard()">
                <i class="glyphicon glyphicon-refresh"></i> Refresh
            </button>
            <button class="btn btn-default" onclick="PipelineView.toggleCompactView()">
                <i class="glyphicon glyphicon-resize-small"></i> Compact View
            </button>
        </div>
    </div>

    <div class="pipeline-board-wrapper">
        <div class="pipeline-board" id="pipeline-board">
            {foreach from=$stages key=stage_key item=stage_name}
                <div class="pipeline-stage" data-stage="{$stage_key}">
                    <div class="stage-header">
                        <h3>{$stage_name}</h3>
                        <div class="stage-stats">
                            <span class="deal-count">{$deals_by_stage.$stage_key|@count}</span>
                            {if $wip_limits.$stage_key}
                                <span class="wip-limit-indicator {if $deals_by_stage.$stage_key|@count >= $wip_limits.$stage_key}over-limit{elseif $deals_by_stage.$stage_key|@count >= $wip_limits.$stage_key * 0.8}near-limit{/if}">
                                    / {$wip_limits.$stage_key}
                                </span>
                            {/if}
                        </div>
                    </div>
                    
                    <div class="stage-body droppable" data-stage="{$stage_key}" data-wip-limit="{$wip_limits.$stage_key|default:999}">
                        {foreach from=$deals_by_stage.$stage_key item=deal}
                            <div class="deal-card draggable {$deal.stage_color_class}" 
                                 data-deal-id="{$deal.id}" 
                                 data-stage="{$stage_key}"
                                 draggable="true">
                                
                                <div class="deal-card-header">
                                    <h4 class="deal-name">
                                        <a href="index.php?module=Opportunities&action=DetailView&record={$deal.id}" 
                                           target="_blank" 
                                           onclick="event.stopPropagation();">
                                            {$deal.name|truncate:50}
                                        </a>
                                    </h4>
                                    <div class="deal-days-indicator" title="Days in current stage">
                                        <i class="glyphicon glyphicon-time"></i> {$deal.days_in_stage}d
                                    </div>
                                </div>
                                
                                <div class="deal-card-body">
                                    {if $deal.account_name}
                                        <div class="deal-account">
                                            <i class="glyphicon glyphicon-briefcase"></i> {$deal.account_name|truncate:30}
                                        </div>
                                    {/if}
                                    
                                    {if $deal.amount}
                                        <div class="deal-amount">
                                            <i class="glyphicon glyphicon-usd"></i> {$deal.amount|number_format:0}
                                        </div>
                                    {/if}
                                    
                                    {if $deal.assigned_user_name}
                                        <div class="deal-assigned">
                                            <i class="glyphicon glyphicon-user"></i> {$deal.assigned_user_name}
                                        </div>
                                    {/if}
                                    
                                    {if $deal.expected_close_date_c}
                                        <div class="deal-close-date">
                                            <i class="glyphicon glyphicon-calendar"></i> {$deal.expected_close_date_c}
                                        </div>
                                    {/if}
                                </div>
                                
                                {if $deal.probability}
                                    <div class="deal-probability">
                                        <div class="probability-bar" style="width: {$deal.probability}%"></div>
                                        <span class="probability-text">{$deal.probability}%</span>
                                    </div>
                                {/if}
                            </div>
                        {/foreach}
                        
                        {if $deals_by_stage.$stage_key|@count == 0}
                            <div class="empty-stage-placeholder">
                                <i class="glyphicon glyphicon-inbox"></i>
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
        <div class="mobile-swipe-hint">
            <i class="glyphicon glyphicon-hand-left"></i> Swipe to see more stages
        </div>
    {/if}
</div>

<!-- Drag ghost preview -->
<div id="drag-ghost" class="deal-card drag-ghost" style="display: none;"></div>

<!-- Loading overlay -->
<div id="pipeline-loading" class="pipeline-loading" style="display: none;">
    <div class="loading-spinner">
        <i class="glyphicon glyphicon-refresh glyphicon-spin"></i>
        <p>Updating pipeline...</p>
    </div>
</div>

<script type="text/javascript">
    // Initialize Pipeline View
    jQuery(document).ready(function() {
        PipelineView.init({
            currentUserId: '{$current_user_id}',
            isMobile: {if $is_mobile}true{else}false{/if},
            updateUrl: 'index.php?module=Deals&action=updatePipelineStage',
            refreshUrl: 'index.php?module=Deals&action=Pipeline&sugar_body_only=1'
        });
    });
</script>