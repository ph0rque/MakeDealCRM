# Accounts Module Design (mdeal_Accounts)

## Overview
The Accounts module manages companies and organizations involved in the M&A process. This includes target companies, portfolio companies, brokers, lenders, law firms, and other business entities. It supports hierarchical relationships and complex organizational structures.

## Module Structure

### Table: mdeal_accounts

### Core Fields

#### Basic Fields (Inherited from Company template)
- `id` (varchar 36) - Primary key
- `name` (varchar 150) - Account/Company name (required)
- `date_entered` (datetime) - Creation timestamp
- `date_modified` (datetime) - Last modification timestamp
- `modified_user_id` (varchar 36) - User who last modified
- `created_by` (varchar 36) - User who created
- `description` (text) - Detailed notes
- `deleted` (bool) - Soft delete flag
- `assigned_user_id` (varchar 36) - Assigned user

#### Account Classification
- `account_type` (enum) - Type of account
  - target_company
  - portfolio_company
  - broker_firm
  - law_firm
  - accounting_firm
  - lender
  - investor
  - vendor
  - customer
  - competitor
  - partner
  - other
- `industry` (varchar 100) - Industry classification
- `sub_industry` (varchar 100) - Sub-industry
- `naics_code` (varchar 10) - NAICS classification
- `sic_code` (varchar 10) - SIC classification

#### Company Information
- `website` (varchar 255) - Company website
- `ticker_symbol` (varchar 10) - If publicly traded
- `ownership_type` (enum)
  - private
  - public
  - private_equity_owned
  - family_owned
  - employee_owned
  - government
- `year_established` (int) - Founding year
- `dba_name` (varchar 150) - Doing Business As
- `tax_id` (varchar 20) - EIN/Tax ID (encrypted)
- `duns_number` (varchar 20) - D&B DUNS

#### Financial Information
- `annual_revenue` (decimal 26,6) - Latest annual revenue
- `revenue_currency_id` (varchar 36) - Currency for revenue
- `ebitda` (decimal 26,6) - Latest EBITDA
- `employee_count` (int) - Number of employees
- `facility_count` (int) - Number of locations

#### Hierarchical Structure
- `parent_id` (varchar 36) - Parent company
- `is_parent` (bool) - Is parent company
- `hierarchy_level` (int) - Level in hierarchy

#### Address Information
- `billing_address_street` (varchar 150)
- `billing_address_city` (varchar 100)
- `billing_address_state` (varchar 100)
- `billing_address_postalcode` (varchar 20)
- `billing_address_country` (varchar 100)
- `shipping_address_street` (varchar 150)
- `shipping_address_city` (varchar 100)
- `shipping_address_state` (varchar 100)
- `shipping_address_postalcode` (varchar 20)
- `shipping_address_country` (varchar 100)
- `same_as_billing` (bool) - Shipping same as billing

#### Contact Information
- `phone_office` (varchar 100) - Main phone
- `phone_alternate` (varchar 100) - Alternate phone
- `phone_fax` (varchar 100) - Fax number
- `email` (varchar 100) - General email

#### Deal-Related Fields
- `rating` (enum) - Account rating
  - hot
  - warm
  - cold
- `account_status` (enum)
  - active
  - inactive
  - prospect
  - closed_won
  - closed_lost
- `deal_count` (int) - Number of related deals
- `total_deal_value` (decimal 26,6) - Sum of all deals
- `last_deal_date` (date) - Most recent deal

#### Compliance & Risk
- `credit_rating` (varchar 10) - Credit score/rating
- `credit_limit` (decimal 26,6) - Credit limit
- `payment_terms` (varchar 100) - Net 30, etc.
- `risk_assessment` (enum)
  - low
  - medium
  - high
  - critical
- `compliance_status` (enum)
  - compliant
  - pending_review
  - non_compliant
  - not_applicable
- `insurance_coverage` (bool) - Has insurance
- `insurance_expiry` (date) - Insurance expiration

#### Portfolio-Specific Fields (for owned companies)
- `acquisition_date` (date) - When acquired
- `acquisition_price` (decimal 26,6) - Purchase price
- `current_valuation` (decimal 26,6) - Current value
- `exit_strategy` (enum)
  - hold_operate
  - strategic_sale
  - financial_sale
  - ipo
  - recapitalization
  - liquidation
- `planned_exit_date` (date) - Target exit
- `integration_status` (enum)
  - not_started
  - in_progress
  - completed
  - on_hold

### Relationships

