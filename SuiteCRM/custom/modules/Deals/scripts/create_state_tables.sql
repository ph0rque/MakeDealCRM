-- State Management Database Schema
-- Creates tables for persistent state management, synchronization, and monitoring

-- Pipeline State Storage Table
CREATE TABLE IF NOT EXISTS pipeline_state_store (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    state_data LONGTEXT NOT NULL,
    version INT NOT NULL DEFAULT 1,
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_version (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- State Changes Log Table
CREATE TABLE IF NOT EXISTS pipeline_state_changes (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    session_id VARCHAR(100) NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    action_data LONGTEXT NOT NULL,
    version INT NOT NULL,
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    applied TINYINT(1) NOT NULL DEFAULT 1,
    conflict_resolved TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_user_id (user_id),
    INDEX idx_session_id (session_id),
    INDEX idx_version (version),
    INDEX idx_timestamp (timestamp),
    INDEX idx_action_type (action_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- State Versions Table
CREATE TABLE IF NOT EXISTS pipeline_state_versions (
    id VARCHAR(36) PRIMARY KEY,
    version INT NOT NULL AUTO_INCREMENT UNIQUE,
    description VARCHAR(255),
    created_by VARCHAR(36),
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_version (version),
    INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert initial version
INSERT IGNORE INTO pipeline_state_versions (id, version, description, timestamp) 
VALUES (UUID(), 1, 'Initial state version', NOW());

-- Pipeline Change Log (Enhanced)
CREATE TABLE IF NOT EXISTS pipeline_change_log (
    id VARCHAR(36) PRIMARY KEY,
    deal_id VARCHAR(36) NOT NULL,
    change_type VARCHAR(50) NOT NULL,
    details LONGTEXT,
    user_id VARCHAR(36) NOT NULL,
    session_id VARCHAR(100),
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    old_value LONGTEXT,
    new_value LONGTEXT,
    change_source VARCHAR(20) DEFAULT 'manual', -- manual, sync, system
    INDEX idx_deal_id (deal_id),
    INDEX idx_user_id (user_id),
    INDEX idx_timestamp (timestamp),
    INDEX idx_change_type (change_type),
    INDEX idx_session_id (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sync Activity Log
CREATE TABLE IF NOT EXISTS pipeline_sync_log (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    session_id VARCHAR(100) NOT NULL,
    changes_applied INT NOT NULL DEFAULT 0,
    conflicts_found INT NOT NULL DEFAULT 0,
    sync_duration_ms INT,
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'success', -- success, failed, partial
    error_message TEXT,
    INDEX idx_user_id (user_id),
    INDEX idx_timestamp (timestamp),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conflict Resolution Log
CREATE TABLE IF NOT EXISTS pipeline_conflict_log (
    id VARCHAR(36) PRIMARY KEY,
    deal_id VARCHAR(36) NOT NULL,
    conflict_type VARCHAR(50) NOT NULL,
    client_change LONGTEXT NOT NULL,
    server_state LONGTEXT NOT NULL,
    resolution_strategy VARCHAR(50) NOT NULL,
    resolved_by VARCHAR(36),
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    auto_resolved TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_deal_id (deal_id),
    INDEX idx_timestamp (timestamp),
    INDEX idx_conflict_type (conflict_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Sessions Table (for tracking active sessions)
CREATE TABLE IF NOT EXISTS pipeline_user_sessions (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    session_id VARCHAR(100) NOT NULL UNIQUE,
    last_activity DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    user_agent TEXT,
    ip_address VARCHAR(45),
    state_version INT NOT NULL DEFAULT 1,
    websocket_connected TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_user_id (user_id),
    INDEX idx_session_id (session_id),
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- State Snapshots Table (for rollback and debugging)
CREATE TABLE IF NOT EXISTS pipeline_state_snapshots (
    id VARCHAR(36) PRIMARY KEY,
    snapshot_name VARCHAR(100) NOT NULL,
    state_data LONGTEXT NOT NULL,
    version INT NOT NULL,
    created_by VARCHAR(36) NOT NULL,
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    description TEXT,
    automatic TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_snapshot_name (snapshot_name),
    INDEX idx_version (version),
    INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Performance Metrics Table
CREATE TABLE IF NOT EXISTS pipeline_performance_metrics (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    session_id VARCHAR(100) NOT NULL,
    metric_type VARCHAR(50) NOT NULL, -- action_time, sync_latency, state_size, etc.
    metric_value DECIMAL(10,3) NOT NULL,
    additional_data JSON,
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_metric_type (metric_type),
    INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Enhanced Opportunities Custom Table (add position and state fields)
ALTER TABLE opportunities_cstm 
ADD COLUMN IF NOT EXISTS position_c INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS state_version_c INT DEFAULT 1,
ADD COLUMN IF NOT EXISTS last_state_sync_c DATETIME NULL,
ADD COLUMN IF NOT EXISTS optimistic_update_c TINYINT(1) DEFAULT 0;

-- Add indexes for better performance
ALTER TABLE opportunities_cstm 
ADD INDEX IF NOT EXISTS idx_position (position_c),
ADD INDEX IF NOT EXISTS idx_state_version (state_version_c),
ADD INDEX IF NOT EXISTS idx_pipeline_stage (pipeline_stage_c),
ADD INDEX IF NOT EXISTS idx_focus_flag (focus_flag_c),
ADD INDEX IF NOT EXISTS idx_focus_order (focus_order_c);

-- Create stored procedures for common operations

DELIMITER //

-- Procedure to get current state with version
CREATE PROCEDURE IF NOT EXISTS GetPipelineState(IN p_user_id VARCHAR(36))
BEGIN
    DECLARE current_version INT DEFAULT 1;
    
    -- Get current version
    SELECT COALESCE(MAX(version), 1) INTO current_version 
    FROM pipeline_state_versions;
    
    -- Return deals with state info
    SELECT 
        d.id,
        d.name,
        d.amount,
        d.sales_stage,
        d.probability,
        d.date_modified,
        d.assigned_user_id,
        CONCAT(u.first_name, ' ', u.last_name) as assigned_user_name,
        c.pipeline_stage_c,
        c.stage_entered_date_c,
        c.focus_flag_c,
        c.focus_order_c,
        c.position_c,
        c.state_version_c,
        current_version as server_version
    FROM opportunities d
    LEFT JOIN opportunities_cstm c ON d.id = c.id_c
    LEFT JOIN users u ON d.assigned_user_id = u.id
    WHERE d.deleted = 0
    AND d.sales_stage NOT IN ('Closed Won', 'Closed Lost')
    ORDER BY c.focus_flag_c DESC, c.focus_order_c ASC, c.position_c ASC, d.date_modified DESC;
END //

-- Procedure to log state changes
CREATE PROCEDURE IF NOT EXISTS LogStateChange(
    IN p_user_id VARCHAR(36),
    IN p_session_id VARCHAR(100),
    IN p_action_type VARCHAR(50),
    IN p_action_data LONGTEXT,
    IN p_version INT
)
BEGIN
    INSERT INTO pipeline_state_changes 
    (id, user_id, session_id, action_type, action_data, version, timestamp) 
    VALUES 
    (UUID(), p_user_id, p_session_id, p_action_type, p_action_data, p_version, NOW());
END //

-- Procedure to handle deal position updates
CREATE PROCEDURE IF NOT EXISTS UpdateDealPosition(
    IN p_deal_id VARCHAR(36),
    IN p_stage VARCHAR(50),
    IN p_position INT,
    IN p_user_id VARCHAR(36)
)
BEGIN
    DECLARE current_stage VARCHAR(50);
    
    -- Get current stage
    SELECT pipeline_stage_c INTO current_stage 
    FROM opportunities_cstm 
    WHERE id_c = p_deal_id;
    
    -- If stage changed, update other deals in the target stage
    IF current_stage != p_stage OR current_stage IS NULL THEN
        -- Increment positions of deals at or after the target position
        UPDATE opportunities_cstm 
        SET position_c = position_c + 1 
        WHERE pipeline_stage_c = p_stage 
        AND position_c >= p_position
        AND id_c != p_deal_id;
    ELSE
        -- Same stage, just reorder
        UPDATE opportunities_cstm 
        SET position_c = position_c - 1 
        WHERE pipeline_stage_c = p_stage 
        AND position_c > (SELECT position_c FROM opportunities_cstm WHERE id_c = p_deal_id)
        AND position_c <= p_position
        AND id_c != p_deal_id;
        
        UPDATE opportunities_cstm 
        SET position_c = position_c + 1 
        WHERE pipeline_stage_c = p_stage 
        AND position_c < (SELECT position_c FROM opportunities_cstm WHERE id_c = p_deal_id)
        AND position_c >= p_position
        AND id_c != p_deal_id;
    END IF;
    
    -- Update the deal itself
    UPDATE opportunities_cstm 
    SET 
        pipeline_stage_c = p_stage,
        position_c = p_position,
        stage_entered_date_c = CASE 
            WHEN current_stage != p_stage OR current_stage IS NULL 
            THEN NOW() 
            ELSE stage_entered_date_c 
        END,
        state_version_c = state_version_c + 1,
        last_state_sync_c = NOW()
    WHERE id_c = p_deal_id;
    
    -- Log the change
    INSERT INTO pipeline_change_log 
    (id, deal_id, change_type, details, user_id, timestamp, change_source) 
    VALUES 
    (UUID(), p_deal_id, 'position_updated', 
     JSON_OBJECT('old_stage', current_stage, 'new_stage', p_stage, 'position', p_position), 
     p_user_id, NOW(), 'state_manager');
END //

-- Procedure to clean up old state data
CREATE PROCEDURE IF NOT EXISTS CleanupOldStateData(IN p_days_to_keep INT DEFAULT 30)
BEGIN
    DECLARE cutoff_date DATETIME;
    SET cutoff_date = DATE_SUB(NOW(), INTERVAL p_days_to_keep DAY);
    
    -- Clean up old changes (keep recent ones for conflict resolution)
    DELETE FROM pipeline_state_changes 
    WHERE timestamp < cutoff_date 
    AND applied = 1 
    AND conflict_resolved = 0;
    
    -- Clean up old sync logs
    DELETE FROM pipeline_sync_log 
    WHERE timestamp < cutoff_date;
    
    -- Clean up old performance metrics
    DELETE FROM pipeline_performance_metrics 
    WHERE timestamp < cutoff_date;
    
    -- Clean up inactive sessions
    DELETE FROM pipeline_user_sessions 
    WHERE last_activity < DATE_SUB(NOW(), INTERVAL 1 DAY);
END //

DELIMITER ;

-- Create views for common queries

-- View for current pipeline state
CREATE OR REPLACE VIEW pipeline_current_state AS
SELECT 
    d.id,
    d.name,
    d.amount,
    d.sales_stage,
    d.probability,
    d.date_modified,
    d.assigned_user_id,
    CONCAT(u.first_name, ' ', u.last_name) as assigned_user_name,
    a.name as account_name,
    c.pipeline_stage_c,
    c.stage_entered_date_c,
    c.focus_flag_c,
    c.focus_order_c,
    c.position_c,
    c.state_version_c,
    DATEDIFF(NOW(), c.stage_entered_date_c) as days_in_stage,
    CASE 
        WHEN DATEDIFF(NOW(), c.stage_entered_date_c) > 30 THEN 'red'
        WHEN DATEDIFF(NOW(), c.stage_entered_date_c) > 14 THEN 'orange'
        ELSE 'normal'
    END as stage_color_class
FROM opportunities d
LEFT JOIN opportunities_cstm c ON d.id = c.id_c
LEFT JOIN users u ON d.assigned_user_id = u.id
LEFT JOIN accounts a ON d.account_id = a.id
WHERE d.deleted = 0
AND d.sales_stage NOT IN ('Closed Won', 'Closed Lost');

-- View for sync statistics
CREATE OR REPLACE VIEW pipeline_sync_stats AS
SELECT 
    user_id,
    COUNT(*) as total_syncs,
    AVG(changes_applied) as avg_changes_per_sync,
    AVG(conflicts_found) as avg_conflicts_per_sync,
    AVG(sync_duration_ms) as avg_sync_duration,
    MAX(timestamp) as last_sync,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_syncs
FROM pipeline_sync_log
GROUP BY user_id;

-- Create initial cleanup event (runs daily)
CREATE EVENT IF NOT EXISTS pipeline_daily_cleanup
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
  CALL CleanupOldStateData(30);

-- Grant necessary permissions (adjust as needed for your SuiteCRM setup)
-- GRANT SELECT, INSERT, UPDATE, DELETE ON pipeline_* TO 'suitecrm_user'@'localhost';

-- Create indexes for JSON columns (MySQL 5.7+)
-- ALTER TABLE pipeline_performance_metrics ADD INDEX idx_additional_data_type ((JSON_EXTRACT(additional_data, '$.type')));

-- Success message
SELECT 'State Management Database Schema Created Successfully' as result;