/**
 * Pipeline Kanban View Component
 * Interactive drag-and-drop pipeline visualization
 */

class PipelineKanbanView {
    constructor(config) {
        this.stages = config.stages || [];
        this.deals = config.deals || [];
        this.wipLimits = config.wipLimits || {};
        this.currentUser = config.currentUser || {};
        this.permissions = config.permissions || {};
        this.containerId = config.containerId || 'pipeline-container';
        this.callbacks = config.callbacks || {};
        
        this.draggedDeal = null;
        this.lastUpdateTime = Date.now();
        
        this.init();
    }
    
    init() {
        this.renderPipeline();
        this.initializeDragDrop();
        this.initializeEventHandlers();
        this.startAutoRefresh();
    }
    
    renderPipeline() {
        const container = document.getElementById(this.containerId);
        if (!container) {
            console.error('Pipeline container not found');
            return;
        }
        
        container.innerHTML = '';
        container.className = 'pipeline-kanban-container';
        
        // Create header with metrics
        const header = this.createPipelineHeader();
        container.appendChild(header);
        
        // Create stages container
        const stagesContainer = document.createElement('div');
        stagesContainer.className = 'pipeline-stages-container';
        
        this.stages.forEach(stage => {
            const stageColumn = this.createStageColumn(stage);
            stagesContainer.appendChild(stageColumn);
        });
        
        container.appendChild(stagesContainer);
        
        // Create footer with controls
        const footer = this.createPipelineFooter();
        container.appendChild(footer);
    }
    
    createPipelineHeader() {
        const header = document.createElement('div');
        header.className = 'pipeline-header';
        
        const metrics = this.calculatePipelineMetrics();
        
        header.innerHTML = `
            <div class="pipeline-title">
                <h2>M&A Deal Pipeline</h2>
                <div class="pipeline-refresh">
                    <button id="refresh-pipeline" class="btn btn-secondary btn-sm">
                        <i class="fa fa-refresh"></i> Refresh
                    </button>
                    <span class="last-updated">Updated: ${new Date().toLocaleTimeString()}</span>
                </div>
            </div>
            <div class="pipeline-metrics">
                <div class="metric-card">
                    <div class="metric-value">${metrics.totalDeals}</div>
                    <div class="metric-label">Active Deals</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value">$${this.formatCurrency(metrics.totalValue)}</div>
                    <div class="metric-label">Pipeline Value</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value">${metrics.avgDaysInStage}</div>
                    <div class="metric-label">Avg Days/Stage</div>
                </div>
                <div class="metric-card ${metrics.stalePercentage > 20 ? 'metric-warning' : ''}">
                    <div class="metric-value">${metrics.stalePercentage}%</div>
                    <div class="metric-label">Stale Deals</div>
                </div>
                <div class="metric-card ${metrics.wipViolations > 0 ? 'metric-danger' : ''}">
                    <div class="metric-value">${metrics.wipViolations}</div>
                    <div class="metric-label">WIP Violations</div>
                </div>
            </div>
        `;
        
        return header;
    }
    
