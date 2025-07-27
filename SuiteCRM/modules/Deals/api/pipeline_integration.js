/**
 * Pipeline API Integration Example
 * 
 * Shows how to integrate the Pipeline API with the JavaScript pipeline view
 */

class PipelineAPIClient {
    constructor(baseUrl, authToken) {
        this.baseUrl = baseUrl || '/rest/v11_1';
        this.authToken = authToken;
        this.headers = {
            'Content-Type': 'application/json',
            'OAuth-Token': this.authToken
        };
    }

    /**
     * Get all pipeline stages with counts
     */
    async getStages() {
        try {
            const response = await fetch(`${this.baseUrl}/Deals/pipeline/stages`, {
                method: 'GET',
                headers: this.headers
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('Error fetching stages:', error);
            throw error;
        }
    }

    /**
     * Get deals for a specific stage
     */
    async getDeals(stage = null, offset = 0, limit = 20) {
        try {
            const params = new URLSearchParams({
                offset: offset,
                limit: limit
            });
            
            if (stage) {
                params.append('stage', stage);
            }
            
            const response = await fetch(`${this.baseUrl}/Deals/pipeline/deals?${params}`, {
                method: 'GET',
                headers: this.headers
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('Error fetching deals:', error);
            throw error;
        }
    }

    /**
     * Move a deal to a different stage
     */
    async moveDeal(dealId, newStage) {
        try {
            const response = await fetch(`${this.baseUrl}/Deals/pipeline/move`, {
                method: 'POST',
                headers: this.headers,
                body: JSON.stringify({
                    deal_id: dealId,
                    new_stage: newStage
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('Error moving deal:', error);
            throw error;
        }
    }

    /**
     * Toggle focus flag on a deal
     */
    async toggleFocus(dealId) {
        try {
            const response = await fetch(`${this.baseUrl}/Deals/pipeline/focus`, {
                method: 'POST',
                headers: this.headers,
                body: JSON.stringify({
                    deal_id: dealId
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('Error toggling focus:', error);
            throw error;
        }
    }

    /**
     * Get pipeline metrics
     */
    async getMetrics() {
        try {
            const response = await fetch(`${this.baseUrl}/Deals/pipeline/metrics`, {
                method: 'GET',
                headers: this.headers
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('Error fetching metrics:', error);
            throw error;
        }
    }
}

// Example usage in pipeline.js
class PipelineView {
    constructor() {
        // Initialize API client with auth token from SuiteCRM
        this.api = new PipelineAPIClient('/rest/v11_1', this.getAuthToken());
        this.stages = [];
        this.deals = {};
        this.metrics = null;
    }

    /**
     * Get auth token from SuiteCRM session
     */
    getAuthToken() {
        // In a real implementation, this would get the OAuth token
        // from the SuiteCRM session or authentication system
        return window.SUGAR?.App?.api?.getOAuthToken() || 'your-oauth-token';
    }

    /**
     * Initialize the pipeline view
     */
    async init() {
        try {
            // Load stages
            const stagesResponse = await this.api.getStages();
            this.stages = stagesResponse.stages;
            
            // Load deals for each stage
            for (const stage of this.stages) {
                const dealsResponse = await this.api.getDeals(stage.id);
                this.deals[stage.id] = dealsResponse.records;
            }
            
            // Load metrics
            const metricsResponse = await this.api.getMetrics();
            this.metrics = metricsResponse.metrics;
            
            // Render the pipeline
            this.render();
        } catch (error) {
            console.error('Failed to initialize pipeline:', error);
            this.showError('Failed to load pipeline data');
        }
    }

    /**
     * Handle drag and drop to move deals between stages
     */
    async handleDealMove(dealId, fromStage, toStage) {
        try {
            // Show loading state
            this.showLoading(dealId);
            
            // Call API to move deal
            const result = await this.api.moveDeal(dealId, toStage);
            
            if (result.success) {
                // Update local state
                this.moveDealInState(dealId, fromStage, toStage);
                
                // Re-render affected stages
                this.renderStage(fromStage);
                this.renderStage(toStage);
                
                // Show success message
                this.showSuccess(`Deal moved to ${toStage}`);
                
                // Update metrics
                await this.updateMetrics();
            } else {
                throw new Error(result.message || 'Failed to move deal');
            }
        } catch (error) {
            console.error('Failed to move deal:', error);
            this.showError('Failed to move deal');
            
            // Revert visual changes
            this.revertDealMove(dealId, fromStage, toStage);
        }
    }

    /**
     * Handle focus toggle
     */
    async handleFocusToggle(dealId) {
        try {
            const result = await this.api.toggleFocus(dealId);
            
            if (result.success) {
                // Update visual state
                const dealElement = document.querySelector(`[data-deal-id="${dealId}"]`);
                if (dealElement) {
                    dealElement.classList.toggle('focused', result.focus);
                }
                
                // Show feedback
                this.showSuccess(result.message);
            }
        } catch (error) {
            console.error('Failed to toggle focus:', error);
            this.showError('Failed to update deal focus');
        }
    }

    /**
     * Load more deals for infinite scroll
     */
    async loadMoreDeals(stage, offset) {
        try {
            const response = await this.api.getDeals(stage, offset, 20);
            
            if (response.records.length > 0) {
                // Append to existing deals
                this.deals[stage].push(...response.records);
                
                // Render new deals
                this.renderDeals(stage, response.records);
                
                return response.has_more;
            }
            
            return false;
        } catch (error) {
            console.error('Failed to load more deals:', error);
            return false;
        }
    }

    /**
     * Update metrics display
     */
    async updateMetrics() {
        try {
            const response = await this.api.getMetrics();
            this.metrics = response.metrics;
            this.renderMetrics();
        } catch (error) {
            console.error('Failed to update metrics:', error);
        }
    }

    // Placeholder methods for UI updates
    render() {
        console.log('Rendering pipeline with', this.stages.length, 'stages');
    }

    renderStage(stageId) {
        console.log('Rendering stage:', stageId);
    }

    renderDeals(stageId, deals) {
        console.log('Rendering', deals.length, 'deals for stage:', stageId);
    }

    renderMetrics() {
        console.log('Rendering metrics:', this.metrics);
    }

    showLoading(dealId) {
        console.log('Showing loading for deal:', dealId);
    }

    showSuccess(message) {
        console.log('Success:', message);
    }

    showError(message) {
        console.error('Error:', message);
    }

    moveDealInState(dealId, fromStage, toStage) {
        // Move deal between stage arrays in local state
        const dealIndex = this.deals[fromStage].findIndex(d => d.id === dealId);
        if (dealIndex !== -1) {
            const deal = this.deals[fromStage].splice(dealIndex, 1)[0];
            deal.pipeline_stage = toStage;
            this.deals[toStage].push(deal);
        }
    }

    revertDealMove(dealId, fromStage, toStage) {
        // Revert the move in case of error
        this.moveDealInState(dealId, toStage, fromStage);
        this.renderStage(fromStage);
        this.renderStage(toStage);
    }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { PipelineAPIClient, PipelineView };
}