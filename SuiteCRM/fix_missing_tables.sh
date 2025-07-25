#!/bin/bash

# Script to create missing tables for Deals module in SuiteCRM database

echo "Creating missing tables for Deals module..."

# Execute SQL commands in the suitecrm_db container
# Using the correct credentials from docker-compose.yml
docker exec -i suitecrm_db mysql -u root -proot_password suitecrm <<'EOF'
-- Create missing tables for Deals module

-- Create checklist_templates table
CREATE TABLE IF NOT EXISTS checklist_templates (
    id char(36) NOT NULL,
    name varchar(255) DEFAULT NULL,
    stage varchar(100) DEFAULT NULL,
    checklist_items text,
    is_default tinyint(1) DEFAULT 0,
    deleted tinyint(1) DEFAULT 0,
    date_entered datetime DEFAULT NULL,
    date_modified datetime DEFAULT NULL,
    created_by char(36) DEFAULT NULL,
    modified_user_id char(36) DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_checklist_templates_stage (stage),
    KEY idx_checklist_templates_deleted (deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create checklist_items table
CREATE TABLE IF NOT EXISTS checklist_items (
    id char(36) NOT NULL,
    deal_id char(36) DEFAULT NULL,
    item_name varchar(255) DEFAULT NULL,
    is_completed tinyint(1) DEFAULT 0,
    completed_date datetime DEFAULT NULL,
    completed_by char(36) DEFAULT NULL,
    deleted tinyint(1) DEFAULT 0,
    date_entered datetime DEFAULT NULL,
    date_modified datetime DEFAULT NULL,
    created_by char(36) DEFAULT NULL,
    modified_user_id char(36) DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_checklist_items_deal_id (deal_id),
    KEY idx_checklist_items_deleted (deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Check if pipeline_stage_history exists and if date_modified column exists
SET @dbname = DATABASE();
SET @tablename = 'pipeline_stage_history';
SET @columnname = 'date_modified';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@dbname
      AND TABLE_NAME=@tablename
      AND COLUMN_NAME=@columnname
  ) > 0,
  "SELECT 'Column date_modified already exists' AS message",
  "ALTER TABLE pipeline_stage_history ADD COLUMN date_modified datetime DEFAULT NULL"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Check if index exists before adding
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA=@dbname
      AND TABLE_NAME=@tablename
      AND INDEX_NAME='idx_pipeline_stage_history_deal_deleted'
  ) > 0,
  "SELECT 'Index idx_pipeline_stage_history_deal_deleted already exists' AS message",
  "ALTER TABLE pipeline_stage_history ADD INDEX idx_pipeline_stage_history_deal_deleted (deal_id, deleted)"
));
PREPARE addIndexIfNotExists FROM @preparedStatement;
EXECUTE addIndexIfNotExists;
DEALLOCATE PREPARE addIndexIfNotExists;

-- Show tables to confirm creation
SHOW TABLES LIKE 'checklist%';
SHOW COLUMNS FROM pipeline_stage_history;
EOF

if [ $? -eq 0 ]; then
    echo "✅ Tables created successfully!"
    echo ""
    echo "You can now run the Playwright tests again:"
    echo "cd SuiteCRM && npm test"
else
    echo "❌ Error creating tables. Please check the database connection."
fi