    createStageColumn(stage) {
        const column = document.createElement('div');
        column.className = 'pipeline-stage-column';
        column.dataset.stage = stage.name;
        
        const stageDeals = this.getDealsInStage(stage.name);
        const wipStatus = this.getWIPStatus(stage, stageDeals.length);
        
        // Apply WIP status classes
        if (wipStatus.exceeded) {
            column.classList.add('wip-exceeded');
        } else if (wipStatus.warning) {
            column.classList.add('wip-warning');
        }
        
        // Create stage header
        const header = document.createElement('div');
        header.className = 'stage-header';
        header.innerHTML = `
            <div class="stage-title">
                <h3>${stage.display_name || stage.name}</h3>
                <div class="stage-probability">${stage.probability_default || 0}%</div>
            </div>
            <div class="stage-metrics">
                <div class="deal-count-display">
                    <span class="deal-count">${stageDeals.length}</span>
                    ${stage.wip_limit ? `<span class="wip-limit">/ ${stage.wip_limit}</span>` : ''}
                </div>
                <div class="stage-value">$${this.formatCurrency(this.calculateStageValue(stageDeals))}</div>
            </div>
            <div class="wip-indicator ${wipStatus.exceeded ? 'exceeded' : wipStatus.warning ? 'warning' : 'normal'}">
                <div class="wip-bar">
                    <div class="wip-fill" style="width: ${Math.min(100, wipStatus.utilization)}%"></div>
                </div>
                <span class="wip-text">${wipStatus.utilization}%</span>
            </div>
        `;
        
        column.appendChild(header);
        
        // Create deals container
        const dealsContainer = document.createElement('div');
        dealsContainer.className = 'stage-deals';
        dealsContainer.setAttribute('data-stage', stage.name);
        
        // Add deals to container
        stageDeals.forEach(deal => {
            const dealCard = this.createDealCard(deal);
            dealsContainer.appendChild(dealCard);
        });
        
        // Add empty state if no deals
        if (stageDeals.length === 0) {
            const emptyState = document.createElement('div');
            emptyState.className = 'stage-empty';
            emptyState.innerHTML = `
                <div class="empty-message">
                    <i class="fa fa-inbox"></i>
                    <p>No deals in ${stage.display_name}</p>
                </div>
            `;
            dealsContainer.appendChild(emptyState);
        }
        
        column.appendChild(dealsContainer);
        
        // Add new deal button
        if (this.permissions.canCreate) {
            const addButton = document.createElement('button');
            addButton.className = 'btn btn-outline-primary btn-sm add-deal-btn';
            addButton.innerHTML = '<i class="fa fa-plus"></i> Add Deal';
            addButton.onclick = () => this.showNewDealModal(stage.name);
            column.appendChild(addButton);
        }
        
        return column;
    }
    
    createDealCard(deal) {
        const card = document.createElement('div');
        card.className = 'deal-card';
        card.dataset.dealId = deal.id;
        card.dataset.stage = deal.stage;
        card.draggable = this.permissions.canEdit;
        
        // Calculate deal status indicators
        const staleness = this.calculateCardStaleness(deal);
        const healthStatus = this.getHealthStatus(deal.health_score);
        const progressStatus = this.getProgressStatus(deal);
        
        // Apply status classes
        if (staleness.status === 'warning') {
            card.classList.add('stale-warning');
        } else if (staleness.status === 'critical') {
            card.classList.add('stale-critical');
        }
        
        card.classList.add(`health-${healthStatus.level}`);
        
        const daysInStage = deal.days_in_stage || 0;
        const probability = deal.probability || 0;
        
        card.innerHTML = `
            <div class="deal-header">
                <div class="deal-title">
                    <h4 title="${deal.name}">${this.truncateText(deal.name, 25)}</h4>
                    <div class="deal-id">#${deal.id.substring(0, 8)}</div>
                </div>
                <div class="deal-value">$${this.formatCurrency(deal.deal_value || 0)}</div>
            </div>
            
            <div class="deal-company">
                <i class="fa fa-building"></i>
                <span title="${deal.company_name}">${this.truncateText(deal.company_name || 'Unknown', 20)}</span>
            </div>
            
            <div class="deal-meta">
                <div class="meta-row">
                    <span class="meta-label">Days in Stage:</span>
                    <span class="meta-value ${daysInStage > 30 ? 'text-warning' : ''}">${daysInStage}d</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Probability:</span>
                    <span class="meta-value">${probability}%</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Health:</span>
                    <span class="meta-value health-indicator ${healthStatus.level}">
                        ${healthStatus.icon} ${deal.health_score || 0}%
                    </span>
                </div>
            </div>
            
            <div class="deal-progress">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: ${progressStatus.percentage}%"></div>
                </div>
                <span class="progress-text">${progressStatus.percentage}% Complete</span>
            </div>
            
            <div class="deal-indicators">
                ${staleness.indicator ? `<span class="stale-indicator ${staleness.status}" title="${staleness.reason}">${staleness.indicator}</span>` : ''}
                ${deal.is_high_priority ? '<span class="priority-indicator" title="High Priority">🔥</span>' : ''}
                ${this.hasRecentActivity(deal) ? '<span class="activity-indicator" title="Recent Activity">💬</span>' : ''}
                ${deal.assigned_user_name ? `<span class="user-indicator" title="Assigned: ${deal.assigned_user_name}">👤</span>` : ''}
            </div>
            
            <div class="deal-actions">
                <button class="btn btn-sm btn-outline-primary view-deal" onclick="window.open('index.php?module=mdeal_Deals&action=DetailView&record=${deal.id}', '_blank')">
                    <i class="fa fa-eye"></i>
                </button>
                ${this.permissions.canEdit ? `
                <button class="btn btn-sm btn-outline-secondary edit-deal" onclick="window.open('index.php?module=mdeal_Deals&action=EditView&record=${deal.id}', '_blank')">
                    <i class="fa fa-edit"></i>
                </button>
                ` : ''}
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-toggle="dropdown">
                        <i class="fa fa-ellipsis-v"></i>
                    </button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="#" onclick="pipelineView.showDealDetails('${deal.id}')">
                            <i class="fa fa-info-circle"></i> Details
                        </a>
                        <a class="dropdown-item" href="#" onclick="pipelineView.showStageHistory('${deal.id}')">
                            <i class="fa fa-history"></i> Stage History
                        </a>
                        <a class="dropdown-item" href="#" onclick="pipelineView.showActivityLog('${deal.id}')">
                            <i class="fa fa-list"></i> Activities
                        </a>
                        ${this.permissions.canEdit ? `
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="#" onclick="pipelineView.forceStageMove('${deal.id}')">
                            <i class="fa fa-forward"></i> Force Move
                        </a>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
        
        return card;
    }
    
