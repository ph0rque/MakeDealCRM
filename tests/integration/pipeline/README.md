# Pipeline Integration Testing Suite

This comprehensive integration testing suite validates all aspects of the Pipeline feature across multiple dimensions: functionality, security, accessibility, performance, and state management.

## Test Coverage Overview

### 1. API Integration Tests (`PipelineApiIntegrationTest.php`)
**Purpose**: Validates all REST API endpoints with real HTTP requests

**Test Scenarios**:
- ✅ Pipeline stages endpoint performance (< 200ms)
- ✅ Deal movement with validation and WIP limits
- ✅ Bulk operations with partial failure handling
- ✅ Filtering and pagination
- ✅ Authentication and authorization
- ✅ Rate limiting enforcement
- ✅ Performance with large datasets (500+ deals)

**Key Features**:
- Real HTTP client testing with Guzzle
- Database integration and consistency checks
- Performance benchmarking
- Error scenario validation

### 2. Drag & Drop Integration Tests (`PipelineDragDropIntegrationTest.php`)
**Purpose**: Tests end-to-end drag-and-drop functionality across devices

**Test Scenarios**:
- ✅ Basic drag and drop with validation
- ✅ WIP limit enforcement with visual feedback
- ✅ Bulk selection and multi-drag operations
- ✅ Mobile touch gestures (long press, swipe)
- ✅ Stage transition validation
- ✅ Time tracking accuracy
- ✅ Stale deal alerts (8+ days threshold)
- ✅ Performance with 500+ deals

**Key Features**:
- WebDriver automation for real browser testing
- Touch event simulation for mobile
- Visual feedback validation
- Database consistency verification

### 3. Responsive Design Tests (`PipelineResponsiveIntegrationTest.php`)
**Purpose**: Validates adaptive layouts across different devices and orientations

**Test Scenarios**:
- ✅ Desktop layouts (1920x1080, 1366x768)
- ✅ Tablet layouts (1024x768, 768x1024)
- ✅ Mobile layouts (414x896, 375x667, 320x568)
- ✅ Touch gestures and swipe navigation
- ✅ Compact view toggle
- ✅ Keyboard navigation
- ✅ Performance across viewports
- ✅ Cross-browser consistency testing

**Key Features**:
- Multiple viewport testing
- Touch gesture simulation
- Performance measurement per viewport
- Orientation change handling

### 4. Accessibility Integration Tests (`PipelineAccessibilityIntegrationTest.php`)
**Purpose**: Ensures WCAG 2.1 AA compliance and inclusive design

**Test Scenarios**:
- ✅ Keyboard navigation (Tab, Arrow keys, Enter)
- ✅ Keyboard-initiated drag and drop (Ctrl+M)
- ✅ Focus indicators visibility
- ✅ ARIA labels and roles
- ✅ Color contrast ratios (4.5:1 minimum)
- ✅ Screen reader support
- ✅ Image alt text validation
- ✅ Form accessibility
- ✅ Accessible error handling

**Key Features**:
- WCAG 2.1 compliance validation
- Color contrast calculation
- Screen reader compatibility
- Keyboard-only interaction testing

### 5. Security Integration Tests (`PipelineSecurityIntegrationTest.php`)
**Purpose**: Validates security measures and prevents common vulnerabilities

**Test Scenarios**:
- ✅ Role-based access control (Admin, Manager, Sales Rep, Guest)
- ✅ Deal ownership security
- ✅ SQL injection prevention
- ✅ XSS (Cross-Site Scripting) prevention
- ✅ CSRF protection
- ✅ Input validation and sanitization
- ✅ Rate limiting
- ✅ Session security
- ✅ Data leakage prevention
- ✅ File upload security

**Key Features**:
- Real attack scenario simulation
- Multiple user role testing
- Security payload injection testing
- Data privacy validation

### 6. State Management Integration Tests (`PipelineStateManagementIntegrationTest.php`)
**Purpose**: Tests complex state scenarios and data consistency

**Test Scenarios**:
- ✅ Concurrent deal movement handling
- ✅ Batch operation consistency
- ✅ Error recovery and rollback
- ✅ Optimistic updates with confirmation
- ✅ WIP limit state management
- ✅ Focus order state management

**Key Features**:
- Concurrent operation testing
- Race condition handling
- Transaction rollback verification
- State synchronization validation

## Running the Tests

### Prerequisites

1. **PHP 8.0+** with extensions:
   - PDO
   - cURL
   - JSON
   - mbstring

2. **PHPUnit 10.0+**
   ```bash
   composer require --dev phpunit/phpunit ^10.0
   ```

3. **Docker** (for Selenium Grid and database)
   ```bash
   docker --version
   docker-compose --version
   ```

4. **Web browser drivers** (Chrome recommended)

### Quick Start

**Run all integration tests**:
```bash
cd tests
./run-comprehensive-integration-tests.sh
```

