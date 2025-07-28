/**
 * Checklist Manager
 * Provides interactive functionality for the Due Diligence Checklist
 */

(function() {
    'use strict';
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        initializeChecklistInteractions();
    });
    
    /**
     * Initialize all checklist interactions
     */
    window.initializeChecklistInteractions = function() {
        // Refresh button
        $('.checklist-refresh').off('click').on('click', function() {
            const dealId = $(this).data('deal-id');
            refreshChecklist(dealId);
        });
        
        // Expand/Collapse all button
        $('.checklist-toggle').off('click').on('click', function() {
            toggleAllCategories();
        });
        
        // Task edit buttons
        $('.task-edit').off('click').on('click', function() {
            const taskId = $(this).data('task-id');
            editTask(taskId);
        });
        
        // Task delete buttons
        $('.task-delete').off('click').on('click', function() {
            const taskId = $(this).data('task-id');
            deleteTask(taskId);
        });
        
        // Add task buttons
        $('.add-task-btn').off('click').on('click', function() {
            const categoryId = $(this).data('category-id');
            addNewTask(categoryId);
        });
        
        // Add category button
        $('.add-category-btn').off('click').on('click', function() {
            const dealId = $(this).data('deal-id');
            addNewCategory(dealId);
        });
        
        // Task status click handlers
        $('.task-status-icon').off('click').on('click', function() {
            const taskItem = $(this).closest('.task-item');
            const taskId = taskItem.data('task-id');
            toggleTaskStatus(taskId);
        });
    };
    
    /**
     * Toggle category visibility
     */
    window.toggleCategory = function(categoryId) {
        const tasksContainer = $('#category-' + categoryId + '-tasks');
        const toggleIcon = tasksContainer.siblings('.category-header').find('.category-toggle');
        
        if (tasksContainer.is(':visible')) {
            tasksContainer.slideUp();
            toggleIcon.text('▶');
        } else {
            tasksContainer.slideDown();
            toggleIcon.text('▼');
        }
    };
    
    /**
     * Toggle all categories
     */
    function toggleAllCategories() {
        const allExpanded = $('.category-tasks:visible').length === $('.category-tasks').length;
        
        if (allExpanded) {
            $('.category-tasks').slideUp();
            $('.category-toggle').text('▶');
        } else {
            $('.category-tasks').slideDown();
            $('.category-toggle').text('▼');
        }
    }
    
    /**
     * Refresh checklist data
     */
    function refreshChecklist(dealId) {
        showLoading('Refreshing checklist...');
        
        $.ajax({
            url: 'index.php?module=Deals&action=checklistApi',
            method: 'POST',
            data: {
                deal_id: dealId,
                checklist_action: 'load'
            },
            success: function(response) {
                hideLoading();
                if (response.success) {
                    // Reload the page to refresh the view
                    location.reload();
                } else {
                    showError('Failed to refresh checklist: ' + (response.message || 'Unknown error'));
                }
            },
            error: function() {
                hideLoading();
                showError('Network error while refreshing checklist');
            }
        });
    }
    
    /**
     * Edit a task
     */
    function editTask(taskId) {
        const taskItem = $('.task-item[data-task-id="' + taskId + '"]');
        const taskName = taskItem.find('strong').text().trim();
        const assignedTo = taskItem.find('em').text().trim();
        
        // Create edit modal
        const modalHtml = `
            <div class="modal fade" id="editTaskModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Task</h5>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                        </div>
                        <div class="modal-body">
                            <form id="editTaskForm">
                                <div class="form-group">
                                    <label for="taskName">Task Name</label>
                                    <input type="text" class="form-control" id="taskName" value="${taskName}" required>
                                </div>
                                <div class="form-group">
                                    <label for="assignedTo">Assigned To</label>
                                    <input type="text" class="form-control" id="assignedTo" value="${assignedTo}" required>
                                </div>
                                <div class="form-group">
                                    <label for="taskStatus">Status</label>
                                    <select class="form-control" id="taskStatus">
                                        <option value="pending">Pending</option>
                                        <option value="in_progress">In Progress</option>
                                        <option value="completed">Completed</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="taskPriority">Priority</label>
                                    <select class="form-control" id="taskPriority">
                                        <option value="low">Low</option>
                                        <option value="medium">Medium</option>
                                        <option value="high">High</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="dueDate">Due Date</label>
                                    <input type="date" class="form-control" id="dueDate">
                                </div>
                                <div class="form-group">
                                    <label for="taskDescription">Description</label>
                                    <textarea class="form-control" id="taskDescription" rows="3"></textarea>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" onclick="saveTask('${taskId}')">Save Changes</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(modalHtml);
        $('#editTaskModal').modal('show');
        
        // Clean up modal when closed
        $('#editTaskModal').on('hidden.bs.modal', function() {
            $(this).remove();
        });
    }
    
    /**
     * Save task changes
     */
    window.saveTask = function(taskId) {
        const formData = {
            task_id: taskId,
            name: $('#taskName').val(),
            assigned_to: $('#assignedTo').val(),
            status: $('#taskStatus').val(),
            priority: $('#taskPriority').val(),
            due_date: $('#dueDate').val(),
            description: $('#taskDescription').val()
        };
        
        showLoading('Saving task...');
        
        $.ajax({
            url: 'index.php?module=Deals&action=checklistApi',
            method: 'POST',
            data: {
                checklist_action: 'update_task',
                task_data: JSON.stringify(formData)
            },
            success: function(response) {
                hideLoading();
                if (response.success) {
                    $('#editTaskModal').modal('hide');
                    showSuccess('Task updated successfully');
                    // Refresh the checklist display
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showError('Failed to update task: ' + (response.message || 'Unknown error'));
                }
            },
            error: function() {
                hideLoading();
                showError('Network error while saving task');
            }
        });
    };
    
    /**
     * Delete a task
     */
    function deleteTask(taskId) {
        if (!confirm('Are you sure you want to delete this task?')) {
            return;
        }
        
        showLoading('Deleting task...');
        
        $.ajax({
            url: 'index.php?module=Deals&action=checklistApi',
            method: 'POST',
            data: {
                checklist_action: 'delete_task',
                task_id: taskId
            },
            success: function(response) {
                hideLoading();
                if (response.success) {
                    $('.task-item[data-task-id="' + taskId + '"]').fadeOut(function() {
                        $(this).remove();
                    });
                    showSuccess('Task deleted successfully');
                } else {
                    showError('Failed to delete task: ' + (response.message || 'Unknown error'));
                }
            },
            error: function() {
                hideLoading();
                showError('Network error while deleting task');
            }
        });
    }
    
    /**
     * Add new task
     */
    function addNewTask(categoryId) {
        const modalHtml = `
            <div class="modal fade" id="addTaskModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Add New Task</h5>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                        </div>
                        <div class="modal-body">
                            <form id="addTaskForm">
                                <div class="form-group">
                                    <label for="newTaskName">Task Name</label>
                                    <input type="text" class="form-control" id="newTaskName" required>
                                </div>
                                <div class="form-group">
                                    <label for="newAssignedTo">Assigned To</label>
                                    <input type="text" class="form-control" id="newAssignedTo" required>
                                </div>
                                <div class="form-group">
                                    <label for="newTaskStatus">Status</label>
                                    <select class="form-control" id="newTaskStatus">
                                        <option value="pending">Pending</option>
                                        <option value="in_progress">In Progress</option>
                                        <option value="completed">Completed</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="newTaskPriority">Priority</label>
                                    <select class="form-control" id="newTaskPriority">
                                        <option value="medium">Medium</option>
                                        <option value="low">Low</option>
                                        <option value="high">High</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="newDueDate">Due Date</label>
                                    <input type="date" class="form-control" id="newDueDate">
                                </div>
                                <div class="form-group">
                                    <label for="newTaskDescription">Description</label>
                                    <textarea class="form-control" id="newTaskDescription" rows="3"></textarea>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-success" onclick="createTask('${categoryId}')">Create Task</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(modalHtml);
        $('#addTaskModal').modal('show');
        
        // Clean up modal when closed
        $('#addTaskModal').on('hidden.bs.modal', function() {
            $(this).remove();
        });
    }
    
    /**
     * Create new task
     */
    window.createTask = function(categoryId) {
        const formData = {
            category_id: categoryId,
            name: $('#newTaskName').val(),
            assigned_to: $('#newAssignedTo').val(),
            status: $('#newTaskStatus').val(),
            priority: $('#newTaskPriority').val(),
            due_date: $('#newDueDate').val(),
            description: $('#newTaskDescription').val()
        };
        
        if (!formData.name || !formData.assigned_to) {
            showError('Task name and assigned to are required');
            return;
        }
        
        showLoading('Creating task...');
        
        $.ajax({
            url: 'index.php?module=Deals&action=checklistApi',
            method: 'POST',
            data: {
                checklist_action: 'create_task',
                task_data: JSON.stringify(formData)
            },
            success: function(response) {
                hideLoading();
                if (response.success) {
                    $('#addTaskModal').modal('hide');
                    showSuccess('Task created successfully');
                    // Refresh the checklist display
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showError('Failed to create task: ' + (response.message || 'Unknown error'));
                }
            },
            error: function() {
                hideLoading();
                showError('Network error while creating task');
            }
        });
    };
    
    /**
     * Add new category
     */
    function addNewCategory(dealId) {
        const modalHtml = `
            <div class="modal fade" id="addCategoryModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Add New Category</h5>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                        </div>
                        <div class="modal-body">
                            <form id="addCategoryForm">
                                <div class="form-group">
                                    <label for="newCategoryName">Category Name</label>
                                    <input type="text" class="form-control" id="newCategoryName" required>
                                </div>
                                <div class="form-group">
                                    <label for="newCategoryDescription">Description</label>
                                    <textarea class="form-control" id="newCategoryDescription" rows="3"></textarea>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-success" onclick="createCategory('${dealId}')">Create Category</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(modalHtml);
        $('#addCategoryModal').modal('show');
        
        // Clean up modal when closed
        $('#addCategoryModal').on('hidden.bs.modal', function() {
            $(this).remove();
        });
    }
    
    /**
     * Create new category
     */
    window.createCategory = function(dealId) {
        const formData = {
            deal_id: dealId,
            name: $('#newCategoryName').val(),
            description: $('#newCategoryDescription').val()
        };
        
        if (!formData.name) {
            showError('Category name is required');
            return;
        }
        
        showLoading('Creating category...');
        
        $.ajax({
            url: 'index.php?module=Deals&action=checklistApi',
            method: 'POST',
            data: {
                checklist_action: 'create_category',
                category_data: JSON.stringify(formData)
            },
            success: function(response) {
                hideLoading();
                if (response.success) {
                    $('#addCategoryModal').modal('hide');
                    showSuccess('Category created successfully');
                    // Refresh the checklist display
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showError('Failed to create category: ' + (response.message || 'Unknown error'));
                }
            },
            error: function() {
                hideLoading();
                showError('Network error while creating category');
            }
        });
    };
    
    /**
     * Toggle task status
     */
    function toggleTaskStatus(taskId) {
        const taskItem = $('.task-item[data-task-id="' + taskId + '"]');
        const currentClass = taskItem.attr('class');
        
        let newStatus = 'pending';
        if (currentClass.includes('task-item-pending')) {
            newStatus = 'in_progress';
        } else if (currentClass.includes('task-item-in_progress')) {
            newStatus = 'completed';
        } else if (currentClass.includes('task-item-completed')) {
            newStatus = 'pending';
        }
        
        showLoading('Updating task status...');
        
        $.ajax({
            url: 'index.php?module=Deals&action=checklistApi',
            method: 'POST',
            data: {
                checklist_action: 'update_task_status',
                task_id: taskId,
                status: newStatus
            },
            success: function(response) {
                hideLoading();
                if (response.success) {
                    // Update the task display
                    taskItem.removeClass('task-item-pending task-item-in_progress task-item-completed');
                    taskItem.addClass('task-item-' + newStatus);
                    
                    // Update the status icon
                    const newIcon = getStatusIcon(newStatus);
                    taskItem.find('.task-status-icon').text(newIcon);
                    
                    showSuccess('Task status updated');
                } else {
                    showError('Failed to update task status: ' + (response.message || 'Unknown error'));
                }
            },
            error: function() {
                hideLoading();
                showError('Network error while updating task status');
            }
        });
    }
    
    /**
     * Get status icon for task status
     */
    function getStatusIcon(status) {
        switch (status) {
            case 'completed': return '✓';
            case 'in_progress': return '🔄';
            case 'pending': return '⏳';
            default: return '○';
        }
    }
    
    /**
     * Show loading indicator
     */
    function showLoading(message) {
        if (!$('#checklistLoadingModal').length) {
            const loadingHtml = `
                <div class="modal fade" id="checklistLoadingModal" tabindex="-1" data-backdrop="static">
                    <div class="modal-dialog modal-sm">
                        <div class="modal-content">
                            <div class="modal-body text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="sr-only">Loading...</span>
                                </div>
                                <p class="mt-2" id="loadingMessage">${message}</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $('body').append(loadingHtml);
        } else {
            $('#loadingMessage').text(message);
        }
        $('#checklistLoadingModal').modal('show');
    }
    
    /**
     * Hide loading indicator
     */
    function hideLoading() {
        $('#checklistLoadingModal').modal('hide');
    }
    
    /**
     * Show success message
     */
    function showSuccess(message) {
        showAlert(message, 'success');
    }
    
    /**
     * Show error message
     */
    function showError(message) {
        showAlert(message, 'danger');
    }
    
    /**
     * Show alert message
     */
    function showAlert(message, type) {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show checklist-alert" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                ${message}
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            </div>
        `;
        
        $('body').append(alertHtml);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            $('.checklist-alert').alert('close');
        }, 5000);
    }
    
})();