    createPipelineFooter() {
        const footer = document.createElement('div');
        footer.className = 'pipeline-footer';
        
        footer.innerHTML = `
            <div class="pipeline-controls">
                <div class="view-controls">
                    <label>View:</label>
                    <select id="pipeline-filter" class="form-control form-control-sm">
                        <option value="all">All Deals</option>
                        <option value="my">My Deals</option>
                        <option value="team">Team Deals</option>
                        <option value="stale">Stale Deals</option>
                        <option value="high-value">High Value (>$10M)</option>
                    </select>
                </div>
                <div class="sort-controls">
                    <label>Sort by:</label>
                    <select id="pipeline-sort" class="form-control form-control-sm">
                        <option value="date_modified">Last Modified</option>
                        <option value="deal_value">Deal Value</option>
                        <option value="days_in_stage">Days in Stage</option>
                        <option value="health_score">Health Score</option>
                        <option value="probability">Probability</option>
                    </select>
                </div>
                <div class="refresh-controls">
                    <label>
                        <input type="checkbox" id="auto-refresh" checked> Auto-refresh (30s)
                    </label>
                </div>
            </div>
            <div class="pipeline-legend">
                <div class="legend-item">
                    <span class="legend-indicator stale-warning"></span>
                    <span>Stale Warning</span>
                </div>
                <div class="legend-item">
                    <span class="legend-indicator stale-critical"></span>
                    <span>Stale Critical</span>
                </div>
                <div class="legend-item">
                    <span class="legend-indicator health-high"></span>
                    <span>High Health</span>
                </div>
                <div class="legend-item">
                    <span class="legend-indicator health-low"></span>
                    <span>Low Health</span>
                </div>
                <div class="legend-item">
                    <span class="legend-indicator wip-exceeded"></span>
                    <span>WIP Exceeded</span>
                </div>
            </div>
        `;
        
        return footer;
    }
    
    initializeDragDrop() {
        // Add drag event listeners to deal cards
        this.addDragEventListeners();
        
        // Add drop zone event listeners to stage columns
        this.addDropZoneListeners();
    }
    
