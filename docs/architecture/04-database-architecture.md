# SuiteCRM Database Architecture

## Overview

SuiteCRM uses a relational database management system (RDBMS) to store all CRM data. The system supports multiple database platforms through its database abstraction layer, with MySQL/MariaDB being the most commonly used. The database architecture is designed for scalability, performance, and data integrity.

## Supported Database Systems

| Database | Version | Status | Notes |
|----------|---------|--------|-------|
| MySQL | 5.7+ | Fully Supported | Recommended for production |
| MariaDB | 10.2+ | Fully Supported | Drop-in MySQL replacement |
| MSSQL | 2012+ | Supported | Microsoft SQL Server |
| PostgreSQL | 9.6+ | Limited Support | Community maintained |

## Database Abstraction Layer

### DBManager Architecture

SuiteCRM uses a custom database abstraction layer:

```
DBManager (Abstract)
├── MysqlManager
├── MysqliManager
├── SqlsrvManager
├── MssqlManager
└── FreeTDSManager
```

### Key Components

```php
// Database factory usage
$db = DBManagerFactory::getInstance();

// Query execution
$result = $db->query("SELECT * FROM accounts WHERE deleted = 0");

// Prepared statements
$sql = "SELECT * FROM accounts WHERE industry = ?";
$result = $db->pQuery($sql, [$industry]);
```

## Database Schema Design

### Naming Conventions

1. **Tables**: Lowercase, plural (e.g., `accounts`, `contacts`)
2. **Fields**: Lowercase with underscores (e.g., `first_name`, `date_entered`)
3. **Indices**: `idx_` prefix (e.g., `idx_account_name`)
4. **Foreign Keys**: Referenced table + `_id` (e.g., `account_id`)

### Standard Fields

Every SuiteCRM table includes these standard fields:

| Field | Type | Purpose |
|-------|------|---------|
| id | char(36) | Primary key (UUID) |
| name | varchar(255) | Display name |
| date_entered | datetime | Creation timestamp |
| date_modified | datetime | Last modification timestamp |
| modified_user_id | char(36) | User who last modified |
| created_by | char(36) | User who created |
| description | text | Long description |
| deleted | tinyint(1) | Soft delete flag |
| assigned_user_id | char(36) | Assigned user |

### Table Categories

#### 1. Module Tables
Core business entity tables:

```sql
-- Accounts table example
CREATE TABLE accounts (
    id char(36) NOT NULL,
    name varchar(150),
    date_entered datetime,
    date_modified datetime,
    modified_user_id char(36),
    created_by char(36),
    description text,
    deleted tinyint(1) DEFAULT 0,
    assigned_user_id char(36),
    account_type varchar(50),
    industry varchar(50),
    annual_revenue varchar(100),
    phone_fax varchar(100),
    billing_address_street varchar(150),
    billing_address_city varchar(100),
    billing_address_state varchar(100),
    billing_address_postalcode varchar(20),
    billing_address_country varchar(255),
    rating varchar(100),
    phone_office varchar(100),
    phone_alternate varchar(100),
    website varchar(255),
    ownership varchar(100),
    employees varchar(10),
    ticker_symbol varchar(10),
    shipping_address_street varchar(150),
    shipping_address_city varchar(100),
    shipping_address_state varchar(100),
    shipping_address_postalcode varchar(20),
    shipping_address_country varchar(255),
    PRIMARY KEY (id),
    KEY idx_accnt_name (name),
    KEY idx_accnt_assigned_del (deleted, assigned_user_id)
);
```

#### 2. Relationship Tables
Many-to-many relationship tables:

```sql
-- Accounts to Contacts relationship
CREATE TABLE accounts_contacts (
    id char(36) NOT NULL,
    contact_id char(36),
    account_id char(36),
    date_modified datetime,
    deleted tinyint(1) DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_account_contact (account_id, contact_id),
    KEY idx_contact_account (contact_id, account_id)
);
```

#### 3. System Tables

Configuration and system tables:

- `config` - System configuration
- `users` - User accounts
- `acl_actions` - Access control definitions
- `schedulers` - Scheduled job definitions
- `job_queue` - Background job queue

#### 4. Audit Tables

Track field changes:

```sql
-- Audit table for accounts
CREATE TABLE accounts_audit (
    id char(36) NOT NULL,
    parent_id char(36) NOT NULL,
    date_created datetime,
    created_by char(36),
    field_name varchar(100),
    data_type varchar(100),
    before_value_string varchar(255),
    after_value_string varchar(255),
    before_value_text text,
    after_value_text text,
    PRIMARY KEY (id),
    KEY idx_accounts_audit_parent_id (parent_id)
);
```

#### 5. Custom Tables

User-defined tables:

```sql
-- Custom fields table
CREATE TABLE accounts_cstm (
    id_c char(36) NOT NULL,
    custom_field_c varchar(255),
    PRIMARY KEY (id_c)
);
```

## SugarBean ORM

### Data Access Patterns

```php
// Create
$account = BeanFactory::newBean('Accounts');
$account->name = 'New Account';
$account->save();

// Read
$account = BeanFactory::getBean('Accounts', $id);

// Update
$account->industry = 'Technology';
$account->save();

// Delete (soft delete)
$account->mark_deleted($id);

// Query
$query = new SugarQuery();
$query->from(BeanFactory::newBean('Accounts'));
$query->where()->equals('industry', 'Technology');
$results = $query->execute();
```

### Relationship Management

