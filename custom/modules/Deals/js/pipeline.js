/**
 * Pipeline View JavaScript
 * Handles drag-and-drop functionality, AJAX updates, and mobile gestures
 */

var PipelineView = {
    config: {
        currentUserId: null,
        isMobile: false,
        updateUrl: '',
        refreshUrl: '',
        compactView: false
    },
    
    draggedElement: null,
    draggedData: null,
    touchStartX: 0,
    touchStartY: 0,
    
    /**
     * Initialize the Pipeline View
     */
    init: function(config) {
        this.config = jQuery.extend(this.config, config);
        
        // Initialize drag and drop
        this.initDragAndDrop();
        
        // Initialize mobile touch events
        if (this.config.isMobile) {
            this.initMobileGestures();
        }
        
        // Initialize other event handlers
        this.initEventHandlers();
        
        // Check for compact view preference
        var compactPref = localStorage.getItem('pipeline_compact_view');
        if (compactPref === 'true') {
            this.setCompactView(true);
        }
    },
    
    /**
     * Initialize drag and drop functionality
     */
    initDragAndDrop: function() {
        var self = this;
        
        // Make cards draggable
        jQuery('.draggable').each(function() {
            this.addEventListener('dragstart', self.handleDragStart.bind(self));
            this.addEventListener('dragend', self.handleDragEnd.bind(self));
        });
        
        // Make stages droppable
        jQuery('.droppable').each(function() {
            this.addEventListener('dragover', self.handleDragOver.bind(self));
            this.addEventListener('drop', self.handleDrop.bind(self));
            this.addEventListener('dragleave', self.handleDragLeave.bind(self));
        });
    },
    
    /**
     * Initialize mobile touch gestures
     */
    initMobileGestures: function() {
        var self = this;
        
        // Touch events for drag simulation
        jQuery('.draggable').on('touchstart', function(e) {
            self.handleTouchStart(e);
        });
        
        jQuery('.draggable').on('touchmove', function(e) {
            self.handleTouchMove(e);
        });
        
        jQuery('.draggable').on('touchend', function(e) {
            self.handleTouchEnd(e);
        });
        
        // Swipe detection for horizontal scrolling
        var board = document.getElementById('pipeline-board');
        var hammer = new Hammer(board);
        
        hammer.on('swipeleft swiperight', function(e) {
            var scrollAmount = 300;
            var currentScroll = jQuery('.pipeline-board-wrapper').scrollLeft();
            
            if (e.type === 'swipeleft') {
                jQuery('.pipeline-board-wrapper').animate({
                    scrollLeft: currentScroll + scrollAmount
                }, 300);
            } else {
                jQuery('.pipeline-board-wrapper').animate({
                    scrollLeft: currentScroll - scrollAmount
                }, 300);
            }
        });
    },
    
    /**
     * Initialize other event handlers
     */
    initEventHandlers: function() {
        // Hide mobile swipe hint after first interaction
        jQuery('.pipeline-board-wrapper').on('scroll', function() {
            jQuery('.mobile-swipe-hint').fadeOut();
        });
    },
    
    /**
     * Handle drag start
     */
    handleDragStart: function(e) {
        this.draggedElement = e.target;
        this.draggedData = {
            dealId: e.target.dataset.dealId,
            sourceStage: e.target.dataset.stage
        };
        
        e.target.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/html', e.target.innerHTML);
        
        // Create drag ghost
        this.createDragGhost(e.target);
    },
    
    /**
     * Handle drag end
     */
    handleDragEnd: function(e) {
        e.target.classList.remove('dragging');
        this.removeDragGhost();
        
        // Remove all drag-over states
        jQuery('.drag-over').removeClass('drag-over');
    },
    
    /**
     * Handle drag over
     */
    handleDragOver: function(e) {
        if (e.preventDefault) {
            e.preventDefault();
        }
        
        e.dataTransfer.dropEffect = 'move';
        
        var dropZone = e.currentTarget;
        var targetStage = dropZone.dataset.stage;
        var wipLimit = parseInt(dropZone.dataset.wipLimit);
        var currentCount = dropZone.querySelectorAll('.deal-card').length;
        
        // Check WIP limit
        if (wipLimit && currentCount >= wipLimit && this.draggedData.sourceStage !== targetStage) {
            dropZone.classList.add('wip-limit-exceeded');
            e.dataTransfer.dropEffect = 'none';
        } else {
            dropZone.classList.add('drag-over');
        }
        
        return false;
    },
    
    /**
     * Handle drag leave
     */
    handleDragLeave: function(e) {
        e.currentTarget.classList.remove('drag-over');
        e.currentTarget.classList.remove('wip-limit-exceeded');
    },
    
    /**
     * Handle drop
     */
    handleDrop: function(e) {
        if (e.stopPropagation) {
            e.stopPropagation();
        }
        
        var dropZone = e.currentTarget;
        var targetStage = dropZone.dataset.stage;
        var wipLimit = parseInt(dropZone.dataset.wipLimit);
        var currentCount = dropZone.querySelectorAll('.deal-card').length;
        
        dropZone.classList.remove('drag-over');
        dropZone.classList.remove('wip-limit-exceeded');
        
        // Check WIP limit
        if (wipLimit && currentCount >= wipLimit && this.draggedData.sourceStage !== targetStage) {
            this.showNotification('WIP limit exceeded for this stage!', 'error');
            return false;
        }
        
        // Don't do anything if dropping in same stage
        if (this.draggedData.sourceStage === targetStage) {
            return false;
        }
        
        // Move the card
        this.moveCard(this.draggedElement, dropZone, targetStage);
        
        return false;
    },
    
    /**
     * Handle touch start (mobile)
     */
    handleTouchStart: function(e) {
        var touch = e.originalEvent.touches[0];
        this.touchStartX = touch.pageX;
        this.touchStartY = touch.pageY;
        
        this.draggedElement = e.currentTarget;
        this.draggedData = {
            dealId: e.currentTarget.dataset.dealId,
            sourceStage: e.currentTarget.dataset.stage
        };
        
        // Add visual feedback
        jQuery(e.currentTarget).addClass('dragging');
    },
    
    /**
     * Handle touch move (mobile)
     */
    handleTouchMove: function(e) {
        e.preventDefault();
        
        var touch = e.originalEvent.touches[0];
        var moveX = touch.pageX - this.touchStartX;
        var moveY = touch.pageY - this.touchStartY;
        
        // Move the element with the touch
        jQuery(this.draggedElement).css({
            transform: 'translate(' + moveX + 'px, ' + moveY + 'px)'
        });
        
        // Find element under touch point
        var elementBelow = document.elementFromPoint(touch.pageX, touch.pageY);
        if (elementBelow) {
            var dropZone = jQuery(elementBelow).closest('.droppable');
            if (dropZone.length) {
                jQuery('.drag-over').removeClass('drag-over');
                dropZone.addClass('drag-over');
            }
        }
    },
    
    /**
     * Handle touch end (mobile)
     */
    handleTouchEnd: function(e) {
        var touch = e.originalEvent.changedTouches[0];
        
        // Reset transform
        jQuery(this.draggedElement).css({
            transform: ''
        }).removeClass('dragging');
        
        // Find drop target
        var elementBelow = document.elementFromPoint(touch.pageX, touch.pageY);
        if (elementBelow) {
            var dropZone = jQuery(elementBelow).closest('.droppable');
            if (dropZone.length) {
                var targetStage = dropZone.data('stage');
                if (targetStage !== this.draggedData.sourceStage) {
                    this.moveCard(this.draggedElement, dropZone[0], targetStage);
                }
            }
        }
        
        jQuery('.drag-over').removeClass('drag-over');
    },
    
    /**
     * Move card to new stage
     */
    moveCard: function(card, dropZone, targetStage) {
        var self = this;
        
        // Show loading
        this.showLoading();
        
        // Remove empty placeholder if exists
        jQuery(dropZone).find('.empty-stage-placeholder').remove();
        
        // Move card visually
        jQuery(dropZone).append(card);
        
        // Update card data
        jQuery(card).attr('data-stage', targetStage);
        
        // Update counts
        this.updateStageCounts();
        
        // Send AJAX request to update backend
        jQuery.ajax({
            url: this.config.updateUrl,
            type: 'POST',
            data: {
                deal_id: this.draggedData.dealId,
                new_stage: targetStage,
                old_stage: this.draggedData.sourceStage
            },
            success: function(response) {
                self.hideLoading();
                
                if (response.success) {
                    self.showNotification('Deal moved successfully', 'success');
                    
                    // Update time in stage
                    if (response.stage_entered_date) {
                        self.updateTimeInStage(card, response.stage_entered_date);
                    }
                } else {
                    // Revert the move
                    self.revertMove(card, self.draggedData.sourceStage);
                    self.showNotification(response.message || 'Failed to move deal', 'error');
                }
            },
            error: function() {
                self.hideLoading();
                self.revertMove(card, self.draggedData.sourceStage);
                self.showNotification('Network error. Please try again.', 'error');
            }
        });
    },
    
    /**
     * Revert card move on error
     */
    revertMove: function(card, originalStage) {
        var originalDropZone = jQuery('.droppable[data-stage="' + originalStage + '"]');
        originalDropZone.append(card);
        jQuery(card).attr('data-stage', originalStage);
        this.updateStageCounts();
    },
    
    /**
     * Update stage counts after move
     */
    updateStageCounts: function() {
        jQuery('.pipeline-stage').each(function() {
            var stage = jQuery(this);
            var stageKey = stage.data('stage');
            var count = stage.find('.deal-card').length;
            var countElement = stage.find('.deal-count');
            var wipIndicator = stage.find('.wip-limit-indicator');
            
            countElement.text(count);
            
            // Update WIP limit indicator
            if (wipIndicator.length) {
                var limit = parseInt(stage.find('.droppable').data('wip-limit'));
                if (limit) {
                    wipIndicator.removeClass('near-limit over-limit');
                    if (count >= limit) {
                        wipIndicator.addClass('over-limit');
                    } else if (count >= limit * 0.8) {
                        wipIndicator.addClass('near-limit');
                    }
                }
            }
            
            // Add empty placeholder if needed
            var stageBody = stage.find('.stage-body');
            if (count === 0 && stageBody.find('.empty-stage-placeholder').length === 0) {
                stageBody.append(
                    '<div class="empty-stage-placeholder">' +
                    '<i class="glyphicon glyphicon-inbox"></i>' +
                    '<p>Drop deals here</p>' +
                    '</div>'
                );
            }
        });
    },
    
    /**
     * Update time in stage for a card
     */
    updateTimeInStage: function(card, stageEnteredDate) {
        // Reset to 0 days and normal color
        jQuery(card).removeClass('stage-orange stage-red').addClass('stage-normal');
        jQuery(card).find('.deal-days-indicator').html('<i class="glyphicon glyphicon-time"></i> 0d');
    },
    
    /**
     * Create drag ghost
     */
    createDragGhost: function(element) {
        var ghost = jQuery('#drag-ghost');
        ghost.html(element.innerHTML);
        ghost.show();
        
        // Position ghost at cursor
        jQuery(document).on('mousemove.dragGhost', function(e) {
            ghost.css({
                left: e.pageX + 10,
                top: e.pageY + 10
            });
        });
    },
    
    /**
     * Remove drag ghost
     */
    removeDragGhost: function() {
        jQuery('#drag-ghost').hide();
        jQuery(document).off('mousemove.dragGhost');
    },
    
    /**
     * Refresh the board
     */
    refreshBoard: function() {
        var self = this;
        this.showLoading();
        
        jQuery.ajax({
            url: this.config.refreshUrl,
            type: 'GET',
            success: function(html) {
                // Replace board content
                jQuery('#pipeline-container').replaceWith(html);
                
                // Reinitialize
                self.init(self.config);
                
                self.hideLoading();
                self.showNotification('Board refreshed', 'success');
            },
            error: function() {
                self.hideLoading();
                self.showNotification('Failed to refresh board', 'error');
            }
        });
    },
    
    /**
     * Toggle compact view
     */
    toggleCompactView: function() {
        this.config.compactView = !this.config.compactView;
        this.setCompactView(this.config.compactView);
        
        // Save preference
        localStorage.setItem('pipeline_compact_view', this.config.compactView);
    },
    
    /**
     * Set compact view state
     */
    setCompactView: function(compact) {
        if (compact) {
            jQuery('#pipeline-container').addClass('compact-view');
        } else {
            jQuery('#pipeline-container').removeClass('compact-view');
        }
    },
    
    /**
     * Show loading overlay
     */
    showLoading: function() {
        jQuery('#pipeline-loading').show();
    },
    
    /**
     * Hide loading overlay
     */
    hideLoading: function() {
        jQuery('#pipeline-loading').hide();
    },
    
    /**
     * Show notification
     */
    showNotification: function(message, type) {
        // Use SuiteCRM's notification system if available
        if (typeof SUGAR !== 'undefined' && SUGAR.App) {
            SUGAR.App.alert.show('pipeline-notification', {
                level: type === 'error' ? 'error' : 'success',
                messages: message,
                autoClose: true
            });
        } else {
            // Fallback to simple alert
            alert(message);
        }
    }
};

