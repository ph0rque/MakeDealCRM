# Pipeline Implementation Guide

## Overview
This guide provides step-by-step instructions for implementing the pipeline business logic rules defined in `pipeline-business-logic-rules.md`.

## Implementation Components

### 1. Database Schema Updates

```sql
-- Add pipeline-specific fields to deals table
ALTER TABLE deals ADD COLUMN stage_entered_date DATETIME;
ALTER TABLE deals ADD COLUMN days_in_stage INT DEFAULT 0;
ALTER TABLE deals ADD COLUMN is_stale BOOLEAN DEFAULT FALSE;
ALTER TABLE deals ADD COLUMN stale_reason VARCHAR(255);
ALTER TABLE deals ADD COLUMN wip_override BOOLEAN DEFAULT FALSE;
ALTER TABLE deals ADD COLUMN wip_override_reason TEXT;
ALTER TABLE deals ADD COLUMN auto_moved BOOLEAN DEFAULT FALSE;
ALTER TABLE deals ADD COLUMN auto_move_reason VARCHAR(255);

-- Create pipeline stages table
CREATE TABLE pipeline_stages (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    sort_order INT NOT NULL,
    wip_limit INT DEFAULT NULL,
    warning_days INT DEFAULT NULL,
    critical_days INT DEFAULT NULL,
    auto_move_rules TEXT,
    required_fields TEXT,
    email_templates TEXT,
    created_date DATETIME,
    modified_date DATETIME,
    deleted BOOLEAN DEFAULT FALSE
);

-- Create pipeline transitions log
CREATE TABLE pipeline_transitions (
    id CHAR(36) PRIMARY KEY,
    deal_id CHAR(36) NOT NULL,
    from_stage VARCHAR(100),
    to_stage VARCHAR(100) NOT NULL,
    transition_date DATETIME NOT NULL,
    transition_by CHAR(36) NOT NULL,
    transition_type ENUM('manual', 'automatic', 'override') DEFAULT 'manual',
    reason TEXT,
    created_date DATETIME,
    FOREIGN KEY (deal_id) REFERENCES deals(id),
    INDEX idx_deal_transitions (deal_id, transition_date)
);

-- Create WIP tracking table
CREATE TABLE pipeline_wip_tracking (
    id CHAR(36) PRIMARY KEY,
    stage VARCHAR(100) NOT NULL,
    user_id CHAR(36) NOT NULL,
    deal_count INT DEFAULT 0,
    wip_limit INT NOT NULL,
    last_updated DATETIME,
    INDEX idx_wip_stage_user (stage, user_id)
);
```

### 2. Core Pipeline Classes

#### PipelineManager.php
```php
<?php
namespace MakeDealCRM\modules\Pipelines;

class PipelineManager {
    
    protected $stages = [];
    protected $transitions = [];
    protected $wipLimits = [];
    
    /**
     * Initialize pipeline with configuration
     */
    public function __construct() {
        $this->loadStages();
        $this->loadTransitionRules();
        $this->loadWIPLimits();
    }
    
    /**
     * Validate stage transition
     */
    public function canTransition($deal, $fromStage, $toStage, $user) {
        // Check valid transition path
        if (!$this->isValidTransition($fromStage, $toStage)) {
            return ['allowed' => false, 'reason' => 'Invalid stage transition'];
        }
        
        // Check required fields
        $missingFields = $this->checkRequiredFields($deal, $toStage);
        if (!empty($missingFields)) {
            return [
                'allowed' => false, 
                'reason' => 'Missing required fields', 
                'fields' => $missingFields
            ];
        }
        
        // Check WIP limits
        if (!$this->checkWIPLimit($toStage, $user)) {
            return [
                'allowed' => false, 
                'reason' => 'WIP limit exceeded',
                'override_allowed' => true
            ];
        }
        
        return ['allowed' => true];
    }
    
    /**
     * Execute stage transition
     */
    public function transitionStage($deal, $toStage, $user, $reason = '') {
        $fromStage = $deal->stage;
        
        // Validate transition
        $validation = $this->canTransition($deal, $fromStage, $toStage, $user);
        if (!$validation['allowed'] && !$deal->wip_override) {
            throw new \Exception($validation['reason']);
        }
        
        // Execute pre-transition actions
        $this->executeExitActions($deal, $fromStage);
        
        // Update deal
        $deal->stage = $toStage;
        $deal->stage_entered_date = date('Y-m-d H:i:s');
        $deal->days_in_stage = 0;
        $deal->save();
        
        // Log transition
        $this->logTransition($deal, $fromStage, $toStage, $user, $reason);
        
        // Execute post-transition actions
        $this->executeEntryActions($deal, $toStage);
        
        // Update WIP tracking
        $this->updateWIPTracking($fromStage, $toStage, $user);
        
        return true;
    }
}
```

