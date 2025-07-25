# Pipeline UI/UX Design Specification

## Visual Design System

### Color Palette

#### Stage Colors
- **Sourcing**: #9c27b0 (Deep Purple)
- **Initial Outreach**: #673ab7 (Deep Purple)
- **Qualified**: #3f51b5 (Indigo)
- **Meeting Scheduled**: #2196f3 (Blue)
- **NDA Executed**: #03a9f4 (Light Blue)
- **Under Review**: #00bcd4 (Cyan)
- **LOI Negotiations**: #009688 (Teal)
- **Under LOI**: #4caf50 (Green)
- **Due Diligence**: #8bc34a (Light Green)
- **Closed**: #4caf50 (Green)
- **Unavailable**: #f44336 (Red)

#### Risk Indicators
- **Normal** (0-13 days): Default card color
- **Warning** (14-29 days): #ff9800 (Orange) border/indicator
- **Critical** (30+ days): #f44336 (Red) border/indicator

#### UI Elements
- **Background**: #f5f5f5 (Light grey)
- **Card Background**: #ffffff (White)
- **Card Shadow**: rgba(0,0,0,0.1)
- **Text Primary**: #212121
- **Text Secondary**: #757575
- **Focus Indicator**: #ffc107 (Amber)
- **Drag Active**: rgba(33, 150, 243, 0.2)

### Typography

```css
/* Headers */
.pipeline-header {
    font-family: 'Roboto', 'Helvetica Neue', sans-serif;
    font-size: 24px;
    font-weight: 500;
    color: #212121;
}

.stage-header {
    font-family: 'Roboto', 'Helvetica Neue', sans-serif;
    font-size: 16px;
    font-weight: 600;
    color: #ffffff;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Deal Cards */
.deal-name {
    font-size: 14px;
    font-weight: 500;
    color: #212121;
    line-height: 1.3;
}

.deal-value {
    font-size: 18px;
    font-weight: 600;
    color: #1976d2;
}

.deal-meta {
    font-size: 12px;
    color: #757575;
}
```

## Component Specifications

### 1. Pipeline Container

```html
<div class="pipeline-container">
    <div class="pipeline-header">
        <h1>Deal Pipeline</h1>
        <div class="pipeline-controls">
            <button class="btn-filter">
                <i class="icon-filter"></i> Filters
            </button>
            <button class="btn-focus-only">
                <i class="icon-star"></i> Focus Only
            </button>
            <button class="btn-settings">
                <i class="icon-settings"></i> Settings
            </button>
        </div>
    </div>
    <div class="pipeline-metrics">
        <!-- Summary metrics bar -->
    </div>
    <div class="pipeline-board">
        <!-- Stage columns -->
    </div>
</div>
```

### 2. Stage Column Design

```html
<div class="stage-column" data-stage="sourcing">
    <div class="stage-header" style="background-color: #9c27b0;">
        <span class="stage-name">Sourcing</span>
        <span class="stage-count">(12)</span>
        <span class="wip-indicator">∞</span>
        <button class="stage-menu">⋮</button>
    </div>
    <div class="stage-body">
        <div class="stage-dropzone">
            <!-- Deal cards -->
        </div>
    </div>
    <div class="stage-footer">
        <span class="stage-value">$2.4M</span>
    </div>
</div>

/* CSS */
.stage-column {
    width: 300px;
    min-height: calc(100vh - 200px);
    background: #f9f9f9;
    border-radius: 4px;
    margin-right: 16px;
    display: flex;
    flex-direction: column;
}

.stage-header {
    padding: 12px 16px;
    color: white;
    border-radius: 4px 4px 0 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 10;
}

.stage-body {
    flex: 1;
    padding: 8px;
    overflow-y: auto;
    overflow-x: hidden;
}

.stage-dropzone {
    min-height: 100px;
}

.stage-dropzone.drag-over {
    background: rgba(33, 150, 243, 0.1);
    border: 2px dashed #2196f3;
}
```

### 3. Deal Card Design

```html
<div class="deal-card" draggable="true" data-deal-id="123">
    <div class="card-risk-indicator warning"></div>
    <div class="card-header">
        <span class="deal-name">Acme Corp Acquisition</span>
        <span class="focus-indicator">⚡</span>
    </div>
    <div class="card-body">
        <div class="deal-primary">
            <span class="deal-value">$250,000</span>
        </div>
        <div class="deal-secondary">
            <span class="deal-source">
                <i class="icon-source"></i> Broker
            </span>
            <span class="deal-owner">
                <i class="icon-user"></i> J. Smith
            </span>
        </div>
    </div>
    <div class="card-footer">
        <div class="time-indicator warning">
            <i class="icon-clock"></i>
            <span>18 days</span>
        </div>
        <div class="card-actions">
            <button class="btn-quick-action" title="Add Note">
                <i class="icon-note"></i>
            </button>
            <button class="btn-quick-action" title="Schedule Activity">
                <i class="icon-calendar"></i>
            </button>
        </div>
    </div>
</div>

/* CSS */
.deal-card {
    background: white;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.12);
    margin-bottom: 8px;
    cursor: move;
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
}

.deal-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    transform: translateY(-2px);
}

.deal-card.dragging {
    opacity: 0.5;
    transform: rotate(2deg);
}

.card-risk-indicator {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
}

.card-risk-indicator.warning {
    background: #ff9800;
}

.card-risk-indicator.critical {
    background: #f44336;
}

.card-header {
    padding: 12px 12px 8px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.focus-indicator {
    color: #ffc107;
    font-size: 16px;
}

.deal-value {
    font-size: 20px;
    font-weight: 600;
    color: #1976d2;
}

.time-indicator {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    padding: 4px 8px;
    border-radius: 12px;
    background: #f5f5f5;
}

.time-indicator.warning {
    background: #fff3e0;
    color: #e65100;
}

.time-indicator.critical {
    background: #ffebee;
    color: #c62828;
}
```

