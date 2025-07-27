<?php
/**
 * Template Versioning API Controller
 * RESTful API endpoints for template version management
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('custom/modules/Deals/services/TemplateVersioningService.php');

class TemplateVersioningApi
{
    private $versioningService;
    private $currentUser;
    
    public function __construct()
    {
        global $current_user;
        $this->currentUser = $current_user;
        $this->versioningService = new TemplateVersioningService();
    }
    
    /**
     * Handle API requests
     */
    public function handleRequest()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $requestUri = $_SERVER['REQUEST_URI'];
        
        // Parse the request path
        $path = parse_url($requestUri, PHP_URL_PATH);
        $pathParts = explode('/', trim($path, '/'));
        
        // Route to appropriate method
        try {
            switch ($method) {
                case 'GET':
                    return $this->handleGet($pathParts);
                case 'POST':
                    return $this->handlePost($pathParts);
                case 'PUT':
                    return $this->handlePut($pathParts);
                case 'DELETE':
                    return $this->handleDelete($pathParts);
                default:
                    return $this->errorResponse('Method not allowed', 405);
            }
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
    
    /**
     * Handle GET requests
     */
    private function handleGet($pathParts)
    {
        $endpoint = $pathParts[count($pathParts) - 1];
        
        switch ($endpoint) {
            case 'versions':
                return $this->getVersionHistory();
            case 'compare':
                return $this->compareVersions();
            case 'audit':
                return $this->getAuditTrail();
            case 'branches':
                return $this->getBranches();
            case 'migrations':
                return $this->getMigrationStatus();
            default:
                if (isset($pathParts[count($pathParts) - 2]) && $pathParts[count($pathParts) - 2] === 'versions') {
                    return $this->getVersionDetails($endpoint);
                }
                return $this->errorResponse('Endpoint not found', 404);
        }
    }
    
    /**
     * Handle POST requests
     */
    private function handlePost($pathParts)
    {
        $endpoint = $pathParts[count($pathParts) - 1];
        $data = $this->getRequestData();
        
        switch ($endpoint) {
            case 'versions':
                return $this->createVersion($data);
            case 'rollback':
                return $this->rollbackVersion($data);
            case 'branches':
                return $this->createBranch($data);
            case 'merge':
                return $this->mergeBranch($data);
            case 'migrate':
                return $this->initiateMigration($data);
            case 'approve':
                return $this->approveVersion($data);
            case 'reject':
                return $this->rejectVersion($data);
            default:
                return $this->errorResponse('Endpoint not found', 404);
        }
    }
    
    /**
     * Handle PUT requests
     */
    private function handlePut($pathParts)
    {
        $endpoint = $pathParts[count($pathParts) - 1];
        $data = $this->getRequestData();
        
        switch ($endpoint) {
            case 'publish':
                return $this->publishVersion($data);
            case 'archive':
                return $this->archiveVersion($data);
            default:
                return $this->errorResponse('Endpoint not found', 404);
        }
    }
    
    /**
     * Handle DELETE requests
     */
    private function handleDelete($pathParts)
    {
        $versionId = $pathParts[count($pathParts) - 1];
        return $this->deleteVersion($versionId);
    }
    
    /**
     * Create new version
     */
    private function createVersion($data)
    {
        // Validate required fields
        $required = ['template_id', 'content'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->errorResponse("Missing required field: {$field}", 400);
            }
        }
        
        // Validate permissions
        if (!$this->hasPermission($data['template_id'], 'edit')) {
            return $this->errorResponse('Insufficient permissions', 403);
        }
        
        $result = $this->versioningService->createVersion(
            $data['template_id'],
            $data['content'],
            $data['version_type'] ?? 'minor',
            $data['changes_summary'] ?? '',
            $data['is_draft'] ?? false
        );
        
        return $this->jsonResponse($result, $result['success'] ? 201 : 400);
    }
    
    /**
     * Get version history
     */
    private function getVersionHistory()
    {
        $templateId = $_GET['template_id'] ?? '';
        if (empty($templateId)) {
            return $this->errorResponse('template_id parameter required', 400);
        }
        
        if (!$this->hasPermission($templateId, 'view')) {
            return $this->errorResponse('Insufficient permissions', 403);
        }
        
        $includeDeleted = $_GET['include_deleted'] === 'true';
        $versions = $this->versioningService->getVersionHistory($templateId, $includeDeleted);
        
        return $this->jsonResponse([
            'success' => true,
            'versions' => $versions,
            'count' => count($versions)
        ]);
    }
    
    /**
     * Get version details
     */
    private function getVersionDetails($versionId)
    {
        // Validate version exists and user has permission
        global $db;
        $query = "SELECT tv.*, td.name as template_name 
                  FROM template_versions tv 
                  JOIN template_definitions td ON tv.template_id = td.id 
                  WHERE tv.id = '{$versionId}' AND tv.deleted = 0";
        
        $result = $db->query($query);
        $version = $db->fetchByAssoc($result);
        
        if (!$version) {
            return $this->errorResponse('Version not found', 404);
        }
        
        if (!$this->hasPermission($version['template_id'], 'view')) {
            return $this->errorResponse('Insufficient permissions', 403);
        }
        
        // Parse content if requested
        if (($_GET['include_content'] ?? 'false') === 'true') {
            $version['parsed_content'] = json_decode($version['content'], true);
        }
        
        return $this->jsonResponse([
            'success' => true,
            'version' => $version
        ]);
    }
    
    /**
     * Compare versions
     */
    private function compareVersions()
    {
        $fromVersionId = $_GET['from_version'] ?? '';
        $toVersionId = $_GET['to_version'] ?? '';
        $diffType = $_GET['diff_type'] ?? 'semantic';
        
        if (empty($fromVersionId) || empty($toVersionId)) {
            return $this->errorResponse('from_version and to_version parameters required', 400);
        }
        
        // Check permissions for both versions
        $versions = $this->getVersionsData([$fromVersionId, $toVersionId]);
        foreach ($versions as $version) {
            if (!$this->hasPermission($version['template_id'], 'view')) {
                return $this->errorResponse('Insufficient permissions', 403);
            }
        }
        
        $result = $this->versioningService->compareVersions($fromVersionId, $toVersionId, $diffType);
        
        return $this->jsonResponse($result, $result['success'] ? 200 : 400);
    }
    
    /**
     * Rollback to version
     */
    private function rollbackVersion($data)
    {
        $required = ['template_id', 'target_version_id'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->errorResponse("Missing required field: {$field}", 400);
            }
        }
        
        if (!$this->hasPermission($data['template_id'], 'admin')) {
            return $this->errorResponse('Admin permissions required for rollback', 403);
        }
        
        $result = $this->versioningService->rollbackToVersion(
            $data['template_id'],
            $data['target_version_id'],
            $data['reason'] ?? ''
        );
        
        return $this->jsonResponse($result, $result['success'] ? 200 : 400);
    }
    
    /**
     * Get audit trail
     */
    private function getAuditTrail()
    {
        $templateId = $_GET['template_id'] ?? '';
        if (empty($templateId)) {
            return $this->errorResponse('template_id parameter required', 400);
        }
        
        if (!$this->hasPermission($templateId, 'view')) {
            return $this->errorResponse('Insufficient permissions', 403);
        }
        
        $versionId = $_GET['version_id'] ?? null;
        $limit = min((int)($_GET['limit'] ?? 100), 500); // Max 500 records
        
        $auditTrail = $this->versioningService->getAuditTrail($templateId, $versionId, $limit);
        
        return $this->jsonResponse([
            'success' => true,
            'audit_trail' => $auditTrail,
            'count' => count($auditTrail)
        ]);
    }
    
    /**
     * Create branch
     */
    private function createBranch($data)
    {
        $required = ['template_id', 'parent_version_id', 'branch_name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->errorResponse("Missing required field: {$field}", 400);
            }
        }
        
        if (!$this->hasPermission($data['template_id'], 'edit')) {
            return $this->errorResponse('Insufficient permissions', 403);
        }
        
        $result = $this->versioningService->createBranch(
            $data['template_id'],
            $data['parent_version_id'],
            $data['branch_name'],
            $data['branch_type'] ?? 'feature',
            $data['description'] ?? ''
        );
        
        return $this->jsonResponse($result, $result['success'] ? 201 : 400);
    }
    
    /**
     * Merge branch
     */
    private function mergeBranch($data)
    {
        $required = ['branch_id', 'target_version_id'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->errorResponse("Missing required field: {$field}", 400);
            }
        }
        
        // Get branch info to check permissions
        global $db;
        $query = "SELECT template_id FROM template_branches WHERE id = '{$data['branch_id']}' AND deleted = 0";
        $result = $db->query($query);
        $branch = $db->fetchByAssoc($result);
        
        if (!$branch) {
            return $this->errorResponse('Branch not found', 404);
        }
        
        if (!$this->hasPermission($branch['template_id'], 'edit')) {
            return $this->errorResponse('Insufficient permissions', 403);
        }
        
        $result = $this->versioningService->mergeBranch(
            $data['branch_id'],
            $data['target_version_id'],
            $data['merge_message'] ?? ''
        );
        
        return $this->jsonResponse($result, $result['success'] ? 200 : 400);
    }
    
    /**
     * Get branches
     */
    private function getBranches()
    {
        $templateId = $_GET['template_id'] ?? '';
        if (empty($templateId)) {
            return $this->errorResponse('template_id parameter required', 400);
        }
        
        if (!$this->hasPermission($templateId, 'view')) {
            return $this->errorResponse('Insufficient permissions', 403);
        }
        
        global $db;
        $query = "SELECT * FROM template_branches 
                  WHERE template_id = '{$templateId}' AND deleted = 0 
                  ORDER BY date_created DESC";
        
        $result = $db->query($query);
        $branches = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            $branches[] = $row;
        }
        
        return $this->jsonResponse([
            'success' => true,
            'branches' => $branches,
            'count' => count($branches)
        ]);
    }
    
    /**
     * Initiate migration
     */
    private function initiateMigration($data)
    {
        $required = ['template_id', 'from_version_id', 'to_version_id'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->errorResponse("Missing required field: {$field}", 400);
            }
        }
        
        if (!$this->hasPermission($data['template_id'], 'admin')) {
            return $this->errorResponse('Admin permissions required for migration', 403);
        }
        
        $migrationManager = new TemplateMigrationManager();
        $result = $migrationManager->initiateMigration(
            $data['template_id'],
            $data['from_version_id'],
            $data['to_version_id'],
            $data['migration_type'] ?? 'auto'
        );
        
        return $this->jsonResponse($result, $result['success'] ? 202 : 400);
    }
    
    /**
     * Get migration status
     */
    private function getMigrationStatus()
    {
        $templateId = $_GET['template_id'] ?? '';
        $migrationId = $_GET['migration_id'] ?? '';
        
        if (empty($templateId) && empty($migrationId)) {
            return $this->errorResponse('template_id or migration_id parameter required', 400);
        }
        
        global $db;
        $whereClause = '';
        if ($migrationId) {
            $whereClause = "WHERE id = '{$migrationId}'";
        } else {
            if (!$this->hasPermission($templateId, 'view')) {
                return $this->errorResponse('Insufficient permissions', 403);
            }
            $whereClause = "WHERE template_id = '{$templateId}'";
        }
        
        $query = "SELECT * FROM template_migration_log {$whereClause} ORDER BY date_started DESC LIMIT 20";
        $result = $db->query($query);
        $migrations = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            // Parse migration data
            $row['migration_data'] = json_decode($row['migration_data'], true) ?? [];
            $row['error_log'] = json_decode($row['error_log'], true) ?? [];
            $migrations[] = $row;
        }
        
        return $this->jsonResponse([
            'success' => true,
            'migrations' => $migrations,
            'count' => count($migrations)
        ]);
    }
    
    /**
     * Approve version
     */
    private function approveVersion($data)
    {
        $required = ['version_id'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->errorResponse("Missing required field: {$field}", 400);
            }
        }
        
        // Get version info for permission check
        $version = $this->getVersionData($data['version_id']);
        if (!$version) {
            return $this->errorResponse('Version not found', 404);
        }
        
        if (!$this->hasPermission($version['template_id'], 'admin')) {
            return $this->errorResponse('Admin permissions required for approval', 403);
        }
        
        global $db;
        $query = "UPDATE template_versions SET 
                    approval_status = 'approved',
                    approved_by = '{$this->currentUser->id}',
                    approval_date = NOW(),
                    approval_notes = '" . $db->quote($data['notes'] ?? '') . "'
                  WHERE id = '{$data['version_id']}'";
        
        $db->query($query);
        
        return $this->jsonResponse([
            'success' => true,
            'message' => 'Version approved successfully'
        ]);
    }
    
    /**
     * Reject version
     */
    private function rejectVersion($data)
    {
        $required = ['version_id', 'reason'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->errorResponse("Missing required field: {$field}", 400);
            }
        }
        
        // Get version info for permission check  
        $version = $this->getVersionData($data['version_id']);
        if (!$version) {
            return $this->errorResponse('Version not found', 404);
        }
        
        if (!$this->hasPermission($version['template_id'], 'admin')) {
            return $this->errorResponse('Admin permissions required for rejection', 403);
        }
        
        global $db;
        $query = "UPDATE template_versions SET 
                    approval_status = 'rejected',
                    approved_by = '{$this->currentUser->id}',
                    approval_date = NOW(),
                    approval_notes = '" . $db->quote($data['reason']) . "'
                  WHERE id = '{$data['version_id']}'";
        
        $db->query($query);
        
        return $this->jsonResponse([
            'success' => true,
            'message' => 'Version rejected'
        ]);
    }
    
    /**
     * Publish version (set as current)
     */
    private function publishVersion($data)
    {
        $required = ['version_id'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->errorResponse("Missing required field: {$field}", 400);
            }
        }
        
        $version = $this->getVersionData($data['version_id']);
        if (!$version) {
            return $this->errorResponse('Version not found', 404);
        }
        
        if (!$this->hasPermission($version['template_id'], 'publish')) {
            return $this->errorResponse('Publish permissions required', 403);
        }
        
        // Set as current version
        global $db;
        
        // Clear current flags for template
        $query = "UPDATE template_versions SET is_current = 0 WHERE template_id = '{$version['template_id']}'";
        $db->query($query);
        
        // Set new current version
        $query = "UPDATE template_versions SET 
                    is_current = 1, 
                    is_draft = 0,
                    approval_status = 'approved'
                  WHERE id = '{$data['version_id']}'";
        $db->query($query);
        
        return $this->jsonResponse([
            'success' => true,
            'message' => 'Version published successfully'
        ]);
    }
    
    /**
     * Archive version
     */
    private function archiveVersion($data)
    {
        $required = ['version_id'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->errorResponse("Missing required field: {$field}", 400);
            }
        }
        
        $version = $this->getVersionData($data['version_id']);
        if (!$version) {
            return $this->errorResponse('Version not found', 404);
        }
        
        if (!$this->hasPermission($version['template_id'], 'admin')) {
            return $this->errorResponse('Admin permissions required', 403);
        }
        
        global $db;
        $query = "UPDATE template_versions SET deleted = 1 WHERE id = '{$data['version_id']}'";
        $db->query($query);
        
        return $this->jsonResponse([
            'success' => true,
            'message' => 'Version archived successfully'
        ]);
    }
    
    /**
     * Delete version
     */
    private function deleteVersion($versionId)
    {
        $version = $this->getVersionData($versionId);
        if (!$version) {
            return $this->errorResponse('Version not found', 404);
        }
        
        if (!$this->hasPermission($version['template_id'], 'admin')) {
            return $this->errorResponse('Admin permissions required', 403);
        }
        
        if ($version['is_current'] == 1) {
            return $this->errorResponse('Cannot delete current version', 400);
        }
        
        global $db;
        $query = "UPDATE template_versions SET deleted = 1 WHERE id = '{$versionId}'";
        $db->query($query);
        
        return $this->jsonResponse([
            'success' => true,
            'message' => 'Version deleted successfully'
        ]);
    }
    
    /**
     * Helper methods
     */
    
    private function getRequestData()
    {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }
    
    private function jsonResponse($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    private function errorResponse($message, $statusCode = 400)
    {
        return $this->jsonResponse([
            'success' => false,
            'error' => $message
        ], $statusCode);
    }
    
    private function hasPermission($templateId, $permission)
    {
        // Implement permission checking logic
        // For now, return true for authenticated users
        return !empty($this->currentUser->id);
    }
    
    private function getVersionData($versionId)
    {
        global $db;
        $query = "SELECT * FROM template_versions WHERE id = '{$versionId}' AND deleted = 0 LIMIT 1";
        $result = $db->query($query);
        return $db->fetchByAssoc($result);
    }
    
    private function getVersionsData($versionIds)
    {
        global $db;
        $ids = implode("','", $versionIds);
        $query = "SELECT * FROM template_versions WHERE id IN ('{$ids}') AND deleted = 0";
        
        $result = $db->query($query);
        $versions = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            $versions[] = $row;
        }
        
        return $versions;
    }
}