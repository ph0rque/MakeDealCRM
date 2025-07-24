# Contacts Module Design (mdeal_Contacts)

## Overview
The Contacts module manages all individuals involved in deals, including sellers, brokers, advisors, attorneys, accountants, and other stakeholders. It supports complex many-to-many relationships with Deals, Accounts, and other modules.

## Module Structure

### Table: mdeal_contacts

### Core Fields

#### Basic Fields (Inherited from Person template)
- `id` (varchar 36) - Primary key
- `date_entered` (datetime) - Creation timestamp
- `date_modified` (datetime) - Last modification timestamp
- `modified_user_id` (varchar 36) - User who last modified
- `created_by` (varchar 36) - User who created
- `description` (text) - Detailed notes
- `deleted` (bool) - Soft delete flag
- `assigned_user_id` (varchar 36) - Assigned user
- `salutation` (varchar 20) - Mr., Ms., Dr., etc.
- `first_name` (varchar 100) - First name
- `last_name` (varchar 100) - Last name (required)
- `full_name` (varchar 255) - Computed full name

#### Contact Information
- `title` (varchar 100) - Job title
- `department` (varchar 100) - Department
- `phone_work` (varchar 100) - Work phone
- `phone_mobile` (varchar 100) - Mobile phone
- `phone_home` (varchar 100) - Home phone
- `phone_other` (varchar 100) - Other phone
- `phone_fax` (varchar 100) - Fax number
- `email_address` (varchar 100) - Primary email
- `email_address2` (varchar 100) - Secondary email
- `assistant` (varchar 100) - Assistant name
- `assistant_phone` (varchar 100) - Assistant phone

#### Professional Information
- `contact_type` (enum) - Type of contact
  - seller
  - broker
  - attorney
  - accountant
  - lender
  - advisor
  - employee
  - vendor
  - customer
  - investor
  - other
- `contact_subtype` (varchar 100) - Further classification
- `account_id` (varchar 36) - Primary account/company
- `reports_to_id` (varchar 36) - Manager/supervisor
- `lead_source` (varchar 100) - How contact was acquired
- `linkedin_url` (varchar 255) - LinkedIn profile

#### Address Information
- `primary_address_street` (varchar 150)
- `primary_address_city` (varchar 100)
- `primary_address_state` (varchar 100)
- `primary_address_postalcode` (varchar 20)
- `primary_address_country` (varchar 100)
- `alt_address_street` (varchar 150)
- `alt_address_city` (varchar 100)
- `alt_address_state` (varchar 100)
- `alt_address_postalcode` (varchar 20)
- `alt_address_country` (varchar 100)

#### Deal-Specific Fields
- `preferred_contact_method` (enum)
  - email
  - phone_mobile
  - phone_work
  - text_message
  - in_person
- `best_time_to_contact` (varchar 100)
- `timezone` (varchar 50)
- `communication_style` (text) - Notes on preferences
- `decision_role` (enum) - Role in deal decisions
  - decision_maker
  - influencer
  - gatekeeper
  - champion
  - technical_evaluator
  - financial_approver
  - end_user
- `influence_level` (enum)
  - high
  - medium
  - low

#### Relationship Tracking
- `relationship_strength` (enum)
  - strong
  - good
  - developing
  - weak
  - damaged
- `last_interaction_date` (datetime)
- `interaction_count` (int) - Total interactions
- `response_rate` (decimal 5,2) - Email response %
- `trust_level` (int) - 1-10 scale

#### Additional Fields
- `do_not_call` (bool) - DNC flag
- `email_opt_out` (bool) - Email opt-out
- `invalid_email` (bool) - Email bounce flag
- `birthdate` (date) - Birthday
- `picture` (varchar 255) - Profile picture path
- `confidentiality_agreement` (bool) - Has signed NDA
- `background_check_completed` (bool)
- `background_check_date` (date)
- `notes_private` (text) - Internal only notes

### Relationships

