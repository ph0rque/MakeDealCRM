# Unified Deal & Portfolio Pipeline Architecture

## Overview

The Unified Deal & Portfolio Pipeline is a comprehensive Kanban-style visualization and management system for SuiteCRM's Deals module. It provides an intuitive drag-and-drop interface for managing deals through 11 predefined pipeline stages with automatic time-in-stage tracking and visual risk indicators.

## Architecture Components

### 1. Database Schema Design

#### 1.1 Pipeline Stages Configuration Table
```sql
CREATE TABLE pipeline_stages (
    id CHAR(36) NOT NULL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    stage_key VARCHAR(50) NOT NULL UNIQUE,
    stage_order INT NOT NULL,
    wip_limit INT DEFAULT NULL,
    color_code VARCHAR(7) DEFAULT '#1976d2',
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_by CHAR(36),
    date_created DATETIME,
    modified_by CHAR(36),
    date_modified DATETIME,
    deleted TINYINT(1) DEFAULT 0,
    INDEX idx_stage_order (stage_order, deleted),
    INDEX idx_stage_key (stage_key, deleted)
);
```

#### 1.2 Stage Transitions Tracking Table
```sql
CREATE TABLE deal_stage_transitions (
    id CHAR(36) NOT NULL PRIMARY KEY,
    deal_id CHAR(36) NOT NULL,
    from_stage VARCHAR(50),
    to_stage VARCHAR(50) NOT NULL,
    transition_date DATETIME NOT NULL,
    transition_by CHAR(36) NOT NULL,
    time_in_previous_stage INT DEFAULT 0,  -- in minutes
    notes TEXT,
    deleted TINYINT(1) DEFAULT 0,
    INDEX idx_deal_transitions (deal_id, transition_date),
    INDEX idx_stage_tracking (to_stage, transition_date),
    FOREIGN KEY (deal_id) REFERENCES deals(id) ON DELETE CASCADE
);
```

#### 1.3 Pipeline View Preferences Table
```sql
CREATE TABLE pipeline_user_preferences (
    id CHAR(36) NOT NULL PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    show_archived TINYINT(1) DEFAULT 0,
    show_focus_only TINYINT(1) DEFAULT 0,
    card_display_fields TEXT,  -- JSON array of fields to show
    sort_order VARCHAR(50) DEFAULT 'date_entered',
    filter_settings TEXT,  -- JSON object for saved filters
    collapsed_stages TEXT,  -- JSON array of collapsed stage keys
    date_modified DATETIME,
    deleted TINYINT(1) DEFAULT 0,
    UNIQUE KEY idx_user_prefs (user_id, deleted)
);
```

#### 1.4 Modifications to Existing Deals Table
```sql
-- Add new fields to deals table
ALTER TABLE deals ADD COLUMN pipeline_stage VARCHAR(50) DEFAULT 'sourcing';
ALTER TABLE deals ADD COLUMN stage_entered_date DATETIME;
ALTER TABLE deals ADD COLUMN time_in_stage INT DEFAULT 0;  -- cached value in hours
ALTER TABLE deals ADD COLUMN wip_position INT DEFAULT NULL;  -- position within stage
ALTER TABLE deals ADD COLUMN is_archived TINYINT(1) DEFAULT 0;

-- Add indexes for pipeline view
CREATE INDEX idx_pipeline_view ON deals(pipeline_stage, wip_position, deleted);
CREATE INDEX idx_stage_tracking ON deals(pipeline_stage, stage_entered_date, deleted);
CREATE INDEX idx_focus_deals ON deals(focus_c, pipeline_stage, deleted);
```

### 2. Pipeline Stages Definition

