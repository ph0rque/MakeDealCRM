#!/bin/bash

# MakeDealCRM Database Migration Script
# Handles database setup and migrations for AWS deployment

set -e

# Configuration
DB_HOST=${DB_HOST:-localhost}
DB_PORT=${DB_PORT:-3306}
DB_NAME=${DB_NAME:-makedealcrm}
DB_USER=${DB_USER:-makedealcrm}
DB_PASSWORD=${DB_PASSWORD}

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Functions
print_status() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Check MySQL connection
check_connection() {
    print_status "Checking database connection..."
    
    if mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" -e "SELECT 1" &> /dev/null; then
        print_status "Database connection successful!"
        return 0
    else
        print_error "Cannot connect to database. Please check your credentials."
        return 1
    fi
}

# Create database if not exists
create_database() {
    print_status "Creating database if not exists..."
    
    mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" <<EOF
CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE $DB_NAME;
EOF
    
    print_status "Database '$DB_NAME' is ready!"
}

# Run initial schema
run_initial_schema() {
    print_status "Running initial database schema..."
    
    # Check if tables already exist
    TABLE_COUNT=$(mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" -D "$DB_NAME" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$DB_NAME'" -s -N)
    
    if [ "$TABLE_COUNT" -gt 0 ]; then
        print_warning "Database already contains $TABLE_COUNT tables. Skipping initial schema."
        return 0
    fi
    
    # Run SuiteCRM install SQL
    if [ -f "/var/www/html/install/suite_install.sql" ]; then
        print_status "Importing SuiteCRM base schema..."
        mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" < /var/www/html/install/suite_install.sql
    fi
    
    print_status "Initial schema created successfully!"
}

