# MakeDeal CRM Testing Guide

## Quick Start

### Running Tests

```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test suite
./vendor/bin/phpunit --testsuite unit
./vendor/bin/phpunit --testsuite integration
./vendor/bin/phpunit --testsuite e2e

# Run tests for specific module
./vendor/bin/phpunit tests/unit/modules/mdeal_Deals/

# Run with coverage report
./vendor/bin/phpunit --coverage-html reports/coverage

# Run specific test method
./vendor/bin/phpunit --filter testDealStageProgression
```

### Test Database Setup

1. Create test database:
```sql
CREATE DATABASE makedeal_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'test_user'@'localhost' IDENTIFIED BY 'test_password';
GRANT ALL PRIVILEGES ON makedeal_test.* TO 'test_user'@'localhost';
```

2. Configure environment:
```bash
# .env.test
TEST_DB_HOST=localhost
TEST_DB_USER=test_user
TEST_DB_PASSWORD=test_password
TEST_DB_NAME=makedeal_test
```

3. Run migrations:
```bash
php migrate.php --env=test
```

## Writing Tests

### Unit Test Example

```php
namespace Tests\Unit\Modules\mdeal_Deals;

use Tests\TestCase;

class DealValidationTest extends TestCase
{
    /**
     * @test
     */
    public function testRequiredFieldValidation(): void
    {
        $deal = new \mdeal_Deals();
        
        $errors = $deal->validateDeal();
        
        $this->assertContains('Deal name is required', $errors);
        $this->assertContains('Pipeline stage is required', $errors);
    }
}
```

### Integration Test Example

```php
namespace Tests\Integration\Modules\mdeal_Deals;

use Tests\DatabaseTestCase;

class DealPersistenceTest extends DatabaseTestCase
{
    /**
     * @test
     */
    public function testDealCreationWithDatabase(): void
    {
        $dealData = [
            'name' => 'Integration Test Deal',
            'amount' => 500000,
            'pipeline_stage_c' => 'sourcing'
        ];
        
        $deal = new \mdeal_Deals();
        $deal->populateFromArray($dealData);
        $deal->save();
        
        $this->assertDatabaseHas('mdeal_deals', [
            'name' => 'Integration Test Deal'
        ]);
    }
}
```

### E2E Test Example

```javascript
// tests/e2e/deal-pipeline.spec.js
describe('Deal Pipeline', () => {
    it('should allow dragging deal between stages', async () => {
        await page.goto('/index.php?module=Deals&action=pipeline');
        
        const deal = await page.$('[data-deal-id="123"]');
        const targetStage = await page.$('[data-stage="qualified"]');
        
        await deal.dragTo(targetStage);
        
        await expect(page).toHaveText('.success-message', 'Deal moved successfully');
    });
});
```

## Test Organization

### Directory Structure
```
tests/
├── unit/                    # Fast, isolated tests
│   └── modules/
│       └── mdeal_Deals/
│           ├── DealValidationTest.php
│           ├── DealCalculationsTest.php
│           └── DealPermissionsTest.php
├── integration/             # Database and service tests
│   └── modules/
│       └── mdeal_Deals/
│           ├── DealPersistenceTest.php
│           └── DealWorkflowTest.php
├── e2e/                     # Browser-based tests
│   ├── deal-pipeline.spec.js
│   └── deal-creation.spec.js
├── fixtures/                # Test data
│   ├── deals.json
│   └── users.json
├── helpers/                 # Test utilities
│   ├── DatabaseTestCase.php
│   └── factories/
│       └── DealFactory.php
└── templates/               # Test templates
    ├── UnitTestTemplate.php
    └── IntegrationTestTemplate.php
```

## Test Data Management

### Using Factories

```php
// tests/helpers/factories/DealFactory.php
class DealFactory
{
    public static function create(array $attributes = []): array
    {
        return array_merge([
            'id' => Uuid::generate(),
            'name' => 'Test Deal ' . rand(1000, 9999),
            'amount' => rand(10000, 1000000),
            'pipeline_stage_c' => 'sourcing',
            'assigned_user_id' => 'test-user-1',
            'created_at' => date('Y-m-d H:i:s')
        ], $attributes);
    }
    
    public static function createMultiple(int $count, array $attributes = []): array
    {
        $deals = [];
        for ($i = 0; $i < $count; $i++) {
            $deals[] = self::create($attributes);
        }
        return $deals;
    }
}
```

### Using Fixtures

```php
// In your test
protected function loadDealFixtures(): void
{
    $fixtures = json_decode(
        file_get_contents(__DIR__ . '/../../fixtures/deals.json'),
        true
    );
    
    foreach ($fixtures as $fixture) {
        $this->insertTestRecords('mdeal_deals', [$fixture]);
    }
}
```

## Mocking Guidelines