```php
// Pipeline stages configuration
$pipeline_stages = array(
    'sourcing' => array(
        'name' => 'Sourcing',
        'order' => 1,
        'wip_limit' => null,
        'description' => 'Initial deal identification and sourcing',
        'color' => '#9c27b0'
    ),
    'initial_outreach' => array(
        'name' => 'Initial Outreach',
        'order' => 2,
        'wip_limit' => 20,
        'description' => 'First contact and initial communication',
        'color' => '#673ab7'
    ),
    'qualified' => array(
        'name' => 'Qualified',
        'order' => 3,
        'wip_limit' => 15,
        'description' => 'Deal has been qualified and meets criteria',
        'color' => '#3f51b5'
    ),
    'meeting_scheduled' => array(
        'name' => 'Meeting Scheduled',
        'order' => 4,
        'wip_limit' => 10,
        'description' => 'Initial meeting has been scheduled',
        'color' => '#2196f3'
    ),
    'nda_executed' => array(
        'name' => 'NDA Executed',
        'order' => 5,
        'wip_limit' => 10,
        'description' => 'Non-disclosure agreement signed',
        'color' => '#03a9f4'
    ),
    'under_review' => array(
        'name' => 'Under Review',
        'order' => 6,
        'wip_limit' => 8,
        'description' => 'Deal is under detailed review',
        'color' => '#00bcd4'
    ),
    'loi_negotiations' => array(
        'name' => 'LOI Negotiations',
        'order' => 7,
        'wip_limit' => 5,
        'description' => 'Letter of Intent negotiations',
        'color' => '#009688'
    ),
    'under_loi' => array(
        'name' => 'Under LOI',
        'order' => 8,
        'wip_limit' => 5,
        'description' => 'Letter of Intent executed',
        'color' => '#4caf50'
    ),
    'due_diligence' => array(
        'name' => 'Due Diligence',
        'order' => 9,
        'wip_limit' => 3,
        'description' => 'Due diligence process',
        'color' => '#8bc34a'
    ),
    'closed' => array(
        'name' => 'Closed',
        'order' => 10,
        'wip_limit' => null,
        'description' => 'Deal successfully closed',
        'color' => '#4caf50',
        'is_terminal' => true
    ),
    'unavailable' => array(
        'name' => 'Unavailable',
        'order' => 11,
        'wip_limit' => null,
        'description' => 'Deal no longer available or lost',
        'color' => '#f44336',
        'is_terminal' => true
    )
);
```

### 3. Module Architecture

#### 3.1 File Structure
```
modules/Pipeline/
├── Pipeline.php                 # Main Pipeline controller
├── PipelineView.php            # Pipeline view class
├── PipelineAPI.php             # REST API endpoints
├── js/
│   ├── pipeline.js             # Main JavaScript controller
│   ├── pipeline-card.js        # Deal card component
│   ├── pipeline-column.js      # Stage column component
│   └── pipeline-drag.js        # Drag-and-drop handler
├── tpls/
│   ├── pipeline.tpl            # Main view template
│   ├── deal-card.tpl          # Deal card template
│   └── stage-column.tpl       # Stage column template
├── css/
│   └── pipeline.css           # Pipeline styles
├── language/
│   └── en_us.lang.php         # Language strings
└── metadata/
    └── pipelineviewdefs.php   # View definitions
```

#### 3.2 Core Classes

```php
// Pipeline.php - Main controller
class Pipeline extends SugarController {
    public function action_index() {
        $this->view = 'pipeline';
    }
    
    public function action_moveCard() {
        // Handle drag-and-drop card movement
    }
    
    public function action_updateWIPLimit() {
        // Update stage WIP limit
    }
    
    public function action_toggleFocus() {
        // Toggle focus flag on deal
    }
}

// PipelineView.php - View class
class ViewPipeline extends SugarView {
    public function display() {
        $this->loadPipelineData();
        $this->loadUserPreferences();
        parent::display();
    }
    
    private function loadPipelineData() {
        // Load deals grouped by stage
        // Calculate time-in-stage
        // Apply filters and sorting
    }
}

// PipelineAPI.php - REST endpoints
class PipelineAPI extends SugarApi {
    public function registerApiRest() {
        return array(
            'moveCard' => array(
                'reqType' => 'POST',
                'path' => array('Pipeline', 'moveCard'),
                'method' => 'moveCard',
            ),
            'getStageMetrics' => array(
                'reqType' => 'GET',
                'path' => array('Pipeline', 'metrics'),
                'method' => 'getStageMetrics',
            ),
        );
    }
}
```

### 4. UI/UX Design

