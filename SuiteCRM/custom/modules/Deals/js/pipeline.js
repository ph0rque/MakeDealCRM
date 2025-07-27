/**
 * Pipeline View JavaScript
 * Handles drag-and-drop functionality, AJAX updates, and mobile gestures
 */

var PipelineView = {
    config: {
        currentUserId: null,
        isMobile: false,
        isTablet: false,
        updateUrl: '',
        refreshUrl: '',
        compactView: false,
        focusFilterActive: false,
        touchScrollThreshold: 10, // pixels before considering it a drag vs scroll
        touchHoldDuration: 150, // ms to hold before drag starts
        touchFeedbackDelay: 100, // ms before showing visual feedback
        viewport: {
            width: window.innerWidth,
            height: window.innerHeight,
            orientation: window.innerWidth > window.innerHeight ? 'landscape' : 'portrait'
        }
    },
    
    draggedElement: null,
    draggedData: null,
    touchStartX: 0,
    touchStartY: 0,
    touchStartTime: 0,
    touchHoldTimer: null,
    isDragging: false,
    touchIdentifier: null,
    scrollStartY: 0,
    originalTouchTarget: null,
    dragClone: null,
    lastDropTarget: null,
    
    /**
     * Initialize the Pipeline View
     */
    init: function(config) {
        this.config = jQuery.extend(this.config, config);
        
        // Detect viewport and device type
        this.detectViewport();
        
        // Initialize responsive features
        this.initResponsiveFeatures();
        
        // Initialize drag and drop
        this.initDragAndDrop();
        
        // Initialize mobile/tablet touch events
        if (this.config.isMobile || this.config.isTablet) {
            this.initMobileGestures();
        }
        
        // Initialize other event handlers
        this.initEventHandlers();
        
        // Check for compact view preference
        var compactPref = localStorage.getItem('pipeline_compact_view');
        if (compactPref === 'true') {
            this.config.compactView = true;
            this.setCompactView(true);
        }
        
        // Check for focus filter preference
        var focusPref = localStorage.getItem('pipeline_focus_filter');
        if (focusPref === 'true') {
            this.config.focusFilterActive = true;
            this.applyFocusFilter(true);
        }
        
        // Initialize responsive layout
        this.updateResponsiveLayout();
    },
    
    /**
     * Detect viewport size and device type
     */
    detectViewport: function() {
        var viewport = this.config.viewport;
        viewport.width = window.innerWidth;
        viewport.height = window.innerHeight;
        viewport.orientation = viewport.width > viewport.height ? 'landscape' : 'portrait';
        
        // Device detection
        this.config.isMobile = viewport.width <= 768;
        this.config.isTablet = viewport.width > 768 && viewport.width <= 1024;
        this.config.isDesktop = viewport.width > 1024;
        
        // Touch capability detection
        this.config.hasTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
        
        // Add CSS classes to body for styling
        jQuery('body')
            .toggleClass('mobile-device', this.config.isMobile)
            .toggleClass('tablet-device', this.config.isTablet)
            .toggleClass('desktop-device', this.config.isDesktop)
            .toggleClass('touch-device', this.config.hasTouch);
    },
    
    /**
     * Initialize responsive features
     */
    initResponsiveFeatures: function() {
        var self = this;
        
        // Handle window resize
        jQuery(window).on('resize orientationchange', function() {
            clearTimeout(self.resizeTimer);
            self.resizeTimer = setTimeout(function() {
                self.detectViewport();
                self.updateResponsiveLayout();
            }, 150);
        });
        
        // Handle viewport meta tag for mobile
        if (this.config.isMobile || this.config.isTablet) {
            this.updateViewportMeta();
        }
    },
    
    /**
     * Update viewport meta tag for better mobile experience
     */
    updateViewportMeta: function() {
        var viewport = document.querySelector('meta[name="viewport"]');
        if (!viewport) {
            viewport = document.createElement('meta');
            viewport.name = 'viewport';
            document.head.appendChild(viewport);
        }
        
        // Responsive viewport settings
        viewport.content = 'width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover';
    },
    
    /**
     * Update responsive layout based on current viewport
     */
    updateResponsiveLayout: function() {
        var container = jQuery('#pipeline-container');
        var board = jQuery('.pipeline-board');
        var stages = jQuery('.pipeline-stage');
        var viewport = this.config.viewport;
        
        // Remove existing responsive classes
        container.removeClass('mobile-layout tablet-layout desktop-layout');
        
        // Apply responsive classes
        if (this.config.isMobile) {
            container.addClass('mobile-layout');
            this.applyMobileLayout();
        } else if (this.config.isTablet) {
            container.addClass('tablet-layout');
            this.applyTabletLayout();
        } else {
            container.addClass('desktop-layout');
            this.applyDesktopLayout();
        }
        
        // Update stage counts for current layout
        this.updateStageCounts();
    },
    
    /**
     * Apply mobile-specific layout optimizations
     */
    applyMobileLayout: function() {
        var stages = jQuery('.pipeline-stage');
        var viewport = this.config.viewport;
        
        // Optimize stage width for mobile
        if (viewport.orientation === 'portrait') {
            stages.css('width', Math.min(280, viewport.width - 40) + 'px');
        } else {
            stages.css('width', Math.min(320, (viewport.width / 2) - 40) + 'px');
        }
        
        // Show mobile swipe hint if not shown before
        if (!localStorage.getItem('mobile_swipe_hint_shown')) {
            this.showMobileSwipeHint();
        }
    },
    
    /**
     * Apply tablet-specific layout optimizations
     */
    applyTabletLayout: function() {
        var stages = jQuery('.pipeline-stage');
        var viewport = this.config.viewport;
        var boardWrapper = jQuery('.pipeline-board-wrapper');
        
        // Calculate optimal stage width for tablet
        var availableWidth = viewport.width - 60; // Account for padding
        var stageCount = stages.length;
        var minStageWidth = 320;
        var maxStagesVisible = Math.floor(availableWidth / minStageWidth);
        
        if (stageCount <= maxStagesVisible) {
            // All stages fit, distribute evenly
            var stageWidth = Math.floor(availableWidth / stageCount) - 20;
            stages.css('width', Math.max(minStageWidth, stageWidth) + 'px');
        } else {
            // Use default width for scrolling
            stages.css('width', minStageWidth + 'px');
        }
    },
    
    /**
     * Apply desktop-specific layout optimizations
     */
    applyDesktopLayout: function() {
        var stages = jQuery('.pipeline-stage');
        
        // Reset to default desktop width
        stages.css('width', '300px');
    },
    
    /**
     * Show mobile swipe hint
     */
    showMobileSwipeHint: function() {
        var hint = jQuery('.mobile-swipe-hint');
        if (hint.length) {
            hint.fadeIn().delay(3000).fadeOut();
            localStorage.setItem('mobile_swipe_hint_shown', 'true');
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
     * Initialize mobile and tablet touch gestures with enhanced support
     */
    initMobileGestures: function() {
        var self = this;
        
        // Enhanced touch events with proper event delegation
        jQuery(document).on('touchstart', '.draggable', function(e) {
            self.handleTouchStart(e);
        });
        
        jQuery(document).on('touchmove', function(e) {
            if (self.isDragging) {
                self.handleTouchMove(e);
            }
        });
        
        jQuery(document).on('touchend touchcancel', function(e) {
            if (self.isDragging || self.touchHoldTimer) {
                self.handleTouchEnd(e);
            }
        });
        
        // Prevent default touch behaviors on drag handles
        jQuery(document).on('touchstart', '.deal-card', function(e) {
            // Allow scrolling by default, will be prevented if drag starts
            e.stopPropagation();
        });
        
        // Enhanced swipe detection for horizontal scrolling
        this.initSwipeGestures();
        
        // Initialize touch feedback
        this.initTouchFeedback();
        
        // Add touch-friendly class to body
        jQuery('body').addClass('touch-enabled');
        
        // Add device-specific classes
        if (this.config.isTablet) {
            jQuery('body').addClass('tablet-touch');
        }
        if (this.config.isMobile) {
            jQuery('body').addClass('mobile-touch');
        }
    },
    
    /**
     * Initialize touch feedback for better user experience
     */
    initTouchFeedback: function() {
        var self = this;
        
        // Add touch feedback to interactive elements
        jQuery(document).on('touchstart', '.deal-card, .focus-toggle-btn, .pipeline-actions .btn', function(e) {
            var element = jQuery(this);
            element.addClass('touch-active');
            
            // Remove feedback after a short delay
            setTimeout(function() {
                element.removeClass('touch-active');
            }, 150);
        });
        
        // Enhanced button feedback
        jQuery(document).on('touchstart', '.btn', function(e) {
            var btn = jQuery(this);
            btn.addClass('btn-pressed');
            
            setTimeout(function() {
                btn.removeClass('btn-pressed');
            }, 100);
        });
    },
    
    /**
     * Initialize swipe gestures for board navigation
     */
    initSwipeGestures: function() {
        var self = this;
        var board = document.getElementById('pipeline-board');
        
        if (typeof Hammer !== 'undefined') {
            var hammer = new Hammer(board, {
                recognizers: [
                    [Hammer.Swipe, { direction: Hammer.DIRECTION_HORIZONTAL }],
                    [Hammer.Pan, { direction: Hammer.DIRECTION_HORIZONTAL, threshold: 30 }]
                ]
            });
            
            hammer.on('swipeleft swiperight', function(e) {
                if (!self.isDragging) {
                    self.handleBoardSwipe(e.type === 'swipeleft' ? 'left' : 'right');
                }
            });
            
            hammer.on('panleft panright', function(e) {
                if (!self.isDragging && Math.abs(e.deltaX) > 50) {
                    self.handleBoardPan(e.deltaX);
                }
            });
        } else {
            // Fallback swipe detection
            this.initFallbackSwipe();
        }
    },
    
    /**
     * Handle board swipe navigation
     */
    handleBoardSwipe: function(direction) {
        var scrollAmount = 300;
        var wrapper = jQuery('.pipeline-board-wrapper');
        var currentScroll = wrapper.scrollLeft();
        var maxScroll = wrapper[0].scrollWidth - wrapper.width();
        
        var targetScroll = direction === 'left' 
            ? Math.min(currentScroll + scrollAmount, maxScroll)
            : Math.max(currentScroll - scrollAmount, 0);
            
        wrapper.animate({
            scrollLeft: targetScroll
        }, 300, 'swing');
    },
    
    /**
     * Handle board pan for smooth scrolling
     */
    handleBoardPan: function(deltaX) {
        var wrapper = jQuery('.pipeline-board-wrapper');
        var currentScroll = wrapper.scrollLeft();
        wrapper.scrollLeft(currentScroll - deltaX * 0.5);
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
        // Use the new handleDropWithFocus function that supports reordering
        return this.handleDropWithFocus(e);
    },
    
    /**
     * Handle touch start with improved detection
     */
    handleTouchStart: function(e) {
        var self = this;
        var touch = e.originalEvent.touches[0];
        
        // Don't start drag if clicking on buttons or links
        if (jQuery(e.target).is('button, a, input, .focus-toggle-btn')) {
            return;
        }
        
        // Store initial touch data
        this.touchStartX = touch.pageX;
        this.touchStartY = touch.pageY;
        this.touchStartTime = Date.now();
        this.touchIdentifier = touch.identifier;
        this.originalTouchTarget = e.currentTarget;
        this.scrollStartY = jQuery('.pipeline-board-wrapper').scrollTop();
        
        // Store potential drag data
        this.draggedElement = e.currentTarget;
        this.draggedData = {
            dealId: e.currentTarget.dataset.dealId,
            sourceStage: e.currentTarget.dataset.stage
        };
        
        // Set up hold timer for drag initiation
        this.touchHoldTimer = setTimeout(function() {
            if (!self.isDragging) {
                self.initiateTouchDrag(touch);
            }
        }, this.config.touchHoldDuration);
        
        // Add touch feedback after delay
        setTimeout(function() {
            if (self.touchHoldTimer && !self.isDragging) {
                jQuery(self.draggedElement).addClass('touch-hold');
            }
        }, this.config.touchFeedbackDelay);
    },
    
    /**
     * Initiate drag operation for touch
     */
    initiateTouchDrag: function(touch) {
        this.isDragging = true;
        
        // Clear any timers
        if (this.touchHoldTimer) {
            clearTimeout(this.touchHoldTimer);
            this.touchHoldTimer = null;
        }
        
        // Create visual clone for dragging
        this.createTouchDragClone();
        
        // Add dragging state
        jQuery(this.draggedElement).addClass('dragging touch-dragging');
        jQuery('body').addClass('touch-dragging-active');
        
        // Prevent scrolling while dragging
        jQuery('.pipeline-board-wrapper').css('overflow', 'hidden');
        
        // Haptic feedback if available
        if (window.navigator && window.navigator.vibrate) {
            window.navigator.vibrate(50);
        }
        
        // Update clone position
        this.updateDragClonePosition(touch.pageX, touch.pageY);
    },
    
    /**
     * Create a visual clone for touch dragging
     */
    createTouchDragClone: function() {
        var original = jQuery(this.draggedElement);
        var offset = original.offset();
        
        // Create clone
        this.dragClone = original.clone()
            .addClass('touch-drag-clone')
            .css({
                position: 'fixed',
                top: offset.top,
                left: offset.left,
                width: original.outerWidth(),
                height: original.outerHeight(),
                zIndex: 9999,
                opacity: 0.9,
                transform: 'scale(1.05) rotate(2deg)',
                transition: 'transform 0.2s',
                pointerEvents: 'none',
                boxShadow: '0 5px 15px rgba(0,0,0,0.3)'
            });
            
        jQuery('body').append(this.dragClone);
    },
    
    /**
     * Handle touch move with enhanced features
     */
    handleTouchMove: function(e) {
        var touch = this.getTouchById(e.originalEvent.touches, this.touchIdentifier);
        if (!touch) return;
        
        var moveX = touch.pageX - this.touchStartX;
        var moveY = touch.pageY - this.touchStartY;
        var distance = Math.sqrt(moveX * moveX + moveY * moveY);
        
        // Check if we should start dragging
        if (!this.isDragging && distance > this.config.touchScrollThreshold) {
            // Clear hold timer if movement detected
            if (this.touchHoldTimer) {
                clearTimeout(this.touchHoldTimer);
                this.touchHoldTimer = null;
                jQuery(this.draggedElement).removeClass('touch-hold');
            }
            
            // Check if it's been long enough to start drag
            var elapsed = Date.now() - this.touchStartTime;
            if (elapsed >= this.config.touchHoldDuration) {
                this.initiateTouchDrag(touch);
            } else {
                // Allow normal scrolling
                return;
            }
        }
        
        if (this.isDragging) {
            e.preventDefault();
            e.stopPropagation();
            
            // Update clone position
            this.updateDragClonePosition(touch.pageX, touch.pageY);
            
            // Find drop target with improved hit testing
            this.updateDropTarget(touch);
            
            // Handle auto-scrolling near edges
            this.handleAutoScroll(touch);
        }
    },
    
    /**
     * Update drag clone position smoothly
     */
    updateDragClonePosition: function(pageX, pageY) {
        if (this.dragClone) {
            var offsetX = pageX - this.touchStartX;
            var offsetY = pageY - this.touchStartY;
            
            this.dragClone.css({
                transform: 'translate(' + offsetX + 'px, ' + offsetY + 'px) scale(1.05) rotate(2deg)'
            });
        }
    },
    
    /**
     * Update drop target with visual feedback
     */
    updateDropTarget: function(touch) {
        // Hide clone temporarily for hit testing
        if (this.dragClone) {
            this.dragClone.hide();
        }
        
        var elementBelow = document.elementFromPoint(touch.pageX, touch.pageY);
        
        // Show clone again
        if (this.dragClone) {
            this.dragClone.show();
        }
        
        if (elementBelow) {
            var dropZone = jQuery(elementBelow).closest('.droppable');
            
            if (dropZone.length && dropZone[0] !== this.lastDropTarget) {
                // Remove previous highlights
                jQuery('.drag-over').removeClass('drag-over');
                jQuery('.drop-position-indicator').remove();
                
                // Check WIP limits
                var targetStage = dropZone.data('stage');
                var wipLimit = parseInt(dropZone.data('wip-limit'));
                var currentCount = dropZone.find('.deal-card').length;
                
                if (wipLimit && currentCount >= wipLimit && this.draggedData.sourceStage !== targetStage) {
                    dropZone.addClass('wip-limit-exceeded');
                } else {
                    dropZone.addClass('drag-over');
                    
                    // Show drop position indicator
                    var dropPosition = this.getDropPosition(dropZone[0], touch.pageY);
                    if (dropPosition.element) {
                        this.showDropPositionIndicator(dropPosition.element, dropPosition.before);
                    }
                }
                
                this.lastDropTarget = dropZone[0];
                
                // Haptic feedback for valid drop zone
                if (window.navigator && window.navigator.vibrate) {
                    window.navigator.vibrate(20);
                }
            }
        }
    },
    
    /**
     * Get drop position within a stage
     */
    getDropPosition: function(dropZone, pageY) {
        var cards = jQuery(dropZone).find('.deal-card:not(.dragging)');
        var result = { element: null, before: true };
        
        cards.each(function() {
            var rect = this.getBoundingClientRect();
            var midpoint = rect.top + rect.height / 2;
            
            if (pageY < midpoint) {
                result.element = this;
                result.before = true;
                return false; // break
            } else if (pageY < rect.bottom) {
                result.element = this;
                result.before = false;
                return false; // break
            }
        });
        
        return result;
    },
    
    /**
     * Show visual indicator for drop position
     */
    showDropPositionIndicator: function(element, before) {
        var indicator = jQuery('<div class="drop-position-indicator"></div>');
        
        if (before) {
            jQuery(element).before(indicator);
        } else {
            jQuery(element).after(indicator);
        }
    },
    
    /**
     * Handle auto-scrolling near edges
     */
    handleAutoScroll: function(touch) {
        var wrapper = jQuery('.pipeline-board-wrapper');
        var wrapperRect = wrapper[0].getBoundingClientRect();
        var scrollSpeed = 5;
        var edgeSize = 50;
        
        // Horizontal scrolling
        if (touch.pageX < wrapperRect.left + edgeSize) {
            wrapper.scrollLeft(wrapper.scrollLeft() - scrollSpeed);
        } else if (touch.pageX > wrapperRect.right - edgeSize) {
            wrapper.scrollLeft(wrapper.scrollLeft() + scrollSpeed);
        }
        
        // Vertical scrolling for stage bodies
        var stageBody = jQuery(touch.target).closest('.stage-body');
        if (stageBody.length) {
            var stageRect = stageBody[0].getBoundingClientRect();
            
            if (touch.pageY < stageRect.top + edgeSize) {
                stageBody.scrollTop(stageBody.scrollTop() - scrollSpeed);
            } else if (touch.pageY > stageRect.bottom - edgeSize) {
                stageBody.scrollTop(stageBody.scrollTop() + scrollSpeed);
            }
        }
    },
    
    /**
     * Get touch by identifier
     */
    getTouchById: function(touches, id) {
        for (var i = 0; i < touches.length; i++) {
            if (touches[i].identifier === id) {
                return touches[i];
            }
        }
        return null;
    },
    
    /**
     * Handle touch end with cleanup
     */
    handleTouchEnd: function(e) {
        // Clear any timers
        if (this.touchHoldTimer) {
            clearTimeout(this.touchHoldTimer);
            this.touchHoldTimer = null;
        }
        
        // Remove touch feedback
        jQuery(this.draggedElement).removeClass('touch-hold');
        
        if (this.isDragging) {
            var touch = this.getTouchById(e.originalEvent.changedTouches, this.touchIdentifier);
            if (touch) {
                this.completeTouchDrag(touch);
            }
        }
        
        // Reset state
        this.cleanupTouchDrag();
    },
    
    /**
     * Complete the touch drag operation
     */
    completeTouchDrag: function(touch) {
        // Remove clone with animation
        if (this.dragClone) {
            this.dragClone.hide();
        }
        
        // Find final drop target
        var elementBelow = document.elementFromPoint(touch.pageX, touch.pageY);
        
        if (elementBelow) {
            var dropZone = jQuery(elementBelow).closest('.droppable');
            if (dropZone.length && !dropZone.hasClass('wip-limit-exceeded')) {
                var targetStage = dropZone.data('stage');
                
                // Get drop position
                var dropPosition = this.getDropPosition(dropZone[0], touch.pageY);
                var afterElement = dropPosition.element && !dropPosition.before ? dropPosition.element : null;
                
                // Move the card
                if (targetStage !== this.draggedData.sourceStage || afterElement) {
                    this.moveCardWithPosition(this.draggedElement, dropZone[0], targetStage, afterElement);
                }
            }
        }
    },
    
    /**
     * Clean up after touch drag
     */
    cleanupTouchDrag: function() {
        // Remove visual states
        jQuery(this.draggedElement).removeClass('dragging touch-dragging');
        jQuery('body').removeClass('touch-dragging-active');
        jQuery('.drag-over').removeClass('drag-over wip-limit-exceeded');
        jQuery('.drop-position-indicator').remove();
        
        // Remove clone
        if (this.dragClone) {
            this.dragClone.remove();
            this.dragClone = null;
        }
        
        // Re-enable scrolling
        jQuery('.pipeline-board-wrapper').css('overflow', '');
        
        // Reset state
        this.isDragging = false;
        this.touchIdentifier = null;
        this.lastDropTarget = null;
        this.draggedElement = null;
        this.draggedData = null;
    },
    
    /**
     * Initialize fallback swipe detection
     */
    initFallbackSwipe: function() {
        var self = this;
        var startX = 0;
        var startY = 0;
        var startTime = 0;
        
        jQuery('.pipeline-board-wrapper').on('touchstart', function(e) {
            if (!self.isDragging) {
                var touch = e.originalEvent.touches[0];
                startX = touch.pageX;
                startY = touch.pageY;
                startTime = Date.now();
            }
        });
        
        jQuery('.pipeline-board-wrapper').on('touchend', function(e) {
            if (!self.isDragging) {
                var touch = e.originalEvent.changedTouches[0];
                var deltaX = touch.pageX - startX;
                var deltaY = touch.pageY - startY;
                var elapsed = Date.now() - startTime;
                
                // Detect swipe
                if (elapsed < 300 && Math.abs(deltaX) > 50 && Math.abs(deltaY) < Math.abs(deltaX)) {
                    self.handleBoardSwipe(deltaX < 0 ? 'left' : 'right');
                }
            }
        });
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
        var self = this;
        
        jQuery('.pipeline-stage').each(function() {
            var stage = jQuery(this);
            var stageKey = stage.data('stage');
            var allCards = stage.find('.deal-card');
            var visibleCards = self.config.focusFilterActive ? 
                allCards.filter('.focused-deal') : 
                allCards;
            var count = visibleCards.length;
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
        
        // Update button state
        var btn = jQuery('.pipeline-actions button:has(.glyphicon-resize-small)').first();
        if (this.config.compactView) {
            btn.addClass('active');
        } else {
            btn.removeClass('active');
        }
        
        // Save preference
        localStorage.setItem('pipeline_compact_view', this.config.compactView ? 'true' : 'false');
    },
    
    /**
     * Set compact view state
     */
    setCompactView: function(compact) {
        if (compact) {
            jQuery('#pipeline-container').addClass('compact-view');
            // Update button state
            jQuery('.pipeline-actions button:has(.glyphicon-resize-small)').first().addClass('active');
        } else {
            jQuery('#pipeline-container').removeClass('compact-view');
            // Update button state
            jQuery('.pipeline-actions button:has(.glyphicon-resize-small)').first().removeClass('active');
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
    },
    
    /**
     * Open bulk stakeholder management
     */
    openBulkStakeholders: function() {
        // Get all visible deals
        var dealIds = [];
        jQuery('.deal-card:visible').each(function() {
            var dealId = jQuery(this).data('deal-id');
            if (dealId) {
                dealIds.push(dealId);
            }
        });
        
        // Open bulk management view with pre-selected deals
        var url = 'index.php?module=Deals&action=stakeholder_bulk';
        if (dealIds.length > 0) {
            url += '&deal_ids=' + dealIds.join(',');
        }
        
        // Open in same window
        window.location.href = url;
    },
    
    /**
     * Toggle focus flag on a deal
     */
    toggleFocus: function(dealId, focusState) {
        var self = this;
        
        // Show loading
        this.showLoading();
        
        // Send AJAX request
        jQuery.ajax({
            url: 'index.php?module=Deals&action=toggleFocus',
            type: 'POST',
            data: {
                deal_id: dealId,
                focus_state: focusState
            },
            success: function(response) {
                self.hideLoading();
                
                if (response.success) {
                    // Update UI
                    var card = jQuery('.deal-card[data-deal-id="' + dealId + '"]');
                    var btn = card.find('.focus-toggle-btn');
                    var star = btn.find('.glyphicon');
                    
                    if (focusState) {
                        card.addClass('focused-deal');
                        card.attr('data-focused', 'true');
                        card.attr('data-focus-order', response.focus_order);
                        btn.addClass('active');
                        star.removeClass('glyphicon-star-empty').addClass('glyphicon-star');
                        btn.attr('onclick', "PipelineView.toggleFocus('" + dealId + "', false); event.stopPropagation();");
                        btn.attr('title', 'Remove focus');
                    } else {
                        card.removeClass('focused-deal');
                        card.attr('data-focused', 'false');
                        card.attr('data-focus-order', '0');
                        btn.removeClass('active');
                        star.removeClass('glyphicon-star').addClass('glyphicon-star-empty');
                        btn.attr('onclick', "PipelineView.toggleFocus('" + dealId + "', true); event.stopPropagation();");
                        btn.attr('title', 'Mark as focused');
                    }
                    
                    // Reorder cards in the stage
                    self.reorderCardsInStage(card.closest('.stage-body'));
                    
                    self.showNotification(response.message, 'success');
                } else {
                    self.showNotification(response.message || 'Failed to update focus', 'error');
                }
            },
            error: function() {
                self.hideLoading();
                self.showNotification('Network error. Please try again.', 'error');
            }
        });
    },
    
    /**
     * Toggle focus filter view
     */
    toggleFocusFilter: function() {
        this.config.focusFilterActive = !this.config.focusFilterActive;
        this.applyFocusFilter(this.config.focusFilterActive);
        
        // Save preference
        localStorage.setItem('pipeline_focus_filter', this.config.focusFilterActive ? 'true' : 'false');
    },
    
    /**
     * Apply focus filter state
     */
    applyFocusFilter: function(active) {
        var btn = jQuery('#focus-filter-btn');
        var btnText = jQuery('#focus-filter-text');
        
        if (active) {
            btn.addClass('active');
            btnText.text('Show All');
            jQuery('.deal-card:not(.focused-deal)').fadeOut();
        } else {
            btn.removeClass('active');
            btnText.text('Show Focused');
            jQuery('.deal-card').fadeIn();
        }
        
        // Update stage counts for visible cards
        this.updateStageCounts();
    },
    
    /**
     * Reorder cards in a stage based on focus order
     */
    reorderCardsInStage: function(stageBody) {
        var cards = stageBody.find('.deal-card').toArray();
        
        // Sort cards: focused deals first (by focus_order), then non-focused
        cards.sort(function(a, b) {
            var aFocused = jQuery(a).attr('data-focused') === 'true';
            var bFocused = jQuery(b).attr('data-focused') === 'true';
            var aOrder = parseInt(jQuery(a).attr('data-focus-order') || '0');
            var bOrder = parseInt(jQuery(b).attr('data-focus-order') || '0');
            
            // Both focused: sort by focus order
            if (aFocused && bFocused) {
                return aOrder - bOrder;
            }
            
            // One focused, one not: focused comes first
            if (aFocused && !bFocused) return -1;
            if (!aFocused && bFocused) return 1;
            
            // Neither focused: maintain current order
            return 0;
        });
        
        // Remove and re-append cards in sorted order
        jQuery(cards).detach();
        stageBody.append(cards);
    },
    
    /**
     * Handle drop with focus order consideration
     */
    handleDropWithFocus: function(e) {
        if (e.stopPropagation) {
            e.stopPropagation();
        }
        
        var dropZone = e.currentTarget;
        var targetStage = dropZone.dataset.stage;
        var sourceStage = this.draggedData.sourceStage;
        var dealId = this.draggedData.dealId;
        
        dropZone.classList.remove('drag-over');
        dropZone.classList.remove('wip-limit-exceeded');
        
        // Check WIP limit using WIP Limit Manager if available
        if (typeof WIPLimitManager !== 'undefined') {
            var validation = WIPLimitManager.validateDrop(targetStage, sourceStage, dealId);
            
            if (!validation.allowed) {
                if (validation.canOverride && WIPLimitManager.config.enableOverrides) {
                    // Show override confirmation
                    if (confirm(validation.message + '\n\nDo you want to override the WIP limit?')) {
                        // Log override event
                        WIPLimitManager.logWIPEvent('limit_override', {
                            dealId: dealId,
                            stage: targetStage,
                            reason: validation.reason,
                            userId: this.config.currentUserId
                        });
                    } else {
                        return false;
                    }
                } else {
                    this.showNotification(validation.message, 'error');
                    return false;
                }
            } else if (validation.warning) {
                // Show warning but allow drop
                this.showNotification(validation.message, 'warning');
            }
        }
        
        // Get drop position
        var dropY = e.clientY;
        var afterElement = this.getDragAfterElement(dropZone, dropY);
        
        // Don't do anything if dropping in same stage at same position
        if (sourceStage === targetStage && !afterElement) {
            return false;
        }
        
        // Move the card with position consideration
        this.moveCardWithPosition(this.draggedElement, dropZone, targetStage, afterElement);
        
        return false;
    },
    
    /**
     * Get element after which to insert dragged element
     */
    getDragAfterElement: function(container, y) {
        var draggableElements = [...container.querySelectorAll('.deal-card:not(.dragging)')];
        
        return draggableElements.reduce(function(closest, child) {
            var box = child.getBoundingClientRect();
            var offset = y - box.top - box.height / 2;
            
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    },
    
    /**
     * Move card with position consideration for focused deals
     */
    moveCardWithPosition: function(card, dropZone, targetStage, afterElement) {
        var self = this;
        var isFocused = jQuery(card).attr('data-focused') === 'true';
        
        // Show loading
        this.showLoading();
        
        // Remove empty placeholder if exists
        jQuery(dropZone).find('.empty-stage-placeholder').remove();
        
        // Calculate new focus order if the card is focused
        var newFocusOrder = 0;
        if (isFocused) {
            var focusedCards = jQuery(dropZone).find('.deal-card.focused-deal');
            if (afterElement) {
                var afterCard = jQuery(afterElement);
                var afterFocused = afterCard.hasClass('focused-deal');
                var afterOrder = parseInt(afterCard.attr('data-focus-order') || '0');
                
                if (afterFocused) {
                    // Find the next focused card
                    var nextFocused = afterCard.nextAll('.focused-deal').first();
                    if (nextFocused.length) {
                        var nextOrder = parseInt(nextFocused.attr('data-focus-order') || '0');
                        newFocusOrder = Math.floor((afterOrder + nextOrder) / 2);
                    } else {
                        newFocusOrder = afterOrder + 1;
                    }
                } else {
                    // Dropped after a non-focused card, find the last focused card before it
                    var prevFocused = afterCard.prevAll('.focused-deal').first();
                    if (prevFocused.length) {
                        newFocusOrder = parseInt(prevFocused.attr('data-focus-order') || '0') + 1;
                    } else {
                        newFocusOrder = 1;
                    }
                }
            } else {
                // Dropped at the beginning
                var firstFocused = focusedCards.first();
                if (firstFocused.length && firstFocused[0] !== card) {
                    var firstOrder = parseInt(firstFocused.attr('data-focus-order') || '1');
                    newFocusOrder = Math.max(0, firstOrder - 1);
                } else {
                    newFocusOrder = 1;
                }
            }
        }
        
        // Move card visually
        if (afterElement) {
            jQuery(afterElement).after(card);
        } else {
            jQuery(dropZone).prepend(card);
        }
        
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
                if (response.success) {
                    // Update focus order if needed
                    if (isFocused && newFocusOrder > 0) {
                        jQuery.ajax({
                            url: 'index.php?module=Deals&action=updateFocusOrder',
                            type: 'POST',
                            data: {
                                deal_id: self.draggedData.dealId,
                                new_order: newFocusOrder,
                                stage: targetStage
                            },
                            success: function(orderResponse) {
                                self.hideLoading();
                                if (orderResponse.success) {
                                    jQuery(card).attr('data-focus-order', orderResponse.new_order);
                                    self.reorderCardsInStage(jQuery(dropZone));
                                }
                            },
                            error: function() {
                                self.hideLoading();
                            }
                        });
                    } else {
                        self.hideLoading();
                    }
                    
                    self.showNotification('Deal moved successfully', 'success');
                    
                    // Update time in stage
                    if (response.stage_entered_date) {
                        self.updateTimeInStage(card, response.stage_entered_date);
                    }
                } else {
                    self.hideLoading();
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