### 4. Drag and Drop Behavior

```javascript
// Visual feedback during drag
.pipeline-board.dragging .stage-dropzone {
    transition: background-color 0.2s ease;
}

.stage-dropzone.can-drop {
    background: rgba(76, 175, 80, 0.1);
    border: 2px solid #4caf50;
}

.stage-dropzone.cannot-drop {
    background: rgba(244, 67, 54, 0.1);
    border: 2px solid #f44336;
}

// WIP limit warning
.stage-column.at-limit .stage-header {
    animation: pulse-warning 2s infinite;
}

@keyframes pulse-warning {
    0% { box-shadow: 0 0 0 0 rgba(255, 152, 0, 0.7); }
    70% { box-shadow: 0 0 0 10px rgba(255, 152, 0, 0); }
    100% { box-shadow: 0 0 0 0 rgba(255, 152, 0, 0); }
}
```

### 5. Responsive Design

#### Desktop (1200px+)
- Show 4-6 columns visible at once
- Horizontal scroll for additional columns
- Full card details visible

#### Tablet (768px - 1199px)
- Show 2-3 columns
- Condensed card view
- Touch-optimized drag handles

#### Mobile (< 768px)
- Single column view
- Stage selector dropdown
- Swipe between stages
- Tap and hold to initiate drag

```css
/* Tablet */
@media (max-width: 1199px) {
    .stage-column {
        width: 280px;
    }
    
    .deal-card {
        font-size: 14px;
    }
    
    .deal-value {
        font-size: 18px;
    }
}

/* Mobile */
@media (max-width: 767px) {
    .pipeline-board {
        display: block;
        overflow: hidden;
    }
    
    .stage-column {
        width: 100%;
        margin: 0;
        display: none;
    }
    
    .stage-column.active {
        display: block;
    }
    
    .mobile-stage-selector {
        display: flex;
        overflow-x: auto;
        padding: 8px;
        background: white;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
}
```

### 6. Interactive Elements

#### Filter Panel
```html
<div class="filter-panel">
    <h3>Filter Deals</h3>
    <div class="filter-group">
        <label>Deal Value Range</label>
        <input type="range" min="0" max="5000000" />
    </div>
    <div class="filter-group">
        <label>Source</label>
        <select multiple>
            <option>Broker</option>
            <option>Direct</option>
            <option>Referral</option>
        </select>
    </div>
    <div class="filter-group">
        <label>Time in Stage</label>
        <select>
            <option>All</option>
            <option>Normal (< 14 days)</option>
            <option>Warning (14-30 days)</option>
            <option>Critical (> 30 days)</option>
        </select>
    </div>
</div>
```

#### Quick Actions Menu
```html
<div class="quick-actions-menu">
    <button class="action-item">
        <i class="icon-note"></i>
        Add Note
    </button>
    <button class="action-item">
        <i class="icon-task"></i>
        Create Task
    </button>
    <button class="action-item">
        <i class="icon-email"></i>
        Send Email
    </button>
    <button class="action-item">
        <i class="icon-calendar"></i>
        Schedule Meeting
    </button>
</div>
```

### 7. Loading States

```html
<!-- Skeleton loader for cards -->
<div class="deal-card skeleton">
    <div class="skeleton-line" style="width: 70%;"></div>
    <div class="skeleton-line" style="width: 40%;"></div>
    <div class="skeleton-line" style="width: 50%;"></div>
</div>

/* CSS */
.skeleton {
    background: #f5f5f5;
    pointer-events: none;
}

.skeleton-line {
    height: 16px;
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
    margin: 8px 12px;
    border-radius: 4px;
}

@keyframes loading {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
```

### 8. Animations and Transitions

```css
/* Stage transition */
.deal-card {
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Card enter animation */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.deal-card.new {
    animation: slideIn 0.3s ease-out;
}

/* Success feedback */
@keyframes successPulse {
    0% {
        box-shadow: 0 0 0 0 rgba(76, 175, 80, 0.7);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(76, 175, 80, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(76, 175, 80, 0);
    }
}

.deal-card.move-success {
    animation: successPulse 0.5s ease-out;
}
```

### 9. Accessibility Features

- **Keyboard Navigation**: Tab through stages, arrow keys to navigate cards
- **Screen Reader Support**: ARIA labels for all interactive elements
- **High Contrast Mode**: Alternative color scheme for better visibility
- **Focus Indicators**: Clear visual focus states for keyboard users
- **Drag Alternative**: Context menu option to move cards without dragging

### 10. Performance Optimizations

- **Virtual Scrolling**: Only render visible cards in long lists
- **Lazy Loading**: Load deal details on demand
- **Debounced Updates**: Batch drag operations to reduce server calls
- **CSS Containment**: Use `contain: layout` on columns for better performance
- **Web Workers**: Process large datasets off the main thread