#### 4.1 Layout Structure
```
┌─────────────────────────────────────────────────────────────────┐
│ Pipeline View                                    [Filters] [Settings] │
├─────────────────────────────────────────────────────────────────┤
│ Sourcing │ Initial │ Qualified │ Meeting │ NDA │ Review │ LOI → │
│ (∞)      │ Outreach│ (12/15)   │ Sched   │ Exec│ (6/8)  │      │
│          │ (18/20) │           │ (8/10)  │(7/10)│        │      │
├──────────┼─────────┼───────────┼─────────┼─────┼────────┼──────┤
│ ┌──────┐ │┌──────┐ │ ┌──────┐  │┌──────┐ │┌────┐│┌──────┐│      │
│ │Deal 1│ ││Deal 3│ │ │Deal 5│  ││Deal 8│ ││D 11│││Deal 13││      │
│ │$250K │ ││$180K │ │ │$420K │  ││$350K │ ││$90K│││$520K ⚡││     │
│ │5 days│ ││12 days│ │ │18 days⚠│││8 days│ ││3d  │││28 days││     │
│ └──────┘ │└──────┘ │ └──────┘  │└──────┘ │└────┘│└──────┘│      │
│          │┌──────┐ │ ┌──────┐  │┌──────┐ │┌────┐│        │      │
│ ┌──────┐ ││Deal 4│ │ │Deal 6│  ││Deal 9│ ││D 12││┌──────┐│      │
│ │Deal 2│ ││$320K │ │ │$180K │  ││$150K │ ││$200K│││Deal 14││     │
│ │$180K │ ││8 days│ │ │22 days⚠│││4 days│ ││15d⚠│││35 days🔥│    │
│ └──────┘ │└──────┘ │ └──────┘  │└──────┘ │└────┘│└──────┘│      │
│          │         │ ┌──────┐  │         │      │        │      │
│          │         │ │Deal 7│  │┌──────┐ │      │        │      │
│          │         │ │$280K │  ││Deal 10││      │        │      │
│          │         │ │32 days🔥│││12 days││      │        │      │
│          │         │ └──────┘  │└──────┘ │      │        │      │
└──────────┴─────────┴───────────┴─────────┴──────┴────────┴──────┘

Legend: ⚠ = 14-30 days (orange) | 🔥 = 30+ days (red) | ⚡ = Focus deal
```

#### 4.2 Deal Card Design
```html
<div class="deal-card [status-class]" data-deal-id="{id}">
    <div class="card-header">
        <span class="deal-name">{name}</span>
        <span class="focus-indicator">⚡</span>
    </div>
    <div class="card-body">
        <div class="deal-value">${value}</div>
        <div class="deal-source">{source}</div>
        <div class="time-in-stage [risk-class]">
            <i class="icon-clock"></i> {days} days
        </div>
    </div>
    <div class="card-footer">
        <span class="assigned-to">{assigned_user}</span>
        <span class="last-activity">{last_activity}</span>
    </div>
</div>
```

#### 4.3 Responsive Design Breakpoints
- **Desktop (1200px+)**: Show all columns horizontally scrollable
- **Tablet (768px-1199px)**: Show 4-5 columns, horizontal scroll
- **Mobile (< 768px)**: Single column view with stage selector

### 5. Integration Points

#### 5.1 Deals Module Integration
- Extend existing Deal bean with pipeline-specific methods
- Add pipeline view as new action in Deals module
- Maintain backward compatibility with existing views

#### 5.2 Workflow Integration
- Trigger stage transition workflows
- Send notifications on extended time-in-stage
- Auto-assign based on stage rules

#### 5.3 Reporting Integration
- Pipeline velocity metrics
- Stage conversion rates
- Time-in-stage analytics
- WIP limit compliance

### 6. Performance Considerations

#### 6.1 Database Optimization
- Indexed columns for fast pipeline queries
- Cached time-in-stage calculations
- Batch updates for drag-and-drop operations

#### 6.2 Frontend Optimization
- Virtual scrolling for large deal counts
- Lazy loading of deal details
- Debounced drag-and-drop updates
- Local state management for smooth UX

### 7. Security Considerations

- Row-level security for deal visibility
- Stage transition permissions
- WIP limit override permissions
- Audit logging for all transitions

### 8. API Endpoints

```
POST   /api/pipeline/move-card
GET    /api/pipeline/deals?stage={stage}
GET    /api/pipeline/metrics
PUT    /api/pipeline/stages/{id}/wip-limit
POST   /api/pipeline/deals/{id}/focus
GET    /api/pipeline/history/{deal_id}
```

### 9. Migration Strategy

1. Create new database tables
2. Migrate existing deal statuses to pipeline stages
3. Calculate initial time-in-stage from audit records
4. Set default WIP limits based on historical data
5. Train users on new interface

### 10. Future Enhancements

- Custom stage configuration per user/team
- AI-powered deal prioritization
- Predictive time-to-close analytics
- Integration with email/calendar for activity tracking
- Mobile app with offline support