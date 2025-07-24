<?php
/**
 * Security and Permission Manager for Checklist System
 * Handles ACL, data validation, and access control for checklist operations
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

class ChecklistSecurity
{
    private $current_user;
    private $log;

    public function __construct()
    {
        $this->current_user = $GLOBALS['current_user'];
        $this->log = $GLOBALS['log'];
    }

    /**
     * Check if user can access checklist operations for a deal
     * 
     * @param string $dealId The deal ID
     * @param string $operation The operation (view, edit, delete, create)
     * @return bool True if access is allowed
     */
    public function canAccessDealChecklists($dealId, $operation = 'view')
    {
        try {
            // Load the deal
            $deal = BeanFactory::getBean('Deals', $dealId);
            if (!$deal || !$deal->id) {
                $this->log->warn("ChecklistSecurity: Deal not found: {$dealId}");
                return false;
            }

            // Check basic deal access
            switch ($operation) {
                case 'view':
                    if (!$deal->ACLAccess('view')) {
                        return false;
                    }
                    break;
                case 'edit':
                case 'create':
                case 'delete':
                    if (!$deal->ACLAccess('edit')) {
                        return false;
                    }
                    break;
                default:
                    return false;
            }

            // Additional checklist-specific permissions
            return $this->checkChecklistPermissions($deal, $operation);

        } catch (Exception $e) {
            $this->log->error("ChecklistSecurity::canAccessDealChecklists - Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate checklist template data
     * 
     * @param array $templateData The template data to validate
     * @return array Validation result with errors if any
     */
    public function validateTemplateData($templateData)
    {
        $errors = array();

        // Required fields
        if (empty($templateData['name'])) {
            $errors[] = 'Template name is required';
        }

        if (empty($templateData['template_id'])) {
            $errors[] = 'Template ID is required';
        }

        // Validate template exists and is active
        if (!empty($templateData['template_id'])) {
            if (!$this->isValidTemplate($templateData['template_id'])) {
                $errors[] = 'Invalid or inactive template';
            }
        }

        // Validate due date format
        if (!empty($templateData['due_date'])) {
            if (!$this->isValidDate($templateData['due_date'])) {
                $errors[] = 'Invalid due date format';
            }
        }

        // Validate assigned user
        if (!empty($templateData['assigned_user_id'])) {
            if (!$this->isValidUser($templateData['assigned_user_id'])) {
                $errors[] = 'Invalid assigned user';
            }
        }

        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }

    /**
     * Validate checklist item data
     * 
     * @param array $itemData The item data to validate
     * @return array Validation result with errors if any
     */
    public function validateItemData($itemData)
    {
        $errors = array();

        // Required fields
        if (empty($itemData['item_id'])) {
            $errors[] = 'Item ID is required';
        }

        if (empty($itemData['completion_status'])) {
            $errors[] = 'Completion status is required';
        }

        // Validate status
        $validStatuses = array('pending', 'in_progress', 'completed', 'not_applicable', 'blocked');
        if (!empty($itemData['completion_status']) && !in_array($itemData['completion_status'], $validStatuses)) {
            $errors[] = 'Invalid completion status';
        }

        // Validate priority
        if (!empty($itemData['priority'])) {
            $validPriorities = array('high', 'medium', 'low');
            if (!in_array($itemData['priority'], $validPriorities)) {
                $errors[] = 'Invalid priority level';
            }
        }

        // Validate hours
        if (!empty($itemData['actual_hours'])) {
            if (!is_numeric($itemData['actual_hours']) || $itemData['actual_hours'] < 0) {
                $errors[] = 'Actual hours must be a positive number';
            }
        }

        // Validate notes length
        if (!empty($itemData['notes']) && strlen($itemData['notes']) > 5000) {
            $errors[] = 'Notes cannot exceed 5000 characters';
        }

        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }

    /**
     * Sanitize input data to prevent XSS and SQL injection
     * 
     * @param array $data The data to sanitize
     * @return array Sanitized data
     */
    public function sanitizeData($data)
    {
        $sanitized = array();

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                // Remove HTML tags and encode special characters
                $sanitized[$key] = htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeData($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Check rate limiting for checklist operations
     * 
     * @param string $operation The operation being performed
     * @param string $userId The user ID
     * @return bool True if within rate limits
     */
    public function checkRateLimit($operation, $userId = null)
    {
        if (!$userId) {
            $userId = $this->current_user->id;
        }

        $limits = array(
            'apply_template' => array('count' => 10, 'period' => 3600), // 10 per hour
            'update_item' => array('count' => 100, 'period' => 3600),   // 100 per hour
            'bulk_update' => array('count' => 5, 'period' => 3600),     // 5 per hour
        );

        if (!isset($limits[$operation])) {
            return true; // No limit defined
        }

        $limit = $limits[$operation];
        $cacheKey = "checklist_rate_limit_{$operation}_{$userId}";

        // Check cache for current count
        $currentCount = SugarCache::instance()->get($cacheKey, 0);

        if ($currentCount >= $limit['count']) {
            $this->log->warn("ChecklistSecurity: Rate limit exceeded for user {$userId}, operation {$operation}");
            return false;
        }

        // Increment counter
        SugarCache::instance()->set($cacheKey, $currentCount + 1, $limit['period']);

        return true;
    }

    /**
     * Log security events
     * 
     * @param string $event The event type
     * @param array $details Event details
     */
    public function logSecurityEvent($event, $details = array())
    {
        $logData = array(
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $this->current_user->id,
            'user_name' => $this->current_user->user_name,
            'event' => $event,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'details' => $details
        );

        $this->log->security("ChecklistSecurity: " . json_encode($logData));

        // Store in database for audit trail if needed
        $this->storeSecurityEvent($logData);
    }

    /**
     * Private helper methods
     */

    private function checkChecklistPermissions($deal, $operation)
    {
        // Admin users have full access
        if ($this->current_user->is_admin) {
            return true;
        }

        // Deal owner has full access
        if ($deal->assigned_user_id === $this->current_user->id) {
            return true;
        }

        // Check team-based permissions
        if ($this->isTeamMember($deal)) {
            // Team members can view and edit but not delete
            return in_array($operation, array('view', 'edit', 'create'));
        }

        // For view operations, check if user has module access
        if ($operation === 'view') {
            return ACLController::checkAccess('Deals', 'view', $this->current_user->is_owner($deal));
        }

        return false;
    }

    private function isValidTemplate($templateId)
    {
        $sql = "SELECT id FROM checklist_templates WHERE id = '" . $GLOBALS['db']->quote($templateId) . 
               "' AND deleted = 0 AND is_active = 1";
        $result = $GLOBALS['db']->query($sql);
        return $GLOBALS['db']->getRowCount($result) > 0;
    }

    private function isValidDate($dateString)
    {
        return (bool)strtotime($dateString);
    }

    private function isValidUser($userId)
    {
        $user = BeanFactory::getBean('Users', $userId);
        return $user && $user->id && $user->status === 'Active';
    }

    private function isTeamMember($deal)
    {
        // Check if current user is in any of the deal's teams
        if (class_exists('TeamSetManager')) {
            $teamSet = new TeamSet();
            $teams = $teamSet->getTeams($deal->team_set_id);
            
            foreach ($teams as $team) {
                if ($team->is_user_on_team($team->id, $this->current_user->id)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function storeSecurityEvent($logData)
    {
        try {
            // Create a simple audit table entry
            $sql = "INSERT INTO checklist_security_audit 
                    (id, user_id, event_type, event_details, ip_address, date_created) 
                    VALUES ('" . create_guid() . "', '" . $logData['user_id'] . "', '" . $logData['event'] . "', 
                    '" . $GLOBALS['db']->quote(json_encode($logData)) . "', '" . $logData['ip_address'] . "', 
                    '" . $logData['timestamp'] . "')";

            $GLOBALS['db']->query($sql);

        } catch (Exception $e) {
            // Don't fail the main operation if audit logging fails
            $this->log->error("ChecklistSecurity: Failed to store security event - " . $e->getMessage());
        }
    }
}