```php
'relationships' => array(
    // Many-to-many with Deals
    'contacts_deals' => array(
        'lhs_module' => 'mdeal_Contacts',
        'lhs_table' => 'mdeal_contacts',
        'lhs_key' => 'id',
        'rhs_module' => 'Deals',
        'rhs_table' => 'deals',
        'rhs_key' => 'id',
        'relationship_type' => 'many-to-many',
        'join_table' => 'mdeal_contacts_deals',
        'join_key_lhs' => 'contact_id',
        'join_key_rhs' => 'deal_id',
        'relationship_fields' => array(
            'contact_role' => array('type' => 'varchar'),
            'primary_contact' => array('type' => 'bool')
        )
    ),
    
    // Many-to-many with Accounts
    'contacts_accounts' => array(
        'lhs_module' => 'mdeal_Contacts',
        'lhs_table' => 'mdeal_contacts',
        'lhs_key' => 'id',
        'rhs_module' => 'mdeal_Accounts',
        'rhs_table' => 'mdeal_accounts',
        'rhs_key' => 'id',
        'relationship_type' => 'many-to-many',
        'join_table' => 'mdeal_contacts_accounts',
        'join_key_lhs' => 'contact_id',
        'join_key_rhs' => 'account_id',
        'relationship_fields' => array(
            'title' => array('type' => 'varchar'),
            'department' => array('type' => 'varchar'),
            'is_primary' => array('type' => 'bool')
        )
    ),
    
    // Direct relationship to primary account
    'contact_account' => array(
        'lhs_module' => 'mdeal_Accounts',
        'lhs_table' => 'mdeal_accounts',
        'lhs_key' => 'id',
        'rhs_module' => 'mdeal_Contacts',
        'rhs_table' => 'mdeal_contacts',
        'rhs_key' => 'account_id',
        'relationship_type' => 'one-to-many'
    ),
    
    // Reports to relationship
    'contact_reports_to' => array(
        'lhs_module' => 'mdeal_Contacts',
        'lhs_table' => 'mdeal_contacts',
        'lhs_key' => 'id',
        'rhs_module' => 'mdeal_Contacts',
        'rhs_table' => 'mdeal_contacts',
        'rhs_key' => 'reports_to_id',
        'relationship_type' => 'one-to-many'
    ),
    
    // Activities
    'contacts_calls' => array(
        'lhs_module' => 'mdeal_Contacts',
        'lhs_table' => 'mdeal_contacts',
        'lhs_key' => 'id',
        'rhs_module' => 'Calls',
        'rhs_table' => 'calls',
        'rhs_key' => 'parent_id',
        'relationship_type' => 'one-to-many',
        'relationship_role_column' => 'parent_type',
        'relationship_role_column_value' => 'mdeal_Contacts'
    ),
    
    'contacts_meetings' => array(
        'lhs_module' => 'mdeal_Contacts',
        'lhs_table' => 'mdeal_contacts',
        'lhs_key' => 'id',
        'rhs_module' => 'Meetings',
        'rhs_table' => 'meetings',
        'rhs_key' => 'parent_id',
        'relationship_type' => 'one-to-many',
        'relationship_role_column' => 'parent_type',
        'relationship_role_column_value' => 'mdeal_Contacts'
    ),
    
    'contacts_tasks' => array(
        'lhs_module' => 'mdeal_Contacts',
        'lhs_table' => 'mdeal_contacts',
        'lhs_key' => 'id',
        'rhs_module' => 'Tasks',
        'rhs_table' => 'tasks',
        'rhs_key' => 'parent_id',
        'relationship_type' => 'one-to-many',
        'relationship_role_column' => 'parent_type',
        'relationship_role_column_value' => 'mdeal_Contacts'
    ),
    
    'contacts_notes' => array(
        'lhs_module' => 'mdeal_Contacts',
        'lhs_table' => 'mdeal_contacts',
        'lhs_key' => 'id',
        'rhs_module' => 'Notes',
        'rhs_table' => 'notes',
        'rhs_key' => 'parent_id',
        'relationship_type' => 'one-to-many',
        'relationship_role_column' => 'parent_type',
        'relationship_role_column_value' => 'mdeal_Contacts'
    ),
    
    'contacts_emails' => array(
        'lhs_module' => 'mdeal_Contacts',
        'lhs_table' => 'mdeal_contacts',
        'lhs_key' => 'id',
        'rhs_module' => 'Emails',
        'rhs_table' => 'emails',
        'rhs_key' => 'parent_id',
        'relationship_type' => 'one-to-many',
        'relationship_role_column' => 'parent_type',
        'relationship_role_column_value' => 'mdeal_Contacts'
    ),
    
    // Documents
    'contacts_documents' => array(
        'lhs_module' => 'mdeal_Contacts',
        'lhs_table' => 'mdeal_contacts',
        'lhs_key' => 'id',
        'rhs_module' => 'Documents',
        'rhs_table' => 'documents',
        'rhs_key' => 'id',
        'relationship_type' => 'many-to-many',
        'join_table' => 'mdeal_contacts_documents',
        'join_key_lhs' => 'contact_id',
        'join_key_rhs' => 'document_id'
    ),
    
    // User relationships
    'contacts_assigned_user' => array(
        'lhs_module' => 'Users',
        'lhs_table' => 'users',
        'lhs_key' => 'id',
        'rhs_module' => 'mdeal_Contacts',
        'rhs_table' => 'mdeal_contacts',
        'rhs_key' => 'assigned_user_id',
        'relationship_type' => 'one-to-many'
    )
)
```

