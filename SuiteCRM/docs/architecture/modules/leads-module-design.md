# Leads Module Design (mdeal_Leads)

## Overview
The Leads module is designed for tracking potential business acquisitions in their earliest stages. It integrates with the pipeline system to manage lead qualification and conversion to Deals.

## Module Structure

### Table: mdeal_leads

### Core Fields

#### Basic Fields (Inherited from SugarBean)
- `id` (varchar 36) - Primary key
- `name` (varchar 255) - Lead name/company name
- `date_entered` (datetime) - Creation timestamp
- `date_modified` (datetime) - Last modification timestamp
- `modified_user_id` (varchar 36) - User who last modified
- `created_by` (varchar 36) - User who created
- `description` (text) - Detailed notes about the lead
- `deleted` (bool) - Soft delete flag
- `assigned_user_id` (varchar 36) - Assigned user

#### Lead-Specific Fields

##### Contact Information
- `first_name` (varchar 100) - Contact first name
- `last_name` (varchar 100) - Contact last name
- `title` (varchar 100) - Contact title
- `phone_work` (varchar 100) - Business phone
- `phone_mobile` (varchar 100) - Mobile phone
- `email_address` (varchar 100) - Primary email
- `website` (varchar 255) - Company website

##### Company Information
- `company_name` (varchar 255) - Business name
- `industry` (varchar 100) - Industry classification
- `annual_revenue` (decimal 26,6) - Estimated annual revenue
- `employee_count` (int) - Number of employees
- `years_in_business` (int) - Company age

##### Lead Qualification
- `lead_source` (enum) - Source of lead
  - broker_network
  - direct_outreach
  - inbound_inquiry
  - referral
  - conference_event
  - online_marketplace
  - other
- `lead_source_description` (text) - Additional source details
- `status` (enum) - Lead status
  - new
  - contacted
  - qualified
  - unqualified
  - converted
  - dead
- `status_description` (text) - Reason for status
- `rating` (enum) - Lead quality rating
  - hot
  - warm
  - cold

##### Pipeline Integration
- `pipeline_stage` (enum) - Pre-deal pipeline stage
  - initial_contact
  - qualification
  - initial_interest
  - ready_to_convert
- `days_in_stage` (int) - Time in current stage
- `date_entered_stage` (datetime) - When entered current stage
- `qualification_score` (int) - 0-100 score
- `converted_deal_id` (varchar 36) - Link to created deal

##### Location
- `primary_address_street` (varchar 150)
- `primary_address_city` (varchar 100)
- `primary_address_state` (varchar 100)
- `primary_address_postalcode` (varchar 20)
- `primary_address_country` (varchar 100)

##### Additional Tracking
- `do_not_call` (bool) - DNC flag
- `email_opt_out` (bool) - Email opt-out flag
- `invalid_email` (bool) - Email validation flag
- `last_activity_date` (datetime) - Last interaction
- `next_follow_up_date` (date) - Scheduled follow-up

### Relationships

```php
'relationships' => array(
    // User relationships
    'leads_assigned_user' => array(
        'lhs_module' => 'Users',
        'lhs_table' => 'users',
        'lhs_key' => 'id',
        'rhs_module' => 'mdeal_Leads',
        'rhs_table' => 'mdeal_leads',
        'rhs_key' => 'assigned_user_id',
        'relationship_type' => 'one-to-many'
    ),
    
    // Activities
    'leads_calls' => array(
        'lhs_module' => 'mdeal_Leads',
        'lhs_table' => 'mdeal_leads',
        'lhs_key' => 'id',
        'rhs_module' => 'Calls',
        'rhs_table' => 'calls',
        'rhs_key' => 'parent_id',
        'relationship_type' => 'one-to-many',
        'relationship_role_column' => 'parent_type',
        'relationship_role_column_value' => 'mdeal_Leads'
    ),
    
    'leads_meetings' => array(
        'lhs_module' => 'mdeal_Leads',
        'lhs_table' => 'mdeal_leads',
        'lhs_key' => 'id',
        'rhs_module' => 'Meetings',
        'rhs_table' => 'meetings',
        'rhs_key' => 'parent_id',
        'relationship_type' => 'one-to-many',
        'relationship_role_column' => 'parent_type',
        'relationship_role_column_value' => 'mdeal_Leads'
    ),
    
    'leads_tasks' => array(
        'lhs_module' => 'mdeal_Leads',
        'lhs_table' => 'mdeal_leads',
        'lhs_key' => 'id',
        'rhs_module' => 'Tasks',
        'rhs_table' => 'tasks',
        'rhs_key' => 'parent_id',
        'relationship_type' => 'one-to-many',
        'relationship_role_column' => 'parent_type',
        'relationship_role_column_value' => 'mdeal_Leads'
    ),
    
    'leads_notes' => array(
        'lhs_module' => 'mdeal_Leads',
        'lhs_table' => 'mdeal_leads',
        'lhs_key' => 'id',
        'rhs_module' => 'Notes',
        'rhs_table' => 'notes',
        'rhs_key' => 'parent_id',
        'relationship_type' => 'one-to-many',
        'relationship_role_column' => 'parent_type',
        'relationship_role_column_value' => 'mdeal_Leads'
    ),
    
    'leads_emails' => array(
        'lhs_module' => 'mdeal_Leads',
        'lhs_table' => 'mdeal_leads',
        'lhs_key' => 'id',
        'rhs_module' => 'Emails',
        'rhs_table' => 'emails',
        'rhs_key' => 'parent_id',
        'relationship_type' => 'one-to-many',
        'relationship_role_column' => 'parent_type',
        'relationship_role_column_value' => 'mdeal_Leads'
    ),
    
    // Documents
    'leads_documents' => array(
        'lhs_module' => 'mdeal_Leads',
        'lhs_table' => 'mdeal_leads',
        'lhs_key' => 'id',
        'rhs_module' => 'Documents',
        'rhs_table' => 'documents',
        'rhs_key' => 'id',
        'relationship_type' => 'many-to-many',
        'join_table' => 'mdeal_leads_documents',
        'join_key_lhs' => 'lead_id',
        'join_key_rhs' => 'document_id'
    ),
    
    // Campaign relationship
    'leads_campaigns' => array(
        'lhs_module' => 'Campaigns',
        'lhs_table' => 'campaigns',
        'lhs_key' => 'id',
        'rhs_module' => 'mdeal_Leads',
        'rhs_table' => 'mdeal_leads',
        'rhs_key' => 'campaign_id',
        'relationship_type' => 'one-to-many'
    )
)
```

