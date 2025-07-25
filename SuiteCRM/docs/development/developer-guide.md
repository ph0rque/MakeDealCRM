# MakeDeal CRM Developer Guide

## Introduction

This guide provides comprehensive information for developers working on or extending the MakeDeal CRM system. It covers development setup, coding standards, module creation, and best practices.

## Development Environment Setup

### Prerequisites

- PHP 7.4+ with extensions: mysqli, gd, curl, zip, mbstring, imap
- MySQL 5.7+ or MariaDB 10.2+
- Apache 2.4+ with mod_rewrite
- Node.js 14+ and npm
- Composer 2.0+
- Git

### Local Development Setup

1. **Clone Repository**
   ```bash
   git clone https://github.com/yourorg/makedeal-crm.git
   cd makedeal-crm
   ```

2. **Install Dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Configure Environment**
   ```bash
   cp config_override.php.example config_override.php
   # Edit config_override.php with your database settings
   ```

4. **Database Setup**
   ```bash
   mysql -u root -p -e "CREATE DATABASE makedeal_dev"
   mysql -u root -p makedeal_dev < install/makedeal_base.sql
   ```

5. **Run Installation Scripts**
   ```bash
   php install.php
   ```

### Development Tools

**Recommended IDE**: PhpStorm or VSCode with PHP extensions

**VSCode Extensions**:
- PHP Intelephense
- PHP Debug
- MySQL
- GitLens
- ESLint

**PhpStorm Plugins**:
- SugarCRM/SuiteCRM Plugin
- PHP Annotations
- Database Tools

## Architecture Overview

### Directory Structure

```
makedeal-crm/
├── cache/                    # Cached files (gitignored)
├── custom/                   # Customizations
│   ├── modules/             # Custom modules
│   │   ├── mdeal_Leads/    # Leads module
│   │   ├── mdeal_Contacts/ # Contacts module
│   │   ├── mdeal_Accounts/ # Accounts module
│   │   ├── mdeal_Deals/    # Deals module
│   │   └── Pipelines/       # Pipeline system
│   ├── Extension/           # Module extensions
│   └── include/             # Custom includes
├── data/                    # Data layer classes
├── include/                 # Core includes
├── modules/                 # Core modules
├── tests/                   # Test suites
│   ├── Unit/               # Unit tests
│   └── Integration/        # Integration tests
├── themes/                  # UI themes
└── vendor/                  # Composer dependencies
```

### Module Structure

Each custom module follows this structure:

```
mdeal_ModuleName/
├── metadata/               # Module metadata
│   ├── detailviewdefs.php # Detail view definition
│   ├── editviewdefs.php   # Edit view definition
│   ├── listviewdefs.php   # List view definition
│   ├── searchdefs.php     # Search definition
│   └── subpaneldefs.php   # Subpanel definitions
├── language/              # Language files
│   └── en_us.lang.php    # English translations
├── mdeal_ModuleName.php   # Bean class
├── vardefs.php            # Field definitions
├── ModuleLogicHooks.php   # Business logic
├── views/                 # Custom views
└── install/              # Installation files
    └── install.sql       # Database schema
```

## Creating a New Module

### Step 1: Define Module Structure

Create vardefs.php:
```php
<?php
$dictionary['mdeal_NewModule'] = [
    'table' => 'mdeal_newmodule',
    'audited' => true,
    'unified_search' => true,
    'fields' => [
        'name' => [
            'name' => 'name',
            'type' => 'name',
            'dbType' => 'varchar',
            'vname' => 'LBL_NAME',
            'required' => true,
            'unified_search' => true,
        ],
        // Add more fields...
    ],
    'relationships' => [
        // Define relationships...
    ],
    'indices' => [
        // Define indexes...
    ],
];
```

### Step 2: Create Bean Class