```php
'relationships' => array(
    // Hierarchical relationship
    'account_parent' => array(
        'lhs_module' => 'mdeal_Accounts',
        'lhs_table' => 'mdeal_accounts',
        'lhs_key' => 'id',
        'rhs_module' => 'mdeal_Accounts',
        'rhs_table' => 'mdeal_accounts',
        'rhs_key' => 'parent_id',
        'relationship_type' => 'one-to-many'
    ),
    
    // Contacts relationship
    'accounts_contacts' => array(
        'lhs_module' => 'mdeal_Accounts',
        'lhs_table' => 'mdeal_accounts',
        'lhs_key' => 'id',
        'rhs_module' => 'mdeal_Contacts',
        'rhs_table' => 'mdeal_contacts',
        'rhs_key' => 'account_id',
        'relationship_type' => 'one-to-many'
    ),
    
    // Many-to-many contacts for complex relationships
    'accounts_contacts_many' => array(
        'lhs_module' => 'mdeal_Accounts',
        'lhs_table' => 'mdeal_accounts',
        'lhs_key' => 'id',
        'rhs_module' => 'mdeal_Contacts',
        'rhs_table' => 'mdeal_contacts',
        'rhs_key' => 'id',
        'relationship_type' => 'many-to-many',
        'join_table' => 'mdeal_accounts_contacts',
        'join_key_lhs' => 'account_id',
        'join_key_rhs' => 'contact_id'
    ),
    
    // Deals relationship
    'accounts_deals' => array(
        'lhs_module' => 'mdeal_Accounts',
        'lhs_table' => 'mdeal_accounts',
        'lhs_key' => 'id',
        'rhs_module' => 'Deals',
        'rhs_table' => 'deals',
        'rhs_key' => 'account_id',
        'relationship_type' => 'one-to-many'
    ),
    
    // Leads relationship
    'accounts_leads' => array(
        'lhs_module' => 'mdeal_Accounts',
        'lhs_table' => 'mdeal_accounts',
        'lhs_key' => 'id',
        'rhs_module' => 'mdeal_Leads',
        'rhs_table' => 'mdeal_leads',
        'rhs_key' => 'account_id',
        'relationship_type' => 'one-to-many'
    ),
    
    // Activities
    'accounts_calls' => array(
        'lhs_module' => 'mdeal_Accounts',
        'lhs_table' => 'mdeal_accounts',
        'lhs_key' => 'id',
        'rhs_module' => 'Calls',
        'rhs_table' => 'calls',
        'rhs_key' => 'parent_id',
        'relationship_type' => 'one-to-many',
        'relationship_role_column' => 'parent_type',
        'relationship_role_column_value' => 'mdeal_Accounts'
    ),
    
    'accounts_meetings' => array(
        'lhs_module' => 'mdeal_Accounts',
        'lhs_table' => 'mdeal_accounts',
        'lhs_key' => 'id',
        'rhs_module' => 'Meetings',
        'rhs_table' => 'meetings',
        'rhs_key' => 'parent_id',
        'relationship_type' => 'one-to-many',
        'relationship_role_column' => 'parent_type',
        'relationship_role_column_value' => 'mdeal_Accounts'
    ),
    
    'accounts_tasks' => array(
        'lhs_module' => 'mdeal_Accounts',
        'lhs_table' => 'mdeal_accounts',
        'lhs_key' => 'id',
        'rhs_module' => 'Tasks',
        'rhs_table' => 'tasks',
        'rhs_key' => 'parent_id',
        'relationship_type' => 'one-to-many',
        'relationship_role_column' => 'parent_type',
        'relationship_role_column_value' => 'mdeal_Accounts'
    ),
    
    'accounts_notes' => array(
        'lhs_module' => 'mdeal_Accounts',
        'lhs_table' => 'mdeal_accounts',
        'lhs_key' => 'id',
        'rhs_module' => 'Notes',
        'rhs_table' => 'notes',
        'rhs_key' => 'parent_id',
        'relationship_type' => 'one-to-many',
        'relationship_role_column' => 'parent_type',
        'relationship_role_column_value' => 'mdeal_Accounts'
    ),
    
    'accounts_emails' => array(
        'lhs_module' => 'mdeal_Accounts',
        'lhs_table' => 'mdeal_accounts',
        'lhs_key' => 'id',
        'rhs_module' => 'Emails',
        'rhs_table' => 'emails',
        'rhs_key' => 'parent_id',
        'relationship_type' => 'one-to-many',
        'relationship_role_column' => 'parent_type',
        'relationship_role_column_value' => 'mdeal_Accounts'
    ),
    
    // Documents
    'accounts_documents' => array(
        'lhs_module' => 'mdeal_Accounts',
        'lhs_table' => 'mdeal_accounts',
        'lhs_key' => 'id',
        'rhs_module' => 'Documents',
        'rhs_table' => 'documents',
        'rhs_key' => 'id',
        'relationship_type' => 'many-to-many',
        'join_table' => 'mdeal_accounts_documents',
        'join_key_lhs' => 'account_id',
        'join_key_rhs' => 'document_id'
    ),
    
    // Contracts
    'accounts_contracts' => array(
        'lhs_module' => 'mdeal_Accounts',
        'lhs_table' => 'mdeal_accounts',
        'lhs_key' => 'id',
        'rhs_module' => 'Contracts',
        'rhs_table' => 'contracts',
        'rhs_key' => 'account_id',
        'relationship_type' => 'one-to-many'
    ),
    
    // User relationships
    'accounts_assigned_user' => array(
        'lhs_module' => 'Users',
        'lhs_table' => 'users',
        'lhs_key' => 'id',
        'rhs_module' => 'mdeal_Accounts',
        'rhs_table' => 'mdeal_accounts',
        'rhs_key' => 'assigned_user_id',
        'relationship_type' => 'one-to-many'
    )
)
```