**Run with performance tests**:
```bash
./run-comprehensive-integration-tests.sh --performance
```

**Run individual test suites**:
```bash
# API tests only
phpunit --configuration ../phpunit.xml integration/pipeline/PipelineApiIntegrationTest.php

# Accessibility tests only
phpunit --configuration ../phpunit.xml integration/pipeline/PipelineAccessibilityIntegrationTest.php

# Security tests only
phpunit --configuration ../phpunit.xml integration/pipeline/PipelineSecurityIntegrationTest.php
```

### Continuous Integration

**GitHub Actions workflow** (`.github/workflows/integration-tests.yml`):
```yaml
name: Pipeline Integration Tests

on: [push, pull_request]

jobs:
  integration-tests:
    runs-on: ubuntu-latest
    
    services:
      selenium:
        image: selenium/standalone-chrome:latest
        ports:
          - 4444:4444
        options: --shm-size=2g
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          extensions: pdo, curl, json, mbstring
          
      - name: Install dependencies
        run: composer install --no-progress --prefer-dist --optimize-autoloader
        
      - name: Run integration tests
        run: |
          cd tests
          ./run-comprehensive-integration-tests.sh
          
      - name: Upload test results
        uses: actions/upload-artifact@v3
        if: always()
        with:
          name: test-results
          path: tests/reports/
```

## Test Configuration

### Environment Variables

Create `.env.testing` file:
```bash
# Database
DB_CONNECTION=sqlite
DB_DATABASE=:memory:

# Application
APP_ENV=testing
APP_DEBUG=true

# Testing
SELENIUM_HUB_URL=http://localhost:4444/wd/hub
TEST_BASE_URL=http://localhost:8080

# Browser Configuration
BROWSER_HEADLESS=true
BROWSER_TIMEOUT=30

# Performance Thresholds
API_RESPONSE_THRESHOLD=200
PAGE_LOAD_THRESHOLD=2000
DRAG_OPERATION_THRESHOLD=500
```

### PHPUnit Configuration

The tests use the main `phpunit.xml` configuration with these key settings:

```xml
<phpunit>
    <testsuites>
        <testsuite name="Pipeline Integration">
            <directory>tests/integration/pipeline</directory>
        </testsuite>
    </testsuites>
    
    <coverage>
        <report>
            <html outputDirectory="tests/coverage/html"/>
            <text outputFile="tests/coverage/coverage.txt"/>
        </report>
    </coverage>
    
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
    </php>
</phpunit>
```

## Test Data and Fixtures

### Test Data Generation

Tests use realistic data generators:

```php
// Deal factory with realistic data
protected function createTestDeal(array $attributes = []): string
{
    $defaults = [
        'id' => $this->generateUuid(),
        'name' => 'Integration Test Deal',
        'pipeline_stage_c' => 'sourcing',
        'amount' => rand(50000, 1000000),
        'assigned_user_id' => 'test-user-1',
        'date_entered' => date('Y-m-d H:i:s'),
        'stage_entry_time' => date('Y-m-d H:i:s')
    ];
    
    return array_merge($defaults, $attributes);
}

// Large dataset generation for performance testing
protected function createLargeTestDataset(int $count): void
{
    $stages = array_keys($this->stages);
    $dealsPerStage = intval($count / count($stages));
    
    foreach ($stages as $stage) {
        for ($i = 0; $i < $dealsPerStage; $i++) {
            $this->createTestDeal([
                'name' => "Load Test Deal {$stage}_{$i}",
                'pipeline_stage_c' => $stage,
                'amount' => rand(50000, 1000000)
            ]);
        }
    }
}
```

### Database State Management

All tests use database transactions for isolation:

```php
protected function setUp(): void
{
    parent::setUp();
    $this->beginTransaction();
}

protected function tearDown(): void
{
    $this->rollbackTransaction();
    parent::tearDown();
}
```

## Performance Benchmarks

### Target Metrics

| Operation | Target | Test Coverage |
|-----------|--------|---------------|
| Pipeline load | < 2 seconds | ✅ All viewport tests |
| API response | < 200ms | ✅ All API tests |
| Drag operation | < 500ms | ✅ Drag & drop tests |
| Large dataset | 500+ deals | ✅ Performance tests |
| Concurrent users | 100+ users | ✅ Load tests |

### Performance Test Results

Results are automatically captured and reported:

```bash
# Performance summary in test output
Pipeline Performance Results:
✅ Load time: 1.23s (target: < 2s)
✅ API response: 156ms (target: < 200ms)  
✅ Drag operation: 234ms (target: < 500ms)
✅ Large dataset: 500 deals loaded in 1.89s
⚠️  Memory usage: 87MB (monitor for optimization)
```

## Test Reports

### HTML Reports

The test runner generates comprehensive HTML reports:

