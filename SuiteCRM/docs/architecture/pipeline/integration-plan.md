# Pipeline Integration Plan with Deals Module

## Overview

This document outlines the integration strategy for adding the Pipeline view to the existing Deals module in SuiteCRM, ensuring minimal disruption while maximizing functionality.

## Integration Strategy

### 1. Module Extension Approach

Rather than creating a separate module, we'll extend the existing Deals module with pipeline functionality:

```
SuiteCRM/modules/Deals/
├── views/
│   ├── view.pipeline.php          # New pipeline view
│   └── view.pipelineapi.php      # Pipeline API handler
├── js/
│   └── pipeline/                  # Pipeline JavaScript files
├── tpls/
│   └── pipeline/                  # Pipeline templates
└── Pipeline/                      # Pipeline-specific classes
    ├── PipelineManager.php
    ├── StageTransition.php
    └── DealCard.php
```

### 2. Database Integration

#### 2.1 Backward Compatibility
- Keep existing `status` field for legacy views
- Add new `pipeline_stage` field mapped to status
- Synchronize both fields during transition period

```php
// In Deal.php save() method
public function save($check_notify = false) {
    // Sync pipeline_stage with status
    if (!empty($this->pipeline_stage)) {
        $this->status = $this->mapPipelineToStatus($this->pipeline_stage);
    } elseif (!empty($this->status)) {
        $this->pipeline_stage = $this->mapStatusToPipeline($this->status);
    }
    
    return parent::save($check_notify);
}
```

#### 2.2 Migration Script
```php
// One-time migration script
class MigrateDealsToPipeline {
    public function run() {
        $sql = "UPDATE deals SET 
                pipeline_stage = CASE 
                    WHEN status = 'New' THEN 'sourcing'
                    WHEN status = 'Assigned' THEN 'initial_outreach'
                    WHEN status = 'In Process' THEN 'qualified'
                    -- ... other mappings
                END,
                stage_entered_date = COALESCE(date_in_current_stage, date_modified)
                WHERE deleted = 0";
        
        $GLOBALS['db']->query($sql);
    }
}
```

### 3. Menu Integration

#### 3.1 Add Pipeline View to Module Menu
```php
// In modules/Deals/Menu.php
if (ACLController::checkAccess('Deals', 'list', true)) {
    $module_menu[] = array(
        "index.php?module=Deals&action=index",
        $mod_strings['LBL_LIST_FORM_TITLE'],
        'List'
    );
    
    // Add Pipeline view
    $module_menu[] = array(
        "index.php?module=Deals&action=pipeline",
        $mod_strings['LBL_PIPELINE_VIEW'],
        'Pipeline'
    );
}
```

#### 3.2 Add Global Shortcut
```php
// In custom/application/Ext/GlobalLinks/pipeline_link.php
$global_control_links['pipeline'] = array(
    'linkinfo' => array(
        'Pipeline' => 'index.php?module=Deals&action=pipeline'
    ),
    'submenu' => ''
);
```

### 4. View Integration

#### 4.1 Pipeline View Controller
```php
// modules/Deals/views/view.pipeline.php
class DealsViewPipeline extends ViewBase {
    
    public function preDisplay() {
        parent::preDisplay();
        
        // Load pipeline CSS and JS
        $this->ss->assign('pipeline_css', array(
            'modules/Deals/css/pipeline.css',
            'modules/Deals/css/pipeline-cards.css'
        ));
        
        $this->ss->assign('pipeline_js', array(
            'modules/Deals/js/pipeline/pipeline.js',
            'modules/Deals/js/pipeline/drag-drop.js',
            'modules/Deals/js/pipeline/api-client.js'
        ));
    }
    
    public function display() {
        // Load pipeline stages
        $pipelineManager = new PipelineManager();
        $stages = $pipelineManager->getStages();
        $deals = $pipelineManager->getDealsByStage();
        
        $this->ss->assign('stages', $stages);
        $this->ss->assign('deals', $deals);
        $this->ss->assign('user_prefs', $this->getUserPreferences());
        
        echo $this->ss->display('modules/Deals/tpls/pipeline/pipeline.tpl');
    }
}
```

### 5. API Integration

