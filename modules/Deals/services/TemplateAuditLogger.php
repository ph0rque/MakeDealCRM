<?php
/**
 * Template Audit Logger
 * Handles comprehensive audit logging for template versioning actions
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

class TemplateAuditLogger
{
    private $db;
    private $currentUser;
    
    public function __construct()
    {
        global $db, $current_user;
        $this->db = $db;
        $this->currentUser = $current_user;
    }
    
    /**
     * Log an action in the audit trail
     */
    public function logAction($templateId, $versionId, $actionType, $actorId, $metadata = [], $changeDescription = '')
    {
        try {
            $auditId = create_guid();
            $sessionId = session_id();
            $ipAddress = $this->getClientIpAddress();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            $query = "INSERT INTO template_audit_log (
                id, template_id, version_id, action_type, actor_id, actor_type,
                change_description, metadata, ip_address, user_agent, session_id, action_date
            ) VALUES (
                '{$auditId}', '{$templateId}', " . ($versionId ? "'{$versionId}'" : 'NULL') . ",
                '{$actionType}', '{$actorId}', 'user',
                '" . $this->db->quote($changeDescription) . "',
                '" . $this->db->quote(json_encode($metadata)) . "',
                '{$ipAddress}', '" . $this->db->quote($userAgent) . "',
                '{$sessionId}', NOW()
            )";
            
            $this->db->query($query);
            
            return $auditId;
            
        } catch (Exception $e) {
            // Log error but don't throw to avoid breaking main operations
            error_log("Audit logging failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log system actions (automated processes)
     */
    public function logSystemAction($templateId, $versionId, $actionType, $metadata = [], $changeDescription = '')
    {
        try {
            $auditId = create_guid();
            
            $query = "INSERT INTO template_audit_log (
                id, template_id, version_id, action_type, actor_id, actor_type,
                change_description, metadata, action_date
            ) VALUES (
                '{$auditId}', '{$templateId}', " . ($versionId ? "'{$versionId}'" : 'NULL') . ",
                '{$actionType}', 'system', 'system',
                '" . $this->db->quote($changeDescription) . "',
                '" . $this->db->quote(json_encode($metadata)) . "',
                NOW()
            )";
            
            $this->db->query($query);
            
            return $auditId;
            
        } catch (Exception $e) {
            error_log("System audit logging failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log errors and failures
     */
    public function logError($templateId, $actionType, $errorMessage, $metadata = [])
    {
        $errorMetadata = array_merge($metadata, [
            'error' => $errorMessage,
            'stack_trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
        ]);
        
        return $this->logSystemAction(
            $templateId, 
            null, 
            'error', 
            $errorMetadata, 
            "Error during {$actionType}: {$errorMessage}"
        );
    }
    
    /**
     * Log version comparison actions
     */
    public function logComparison($templateId, $fromVersionId, $toVersionId, $comparisonType, $results)
    {
        $metadata = [
            'from_version_id' => $fromVersionId,
            'to_version_id' => $toVersionId,
            'comparison_type' => $comparisonType,
            'change_count' => $results['change_count'] ?? 0,
            'complexity_score' => $results['complexity_score'] ?? 0
        ];
        
        return $this->logAction(
            $templateId,
            null,
            'compare',
            $this->currentUser->id,
            $metadata,
            "Version comparison performed between versions"
        );
    }
    
    /**
     * Log migration activities
     */
    public function logMigration($templateId, $fromVersionId, $toVersionId, $migrationType, $status, $metadata = [])
    {
        $migrationMetadata = array_merge($metadata, [
            'from_version_id' => $fromVersionId,
            'to_version_id' => $toVersionId,
            'migration_type' => $migrationType,
            'migration_status' => $status
        ]);
        
        return $this->logSystemAction(
            $templateId,
            $toVersionId,
            'migrate',
            $migrationMetadata,
            "Template migration {$status}: {$migrationType} migration from version to version"
        );
    }
    
    /**
     * Get audit trail with filtering and pagination
     */
    public function getAuditTrail($templateId, $versionId = null, $limit = 100, $offset = 0, $filters = [])
    {
        $whereConditions = ["template_id = '{$templateId}'"];
        
        if ($versionId) {
            $whereConditions[] = "version_id = '{$versionId}'";
        }
        
        // Add filter conditions
        if (!empty($filters['action_type'])) {
            $whereConditions[] = "action_type = '" . $this->db->quote($filters['action_type']) . "'";
        }
        
        if (!empty($filters['actor_id'])) {
            $whereConditions[] = "actor_id = '" . $this->db->quote($filters['actor_id']) . "'";
        }
        
        if (!empty($filters['from_date'])) {
            $whereConditions[] = "action_date >= '" . $this->db->quote($filters['from_date']) . "'";
        }
        
        if (!empty($filters['to_date'])) {
            $whereConditions[] = "action_date <= '" . $this->db->quote($filters['to_date']) . "'";
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $query = "SELECT 
                    tal.*,
                    u.user_name as actor_name,
                    u.first_name,
                    u.last_name,
                    tv.version_number
                  FROM template_audit_log tal
                  LEFT JOIN users u ON tal.actor_id = u.id
                  LEFT JOIN template_versions tv ON tal.version_id = tv.id
                  WHERE {$whereClause}
                  ORDER BY tal.action_date DESC
                  LIMIT {$offset}, {$limit}";
        
        $result = $this->db->query($query);
        $auditTrail = [];
        
        while ($row = $this->db->fetchByAssoc($result)) {
            // Decode metadata
            $row['metadata'] = json_decode($row['metadata'], true) ?? [];
            
            // Format actor name
            if ($row['actor_type'] === 'user' && $row['first_name'] && $row['last_name']) {
                $row['actor_display_name'] = trim($row['first_name'] . ' ' . $row['last_name']);
            } else {
                $row['actor_display_name'] = $row['actor_name'] ?? $row['actor_type'];
            }
            
            $auditTrail[] = $row;
        }
        
        return $auditTrail;
    }
    
    /**
     * Get audit statistics
     */
    public function getAuditStatistics($templateId, $period = '30 days')
    {
        $query = "SELECT 
                    action_type,
                    COUNT(*) as action_count,
                    COUNT(DISTINCT actor_id) as unique_actors,
                    MIN(action_date) as first_action,
                    MAX(action_date) as last_action
                  FROM template_audit_log 
                  WHERE template_id = '{$templateId}' 
                  AND action_date >= DATE_SUB(NOW(), INTERVAL {$period})
                  GROUP BY action_type
                  ORDER BY action_count DESC";
        
        $result = $this->db->query($query);
        $statistics = [];
        
        while ($row = $this->db->fetchByAssoc($result)) {
            $statistics[$row['action_type']] = [
                'count' => (int)$row['action_count'],
                'unique_actors' => (int)$row['unique_actors'],
                'first_action' => $row['first_action'],
                'last_action' => $row['last_action']
            ];
        }
        
        return $statistics;
    }
    
    /**
     * Get user activity summary
     */
    public function getUserActivity($templateId, $userId, $limit = 50)
    {
        $query = "SELECT 
                    action_type,
                    version_id,
                    change_description,
                    action_date,
                    tv.version_number
                  FROM template_audit_log tal
                  LEFT JOIN template_versions tv ON tal.version_id = tv.id
                  WHERE tal.template_id = '{$templateId}' 
                  AND tal.actor_id = '{$userId}'
                  ORDER BY tal.action_date DESC
                  LIMIT {$limit}";
        
        $result = $this->db->query($query);
        $activities = [];
        
        while ($row = $this->db->fetchByAssoc($result)) {
            $activities[] = $row;
        }
        
        return $activities;
    }
    
    /**
     * Export audit trail to various formats
     */
    public function exportAuditTrail($templateId, $format = 'json', $filters = [])
    {
        $auditData = $this->getAuditTrail($templateId, null, 10000, 0, $filters);
        
        switch ($format) {
            case 'csv':
                return $this->exportToCsv($auditData);
            case 'xml':
                return $this->exportToXml($auditData);
            case 'json':
            default:
                return json_encode($auditData, JSON_PRETTY_PRINT);
        }
    }
    
    /**
     * Private helper methods
     */
    
    private function getClientIpAddress()
    {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    private function exportToCsv($data)
    {
        if (empty($data)) return '';
        
        $output = fopen('php://temp', 'r+');
        
        // Write header
        fputcsv($output, array_keys($data[0]));
        
        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
    
    private function exportToXml($data)
    {
        $xml = new SimpleXMLElement('<audit_trail/>');
        
        foreach ($data as $entry) {
            $xmlEntry = $xml->addChild('entry');
            foreach ($entry as $key => $value) {
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                $xmlEntry->addChild($key, htmlspecialchars($value));
            }
        }
        
        return $xml->asXML();
    }
}