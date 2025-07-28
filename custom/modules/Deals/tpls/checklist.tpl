{*
 * Checklist Template for Deal Detail View
 * Displays checklist items and provides CRUD functionality
 *}

<div id="checklist-section" class="detail-view-row-item detail-view-bordered" style="margin-top: 20px;">
    <div class="detail-view-field" style="width: 100%;">
        <h3 style="margin-bottom: 15px; color: #534d64;">
            <span class="glyphicon glyphicon-list-alt"></span> Due Diligence Checklist
            <div class="pull-right">
                <button type="button" class="btn btn-sm btn-primary checklist-refresh" data-deal-id="{$fields.id.value}" title="Refresh checklist">
                    <span class="glyphicon glyphicon-refresh"></span> Refresh
                </button>
                <button type="button" class="btn btn-sm btn-default checklist-toggle" title="Expand/Collapse all">
                    <span class="glyphicon glyphicon-resize-vertical"></span>
                </button>
                <button type="button" class="btn btn-sm btn-success add-category-btn" data-deal-id="{$fields.id.value}" title="Add new category">
                    <span class="glyphicon glyphicon-plus"></span> Add Category
                </button>
            </div>
        </h3>
        
        <div id="checklist-container" class="checklist-content">
            <div class="checklist-loading text-center" style="padding: 40px;">
                <div class="spinner-border text-primary" role="status">
                    <span class="sr-only">Loading checklist...</span>
                </div>
                <p class="text-muted mt-2">Loading checklist data...</p>
            </div>
        </div>
    </div>
</div>

<style>
.checklist-content {
    min-height: 200px;
    background: #f9f9f9;
    border-radius: 4px;
    padding: 15px;
}

