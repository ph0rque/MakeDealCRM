<?php
/**
 * Template Migration Manager
 * Handles migration of template instances when versions change
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

class TemplateMigrationManager
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
     * Initiate migration process
     */
    public function initiateMigration($templateId, $fromVersionId, $toVersionId, $migrationType = 'auto')
    {
        try {
            // Validate versions exist
            $fromVersion = $this->getVersionData($fromVersionId);
            $toVersion = $this->getVersionData($toVersionId);
            
            if (!$fromVersion || !$toVersion) {
                throw new Exception('Invalid version IDs provided');
            }
            
            // Count affected instances
            $affectedCount = $this->countAffectedInstances($templateId, $fromVersionId);
            
            // Create migration record
            $migrationId = create_guid();
            $query = "INSERT INTO template_migration_log (
                id, template_id, from_version_id, to_version_id, migration_type,
                migration_status, affected_instances, started_by, date_started
            ) VALUES (
                '{$migrationId}', '{$templateId}', '{$fromVersionId}', '{$toVersionId}',
                '{$migrationType}', 'pending', {$affectedCount}, 
                '{$this->currentUser->id}', NOW()
            )";
            
            $this->db->query($query);
            
            // Generate migration plan
            $migrationPlan = $this->generateMigrationPlan($fromVersion, $toVersion);
            
            // Update migration record with plan
            $this->updateMigrationData($migrationId, [
                'migration_plan' => $migrationPlan,
                'status' => 'planned'
            ]);
            
            // Start migration based on type
            switch ($migrationType) {
                case 'auto':
                    return $this->executeAutoMigration($migrationId);
                case 'manual':
                    return $this->prepareManualMigration($migrationId);
                case 'batch':
                    return $this->scheduleBatchMigration($migrationId);
                default:
                    throw new Exception('Invalid migration type: ' . $migrationType);
            }
            
        } catch (Exception $e) {
            $this->auditLogger->logError($templateId, 'migration_initiate', $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Execute automatic migration
     */
    public function executeAutoMigration($migrationId)
    {
        try {
            // Get migration details
            $migration = $this->getMigrationById($migrationId);
            if (!$migration) {
                throw new Exception('Migration not found');
            }
            
            // Update status to running
            $this->updateMigrationStatus($migrationId, 'running');
            
            // Get migration plan
            $migrationData = json_decode($migration['migration_data'], true);
            $migrationPlan = $migrationData['migration_plan'] ?? [];
            
            // Get affected instances
            $instances = $this->getAffectedInstances($migration['template_id'], $migration['from_version_id']);
            
            $migratedCount = 0;
            $failedCount = 0;
            $errors = [];
            
            foreach ($instances as $instance) {
                try {
                    // Migrate single instance
                    $result = $this->migrateSingleInstance($instance, $migrationPlan, $migration);
                    
                    if ($result['success']) {
                        $migratedCount++;
                    } else {
                        $failedCount++;
                        $errors[] = "Instance {$instance['id']}: " . $result['error'];
                    }
                    
                } catch (Exception $e) {
                    $failedCount++;
                    $errors[] = "Instance {$instance['id']}: " . $e->getMessage();
                }
            }
            
            // Update migration results
            $status = ($failedCount > 0) ? 'completed' : 'completed';
            if ($migratedCount === 0 && $failedCount > 0) {
                $status = 'failed';
            }
            
            $this->updateMigrationResults($migrationId, $migratedCount, $failedCount, $errors, $status);
            
            // Log audit trail
            $this->auditLogger->logMigration(
                $migration['template_id'],
                $migration['from_version_id'],
                $migration['to_version_id'],
                'auto',
                $status,
                [
                    'migrated_count' => $migratedCount,
                    'failed_count' => $failedCount,
                    'total_instances' => count($instances)
                ]
            );
            
            return [
                'success' => true,
                'migration_id' => $migrationId,
                'migrated_instances' => $migratedCount,
                'failed_instances' => $failedCount,
                'total_instances' => count($instances),
                'status' => $status,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            $this->updateMigrationStatus($migrationId, 'failed');
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Prepare manual migration
     */
    public function prepareManualMigration($migrationId)
    {
        try {
            $migration = $this->getMigrationById($migrationId);
            if (!$migration) {
                throw new Exception('Migration not found');
            }
            
            // Get affected instances with detailed information
            $instances = $this->getDetailedAffectedInstances($migration['template_id'], $migration['from_version_id']);
            
            // Generate migration recommendations
            $recommendations = $this->generateMigrationRecommendations($migration, $instances);
            
            // Update migration with manual preparation data
            $manualData = [
                'instances' => $instances,
                'recommendations' => $recommendations,
                'prepared_date' => date('Y-m-d H:i:s')
            ];
            
            $this->updateMigrationData($migrationId, array_merge(
                json_decode($migration['migration_data'], true) ?? [],
                ['manual_data' => $manualData]
            ));
            
            return [
                'success' => true,
                'migration_id' => $migrationId,
                'instances' => $instances,
                'recommendations' => $recommendations,
                'message' => 'Manual migration prepared. Review instances and execute individually.'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Schedule batch migration
     */
    public function scheduleBatchMigration($migrationId)
    {
        // Implementation for batch migration scheduling
        // This would typically integrate with a job queue system
        
        try {
            // For now, execute immediately in chunks
            return $this->executeBatchMigration($migrationId);
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Execute batch migration in chunks
     */
    public function executeBatchMigration($migrationId, $chunkSize = 10)
    {
        try {
            $migration = $this->getMigrationById($migrationId);
            if (!$migration) {
                throw new Exception('Migration not found');
            }
            
            $this->updateMigrationStatus($migrationId, 'running');
            
            $instances = $this->getAffectedInstances($migration['template_id'], $migration['from_version_id']);
            $chunks = array_chunk($instances, $chunkSize);
            
            $totalMigrated = 0;
            $totalFailed = 0;
            $allErrors = [];
            
            foreach ($chunks as $chunkIndex => $chunk) {
                $chunkResult = $this->processMigrationChunk($chunk, $migration, $chunkIndex);
                
                $totalMigrated += $chunkResult['migrated'];
                $totalFailed += $chunkResult['failed'];
                $allErrors = array_merge($allErrors, $chunkResult['errors']);
                
                // Update progress
                $progress = (($chunkIndex + 1) / count($chunks)) * 100;
                $this->updateMigrationProgress($migrationId, $progress, $totalMigrated, $totalFailed);
                
                // Brief pause between chunks to avoid overwhelming the system
                usleep(100000); // 0.1 second
            }
            
            $finalStatus = ($totalFailed > 0) ? 'completed' : 'completed';
            if ($totalMigrated === 0 && $totalFailed > 0) {
                $finalStatus = 'failed';
            }
            
            $this->updateMigrationResults($migrationId, $totalMigrated, $totalFailed, $allErrors, $finalStatus);
            
            return [
                'success' => true,
                'migration_id' => $migrationId,
                'migrated_instances' => $totalMigrated,
                'failed_instances' => $totalFailed,
                'total_instances' => count($instances),
                'status' => $finalStatus,
                'chunks_processed' => count($chunks),
                'errors' => $allErrors
            ];
            
        } catch (Exception $e) {
            $this->updateMigrationStatus($migrationId, 'failed');
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Rollback migration
     */
    public function rollbackMigration($migrationId)
    {
        try {
            $migration = $this->getMigrationById($migrationId);
            if (!$migration) {
                throw new Exception('Migration not found');
            }
            
            if ($migration['migration_status'] !== 'completed') {
                throw new Exception('Can only rollback completed migrations');
            }
            
            // Get rollback data
            $migrationData = json_decode($migration['migration_data'], true);
            $rollbackData = $migrationData['rollback_data'] ?? [];
            
            if (empty($rollbackData)) {
                throw new Exception('No rollback data available for this migration');
            }
            
            // Execute rollback
            $rollbackCount = 0;
            $errors = [];
            
            foreach ($rollbackData as $instanceId => $originalData) {
                try {
                    $this->rollbackSingleInstance($instanceId, $originalData);
                    $rollbackCount++;
                } catch (Exception $e) {
                    $errors[] = "Instance {$instanceId}: " . $e->getMessage();
                }
            }
            
            // Update migration status
            $this->updateMigrationStatus($migrationId, 'rolled_back');
            
            // Log audit trail
            $this->auditLogger->logMigration(
                $migration['template_id'],
                $migration['to_version_id'], // Reversed for rollback
                $migration['from_version_id'],
                'rollback',
                'completed',
                [
                    'rolled_back_count' => $rollbackCount,
                    'rollback_errors' => count($errors)
                ]
            );
            
            return [
                'success' => true,
                'rolled_back_instances' => $rollbackCount,
                'errors' => $errors,
                'message' => 'Migration rolled back successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Private helper methods
     */
    
    private function getVersionData($versionId)
    {
        $query = "SELECT * FROM template_versions WHERE id = '{$versionId}' AND deleted = 0 LIMIT 1";
        $result = $this->db->query($query);
        return $this->db->fetchByAssoc($result);
    }
    
    private function countAffectedInstances($templateId, $versionId)
    {
        // This would query your actual template instances table
        // For now, return a mock count
        return 0; // Placeholder - implement based on your instance storage
    }
    
    private function getAffectedInstances($templateId, $versionId)
    {
        // This would query your actual template instances
        // For now, return empty array
        return []; // Placeholder - implement based on your instance storage
    }
    
    private function getDetailedAffectedInstances($templateId, $versionId)
    {
        // Similar to getAffectedInstances but with more detail for manual migration
        return []; // Placeholder
    }
    
    private function generateMigrationPlan($fromVersion, $toVersion)
    {
        $fromContent = json_decode($fromVersion['content'], true);
        $toContent = json_decode($toVersion['content'], true);
        
        // Generate a plan for migrating from one version to another
        $plan = [
            'version_change' => [
                'from' => $fromVersion['version_number'],
                'to' => $toVersion['version_number']
            ],
            'content_changes' => $this->identifyContentChanges($fromContent, $toContent),
            'migration_steps' => $this->generateMigrationSteps($fromContent, $toContent),
            'risk_assessment' => $this->assessMigrationRisk($fromContent, $toContent)
        ];
        
        return $plan;
    }
    
    private function identifyContentChanges($fromContent, $toContent)
    {
        // Identify what changed between versions
        $changes = [
            'added_tasks' => [],
            'removed_tasks' => [],
            'modified_tasks' => [],
            'metadata_changes' => []
        ];
        
        // Implementation would compare the two content structures
        // and identify specific changes
        
        return $changes;
    }
    
    private function generateMigrationSteps($fromContent, $toContent)
    {
        // Generate step-by-step migration instructions
        return [
            ['action' => 'backup_current_state'],
            ['action' => 'validate_prerequisites'],
            ['action' => 'apply_content_changes'],
            ['action' => 'update_dependencies'],
            ['action' => 'verify_migration']
        ];
    }
    
    private function assessMigrationRisk($fromContent, $toContent)
    {
        // Assess the risk level of the migration
        return [
            'risk_level' => 'low', // low, medium, high
            'risk_factors' => [],
            'recommended_approach' => 'auto'
        ];
    }
    
    private function migrateSingleInstance($instance, $migrationPlan, $migration)
    {
        // Implementation for migrating a single template instance
        // This would depend on how instances are stored in your system
        
        return [
            'success' => true,
            'message' => 'Instance migrated successfully'
        ];
    }
    
    private function processMigrationChunk($chunk, $migration, $chunkIndex)
    {
        $migrated = 0;
        $failed = 0;
        $errors = [];
        
        foreach ($chunk as $instance) {
            try {
                // Process single instance
                $migrated++;
            } catch (Exception $e) {
                $failed++;
                $errors[] = "Instance {$instance['id']}: " . $e->getMessage();
            }
        }
        
        return [
            'migrated' => $migrated,
            'failed' => $failed,
            'errors' => $errors
        ];
    }
    
    private function rollbackSingleInstance($instanceId, $originalData)
    {
        // Implementation for rolling back a single instance
        // This would restore the instance to its original state
    }
    
    private function generateMigrationRecommendations($migration, $instances)
    {
        // Generate recommendations for manual migration
        return [
            'high_priority' => [],
            'medium_priority' => [],
            'low_priority' => [],
            'warnings' => []
        ];
    }
    
    private function getMigrationById($migrationId)
    {
        $query = "SELECT * FROM template_migration_log WHERE id = '{$migrationId}' LIMIT 1";
        $result = $this->db->query($query);
        return $this->db->fetchByAssoc($result);
    }
    
    private function updateMigrationStatus($migrationId, $status)
    {
        $query = "UPDATE template_migration_log SET migration_status = '{$status}' WHERE id = '{$migrationId}'";
        $this->db->query($query);
    }
    
    private function updateMigrationData($migrationId, $data)
    {
        $jsonData = $this->db->quote(json_encode($data));
        $query = "UPDATE template_migration_log SET migration_data = '{$jsonData}' WHERE id = '{$migrationId}'";
        $this->db->query($query);
    }
    
    private function updateMigrationResults($migrationId, $migrated, $failed, $errors, $status)
    {
        $errorLog = $this->db->quote(json_encode($errors));
        $completedDate = ($status === 'completed' || $status === 'failed') ? 'NOW()' : 'NULL';
        
        $query = "UPDATE template_migration_log SET 
                    migrated_instances = {$migrated},
                    failed_instances = {$failed},
                    error_log = '{$errorLog}',
                    migration_status = '{$status}',
                    date_completed = {$completedDate}
                  WHERE id = '{$migrationId}'";
        
        $this->db->query($query);
    }
    
    private function updateMigrationProgress($migrationId, $progress, $migrated, $failed)
    {
        $progressData = json_encode([
            'progress_percent' => $progress,
            'migrated_so_far' => $migrated,
            'failed_so_far' => $failed,
            'last_update' => date('Y-m-d H:i:s')
        ]);
        
        $query = "UPDATE template_migration_log SET 
                    migration_data = JSON_SET(COALESCE(migration_data, '{}'), '$.progress', '{$progressData}')
                  WHERE id = '{$migrationId}'";
        
        $this->db->query($query);
    }
}