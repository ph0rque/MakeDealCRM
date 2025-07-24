# MakeDeal CRM Test Strategy and Guidelines

## Test Philosophy

Our testing approach follows these core principles:
1. **Test Behavior, Not Implementation** - Focus on what the code does, not how
2. **80/20 Rule** - 80% of bugs come from 20% of code paths
3. **Fast Feedback** - Tests should run quickly and fail clearly
4. **Isolated Tests** - Each test should be independent
5. **Realistic Scenarios** - Test with production-like data

## Test Coverage Requirements

### Coverage Targets by Module Type
- **Core Business Logic**: 90%+ coverage
- **Controllers/API Endpoints**: 85%+ coverage
- **Data Access Layer**: 85%+ coverage
- **Utilities/Helpers**: 80%+ coverage
- **UI Components**: 70%+ coverage

### Critical Path Coverage
These areas MUST have 95%+ coverage:
- Payment processing
- User authentication/authorization
- Deal stage transitions
- Data validation
- Business rule enforcement

## Test Types and When to Use Them

### 1. Unit Tests (70% of tests)
**Purpose**: Test individual components in isolation

**Use When**:
- Testing pure functions
- Testing class methods
- Validating business logic
- Testing edge cases

**Example Scenarios**:
```php
// Good unit test candidate
public function calculateCommission($dealValue, $rate) {
    return $dealValue * $rate;
}

// Test edge cases
- Zero values
- Negative values
- Maximum values
- Null/empty inputs
```

### 2. Integration Tests (20% of tests)
**Purpose**: Test component interactions

**Use When**:
- Testing database operations
- Testing API endpoints
- Testing service integrations
- Testing transaction handling

**Example Scenarios**:
```php
// Database integration
- Create -> Read -> Update -> Delete workflows
- Complex queries with joins
- Transaction rollback scenarios
- Concurrent access handling
```

### 3. End-to-End Tests (10% of tests)
**Purpose**: Test complete user workflows

**Use When**:
- Testing critical user journeys
- Testing UI interactions
- Testing cross-browser compatibility
- Testing mobile responsiveness

**Example Scenarios**:
- Complete deal lifecycle (create -> progress -> close)
- User login -> navigate -> perform action -> verify result
- Drag-and-drop operations
- Multi-step forms

## Test Patterns and Best Practices

### 1. Arrange-Act-Assert (AAA) Pattern
```php
public function testDealStageTransition() {
    // Arrange
    $deal = $this->createDeal(['stage' => 'lead']);
    
    // Act
    $result = $deal->moveToStage('qualified');
    
    // Assert
    $this->assertTrue($result);
    $this->assertEquals('qualified', $deal->getStage());
}
```

### 2. Data Providers for Parameterized Tests
```php
/**
 * @dataProvider stageTransitionProvider
 */
public function testValidStageTransitions($from, $to, $expected) {
    // Test implementation
}

public function stageTransitionProvider() {
    return [
        ['lead', 'contacted', true],
        ['lead', 'won', false],  // Can't skip stages
        ['won', 'lead', false],  // Terminal stage
    ];
}
```

### 3. Mock External Dependencies
```php
public function testEmailNotification() {
    $mockMailer = $this->createMock(MailerInterface::class);
    $mockMailer->expects($this->once())
        ->method('send')
        ->with($this->equalTo('user@example.com'))
        ->willReturn(true);
    
    $service = new NotificationService($mockMailer);
    $result = $service->notifyDealWon($deal);
    
    $this->assertTrue($result);
}
```

### 4. Test Fixtures and Factories
```php
// Use factories for consistent test data
class DealFactory {
    public static function create(array $attributes = []) {
        return array_merge([
            'id' => uniqid(),
            'name' => 'Test Deal',
            'stage' => 'lead',
            'amount' => 10000,
            'probability' => 20,
            'owner_id' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ], $attributes);
    }
}
```

## Testing SuiteCRM Specifics

### 1. Bean Testing
```php
// Test custom fields
$deal = BeanFactory::newBean('Opportunities');
$deal->name = 'Test Deal';
$deal->pipeline_stage_c = 'qualified';
$deal->save();

$this->assertNotEmpty($deal->id);
$this->assertEquals('qualified', $deal->pipeline_stage_c);
```

### 2. Hook Testing
```php
// Test logic hooks
class DealHooksTest extends TestCase {
    public function testBeforeSaveHook() {
        $deal = $this->createDeal();
        $hook = new DealLogicHooks();
        
        $hook->beforeSave($deal, 'before_save', []);
        
        $this->assertNotNull($deal->stage_entered_date_c);
    }
}
```