    addDragEventListeners() {
        document.addEventListener('dragstart', (e) => {
            if (e.target.classList.contains('deal-card')) {
                this.draggedDeal = {
                    id: e.target.dataset.dealId,
                    fromStage: e.target.dataset.stage,
                    element: e.target
                };
                e.target.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/html', e.target.outerHTML);
            }
        });
        
        document.addEventListener('dragend', (e) => {
            if (e.target.classList.contains('deal-card')) {
                e.target.classList.remove('dragging');
                this.draggedDeal = null;
                
                // Remove all drop indicators
                document.querySelectorAll('.drop-indicator').forEach(el => {
                    el.classList.remove('drop-indicator');
                });
            }
        });
    }
    
    addDropZoneListeners() {
        document.addEventListener('dragover', (e) => {
            const stageDeals = e.target.closest('.stage-deals');
            if (stageDeals && this.draggedDeal) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                
                // Add visual indicators
                stageDeals.classList.add('drop-indicator');
                
                // Show validation feedback
                const targetStage = stageDeals.dataset.stage;
                this.showDropValidation(targetStage);
            }
        });
        
        document.addEventListener('dragleave', (e) => {
            const stageDeals = e.target.closest('.stage-deals');
            if (stageDeals) {
                stageDeals.classList.remove('drop-indicator');
                this.hideDropValidation();
            }
        });
        
