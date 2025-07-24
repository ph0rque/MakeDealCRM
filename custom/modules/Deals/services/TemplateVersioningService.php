<?php
/**
 * Template Versioning Service
 * Handles version control, rollback capabilities, and audit logging for templates
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('custom/modules/Deals/services/TemplateAuditLogger.php');
require_once('custom/modules/Deals/services/TemplateVersionComparator.php');
require_once('custom/modules/Deals/services/TemplateMigrationManager.php');

class TemplateVersioningService
{
    private $db;
    private $auditLogger;
    private $comparator;
    private $migrationManager;
    private $currentUser;
    
    public function __construct()
    {
        global $db, $current_user;
        $this->db = $db;
        $this->currentUser = $current_user;
        $this->auditLogger = new TemplateAuditLogger();
        $this->comparator = new TemplateVersionComparator();
        $this->migrationManager = new TemplateMigrationManager();
    }
    
    /**
     * Create a new template version
     */
    public function createVersion($templateId, $content, $versionType = 'minor', $changesSummary = '', $isDraft = false)
    {
        try {
            // Validate input
            if (empty($templateId) || empty($content)) {
                throw new Exception('Template ID and content are required');
            }
            
            // Get current version for increment
            $currentVersion = $this->getCurrentVersion($templateId);
            $newVersionNumber = $this->calculateNextVersion($currentVersion, $versionType);
            
            // Parse version number
            $versionParts = $this->parseVersionNumber($newVersionNumber);
            
            // Generate content hash for integrity
            $contentHash = hash('sha256', json_encode($content));
            
            // Check for duplicate content
            if ($this->isDuplicateContent($templateId, $contentHash)) {
                throw new Exception('No changes detected - content is identical to existing version');
            }
            
            // Create version record
            $versionId = create_guid();
            $query = "INSERT INTO template_versions (
                id, template_id, version_number, major_version, minor_version, patch_version,
                content, content_hash, change_summary, is_current, is_draft, 
                approval_status, created_by, date_created
            ) VALUES (
                '{$versionId}', '{$templateId}', '{$newVersionNumber}', 
                {$versionParts['major']}, {$versionParts['minor']}, {$versionParts['patch']},
                '" . $this->db->quote(json_encode($content)) . "', '{$contentHash}', 
                '" . $this->db->quote($changesSummary) . "', 
                " . ($isDraft ? '0' : '1') . ", " . ($isDraft ? '1' : '0') . ",
                '" . ($isDraft ? 'draft' : 'approved') . "',
                '{$this->currentUser->id}', NOW()
            )";
            
            $this->db->query($query);
            
            // If not draft, update current version flags
            if (!$isDraft) {
                $this->setCurrentVersion($templateId, $versionId);
            }
            
            // Log audit trail
            $this->auditLogger->logAction($templateId, $versionId, 'create', $this->currentUser->id, [
                'version_number' => $newVersionNumber,
                'version_type' => $versionType,
                'is_draft' => $isDraft,
                'changes_summary' => $changesSummary
            ]);
            
            return [
                'success' => true,
                'version_id' => $versionId,
                'version_number' => $newVersionNumber,
                'message' => 'Version created successfully'
            ];
            
        } catch (Exception $e) {
            $this->auditLogger->logError($templateId, 'create_version', $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Rollback to a previous version
     */
    public function rollbackToVersion($templateId, $targetVersionId, $reason = '')
    {
        try {
            // Validate target version exists
            $targetVersion = $this->getVersionById($targetVersionId);
            if (!$targetVersion) {
                throw new Exception('Target version not found');
            }
            
            // Get current version for backup
            $currentVersion = $this->getCurrentVersionData($templateId);
            
            // Create rollback backup
            $backupResult = $this->createVersion(
                $templateId, 
                json_decode($currentVersion['content'], true),
                'patch',
                'Automatic backup before rollback to version ' . $targetVersion['version_number'],
                true // Create as draft backup
            );
            
            if (!$backupResult['success']) {
                throw new Exception('Failed to create rollback backup: ' . $backupResult['error']);
            }
            
            // Set target version as current
            $this->setCurrentVersion($templateId, $targetVersionId);
            
            // Trigger migration if needed
            $this->migrationManager->initiateMigration(
                $templateId, 
                $currentVersion['id'], 
                $targetVersionId,
                'rollback'
            );
            
            // Log audit trail
            $this->auditLogger->logAction($templateId, $targetVersionId, 'rollback', $this->currentUser->id, [
                'from_version' => $currentVersion['version_number'],
                'to_version' => $targetVersion['version_number'],
                'reason' => $reason,
                'backup_version_id' => $backupResult['version_id']
            ]);
            
            return [
                'success' => true,
                'from_version' => $currentVersion['version_number'],
                'to_version' => $targetVersion['version_number'],
                'backup_version_id' => $backupResult['version_id'],
                'message' => 'Successfully rolled back to version ' . $targetVersion['version_number']
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
     * Compare two versions
     */
    public function compareVersions($fromVersionId, $toVersionId, $diffType = 'semantic')
    {
        try {
            return $this->comparator->compareVersions($fromVersionId, $toVersionId, $diffType);
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get version history for a template
     */
    public function getVersionHistory($templateId, $includeDeleted = false)
    {
        $deletedCondition = $includeDeleted ? '' : 'AND deleted = 0';
        
        $query = "SELECT 
                    id, version_number, major_version, minor_version, patch_version,
                    version_name, change_summary, is_current, is_draft, approval_status,
                    approved_by, approval_date, created_by, date_created, date_modified
                  FROM template_versions 
                  WHERE template_id = '{$templateId}' {$deletedCondition}
                  ORDER BY major_version DESC, minor_version DESC, patch_version DESC, date_created DESC";
        
        $result = $this->db->query($query);
        $versions = [];
        
        while ($row = $this->db->fetchByAssoc($result)) {
            $versions[] = $row;
        }
        
        return $versions;
    }
    
    /**
     * Get audit trail for template or version
     */
    public function getAuditTrail($templateId, $versionId = null, $limit = 100)
    {
        return $this->auditLogger->getAuditTrail($templateId, $versionId, $limit);
    }
    
    /**
     * Create a new branch from a version
     */
    public function createBranch($templateId, $parentVersionId, $branchName, $branchType = 'feature', $description = '')
    {
        try {
            // Validate branch name doesn't exist
            if ($this->branchExists($templateId, $branchName)) {
                throw new Exception('Branch name already exists for this template');
            }
            
            // Create branch record
            $branchId = create_guid();
            $query = "INSERT INTO template_branches (
                id, template_id, branch_name, parent_version_id, branch_type,
                branch_status, description, created_by, date_created
            ) VALUES (
                '{$branchId}', '{$templateId}', '" . $this->db->quote($branchName) . "',
                '{$parentVersionId}', '{$branchType}', 'active',
                '" . $this->db->quote($description) . "', '{$this->currentUser->id}', NOW()
            )";
            
            $this->db->query($query);
            
            // Log audit trail
            $this->auditLogger->logAction($templateId, $parentVersionId, 'branch', $this->currentUser->id, [
                'branch_id' => $branchId,
                'branch_name' => $branchName,
                'branch_type' => $branchType,
                'description' => $description
            ]);
            
            return [
                'success' => true,
                'branch_id' => $branchId,
                'message' => 'Branch created successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Merge branch back to main
     */
    public function mergeBranch($branchId, $targetVersionId, $mergeMessage = '')
    {
        try {
            // Get branch information
            $branch = $this->getBranchById($branchId);
            if (!$branch || $branch['branch_status'] !== 'active') {
                throw new Exception('Branch not found or not active');
            }
            
            // Mark branch as merged
            $query = "UPDATE template_branches SET 
                        branch_status = 'merged',
                        merged_by = '{$this->currentUser->id}',
                        date_merged = NOW(),
                        merge_target_version_id = '{$targetVersionId}'
                      WHERE id = '{$branchId}'";
            
            $this->db->query($query);
            
            // Log audit trail
            $this->auditLogger->logAction($branch['template_id'], $targetVersionId, 'merge', $this->currentUser->id, [
                'branch_id' => $branchId,
                'branch_name' => $branch['branch_name'],
                'merge_message' => $mergeMessage
            ]);
            
            return [
                'success' => true,
                'message' => 'Branch merged successfully'
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
    
    private function getCurrentVersion($templateId)
    {
        $query = "SELECT version_number FROM template_versions 
                  WHERE template_id = '{$templateId}' AND is_current = 1 AND deleted = 0
                  ORDER BY major_version DESC, minor_version DESC, patch_version DESC 
                  LIMIT 1";
        
        $result = $this->db->query($query);
        $row = $this->db->fetchByAssoc($result);
        
        return $row ? $row['version_number'] : '0.0.0';
    }
    
    private function getCurrentVersionData($templateId)
    {
        $query = "SELECT * FROM template_versions 
                  WHERE template_id = '{$templateId}' AND is_current = 1 AND deleted = 0
                  LIMIT 1";
        
        $result = $this->db->query($query);
        return $this->db->fetchByAssoc($result);
    }
    
    private function calculateNextVersion($currentVersion, $type)
    {
        $parts = $this->parseVersionNumber($currentVersion);
        
        switch ($type) {
            case 'major':
                $parts['major']++;
                $parts['minor'] = 0;
                $parts['patch'] = 0;
                break;
            case 'minor':
                $parts['minor']++;
                $parts['patch'] = 0;
                break;
            case 'patch':
            default:
                $parts['patch']++;
                break;
        }
        
        return "{$parts['major']}.{$parts['minor']}.{$parts['patch']}";
    }
    
    private function parseVersionNumber($version)
    {
        $parts = explode('.', $version);
        return [
            'major' => (int)($parts[0] ?? 0),
            'minor' => (int)($parts[1] ?? 0),
            'patch' => (int)($parts[2] ?? 0)
        ];
    }
    
    private function isDuplicateContent($templateId, $contentHash)
    {
        $query = "SELECT id FROM template_versions 
                  WHERE template_id = '{$templateId}' AND content_hash = '{$contentHash}' AND deleted = 0
                  LIMIT 1";
        
        $result = $this->db->query($query);
        return (bool)$this->db->fetchByAssoc($result);
    }
    
    private function setCurrentVersion($templateId, $versionId)
    {
        // Clear all current flags for this template
        $query = "UPDATE template_versions SET is_current = 0 
                  WHERE template_id = '{$templateId}'";
        $this->db->query($query);
        
        // Set new current version
        $query = "UPDATE template_versions SET is_current = 1 
                  WHERE id = '{$versionId}'";
        $this->db->query($query);
    }
    
    private function getVersionById($versionId)
    {
        $query = "SELECT * FROM template_versions WHERE id = '{$versionId}' AND deleted = 0 LIMIT 1";
        $result = $this->db->query($query);
        return $this->db->fetchByAssoc($result);
    }
    
    private function branchExists($templateId, $branchName)
    {
        $query = "SELECT id FROM template_branches 
                  WHERE template_id = '{$templateId}' AND branch_name = '" . $this->db->quote($branchName) . "' 
                  AND deleted = 0 LIMIT 1";
        
        $result = $this->db->query($query);
        return (bool)$this->db->fetchByAssoc($result);
    }
    
    private function getBranchById($branchId)
    {
        $query = "SELECT * FROM template_branches WHERE id = '{$branchId}' AND deleted = 0 LIMIT 1";
        $result = $this->db->query($query);
        return $this->db->fetchByAssoc($result);
    }
}