#### StaleDetector.php
```php
<?php
namespace MakeDealCRM\modules\Pipelines;

class StaleDetector {
    
    protected $thresholds = [];
    
    /**
     * Check all deals for staleness
     */
    public function checkStaleDeals() {
        $deals = $this->getActiveDeals();
        $staleDeals = [];
        
        foreach ($deals as $deal) {
            $staleness = $this->calculateStaleness($deal);
            
            if ($staleness['status'] !== 'fresh') {
                $deal->is_stale = true;
                $deal->stale_reason = $staleness['reason'];
                $deal->save();
                
                // Execute escalation actions
                if ($staleness['action_required']) {
                    $this->executeEscalation($deal, $staleness);
                }
                
                $staleDeals[] = $deal;
            }
        }
        
        return $staleDeals;
    }
    
    /**
     * Calculate deal staleness
     */
    protected function calculateStaleness($deal) {
        $lastActivity = $this->getLastActivityDate($deal);
        $daysSinceActivity = $this->daysBetween($lastActivity, new DateTime());
        
        $threshold = $this->thresholds[$deal->stage] ?? null;
        if (!$threshold) {
            return ['status' => 'fresh'];
        }
        
        if ($daysSinceActivity < $threshold['warning']) {
            return ['status' => 'fresh'];
        } elseif ($daysSinceActivity < $threshold['critical']) {
            return [
                'status' => 'warning',
                'days' => $daysSinceActivity,
                'reason' => "No activity for {$daysSinceActivity} days"
            ];
        } else {
            return [
                'status' => 'critical',
                'days' => $daysSinceActivity,
                'reason' => "Critical: No activity for {$daysSinceActivity} days",
                'action_required' => true,
                'action' => $threshold['action']
            ];
        }
    }
}
```

### 3. Automation Engine

#### AutomationEngine.php
```php
<?php
namespace MakeDealCRM\modules\Pipelines;

class AutomationEngine {
    
    protected $rules = [];
    protected $emailTemplates = [];
    
    /**
     * Process automation rules for a deal
     */
    public function processAutomation($deal) {
        // Check auto-move rules
        $this->checkAutoMoveRules($deal);
        
        // Process email automation
        $this->processEmailAutomation($deal);
        
        // Generate automatic tasks
        $this->generateStageTasks($deal);
    }
    
    /**
     * Check and execute auto-move rules
     */
    protected function checkAutoMoveRules($deal) {
        $rules = $this->getActiveRules($deal->stage);
        
        foreach ($rules as $rule) {
            if ($this->evaluateCondition($rule['condition'], $deal)) {
                // Check if delay has passed
                if ($this->checkDelay($rule, $deal)) {
                    $this->executeAutoMove($deal, $rule);
                }
            }
        }
    }
    
    /**
     * Send stage-specific emails
     */
    protected function processEmailAutomation($deal) {
        $templates = $this->getStageTemplates($deal->stage);
        
        foreach ($templates as $template) {
            if ($this->shouldSendEmail($template, $deal)) {
                $this->sendAutomatedEmail($deal, $template);
            }
        }
    }
    
    /**
     * Generate tasks on stage entry
     */
    protected function generateStageTasks($deal) {
        $tasks = $this->getStageTasks($deal->stage);
        
        foreach ($tasks as $taskTemplate) {
            $task = new Task();
            $task->name = $taskTemplate['name'];
            $task->parent_type = 'Deals';
            $task->parent_id = $deal->id;
            $task->assigned_user_id = $deal->assigned_user_id;
            $task->due_date = $this->calculateDueDate($taskTemplate['due']);
            $task->priority = $taskTemplate['priority'];
            $task->save();
        }
    }
}
```

### 4. UI Components