### Indexes

```php
'indices' => array(
    array('name' => 'idx_lead_name', 'type' => 'index', 'fields' => array('company_name', 'deleted')),
    array('name' => 'idx_lead_status', 'type' => 'index', 'fields' => array('status', 'deleted')),
    array('name' => 'idx_lead_assigned', 'type' => 'index', 'fields' => array('assigned_user_id', 'deleted')),
    array('name' => 'idx_lead_source', 'type' => 'index', 'fields' => array('lead_source', 'deleted')),
    array('name' => 'idx_lead_rating', 'type' => 'index', 'fields' => array('rating', 'deleted')),
    array('name' => 'idx_lead_pipeline', 'type' => 'index', 'fields' => array('pipeline_stage', 'deleted')),
    array('name' => 'idx_lead_email', 'type' => 'index', 'fields' => array('email_address')),
    array('name' => 'idx_lead_converted', 'type' => 'index', 'fields' => array('status', 'converted_deal_id'))
)
```

## Business Logic

### Lead Qualification Process

1. **Initial Contact Stage**
   - Capture basic information
   - Verify contact details
   - Initial source tracking

2. **Qualification Stage**
   - Revenue verification
   - Industry fit assessment
   - Calculate qualification score

3. **Initial Interest Stage**
   - Gauge seller motivation
   - Preliminary valuation discussion
   - NDA consideration

4. **Ready to Convert Stage**
   - All key information collected
   - Qualification score > 70
   - Decision to pursue as deal

### Conversion to Deal

When converting a Lead to a Deal:

1. Create new Deal record with:
   - Copy company information
   - Set initial deal status to "Sourcing"
   - Link back to original lead
   - Copy all activities

2. Create Contact record with:
   - Contact person information
   - Link to new Deal
   - Preserve communication history

3. Update Lead record:
   - Set status to "converted"
   - Set converted_deal_id
   - Prevent further edits

### Automation Rules

1. **Auto-Assignment**
   - Round-robin by source
   - Territory-based assignment
   - Load balancing

2. **Follow-up Reminders**
   - Auto-create tasks based on stage
   - Escalate stale leads
   - Email sequence triggers

3. **Qualification Scoring**
   - Industry fit (0-25 points)
   - Revenue size (0-25 points)
   - Response rate (0-25 points)
   - Seller motivation (0-25 points)

4. **Pipeline Movement**
   - Auto-advance based on activities
   - Stale lead detection
   - Conversion readiness alerts

## Views

### List View
- Company Name
- Contact Name
- Status
- Rating
- Lead Source
- Assigned User
- Last Activity
- Next Follow-up

### Detail View
- Lead Information panel
- Contact Information panel
- Qualification panel
- Activities subpanel
- History subpanel
- Documents subpanel

### Edit View
- Tabbed interface:
  - Overview (basic info)
  - Contact Details
  - Qualification
  - Other Info

## Security

- Row-level security based on assignment
- Team-based visibility optional
- Lead source masking for confidential sources
- Activity history preservation

## Integration Points

1. **Email Integration**
   - Auto-capture inbound emails
   - Track email opens/clicks
   - Template management

2. **Calendar Integration**
   - Follow-up scheduling
   - Meeting coordination
   - Task synchronization

3. **Marketing Integration**
   - Campaign tracking
   - Lead scoring updates
   - Source attribution

4. **Reporting**
   - Conversion funnel
   - Source effectiveness
   - User performance
   - Pipeline velocity