### Mocking External Services

```php
public function testExternalAPIIntegration(): void
{
    $mockClient = $this->createMock(HttpClient::class);
    $mockClient->expects($this->once())
        ->method('post')
        ->with('https://api.example.com/webhook')
        ->willReturn(['status' => 'success']);
    
    $service = new DealNotificationService($mockClient);
    $result = $service->notifyExternalSystem($deal);
    
    $this->assertTrue($result);
}
```

### Mocking SuiteCRM Beans

```php
public function testBeanInteraction(): void
{
    $mockUser = $this->createMock(\User::class);
    $mockUser->id = 'test-user-123';
    $mockUser->method('ACLAccess')->willReturn(true);
    
    $deal = new \mdeal_Deals();
    $deal->assigned_user = $mockUser;
    
    $this->assertTrue($deal->userCanEdit());
}
```

## Performance Testing

### Benchmarking Tests

```php
/**
 * @test
 * @group performance
 */
public function testBulkDealProcessingPerformance(): void
{
    $deals = DealFactory::createMultiple(1000);
    
    $this->assertExecutionTime(function() use ($deals) {
        foreach ($deals as $deal) {
            $this->service->processDeal($deal);
        }
    }, 5.0, 'Processing 1000 deals should complete within 5 seconds');
}
```

### Memory Usage Tests

```php
/**
 * @test
 * @group performance
 */
public function testMemoryEfficiency(): void
{
    $this->assertMemoryUsage(function() {
        $report = $this->service->generateLargeReport(10000);
    }, 100 * 1024 * 1024, 'Report generation should use less than 100MB');
}
```

## Continuous Integration

### PHPUnit Configuration

```xml
<!-- phpunit.xml -->
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="tests/bootstrap.php"
         colors="true"
         verbose="true"
         stopOnFailure="false">
    
    <testsuites>
        <testsuite name="unit">
            <directory>tests/unit</directory>
        </testsuite>
        <testsuite name="integration">
            <directory>tests/integration</directory>
        </testsuite>
        <testsuite name="performance">
            <directory>tests/unit</directory>
            <directory>tests/integration</directory>
            <group>performance</group>
        </testsuite>
    </testsuites>
    
    <coverage processUncoveredFiles="true">
        <include>
            <directory suffix=".php">custom/modules</directory>
        </include>
        <exclude>
            <directory>custom/modules/*/language</directory>
            <directory>custom/modules/*/metadata</directory>
        </exclude>
    </coverage>
    
    <php>
        <env name="APP_ENV" value="testing"/>
    </php>
</phpunit>
```

### GitHub Actions Example

```yaml
# .github/workflows/tests.yml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: makedeal_test
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=3
    
    steps:
    - uses: actions/checkout@v2
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.1'
        extensions: mbstring, xml, mysql
        coverage: xdebug
    
    - name: Install dependencies
      run: composer install --prefer-dist --no-progress
    
    - name: Run tests
      run: ./vendor/bin/phpunit --coverage-clover coverage.xml
    
    - name: Upload coverage
      uses: codecov/codecov-action@v1
```

## Best Practices

### 1. Test Naming
- Use descriptive test names that explain what is being tested
- Include the scenario and expected outcome
- Group related tests in the same class

### 2. Test Independence
- Each test should be able to run independently
- Use setUp() and tearDown() for initialization and cleanup
- Avoid dependencies between tests

### 3. Test Speed
- Unit tests should run in milliseconds
- Integration tests should run in seconds
- E2E tests can take longer but should be minimized

### 4. Test Coverage
- Aim for 80%+ coverage for business logic
- Focus on critical paths and edge cases
- Don't test framework code or simple getters/setters

### 5. Test Maintenance
- Refactor tests along with code
- Remove obsolete tests
- Keep test code DRY using helpers and base classes

## Troubleshooting

### Common Issues

1. **Database Connection Errors**
   - Check test database credentials
   - Ensure test database exists
   - Verify MySQL service is running

2. **Permission Errors**
   - Check file permissions in test directories
   - Ensure web server user can write to temp directories

3. **Memory Limit Errors**
   - Increase PHP memory limit for tests
   - Use data providers to reduce memory usage
   - Clear large objects in tearDown()

4. **Timeout Errors**
   - Increase PHPUnit timeout for slow tests
   - Optimize database queries
   - Use test doubles for external services

### Debug Mode

Enable debug output:
```bash
./vendor/bin/phpunit --debug
```

Show test execution plan:
```bash
./vendor/bin/phpunit --list-tests
```

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [SuiteCRM Testing Guide](https://docs.suitecrm.com/developer/tests/)
- [Test Driven Development](https://en.wikipedia.org/wiki/Test-driven_development)
- [MakeDeal CRM Test Strategy](tests/templates/TEST_STRATEGY.md)