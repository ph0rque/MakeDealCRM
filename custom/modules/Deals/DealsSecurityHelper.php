<?php
/**
 * Security Helper for Deals Module
 * Provides centralized security functions for input sanitization, CSRF protection, and ACL checks
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('include/SugarCleaner.php');

class DealsSecurityHelper
{
    /**
     * Sanitize input data using SugarCleaner
     * 
     * @param mixed $data Data to sanitize
     * @param string $type Type of sanitization (default, sql, html)
     * @return mixed Sanitized data
     */
    public static function sanitizeInput($data, $type = 'default')
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = self::sanitizeInput($value, $type);
            }
            return $data;
        }
        
        switch ($type) {
            case 'sql':
                return SugarCleaner::cleanSql($data);
            case 'html':
                return SugarCleaner::cleanHtml($data);
            case 'script':
                return SugarCleaner::stripTags($data, 0);
            default:
                return SugarCleaner::cleanQuery($data);
        }
    }
    
    /**
     * Generate CSRF token for forms
     * 
     * @return string CSRF token
     */
    public static function generateCSRFToken()
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token
     * 
     * @param string $token Token to validate
     * @return bool True if valid
     */
    public static function validateCSRFToken($token)
    {
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Check module access with proper ACL
     * 
     * @param string $module Module name
     * @param string $action Action to check
     * @param bool $owner Check owner permissions
     * @return bool True if access allowed
     */
    public static function checkModuleAccess($module, $action, $owner = false)
    {
        global $current_user;
        
        if (empty($current_user->id)) {
            return false;
        }
        
        require_once('modules/ACL/ACLController.php');
        return ACLController::checkAccess($module, $action, $owner);
    }
    
    /**
     * Check record access with proper ACL
     * 
     * @param SugarBean $bean Bean to check
     * @param string $action Action to check
     * @return bool True if access allowed
     */
    public static function checkRecordAccess($bean, $action)
    {
        if (empty($bean) || empty($bean->id)) {
            return false;
        }
        
        return $bean->ACLAccess($action);
    }
    
    /**
     * Prepare SQL with proper parameterization
     * 
     * @param string $query SQL query with placeholders
     * @param array $params Parameters to bind
     * @return string Prepared query
     */
    public static function prepareSQLQuery($query, $params = array())
    {
        global $db;
        
        foreach ($params as $key => $value) {
            if (is_string($value)) {
                $value = $db->quote($value);
            } elseif (is_null($value)) {
                $value = 'NULL';
            } elseif (is_bool($value)) {
                $value = $value ? 1 : 0;
            } elseif (!is_numeric($value)) {
                $value = $db->quote(strval($value));
            }
            
            $query = str_replace(':' . $key, $value, $query);
        }
        
        return $query;
    }
    
    /**
     * Encode output for XSS prevention
     * 
     * @param string $data Data to encode
     * @param string $context Context (html, attribute, js)
     * @return string Encoded data
     */
    public static function encodeOutput($data, $context = 'html')
    {
        if (empty($data)) {
            return '';
        }
        
        switch ($context) {
            case 'html':
                return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            case 'attribute':
                return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            case 'js':
                return json_encode($data);
            case 'url':
                return urlencode($data);
            default:
                return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }
    
    /**
     * Validate and sanitize file uploads
     * 
     * @param array $file $_FILES array element
     * @param array $allowedTypes Allowed MIME types
     * @param int $maxSize Maximum file size in bytes
     * @return array Validation result
     */
    public static function validateFileUpload($file, $allowedTypes = array(), $maxSize = 5242880)
    {
        $result = array(
            'valid' => false,
            'error' => '',
            'sanitized_name' => ''
        );
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $result['error'] = 'Upload failed with error code: ' . $file['error'];
            return $result;
        }
        
        // Check file size
        if ($file['size'] > $maxSize) {
            $result['error'] = 'File size exceeds maximum allowed size';
            return $result;
        }
        
        // Check MIME type if specified
        if (!empty($allowedTypes)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedTypes)) {
                $result['error'] = 'File type not allowed';
                return $result;
            }
        }
        
        // Sanitize filename
        $result['sanitized_name'] = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
        $result['valid'] = true;
        
        return $result;
    }
    
    /**
     * Log security events
     * 
     * @param string $type Event type
     * @param string $message Event message
     * @param array $data Additional data
     */
    public static function logSecurityEvent($type, $message, $data = array())
    {
        global $current_user, $db;
        
        $logData = array(
            'type' => $type,
            'message' => $message,
            'user_id' => $current_user->id ?? 'anonymous',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => $data
        );
        
        // Log to SuiteCRM log
        $GLOBALS['log']->security("DEALS_SECURITY: " . json_encode($logData));
        
        // Optionally log to custom security table
        self::logToSecurityTable($logData);
    }
    
    /**
     * Log to custom security table
     * 
     * @param array $logData Log data
     */
    private static function logToSecurityTable($logData)
    {
        global $db;
        
        // Create table if not exists
        $createTable = "CREATE TABLE IF NOT EXISTS deals_security_log (
            id CHAR(36) NOT NULL PRIMARY KEY,
            event_type VARCHAR(50) NOT NULL,
            message TEXT,
            user_id CHAR(36),
            ip_address VARCHAR(45),
            event_data LONGTEXT,
            created_date DATETIME NOT NULL,
            KEY idx_event_type (event_type),
            KEY idx_user_id (user_id),
            KEY idx_created_date (created_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->query($createTable);
        
        // Insert log entry
        $id = create_guid();
        $query = self::prepareSQLQuery(
            "INSERT INTO deals_security_log 
             (id, event_type, message, user_id, ip_address, event_data, created_date) 
             VALUES 
             (:id, :type, :message, :user_id, :ip, :data, :created)",
            array(
                'id' => $id,
                'type' => $logData['type'],
                'message' => $logData['message'],
                'user_id' => $logData['user_id'],
                'ip' => $logData['ip_address'],
                'data' => json_encode($logData['data']),
                'created' => $logData['timestamp']
            )
        );
        
        $db->query($query);
    }
    
    /**
     * Rate limiting check
     * 
     * @param string $action Action to check
     * @param string $identifier User or IP identifier
     * @param int $maxAttempts Maximum attempts allowed
     * @param int $timeWindow Time window in seconds
     * @return bool True if within rate limit
     */
    public static function checkRateLimit($action, $identifier, $maxAttempts = 60, $timeWindow = 60)
    {
        $cacheKey = 'rate_limit_' . md5($action . '_' . $identifier);
        $attempts = sugar_cache_retrieve($cacheKey);
        
        if ($attempts === null) {
            $attempts = array();
        }
        
        // Remove old attempts outside time window
        $cutoff = time() - $timeWindow;
        $attempts = array_filter($attempts, function($timestamp) use ($cutoff) {
            return $timestamp > $cutoff;
        });
        
        // Check if limit exceeded
        if (count($attempts) >= $maxAttempts) {
            self::logSecurityEvent('rate_limit_exceeded', "Rate limit exceeded for action: $action", array(
                'identifier' => $identifier,
                'attempts' => count($attempts)
            ));
            return false;
        }
        
        // Add current attempt
        $attempts[] = time();
        sugar_cache_put($cacheKey, $attempts, $timeWindow);
        
        return true;
    }
}