#### 5.1 REST API Endpoints
```php
// custom/modules/Deals/clients/base/api/PipelineApi.php
class PipelineApi extends SugarApi {
    
    public function registerApiRest() {
        return array(
            'moveCard' => array(
                'reqType' => 'POST',
                'path' => array('Deals', 'pipeline', 'move'),
                'pathVars' => array('module', 'pipeline', 'action'),
                'method' => 'moveCard',
                'shortHelp' => 'Move deal to different pipeline stage'
            ),
            'getStageMetrics' => array(
                'reqType' => 'GET',
                'path' => array('Deals', 'pipeline', 'metrics'),
                'pathVars' => array('module', 'pipeline', 'action'),
                'method' => 'getStageMetrics',
                'shortHelp' => 'Get pipeline stage metrics'
            ),
            'updatePreferences' => array(
                'reqType' => 'PUT',
                'path' => array('Deals', 'pipeline', 'preferences'),
                'pathVars' => array('module', 'pipeline', 'action'),
                'method' => 'updateUserPreferences',
                'shortHelp' => 'Update user pipeline preferences'
            )
        );
    }
    
    public function moveCard($api, $args) {
        $dealId = $args['deal_id'];
        $newStage = $args['new_stage'];
        $position = $args['position'];
        
        $deal = BeanFactory::getBean('Deals', $dealId);
        if (!$deal) {
            throw new SugarApiExceptionNotFound('Deal not found');
        }
        
        // Check WIP limits
        $pipelineManager = new PipelineManager();
        if (!$pipelineManager->canMoveToStage($newStage, $dealId)) {
            throw new SugarApiExceptionInvalidParameter('WIP limit exceeded');
        }
        
        // Move deal
        $oldStage = $deal->pipeline_stage;
        $deal->pipeline_stage = $newStage;
        $deal->wip_position = $position;
        $deal->save();
        
        // Log transition
        $pipelineManager->logTransition($dealId, $oldStage, $newStage);
        
        return array('success' => true);
    }
}
```

### 6. ACL Integration

#### 6.1 Pipeline-Specific Permissions
```php
// modules/Deals/acl/PipelineAccess.php
class PipelineAccess {
    
    public static function canViewPipeline($user = null) {
        return ACLController::checkAccess('Deals', 'list', true, 'module', $user);
    }
    
    public static function canMoveCards($user = null) {
        return ACLController::checkAccess('Deals', 'edit', true, 'module', $user);
    }
    
    public static function canOverrideWIPLimits($user = null) {
        // Check for admin or special role
        global $current_user;
        $user = $user ?: $current_user;
        return $user->isAdmin() || ACLRole::userHasRole($user->id, 'Pipeline Manager');
    }
}
```

### 7. Workflow Integration

#### 7.1 Stage Change Workflows
```php
// custom/modules/Deals/workflow/PipelineWorkflows.php
class PipelineWorkflows {
    
    public function onStageChange($bean, $event, $arguments) {
        if ($bean->pipeline_stage != $bean->fetched_row['pipeline_stage']) {
            // Trigger notifications
            $this->notifyStageChange($bean);
            
            // Update related records
            $this->updateRelatedRecords($bean);
            
            // Check for automated actions
            $this->checkAutomatedActions($bean);
        }
    }
    
    private function notifyStageChange($deal) {
        // Send email to assigned user
        $template = $this->getStageChangeTemplate($deal->pipeline_stage);
        $this->sendNotification($deal->assigned_user_id, $template, $deal);
    }
}
```

### 8. Reporting Integration

#### 8.1 Pipeline Reports
```php
// custom/modules/Deals/reports/PipelineReports.php
class PipelineReports {
    
    public function getPipelineVelocity($dateRange) {
        $sql = "SELECT 
                ps.name as stage_name,
                COUNT(DISTINCT dst.deal_id) as deals_moved,
                AVG(dst.time_in_previous_stage) as avg_time_hours
                FROM deal_stage_transitions dst
                JOIN pipeline_stages ps ON dst.to_stage = ps.stage_key
                WHERE dst.transition_date BETWEEN ? AND ?
                GROUP BY ps.stage_key
                ORDER BY ps.stage_order";
        
        return $this->db->fetchAll($sql, array($dateRange['start'], $dateRange['end']));
    }
    
    public function getConversionFunnel($dateRange) {
        // Calculate conversion rates between stages
    }
}
```

