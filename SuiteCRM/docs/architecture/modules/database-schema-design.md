# Database Schema Design for MakeDeal CRM Modules

## Overview
This document defines the database schema for the Leads, Contacts, and Accounts modules, including their relationships and integration with the existing Deals module.

## 1. Leads Module Schema

### Table: mdeal_leads

```sql
CREATE TABLE mdeal_leads (
    -- Basic fields
    id VARCHAR(36) NOT NULL PRIMARY KEY,
    name VARCHAR(255),
    date_entered DATETIME,
    date_modified DATETIME,
    modified_user_id VARCHAR(36),
    created_by VARCHAR(36),
    description TEXT,
    deleted BOOLEAN DEFAULT 0,
    assigned_user_id VARCHAR(36),
    
    -- Contact information
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    title VARCHAR(100),
    phone_work VARCHAR(100),
    phone_mobile VARCHAR(100),
    email_address VARCHAR(100),
    website VARCHAR(255),
    
    -- Company information
    company_name VARCHAR(255),
    industry VARCHAR(100),
    annual_revenue DECIMAL(26,6),
    employee_count INT,
    years_in_business INT,
    
    -- Lead qualification
    lead_source VARCHAR(100),
    lead_source_description TEXT,
    status VARCHAR(100) DEFAULT 'new',
    status_description TEXT,
    rating VARCHAR(20),
    
    -- Pipeline integration
    pipeline_stage VARCHAR(50) DEFAULT 'initial_contact',
    days_in_stage INT DEFAULT 0,
    date_entered_stage DATETIME,
    qualification_score INT DEFAULT 0,
    converted_deal_id VARCHAR(36),
    
    -- Location
    primary_address_street VARCHAR(150),
    primary_address_city VARCHAR(100),
    primary_address_state VARCHAR(100),
    primary_address_postalcode VARCHAR(20),
    primary_address_country VARCHAR(100),
    
    -- Additional tracking
    do_not_call BOOLEAN DEFAULT 0,
    email_opt_out BOOLEAN DEFAULT 0,
    invalid_email BOOLEAN DEFAULT 0,
    last_activity_date DATETIME,
    next_follow_up_date DATE,
    campaign_id VARCHAR(36),
    
    -- Indexes
    INDEX idx_lead_name (company_name, deleted),
    INDEX idx_lead_status (status, deleted),
    INDEX idx_lead_assigned (assigned_user_id, deleted),
    INDEX idx_lead_source (lead_source, deleted),
    INDEX idx_lead_rating (rating, deleted),
    INDEX idx_lead_pipeline (pipeline_stage, deleted),
    INDEX idx_lead_email (email_address),
    INDEX idx_lead_converted (status, converted_deal_id),
    
    -- Foreign keys
    FOREIGN KEY (assigned_user_id) REFERENCES users(id),
    FOREIGN KEY (modified_user_id) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (converted_deal_id) REFERENCES deals(id),
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: mdeal_leads_audit

```sql
CREATE TABLE mdeal_leads_audit (
    id VARCHAR(36) NOT NULL PRIMARY KEY,
    parent_id VARCHAR(36) NOT NULL,
    date_created DATETIME,
    created_by VARCHAR(36),
    field_name VARCHAR(100),
    data_type VARCHAR(100),
    before_value_string VARCHAR(255),
    after_value_string VARCHAR(255),
    before_value_text TEXT,
    after_value_text TEXT,
    
    INDEX idx_leads_audit_parent_id (parent_id),
    FOREIGN KEY (parent_id) REFERENCES mdeal_leads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: mdeal_leads_documents

```sql
CREATE TABLE mdeal_leads_documents (
    id VARCHAR(36) NOT NULL PRIMARY KEY,
    lead_id VARCHAR(36) NOT NULL,
    document_id VARCHAR(36) NOT NULL,
    date_modified DATETIME,
    deleted BOOLEAN DEFAULT 0,
    
    INDEX idx_lead_doc_lead (lead_id, deleted),
    INDEX idx_lead_doc_document (document_id, deleted),
    FOREIGN KEY (lead_id) REFERENCES mdeal_leads(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 2. Contacts Module Schema

### Table: mdeal_contacts

```sql
CREATE TABLE mdeal_contacts (
    -- Basic fields
    id VARCHAR(36) NOT NULL PRIMARY KEY,
    date_entered DATETIME,
    date_modified DATETIME,
    modified_user_id VARCHAR(36),
    created_by VARCHAR(36),
    description TEXT,
    deleted BOOLEAN DEFAULT 0,
    assigned_user_id VARCHAR(36),
    
    -- Person fields
    salutation VARCHAR(20),
    first_name VARCHAR(100),
    last_name VARCHAR(100) NOT NULL,
    full_name VARCHAR(255) GENERATED ALWAYS AS (CONCAT_WS(' ', salutation, first_name, last_name)) STORED,
    
    -- Contact information
    title VARCHAR(100),
    department VARCHAR(100),
    phone_work VARCHAR(100),
    phone_mobile VARCHAR(100),
    phone_home VARCHAR(100),
    phone_other VARCHAR(100),
    phone_fax VARCHAR(100),
    email_address VARCHAR(100),
    email_address2 VARCHAR(100),
    assistant VARCHAR(100),
    assistant_phone VARCHAR(100),
    
    -- Professional information
    contact_type VARCHAR(50),
    contact_subtype VARCHAR(100),
    account_id VARCHAR(36),
    reports_to_id VARCHAR(36),
    lead_source VARCHAR(100),
    linkedin_url VARCHAR(255),
    
    -- Addresses
    primary_address_street VARCHAR(150),
    primary_address_city VARCHAR(100),
    primary_address_state VARCHAR(100),
    primary_address_postalcode VARCHAR(20),
    primary_address_country VARCHAR(100),
    alt_address_street VARCHAR(150),
    alt_address_city VARCHAR(100),
    alt_address_state VARCHAR(100),
    alt_address_postalcode VARCHAR(20),
    alt_address_country VARCHAR(100),
    
    -- Deal-specific fields
    preferred_contact_method VARCHAR(50),
    best_time_to_contact VARCHAR(100),
    timezone VARCHAR(50),
    communication_style TEXT,
    decision_role VARCHAR(50),
    influence_level VARCHAR(20),
    
    -- Relationship tracking
    relationship_strength VARCHAR(20),
    last_interaction_date DATETIME,
    interaction_count INT DEFAULT 0,
    response_rate DECIMAL(5,2),
    trust_level INT,
    
    -- Additional fields
    do_not_call BOOLEAN DEFAULT 0,
    email_opt_out BOOLEAN DEFAULT 0,
    invalid_email BOOLEAN DEFAULT 0,
    birthdate DATE,
    picture VARCHAR(255),
    confidentiality_agreement BOOLEAN DEFAULT 0,
    background_check_completed BOOLEAN DEFAULT 0,
    background_check_date DATE,
    notes_private TEXT,
    
    -- Indexes
    INDEX idx_contact_name (last_name, first_name, deleted),
    INDEX idx_contact_assigned (assigned_user_id, deleted),
    INDEX idx_contact_email (email_address),
    INDEX idx_contact_account (account_id, deleted),
    INDEX idx_contact_type (contact_type, deleted),
    INDEX idx_contact_reports_to (reports_to_id),
    INDEX idx_contact_phone (phone_mobile, phone_work),
    
    -- Foreign keys
    FOREIGN KEY (assigned_user_id) REFERENCES users(id),
    FOREIGN KEY (modified_user_id) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (account_id) REFERENCES mdeal_accounts(id),
    FOREIGN KEY (reports_to_id) REFERENCES mdeal_contacts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: mdeal_contacts_deals

```sql
CREATE TABLE mdeal_contacts_deals (
    id VARCHAR(36) NOT NULL PRIMARY KEY,
    contact_id VARCHAR(36) NOT NULL,
    deal_id VARCHAR(36) NOT NULL,
    contact_role VARCHAR(100),
    primary_contact BOOLEAN DEFAULT 0,
    date_modified DATETIME,
    deleted BOOLEAN DEFAULT 0,
    
    INDEX idx_contact_deal_contact (contact_id, deleted),
    INDEX idx_contact_deal_deal (deal_id, deleted),
    INDEX idx_contact_deal_primary (deal_id, primary_contact, deleted),
    FOREIGN KEY (contact_id) REFERENCES mdeal_contacts(id) ON DELETE CASCADE,
    FOREIGN KEY (deal_id) REFERENCES deals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: mdeal_contacts_accounts

```sql
CREATE TABLE mdeal_contacts_accounts (
    id VARCHAR(36) NOT NULL PRIMARY KEY,
    contact_id VARCHAR(36) NOT NULL,
    account_id VARCHAR(36) NOT NULL,
    title VARCHAR(100),
    department VARCHAR(100),
    is_primary BOOLEAN DEFAULT 0,
    date_modified DATETIME,
    deleted BOOLEAN DEFAULT 0,
    
    INDEX idx_contact_account_contact (contact_id, deleted),
    INDEX idx_contact_account_account (account_id, deleted),
    INDEX idx_contact_account_primary (account_id, is_primary, deleted),
    FOREIGN KEY (contact_id) REFERENCES mdeal_contacts(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES mdeal_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: mdeal_contacts_documents

```sql
CREATE TABLE mdeal_contacts_documents (
    id VARCHAR(36) NOT NULL PRIMARY KEY,
    contact_id VARCHAR(36) NOT NULL,
    document_id VARCHAR(36) NOT NULL,
    date_modified DATETIME,
    deleted BOOLEAN DEFAULT 0,
    
    INDEX idx_contact_doc_contact (contact_id, deleted),
    INDEX idx_contact_doc_document (document_id, deleted),
    FOREIGN KEY (contact_id) REFERENCES mdeal_contacts(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 3. Accounts Module Schema

### Table: mdeal_accounts

```sql
CREATE TABLE mdeal_accounts (
    -- Basic fields
    id VARCHAR(36) NOT NULL PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    date_entered DATETIME,
    date_modified DATETIME,
    modified_user_id VARCHAR(36),
    created_by VARCHAR(36),
    description TEXT,
    deleted BOOLEAN DEFAULT 0,
    assigned_user_id VARCHAR(36),
    
    -- Account classification
    account_type VARCHAR(50),
    industry VARCHAR(100),
    sub_industry VARCHAR(100),
    naics_code VARCHAR(10),
    sic_code VARCHAR(10),
    
    -- Company information
    website VARCHAR(255),
    ticker_symbol VARCHAR(10),
    ownership_type VARCHAR(50),
    year_established INT,
    dba_name VARCHAR(150),
    tax_id VARCHAR(20) COMMENT 'Encrypted',
    duns_number VARCHAR(20),
    
    -- Financial information
    annual_revenue DECIMAL(26,6),
    revenue_currency_id VARCHAR(36),
    ebitda DECIMAL(26,6),
    employee_count INT,
    facility_count INT,
    
    -- Hierarchical structure
    parent_id VARCHAR(36),
    is_parent BOOLEAN DEFAULT 0,
    hierarchy_level INT DEFAULT 0,
    
    -- Addresses
    billing_address_street VARCHAR(150),
    billing_address_city VARCHAR(100),
    billing_address_state VARCHAR(100),
    billing_address_postalcode VARCHAR(20),
    billing_address_country VARCHAR(100),
    shipping_address_street VARCHAR(150),
    shipping_address_city VARCHAR(100),
    shipping_address_state VARCHAR(100),
    shipping_address_postalcode VARCHAR(20),
    shipping_address_country VARCHAR(100),
    same_as_billing BOOLEAN DEFAULT 0,
    
    -- Contact information
    phone_office VARCHAR(100),
    phone_alternate VARCHAR(100),
    phone_fax VARCHAR(100),
    email VARCHAR(100),
    
    -- Deal-related fields
    rating VARCHAR(20),
    account_status VARCHAR(50) DEFAULT 'active',
    deal_count INT DEFAULT 0,
    total_deal_value DECIMAL(26,6),
    last_deal_date DATE,
    
    -- Compliance & risk
    credit_rating VARCHAR(10),
    credit_limit DECIMAL(26,6),
    payment_terms VARCHAR(100),
    risk_assessment VARCHAR(20),
    compliance_status VARCHAR(50),
    insurance_coverage BOOLEAN DEFAULT 0,
    insurance_expiry DATE,
    
    -- Portfolio-specific fields
    acquisition_date DATE,
    acquisition_price DECIMAL(26,6),
    current_valuation DECIMAL(26,6),
    exit_strategy VARCHAR(50),
    planned_exit_date DATE,
    integration_status VARCHAR(50),
    
    -- Indexes
    INDEX idx_account_name (name, deleted),
    INDEX idx_account_type (account_type, deleted),
    INDEX idx_account_industry (industry, deleted),
    INDEX idx_account_assigned (assigned_user_id, deleted),
    INDEX idx_account_parent (parent_id, deleted),
    INDEX idx_account_status (account_status, deleted),
    INDEX idx_account_website (website),
    INDEX idx_account_tax_id (tax_id),
    
    -- Foreign keys
    FOREIGN KEY (assigned_user_id) REFERENCES users(id),
    FOREIGN KEY (modified_user_id) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (parent_id) REFERENCES mdeal_accounts(id),
    FOREIGN KEY (revenue_currency_id) REFERENCES currencies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: mdeal_accounts_audit

```sql
CREATE TABLE mdeal_accounts_audit (
    id VARCHAR(36) NOT NULL PRIMARY KEY,
    parent_id VARCHAR(36) NOT NULL,
    date_created DATETIME,
    created_by VARCHAR(36),
    field_name VARCHAR(100),
    data_type VARCHAR(100),
    before_value_string VARCHAR(255),
    after_value_string VARCHAR(255),
    before_value_text TEXT,
    after_value_text TEXT,
    
    INDEX idx_accounts_audit_parent_id (parent_id),
    FOREIGN KEY (parent_id) REFERENCES mdeal_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: mdeal_accounts_documents

```sql
CREATE TABLE mdeal_accounts_documents (
    id VARCHAR(36) NOT NULL PRIMARY KEY,
    account_id VARCHAR(36) NOT NULL,
    document_id VARCHAR(36) NOT NULL,
    date_modified DATETIME,
    deleted BOOLEAN DEFAULT 0,
    
    INDEX idx_account_doc_account (account_id, deleted),
    INDEX idx_account_doc_document (document_id, deleted),
    FOREIGN KEY (account_id) REFERENCES mdeal_accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 4. Module Relationships

### Updates to Deals Table

```sql
-- Add account relationship to deals table
ALTER TABLE deals ADD COLUMN account_id VARCHAR(36) AFTER assigned_user_id;
ALTER TABLE deals ADD INDEX idx_deal_account (account_id, deleted);
ALTER TABLE deals ADD FOREIGN KEY (account_id) REFERENCES mdeal_accounts(id);
```

### Lead Conversion Tracking

```sql
CREATE TABLE mdeal_lead_conversion_history (
    id VARCHAR(36) NOT NULL PRIMARY KEY,
    lead_id VARCHAR(36) NOT NULL,
    deal_id VARCHAR(36) NOT NULL,
    contact_id VARCHAR(36),
    account_id VARCHAR(36),
    conversion_date DATETIME NOT NULL,
    converted_by VARCHAR(36) NOT NULL,
    
    INDEX idx_conversion_lead (lead_id),
    INDEX idx_conversion_deal (deal_id),
    FOREIGN KEY (lead_id) REFERENCES mdeal_leads(id),
    FOREIGN KEY (deal_id) REFERENCES deals(id),
    FOREIGN KEY (contact_id) REFERENCES mdeal_contacts(id),
    FOREIGN KEY (account_id) REFERENCES mdeal_accounts(id),
    FOREIGN KEY (converted_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 5. Pipeline Integration Tables

### Lead Pipeline Tracking

```sql
CREATE TABLE mdeal_lead_pipeline_history (
    id VARCHAR(36) NOT NULL PRIMARY KEY,
    lead_id VARCHAR(36) NOT NULL,
    stage VARCHAR(50) NOT NULL,
    date_entered DATETIME NOT NULL,
    date_left DATETIME,
    days_in_stage INT,
    created_by VARCHAR(36),
    
    INDEX idx_lead_pipeline_lead (lead_id),
    INDEX idx_lead_pipeline_stage (stage),
    FOREIGN KEY (lead_id) REFERENCES mdeal_leads(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 6. Database Views

### Active Leads View

```sql
CREATE VIEW v_active_leads AS
SELECT 
    l.*,
    u.user_name as assigned_user_name,
    DATEDIFF(NOW(), l.date_entered_stage) as current_days_in_stage
FROM mdeal_leads l
LEFT JOIN users u ON l.assigned_user_id = u.id
WHERE l.deleted = 0 
    AND l.status NOT IN ('converted', 'dead');
```

### Contact Deal Relationships View

```sql
CREATE VIEW v_contact_deals AS
SELECT 
    c.id as contact_id,
    c.full_name,
    c.email_address,
    c.phone_mobile,
    d.id as deal_id,
    d.name as deal_name,
    d.status as deal_status,
    cd.contact_role,
    cd.primary_contact
FROM mdeal_contacts c
JOIN mdeal_contacts_deals cd ON c.id = cd.contact_id
JOIN deals d ON cd.deal_id = d.id
WHERE c.deleted = 0 
    AND cd.deleted = 0 
    AND d.deleted = 0;
```

### Account Hierarchy View

```sql
CREATE VIEW v_account_hierarchy AS
WITH RECURSIVE account_tree AS (
    SELECT 
        id,
        name,
        parent_id,
        0 as level,
        CAST(id AS CHAR(500)) AS path
    FROM mdeal_accounts
    WHERE parent_id IS NULL AND deleted = 0
    
    UNION ALL
    
    SELECT 
        a.id,
        a.name,
        a.parent_id,
        at.level + 1,
        CONCAT(at.path, '/', a.id)
    FROM mdeal_accounts a
    JOIN account_tree at ON a.parent_id = at.id
    WHERE a.deleted = 0
)
SELECT * FROM account_tree;
```

## 7. Performance Considerations

1. **Indexes**: All foreign keys and commonly queried fields are indexed
2. **Partitioning**: Consider partitioning large tables by date_entered
3. **Archiving**: Implement archiving strategy for old/converted leads
4. **Caching**: Cache account hierarchies and contact relationships
5. **Query Optimization**: Use appropriate JOIN strategies for complex queries

## 8. Migration Scripts

### Initial Module Creation

```sql
-- Run in order:
-- 1. Create Accounts table first (no dependencies)
-- 2. Create Contacts table (depends on Accounts)
-- 3. Create Leads table (no dependencies)
-- 4. Create relationship tables
-- 5. Update Deals table
-- 6. Create views
```

### Data Migration from Existing System

```sql
-- Example migration for importing existing contacts
INSERT INTO mdeal_contacts (
    id, 
    first_name, 
    last_name, 
    email_address,
    phone_work,
    created_by,
    date_entered,
    date_modified,
    assigned_user_id
)
SELECT 
    UUID(),
    first_name,
    last_name,
    email,
    phone,
    '1', -- admin user
    NOW(),
    NOW(),
    '1' -- admin user
FROM legacy_contacts;
```