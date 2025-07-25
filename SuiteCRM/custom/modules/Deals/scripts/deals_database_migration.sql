-- Comprehensive Database Migration for Deals Module
-- Purpose: Execute all required database schema changes for production deployment
-- Date: 2025-07-24
-- Agent: Database Migration Specialist

-- Start transaction to ensure atomicity
START TRANSACTION;

-- ============================================================================
-- SECTION 1: CORE PIPELINE INFRASTRUCTURE
-- ============================================================================

-- Create pipeline_stages table for managing deal stages
CREATE TABLE IF NOT EXISTS pipeline_stages (
    id char(36) NOT NULL PRIMARY KEY,
    name varchar(255) NOT NULL,
    stage_key varchar(50) NOT NULL UNIQUE,
    stage_order int(11) NOT NULL DEFAULT 0,
    wip_limit int(11) DEFAULT NULL COMMENT 'Work In Progress limit for this stage',
    color_code varchar(7) DEFAULT '#1976d2' COMMENT 'Hex color for stage column',
    description text,
    is_terminal tinyint(1) DEFAULT 0 COMMENT 'Terminal stages like Closed/Unavailable',
    is_active tinyint(1) DEFAULT 1,
    date_entered datetime DEFAULT NULL,
    date_modified datetime DEFAULT NULL,
    created_by char(36) DEFAULT NULL,
    modified_user_id char(36) DEFAULT NULL,
    deleted tinyint(1) DEFAULT 0,
    KEY idx_stage_order (stage_order, deleted),
    KEY idx_stage_key (stage_key, deleted),
    KEY idx_is_active (is_active, deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create deal_stage_transitions table for tracking stage movements
CREATE TABLE IF NOT EXISTS deal_stage_transitions (
    id char(36) NOT NULL PRIMARY KEY,
    deal_id char(36) NOT NULL,
    from_stage varchar(50) COMMENT 'NULL for initial stage entry',
    to_stage varchar(50) NOT NULL,
    transition_date datetime NOT NULL,
    transition_by char(36) NOT NULL COMMENT 'User who made the transition',
    time_in_previous_stage int DEFAULT 0 COMMENT 'Time spent in previous stage (minutes)',
    transition_reason varchar(255),
    notes text,
    created_by char(36),
    date_created datetime,
    deleted tinyint(1) DEFAULT 0,
    KEY idx_deal_transitions (deal_id, transition_date),
    KEY idx_stage_tracking (to_stage, transition_date),
    KEY idx_from_stage (from_stage, transition_date),
    KEY idx_transition_by (transition_by, transition_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create pipeline_stage_history table for audit trail
CREATE TABLE IF NOT EXISTS pipeline_stage_history (
    id char(36) NOT NULL PRIMARY KEY,
    deal_id char(36) NOT NULL,
    old_stage varchar(50),
    new_stage varchar(50) NOT NULL,
    changed_by char(36) NOT NULL,
    date_changed datetime NOT NULL,
    date_created datetime DEFAULT CURRENT_TIMESTAMP,
    deleted tinyint(1) DEFAULT 0,
    KEY idx_deal_id (deal_id),
    KEY idx_changed_by (changed_by),
    KEY idx_date_changed (date_changed),
    KEY idx_deleted (deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SECTION 2: CHECKLIST SYSTEM TABLES
-- ============================================================================

-- Create checklist_templates table for template definitions
CREATE TABLE IF NOT EXISTS checklist_templates (
    id varchar(36) NOT NULL PRIMARY KEY,
    name varchar(255) NOT NULL,
    description text,
    category varchar(100),
    is_active tinyint(1) DEFAULT 1,
    template_version varchar(20) DEFAULT '1.0',
    estimated_duration_days int DEFAULT 0,
    created_by varchar(36),
    date_entered datetime,
    modified_user_id varchar(36),
    date_modified datetime,
    deleted tinyint(1) DEFAULT 0,
    INDEX idx_template_active (is_active, deleted),
    INDEX idx_template_category (category, deleted),
    INDEX idx_template_name (name, deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create checklist_items table for individual checklist items
CREATE TABLE IF NOT EXISTS checklist_items (
    id varchar(36) NOT NULL PRIMARY KEY,
    template_id varchar(36),
    name varchar(255) NOT NULL,
    description text,
    sort_order int DEFAULT 0,
    is_required tinyint(1) DEFAULT 1,
    estimated_hours decimal(5,2) DEFAULT 0.00,
    requires_document tinyint(1) DEFAULT 0,
    document_description text,
    prerequisite_items text COMMENT 'JSON array of prerequisite item IDs',
    created_by varchar(36),
    date_entered datetime,
    modified_user_id varchar(36),
    date_modified datetime,
    deleted tinyint(1) DEFAULT 0,
    INDEX idx_item_template (template_id, deleted),
    INDEX idx_item_sort (template_id, sort_order),
    INDEX idx_item_required (is_required, deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create deals_checklist_templates junction table
CREATE TABLE IF NOT EXISTS deals_checklist_templates (
    id varchar(36) NOT NULL PRIMARY KEY,
    deal_id varchar(36) NOT NULL,
    template_id varchar(36) NOT NULL,
    applied_date datetime DEFAULT CURRENT_TIMESTAMP,
    completion_percentage decimal(5,2) DEFAULT 0.00,
    status enum('active', 'completed', 'paused', 'cancelled') DEFAULT 'active',
    due_date date,
    assigned_user_id varchar(36),
    notes text,
    date_modified datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    date_entered datetime DEFAULT CURRENT_TIMESTAMP,
    modified_user_id varchar(36),
    created_by varchar(36),
    deleted tinyint(1) DEFAULT 0,
    UNIQUE KEY idx_deal_template (deal_id, template_id),
    INDEX idx_deal_id_del (deal_id, deleted),
    INDEX idx_template_id_del (template_id, deleted),
    INDEX idx_completion_status (completion_percentage, status),
    INDEX idx_due_date (due_date, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create deals_checklist_items table for individual item tracking
CREATE TABLE IF NOT EXISTS deals_checklist_items (
    id varchar(36) NOT NULL PRIMARY KEY,
    deal_id varchar(36) NOT NULL,
    item_id varchar(36) NOT NULL,
    template_instance_id varchar(36) COMMENT 'Links to deals_checklist_templates.id',
    completion_status enum('pending', 'in_progress', 'completed', 'not_applicable', 'blocked') DEFAULT 'pending',
    completion_date datetime,
    due_date date,
    priority enum('high', 'medium', 'low') DEFAULT 'medium',
    notes text,
    document_requested tinyint(1) DEFAULT 0,
    document_received tinyint(1) DEFAULT 0,
    assigned_user_id varchar(36),
    estimated_hours decimal(5,2) DEFAULT 0.00,
    actual_hours decimal(5,2) DEFAULT 0.00,
    date_modified datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    date_entered datetime DEFAULT CURRENT_TIMESTAMP,
    modified_user_id varchar(36),
    created_by varchar(36),
    deleted tinyint(1) DEFAULT 0,
    UNIQUE KEY idx_deal_item (deal_id, item_id),
    INDEX idx_deal_status (deal_id, completion_status, deleted),
    INDEX idx_template_instance (template_instance_id, deleted),
    INDEX idx_due_date_priority (due_date, priority),
    INDEX idx_completion_date (completion_date),
    INDEX idx_assigned_user (assigned_user_id, completion_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SECTION 3: TASK GENERATION SYSTEM TABLES
-- ============================================================================

-- Create task_generation_requests table
CREATE TABLE IF NOT EXISTS task_generation_requests (
    id varchar(36) NOT NULL PRIMARY KEY,
    deal_id varchar(36) NOT NULL,
    template_id varchar(36) NOT NULL,
    requested_by varchar(36) NOT NULL,
    request_date datetime DEFAULT CURRENT_TIMESTAMP,
    generation_status enum('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    generation_data json DEFAULT NULL,
    completion_date datetime DEFAULT NULL,
    error_message text DEFAULT NULL,
    created_by varchar(36),
    date_entered datetime DEFAULT CURRENT_TIMESTAMP,
    modified_user_id varchar(36),
    date_modified datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted tinyint(1) DEFAULT 0,
    INDEX idx_deal_template (deal_id, template_id),
    INDEX idx_status (generation_status, deleted),
    INDEX idx_requested_by (requested_by),
    INDEX idx_request_date (request_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create generated_tasks table
CREATE TABLE IF NOT EXISTS generated_tasks (
    id varchar(36) NOT NULL PRIMARY KEY,
    generation_request_id varchar(36) NOT NULL,
    deal_id varchar(36) NOT NULL,
    template_item_id varchar(36) NOT NULL,
    task_name varchar(255) NOT NULL,
    task_description text,
    task_priority enum('high', 'medium', 'low') DEFAULT 'medium',
    due_date date,
    assigned_user_id varchar(36),
    status enum('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    dependencies json DEFAULT NULL,
    variables_used json DEFAULT NULL,
    created_by varchar(36),
    date_entered datetime DEFAULT CURRENT_TIMESTAMP,
    modified_user_id varchar(36),
    date_modified datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted tinyint(1) DEFAULT 0,
    INDEX idx_generation_request (generation_request_id),
    INDEX idx_deal_id (deal_id),
    INDEX idx_template_item (template_item_id),
    INDEX idx_status (status, deleted),
    INDEX idx_due_date (due_date),
    INDEX idx_assigned_user (assigned_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SECTION 4: FILE REQUEST SYSTEM TABLES
-- ============================================================================

-- Create file_requests table
CREATE TABLE IF NOT EXISTS file_requests (
    id varchar(36) NOT NULL PRIMARY KEY,
    deal_id varchar(36) NOT NULL,
    checklist_item_id varchar(36),
    request_token varchar(255) NOT NULL UNIQUE,
    recipient_email varchar(255) NOT NULL,
    recipient_name varchar(255),
    request_subject varchar(500),
    request_message text,
    request_type enum('due_diligence', 'financial', 'legal', 'general') DEFAULT 'general',
    status enum('pending', 'sent', 'viewed', 'uploaded', 'completed', 'expired') DEFAULT 'pending',
    expires_at datetime,
    created_by varchar(36),
    date_entered datetime DEFAULT CURRENT_TIMESTAMP,
    modified_user_id varchar(36),
    date_modified datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted tinyint(1) DEFAULT 0,
    INDEX idx_deal_id (deal_id),
    INDEX idx_checklist_item (checklist_item_id),
    INDEX idx_token (request_token),
    INDEX idx_status (status, deleted),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create file_uploads table
CREATE TABLE IF NOT EXISTS file_uploads (
    id varchar(36) NOT NULL PRIMARY KEY,
    file_request_id varchar(36) NOT NULL,
    original_filename varchar(500) NOT NULL,
    stored_filename varchar(500) NOT NULL,
    file_path varchar(1000) NOT NULL,
    file_size bigint NOT NULL,
    mime_type varchar(255),
    file_hash varchar(255),
    scan_status enum('pending', 'clean', 'infected', 'error') DEFAULT 'pending',
    upload_date datetime DEFAULT CURRENT_TIMESTAMP,
    uploaded_by_ip varchar(45),
    created_by varchar(36),
    date_entered datetime DEFAULT CURRENT_TIMESTAMP,
    deleted tinyint(1) DEFAULT 0,
    INDEX idx_file_request (file_request_id),
    INDEX idx_scan_status (scan_status),
    INDEX idx_upload_date (upload_date),
    INDEX idx_file_hash (file_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create email_logs table
CREATE TABLE IF NOT EXISTS email_logs (
    id varchar(36) NOT NULL PRIMARY KEY,
    file_request_id varchar(36) NOT NULL,
    email_type enum('initial', 'reminder', 'confirmation', 'notification') NOT NULL,
    recipient_email varchar(255) NOT NULL,
    subject varchar(500),
    message_body text,
    sent_date datetime DEFAULT CURRENT_TIMESTAMP,
    delivery_status enum('pending', 'sent', 'delivered', 'failed', 'bounced') DEFAULT 'pending',
    error_message text,
    created_by varchar(36),
    date_entered datetime DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_file_request (file_request_id),
    INDEX idx_email_type (email_type),
    INDEX idx_delivery_status (delivery_status),
    INDEX idx_sent_date (sent_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SECTION 5: TEMPLATE VERSIONING SYSTEM TABLES
-- ============================================================================

-- Create template_versions table
CREATE TABLE IF NOT EXISTS template_versions (
    id varchar(36) NOT NULL PRIMARY KEY,
    template_id varchar(36) NOT NULL,
    version_number varchar(20) NOT NULL,
    version_type enum('major', 'minor', 'patch', 'prerelease') DEFAULT 'minor',
    branch_name varchar(100) DEFAULT 'main',
    parent_version_id varchar(36),
    is_active tinyint(1) DEFAULT 0,
    is_published tinyint(1) DEFAULT 0,
    template_data json NOT NULL,
    changelog text,
    migration_notes text,
    approval_status enum('draft', 'pending', 'approved', 'rejected') DEFAULT 'draft',
    approved_by varchar(36),
    approval_date datetime,
    created_by varchar(36) NOT NULL,
    date_entered datetime DEFAULT CURRENT_TIMESTAMP,
    modified_user_id varchar(36),
    date_modified datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted tinyint(1) DEFAULT 0,
    UNIQUE KEY idx_template_version (template_id, version_number),
    INDEX idx_template_id (template_id),
    INDEX idx_version_number (version_number),
    INDEX idx_branch_name (branch_name),
    INDEX idx_is_active (is_active, deleted),
    INDEX idx_is_published (is_published, deleted),
    INDEX idx_approval_status (approval_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create template_version_comparisons table
CREATE TABLE IF NOT EXISTS template_version_comparisons (
    id varchar(36) NOT NULL PRIMARY KEY,
    template_id varchar(36) NOT NULL,
    version_a_id varchar(36) NOT NULL,
    version_b_id varchar(36) NOT NULL,
    comparison_type enum('structural', 'semantic', 'full') DEFAULT 'full',
    comparison_result json,
    differences_count int DEFAULT 0,
    similarity_score decimal(5,2) DEFAULT 0.00,
    computed_at datetime DEFAULT CURRENT_TIMESTAMP,
    cache_expires_at datetime,
    created_by varchar(36),
    date_entered datetime DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_template_versions (template_id, version_a_id, version_b_id),
    INDEX idx_comparison_type (comparison_type),
    INDEX idx_cache_expires (cache_expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create template_migrations table
CREATE TABLE IF NOT EXISTS template_migrations (
    id varchar(36) NOT NULL PRIMARY KEY,
    template_id varchar(36) NOT NULL,
    from_version_id varchar(36) NOT NULL,
    to_version_id varchar(36) NOT NULL,
    migration_type enum('automatic', 'manual', 'rollback') DEFAULT 'automatic',
    migration_status enum('pending', 'running', 'completed', 'failed', 'rolled_back') DEFAULT 'pending',
    migration_script text,
    execution_log text,
    affected_instances_count int DEFAULT 0,
    started_at datetime,
    completed_at datetime,
    created_by varchar(36) NOT NULL,
    date_entered datetime DEFAULT CURRENT_TIMESTAMP,
    modified_user_id varchar(36),
    date_modified datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted tinyint(1) DEFAULT 0,
    INDEX idx_template_id (template_id),
    INDEX idx_versions (from_version_id, to_version_id),
    INDEX idx_migration_status (migration_status),
    INDEX idx_started_at (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SECTION 6: STAKEHOLDER TRACKING TABLES  
-- ============================================================================

-- Create contact_roles table for stakeholder role management
CREATE TABLE IF NOT EXISTS contact_roles (
    id varchar(36) NOT NULL PRIMARY KEY,
    role_name varchar(100) NOT NULL UNIQUE,
    role_description text,
    sort_order int DEFAULT 0,
    is_active tinyint(1) DEFAULT 1,
    created_by varchar(36),
    date_entered datetime DEFAULT CURRENT_TIMESTAMP,
    modified_user_id varchar(36),
    date_modified datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted tinyint(1) DEFAULT 0,
    INDEX idx_role_name (role_name, deleted),
    INDEX idx_sort_order (sort_order, is_active),
    INDEX idx_is_active (is_active, deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create deals_contacts_relationships table for enhanced stakeholder tracking
CREATE TABLE IF NOT EXISTS deals_contacts_relationships (
    id varchar(36) NOT NULL PRIMARY KEY,
    deal_id varchar(36) NOT NULL,
    contact_id varchar(36) NOT NULL,
    role_id varchar(36),
    relationship_strength enum('weak', 'moderate', 'strong') DEFAULT 'moderate',
    is_primary_contact tinyint(1) DEFAULT 0,
    last_contact_date datetime,
    next_followup_date date,
    communication_frequency_days int DEFAULT 30,
    notes text,
    relationship_status enum('active', 'inactive', 'terminated') DEFAULT 'active',
    created_by varchar(36),
    date_entered datetime DEFAULT CURRENT_TIMESTAMP,
    modified_user_id varchar(36),
    date_modified datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted tinyint(1) DEFAULT 0,
    UNIQUE KEY idx_deal_contact (deal_id, contact_id),
    INDEX idx_deal_id (deal_id, deleted),
    INDEX idx_contact_id (contact_id, deleted),
    INDEX idx_role_id (role_id),
    INDEX idx_primary_contact (is_primary_contact, deal_id),
    INDEX idx_last_contact (last_contact_date),
    INDEX idx_next_followup (next_followup_date, relationship_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create communication_history table
CREATE TABLE IF NOT EXISTS communication_history (
    id varchar(36) NOT NULL PRIMARY KEY,
    deal_id varchar(36),
    contact_id varchar(36) NOT NULL,
    communication_type enum('email', 'call', 'meeting', 'note', 'document') NOT NULL,
    subject varchar(500),
    content text,
    communication_date datetime NOT NULL,
    direction enum('inbound', 'outbound') DEFAULT 'outbound',
    status enum('scheduled', 'completed', 'cancelled', 'missed') DEFAULT 'completed',
    related_record_id varchar(36) COMMENT 'Link to emails, calls, meetings tables',
    related_record_type varchar(50),
    created_by varchar(36),
    date_entered datetime DEFAULT CURRENT_TIMESTAMP,
    modified_user_id varchar(36),
    date_modified datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted tinyint(1) DEFAULT 0,
    INDEX idx_deal_id (deal_id, communication_date),
    INDEX idx_contact_id (contact_id, communication_date),
    INDEX idx_communication_type (communication_type),
    INDEX idx_communication_date (communication_date),
    INDEX idx_related_record (related_record_type, related_record_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SECTION 7: ALTER EXISTING TABLES - OPPORTUNITIES (DEALS) EXTENSIONS
-- ============================================================================

-- Add pipeline tracking fields to opportunities table
ALTER TABLE opportunities 
ADD COLUMN IF NOT EXISTS pipeline_stage_c varchar(50) DEFAULT 'sourcing',
ADD COLUMN IF NOT EXISTS stage_entered_date_c datetime DEFAULT NULL,
ADD COLUMN IF NOT EXISTS time_in_stage int DEFAULT 0,
ADD COLUMN IF NOT EXISTS wip_position int DEFAULT NULL,
ADD COLUMN IF NOT EXISTS is_archived tinyint(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS last_stage_update datetime DEFAULT NULL,
ADD COLUMN IF NOT EXISTS expected_close_date_c date DEFAULT NULL,
ADD COLUMN IF NOT EXISTS deal_source_c varchar(50) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS pipeline_notes_c text DEFAULT NULL,
ADD COLUMN IF NOT EXISTS days_in_stage_c int DEFAULT 0,
ADD COLUMN IF NOT EXISTS checklist_completion_c decimal(5,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS active_checklists_count_c int DEFAULT 0,
ADD COLUMN IF NOT EXISTS overdue_checklist_items_c int DEFAULT 0;

-- Add performance indexes for opportunities table
CREATE INDEX IF NOT EXISTS idx_opportunities_pipeline_stage ON opportunities(pipeline_stage_c, deleted);
CREATE INDEX IF NOT EXISTS idx_opportunities_stage_date ON opportunities(stage_entered_date_c);
CREATE INDEX IF NOT EXISTS idx_opportunities_checklist_completion ON opportunities(checklist_completion_c);
CREATE INDEX IF NOT EXISTS idx_opportunities_active_checklists ON opportunities(active_checklists_count_c);
CREATE INDEX IF NOT EXISTS idx_opportunities_overdue_items ON opportunities(overdue_checklist_items_c);
CREATE INDEX IF NOT EXISTS idx_opportunities_deal_source ON opportunities(deal_source_c, deleted);
CREATE INDEX IF NOT EXISTS idx_opportunities_expected_close ON opportunities(expected_close_date_c);

-- Add stakeholder tracking fields to contacts table
ALTER TABLE contacts 
ADD COLUMN IF NOT EXISTS stakeholder_role_c varchar(100) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS relationship_strength_c enum('weak', 'moderate', 'strong') DEFAULT 'moderate',
ADD COLUMN IF NOT EXISTS last_contact_date_c datetime DEFAULT NULL,
ADD COLUMN IF NOT EXISTS next_followup_date_c date DEFAULT NULL,
ADD COLUMN IF NOT EXISTS communication_frequency_c int DEFAULT 30,
ADD COLUMN IF NOT EXISTS is_key_stakeholder_c tinyint(1) DEFAULT 0;

-- Add performance indexes for contacts table
CREATE INDEX IF NOT EXISTS idx_contacts_stakeholder_role ON contacts(stakeholder_role_c, deleted);
CREATE INDEX IF NOT EXISTS idx_contacts_last_contact ON contacts(last_contact_date_c);
CREATE INDEX IF NOT EXISTS idx_contacts_next_followup ON contacts(next_followup_date_c);
CREATE INDEX IF NOT EXISTS idx_contacts_key_stakeholder ON contacts(is_key_stakeholder_c, deleted);

-- ============================================================================
-- SECTION 8: FOREIGN KEY CONSTRAINTS
-- ============================================================================

-- Add foreign key constraints for data integrity
ALTER TABLE pipeline_stage_history
ADD CONSTRAINT IF NOT EXISTS fk_pipeline_history_deal
    FOREIGN KEY (deal_id) REFERENCES opportunities(id)
    ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE pipeline_stage_history
ADD CONSTRAINT IF NOT EXISTS fk_pipeline_history_user
    FOREIGN KEY (changed_by) REFERENCES users(id)
    ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE deal_stage_transitions
ADD CONSTRAINT IF NOT EXISTS fk_deal_transitions_deal
    FOREIGN KEY (deal_id) REFERENCES opportunities(id)
    ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE deal_stage_transitions
ADD CONSTRAINT IF NOT EXISTS fk_deal_transitions_user
    FOREIGN KEY (transition_by) REFERENCES users(id)
    ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE checklist_items
ADD CONSTRAINT IF NOT EXISTS fk_checklist_items_template
    FOREIGN KEY (template_id) REFERENCES checklist_templates(id)
    ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE deals_checklist_templates
ADD CONSTRAINT IF NOT EXISTS fk_deals_checklists_deal
    FOREIGN KEY (deal_id) REFERENCES opportunities(id)
    ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE deals_checklist_templates
ADD CONSTRAINT IF NOT EXISTS fk_deals_checklists_template
    FOREIGN KEY (template_id) REFERENCES checklist_templates(id)
    ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE deals_checklist_items
ADD CONSTRAINT IF NOT EXISTS fk_deals_items_deal
    FOREIGN KEY (deal_id) REFERENCES opportunities(id)
    ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE deals_checklist_items
ADD CONSTRAINT IF NOT EXISTS fk_deals_items_item
    FOREIGN KEY (item_id) REFERENCES checklist_items(id)
    ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE deals_checklist_items
ADD CONSTRAINT IF NOT EXISTS fk_deals_items_template_instance
    FOREIGN KEY (template_instance_id) REFERENCES deals_checklist_templates(id)
    ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE deals_contacts_relationships
ADD CONSTRAINT IF NOT EXISTS fk_deals_contacts_deal
    FOREIGN KEY (deal_id) REFERENCES opportunities(id)
    ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE deals_contacts_relationships
ADD CONSTRAINT IF NOT EXISTS fk_deals_contacts_contact
    FOREIGN KEY (contact_id) REFERENCES contacts(id)
    ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE deals_contacts_relationships
ADD CONSTRAINT IF NOT EXISTS fk_deals_contacts_role
    FOREIGN KEY (role_id) REFERENCES contact_roles(id)
    ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE communication_history
ADD CONSTRAINT IF NOT EXISTS fk_communication_deal
    FOREIGN KEY (deal_id) REFERENCES opportunities(id)
    ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE communication_history
ADD CONSTRAINT IF NOT EXISTS fk_communication_contact
    FOREIGN KEY (contact_id) REFERENCES contacts(id)
    ON DELETE CASCADE ON UPDATE CASCADE;

-- ============================================================================
-- SECTION 9: SEED DATA INSERTION
-- ============================================================================

-- Insert default pipeline stages
INSERT IGNORE INTO pipeline_stages (id, name, stage_key, stage_order, wip_limit, color_code, description, is_terminal, is_active, date_entered, date_modified, created_by) VALUES
(UUID(), 'Sourcing', 'sourcing', 1, NULL, '#9E9E9E', 'Initial deal identification and sourcing', 0, 1, NOW(), NOW(), '1'),
(UUID(), 'Initial Contact', 'initial_contact', 2, 20, '#2196F3', 'First contact with seller/broker', 0, 1, NOW(), NOW(), '1'),
(UUID(), 'Marketing Package Review', 'marketing_review', 3, 15, '#03A9F4', 'Review of marketing materials and teaser', 0, 1, NOW(), NOW(), '1'),
(UUID(), 'Management Call', 'management_call', 4, 12, '#00BCD4', 'Initial call with management team', 0, 1, NOW(), NOW(), '1'),
(UUID(), 'LOI Submitted', 'loi_submitted', 5, 10, '#009688', 'Letter of Intent submitted', 0, 1, NOW(), NOW(), '1'),
(UUID(), 'Due Diligence', 'due_diligence', 6, 8, '#4CAF50', 'Due diligence phase', 0, 1, NOW(), NOW(), '1'),
(UUID(), 'Final Negotiation', 'final_negotiation', 7, 6, '#8BC34A', 'Final terms negotiation', 0, 1, NOW(), NOW(), '1'),
(UUID(), 'Purchase Agreement', 'purchase_agreement', 8, 4, '#CDDC39', 'Purchase agreement signed', 0, 1, NOW(), NOW(), '1'),
(UUID(), 'Closing', 'closing', 9, 3, '#FFC107', 'Deal closing process', 0, 1, NOW(), NOW(), '1'),
(UUID(), 'Closed', 'closed', 10, NULL, '#4CAF50', 'Deal successfully closed', 1, 1, NOW(), NOW(), '1'),
(UUID(), 'Unavailable', 'unavailable', 11, NULL, '#F44336', 'Deal no longer available', 1, 1, NOW(), NOW(), '1');

-- Insert default contact roles
INSERT IGNORE INTO contact_roles (id, role_name, role_description, sort_order, is_active, created_by, date_entered, date_modified) VALUES
(UUID(), 'Seller', 'Business owner/seller', 1, 1, '1', NOW(), NOW()),
(UUID(), 'Broker', 'Business broker or intermediary', 2, 1, '1', NOW(), NOW()),
(UUID(), 'Attorney', 'Legal counsel', 3, 1, '1', NOW(), NOW()),
(UUID(), 'Accountant', 'CPA or financial advisor', 4, 1, '1', NOW(), NOW()),
(UUID(), 'Lender', 'Financing provider', 5, 1, '1', NOW(), NOW()),
(UUID(), 'Consultant', 'Business consultant or advisor', 6, 1, '1', NOW(), NOW()),
(UUID(), 'Key Employee', 'Important company employee', 7, 1, '1', NOW(), NOW());

-- Insert default checklist templates
INSERT IGNORE INTO checklist_templates (id, name, description, category, is_active, template_version, estimated_duration_days, created_by, date_entered, date_modified) VALUES
(UUID(), 'Quick Screen Checklist', 'Basic screening checklist for initial deal evaluation', 'screening', 1, '1.0', 3, '1', NOW(), NOW()),
(UUID(), 'Financial Due Diligence', 'Comprehensive financial review checklist', 'due_diligence', 1, '1.0', 14, '1', NOW(), NOW()),
(UUID(), 'Legal Due Diligence', 'Legal review and compliance checklist', 'due_diligence', 1, '1.0', 10, '1', NOW(), NOW()),
(UUID(), 'Operational Due Diligence', 'Operational assessment and review checklist', 'due_diligence', 1, '1.0', 7, '1', NOW(), NOW()),
(UUID(), 'Closing Checklist', 'Final steps before deal closing', 'closing', 1, '1.0', 5, '1', NOW(), NOW());

-- ============================================================================
-- SECTION 10: AUDIT TABLES
-- ============================================================================

-- Create audit tables for tracking changes
CREATE TABLE IF NOT EXISTS checklist_templates_audit (
    id varchar(36) NOT NULL,
    parent_id varchar(36) NOT NULL,
    date_created datetime,
    created_by varchar(36),
    field_name varchar(100),
    data_type varchar(100),
    before_value_text text,
    after_value_text text,
    before_value_string varchar(255),
    after_value_string varchar(255),
    INDEX idx_parent_id (parent_id),
    INDEX idx_date_created (date_created),
    INDEX idx_field_name (field_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS deals_checklist_templates_audit (
    id varchar(36) NOT NULL,
    parent_id varchar(36) NOT NULL,
    date_created datetime,
    created_by varchar(36),
    field_name varchar(100),
    data_type varchar(100),
    before_value_text text,
    after_value_text text,
    before_value_string varchar(255),
    after_value_string varchar(255),
    INDEX idx_parent_id (parent_id),
    INDEX idx_date_created (date_created),
    INDEX idx_field_name (field_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Commit the transaction
COMMIT;

-- Verify the migration by checking table existence
SELECT 'Migration completed successfully. Verifying table creation...' AS message;

SELECT COUNT(*) as pipeline_stages_count FROM pipeline_stages;
SELECT COUNT(*) as deal_transitions_count FROM deal_stage_transitions;
SELECT COUNT(*) as checklist_templates_count FROM checklist_templates;
SELECT COUNT(*) as contact_roles_count FROM contact_roles;

-- Show pipeline stages
SELECT name, stage_key, stage_order, color_code FROM pipeline_stages ORDER BY stage_order;

-- Show contact roles  
SELECT role_name, role_description, sort_order FROM contact_roles ORDER BY sort_order;

SELECT 'Database migration completed successfully!' AS final_message;