### 9. Mobile Integration

#### 9.1 Mobile-Specific Views
```javascript
// modules/Deals/js/pipeline/mobile-pipeline.js
class MobilePipeline {
    constructor() {
        this.currentStage = 0;
        this.initSwipeHandlers();
        this.initTouchDrag();
    }
    
    initSwipeHandlers() {
        // Swipe between stages
        this.hammer = new Hammer(this.container);
        this.hammer.on('swipeleft', () => this.nextStage());
        this.hammer.on('swiperight', () => this.prevStage());
    }
    
    initTouchDrag() {
        // Long press to start drag
        this.hammer.on('press', (e) => {
            this.startDrag(e.target);
        });
    }
}
```

### 10. Testing Integration

#### 10.1 Unit Tests
```php
// tests/unit/modules/Deals/PipelineTest.php
class PipelineTest extends SugarTestCase {
    
    public function testStageTransition() {
        $deal = SugarTestDealUtilities::createDeal();
        $deal->pipeline_stage = 'sourcing';
        $deal->save();
        
        $pipelineManager = new PipelineManager();
        $result = $pipelineManager->moveToStage($deal->id, 'qualified');
        
        $this->assertTrue($result);
        $this->assertEquals('qualified', $deal->pipeline_stage);
    }
    
    public function testWIPLimitEnforcement() {
        // Test WIP limit validation
    }
}
```

#### 10.2 Integration Tests
```javascript
// tests/integration/pipeline.spec.js
describe('Pipeline View', () => {
    it('should load pipeline with deals', async () => {
        await page.goto('/index.php?module=Deals&action=pipeline');
        await page.waitForSelector('.pipeline-board');
        
        const stages = await page.$$('.stage-column');
        expect(stages.length).toBe(11);
    });
    
    it('should drag deal between stages', async () => {
        const deal = await page.$('.deal-card[data-id="test-deal"]');
        const targetStage = await page.$('.stage-column[data-stage="qualified"]');
        
        await deal.dragAndDrop(targetStage);
        
        // Verify move
        const movedDeal = await page.$('.stage-column[data-stage="qualified"] .deal-card[data-id="test-deal"]');
        expect(movedDeal).toBeTruthy();
    });
});
```

### 11. Configuration Integration

#### 11.1 Admin Configuration
```php
// modules/Administration/PipelineSettings.php
class PipelineSettings extends SugarView {
    
    public function display() {
        // WIP limit configuration
        // Stage color customization
        // Default view preferences
        // Automation rules
    }
}
```

### 12. Upgrade Considerations

#### 12.1 Module Loader Manifest
```php
// manifest.php for upgrade package
$manifest = array(
    'acceptable_sugar_versions' => array(
        'regex_matches' => array('7\\.*', '8\\.*')
    ),
    'copy' => array(
        array(
            'from' => '<basepath>/modules/Deals/views/view.pipeline.php',
            'to' => 'modules/Deals/views/view.pipeline.php',
        ),
        // ... other files
    ),
    'post_install' => array(
        '<basepath>/scripts/post_install.php',
    ),
);
```

### 13. Performance Monitoring

```php
// custom/modules/Deals/PipelineMonitor.php
class PipelineMonitor {
    
    public function logPerformance($action, $duration, $metadata = array()) {
        $log = array(
            'action' => $action,
            'duration_ms' => $duration,
            'user_id' => $GLOBALS['current_user']->id,
            'timestamp' => time(),
            'metadata' => $metadata
        );
        
        // Log to performance table or monitoring service
        $this->writeToLog($log);
    }
}
```

## Implementation Timeline

1. **Week 1-2**: Database schema and migration
2. **Week 3-4**: Core pipeline view and drag-drop
3. **Week 5-6**: API integration and workflows
4. **Week 7-8**: Testing and performance optimization
5. **Week 9-10**: Documentation and training

## Rollback Plan

In case of issues:

1. Pipeline view can be disabled via config
2. Database changes are non-destructive
3. Original Deal views remain unchanged
4. Simple SQL script to remove pipeline data if needed