Create mdeal_NewModule.php:
```php
<?php
require_once('data/SugarBean.php');
require_once('include/templates/basic/Basic.php');

class mdeal_NewModule extends Basic
{
    public $module_name = 'mdeal_NewModule';
    public $object_name = 'mdeal_NewModule';
    public $module_dir = 'mdeal_NewModule';
    public $table_name = 'mdeal_newmodule';
    public $new_schema = true;
    
    public function bean_implements($interface)
    {
        switch($interface) {
            case 'ACL':
                return true;
        }
        return false;
    }
    
    // Custom methods...
}
```

### Step 3: Create Metadata Files

Create metadata/detailviewdefs.php:
```php
<?php
$viewdefs['mdeal_NewModule']['DetailView'] = [
    'templateMeta' => [
        'form' => ['buttons' => ['EDIT', 'DELETE', 'DUPLICATE']],
        'maxColumns' => '2',
        'widths' => [
            ['label' => '10', 'field' => '30'],
            ['label' => '10', 'field' => '30']
        ],
    ],
    'panels' => [
        'default' => [
            [
                'name',
                'assigned_user_name',
            ],
            // Add more fields...
        ],
    ],
];
```

### Step 4: Register Module

Add to custom/Extension/application/Ext/Include/modules.ext.php:
```php
$beanList['mdeal_NewModule'] = 'mdeal_NewModule';
$beanFiles['mdeal_NewModule'] = 'custom/modules/mdeal_NewModule/mdeal_NewModule.php';
$moduleList[] = 'mdeal_NewModule';
```

## Working with the Pipeline System

### Understanding Pipeline Stages

The pipeline system uses a state machine pattern:

```php
class PipelineAutomationEngine
{
    protected $stages = [
        'sourcing' => ['next' => ['screening'], 'probability' => 10],
        'screening' => ['next' => ['analysis_outreach'], 'probability' => 20],
        // ... more stages
    ];
    
    public function canTransition($fromStage, $toStage)
    {
        return in_array($toStage, $this->stages[$fromStage]['next']);
    }
}
```

### Adding Custom Validation

Create custom validators:
```php
class CustomValidator implements ValidatorInterface
{
    public function validate($bean, $stage)
    {
        $errors = [];
        
        if ($stage === 'due_diligence') {
            if (empty($bean->legal_review_complete)) {
                $errors[] = 'Legal review must be completed';
            }
        }
        
        return $errors;
    }
}
```

### Implementing Automation Rules

```php
class CustomAutomationRule implements AutomationRuleInterface
{
    public function evaluate($bean)
    {
        // Return true if rule should fire
        return $bean->deal_value > 50000000 
            && $bean->stage === 'term_sheet';
    }
    
    public function execute($bean)
    {
        // Execute automation
        $bean->requires_board_approval = true;
        $bean->save();
        
        // Create notification task
        $this->createApprovalTask($bean);
    }
}
```

## Logic Hooks

### Available Hook Points

- `before_save` - Before record is saved
- `after_save` - After record is saved
- `before_delete` - Before record deletion
- `after_delete` - After record deletion
- `after_retrieve` - After record is retrieved
- `process_record` - During list view processing

### Implementing Logic Hooks

Create logic_hooks.php:
```php
<?php
$hook_array['after_save'][] = [
    1, 
    'Calculate scores', 
    'custom/modules/mdeal_Leads/LeadLogicHooks.php',
    'LeadLogicHooks', 
    'calculateLeadScore'
];
```

Create hook class:
```php
class LeadLogicHooks
{
    public function calculateLeadScore($bean, $event, $arguments)
    {
        if ($bean->fetched_row['annual_revenue'] != $bean->annual_revenue) {
            // Revenue changed, recalculate score
            $score = $this->calculateScore($bean);
            $bean->lead_score = $score;
        }
    }
}
```

## API Development

### Creating Custom API Endpoints