### Indexes

```php
'indices' => array(
    array('name' => 'idx_contact_name', 'type' => 'index', 'fields' => array('last_name', 'first_name', 'deleted')),
    array('name' => 'idx_contact_assigned', 'type' => 'index', 'fields' => array('assigned_user_id', 'deleted')),
    array('name' => 'idx_contact_email', 'type' => 'index', 'fields' => array('email_address')),
    array('name' => 'idx_contact_account', 'type' => 'index', 'fields' => array('account_id', 'deleted')),
    array('name' => 'idx_contact_type', 'type' => 'index', 'fields' => array('contact_type', 'deleted')),
    array('name' => 'idx_contact_reports_to', 'type' => 'index', 'fields' => array('reports_to_id')),
    array('name' => 'idx_contact_phone', 'type' => 'index', 'fields' => array('phone_mobile', 'phone_work'))
)
```

## Business Logic

### Contact Roles in Deals

Each contact can have multiple roles across different deals:

1. **Seller** - Business owner/shareholder
2. **Broker** - Intermediary/M&A advisor
3. **Attorney** - Legal counsel (buyer/seller side)
4. **Accountant** - Financial advisor/CPA
5. **Lender** - Debt financing provider
6. **Key Employee** - Important for transition
7. **Advisor** - Other professional services

### Relationship Management

1. **Interaction Tracking**
   - Auto-increment interaction count
   - Update last interaction date
   - Calculate response rates
   - Track communication preferences

2. **Relationship Scoring**
   - Frequency of interaction
   - Response timeliness
   - Deal involvement
   - Trust indicators

3. **Contact Hierarchy**
   - Organization chart view
   - Decision-making flow
   - Influence mapping
   - Reporting relationships

### Duplicate Detection

1. **Matching Criteria**
   - Email address (primary)
   - Phone numbers
   - Name + Company
   - LinkedIn URL

2. **Merge Process**
   - Preserve all relationships
   - Combine activity history
   - Merge custom fields
   - Audit trail

### Communication Management

1. **Preferred Channels**
   - Respect contact preferences
   - Time zone awareness
   - Do not disturb rules
   - Communication templates

2. **Email Integration**
   - Sync with email client
   - Track opens/clicks
   - Auto-log to CRM
   - Template personalization

## Views

### List View
- Full Name
- Account/Company
- Title
- Contact Type
- Email
- Phone
- Last Activity
- Assigned User

### Detail View
- Contact Information panel
- Professional Details panel
- Communication Preferences panel
- Deals subpanel (with roles)
- Activities subpanel
- History subpanel
- Documents subpanel
- Relationships chart

### Edit View
- Tabbed interface:
  - Personal Info
  - Professional Info
  - Contact Details
  - Preferences
  - Internal Notes

### Special Views
- Organization Chart
- Influence Map
- Communication Timeline
- Deal Involvement

## Security

- Row-level security based on assignment
- Sensitive field masking (SSN, etc.)
- Private notes visibility
- Deal-based access control
- Audit trail for all changes

## Integration Points

1. **Email Clients**
   - Outlook plugin
   - Gmail integration
   - Email tracking
   - Calendar sync

2. **Social Media**
   - LinkedIn integration
   - Profile enrichment
   - Social selling tools
   - Activity monitoring

3. **Communication Tools**
   - VoIP integration
   - SMS capabilities
   - Video conferencing
   - Call recording

4. **Data Enrichment**
   - Business card scanning
   - Data append services
   - Verification services
   - Background checks

## Automation

1. **Data Quality**
   - Email verification
   - Phone standardization
   - Address validation
   - Duplicate prevention

2. **Engagement**
   - Birthday reminders
   - Follow-up scheduling
   - Inactivity alerts
   - Re-engagement campaigns

3. **Relationship Building**
   - Touch point tracking
   - Interaction scoring
   - Next best action
   - Referral tracking