# Run custom migrations
run_custom_migrations() {
    print_status "Running custom migrations..."
    
    # Create migrations tracking table
    mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" <<EOF
CREATE TABLE IF NOT EXISTS migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL UNIQUE,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    checksum VARCHAR(64),
    status ENUM('success', 'failed') DEFAULT 'success',
    error_message TEXT,
    INDEX idx_filename (filename),
    INDEX idx_executed_at (executed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
EOF
    
    # Process migration files
    MIGRATION_DIR="/var/www/html/custom/migrations"
    
    if [ -d "$MIGRATION_DIR" ]; then
        for migration in $(ls -1 "$MIGRATION_DIR"/*.sql 2>/dev/null | sort); do
            FILENAME=$(basename "$migration")
            
            # Check if migration already executed
            EXECUTED=$(mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" \
                -e "SELECT COUNT(*) FROM migrations WHERE filename = '$FILENAME'" -s -N)
            
            if [ "$EXECUTED" -eq 0 ]; then
                print_status "Running migration: $FILENAME"
                
                # Calculate checksum
                CHECKSUM=$(sha256sum "$migration" | awk '{print $1}')
                
                # Run migration
                if mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" < "$migration"; then
                    # Record success
                    mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" <<EOF
INSERT INTO migrations (filename, checksum, status) VALUES ('$FILENAME', '$CHECKSUM', 'success');
EOF
                    print_status "Migration $FILENAME completed successfully!"
                else
                    # Record failure
                    mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" <<EOF
INSERT INTO migrations (filename, checksum, status, error_message) 
VALUES ('$FILENAME', '$CHECKSUM', 'failed', 'Migration failed - check logs');
EOF
                    print_error "Migration $FILENAME failed!"
                fi
            else
                print_status "Migration $FILENAME already executed, skipping..."
            fi
        done
    else
        print_warning "No migration directory found at $MIGRATION_DIR"
    fi
}

# Add custom fields for Deals module
add_deals_custom_fields() {
    print_status "Adding custom fields for Deals module..."
    
    mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" <<EOF
-- Create custom fields table if not exists
CREATE TABLE IF NOT EXISTS deals_cstm (
    id_c CHAR(36) NOT NULL PRIMARY KEY,
    -- Pipeline fields
    pipeline_stage_c VARCHAR(50) DEFAULT 'sourcing',
    stage_entered_date_c DATETIME,
    expected_close_date_c DATE,
    deal_source_c VARCHAR(100),
    pipeline_notes_c TEXT,
    
    -- Focus tracking
    focus_c TINYINT(1) DEFAULT 0,
    focus_order_c INT,
    focus_date_c DATETIME,
    
    -- Financial fields
    asking_price_c DECIMAL(26,6),
    ttm_revenue_c DECIMAL(26,6),
    ttm_ebitda_c DECIMAL(26,6),
    sde_c DECIMAL(26,6),
    proposed_valuation_c DECIMAL(26,6),
    target_multiple_c DECIMAL(10,2),
    
    -- Capital stack
    equity_c DECIMAL(26,6),
    senior_debt_c DECIMAL(26,6),
    seller_note_c DECIMAL(26,6),
    
    -- Checklist progress
    checklist_progress INT DEFAULT 0,
    
    INDEX idx_pipeline_stage (pipeline_stage_c),
    INDEX idx_focus (focus_c, focus_order_c),
    INDEX idx_stage_date (stage_entered_date_c)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create pipeline stage history table
CREATE TABLE IF NOT EXISTS pipeline_stage_history (
    id CHAR(36) NOT NULL PRIMARY KEY,
    deal_id CHAR(36) NOT NULL,
    old_stage VARCHAR(50),
    new_stage VARCHAR(50),
    changed_by CHAR(36),
    date_changed DATETIME NOT NULL,
    notes TEXT,
    INDEX idx_deal_id (deal_id),
    INDEX idx_date_changed (date_changed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create checklist templates table
CREATE TABLE IF NOT EXISTS checklist_templates (
    id CHAR(36) NOT NULL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(100),
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_by CHAR(36),
    date_created DATETIME,
    modified_by CHAR(36),
    date_modified DATETIME,
    deleted TINYINT(1) DEFAULT 0,
    INDEX idx_category (category),
    INDEX idx_active (is_active, deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create checklist items table
CREATE TABLE IF NOT EXISTS checklist_items (
    id CHAR(36) NOT NULL PRIMARY KEY,
    template_id CHAR(36) NOT NULL,
    parent_id CHAR(36),
    title VARCHAR(255) NOT NULL,
    description TEXT,
    priority VARCHAR(20) DEFAULT 'medium',
    estimated_hours DECIMAL(5,2),
    sort_order INT DEFAULT 0,
    required TINYINT(1) DEFAULT 1,
    deleted TINYINT(1) DEFAULT 0,
    INDEX idx_template (template_id),
    INDEX idx_parent (parent_id),
    INDEX idx_sort (template_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create deal checklists table
CREATE TABLE IF NOT EXISTS deal_checklists (
    id CHAR(36) NOT NULL PRIMARY KEY,
    deal_id CHAR(36) NOT NULL,
    template_id CHAR(36),
    name VARCHAR(255) NOT NULL,
    status VARCHAR(20) DEFAULT 'active',
    progress INT DEFAULT 0,
    created_by CHAR(36),
    date_created DATETIME,
    completed_date DATETIME,
    deleted TINYINT(1) DEFAULT 0,
    INDEX idx_deal (deal_id),
    INDEX idx_status (status, deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create file requests table
CREATE TABLE IF NOT EXISTS file_requests (
    id CHAR(36) NOT NULL PRIMARY KEY,
    deal_id CHAR(36) NOT NULL,
    checklist_item_id CHAR(36),
    request_type VARCHAR(50),
    recipient_email VARCHAR(255),
    recipient_name VARCHAR(255),
    status VARCHAR(20) DEFAULT 'pending',
    upload_token VARCHAR(64) UNIQUE,
    token_expiry DATETIME,
    files_received INT DEFAULT 0,
    sent_date DATETIME,
    completed_date DATETIME,
    created_by CHAR(36),
    date_created DATETIME,
    deleted TINYINT(1) DEFAULT 0,
    INDEX idx_deal (deal_id),
    INDEX idx_token (upload_token),
    INDEX idx_status (status, deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
EOF
    
    print_status "Custom fields added successfully!"
}

# Create default checklist templates
create_default_templates() {
    print_status "Creating default checklist templates..."
    
    mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" <<EOF
-- Quick-Screen Template
INSERT IGNORE INTO checklist_templates (id, name, category, description, date_created)
VALUES (
    UUID(),
    'Quick-Screen Due Diligence',
    'quick_screen',
    'Initial screening checklist for rapid deal evaluation',
    NOW()
);

-- Financial DD Template
INSERT IGNORE INTO checklist_templates (id, name, category, description, date_created)
VALUES (
    UUID(),
    'Financial Due Diligence',
    'financial_dd',
    'Comprehensive financial analysis checklist',
    NOW()
);

-- Legal DD Template
INSERT IGNORE INTO checklist_templates (id, name, category, description, date_created)
VALUES (
    UUID(),
    'Legal Due Diligence',
    'legal_dd',
    'Legal and compliance review checklist',
    NOW()
);

-- Operational Review Template
INSERT IGNORE INTO checklist_templates (id, name, category, description, date_created)
VALUES (
    UUID(),
    'Operational Review',
    'operational_review',
    'Operations and systems assessment checklist',
    NOW()
);
EOF
    
    print_status "Default templates created!"
}

# Optimize database
optimize_database() {
    print_status "Optimizing database performance..."
    
    mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" <<EOF
-- Analyze tables for optimizer statistics
ANALYZE TABLE accounts;
ANALYZE TABLE contacts;
ANALYZE TABLE deals;
ANALYZE TABLE deals_cstm;
ANALYZE TABLE tasks;
ANALYZE TABLE notes;
ANALYZE TABLE emails;

-- Add additional indexes for performance
ALTER TABLE deals ADD INDEX IF NOT EXISTS idx_date_modified (date_modified);
ALTER TABLE deals ADD INDEX IF NOT EXISTS idx_assigned_user (assigned_user_id, deleted);
ALTER TABLE tasks ADD INDEX IF NOT EXISTS idx_parent (parent_type, parent_id, deleted);
ALTER TABLE notes ADD INDEX IF NOT EXISTS idx_parent (parent_type, parent_id, deleted);
EOF
    
    print_status "Database optimization complete!"
}

# Main execution
main() {
    print_status "Starting MakeDealCRM database migration..."
    
    # Check for required environment variables
    if [ -z "$DB_PASSWORD" ]; then
        print_error "DB_PASSWORD environment variable is required!"
        exit 1
    fi
    
    # Run migration steps
    check_connection || exit 1
    create_database
    run_initial_schema
    add_deals_custom_fields
    create_default_templates
    run_custom_migrations
    optimize_database
    
    print_status "Database migration completed successfully!"
}

# Run main function
main "$@"