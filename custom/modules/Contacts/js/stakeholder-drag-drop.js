/**
 * Stakeholder Drag and Drop Functionality
 * Handles role assignment and reorganization via drag and drop
 */

(function($) {
    'use strict';

    window.StakeholderDragDrop = {
        
        draggedElement: null,
        draggedData: null,
        
        // Initialize drag and drop
        init: function() {
            this.setupDraggableCards();
            this.setupDropZones();
            this.bindEvents();
        },

        // Make stakeholder cards draggable
        setupDraggableCards: function() {
            $('.stakeholder-card').each(function() {
                const $card = $(this);
                
                // Make card draggable
                $card.attr('draggable', true);
                
                // Drag start
                $card.on('dragstart', function(e) {
                    StakeholderDragDrop.handleDragStart(e, $(this));
                });
                
                // Drag end
                $card.on('dragend', function(e) {
                    StakeholderDragDrop.handleDragEnd(e, $(this));
                });
            });
        },

        // Setup drop zones for role assignment
        setupDropZones: function() {
            $('.role-drop-zone').each(function() {
                const $zone = $(this);
                
                // Drag over
                $zone.on('dragover', function(e) {
                    e.preventDefault();
                    StakeholderDragDrop.handleDragOver(e, $(this));
                });
                
                // Drag enter
                $zone.on('dragenter', function(e) {
                    e.preventDefault();
                    $(this).addClass('drag-over');
                });
                
                // Drag leave
                $zone.on('dragleave', function(e) {
                    if (e.target === this) {
                        $(this).removeClass('drag-over');
                    }
                });
                
                // Drop
                $zone.on('drop', function(e) {
                    e.preventDefault();
                    StakeholderDragDrop.handleDrop(e, $(this));
                });
            });
        },

        // Handle drag start
        handleDragStart: function(e, $element) {
            this.draggedElement = $element;
            this.draggedData = {
                contactId: $element.data('contact-id'),
                contactName: $element.find('.stakeholder-name').text(),
                currentRole: $element.data('role')
            };
            
            // Add dragging class
            $element.addClass('dragging');
            
            // Set drag effect
            e.originalEvent.dataTransfer.effectAllowed = 'move';
            e.originalEvent.dataTransfer.setData('text/html', $element.html());
            
            // Create custom drag image
            const dragImage = this.createDragImage($element);
            e.originalEvent.dataTransfer.setDragImage(dragImage, 50, 50);
        },

        // Handle drag end
        handleDragEnd: function(e, $element) {
            $element.removeClass('dragging');
            $('.role-drop-zone').removeClass('drag-over');
            
            this.draggedElement = null;
            this.draggedData = null;
        },

        // Handle drag over
        handleDragOver: function(e, $zone) {
            if (e.preventDefault) {
                e.preventDefault();
            }
            
            e.originalEvent.dataTransfer.dropEffect = 'move';
            return false;
        },

        // Handle drop
        handleDrop: function(e, $zone) {
            if (e.stopPropagation) {
                e.stopPropagation();
            }
            
            $zone.removeClass('drag-over');
            
            if (this.draggedData) {
                const newRole = $zone.data('role');
                
                // Check if role changed
                if (newRole !== this.draggedData.currentRole) {
                    this.assignRole(this.draggedData.contactId, newRole, $zone);
                }
            }
            
            return false;
        },

        // Assign role to stakeholder
        assignRole: function(contactId, role, $dropZone) {
            const self = this;
            
            // Show loading state
            this.showLoading($dropZone);
            
            // Make AJAX call to assign role
            $.ajax({
                url: 'index.php?module=Contacts&action=AssignStakeholderRole',
                method: 'POST',
                data: {
                    contact_id: contactId,
                    role: role,
                    deal_id: this.getCurrentDealId()
                },
                success: function(response) {
                    if (response.success) {
                        self.updateUI(contactId, role, $dropZone);
                        self.showNotification('Role assigned successfully', 'success');
                    } else {
                        self.showNotification('Failed to assign role', 'error');
                    }
                },
                error: function() {
                    self.showNotification('Error assigning role', 'error');
                },
                complete: function() {
                    self.hideLoading($dropZone);
                }
            });
        },

        // Update UI after role assignment
        updateUI: function(contactId, role, $dropZone) {
            // Update card
            const $card = $('.stakeholder-card[data-contact-id="' + contactId + '"]');
            $card.data('role', role);
            
            // Update role badge
            const roleBadge = StakeholderBadges.generateRoleBadge(role);
            $card.find('.stakeholder-role').replaceWith(roleBadge);
            
            // Add mini badge to drop zone
            this.addMiniBadge(contactId, this.draggedData.contactName, $dropZone);
            
            // Update count
            this.updateDropZoneCount($dropZone);
            
            // Trigger custom event
            $(document).trigger('stakeholder:role-changed', {
                contactId: contactId,
                role: role
            });
        },

        // Add mini badge to drop zone
        addMiniBadge: function(contactId, contactName, $dropZone) {
            const badgeHtml = `
                <span class="stakeholder-mini-badge" data-contact-id="${contactId}">
                    ${contactName}
                    <span class="remove" data-contact-id="${contactId}">&times;</span>
                </span>
            `;
            
            $dropZone.find('.role-drop-zone-content').append(badgeHtml);
        },

        // Update drop zone count
        updateDropZoneCount: function($dropZone) {
            const count = $dropZone.find('.stakeholder-mini-badge').length;
            $dropZone.find('.role-drop-zone-count').text(count + ' contacts');
        },

        // Create custom drag image
        createDragImage: function($element) {
            const dragImage = document.createElement('div');
            dragImage.className = 'drag-image';
            dragImage.style.cssText = 'position: absolute; top: -1000px; padding: 10px; background: white; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.15);';
            
            const name = $element.find('.stakeholder-name').text();
            dragImage.innerHTML = '<strong>' + name + '</strong>';
            
            document.body.appendChild(dragImage);
            
            setTimeout(function() {
                document.body.removeChild(dragImage);
            }, 0);
            
            return dragImage;
        },

        // Bind additional events
        bindEvents: function() {
            // Remove stakeholder from role
            $(document).on('click', '.stakeholder-mini-badge .remove', function(e) {
                e.stopPropagation();
                const contactId = $(this).data('contact-id');
                const $badge = $(this).closest('.stakeholder-mini-badge');
                const $dropZone = $(this).closest('.role-drop-zone');
                
                StakeholderDragDrop.removeRole(contactId, $badge, $dropZone);
            });

            // Sortable within grid
            this.enableGridSorting();
        },

        // Remove role from stakeholder
        removeRole: function(contactId, $badge, $dropZone) {
            const self = this;
            
            $.ajax({
                url: 'index.php?module=Contacts&action=RemoveStakeholderRole',
                method: 'POST',
                data: {
                    contact_id: contactId,
                    deal_id: this.getCurrentDealId()
                },
                success: function(response) {
                    if (response.success) {
                        // Remove badge
                        $badge.fadeOut(200, function() {
                            $(this).remove();
                            self.updateDropZoneCount($dropZone);
                        });
                        
                        // Update card
                        const $card = $('.stakeholder-card[data-contact-id="' + contactId + '"]');
                        $card.data('role', '');
                        $card.find('.stakeholder-role').remove();
                        
                        self.showNotification('Role removed', 'success');
                    }
                }
            });
        },

        // Enable sorting within the grid
        enableGridSorting: function() {
            const self = this;
            let placeholder = null;
            
            $('.stakeholder-grid').on('dragover', function(e) {
                e.preventDefault();
                
                if (!self.draggedElement || !self.draggedElement.hasClass('stakeholder-card')) {
                    return;
                }
                
                const afterElement = self.getDragAfterElement($(this), e.clientY);
                
                if (!placeholder) {
                    placeholder = $('<div class="stakeholder-card-placeholder"></div>');
                    placeholder.css({
                        height: self.draggedElement.outerHeight(),
                        background: '#e5e7eb',
                        border: '2px dashed #9ca3af',
                        borderRadius: '8px'
                    });
                }
                
                if (afterElement == null) {
                    $(this).append(placeholder);
                } else {
                    $(afterElement).before(placeholder);
                }
            });
            
            $('.stakeholder-grid').on('drop', function(e) {
                e.preventDefault();
                
                if (!self.draggedElement || !self.draggedElement.hasClass('stakeholder-card')) {
                    return;
                }
                
                if (placeholder) {
                    placeholder.replaceWith(self.draggedElement);
                    placeholder = null;
                }
                
                // Save new order
                self.saveGridOrder();
            });
        },

        // Get element after which to insert
        getDragAfterElement: function($container, y) {
            const draggableElements = $container.find('.stakeholder-card:not(.dragging)');
            
            let closestElement = null;
            let closestOffset = Number.NEGATIVE_INFINITY;
            
            draggableElements.each(function() {
                const box = this.getBoundingClientRect();
                const offset = y - box.top - box.height / 2;
                
                if (offset < 0 && offset > closestOffset) {
                    closestOffset = offset;
                    closestElement = this;
                }
            });
            
            return closestElement;
        },

        // Save grid order
        saveGridOrder: function() {
            const order = [];
            $('.stakeholder-card').each(function(index) {
                order.push({
                    contactId: $(this).data('contact-id'),
                    order: index
                });
            });
            
            $.ajax({
                url: 'index.php?module=Contacts&action=SaveStakeholderOrder',
                method: 'POST',
                data: {
                    order: order,
                    deal_id: this.getCurrentDealId()
                }
            });
        },

        // Get current deal ID
        getCurrentDealId: function() {
            // This would get the current deal ID from the page context
            return $('[name="record"]').val() || $('#deal_id').val() || '';
        },

        // Show loading state
        showLoading: function($element) {
            $element.css('opacity', '0.6');
            $element.append('<div class="loading-overlay"><div class="spinner"></div></div>');
        },

        // Hide loading state
        hideLoading: function($element) {
            $element.css('opacity', '1');
            $element.find('.loading-overlay').remove();
        },

        // Show notification
        showNotification: function(message, type) {
            const notificationHtml = `
                <div class="stakeholder-notification ${type}">
                    <i class="fa fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                    ${message}
                </div>
            `;
            
            const $notification = $(notificationHtml);
            $('body').append($notification);
            
            // Animate in
            setTimeout(function() {
                $notification.addClass('show');
            }, 10);
            
            // Remove after delay
            setTimeout(function() {
                $notification.removeClass('show');
                setTimeout(function() {
                    $notification.remove();
                }, 300);
            }, 3000);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        if ($('.stakeholder-grid, .role-drop-zone').length) {
            StakeholderDragDrop.init();
        }
    });

})(jQuery);