        document.addEventListener('drop', (e) => {
            const stageDeals = e.target.closest('.stage-deals');
            if (stageDeals && this.draggedDeal) {
                e.preventDefault();
                
                const targetStage = stageDeals.dataset.stage;
                const dealId = this.draggedDeal.id;
                const fromStage = this.draggedDeal.fromStage;
                
                if (targetStage !== fromStage) {
                    this.handleStageDrop(dealId, fromStage, targetStage);
                }
                
                // Clean up
                stageDeals.classList.remove('drop-indicator');
                this.hideDropValidation();
            }
        });
    }
    
    showDropValidation(targetStage) {
        const validation = this.validateStageTransition(this.draggedDeal.id, this.draggedDeal.fromStage, targetStage);
        
        const indicator = document.getElementById('drop-validation') || this.createDropValidationIndicator();
        
        if (validation.allowed) {
            indicator.className = 'drop-validation success';
            indicator.innerHTML = `<i class="fa fa-check-circle"></i> Move to ${targetStage}`;
        } else {
            indicator.className = 'drop-validation error';
            indicator.innerHTML = `<i class="fa fa-exclamation-circle"></i> ${validation.reason}`;
        }
        
        indicator.style.display = 'block';
    }
    
    hideDropValidation() {
        const indicator = document.getElementById('drop-validation');
        if (indicator) {
            indicator.style.display = 'none';
        }
    }
    
    createDropValidationIndicator() {
        const indicator = document.createElement('div');
        indicator.id = 'drop-validation';
        indicator.className = 'drop-validation';
        document.body.appendChild(indicator);
        return indicator;
    }
    
    handleStageDrop(dealId, fromStage, toStage) {
        // Show confirmation dialog if needed
        const validation = this.validateStageTransition(dealId, fromStage, toStage);
        
        if (!validation.allowed) {
            this.showErrorMessage(`Cannot move deal: ${validation.reason}`);
            return;
        }
        
        if (validation.warnings && validation.warnings.length > 0) {
            this.showTransitionConfirmation(dealId, fromStage, toStage, validation.warnings);
        } else {
            this.executeStageTransition(dealId, fromStage, toStage);
        }
    }
    
    showTransitionConfirmation(dealId, fromStage, toStage, warnings) {
        const modal = this.createConfirmationModal(dealId, fromStage, toStage, warnings);
        document.body.appendChild(modal);
        $(modal).modal('show');
    }
    
    createConfirmationModal(dealId, fromStage, toStage, warnings) {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Confirm Stage Transition</h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>Move deal from <strong>${fromStage}</strong> to <strong>${toStage}</strong>?</p>
                        
                        ${warnings.length > 0 ? `
                        <div class="alert alert-warning">
                            <h6>Warnings:</h6>
                            <ul>
                                ${warnings.map(w => `<li>${w}</li>`).join('')}
                            </ul>
                        </div>
                        ` : ''}
                        
                        <div class="form-group">
                            <label for="transition-reason">Reason (optional):</label>
                            <textarea id="transition-reason" class="form-control" rows="3" placeholder="Enter reason for stage transition..."></textarea>
                        </div>
                        
                        <div class="form-check">
                            <input type="checkbox" id="override-warnings" class="form-check-input">
                            <label for="override-warnings" class="form-check-label">
                                Override warnings and proceed
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="pipelineView.confirmStageTransition('${dealId}', '${fromStage}', '${toStage}', this)">
                            Confirm Move
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        return modal;
    }
    
    confirmStageTransition(dealId, fromStage, toStage, button) {
        const modal = button.closest('.modal');
        const reason = modal.querySelector('#transition-reason').value;
        const override = modal.querySelector('#override-warnings').checked;
        
        $(modal).modal('hide');
        
        this.executeStageTransition(dealId, fromStage, toStage, reason, override);
        
        // Clean up modal
        setTimeout(() => modal.remove(), 500);
    }
    
    executeStageTransition(dealId, fromStage, toStage, reason = '', override = false) {
        // Show loading indicator
        this.showLoadingIndicator(`Moving deal to ${toStage}...`);
        
        // Call API to execute transition
        this.apiCall('executeStageTransition', {
            dealId: dealId,
            fromStage: fromStage,
            toStage: toStage,
            reason: reason,
            override: override
        }).then(response => {
            if (response.success) {
                this.showSuccessMessage(`Deal moved to ${toStage} successfully`);
                this.refreshPipeline();
                
                // Trigger callback if provided
                if (this.callbacks.onStageTransition) {
                    this.callbacks.onStageTransition(dealId, fromStage, toStage, response);
                }
            } else {
                this.showErrorMessage(`Failed to move deal: ${response.message}`);
                this.refreshPipeline(); // Refresh to restore original state
            }
        }).catch(error => {
            this.showErrorMessage(`Error moving deal: ${error.message}`);
            this.refreshPipeline();
        }).finally(() => {
            this.hideLoadingIndicator();
        });
    }
    
    // Utility methods
    getDealsInStage(stageName) {
        return this.deals.filter(deal => deal.stage === stageName);
    }
    
    calculateStageValue(deals) {
        return deals.reduce((total, deal) => total + (parseFloat(deal.deal_value) || 0), 0);
    }
    
    getWIPStatus(stage, dealCount) {
        const wipLimit = stage.wip_limit;
        if (!wipLimit) {
            return { utilization: 0, exceeded: false, warning: false };
        }
        
        const utilization = Math.round((dealCount / wipLimit) * 100);
        
        return {
            utilization: utilization,
            exceeded: dealCount > wipLimit,
            warning: utilization >= 80 && dealCount <= wipLimit
        };
    }
    
    calculatePipelineMetrics() {
        const totalDeals = this.deals.filter(d => !['closed_won', 'closed_lost', 'unavailable'].includes(d.stage)).length;
        const totalValue = this.deals.reduce((sum, deal) => sum + (parseFloat(deal.deal_value) || 0), 0);
        const staleDeals = this.deals.filter(d => d.is_stale == 1).length;
        const avgDaysInStage = totalDeals > 0 ? Math.round(this.deals.reduce((sum, deal) => sum + (parseInt(deal.days_in_stage) || 0), 0) / totalDeals) : 0;
        
        // Calculate WIP violations
        let wipViolations = 0;
        this.stages.forEach(stage => {
            const dealsInStage = this.getDealsInStage(stage.name).length;
            if (stage.wip_limit && dealsInStage > stage.wip_limit) {
                wipViolations++;
            }
        });
        
        return {
            totalDeals: totalDeals,
            totalValue: totalValue,
            avgDaysInStage: avgDaysInStage,
            stalePercentage: totalDeals > 0 ? Math.round((staleDeals / totalDeals) * 100) : 0,
            wipViolations: wipViolations
        };
    }
    
    calculateCardStaleness(deal) {
        const daysInStage = parseInt(deal.days_in_stage) || 0;
        const stage = this.stages.find(s => s.name === deal.stage);
        
        if (!stage) return { status: 'normal' };
        
        if (stage.critical_days && daysInStage >= stage.critical_days) {
            return {
                status: 'critical',
                indicator: '🚨',
                reason: `${daysInStage} days (critical: ${stage.critical_days})`
            };
        } else if (stage.warning_days && daysInStage >= stage.warning_days) {
            return {
                status: 'warning',
                indicator: '⚠️',
                reason: `${daysInStage} days (warning: ${stage.warning_days})`
            };
        }
        
        return { status: 'normal' };
    }
    
    getHealthStatus(healthScore) {
        const score = parseInt(healthScore) || 0;
        
        if (score >= 80) {
            return { level: 'high', icon: '💚' };
        } else if (score >= 60) {
            return { level: 'medium', icon: '💛' };
        } else if (score >= 40) {
            return { level: 'low', icon: '🧡' };
        } else {
            return { level: 'critical', icon: '❤️' };
        }
    }
    
    getProgressStatus(deal) {
        // Calculate progress based on completed activities, tasks, etc.
        // This would integrate with the actual checklist system
        const baseProgress = (parseInt(deal.probability) || 0);
        return {
            percentage: Math.min(100, baseProgress)
        };
    }
    
    hasRecentActivity(deal) {
        // This would check for recent activities
        // Placeholder logic
        return deal.last_activity && new Date(deal.last_activity) > new Date(Date.now() - 7 * 24 * 60 * 60 * 1000);
    }
    
    validateStageTransition(dealId, fromStage, toStage) {
        // This would call the validation API
        // For now, return a mock validation
        return {
            allowed: true,
            warnings: fromStage === 'sourcing' && toStage === 'due_diligence' ? ['Skipping multiple stages'] : [],
            reason: ''
        };
    }
    
    formatCurrency(value) {
        if (value >= 1000000000) {
            return (value / 1000000000).toFixed(1) + 'B';
        } else if (value >= 1000000) {
            return (value / 1000000).toFixed(1) + 'M';
        } else if (value >= 1000) {
            return (value / 1000).toFixed(1) + 'K';
        }
        return value.toLocaleString();
    }
    
    truncateText(text, maxLength) {
        if (!text) return '';
        return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
    }
    
    // Event handlers
    initializeEventHandlers() {
        // Refresh button
        document.getElementById('refresh-pipeline')?.addEventListener('click', () => {
            this.refreshPipeline();
        });
        
        // Filter and sort controls
        document.getElementById('pipeline-filter')?.addEventListener('change', (e) => {
            this.applyFilter(e.target.value);
        });
        
        document.getElementById('pipeline-sort')?.addEventListener('change', (e) => {
            this.applySorting(e.target.value);
        });
        
        // Auto-refresh toggle
        document.getElementById('auto-refresh')?.addEventListener('change', (e) => {
            if (e.target.checked) {
                this.startAutoRefresh();
            } else {
                this.stopAutoRefresh();
            }
        });
    }
    
    // Auto-refresh functionality
    startAutoRefresh() {
        this.stopAutoRefresh(); // Clear any existing interval
        this.refreshInterval = setInterval(() => {
            this.refreshPipeline(true); // Silent refresh
        }, 30000); // 30 seconds
    }
    
    stopAutoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
    }
    
    refreshPipeline(silent = false) {
        if (!silent) {
            this.showLoadingIndicator('Refreshing pipeline...');
        }
        
        this.apiCall('getPipelineData', {
            includeDeals: true,
            includeMetrics: true
        }).then(response => {
            this.deals = response.deals || [];
            this.stages = response.stages || this.stages;
            this.renderPipeline();
            
            if (!silent) {
                this.showSuccessMessage('Pipeline refreshed');
            }
            
            // Update last refresh time
            const lastUpdated = document.querySelector('.last-updated');
            if (lastUpdated) {
                lastUpdated.textContent = `Updated: ${new Date().toLocaleTimeString()}`;
            }
        }).catch(error => {
            if (!silent) {
                this.showErrorMessage('Failed to refresh pipeline');
            }
            console.error('Pipeline refresh failed:', error);
        }).finally(() => {
            if (!silent) {
                this.hideLoadingIndicator();
            }
        });
    }
    
    // API communication
    apiCall(action, data) {
        return fetch('index.php?module=Pipelines&action=AjaxHandler', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: action,
                data: data
            })
        }).then(response => response.json());
    }
    
    // UI helpers
    showLoadingIndicator(message) {
        // Implementation for loading indicator
        console.log('Loading:', message);
    }
    
    hideLoadingIndicator() {
        // Implementation to hide loading indicator
    }
    
    showSuccessMessage(message) {
        // Implementation for success toast
        console.log('Success:', message);
    }
    
    showErrorMessage(message) {
        // Implementation for error toast
        console.error('Error:', message);
    }
    
    // Modal methods
    showNewDealModal(stage) {
        if (this.callbacks.onNewDeal) {
            this.callbacks.onNewDeal(stage);
        }
    }
    
    showDealDetails(dealId) {
        if (this.callbacks.onShowDetails) {
            this.callbacks.onShowDetails(dealId);
        }
    }
    
    showStageHistory(dealId) {
        if (this.callbacks.onShowHistory) {
            this.callbacks.onShowHistory(dealId);
        }
    }
    
    showActivityLog(dealId) {
        if (this.callbacks.onShowActivity) {
            this.callbacks.onShowActivity(dealId);
        }
    }
    
    forceStageMove(dealId) {
        if (this.callbacks.onForceMove) {
            this.callbacks.onForceMove(dealId);
        }
    }
    
    // Filter and sort methods
    applyFilter(filterType) {
        // Implementation for filtering deals
        console.log('Applying filter:', filterType);
    }
    
    applySorting(sortType) {
        // Implementation for sorting deals
        console.log('Applying sort:', sortType);
    }
    
    // Cleanup
    destroy() {
        this.stopAutoRefresh();
        
        // Remove event listeners
        document.removeEventListener('dragstart', this.handleDragStart);
        document.removeEventListener('dragend', this.handleDragEnd);
        document.removeEventListener('dragover', this.handleDragOver);
        document.removeEventListener('drop', this.handleDrop);
        
        // Clear container
        const container = document.getElementById(this.containerId);
        if (container) {
            container.innerHTML = '';
        }
    }
}

// Global instance for access from inline event handlers
window.pipelineView = null;

// Initialize pipeline view when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // This would be initialized with actual data from the server
    const config = {
        stages: window.pipelineStages || [],
        deals: window.pipelineDeals || [],
        wipLimits: window.wipLimits || {},
        currentUser: window.currentUser || {},
        permissions: window.pipelinePermissions || {},
        containerId: 'pipeline-container',
        callbacks: {
            onStageTransition: function(dealId, fromStage, toStage, response) {
                console.log('Stage transition completed:', dealId, fromStage, '->', toStage);
            },
            onNewDeal: function(stage) {
                window.location = `index.php?module=mdeal_Deals&action=EditView&stage=${stage}`;
            },
            onShowDetails: function(dealId) {
                // Show deal details modal or navigate to detail view
            },
            onShowHistory: function(dealId) {
                // Show stage transition history
            },
            onShowActivity: function(dealId) {
                // Show activity log
            },
            onForceMove: function(dealId) {
                // Show force move modal
            }
        }
    };
    
    window.pipelineView = new PipelineKanbanView(config);
});