#### PipelineView.js
```javascript
// Pipeline Kanban View Component
class PipelineView {
    constructor(config) {
        this.stages = config.stages;
        this.deals = config.deals;
        this.wipLimits = config.wipLimits;
        this.currentUser = config.currentUser;
    }
    
    renderPipeline() {
        const container = document.getElementById('pipeline-container');
        
        this.stages.forEach(stage => {
            const column = this.createStageColumn(stage);
            const deals = this.getDealsInStage(stage.name);
            
            deals.forEach(deal => {
                const card = this.createDealCard(deal);
                column.appendChild(card);
            });
            
            container.appendChild(column);
        });
        
        this.initializeDragDrop();
    }
    
    createStageColumn(stage) {
        const column = document.createElement('div');
        column.className = 'pipeline-stage';
        column.dataset.stage = stage.name;
        
        // Add WIP indicator
        const wipStatus = this.getWIPStatus(stage);
        if (wipStatus.exceeded) {
            column.classList.add('wip-exceeded');
        }
        
        const header = `
            <div class="stage-header">
                <h3>${stage.name}</h3>
                <div class="stage-metrics">
                    <span class="deal-count">${wipStatus.count}</span>
                    ${wipStatus.limit ? `<span class="wip-limit">/ ${wipStatus.limit}</span>` : ''}
                </div>
            </div>
        `;
        
        column.innerHTML = header;
        return column;
    }
    
    createDealCard(deal) {
        const card = document.createElement('div');
        card.className = 'deal-card';
        card.dataset.dealId = deal.id;
        
        // Add staleness indicator
        const staleness = this.calculateStaleness(deal);
        if (staleness.status === 'warning') {
            card.classList.add('stale-warning');
        } else if (staleness.status === 'critical') {
            card.classList.add('stale-critical');
        }
        
        // Add progress indicator
        const progress = deal.checklist_progress || 0;
        
        card.innerHTML = `
            <div class="deal-header">
                <h4>${deal.name}</h4>
                <span class="deal-value">$${this.formatCurrency(deal.deal_value)}</span>
            </div>
            <div class="deal-meta">
                <span class="days-in-stage">${deal.days_in_stage}d</span>
                <span class="progress">${progress}%</span>
            </div>
            <div class="deal-indicators">
                ${staleness.days ? `<span class="stale-indicator" title="${staleness.reason}">⚠️ ${staleness.days}d</span>` : ''}
            </div>
        `;
        
        return card;
    }
}
```

### 5. Scheduled Jobs

#### PipelineMaintenanceJob.php
```php
<?php
namespace MakeDealCRM\modules\Pipelines;

class PipelineMaintenanceJob {
    
    /**
     * Run daily pipeline maintenance
     */
    public function run() {
        // Update days in stage
        $this->updateDaysInStage();
        
        // Check for stale deals
        $staleDetector = new StaleDetector();
        $staleDeals = $staleDetector->checkStaleDeals();
        
        // Process automation rules
        $automation = new AutomationEngine();
        $deals = $this->getActiveDeals();
        
        foreach ($deals as $deal) {
            $automation->processAutomation($deal);
        }
        
        // Send notifications
        $this->sendStaleNotifications($staleDeals);
        
        // Update WIP tracking
        $this->updateWIPCounts();
        
        return [
            'deals_processed' => count($deals),
            'stale_deals' => count($staleDeals),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Update days in stage for all active deals
     */
    protected function updateDaysInStage() {
        $sql = "UPDATE deals 
                SET days_in_stage = DATEDIFF(NOW(), stage_entered_date) 
                WHERE deleted = 0 
                AND stage NOT IN ('closed_won', 'closed_lost', 'unavailable')";
        
        $this->db->query($sql);
    }
}
```

## Testing Strategy

### 1. Unit Tests
```php
class PipelineManagerTest extends PHPUnit_Framework_TestCase {
    
    public function testValidTransition() {
        $manager = new PipelineManager();
        $deal = $this->createMockDeal('screening');
        
        $result = $manager->canTransition($deal, 'screening', 'analysis_outreach', $this->user);
        $this->assertTrue($result['allowed']);
    }
    
    public function testWIPLimitEnforcement() {
        $manager = new PipelineManager();
        // Set up scenario where WIP limit is reached
        
        $result = $manager->canTransition($deal, 'sourcing', 'due_diligence', $this->user);
        $this->assertFalse($result['allowed']);
        $this->assertEquals('WIP limit exceeded', $result['reason']);
    }
}
```

### 2. Integration Tests
```javascript
describe('Pipeline Drag and Drop', () => {
    it('should validate stage transition on drop', async () => {
        // Test drag from Sourcing to Due Diligence
        // Should fail without required fields
    });
    
    it('should show WIP warning on limit breach', async () => {
        // Test moving deal to stage at WIP limit
        // Should show warning modal
    });
});
```

## Deployment Checklist

1. **Database Migration**
   - [ ] Run schema updates
   - [ ] Migrate existing deals data
   - [ ] Set initial stage_entered_date

2. **Configuration**
   - [ ] Configure stage definitions
   - [ ] Set WIP limits per stage
   - [ ] Define stale thresholds
   - [ ] Create email templates

3. **Permissions**
   - [ ] Set stage transition permissions
   - [ ] Configure override capabilities
   - [ ] Define notification recipients

4. **Testing**
   - [ ] Unit test coverage > 80%
   - [ ] Integration tests passing
   - [ ] User acceptance testing
   - [ ] Performance benchmarks

5. **Training**
   - [ ] User documentation
   - [ ] Admin configuration guide
   - [ ] Video tutorials
   - [ ] Quick reference cards

## Monitoring & Optimization

### Key Metrics to Track
- Average time in stage
- Conversion rates between stages
- WIP limit breaches
- Stale deal counts
- Automation success rates

### Performance Optimization
- Index stage and date fields
- Cache WIP calculations
- Batch automation processing
- Async email sending
- Progressive UI loading