```php
// Load relationships
$account->load_relationship('contacts');

// Add relationship
$account->contacts->add($contact_id);

// Remove relationship
$account->contacts->delete($account->id, $contact_id);

// Get related beans
$contacts = $account->contacts->getBeans();
```

## Indexing Strategy

### Index Types

1. **Primary Keys**: Automatic on `id` field
2. **Foreign Keys**: On relationship fields
3. **Search Indices**: On commonly searched fields
4. **Composite Indices**: For complex queries

### Common Indices

```sql
-- Name search
CREATE INDEX idx_account_name ON accounts(name, deleted);

-- Assignment and status
CREATE INDEX idx_account_assigned ON accounts(assigned_user_id, deleted);

-- Date range queries
CREATE INDEX idx_account_date ON accounts(date_entered, deleted);

-- Relationship performance
CREATE INDEX idx_acc_cont_acc ON accounts_contacts(account_id, deleted);
CREATE INDEX idx_acc_cont_cont ON accounts_contacts(contact_id, deleted);
```

## Data Types

### Field Type Mapping

| SuiteCRM Type | MySQL Type | Description |
|---------------|------------|-------------|
| id | CHAR(36) | UUID primary key |
| varchar | VARCHAR(n) | Short text |
| name | VARCHAR(255) | Name fields |
| text | TEXT | Long text |
| int | INT | Integer numbers |
| decimal | DECIMAL(26,6) | Financial values |
| bool | TINYINT(1) | Boolean values |
| date | DATE | Date only |
| datetime | DATETIME | Date and time |
| time | TIME | Time only |
| relate | CHAR(36) | Foreign key |
| enum | VARCHAR(100) | Dropdown values |
| multienum | TEXT | Multiple selections |
| currency | DECIMAL(26,6) | Currency amounts |
| encrypted | VARCHAR(255) | Encrypted data |

## Performance Optimization

### Query Optimization

1. **Use Indices Effectively**
```sql
-- Good: Uses index
SELECT * FROM accounts WHERE name LIKE 'Acme%' AND deleted = 0;

-- Bad: Can't use index efficiently
SELECT * FROM accounts WHERE name LIKE '%Acme%';
```

2. **Limit Result Sets**
```php
$query->limit(20)->offset(0);
```

3. **Select Specific Fields**
```php
$query->select(['id', 'name', 'industry']);
```

### Database Configuration

```sql
-- MySQL optimization settings
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
query_cache_size = 64M
max_connections = 200
```

### Maintenance Tasks

1. **Regular Optimization**
```sql
OPTIMIZE TABLE accounts;
ANALYZE TABLE accounts;
```

2. **Cleanup Deleted Records**
```sql
-- Permanently remove soft-deleted records older than 90 days
DELETE FROM accounts 
WHERE deleted = 1 
AND date_modified < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

## Security Considerations

### SQL Injection Prevention

1. **Use Prepared Statements**
```php
$sql = "SELECT * FROM accounts WHERE industry = ?";
$result = $db->pQuery($sql, [$industry]);
```

2. **Quote Values**
```php
$safe_value = $db->quote($unsafe_value);
```

3. **Use SugarQuery**
```php
$query = new SugarQuery();
$query->where()->equals('field', $value); // Automatically escaped
```

### Data Encryption

Sensitive fields can be encrypted:

```php
// In vardefs.php
'ssn' => array(
    'name' => 'ssn',
    'type' => 'encrypted',
    'vname' => 'LBL_SSN',
);
```

## Backup and Recovery

### Backup Strategies

1. **Full Database Backup**
```bash
mysqldump -u user -p suitecrm > backup.sql
```

2. **Incremental Backups**
Use binary logs for point-in-time recovery

3. **Backup Verification**
Regular restoration tests to ensure backup integrity

### Recovery Procedures

1. **Full Restore**
```bash
mysql -u user -p suitecrm < backup.sql
```

2. **Selective Restore**
Restore specific tables or records

## Migration and Upgrades

### Schema Changes

Use SuiteCRM's upgrade-safe methods:

```php
// custom/Extension/modules/Accounts/Ext/Vardefs/custom_fields.php
$dictionary['Account']['fields']['new_field'] = array(
    'name' => 'new_field',
    'type' => 'varchar',
    'len' => 100,
);
```

### Data Migration

```php
// Migration script example
$sql = "UPDATE accounts SET new_field = old_field WHERE new_field IS NULL";
$db->query($sql);
```

## Monitoring and Diagnostics

### Performance Monitoring

1. **Slow Query Log**
```sql
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 2;
```

2. **Query Analysis**
```sql
EXPLAIN SELECT * FROM accounts WHERE industry = 'Technology';
```

### Database Health Checks

```php
// Check table status
$result = $db->query("SHOW TABLE STATUS");

// Check connections
$result = $db->query("SHOW PROCESSLIST");
```

## Best Practices

1. **Always Use Soft Deletes** - Set `deleted = 1` instead of DELETE
2. **UUID Primary Keys** - Use char(36) for all primary keys
3. **Audit Important Fields** - Enable audit for sensitive data
4. **Index Strategically** - Index based on actual query patterns
5. **Regular Maintenance** - Schedule optimization and cleanup
6. **Monitor Performance** - Track slow queries and optimize
7. **Backup Regularly** - Implement automated backup procedures
8. **Use Transactions** - For data consistency in complex operations

## Conclusion

SuiteCRM's database architecture provides a robust foundation for storing and managing CRM data. The combination of a flexible schema design, powerful ORM, and comprehensive database abstraction layer enables developers to build scalable and maintainable CRM solutions while ensuring data integrity and security.