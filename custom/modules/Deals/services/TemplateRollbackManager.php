<?php
/**
 * Template Rollback Manager
 * Handles rollback operations with backup and recovery capabilities
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

class TemplateRollbackManager
{
    private $db;
    private $currentUser;
    private $auditLogger;
    
    public function __construct()
    {
        global $db, $current_user;
        $this->db = $db;
        $this->currentUser = $current_user;
        $this->auditLogger = new TemplateAuditLogger();
    }
    
    /**
     * Perform rollback with comprehensive safety checks
     */
    public function performRollback($templateId, $targetVersionId, $options = [])
    {
        try {
            // Validate parameters
            if (empty($templateId) || empty($targetVersionId)) {
                throw new Exception('Template ID and target version ID are required');
            }
            
            // Get current and target version data
            $currentVersion = $this->getCurrentVersion($templateId);
            $targetVersion = $this->getVersionById($targetVersionId);
            
            if (!$currentVersion || !$targetVersion) {
                throw new Exception('Invalid version data');
            }
            
            // Validate rollback is allowed
            $validationResult = $this->validateRollback($templateId, $currentVersion, $targetVersion, $options);
            if (!$validationResult['allowed']) {
                throw new Exception('Rollback not allowed: ' . $validationResult['reason']);
            }
            
            // Create safety backup
            $backupResult = $this->createSafetyBackup($templateId, $currentVersion, $options);
            if (!$backupResult['success']) {
                throw new Exception('Failed to create safety backup: ' . $backupResult['error']);
            }
            
            // Perform pre-rollback checks
            $preCheckResult = $this->performPreRollbackChecks($templateId, $targetVersion, $options);
            if (!$preCheckResult['passed']) {
                throw new Exception('Pre-rollback checks failed: ' . implode(', ', $preCheckResult['errors']));
            }
            
            // Execute rollback
            $rollbackResult = $this->executeRollback($templateId, $currentVersion, $targetVersion, $options);
            if (!$rollbackResult['success']) {
                // Attempt to restore from backup
                $this->restoreFromBackup($templateId, $backupResult['backup_id']);
                throw new Exception('Rollback failed: ' . $rollbackResult['error']);
            }
            
            // Perform post-rollback validation
            $postCheckResult = $this->performPostRollbackChecks($templateId, $targetVersion, $options);
            if (!$postCheckResult['passed']) {
                // Rollback the rollback (restore from backup)
                $this->restoreFromBackup($templateId, $backupResult['backup_id']);
                throw new Exception('Post-rollback validation failed: ' . implode(', ', $postCheckResult['errors']));
            }
            
            // Update system state
            $this->updateSystemState($templateId, $currentVersion, $targetVersion, $backupResult['backup_id']);
            
            // Log successful rollback
            $this->auditLogger->logAction($templateId, $targetVersionId, 'rollback', $this->currentUser->id, [
                'from_version' => $currentVersion['version_number'],
                'to_version' => $targetVersion['version_number'],
                'backup_id' => $backupResult['backup_id'],
                'rollback_reason' => $options['reason'] ?? '',
                'validation_checks' => $preCheckResult['checks'],
                'post_validation' => $postCheckResult['checks']
            ]);
            
            return [
                'success' => true,
                'from_version' => $currentVersion['version_number'],
                'to_version' => $targetVersion['version_number'],
                'backup_id' => $backupResult['backup_id'],
                'rollback_id' => $rollbackResult['rollback_id'],
                'checks_performed' => array_merge($preCheckResult['checks'], $postCheckResult['checks']),
                'message' => 'Rollback completed successfully'
            ];
            
        } catch (Exception $e) {
            $this->auditLogger->logError($templateId, 'rollback', $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate if rollback is allowed
     */
    private function validateRollback($templateId, $currentVersion, $targetVersion, $options)
    {
        $validation = [
            'allowed' => true,
            'reason' => '',
            'warnings' => []
        ];
        
        // Check if target version is older than current
        if ($this->compareVersionNumbers($targetVersion['version_number'], $currentVersion['version_number']) >= 0) {
            $validation['allowed'] = false;
            $validation['reason'] = 'Target version must be older than current version';
            return $validation;
        }
        
        // Check if there are active instances using this template
        $activeInstancesCount = $this->countActiveInstances($templateId);
        if ($activeInstancesCount > 0 && !($options['force_with_instances'] ?? false)) {
            $validation['allowed'] = false;
            $validation['reason'] = "Template has {$activeInstancesCount} active instances. Use force_with_instances option to proceed.";
            return $validation;
        }
        
        // Check for breaking changes
        $breakingChanges = $this->identifyBreakingChanges($currentVersion, $targetVersion);
        if (!empty($breakingChanges) && !($options['allow_breaking_changes'] ?? false)) {
            $validation['allowed'] = false;
            $validation['reason'] = 'Breaking changes detected: ' . implode(', ', $breakingChanges);
            return $validation;
        }
        
        // Check rollback depth limit
        $maxRollbackDepth = $options['max_rollback_depth'] ?? 10;
        $versionDepth = $this->calculateVersionDepth($currentVersion, $targetVersion);
        if ($versionDepth > $maxRollbackDepth) {
            $validation['allowed'] = false;
            $validation['reason'] = "Rollback depth ({$versionDepth}) exceeds maximum allowed ({$maxRollbackDepth})";
            return $validation;
        }
        
        // Add warnings for significant rollbacks
        if ($versionDepth > 5) {
            $validation['warnings'][] = "Rolling back {$versionDepth} versions - significant change";
        }
        
        if ($activeInstancesCount > 0) {
            $validation['warnings'][] = "{$activeInstancesCount} active instances will be affected";
        }
        
        return $validation;
    }
    
    /**
     * Create safety backup before rollback
     */
    private function createSafetyBackup($templateId, $currentVersion, $options)
    {
        try {
            $backupId = create_guid();
            $backupData = [
                'version_id' => $currentVersion['id'],
                'version_number' => $currentVersion['version_number'],
                'content' => $currentVersion['content'],
                'backup_type' => 'pre_rollback',
                'backup_reason' => 'Safety backup before rollback to ' . ($options['target_version'] ?? 'unknown'),
                'created_by' => $this->currentUser->id,
                'created_date' => date('Y-m-d H:i:s'),
                'template_state' => $this->captureTemplateState($templateId)
            ];
            
            // Store backup data
            $query = "INSERT INTO template_rollback_backups (
                id, template_id, backup_data, backup_type, created_by, date_created
            ) VALUES (
                '{$backupId}', '{$templateId}', 
                '" . $this->db->quote(json_encode($backupData)) . "',
                'pre_rollback', '{$this->currentUser->id}', NOW()
            )";
            
            $this->db->query($query);
            
            return [
                'success' => true,
                'backup_id' => $backupId,
                'backup_data' => $backupData
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Perform pre-rollback checks
     */
    private function performPreRollbackChecks($templateId, $targetVersion, $options)
    {
        $checks = [
            'passed' => true,
            'errors' => [],
            'warnings' => [],
            'checks' => []
        ];
        
        // Check 1: Validate target version content integrity
        $contentCheck = $this->validateVersionContent($targetVersion);
        $checks['checks']['content_integrity'] = $contentCheck;
        if (!$contentCheck['valid']) {
            $checks['passed'] = false;
            $checks['errors'][] = 'Target version content integrity check failed: ' . $contentCheck['error'];
        }
        
        // Check 2: Validate template dependencies
        $dependencyCheck = $this->validateTemplateDependencies($templateId, $targetVersion);
        $checks['checks']['dependencies'] = $dependencyCheck;
        if (!$dependencyCheck['valid']) {
            $checks['passed'] = false;
            $checks['errors'][] = 'Template dependency check failed: ' . $dependencyCheck['error'];
        }
        
        // Check 3: Database state consistency
        $dbCheck = $this->validateDatabaseState($templateId);
        $checks['checks']['database_state'] = $dbCheck;
        if (!$dbCheck['valid']) {
            $checks['passed'] = false;
            $checks['errors'][] = 'Database state check failed: ' . $dbCheck['error'];
        }
        
        // Check 4: Permission validation
        $permissionCheck = $this->validateRollbackPermissions($templateId, $targetVersion);
        $checks['checks']['permissions'] = $permissionCheck;
        if (!$permissionCheck['valid']) {
            $checks['passed'] = false;
            $checks['errors'][] = 'Permission check failed: ' . $permissionCheck['error'];
        }
        
        // Check 5: System resource availability
        $resourceCheck = $this->validateSystemResources($options);
        $checks['checks']['system_resources'] = $resourceCheck;
        if (!$resourceCheck['valid']) {
            $checks['warnings'][] = 'System resource warning: ' . $resourceCheck['warning'];
        }
        
        return $checks;
    }
    
    /**
     * Execute the actual rollback
     */
    private function executeRollback($templateId, $currentVersion, $targetVersion, $options)
    {
        try {
            $rollbackId = create_guid();
            
            // Start transaction
            $this->db->query('START TRANSACTION');
            
            // Clear current version flag
            $query = "UPDATE template_versions SET is_current = 0 WHERE template_id = '{$templateId}'";
            $this->db->query($query);
            
            // Set target version as current
            $query = "UPDATE template_versions SET is_current = 1 WHERE id = '{$targetVersion['id']}'";
            $this->db->query($query);
            
            // Create rollback record
            $rollbackData = [
                'from_version_id' => $currentVersion['id'],
                'to_version_id' => $targetVersion['id'],
                'rollback_type' => $options['rollback_type'] ?? 'manual',
                'rollback_reason' => $options['reason'] ?? '',
                'performed_by' => $this->currentUser->id,
                'rollback_date' => date('Y-m-d H:i:s'),
                'options' => $options
            ];
            
            $query = "INSERT INTO template_rollback_log (
                id, template_id, rollback_data, performed_by, date_performed
            ) VALUES (
                '{$rollbackId}', '{$templateId}', 
                '" . $this->db->quote(json_encode($rollbackData)) . "',
                '{$this->currentUser->id}', NOW()
            )";
            
            $this->db->query($query);
            
            // Handle any necessary data migrations
            $migrationResult = $this->handleRollbackMigrations($templateId, $currentVersion, $targetVersion, $options);
            if (!$migrationResult['success']) {
                throw new Exception('Migration during rollback failed: ' . $migrationResult['error']);
            }
            
            // Commit transaction
            $this->db->query('COMMIT');
            
            return [
                'success' => true,
                'rollback_id' => $rollbackId,
                'migration_result' => $migrationResult
            ];
            
        } catch (Exception $e) {
            $this->db->query('ROLLBACK');
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Perform post-rollback validation
     */
    private function performPostRollbackChecks($templateId, $targetVersion, $options)
    {
        $checks = [
            'passed' => true,
            'errors' => [],
            'warnings' => [],
            'checks' => []
        ];
        
        // Check 1: Verify current version is correctly set
        $currentCheck = $this->validateCurrentVersionSet($templateId, $targetVersion['id']);
        $checks['checks']['current_version'] = $currentCheck;
        if (!$currentCheck['valid']) {
            $checks['passed'] = false;
            $checks['errors'][] = 'Current version not correctly set';
        }
        
        // Check 2: Validate template content accessibility
        $accessCheck = $this->validateTemplateAccess($templateId);
        $checks['checks']['template_access'] = $accessCheck;
        if (!$accessCheck['valid']) {
            $checks['passed'] = false;
            $checks['errors'][] = 'Template access validation failed: ' . $accessCheck['error'];
        }
        
        // Check 3: Verify related data consistency
        $consistencyCheck = $this->validateDataConsistency($templateId, $targetVersion);
        $checks['checks']['data_consistency'] = $consistencyCheck;
        if (!$consistencyCheck['valid']) {
            $checks['passed'] = false;
            $checks['errors'][] = 'Data consistency check failed: ' . $consistencyCheck['error'];
        }
        
        return $checks;
    }
    
    /**
     * Restore from backup in case of rollback failure
     */
    private function restoreFromBackup($templateId, $backupId)
    {
        try {
            // Get backup data
            $query = "SELECT backup_data FROM template_rollback_backups WHERE id = '{$backupId}' LIMIT 1";
            $result = $this->db->query($query);
            $backup = $this->db->fetchByAssoc($result);
            
            if (!$backup) {
                throw new Exception('Backup not found');
            }
            
            $backupData = json_decode($backup['backup_data'], true);
            
            // Restore previous state
            $this->db->query('START TRANSACTION');
            
            // Clear current flags
            $query = "UPDATE template_versions SET is_current = 0 WHERE template_id = '{$templateId}'";
            $this->db->query($query);
            
            // Restore original current version
            $query = "UPDATE template_versions SET is_current = 1 WHERE id = '{$backupData['version_id']}'";
            $this->db->query($query);
            
            // Restore any additional state if needed
            $this->restoreTemplateState($templateId, $backupData['template_state']);
            
            $this->db->query('COMMIT');
            
            // Log restoration
            $this->auditLogger->logAction($templateId, $backupData['version_id'], 'restore', $this->currentUser->id, [
                'backup_id' => $backupId,
                'restoration_reason' => 'Rollback failure recovery'
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->db->query('ROLLBACK');
            $this->auditLogger->logError($templateId, 'restore_backup', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Helper methods
     */
    
    private function getCurrentVersion($templateId)
    {
        $query = "SELECT * FROM template_versions 
                  WHERE template_id = '{$templateId}' AND is_current = 1 AND deleted = 0 
                  LIMIT 1";
        $result = $this->db->query($query);
        return $this->db->fetchByAssoc($result);
    }
    
    private function getVersionById($versionId)
    {
        $query = "SELECT * FROM template_versions WHERE id = '{$versionId}' AND deleted = 0 LIMIT 1";
        $result = $this->db->query($query);
        return $this->db->fetchByAssoc($result);
    }
    
    private function compareVersionNumbers($version1, $version2)
    {
        return version_compare($version1, $version2);
    }
    
    private function countActiveInstances($templateId)
    {
        // This would count active instances using this template
        // Implementation depends on your instance storage structure
        return 0; // Placeholder
    }
    
    private function identifyBreakingChanges($fromVersion, $toVersion)
    {
        $fromContent = json_decode($fromVersion['content'], true);
        $toContent = json_decode($toVersion['content'], true);
        
        $breakingChanges = [];
        
        // Check for removed required fields
        $fromRequired = $this->getRequiredFields($fromContent);
        $toRequired = $this->getRequiredFields($toContent);
        
        $removedRequired = array_diff($fromRequired, $toRequired);
        if (!empty($removedRequired)) {
            $breakingChanges[] = 'Removed required fields: ' . implode(', ', $removedRequired);
        }
        
        // Check for changed data types
        $typeChanges = $this->identifyTypeChanges($fromContent, $toContent);
        if (!empty($typeChanges)) {
            $breakingChanges[] = 'Data type changes: ' . implode(', ', $typeChanges);
        }
        
        return $breakingChanges;
    }
    
    private function calculateVersionDepth($fromVersion, $toVersion)
    {
        $fromParts = explode('.', $fromVersion['version_number']);
        $toParts = explode('.', $toVersion['version_number']);
        
        $majorDiff = (int)$fromParts[0] - (int)$toParts[0];
        $minorDiff = (int)$fromParts[1] - (int)$toParts[1];
        $patchDiff = (int)$fromParts[2] - (int)$toParts[2];
        
        return $majorDiff + $minorDiff + $patchDiff;
    }
    
    private function captureTemplateState($templateId)
    {
        // Capture current template state for backup
        return [
            'capture_time' => date('Y-m-d H:i:s'),
            'active_instances' => $this->countActiveInstances($templateId),
            'permissions' => $this->getTemplatePermissions($templateId),
            'metadata' => $this->getTemplateMetadata($templateId)
        ];
    }
    
    private function validateVersionContent($version)
    {
        try {
            $content = json_decode($version['content'], true);
            if (!$content) {
                return ['valid' => false, 'error' => 'Invalid JSON content'];
            }
            
            // Validate content structure
            if (!isset($content['tasks']) || !is_array($content['tasks'])) {
                return ['valid' => false, 'error' => 'Missing or invalid tasks array'];
            }
            
            return ['valid' => true];
            
        } catch (Exception $e) {
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function validateTemplateDependencies($templateId, $version)
    {
        // Validate template dependencies are satisfied
        return ['valid' => true]; // Placeholder
    }
    
    private function validateDatabaseState($templateId)
    {
        // Check database state consistency
        return ['valid' => true]; // Placeholder
    }
    
    private function validateRollbackPermissions($templateId, $version)
    {
        // Validate user has permissions for rollback
        return ['valid' => true]; // Placeholder
    }
    
    private function validateSystemResources($options)
    {
        // Check system resources
        return ['valid' => true]; // Placeholder
    }
    
    private function handleRollbackMigrations($templateId, $fromVersion, $toVersion, $options)
    {
        // Handle any necessary data migrations during rollback
        return ['success' => true]; // Placeholder
    }
    
    private function validateCurrentVersionSet($templateId, $expectedVersionId)
    {
        $query = "SELECT id FROM template_versions 
                  WHERE template_id = '{$templateId}' AND is_current = 1 AND id = '{$expectedVersionId}'
                  LIMIT 1";
        $result = $this->db->query($query);
        $isSet = (bool)$this->db->fetchByAssoc($result);
        
        return ['valid' => $isSet];
    }
    
    private function validateTemplateAccess($templateId)
    {
        // Validate template is accessible after rollback
        return ['valid' => true]; // Placeholder
    }
    
    private function validateDataConsistency($templateId, $version)
    {
        // Validate data consistency after rollback
        return ['valid' => true]; // Placeholder
    }
    
    private function updateSystemState($templateId, $fromVersion, $toVersion, $backupId)
    {
        // Update any additional system state after successful rollback
        // This might include clearing caches, updating indexes, etc.
    }
    
    private function restoreTemplateState($templateId, $templateState)
    {
        // Restore template state from backup
        // Implementation depends on what state data was captured
    }
    
    private function getRequiredFields($content)
    {
        $required = [];
        if (isset($content['tasks'])) {
            foreach ($content['tasks'] as $task) {
                if (isset($task['required']) && $task['required']) {
                    $required[] = $task['id'];
                }
            }
        }
        return $required;
    }
    
    private function identifyTypeChanges($fromContent, $toContent)
    {
        // Identify data type changes between versions
        return []; // Placeholder
    }
    
    private function getTemplatePermissions($templateId)
    {
        // Get template permissions for backup
        return []; // Placeholder
    }
    
    private function getTemplateMetadata($templateId)
    {
        // Get template metadata for backup
        return []; // Placeholder
    }
}

// Add table for rollback backups if it doesn't exist
if (!class_exists('TemplateRollbackBackupSchema')) {
    class TemplateRollbackBackupSchema {
        public static function createTableIfNotExists($db) {
            $query = "CREATE TABLE IF NOT EXISTS template_rollback_backups (
                id char(36) NOT NULL PRIMARY KEY,
                template_id char(36) NOT NULL,
                backup_data longtext NOT NULL,
                backup_type enum('pre_rollback', 'manual', 'automated') DEFAULT 'manual',
                created_by char(36) NOT NULL,
                date_created datetime NOT NULL,
                expires_date datetime DEFAULT NULL,
                KEY idx_template_backup (template_id, date_created),
                KEY idx_backup_type (backup_type, date_created)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
            
            $db->query($query);
            
            $query = "CREATE TABLE IF NOT EXISTS template_rollback_log (
                id char(36) NOT NULL PRIMARY KEY,
                template_id char(36) NOT NULL,
                rollback_data longtext NOT NULL,
                performed_by char(36) NOT NULL,
                date_performed datetime NOT NULL,
                KEY idx_template_rollback (template_id, date_performed),
                KEY idx_performer (performed_by, date_performed)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
            
            $db->query($query);
        }
    }
}