// Polyfill for touch devices if Hammer.js is not available
if (typeof Hammer === 'undefined' && 'ontouchstart' in window) {
    // Simple swipe detection
    (function() {
        var startX = 0;
        var startY = 0;
        var dist = 0;
        var threshold = 50; // minimum distance for swipe
        var allowedTime = 300; // maximum time for swipe
        var elapsedTime = 0;
        var startTime = 0;
        
        jQuery('.pipeline-board-wrapper').on('touchstart', function(e) {
            var touchobj = e.originalEvent.changedTouches[0];
            dist = 0;
            startX = touchobj.pageX;
            startY = touchobj.pageY;
            startTime = new Date().getTime();
        });
        
        jQuery('.pipeline-board-wrapper').on('touchend', function(e) {
            var touchobj = e.originalEvent.changedTouches[0];
            dist = touchobj.pageX - startX;
            elapsedTime = new Date().getTime() - startTime;
            
            if (elapsedTime <= allowedTime && Math.abs(dist) >= threshold) {
                var scrollAmount = 300;
                var currentScroll = jQuery('.pipeline-board-wrapper').scrollLeft();
                
                if (dist < 0) {
                    // Swipe left
                    jQuery('.pipeline-board-wrapper').animate({
                        scrollLeft: currentScroll + scrollAmount
                    }, 300);
                } else {
                    // Swipe right
                    jQuery('.pipeline-board-wrapper').animate({
                        scrollLeft: currentScroll - scrollAmount
                    }, 300);
                }
            }
        });
    })();
}