Create custom/api/LeadScoringApi.php:
```php
<?php
require_once('include/api/SugarApi.php');

class LeadScoringApi extends SugarApi
{
    public function registerApiRest()
    {
        return [
            'scoreLeads' => [
                'reqType' => 'POST',
                'path' => ['Leads', 'score-batch'],
                'method' => 'scoreBatchLeads',
                'shortHelp' => 'Score multiple leads',
            ],
        ];
    }
    
    public function scoreBatchLeads($api, $args)
    {
        $leadIds = $args['lead_ids'] ?? [];
        $results = [];
        
        foreach ($leadIds as $leadId) {
            $lead = BeanFactory::getBean('mdeal_Leads', $leadId);
            if ($lead) {
                $score = $this->calculateScore($lead);
                $lead->lead_score = $score;
                $lead->save();
                
                $results[] = [
                    'id' => $leadId,
                    'score' => $score
                ];
            }
        }
        
        return ['success' => true, 'results' => $results];
    }
}
```

## Testing

### Unit Testing

Create tests/Unit/modules/mdeal_Leads/LeadScoringTest.php:
```php
<?php
use PHPUnit\Framework\TestCase;

class LeadScoringTest extends TestCase
{
    protected $scorer;
    
    protected function setUp(): void
    {
        $this->scorer = new LeadScorer();
    }
    
    public function testHighRevenueScore()
    {
        $lead = $this->createMock('mdeal_Leads');
        $lead->annual_revenue = 100000000;
        $lead->employee_count = 500;
        
        $score = $this->scorer->calculateScore($lead);
        
        $this->assertGreaterThanOrEqual(80, $score);
    }
}
```

### Integration Testing

```php
class PipelineIntegrationTest extends TestCase
{
    public function testCompleteWorkflow()
    {
        // Create lead
        $lead = BeanFactory::newBean('mdeal_Leads');
        $lead->company_name = 'Test Corp';
        $lead->save();
        
        // Convert to deal
        $converter = new LeadConversionEngine();
        $result = $converter->convertLead($lead);
        
        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['deal_id']);
        
        // Progress through pipeline
        $deal = BeanFactory::getBean('mdeal_Deals', $result['deal_id']);
        $automation = new PipelineAutomationEngine();
        
        $transition = $automation->executeStageTransition($deal, 'screening');
        $this->assertTrue($transition['success']);
    }
}
```

## Performance Optimization

### Database Query Optimization

```php
// Bad - N+1 query problem
foreach ($accounts as $account) {
    $contacts = $account->getRelatedContacts(); // Queries each time
}

// Good - Single query with join
$query = "SELECT c.* FROM mdeal_contacts c 
          JOIN mdeal_accounts a ON c.account_id = a.id 
          WHERE a.id IN (?)";
$contacts = $db->fetchAll($query, [$accountIds]);
```

### Caching Strategies

```php
class CachedDataProvider
{
    protected $cache;
    
    public function getStageConfiguration($stage)
    {
        $cacheKey = "stage_config_{$stage}";
        
        if ($cached = $this->cache->get($cacheKey)) {
            return $cached;
        }
        
        $config = $this->loadStageConfig($stage);
        $this->cache->set($cacheKey, $config, 3600); // 1 hour
        
        return $config;
    }
}
```

## Security Best Practices

### Input Validation

```php
class DealValidator
{
    public function validateDealValue($value)
    {
        if (!is_numeric($value) || $value < 0) {
            throw new ValidationException('Invalid deal value');
        }
        
        if ($value > 1000000000) { // $1B limit
            throw new ValidationException('Deal value exceeds maximum');
        }
        
        return true;
    }
}
```

### SQL Injection Prevention

```php
// Bad - SQL injection vulnerable
$query = "SELECT * FROM mdeal_deals WHERE name = '{$name}'";

// Good - Parameterized query
$query = "SELECT * FROM mdeal_deals WHERE name = ?";
$result = $db->pQuery($query, [$name]);
```

### Access Control

```php
class DealSecurityManager
{
    public function canViewDeal($userId, $dealId)
    {
        // Check ownership
        if ($this->isOwner($userId, $dealId)) {
            return true;
        }
        
        // Check team membership
        if ($this->isTeamMember($userId, $dealId)) {
            return true;
        }
        
        // Check role permissions
        return $this->hasRolePermission($userId, 'view_all_deals');
    }
}
```