### Indexes

```php
'indices' => array(
    array('name' => 'idx_account_name', 'type' => 'index', 'fields' => array('name', 'deleted')),
    array('name' => 'idx_account_type', 'type' => 'index', 'fields' => array('account_type', 'deleted')),
    array('name' => 'idx_account_industry', 'type' => 'index', 'fields' => array('industry', 'deleted')),
    array('name' => 'idx_account_assigned', 'type' => 'index', 'fields' => array('assigned_user_id', 'deleted')),
    array('name' => 'idx_account_parent', 'type' => 'index', 'fields' => array('parent_id', 'deleted')),
    array('name' => 'idx_account_status', 'type' => 'index', 'fields' => array('account_status', 'deleted')),
    array('name' => 'idx_account_website', 'type' => 'index', 'fields' => array('website')),
    array('name' => 'idx_account_tax_id', 'type' => 'index', 'fields' => array('tax_id'))
)
```

## Business Logic

### Account Hierarchy Management

1. **Parent-Child Relationships**
   - Unlimited hierarchy levels
   - Roll-up financials to parent
   - Inherit certain attributes
   - Consolidated reporting

2. **Hierarchy Operations**
   - Move branches
   - Merge organizations
   - Split entities
   - Cascade updates

### Account Types and Workflows

#### Target Company Workflow
1. Initial research and qualification
2. Financial data collection
3. Deal progression tracking
4. Due diligence management
5. Post-acquisition transition

#### Portfolio Company Workflow
1. Onboarding and integration
2. Performance monitoring
3. Strategic planning
4. Value creation tracking
5. Exit preparation

#### Service Provider Workflow
1. Vendor qualification
2. Contract management
3. Performance evaluation
4. Relationship tracking
5. Renewal management

### Financial Tracking

1. **Revenue Tracking**
   - Historical revenue
   - Growth trends
   - Seasonality analysis
   - Forecast vs. actual

2. **Valuation Management**
   - Acquisition multiples
   - Current valuation
   - Value creation tracking
   - Exit projections

3. **Portfolio Analytics**
   - Cross-portfolio metrics
   - Industry diversification
   - Geographic distribution
   - Risk assessment

### Duplicate Management

1. **Detection Rules**
   - Name similarity
   - Website domain
   - Tax ID matching
   - Address matching

2. **Merge Process**
   - Preserve relationships
   - Combine financials
   - Merge activities
   - Maintain audit trail

### Compliance and Risk

1. **Due Diligence**
   - KYC requirements
   - Background checks
   - Financial verification
   - Reference checks

2. **Ongoing Monitoring**
   - Credit monitoring
   - Insurance tracking
   - Compliance updates
   - Risk reassessment

## Views

### List View
- Account Name
- Type
- Industry
- Annual Revenue
- Status
- Assigned User
- Last Activity
- Deal Count

### Detail View
- Account Information panel
- Financial Summary panel
- Hierarchy view
- Contacts subpanel
- Deals subpanel
- Activities subpanel
- History subpanel
- Documents subpanel
- Contracts subpanel

### Edit View
- Tabbed interface:
  - Company Info
  - Financial Data
  - Address Info
  - Compliance
  - Portfolio Details (if applicable)

### Special Views
- Hierarchy Tree View
- Portfolio Dashboard
- Financial Timeline
- Risk Matrix
- Geographic Map

## Security

- Row-level security based on assignment
- Financial data access controls
- Sensitive field encryption (Tax ID, etc.)
- Hierarchy-based permissions
- Audit trail for all changes

## Integration Points

1. **Financial Systems**
   - QuickBooks integration
   - Banking data feeds
   - Credit bureau APIs
   - Valuation services

2. **Data Enrichment**
   - D&B integration
   - Industry databases
   - News monitoring
   - Social media monitoring

3. **Document Management**
   - Data room integration
   - Contract repository
   - Financial statements
   - Legal documents

4. **Portfolio Management**
   - Board reporting
   - LP reporting
   - Performance dashboards
   - Exit planning tools

## Automation

1. **Data Maintenance**
   - Address standardization
   - Industry classification
   - Financial updates
   - Hierarchy optimization

2. **Alerts and Monitoring**
   - Revenue changes
   - Risk indicators
   - Compliance deadlines
   - Insurance renewals

3. **Workflow Automation**
   - Onboarding checklists
   - Periodic reviews
   - Report generation
   - Task assignment

## Reporting

1. **Standard Reports**
   - Account listing
   - Hierarchy report
   - Financial summary
   - Activity summary

2. **Portfolio Reports**
   - Performance metrics
   - Value creation
   - Risk assessment
   - Exit readiness

3. **Analytics**
   - Industry analysis
   - Geographic distribution
   - Growth trends
   - Comparative analysis