- **Coverage Report**: Line-by-line code coverage
- **Test Results**: Pass/fail status with details
- **Performance Metrics**: Response times and benchmarks
- **Security Findings**: Authentication and validation results
- **Accessibility Report**: WCAG compliance status

### JUnit XML

For CI/CD integration:
```xml
<testsuite name="Pipeline Integration Tests" tests="45" failures="0" errors="0">
    <testcase classname="PipelineApiIntegrationTest" name="testGetPipelineStagesEndpoint" time="0.156"/>
    <testcase classname="PipelineDragDropIntegrationTest" name="testBasicDragAndDropFunctionality" time="2.341"/>
    <!-- ... more test cases ... -->
</testsuite>
```

## Troubleshooting

### Common Issues

**Selenium Connection Failed**:
```bash
# Check if Selenium Grid is running
curl http://localhost:4444/wd/hub/status

# Start Selenium Grid manually
docker run -d -p 4444:4444 --shm-size=2g selenium/standalone-chrome:latest
```

**Database Connection Issues**:
```bash
# Verify SQLite support
php -m | grep sqlite

# Check database permissions
ls -la tests/database/
```

**Performance Test Timeouts**:
```bash
# Increase timeout in phpunit.xml
<phpunit processTimeout="300">

# Or set environment variable
export PHPUNIT_TIMEOUT=300
```

### Debug Mode

Enable verbose testing:
```bash
# Run with debug output
phpunit --configuration ../phpunit.xml --debug integration/pipeline/

# Capture screenshots on failure
CAPTURE_SCREENSHOTS=true phpunit integration/pipeline/PipelineDragDropIntegrationTest.php
```

## Test Maintenance

### Adding New Tests

1. **Create test class** following naming convention
2. **Extend appropriate base class**:
   - `DatabaseTestCase` for database tests
   - `ApiTestCase` for API tests
   - WebDriver tests extend `DatabaseTestCase`

3. **Follow test structure**:
   ```php
   /**
    * @test
    * @group integration
    * @group feature-name
    * @group test-category
    */
   public function testSpecificScenario(): void
   {
       // Arrange
       $this->setupTestData();
       
       // Act
       $result = $this->performAction();
       
       // Assert
       $this->assertExpectedBehavior($result);
       
       // Cleanup (if needed)
       $this->cleanupTestData();
   }
   ```

### Updating Existing Tests

1. **Maintain backward compatibility**
2. **Update test data generators** if schema changes
3. **Review performance thresholds** periodically
4. **Update documentation** for new features

### Performance Monitoring

Set up alerts for performance regression:
```bash
# Add to CI pipeline
if [ "$API_RESPONSE_TIME" -gt 250 ]; then
    echo "⚠️ API response time regression detected"
    exit 1
fi
```

## Integration with Development Workflow

### Pre-commit Hooks

Install pre-commit hooks to run quick tests:
```bash
#!/bin/sh
# .git/hooks/pre-commit

# Run quick integration tests
cd tests
phpunit --configuration ../phpunit.xml --group quick integration/pipeline/

if [ $? -ne 0 ]; then
    echo "❌ Integration tests failed. Commit aborted."
    exit 1
fi
```

### Pull Request Checks

Automated testing on PR creation:
```yaml
# GitHub Actions workflow
- name: Run Integration Tests
  run: |
    cd tests
    ./run-comprehensive-integration-tests.sh --performance
    
- name: Comment PR
  if: failure()
  uses: actions/github-script@v6
  with:
    script: |
      github.rest.issues.createComment({
        issue_number: context.issue.number,
        owner: context.repo.owner,
        repo: context.repo.repo,
        body: '❌ Integration tests failed. Please check the test results.'
      })
```

## Quality Gates

### Deployment Readiness Checklist

Before production deployment, ensure:

- [ ] ✅ All integration tests pass (100%)
- [ ] ✅ Code coverage > 85%
- [ ] ✅ Performance benchmarks met
- [ ] ✅ Security tests pass
- [ ] ✅ Accessibility compliance verified
- [ ] ✅ Cross-browser testing completed
- [ ] ✅ Mobile testing completed
- [ ] ✅ Load testing passed

### Monitoring in Production

Set up production monitoring to validate test assumptions:

```javascript
// Production performance monitoring
window.pipelinePerformance = {
    loadTime: performance.timing.loadEventEnd - performance.timing.navigationStart,
    dragLatency: /* measure drag operation time */,
    apiResponseTime: /* measure API calls */
};

// Send metrics to monitoring service
if (window.pipelinePerformance.loadTime > 2000) {
    analytics.track('performance_issue', {
        metric: 'load_time',
        value: window.pipelinePerformance.loadTime,
        threshold: 2000
    });
}
```

---

This comprehensive integration testing suite ensures the Pipeline feature is robust, secure, accessible, and performant across all supported platforms and user scenarios.