## Debugging and Troubleshooting

### Enable Debug Mode

In config_override.php:
```php
$sugar_config['developerMode'] = true;
$sugar_config['logger']['level'] = 'debug';
$sugar_config['logger']['file']['maxSize'] = '50MB';
$sugar_config['dump_slow_queries'] = true;
```

### Custom Logging

```php
class PipelineLogger
{
    public function logTransition($deal, $fromStage, $toStage, $result)
    {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'deal_id' => $deal->id,
            'from_stage' => $fromStage,
            'to_stage' => $toStage,
            'success' => $result['success'],
            'errors' => $result['errors'] ?? [],
            'user_id' => $GLOBALS['current_user']->id
        ];
        
        $GLOBALS['log']->info('Pipeline Transition: ' . json_encode($logEntry));
    }
}
```

### Performance Profiling

```php
class PerformanceProfiler
{
    protected $markers = [];
    
    public function mark($label)
    {
        $this->markers[$label] = microtime(true);
    }
    
    public function measure($startLabel, $endLabel)
    {
        $duration = $this->markers[$endLabel] - $this->markers[$startLabel];
        $GLOBALS['log']->info("Performance: {$startLabel} to {$endLabel}: {$duration}s");
        
        if ($duration > 1.0) {
            $GLOBALS['log']->warn("Slow operation detected: {$startLabel}");
        }
    }
}
```

## Deployment

### Pre-deployment Checklist

- [ ] All tests passing
- [ ] Code review completed
- [ ] Database migrations prepared
- [ ] Performance impact assessed
- [ ] Security review completed
- [ ] Documentation updated

### Deployment Process

1. **Backup Production**
   ```bash
   ./scripts/backup_production.sh
   ```

2. **Deploy Code**
   ```bash
   git pull origin main
   composer install --no-dev
   ```

3. **Run Migrations**
   ```bash
   php migrate.php
   ```

4. **Clear Caches**
   ```bash
   php repair.php --clearCache
   ```

5. **Quick Repair**
   ```bash
   php repair.php --quickRepair
   ```

## Contributing

### Code Style

Follow PSR-12 coding standards:

```php
<?php

namespace Custom\Modules\Deals;

use SugarBean;

class DealManager
{
    private const DEFAULT_LIMIT = 100;
    
    public function getActiveDeals(int $limit = self::DEFAULT_LIMIT): array
    {
        $deals = [];
        
        // Implementation...
        
        return $deals;
    }
}
```

### Git Workflow

1. Create feature branch
   ```bash
   git checkout -b feature/add-deal-scoring
   ```

2. Make changes and commit
   ```bash
   git add .
   git commit -m "feat: Add deal scoring algorithm"
   ```

3. Push and create PR
   ```bash
   git push origin feature/add-deal-scoring
   ```

### Commit Message Format

Follow conventional commits:
- `feat:` New feature
- `fix:` Bug fix
- `docs:` Documentation
- `style:` Code style
- `refactor:` Code refactoring
- `test:` Tests
- `chore:` Maintenance

## Resources

### Internal Documentation
- [API Documentation](./API_DOCUMENTATION.md)
- [Technical Documentation](./TECHNICAL_DOCUMENTATION.md)
- [Database Schema](./DATABASE_SCHEMA.md)

### External Resources
- [SuiteCRM Developer Guide](https://docs.suitecrm.com/developer/)
- [PHP-FIG Standards](https://www.php-fig.org/)
- [MySQL Performance Blog](https://www.percona.com/blog/)

### Support Channels
- Development Team Slack: #makedeal-dev
- Issue Tracker: https://github.com/yourorg/makedeal-crm/issues
- Wiki: https://wiki.yourorg.com/makedeal-crm

---

Last Updated: [Current Date]
Version: 1.0.0