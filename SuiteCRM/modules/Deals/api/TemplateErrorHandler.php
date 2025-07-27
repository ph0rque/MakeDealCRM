<?php
/**
 * Template Error Handler
 * 
 * Provides centralized error handling and formatting for template API endpoints.
 * Ensures consistent error responses and proper HTTP status codes.
 * 
 * @category  API
 * @package   Deals
 * @author    MakeDealCRM
 * @license   GPL-3.0+
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

class TemplateErrorHandler
{
    // Error codes for different types of template errors
    const ERROR_VALIDATION = 'TEMPLATE_VALIDATION_ERROR';
    const ERROR_NOT_FOUND = 'TEMPLATE_NOT_FOUND';
    const ERROR_ACCESS_DENIED = 'TEMPLATE_ACCESS_DENIED';
    const ERROR_DUPLICATE_NAME = 'TEMPLATE_DUPLICATE_NAME';
    const ERROR_IN_USE = 'TEMPLATE_IN_USE';
    const ERROR_DATABASE = 'TEMPLATE_DATABASE_ERROR';
    const ERROR_INTERNAL = 'TEMPLATE_INTERNAL_ERROR';
    const ERROR_RATE_LIMIT = 'TEMPLATE_RATE_LIMIT';
    const ERROR_QUOTA_EXCEEDED = 'TEMPLATE_QUOTA_EXCEEDED';
    
    /**
     * Handle API exceptions and return formatted error response
     * 
     * @param Exception $exception The exception to handle
     * @return array Formatted error response
     */
    public static function handleException($exception)
    {
        $errorCode = self::ERROR_INTERNAL;
        $httpStatus = 500;
        $message = 'An internal error occurred';
        $details = array();
        
        // Map exception types to appropriate error codes and HTTP status
        if ($exception instanceof SugarApiExceptionNotFound) {
            $errorCode = self::ERROR_NOT_FOUND;
            $httpStatus = 404;
            $message = $exception->getMessage() ?: 'Template not found';
            
        } elseif ($exception instanceof SugarApiExceptionNotAuthorized) {
            $errorCode = self::ERROR_ACCESS_DENIED;
            $httpStatus = 403;
            $message = $exception->getMessage() ?: 'Access denied';
            
        } elseif ($exception instanceof SugarApiExceptionMissingParameter || 
                  $exception instanceof SugarApiExceptionRequestMethodFailure) {
            $errorCode = self::ERROR_VALIDATION;
            $httpStatus = 400;
            $message = $exception->getMessage() ?: 'Validation error';
            
        } elseif ($exception instanceof SugarApiExceptionNoMethod) {
            $errorCode = self::ERROR_VALIDATION;
            $httpStatus = 405;
            $message = 'Method not allowed';
            
        } elseif ($exception instanceof SugarApiExceptionIncorrectVersion) {
            $errorCode = self::ERROR_VALIDATION;
            $httpStatus = 400;
            $message = 'Incorrect API version';
            
        } else {
            // Log unexpected exceptions for debugging
            self::logError($exception);
        }
        
        return self::formatErrorResponse($errorCode, $message, $httpStatus, $details);
    }
    
    /**
     * Handle validation errors
     * 
     * @param array $validationErrors Array of validation error messages
     * @return array Formatted error response
     */
    public static function handleValidationErrors($validationErrors)
    {
        return self::formatErrorResponse(
            self::ERROR_VALIDATION,
            'Template validation failed',
            400,
            array(
                'validation_errors' => $validationErrors,
                'error_count' => count($validationErrors)
            )
        );
    }
    
    /**
     * Handle duplicate template name error
     * 
     * @param string $templateName The duplicate template name
     * @return array Formatted error response
     */
    public static function handleDuplicateNameError($templateName)
    {
        return self::formatErrorResponse(
            self::ERROR_DUPLICATE_NAME,
            'Template name already exists',
            409,
            array(
                'template_name' => $templateName,
                'suggestion' => 'Please choose a different name for your template'
            )
        );
    }
    
    /**
     * Handle template in use error (when trying to delete)
     * 
     * @param int $usageCount Number of active uses
     * @return array Formatted error response
     */
    public static function handleTemplateInUseError($usageCount)
    {
        return self::formatErrorResponse(
            self::ERROR_IN_USE,
            'Cannot delete template that is currently in use',
            409,
            array(
                'usage_count' => $usageCount,
                'suggestion' => 'Please remove the template from all active checklists before deleting'
            )
        );
    }
    
    /**
     * Handle access denied error with specific context
     * 
     * @param string $action The action that was denied (view, edit, delete, share)
     * @param string $templateId The template ID
     * @return array Formatted error response
     */
    public static function handleAccessDeniedError($action, $templateId = null)
    {
        $message = "Access denied: insufficient permissions to $action template";
        $details = array('action' => $action);
        
        if ($templateId) {
            $details['template_id'] = $templateId;
        }
        
        return self::formatErrorResponse(
            self::ERROR_ACCESS_DENIED,
            $message,
            403,
            $details
        );
    }
    
    /**
     * Handle database error
     * 
     * @param string $operation The database operation that failed
     * @param string $debugMessage Debug message (not exposed to client)
     * @return array Formatted error response
     */
    public static function handleDatabaseError($operation, $debugMessage = '')
    {
        // Log the debug message for internal use
        if ($debugMessage) {
            self::logError(new Exception("Database error during $operation: $debugMessage"));
        }
        
        return self::formatErrorResponse(
            self::ERROR_DATABASE,
            'Database operation failed',
            500,
            array(
                'operation' => $operation,
                'retry_suggested' => true
            )
        );
    }
    
    /**
     * Handle rate limiting error
     * 
     * @param int $retryAfter Seconds until next request allowed
     * @return array Formatted error response
     */
    public static function handleRateLimitError($retryAfter = 60)
    {
        return self::formatErrorResponse(
            self::ERROR_RATE_LIMIT,
            'Rate limit exceeded',
            429,
            array(
                'retry_after' => $retryAfter,
                'message' => "Too many requests. Please try again in $retryAfter seconds."
            )
        );
    }
    
    /**
     * Handle quota exceeded error
     * 
     * @param string $quotaType The type of quota exceeded (templates, items, etc.)
     * @param int $currentCount Current count
     * @param int $maxAllowed Maximum allowed
     * @return array Formatted error response
     */
    public static function handleQuotaExceededError($quotaType, $currentCount, $maxAllowed)
    {
        return self::formatErrorResponse(
            self::ERROR_QUOTA_EXCEEDED,
            "Quota exceeded for $quotaType",
            409,
            array(
                'quota_type' => $quotaType,
                'current_count' => $currentCount,
                'max_allowed' => $maxAllowed,
                'suggestion' => "Please delete some existing $quotaType or contact your administrator"
            )
        );
    }
    
    /**
     * Format error response with consistent structure
     * 
     * @param string $errorCode Error code constant
     * @param string $message Human-readable error message
     * @param int $httpStatus HTTP status code
     * @param array $details Additional error details
     * @return array Formatted error response
     */
    private static function formatErrorResponse($errorCode, $message, $httpStatus, $details = array())
    {
        global $current_user;
        
        $response = array(
            'success' => false,
            'error' => array(
                'code' => $errorCode,
                'message' => $message,
                'http_status' => $httpStatus,
                'timestamp' => date('c'), // ISO 8601 format
                'request_id' => self::generateRequestId(),
            )
        );
        
        // Add details if provided
        if (!empty($details)) {
            $response['error']['details'] = $details;
        }
        
        // Add user context for debugging (but not sensitive info)
        if (!empty($current_user->id)) {
            $response['error']['user_id'] = $current_user->id;
        }
        
        // Add helpful documentation links for common errors
        $response['error']['documentation'] = self::getDocumentationLink($errorCode);
        
        return $response;
    }
    
    /**
     * Generate unique request ID for error tracking
     * 
     * @return string Unique request ID
     */
    private static function generateRequestId()
    {
        return 'template_' . uniqid() . '_' . time();
    }
    
    /**
     * Get documentation link for error code
     * 
     * @param string $errorCode Error code
     * @return string Documentation URL
     */
    private static function getDocumentationLink($errorCode)
    {
        $baseUrl = 'https://docs.makedealscrm.com/api/templates/errors/';
        
        $linkMap = array(
            self::ERROR_VALIDATION => $baseUrl . 'validation',
            self::ERROR_NOT_FOUND => $baseUrl . 'not-found',
            self::ERROR_ACCESS_DENIED => $baseUrl . 'access-denied',
            self::ERROR_DUPLICATE_NAME => $baseUrl . 'duplicate-name',
            self::ERROR_IN_USE => $baseUrl . 'in-use',
            self::ERROR_DATABASE => $baseUrl . 'database',
            self::ERROR_RATE_LIMIT => $baseUrl . 'rate-limit',
            self::ERROR_QUOTA_EXCEEDED => $baseUrl . 'quota-exceeded',
        );
        
        return $linkMap[$errorCode] ?? $baseUrl . 'general';
    }
    
    /**
     * Log error for debugging and monitoring
     * 
     * @param Exception $exception Exception to log
     */
    private static function logError($exception)
    {
        global $current_user;
        
        $logMessage = sprintf(
            '[TEMPLATE_API_ERROR] %s: %s in %s:%d',
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );
        
        // Add user context if available
        if (!empty($current_user->id)) {
            $logMessage .= " [User: {$current_user->id}]";
        }
        
        // Add stack trace for debugging
        $logMessage .= "\nStack trace:\n" . $exception->getTraceAsString();
        
        // Use SuiteCRM's logging system
        if (class_exists('LoggerManager')) {
            $logger = LoggerManager::getLogger('TemplateAPI');
            $logger->error($logMessage);
        } else {
            // Fallback to error_log
            error_log($logMessage);
        }
        
        // Optionally send to monitoring service
        self::sendToMonitoring($exception);
    }
    
    /**
     * Send error to monitoring service (placeholder for external monitoring)
     * 
     * @param Exception $exception Exception to monitor
     */
    private static function sendToMonitoring($exception)
    {
        // This would integrate with monitoring services like Sentry, Bugsnag, etc.
        // For now, it's a placeholder that could be implemented based on needs
        
        // Example implementation:
        /*
        if (function_exists('sentry_capture_exception')) {
            sentry_capture_exception($exception);
        }
        */
    }
    
    /**
     * Validate error response format
     * 
     * @param array $response Error response to validate
     * @return bool True if valid error response format
     */
    public static function isValidErrorResponse($response)
    {
        if (!is_array($response)) {
            return false;
        }
        
        // Check required fields
        if (!isset($response['success']) || $response['success'] !== false) {
            return false;
        }
        
        if (!isset($response['error']) || !is_array($response['error'])) {
            return false;
        }
        
        $error = $response['error'];
        $requiredFields = array('code', 'message', 'http_status', 'timestamp', 'request_id');
        
        foreach ($requiredFields as $field) {
            if (!isset($error[$field])) {
                return false;
            }
        }
        
        // Validate HTTP status code
        if (!is_int($error['http_status']) || $error['http_status'] < 100 || $error['http_status'] > 599) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get user-friendly error message based on error code
     * 
     * @param string $errorCode Error code
     * @return string User-friendly message
     */
    public static function getUserFriendlyMessage($errorCode)
    {
        $messages = array(
            self::ERROR_VALIDATION => 'Please check your input and try again.',
            self::ERROR_NOT_FOUND => 'The requested template could not be found.',
            self::ERROR_ACCESS_DENIED => 'You do not have permission to perform this action.',
            self::ERROR_DUPLICATE_NAME => 'A template with this name already exists.',
            self::ERROR_IN_USE => 'This template cannot be modified because it is currently being used.',
            self::ERROR_DATABASE => 'A temporary database issue occurred. Please try again.',
            self::ERROR_RATE_LIMIT => 'You are making requests too quickly. Please slow down.',
            self::ERROR_QUOTA_EXCEEDED => 'You have reached your limit. Please contact support.',
            self::ERROR_INTERNAL => 'An unexpected error occurred. Please contact support.',
        );
        
        return $messages[$errorCode] ?? $messages[self::ERROR_INTERNAL];
    }
    
    /**
     * Create error response for missing required parameters
     * 
     * @param array $missingParams Array of missing parameter names
     * @return array Formatted error response
     */
    public static function createMissingParametersError($missingParams)
    {
        return self::formatErrorResponse(
            self::ERROR_VALIDATION,
            'Missing required parameters',
            400,
            array(
                'missing_parameters' => $missingParams,
                'message' => 'The following parameters are required: ' . implode(', ', $missingParams)
            )
        );
    }
    
    /**
     * Create error response for invalid parameter values
     * 
     * @param array $invalidParams Array of invalid parameters with reasons
     * @return array Formatted error response
     */
    public static function createInvalidParametersError($invalidParams)
    {
        return self::formatErrorResponse(
            self::ERROR_VALIDATION,
            'Invalid parameter values',
            400,
            array(
                'invalid_parameters' => $invalidParams,
                'message' => 'Please check the parameter values and try again'
            )
        );
    }
}