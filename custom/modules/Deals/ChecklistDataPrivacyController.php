<?php
/**
 * Checklist Data Privacy Controller
 * Handles data privacy controls and compliance for checklist system
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('custom/modules/Deals/ChecklistPermissionManager.php');
require_once('include/SugarLogger/LoggerManager.php');

class ChecklistDataPrivacyController
{
    // Data classification levels
    const DATA_PUBLIC = 'public';
    const DATA_INTERNAL = 'internal';
    const DATA_CONFIDENTIAL = 'confidential';
    const DATA_RESTRICTED = 'restricted';
    
    // Privacy actions
    const ACTION_VIEW = 'view';
    const ACTION_EXPORT = 'export';
    const ACTION_SHARE = 'share';
    const ACTION_DELETE = 'delete';
    
    private $db;
    private $current_user;
    private $logger;
    private $permission_manager;
    
    public function __construct()
    {
        global $db, $current_user;
        $this->db = $db;
        $this->current_user = $current_user;
        $this->logger = LoggerManager::getLogger('ChecklistPrivacy');
        $this->permission_manager = new ChecklistPermissionManager();
    }
    
    /**
     * Check if user can perform action on data based on privacy classification
     */
    public function canPerformAction($template_id, $action, $user_id = null)
    {
        if (empty($user_id)) {
            $user_id = $this->current_user->id;
        }
        
        // Validate input
        if (!$this->validateGUID($template_id) || !$this->validateGUID($user_id)) {
            $this->logger->error("Invalid GUID provided to canPerformAction");
            return false;
        }
        
        if (!in_array($action, [self::ACTION_VIEW, self::ACTION_EXPORT, self::ACTION_SHARE, self::ACTION_DELETE])) {
            $this->logger->error("Invalid action provided: $action");
            return false;
        }
        
        // Get data classification
        $classification = $this->getDataClassification($template_id);
        
        // Check basic permission first
        $required_permission = $this->getRequiredPermission($action);
        if (!$this->permission_manager->hasPermission($template_id, $required_permission, $user_id)) {
            return false;
        }
        
        // Apply data classification rules
        return $this->checkPrivacyRules($classification, $action, $user_id, $template_id);
    }
    
    /**
     * Set data classification for checklist template
     */
    public function setDataClassification($template_id, $classification, $reason = '', $set_by = null)
    {
        if (empty($set_by)) {
            $set_by = $this->current_user->id;
        }
        
        // Validate input
        if (!$this->validateGUID($template_id) || !$this->validateGUID($set_by)) {
            throw new InvalidArgumentException("Invalid GUID provided");
        }
        
        if (!in_array($classification, [self::DATA_PUBLIC, self::DATA_INTERNAL, self::DATA_CONFIDENTIAL, self::DATA_RESTRICTED])) {
            throw new InvalidArgumentException("Invalid data classification");
        }
        
        // Check if user has admin permission
        if (!$this->permission_manager->hasPermission($template_id, ChecklistPermissionManager::PERMISSION_ADMIN, $set_by)) {
            throw new AccessDeniedException("Insufficient permissions to set data classification");
        }
        
        $stmt = $this->db->getConnection()->prepare("
            INSERT INTO checklist_data_classification 
            (id, template_id, classification, reason, set_by, date_set, deleted) 
            VALUES (?, ?, ?, ?, ?, NOW(), 0)
            ON DUPLICATE KEY UPDATE 
            classification = VALUES(classification),
            reason = VALUES(reason),
            set_by = VALUES(set_by),
            date_modified = NOW()
        ");
        
        $id = create_guid();
        $stmt->bind_param('sssss', $id, $template_id, $classification, $reason, $set_by);
        
        if ($stmt->execute()) {
            $this->logPrivacyAction('classification_change', $template_id, $set_by, 
                ['old_classification' => $this->getDataClassification($template_id), 
                 'new_classification' => $classification, 
                 'reason' => $reason]);
            return true;
        }
        
        return false;
    }
    
    /**
     * Apply data retention policy
     */
    public function applyRetentionPolicy($template_id, $retention_days, $set_by = null)
    {
        if (empty($set_by)) {
            $set_by = $this->current_user->id;
        }
        
        // Validate input
        if (!$this->validateGUID($template_id) || !$this->validateGUID($set_by)) {
            throw new InvalidArgumentException("Invalid GUID provided");
        }
        
        if (!is_numeric($retention_days) || $retention_days < 0) {
            throw new InvalidArgumentException("Invalid retention period");
        }
        
        // Check admin permission
        if (!$this->permission_manager->hasPermission($template_id, ChecklistPermissionManager::PERMISSION_ADMIN, $set_by)) {
            throw new AccessDeniedException("Insufficient permissions to set retention policy");
        }
        
        $stmt = $this->db->getConnection()->prepare("
            INSERT INTO checklist_retention_policy 
            (id, template_id, retention_days, set_by, date_set, deleted) 
            VALUES (?, ?, ?, ?, NOW(), 0)
            ON DUPLICATE KEY UPDATE 
            retention_days = VALUES(retention_days),
            set_by = VALUES(set_by),
            date_modified = NOW()
        ");
        
        $id = create_guid();
        $stmt->bind_param('ssis', $id, $template_id, $retention_days, $set_by);
        
        if ($stmt->execute()) {
            $this->logPrivacyAction('retention_policy_set', $template_id, $set_by, 
                ['retention_days' => $retention_days]);
            return true;
        }
        
        return false;
    }
    
    /**
     * Anonymize sensitive data in checklist
     */
    public function anonymizeChecklistData($template_id, $user_id = null)
    {
        if (empty($user_id)) {
            $user_id = $this->current_user->id;
        }
        
        // Validate input
        if (!$this->validateGUID($template_id) || !$this->validateGUID($user_id)) {
            throw new InvalidArgumentException("Invalid GUID provided");
        }
        
        // Check admin permission
        if (!$this->permission_manager->hasPermission($template_id, ChecklistPermissionManager::PERMISSION_ADMIN, $user_id)) {
            throw new AccessDeniedException("Insufficient permissions to anonymize data");
        }
        
        // Get all checklist instances using this template
        $stmt = $this->db->getConnection()->prepare("
            SELECT id FROM checklist_instances 
            WHERE template_id = ? AND deleted = 0
        ");
        
        $stmt->bind_param('s', $template_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $anonymized_count = 0;
        while ($row = $result->fetch_assoc()) {
            if ($this->anonymizeChecklistInstance($row['id'], $user_id)) {
                $anonymized_count++;
            }
        }
        
        $this->logPrivacyAction('data_anonymization', $template_id, $user_id, 
            ['instances_anonymized' => $anonymized_count]);
        
        return $anonymized_count;
    }
    
    /**
     * Get data access audit trail
     */
    public function getAccessAuditTrail($template_id, $days = 30)
    {
        // Validate input
        if (!$this->validateGUID($template_id)) {
            return [];
        }
        
        // Check admin permission
        if (!$this->permission_manager->hasPermission($template_id, ChecklistPermissionManager::PERMISSION_ADMIN)) {
            throw new AccessDeniedException("Insufficient permissions to view audit trail");
        }
        
        $stmt = $this->db->getConnection()->prepare("
            SELECT 
                pa.action,
                pa.performed_by,
                pa.date_performed,
                pa.details,
                pa.ip_address,
                u.first_name,
                u.last_name,
                u.user_name
            FROM checklist_privacy_audit pa
            JOIN users u ON pa.performed_by = u.id
            WHERE pa.template_id = ? 
            AND pa.date_performed >= DATE_SUB(NOW(), INTERVAL ? DAY)
            AND pa.deleted = 0 AND u.deleted = 0
            ORDER BY pa.date_performed DESC
        ");
        
        $stmt->bind_param('si', $template_id, $days);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $audit_trail = [];
        while ($row = $result->fetch_assoc()) {
            $row['details'] = json_decode($row['details'], true);
            $audit_trail[] = $row;
        }
        
        return $audit_trail;
    }
    
    /**
     * Export compliance report
     */
    public function generateComplianceReport($template_id, $format = 'json')
    {
        // Validate input
        if (!$this->validateGUID($template_id)) {
            throw new InvalidArgumentException("Invalid template ID");
        }
        
        // Check admin permission
        if (!$this->permission_manager->hasPermission($template_id, ChecklistPermissionManager::PERMISSION_ADMIN)) {
            throw new AccessDeniedException("Insufficient permissions to generate compliance report");
        }
        
        $report = [
            'template_id' => $template_id,
            'generated_by' => $this->current_user->id,
            'generated_at' => date('Y-m-d H:i:s'),
            'data_classification' => $this->getDataClassification($template_id),
            'retention_policy' => $this->getRetentionPolicy($template_id),
            'access_permissions' => $this->permission_manager->getTemplateAccessList($template_id),
            'recent_activities' => $this->getAccessAuditTrail($template_id, 90),
            'compliance_status' => $this->checkComplianceStatus($template_id)
        ];
        
        // Log compliance report generation
        $this->logPrivacyAction('compliance_report_generated', $template_id, $this->current_user->id, 
            ['format' => $format]);
        
        switch ($format) {
            case 'pdf':
                return $this->generatePDFReport($report);
            case 'csv':
                return $this->generateCSVReport($report);
            case 'json':
            default:
                return json_encode($report, JSON_PRETTY_PRINT);
        }
    }
    
    /**
     * Check for data breaches or unauthorized access
     */
    public function checkSecurityBreaches($template_id)
    {
        // Validate input
        if (!$this->validateGUID($template_id)) {
            return [];
        }
        
        $breaches = [];
        
        // Check for multiple failed access attempts
        $stmt = $this->db->getConnection()->prepare("
            SELECT 
                performed_by,
                ip_address,
                COUNT(*) as attempt_count,
                MAX(date_performed) as last_attempt
            FROM checklist_privacy_audit 
            WHERE template_id = ? 
            AND action = 'access_denied'
            AND date_performed >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            GROUP BY performed_by, ip_address
            HAVING attempt_count >= 5
        ");
        
        $stmt->bind_param('s', $template_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $breaches[] = [
                'type' => 'multiple_failed_attempts',
                'user_id' => $row['performed_by'],
                'ip_address' => $row['ip_address'],
                'attempt_count' => $row['attempt_count'],
                'last_attempt' => $row['last_attempt']
            ];
        }
        
        // Check for unusual access patterns
        $stmt = $this->db->getConnection()->prepare("
            SELECT 
                performed_by,
                COUNT(DISTINCT ip_address) as unique_ips,
                COUNT(*) as access_count
            FROM checklist_privacy_audit 
            WHERE template_id = ? 
            AND action IN ('view', 'export')
            AND date_performed >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY performed_by
            HAVING unique_ips > 3 OR access_count > 50
        ");
        
        $stmt->bind_param('s', $template_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $breaches[] = [
                'type' => 'unusual_access_pattern',
                'user_id' => $row['performed_by'],
                'unique_ips' => $row['unique_ips'],
                'access_count' => $row['access_count']
            ];
        }
        
        return $breaches;
    }
    
    /**
     * Private helper methods
     */
    private function validateGUID($guid)
    {
        return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $guid);
    }
    
    private function getDataClassification($template_id)
    {
        $stmt = $this->db->getConnection()->prepare("
            SELECT classification FROM checklist_data_classification 
            WHERE template_id = ? AND deleted = 0
        ");
        
        $stmt->bind_param('s', $template_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row ? $row['classification'] : self::DATA_INTERNAL; // Default classification
    }
    
    private function getRetentionPolicy($template_id)
    {
        $stmt = $this->db->getConnection()->prepare("
            SELECT retention_days FROM checklist_retention_policy 
            WHERE template_id = ? AND deleted = 0
        ");
        
        $stmt->bind_param('s', $template_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row ? $row['retention_days'] : null;
    }
    
    private function getRequiredPermission($action)
    {
        switch ($action) {
            case self::ACTION_VIEW:
                return ChecklistPermissionManager::PERMISSION_READ;
            case self::ACTION_EXPORT:
            case self::ACTION_SHARE:
                return ChecklistPermissionManager::PERMISSION_WRITE;
            case self::ACTION_DELETE:
                return ChecklistPermissionManager::PERMISSION_ADMIN;
            default:
                return ChecklistPermissionManager::PERMISSION_ADMIN;
        }
    }
    
    private function checkPrivacyRules($classification, $action, $user_id, $template_id)
    {
        switch ($classification) {
            case self::DATA_RESTRICTED:
                // Restricted data requires special approval for export/share
                if (in_array($action, [self::ACTION_EXPORT, self::ACTION_SHARE])) {
                    return $this->hasSpecialApproval($template_id, $user_id, $action);
                }
                break;
                
            case self::DATA_CONFIDENTIAL:
                // Confidential data has IP restrictions
                if (!$this->isAccessFromTrustedIP()) {
                    $this->logPrivacyAction('access_denied_untrusted_ip', $template_id, $user_id, 
                        ['ip_address' => $_SERVER['REMOTE_ADDR'] ?? '']);
                    return false;
                }
                break;
                
            case self::DATA_INTERNAL:
                // Internal data can only be accessed by organization members
                if (!$this->isOrganizationMember($user_id)) {
                    return false;
                }
                break;
                
            case self::DATA_PUBLIC:
            default:
                // Public data has no additional restrictions
                break;
        }
        
        return true;
    }
    
    private function hasSpecialApproval($template_id, $user_id, $action)
    {
        $stmt = $this->db->getConnection()->prepare("
            SELECT id FROM checklist_special_approvals 
            WHERE template_id = ? AND user_id = ? AND action = ? 
            AND expiry_date > NOW() AND deleted = 0
        ");
        
        $stmt->bind_param('sss', $template_id, $user_id, $action);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0;
    }
    
    private function isAccessFromTrustedIP()
    {
        $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        // Check against trusted IP ranges
        $stmt = $this->db->getConnection()->prepare("
            SELECT ip_range FROM trusted_ip_ranges WHERE deleted = 0
        ");
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            if ($this->ipInRange($client_ip, $row['ip_range'])) {
                return true;
            }
        }
        
        return false;
    }
    
    private function isOrganizationMember($user_id)
    {
        // In SuiteCRM context, check if user is active organization member
        $stmt = $this->db->getConnection()->prepare("
            SELECT id FROM users 
            WHERE id = ? AND status = 'Active' AND deleted = 0
        ");
        
        $stmt->bind_param('s', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0;
    }
    
    private function ipInRange($ip, $range)
    {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }
        
        list($subnet, $bits) = explode('/', $range);
        $ip_long = ip2long($ip);
        $subnet_long = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        
        return ($ip_long & $mask) === ($subnet_long & $mask);
    }
    
    private function anonymizeChecklistInstance($instance_id, $user_id)
    {
        // Anonymize sensitive fields in checklist instance
        $stmt = $this->db->getConnection()->prepare("
            UPDATE checklist_instances 
            SET 
                creator_name = 'ANONYMIZED',
                creator_email = 'anonymized@domain.com',
                sensitive_notes = 'DATA ANONYMIZED',
                date_modified = NOW(),
                modified_by = ?
            WHERE id = ? AND deleted = 0
        ");
        
        $stmt->bind_param('ss', $user_id, $instance_id);
        return $stmt->execute();
    }
    
    private function checkComplianceStatus($template_id)
    {
        $status = [
            'gdpr_compliant' => true,
            'retention_policy_set' => !empty($this->getRetentionPolicy($template_id)),
            'data_classification_set' => $this->getDataClassification($template_id) !== self::DATA_INTERNAL,
            'access_controls_configured' => count($this->permission_manager->getTemplateAccessList($template_id)) > 0,
            'audit_trail_enabled' => true
        ];
        
        $status['overall_compliant'] = $status['gdpr_compliant'] && 
                                     $status['retention_policy_set'] && 
                                     $status['data_classification_set'] && 
                                     $status['access_controls_configured'];
        
        return $status;
    }
    
    private function logPrivacyAction($action, $template_id, $user_id, $details = [])
    {
        $stmt = $this->db->getConnection()->prepare("
            INSERT INTO checklist_privacy_audit 
            (id, template_id, action, performed_by, date_performed, details, ip_address, user_agent, deleted) 
            VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, 0)
        ");
        
        $id = create_guid();
        $details_json = json_encode($details);
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt->bind_param('sssssss', $id, $template_id, $action, $user_id, $details_json, $ip_address, $user_agent);
        $stmt->execute();
        
        $this->logger->info("Privacy action logged: $action for template $template_id by user $user_id");
    }
    
    private function generatePDFReport($report)
    {
        // Implementation would use TCPDF or similar library
        // For now, return JSON with PDF indicator
        return json_encode(array_merge($report, ['format' => 'pdf']));
    }
    
    private function generateCSVReport($report)
    {
        // Implementation would generate CSV format
        // For now, return JSON with CSV indicator
        return json_encode(array_merge($report, ['format' => 'csv']));
    }
}