### 3. API Testing
```php
// Test custom API endpoints
public function testPipelineAPI() {
    $response = $this->apiCall('POST', '/pipeline/move-deal', [
        'deal_id' => '123',
        'new_stage' => 'qualified'
    ]);
    
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('qualified', $response->json()['stage']);
}
```

## Performance Testing Guidelines

### 1. Set Performance Budgets
```php
/**
 * @group performance
 */
public function testBulkDealProcessing() {
    $this->assertExecutionTime(function() {
        $this->service->processBulkDeals(100);
    }, 2.0); // Must complete within 2 seconds
}
```

### 2. Memory Usage Testing
```php
public function testMemoryEfficiency() {
    $this->assertMemoryUsage(function() {
        $results = $this->service->loadLargeDataset(10000);
    }, 50 * 1024 * 1024); // Max 50MB
}
```

## Test Data Management

### 1. Test Database Strategy
- Use transactions for test isolation
- Rollback after each test
- Maintain separate test database
- Use minimal fixtures

### 2. Test Data Principles
- Use realistic data volumes
- Include edge cases
- Test with international characters
- Include null/empty values
- Test boundary conditions

## Common Testing Pitfalls to Avoid

### 1. **Don't Test Framework Code**
```php
// Bad: Testing SuiteCRM's save method
public function testBeanSave() {
    $bean->save();
    $this->assertTrue($bean->id); // Framework's job
}

// Good: Test your business logic
public function testCustomSaveLogic() {
    $bean->customSave();
    $this->assertEquals('processed', $bean->status_c);
}
```

### 2. **Avoid Brittle Tests**
```php
// Bad: Depends on specific IDs
$this->assertEquals('12345', $deal->id);

// Good: Test behavior
$this->assertNotEmpty($deal->id);
$this->assertStringStartsWith('DEAL-', $deal->reference_number);
```

### 3. **Don't Over-Mock**
```php
// Bad: Mocking everything
$mockDeal = $this->createMock(Deal::class);
$mockDeal->method('getStage')->willReturn('qualified');

// Good: Use real objects when possible
$deal = new Deal(['stage' => 'qualified']);
```

## Test Organization

### Directory Structure
```
tests/
├── unit/
│   └── modules/
│       └── ModuleName/
│           ├── Services/
│           ├── Models/
│           └── Validators/
├── integration/
│   └── modules/
│       └── ModuleName/
│           ├── API/
│           └── Database/
├── e2e/
│   └── workflows/
├── fixtures/
│   └── modules/
└── helpers/
    └── factories/
```

### Naming Conventions
- Test classes: `{ClassName}Test.php`
- Test methods: `test{MethodName}{Scenario}`
- Data providers: `{methodName}Provider`
- Fixtures: `{entity}_fixture.{ext}`

## Continuous Integration

### Test Execution Order
1. **Pre-commit** (< 10 seconds)
   - Linting
   - Unit tests for changed files
   
2. **Pull Request** (< 5 minutes)
   - All unit tests
   - Integration tests
   - Code coverage check
   
3. **Nightly** (< 30 minutes)
   - Full test suite
   - E2E tests
   - Performance tests
   - Security scans

## Debugging Failed Tests

### 1. Use Descriptive Assertions
```php
// Bad
$this->assertTrue($result);

// Good
$this->assertTrue($result, 
    "Deal transition from {$from} to {$to} should be allowed");
```

### 2. Add Context to Failures
```php
try {
    $service->process($data);
} catch (Exception $e) {
    $this->fail("Processing failed with data: " . json_encode($data));
}
```

### 3. Use Test Helpers
```php
// Helper for debugging
protected function debugDeal($deal) {
    echo "\nDeal State:\n";
    echo "ID: {$deal->id}\n";
    echo "Stage: {$deal->stage}\n";
    echo "Updated: {$deal->date_modified}\n";
}
```

## Module-Specific Testing Guidelines

### Deal/Opportunity Module
- Test all 11 pipeline stages
- Test stage transition rules
- Test WIP limit enforcement
- Test time tracking accuracy
- Test stale deal detection

### Contact Module
- Test duplicate detection
- Test merge functionality
- Test relationship management
- Test data validation

### Activity Module
- Test scheduling logic
- Test reminder functionality
- Test recurring activities
- Test timezone handling

## Test Maintenance

### Regular Review Checklist
- [ ] Remove obsolete tests
- [ ] Update tests for new requirements
- [ ] Refactor duplicate test code
- [ ] Review and update test data
- [ ] Check test execution time
- [ ] Validate coverage metrics

### Test Documentation
Each test class should include:
- Purpose and scope
- Prerequisites
- Test data requirements
- Known limitations
- Related requirements/tickets