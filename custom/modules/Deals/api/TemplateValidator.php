<?php
/**
 * Template Validation Helper
 * 
 * Provides comprehensive validation for checklist template data,
 * including structure validation, business rules, and data integrity checks.
 * 
 * @category  API
 * @package   Deals
 * @author    MakeDealCRM
 * @license   GPL-3.0+
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

class TemplateValidator
{
    /**
     * Validate template data structure
     * 
     * @param array $data Template data to validate
     * @param string $operation Operation type: create, update, validate
     * @return array Array of validation errors (empty if valid)
     */
    public static function validateTemplateData($data, $operation = 'create')
    {
        $errors = array();
        
        // Required fields validation
        if ($operation === 'create') {
            if (empty($data['name'])) {
                $errors[] = 'Template name is required';
            }
        }
        
        // Field length validation
        if (!empty($data['name'])) {
            if (strlen($data['name']) < 3) {
                $errors[] = 'Template name must be at least 3 characters long';
            }
            if (strlen($data['name']) > 255) {
                $errors[] = 'Template name cannot exceed 255 characters';
            }
            if (!self::isValidTemplateName($data['name'])) {
                $errors[] = 'Template name contains invalid characters';
            }
        }
        
        if (!empty($data['description']) && strlen($data['description']) > 2000) {
            $errors[] = 'Template description cannot exceed 2000 characters';
        }
        
        // Category validation
        if (!empty($data['category'])) {
            if (strlen($data['category']) > 100) {
                $errors[] = 'Category name cannot exceed 100 characters';
            }
            if (!self::isValidCategory($data['category'])) {
                $errors[] = 'Invalid category specified';
            }
        }
        
        // Boolean field validation
        if (isset($data['is_public'])) {
            if (!self::isValidBoolean($data['is_public'])) {
                $errors[] = 'Invalid value for is_public field';
            }
        }
        
        if (isset($data['is_active'])) {
            if (!self::isValidBoolean($data['is_active'])) {
                $errors[] = 'Invalid value for is_active field';
            }
        }
        
        // Template data structure validation
        if (!empty($data['template_data'])) {
            $templateDataErrors = self::validateTemplateDataStructure($data['template_data']);
            $errors = array_merge($errors, $templateDataErrors);
        }
        
        // Items validation
        if (!empty($data['items'])) {
            $itemErrors = self::validateTemplateItems($data['items']);
            $errors = array_merge($errors, $itemErrors);
        }
        
        return $errors;
    }
    
    /**
     * Validate template items structure
     * 
     * @param array $items Array of template items
     * @return array Array of validation errors
     */
    public static function validateTemplateItems($items)
    {
        $errors = array();
        
        if (!is_array($items)) {
            $errors[] = 'Template items must be an array';
            return $errors;
        }
        
        if (count($items) > 500) {
            $errors[] = 'Template cannot have more than 500 items';
        }
        
        foreach ($items as $index => $item) {
            $itemErrors = self::validateTemplateItem($item, $index);
            $errors = array_merge($errors, $itemErrors);
        }
        
        return $errors;
    }
    
    /**
     * Validate individual template item
     * 
     * @param array $item Template item data
     * @param int $index Item index for error reporting
     * @return array Array of validation errors
     */
    public static function validateTemplateItem($item, $index)
    {
        $errors = array();
        $prefix = "Item $index: ";
        
        if (!is_array($item)) {
            $errors[] = $prefix . 'Item must be an object';
            return $errors;
        }
        
        // Required fields
        if (empty($item['title'])) {
            $errors[] = $prefix . 'Title is required';
        }
        
        // Field length validation
        if (!empty($item['title']) && strlen($item['title']) > 500) {
            $errors[] = $prefix . 'Title cannot exceed 500 characters';
        }
        
        if (!empty($item['description']) && strlen($item['description']) > 2000) {
            $errors[] = $prefix . 'Description cannot exceed 2000 characters';
        }
        
        // Type validation
        if (!empty($item['type'])) {
            $validTypes = array('checkbox', 'text', 'number', 'date', 'file', 'select', 'textarea');
            if (!in_array($item['type'], $validTypes)) {
                $errors[] = $prefix . 'Invalid item type. Must be one of: ' . implode(', ', $validTypes);
            }
        }
        
        // Order validation
        if (isset($item['order']) && (!is_numeric($item['order']) || $item['order'] < 0)) {
            $errors[] = $prefix . 'Order must be a non-negative number';
        }
        
        // Required field validation
        if (isset($item['is_required']) && !self::isValidBoolean($item['is_required'])) {
            $errors[] = $prefix . 'Invalid value for is_required field';
        }
        
        // Options validation for select items
        if (!empty($item['type']) && $item['type'] === 'select') {
            if (empty($item['options']) || !is_array($item['options'])) {
                $errors[] = $prefix . 'Select items must have options array';
            } else {
                foreach ($item['options'] as $optIndex => $option) {
                    if (empty($option['label'])) {
                        $errors[] = $prefix . "Option $optIndex must have a label";
                    }
                    if (!isset($option['value'])) {
                        $errors[] = $prefix . "Option $optIndex must have a value";
                    }
                }
            }
        }
        
        // Dependencies validation
        if (!empty($item['dependencies'])) {
            if (!is_array($item['dependencies'])) {
                $errors[] = $prefix . 'Dependencies must be an array';
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate template data structure (JSON schema-like validation)
     * 
     * @param array $templateData Template configuration data
     * @return array Array of validation errors
     */
    public static function validateTemplateDataStructure($templateData)
    {
        $errors = array();
        
        if (!is_array($templateData)) {
            $errors[] = 'Template data must be an object';
            return $errors;
        }
        
        // Validate workflow settings if present
        if (!empty($templateData['workflow'])) {
            $workflowErrors = self::validateWorkflowSettings($templateData['workflow']);
            $errors = array_merge($errors, $workflowErrors);
        }
        
        // Validate completion settings
        if (!empty($templateData['completion'])) {
            $completionErrors = self::validateCompletionSettings($templateData['completion']);
            $errors = array_merge($errors, $completionErrors);
        }
        
        // Validate notification settings
        if (!empty($templateData['notifications'])) {
            $notificationErrors = self::validateNotificationSettings($templateData['notifications']);
            $errors = array_merge($errors, $notificationErrors);
        }
        
        return $errors;
    }
    
    /**
     * Validate workflow settings
     * 
     * @param array $workflow Workflow configuration
     * @return array Array of validation errors
     */
    private static function validateWorkflowSettings($workflow)
    {
        $errors = array();
        
        if (!is_array($workflow)) {
            $errors[] = 'Workflow settings must be an object';
            return $errors;
        }
        
        // Validate auto-progression settings
        if (isset($workflow['auto_progress'])) {
            if (!self::isValidBoolean($workflow['auto_progress'])) {
                $errors[] = 'Invalid auto_progress setting';
            }
        }
        
        // Validate approval requirements
        if (!empty($workflow['requires_approval'])) {
            if (!self::isValidBoolean($workflow['requires_approval'])) {
                $errors[] = 'Invalid requires_approval setting';
            }
        }
        
        // Validate assignee settings
        if (!empty($workflow['default_assignee'])) {
            if (!self::isValidUserId($workflow['default_assignee'])) {
                $errors[] = 'Invalid default_assignee user ID';
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate completion settings
     * 
     * @param array $completion Completion configuration
     * @return array Array of validation errors
     */
    private static function validateCompletionSettings($completion)
    {
        $errors = array();
        
        if (!is_array($completion)) {
            $errors[] = 'Completion settings must be an object';
            return $errors;
        }
        
        // Validate completion percentage requirements
        if (isset($completion['required_percentage'])) {
            $percentage = $completion['required_percentage'];
            if (!is_numeric($percentage) || $percentage < 0 || $percentage > 100) {
                $errors[] = 'Required percentage must be between 0 and 100';
            }
        }
        
        // Validate completion actions
        if (!empty($completion['on_complete_actions'])) {
            if (!is_array($completion['on_complete_actions'])) {
                $errors[] = 'Completion actions must be an array';
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate notification settings
     * 
     * @param array $notifications Notification configuration
     * @return array Array of validation errors
     */
    private static function validateNotificationSettings($notifications)
    {
        $errors = array();
        
        if (!is_array($notifications)) {
            $errors[] = 'Notification settings must be an object';
            return $errors;
        }
        
        // Validate notification types
        $validTypes = array('email', 'system', 'webhook');
        
        foreach ($notifications as $type => $settings) {
            if (!in_array($type, $validTypes)) {
                $errors[] = "Invalid notification type: $type";
                continue;
            }
            
            if (!is_array($settings)) {
                $errors[] = "Notification settings for $type must be an object";
                continue;
            }
            
            // Validate enabled flag
            if (isset($settings['enabled']) && !self::isValidBoolean($settings['enabled'])) {
                $errors[] = "Invalid enabled setting for $type notifications";
            }
            
            // Validate email-specific settings
            if ($type === 'email' && !empty($settings['enabled'])) {
                if (!empty($settings['template']) && strlen($settings['template']) > 100) {
                    $errors[] = 'Email template name too long';
                }
                
                if (!empty($settings['recipients']) && !is_array($settings['recipients'])) {
                    $errors[] = 'Email recipients must be an array';
                }
            }
            
            // Validate webhook-specific settings
            if ($type === 'webhook' && !empty($settings['enabled'])) {
                if (empty($settings['url'])) {
                    $errors[] = 'Webhook URL is required when webhook notifications are enabled';
                } elseif (!filter_var($settings['url'], FILTER_VALIDATE_URL)) {
                    $errors[] = 'Invalid webhook URL format';
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate template sharing permissions
     * 
     * @param array $shares Array of share configurations
     * @return array Array of validation errors
     */
    public static function validateTemplateShares($shares)
    {
        $errors = array();
        
        if (!is_array($shares)) {
            $errors[] = 'Shares must be an array';
            return $errors;
        }
        
        $validPermissions = array('view', 'edit', 'delete');
        $seenUsers = array();
        
        foreach ($shares as $index => $share) {
            if (!is_array($share)) {
                $errors[] = "Share $index must be an object";
                continue;
            }
            
            // Required fields
            if (empty($share['user_id'])) {
                $errors[] = "Share $index: user_id is required";
            } else {
                // Check for duplicate users
                if (in_array($share['user_id'], $seenUsers)) {
                    $errors[] = "Share $index: duplicate user_id {$share['user_id']}";
                } else {
                    $seenUsers[] = $share['user_id'];
                }
                
                // Validate user ID
                if (!self::isValidUserId($share['user_id'])) {
                    $errors[] = "Share $index: invalid user_id format";
                }
            }
            
            if (empty($share['permission'])) {
                $errors[] = "Share $index: permission is required";
            } elseif (!in_array($share['permission'], $validPermissions)) {
                $errors[] = "Share $index: invalid permission. Must be one of: " . implode(', ', $validPermissions);
            }
        }
        
        return $errors;
    }
    
    /**
     * Check if template name is valid
     * 
     * @param string $name Template name
     * @return bool
     */
    private static function isValidTemplateName($name)
    {
        // Allow letters, numbers, spaces, hyphens, underscores, and basic punctuation
        return preg_match('/^[a-zA-Z0-9\s\-_.,()]+$/', $name);
    }
    
    /**
     * Check if category is valid
     * 
     * @param string $category Category name
     * @return bool
     */
    private static function isValidCategory($category)
    {
        $validCategories = array(
            'general',
            'due_diligence',
            'compliance',
            'onboarding',
            'quality_assurance',
            'legal',
            'financial',
            'technical',
            'marketing',
            'sales'
        );
        
        return in_array($category, $validCategories);
    }
    
    /**
     * Check if value is a valid boolean
     * 
     * @param mixed $value Value to check
     * @return bool
     */
    private static function isValidBoolean($value)
    {
        return is_bool($value) || in_array($value, array(0, 1, '0', '1', 'true', 'false'), true);
    }
    
    /**
     * Check if user ID is valid (basic format check)
     * 
     * @param string $userId User ID to validate
     * @return bool
     */
    private static function isValidUserId($userId)
    {
        // Check if it's a valid GUID format (36 characters with hyphens)
        return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $userId);
    }
    
    /**
     * Sanitize template data for safe storage
     * 
     * @param array $data Template data to sanitize
     * @return array Sanitized data
     */
    public static function sanitizeTemplateData($data)
    {
        $sanitized = array();
        
        // Sanitize string fields
        $stringFields = array('name', 'description', 'category');
        foreach ($stringFields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = self::sanitizeString($data[$field]);
            }
        }
        
        // Sanitize boolean fields
        $booleanFields = array('is_public', 'is_active');
        foreach ($booleanFields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = self::sanitizeBoolean($data[$field]);
            }
        }
        
        // Sanitize template data (complex structure)
        if (isset($data['template_data'])) {
            $sanitized['template_data'] = self::sanitizeComplexData($data['template_data']);
        }
        
        // Sanitize items array
        if (isset($data['items'])) {
            $sanitized['items'] = self::sanitizeTemplateItems($data['items']);
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize string field
     * 
     * @param string $value String to sanitize
     * @return string Sanitized string
     */
    private static function sanitizeString($value)
    {
        // Remove potential XSS vectors while preserving necessary characters
        $value = trim($value);
        $value = strip_tags($value, '<b><i><em><strong><br><p>'); // Allow basic formatting
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        return $value;
    }
    
    /**
     * Sanitize boolean field
     * 
     * @param mixed $value Value to convert to boolean
     * @return bool Sanitized boolean
     */
    private static function sanitizeBoolean($value)
    {
        if (is_bool($value)) {
            return $value;
        }
        
        return in_array($value, array(1, '1', 'true', 'yes', 'on'), true);
    }
    
    /**
     * Sanitize complex data structures
     * 
     * @param mixed $data Data to sanitize
     * @return mixed Sanitized data
     */
    private static function sanitizeComplexData($data)
    {
        if (is_array($data)) {
            $sanitized = array();
            foreach ($data as $key => $value) {
                $sanitizedKey = self::sanitizeString($key);
                $sanitized[$sanitizedKey] = self::sanitizeComplexData($value);
            }
            return $sanitized;
        } elseif (is_string($data)) {
            return self::sanitizeString($data);
        } elseif (is_bool($data) || is_numeric($data)) {
            return $data;
        } else {
            return null; // Remove unsupported data types
        }
    }
    
    /**
     * Sanitize template items array
     * 
     * @param array $items Items to sanitize
     * @return array Sanitized items
     */
    private static function sanitizeTemplateItems($items)
    {
        if (!is_array($items)) {
            return array();
        }
        
        $sanitized = array();
        foreach ($items as $item) {
            if (is_array($item)) {
                $sanitizedItem = array();
                
                // Sanitize known fields
                $stringFields = array('title', 'description', 'type');
                foreach ($stringFields as $field) {
                    if (isset($item[$field])) {
                        $sanitizedItem[$field] = self::sanitizeString($item[$field]);
                    }
                }
                
                // Sanitize numeric fields
                if (isset($item['order'])) {
                    $sanitizedItem['order'] = (int)$item['order'];
                }
                
                // Sanitize boolean fields
                if (isset($item['is_required'])) {
                    $sanitizedItem['is_required'] = self::sanitizeBoolean($item['is_required']);
                }
                
                // Sanitize options array for select items
                if (isset($item['options'])) {
                    $sanitizedItem['options'] = self::sanitizeComplexData($item['options']);
                }
                
                // Sanitize dependencies
                if (isset($item['dependencies'])) {
                    $sanitizedItem['dependencies'] = self::sanitizeComplexData($item['dependencies']);
                }
                
                $sanitized[] = $sanitizedItem;
            }
        }
        
        return $sanitized;
    }
}