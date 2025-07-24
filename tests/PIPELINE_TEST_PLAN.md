# Pipeline Feature Test Plan

## Test Coverage Overview

### 1. Unit Tests (PHPUnit)
- **Location**: `tests/unit/modules/Pipeline/`
- **Coverage Target**: 90%+ for all PHP classes

#### Core Classes to Test:
1. **PipelineController**
   - Stage management (11 stages)
   - Deal movement between stages
   - WIP limit enforcement
   - Time tracking
   - Stage metrics calculation

2. **PipelineStage**
   - Stage properties (name, color, WIP limit)
   - Deal capacity checks
   - Stage transition rules
   - Performance indicators

3. **PipelineDeal**
   - Deal properties and metadata
   - Stage history tracking
   - Time in stage calculations
   - Stale deal detection (7-day threshold)

4. **PipelineService**
   - Business logic layer
   - Bulk operations
   - Data validation
   - Event handling

### 2. Integration Tests
- **Location**: `tests/integration/pipeline/`
- **Framework**: PHPUnit with database transactions

#### Test Scenarios:
1. **Stage Transitions**
   - All 11 valid stage transitions
   - Invalid transition attempts
   - Bulk deal movements
   - Transaction rollback on failure

2. **WIP Limits**
   - Enforcement across all stages
   - Override permissions
   - Warning messages
   - Capacity calculations

3. **Time Tracking**
   - Accurate time-in-stage calculations
   - Stage history persistence
   - Timezone handling
   - Historical reporting

4. **Data Persistence**
   - Database state consistency
   - Concurrent user updates
   - Audit trail integrity
   - Performance with large datasets

### 3. End-to-End Tests (Playwright)
- **Location**: `tests/e2e/pipeline/`
- **Coverage**: User workflows and UI interactions

#### Test Scenarios:
1. **Drag and Drop**
   - Desktop drag operations
   - Touch gestures on mobile
   - Multi-select and bulk drag
   - Drag validation and feedback
   - Undo/redo functionality

2. **Visual Feedback**
   - Loading states
   - Error messages
   - Success notifications
   - Stale deal indicators
   - WIP limit warnings

3. **Responsive Design**
   - Desktop (1920x1080, 1366x768)
   - Tablet (768x1024)
   - Mobile (375x667, 414x896)
   - Orientation changes
   - Touch gesture support

4. **Performance**
   - Load time < 2s
   - Smooth animations (60fps)
   - No lag with 500+ deals
   - Efficient DOM updates
   - Memory leak prevention

### 4. API Tests
- **Location**: `tests/api/pipeline/`
- **Framework**: PHPUnit with HTTP client

#### Endpoints to Test:
1. **GET /pipeline/stages**
   - List all stages with metrics
   - Filter and pagination
   - Response time < 200ms

2. **POST /pipeline/move-deal**
   - Move single deal
   - Validate stage transitions
   - Check WIP limits
   - Update timestamps

3. **POST /pipeline/bulk-move**
   - Move multiple deals
   - Transaction handling
   - Partial failure scenarios
   - Performance with 100+ deals

4. **GET /pipeline/metrics**
   - Stage conversion rates
   - Average time in stage
   - Deal velocity
   - Historical trends

### 5. Performance Tests
- **Location**: `tests/performance/`
- **Tools**: PHPUnit + custom benchmarks

#### Benchmarks:
1. **Load Testing**
   - 500 concurrent users
   - 10,000 deals across stages
   - Sub-second response times
   - Database query optimization

2. **Memory Usage**
   - Frontend memory profiling
   - Backend memory limits
   - Garbage collection
   - Resource cleanup

3. **Scalability**
   - Horizontal scaling readiness
   - Cache effectiveness
   - Query performance
   - CDN integration

## Test Data Requirements

### Fixtures Needed:
1. **Deals**
   - 1,000 test deals
   - Various stages and states
   - Realistic data distribution
   - Edge cases (nulls, special chars)

2. **Users**
   - Admin, Manager, Sales Rep roles
   - Different permissions
   - Team hierarchies
   - Territory assignments

3. **Stage Configurations**
   - Default 11-stage setup
   - Custom stage scenarios
   - Various WIP limits
   - Different color schemes

## Mobile Testing Requirements

### Devices to Test:
1. **iOS**
   - iPhone 14 Pro (latest)
   - iPhone SE (small screen)
   - iPad Pro (tablet)

2. **Android**
   - Pixel 7 (latest)
   - Samsung Galaxy S22
   - Budget devices (< $200)

### Touch Gestures:
- Long press to select
- Swipe to move
- Pinch to zoom
- Double tap for details
- Pull to refresh

## Accessibility Testing

### WCAG 2.1 Compliance:
- Keyboard navigation
- Screen reader support
- Color contrast (4.5:1 minimum)
- Focus indicators
- ARIA labels

## Security Testing

### Areas to Cover:
1. **Authorization**
   - Role-based access
   - Deal ownership
   - Stage permissions
   - API authentication

2. **Data Validation**
   - SQL injection prevention
   - XSS protection
   - CSRF tokens
   - Input sanitization

## Test Execution Strategy

### Priorities:
1. **Critical Path** (Run on every commit)
   - Core stage transitions
   - WIP limit enforcement
   - Basic drag and drop
   - API authentication

2. **Regression Suite** (Daily)
   - All unit tests
   - Integration tests
   - Key E2E scenarios
   - Performance benchmarks

3. **Full Suite** (Weekly)
   - All tests
   - Cross-browser testing
   - Mobile device testing
   - Security scans

## Success Criteria

### Coverage Targets:
- Unit Tests: 90%+ coverage
- Integration: All happy paths + edge cases
- E2E: Critical user journeys
- Performance: < 2s load, < 200ms API

### Quality Gates:
- Zero critical bugs
- < 5 minor issues
- All tests passing
- Performance within targets
- Accessibility compliant