.checklist-category {
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-bottom: 15px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.category-header {
    padding: 12px 15px;
    background: #f5f5f5;
    border-bottom: 1px solid #ddd;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.category-header:hover {
    background: #e9e9e9;
}

.category-title {
    font-weight: bold;
    font-size: 16px;
    color: #333;
}

.category-progress {
    display: flex;
    align-items: center;
    gap: 10px;
}

.progress {
    width: 150px;
    height: 20px;
    margin-bottom: 0;
}

.category-toggle {
    font-size: 12px;
    color: #666;
}

.category-tasks {
    padding: 10px;
}

.task-item {
    padding: 10px;
    border-bottom: 1px solid #eee;
    display: flex;
    align-items: center;
    transition: background-color 0.2s;
}

.task-item:hover {
    background: #f9f9f9;
}

.task-item:last-child {
    border-bottom: none;
}

.task-status-icon {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 10px;
    cursor: pointer;
    font-size: 14px;
}

.task-item-pending .task-status-icon {
    background: #f0f0f0;
    color: #999;
}

.task-item-in_progress .task-status-icon {
    background: #ffc107;
    color: white;
}

.task-item-completed .task-status-icon {
    background: #28a745;
    color: white;
}

.task-content {
    flex: 1;
}

.task-name {
    font-weight: 500;
    color: #333;
}

.task-details {
    font-size: 12px;
    color: #666;
    margin-top: 3px;
}

.task-actions {
    display: flex;
    gap: 5px;
    opacity: 0;
    transition: opacity 0.2s;
}

.task-item:hover .task-actions {
    opacity: 1;
}

.task-actions button {
    padding: 2px 8px;
    font-size: 12px;
}

.add-task-btn {
    margin: 10px 0;
    font-size: 13px;
}

.checklist-stats {
    background: #e9ecef;
    padding: 10px 15px;
    border-radius: 4px;
    margin-bottom: 15px;
    text-align: center;
}

.checklist-stats strong {
    color: #28a745;
}

/* Status badges */
.badge-pending {
    background-color: #6c757d;
}

.badge-in_progress {
    background-color: #ffc107;
}

.badge-completed {
    background-color: #28a745;
}

.badge-high {
    background-color: #dc3545;
}

.badge-medium {
    background-color: #ffc107;
}

.badge-low {
    background-color: #6c757d;
}
</style>

<script type="text/javascript">
// Initialize checklist when document is ready
$(document).ready(function() {
    var dealId = '{$fields.id.value}';
    if (dealId) {
        loadChecklistData(dealId);
    }
});

// Load checklist data via AJAX
function loadChecklistData(dealId) {
    $.ajax({
        url: 'index.php?module=Deals&action=checklistApi',
        method: 'POST',
        data: {
            deal_id: dealId,
            checklist_action: 'load'
        },
        success: function(response) {
            if (response.success && response.data) {
                renderChecklist(response.data.checklist);
                // Initialize interactions after rendering
                if (typeof initializeChecklistInteractions === 'function') {
                    initializeChecklistInteractions();
                }
            } else {
                showChecklistError(response.error || 'Failed to load checklist');
            }
        },
        error: function() {
            showChecklistError('Network error while loading checklist');
        }
    });
}

// Render checklist HTML
function renderChecklist(checklist) {
    var html = '';
    
    // Stats summary
    html += '<div class="checklist-stats">';
    html += '<strong>' + checklist.completed_tasks + '</strong> of <strong>' + checklist.total_tasks + '</strong> tasks completed ';
    html += '(' + checklist.progress + '%)';
    html += '</div>';
    
    // Categories and tasks
    if (checklist.categories && checklist.categories.length > 0) {
        checklist.categories.forEach(function(category) {
            html += renderCategory(category, checklist.deal_id);
        });
    } else if (checklist.tasks && checklist.tasks.length > 0) {
        // Render tasks without categories
        html += '<div class="checklist-category">';
        html += '<div class="category-tasks" style="display: block;">';
        checklist.tasks.forEach(function(task) {
            html += renderTask(task);
        });
        html += '<button type="button" class="btn btn-sm btn-default add-task-btn" data-category-id="general">';
        html += '<span class="glyphicon glyphicon-plus"></span> Add Task';
        html += '</button>';
        html += '</div>';
        html += '</div>';
    } else {
        html += '<div class="alert alert-info">';
        html += 'No checklist items found. Click "Add Category" to get started.';
        html += '</div>';
    }
    
    $('#checklist-container').html(html);
}

// Render a category
function renderCategory(category, dealId) {
    var html = '';
    var isExpanded = category.status !== 'completed';
    
    html += '<div class="checklist-category" id="category-' + category.id + '">';
    html += '<div class="category-header" onclick="toggleCategory(\'' + category.id + '\')">';
    html += '<div>';
    html += '<span class="category-toggle">' + (isExpanded ? '▼' : '▶') + '</span> ';
    html += '<span class="category-title">' + category.name + '</span>';
    html += '</div>';
    html += '<div class="category-progress">';
    html += '<span class="badge badge-' + category.status + '">' + category.status + '</span>';
    html += '<div class="progress">';
    html += '<div class="progress-bar" style="width: ' + category.progress + '%"></div>';
    html += '</div>';
    html += '<span>' + category.progress + '%</span>';
    html += '</div>';
    html += '</div>';
    
    html += '<div class="category-tasks" id="category-' + category.id + '-tasks" style="display: ' + (isExpanded ? 'block' : 'none') + ';">';
    
    if (category.items && category.items.length > 0) {
        category.items.forEach(function(task) {
            html += renderTask(task);
        });
    }
    
    html += '<button type="button" class="btn btn-sm btn-default add-task-btn" data-category-id="' + category.id + '">';
    html += '<span class="glyphicon glyphicon-plus"></span> Add Task';
    html += '</button>';
    html += '</div>';
    html += '</div>';
    
    return html;
}

// Render a task
function renderTask(task) {
    var statusIcon = getStatusIcon(task.status);
    var priorityBadge = task.priority ? '<span class="badge badge-' + task.priority + '">' + task.priority + '</span>' : '';
    
    var html = '';
    html += '<div class="task-item task-item-' + task.status + '" data-task-id="' + task.id + '">';
    html += '<div class="task-status-icon" title="Click to change status">' + statusIcon + '</div>';
    html += '<div class="task-content">';
    html += '<div class="task-name">';
    html += '<strong>' + task.name + '</strong> ' + priorityBadge;
    html += '</div>';
    html += '<div class="task-details">';
    if (task.assigned_to) {
        html += '<em>' + task.assigned_to + '</em>';
    }
    if (task.due_date) {
        html += ' • Due: ' + formatDate(task.due_date);
    }
    if (task.description) {
        html += ' • ' + task.description;
    }
    html += '</div>';
    html += '</div>';
    html += '<div class="task-actions">';
    html += '<button type="button" class="btn btn-xs btn-default task-edit" data-task-id="' + task.id + '" title="Edit task">';
    html += '<span class="glyphicon glyphicon-pencil"></span>';
    html += '</button>';
    html += '<button type="button" class="btn btn-xs btn-danger task-delete" data-task-id="' + task.id + '" title="Delete task">';
    html += '<span class="glyphicon glyphicon-trash"></span>';
    html += '</button>';
    html += '</div>';
    html += '</div>';
    
    return html;
}

// Get status icon
function getStatusIcon(status) {
    switch (status) {
        case 'completed': return '✓';
        case 'in_progress': return '🔄';
        case 'pending': return '⏳';
        default: return '○';
    }
}

// Format date
function formatDate(dateStr) {
    if (!dateStr) return '';
    var date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

// Show error message
function showChecklistError(message) {
    var html = '<div class="alert alert-danger">';
    html += '<strong>Error:</strong> ' + message;
    html += '</div>';
    $('#checklist-container').html(html);
}

// Toggle category visibility (global function for onclick)
window.toggleCategory = function(categoryId) {
    // This will be handled by checklist-manager.js
    if (typeof window.toggleCategory === 'function') {
        window.toggleCategory(categoryId);
    }
};
</script>