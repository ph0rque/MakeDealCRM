<?php
/**
 * Checklist Permission Manager
 * Handles role-based access control for checklist templates and instances
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('include/SugarLogger/LoggerManager.php');

class ChecklistPermissionManager
{
    // Permission levels
    const PERMISSION_READ = 'read';
    const PERMISSION_WRITE = 'write';
    const PERMISSION_ADMIN = 'admin';
    
    // Role types
    const ROLE_VIEWER = 'viewer';
    const ROLE_EDITOR = 'editor';
    const ROLE_ADMIN = 'admin';
    const ROLE_OWNER = 'owner';
    
    // Sharing levels
    const SHARE_PRIVATE = 'private';
    const SHARE_TEAM = 'team';
    const SHARE_ORGANIZATION = 'organization';
    const SHARE_PUBLIC = 'public';
    
    private $db;
    private $current_user;
    private $logger;
    
    public function __construct()
    {
        global $db, $current_user;
        $this->db = $db;
        $this->current_user = $current_user;
        $this->logger = LoggerManager::getLogger('ChecklistPermissions');
    }
    
    /**
     * Check if user has specific permission on checklist template
     */
    public function hasPermission($template_id, $permission, $user_id = null)
    {
        if (empty($user_id)) {
            $user_id = $this->current_user->id;
        }
        
        // Validate input
        if (!$this->validateGUID($template_id) || !$this->validateGUID($user_id)) {
            $this->logger->error("Invalid GUID provided to hasPermission");
            return false;
        }
        
        // Check if user is template owner (owners have all permissions)
        if ($this->isTemplateOwner($template_id, $user_id)) {
            return true;
        }
        
        // Check direct user permissions
        $user_permission = $this->getUserPermission($template_id, $user_id);
        if ($this->isPermissionSufficient($user_permission, $permission)) {
            return true;
        }
        
        // Check team permissions
        $team_permission = $this->getTeamPermission($template_id, $user_id);
        if ($this->isPermissionSufficient($team_permission, $permission)) {
            return true;
        }
        
        // Check organization-wide permissions
        $org_permission = $this->getOrganizationPermission($template_id, $user_id);
        return $this->isPermissionSufficient($org_permission, $permission);
    }
    
    /**
     * Grant permission to user for checklist template
     */
    public function grantPermission($template_id, $user_id, $permission, $granted_by = null)
    {
        if (empty($granted_by)) {
            $granted_by = $this->current_user->id;
        }
        
        // Validate input
        if (!$this->validateGUID($template_id) || !$this->validateGUID($user_id) || !$this->validateGUID($granted_by)) {
            throw new InvalidArgumentException("Invalid GUID provided");
        }
        
        if (!in_array($permission, [self::PERMISSION_READ, self::PERMISSION_WRITE, self::PERMISSION_ADMIN])) {
            throw new InvalidArgumentException("Invalid permission level");
        }
        
        // Check if granter has admin permission
        if (!$this->hasPermission($template_id, self::PERMISSION_ADMIN, $granted_by)) {
            throw new AccessDeniedException("Insufficient permissions to grant access");
        }
        
        // Use prepared statement to prevent SQL injection
        $stmt = $this->db->getConnection()->prepare("
            INSERT INTO checklist_template_permissions 
            (id, template_id, user_id, permission_level, granted_by, date_granted, deleted) 
            VALUES (?, ?, ?, ?, ?, NOW(), 0)
            ON DUPLICATE KEY UPDATE 
            permission_level = VALUES(permission_level),
            granted_by = VALUES(granted_by),
            date_modified = NOW()
        ");
        
        $id = create_guid();
        $stmt->bind_param('sssss', $id, $template_id, $user_id, $permission, $granted_by);
        
        if ($stmt->execute()) {
            $this->logPermissionChange('grant', $template_id, $user_id, $permission, $granted_by);
            return true;
        }
        
        return false;
    }
    
    /**
     * Revoke permission from user for checklist template
     */
    public function revokePermission($template_id, $user_id, $revoked_by = null)
    {
        if (empty($revoked_by)) {
            $revoked_by = $this->current_user->id;
        }
        
        // Validate input
        if (!$this->validateGUID($template_id) || !$this->validateGUID($user_id) || !$this->validateGUID($revoked_by)) {
            throw new InvalidArgumentException("Invalid GUID provided");
        }
        
        // Check if revoker has admin permission
        if (!$this->hasPermission($template_id, self::PERMISSION_ADMIN, $revoked_by)) {
            throw new AccessDeniedException("Insufficient permissions to revoke access");
        }
        
        // Cannot revoke owner's permissions
        if ($this->isTemplateOwner($template_id, $user_id)) {
            throw new AccessDeniedException("Cannot revoke owner's permissions");
        }
        
        $stmt = $this->db->getConnection()->prepare("
            UPDATE checklist_template_permissions 
            SET deleted = 1, date_modified = NOW() 
            WHERE template_id = ? AND user_id = ? AND deleted = 0
        ");
        
        $stmt->bind_param('ss', $template_id, $user_id);
        
        if ($stmt->execute()) {
            $this->logPermissionChange('revoke', $template_id, $user_id, null, $revoked_by);
            return true;
        }
        
        return false;
    }
    
    /**
     * Grant team permission for checklist template
     */
    public function grantTeamPermission($template_id, $team_id, $permission, $granted_by = null)
    {
        if (empty($granted_by)) {
            $granted_by = $this->current_user->id;
        }
        
        // Validate input
        if (!$this->validateGUID($template_id) || !$this->validateGUID($team_id) || !$this->validateGUID($granted_by)) {
            throw new InvalidArgumentException("Invalid GUID provided");
        }
        
        if (!in_array($permission, [self::PERMISSION_READ, self::PERMISSION_WRITE, self::PERMISSION_ADMIN])) {
            throw new InvalidArgumentException("Invalid permission level");
        }
        
        // Check if granter has admin permission
        if (!$this->hasPermission($template_id, self::PERMISSION_ADMIN, $granted_by)) {
            throw new AccessDeniedException("Insufficient permissions to grant team access");
        }
        
        $stmt = $this->db->getConnection()->prepare("
            INSERT INTO checklist_template_team_permissions 
            (id, template_id, team_id, permission_level, granted_by, date_granted, deleted) 
            VALUES (?, ?, ?, ?, ?, NOW(), 0)
            ON DUPLICATE KEY UPDATE 
            permission_level = VALUES(permission_level),
            granted_by = VALUES(granted_by),
            date_modified = NOW()
        ");
        
        $id = create_guid();
        $stmt->bind_param('sssss', $id, $template_id, $team_id, $permission, $granted_by);
        
        if ($stmt->execute()) {
            $this->logPermissionChange('grant_team', $template_id, $team_id, $permission, $granted_by);
            return true;
        }
        
        return false;
    }
    
    /**
     * Set sharing level for checklist template
     */
    public function setTemplateSharing($template_id, $sharing_level, $set_by = null)
    {
        if (empty($set_by)) {
            $set_by = $this->current_user->id;
        }
        
        // Validate input
        if (!$this->validateGUID($template_id) || !$this->validateGUID($set_by)) {
            throw new InvalidArgumentException("Invalid GUID provided");
        }
        
        if (!in_array($sharing_level, [self::SHARE_PRIVATE, self::SHARE_TEAM, self::SHARE_ORGANIZATION, self::SHARE_PUBLIC])) {
            throw new InvalidArgumentException("Invalid sharing level");
        }
        
        // Check if user has admin permission
        if (!$this->hasPermission($template_id, self::PERMISSION_ADMIN, $set_by)) {
            throw new AccessDeniedException("Insufficient permissions to change sharing settings");
        }
        
        $stmt = $this->db->getConnection()->prepare("
            UPDATE checklist_templates 
            SET sharing_level = ?, modified_by = ?, date_modified = NOW() 
            WHERE id = ? AND deleted = 0
        ");
        
        $stmt->bind_param('sss', $sharing_level, $set_by, $template_id);
        
        if ($stmt->execute()) {
            $this->logPermissionChange('sharing_change', $template_id, null, $sharing_level, $set_by);
            return true;
        }
        
        return false;
    }
    
    /**
     * Get list of users with access to template
     */
    public function getTemplateAccessList($template_id)
    {
        if (!$this->validateGUID($template_id)) {
            return [];
        }
        
        // Check if current user has admin permission
        if (!$this->hasPermission($template_id, self::PERMISSION_ADMIN)) {
            throw new AccessDeniedException("Insufficient permissions to view access list");
        }
        
        $stmt = $this->db->getConnection()->prepare("
            SELECT 
                p.user_id,
                p.permission_level,
                p.date_granted,
                u.first_name,
                u.last_name,
                u.user_name,
                'direct' as permission_source
            FROM checklist_template_permissions p
            JOIN users u ON p.user_id = u.id
            WHERE p.template_id = ? AND p.deleted = 0 AND u.deleted = 0
            
            UNION
            
            SELECT 
                u.id as user_id,
                tp.permission_level,
                tp.date_granted,
                u.first_name,
                u.last_name,
                u.user_name,
                'team' as permission_source
            FROM checklist_template_team_permissions tp
            JOIN team_memberships tm ON tp.team_id = tm.team_id
            JOIN users u ON tm.user_id = u.id
            WHERE tp.template_id = ? AND tp.deleted = 0 AND tm.deleted = 0 AND u.deleted = 0
            
            ORDER BY permission_level DESC, last_name, first_name
        ");
        
        $stmt->bind_param('ss', $template_id, $template_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $access_list = [];
        while ($row = $result->fetch_assoc()) {
            $access_list[] = $row;
        }
        
        return $access_list;
    }
    
    /**
     * Check if sharing level allows access
     */
    public function checkSharingAccess($template_id, $user_id = null)
    {
        if (empty($user_id)) {
            $user_id = $this->current_user->id;
        }
        
        if (!$this->validateGUID($template_id) || !$this->validateGUID($user_id)) {
            return false;
        }
        
        $stmt = $this->db->getConnection()->prepare("
            SELECT sharing_level, created_by 
            FROM checklist_templates 
            WHERE id = ? AND deleted = 0
        ");
        
        $stmt->bind_param('s', $template_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $template = $result->fetch_assoc();
        
        if (!$template) {
            return false;
        }
        
        // Owner always has access
        if ($template['created_by'] === $user_id) {
            return true;
        }
        
        switch ($template['sharing_level']) {
            case self::SHARE_PUBLIC:
                return true;
                
            case self::SHARE_ORGANIZATION:
                return $this->isUserInSameOrganization($template['created_by'], $user_id);
                
            case self::SHARE_TEAM:
                return $this->isUserInSameTeam($template['created_by'], $user_id);
                
            case self::SHARE_PRIVATE:
            default:
                return false;
        }
    }
    
    /**
     * Create audit log entry for permission changes
     */
    private function logPermissionChange($action, $template_id, $target_id, $permission, $user_id)
    {
        $stmt = $this->db->getConnection()->prepare("
            INSERT INTO checklist_permission_audit 
            (id, template_id, action, target_id, permission_level, performed_by, date_performed, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");
        
        $id = create_guid();
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt->bind_param('ssssssss', $id, $template_id, $action, $target_id, $permission, $user_id, $ip_address, $user_agent);
        $stmt->execute();
        
        $this->logger->info("Permission change logged: $action for template $template_id by user $user_id");
    }
    
    /**
     * Helper methods
     */
    private function validateGUID($guid)
    {
        return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $guid);
    }
    
    private function isTemplateOwner($template_id, $user_id)
    {
        $stmt = $this->db->getConnection()->prepare("
            SELECT created_by FROM checklist_templates 
            WHERE id = ? AND deleted = 0
        ");
        
        $stmt->bind_param('s', $template_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row && $row['created_by'] === $user_id;
    }
    
    private function getUserPermission($template_id, $user_id)
    {
        $stmt = $this->db->getConnection()->prepare("
            SELECT permission_level FROM checklist_template_permissions 
            WHERE template_id = ? AND user_id = ? AND deleted = 0
        ");
        
        $stmt->bind_param('ss', $template_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row ? $row['permission_level'] : null;
    }
    
    private function getTeamPermission($template_id, $user_id)
    {
        $stmt = $this->db->getConnection()->prepare("
            SELECT MAX(tp.permission_level) as permission_level
            FROM checklist_template_team_permissions tp
            JOIN team_memberships tm ON tp.team_id = tm.team_id
            WHERE tp.template_id = ? AND tm.user_id = ? 
            AND tp.deleted = 0 AND tm.deleted = 0
        ");
        
        $stmt->bind_param('ss', $template_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row ? $row['permission_level'] : null;
    }
    
    private function getOrganizationPermission($template_id, $user_id)
    {
        // Check sharing level and organization membership
        if ($this->checkSharingAccess($template_id, $user_id)) {
            return self::PERMISSION_READ; // Default org permission is read
        }
        
        return null;
    }
    
    private function isPermissionSufficient($user_permission, $required_permission)
    {
        if (empty($user_permission)) {
            return false;
        }
        
        $hierarchy = [
            self::PERMISSION_READ => 1,
            self::PERMISSION_WRITE => 2,
            self::PERMISSION_ADMIN => 3
        ];
        
        return $hierarchy[$user_permission] >= $hierarchy[$required_permission];
    }
    
    private function isUserInSameOrganization($user1_id, $user2_id)
    {
        // In SuiteCRM, this would check if users belong to same organization
        // For now, return true (all users in same org)
        return true;
    }
    
    private function isUserInSameTeam($user1_id, $user2_id)
    {
        $stmt = $this->db->getConnection()->prepare("
            SELECT COUNT(*) as shared_teams
            FROM team_memberships tm1
            JOIN team_memberships tm2 ON tm1.team_id = tm2.team_id
            WHERE tm1.user_id = ? AND tm2.user_id = ? 
            AND tm1.deleted = 0 AND tm2.deleted = 0
        ");
        
        $stmt->bind_param('ss', $user1_id, $user2_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row && $row['shared_teams'] > 0;
    }
}

/**
 * Custom exceptions for permission system
 */
class AccessDeniedException extends Exception {}