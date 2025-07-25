<?php
/**
 * Email Configuration for Deals Module
 * Configure email processing settings
 * 
 * @package MakeDealCRM
 * @subpackage Deals
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

// Email processing configuration
$deals_email_config = array(
    // Email address to monitor
    'monitor_address' => 'deals@mycrm',
    
    // Processing settings
    'processing' => array(
        'enabled' => true,
        'retry_attempts' => 3,
        'retry_delay' => 5, // seconds
        'max_email_age' => 30, // days
        'batch_size' => 50, // for bulk processing
    ),
    
    // Duplicate detection settings
    'duplicate_detection' => array(
        'enabled' => true,
        'similarity_threshold' => 0.7, // 70% similarity
        'check_window' => 7, // days to look back
        'check_fields' => array('name', 'account_name', 'amount'),
    ),
    
    // Contact extraction settings
    'contact_extraction' => array(
        'enabled' => true,
        'extract_from_signature' => true,
        'extract_from_body' => true,
        'auto_assign_roles' => true,
        'role_keywords' => array(
            'seller' => array('seller', 'owner', 'proprietor'),
            'broker' => array('broker', 'agent', 'representative'),
            'attorney' => array('attorney', 'lawyer', 'counsel', 'esq'),
            'accountant' => array('accountant', 'cpa', 'cfo'),
            'buyer' => array('buyer', 'purchaser', 'investor'),
        ),
    ),
    
    // Deal creation settings
    'deal_creation' => array(
        'default_stage' => 'sourcing',
        'default_probability' => 10,
        'default_sales_stage' => 'Prospecting',
        'auto_assign' => true, // assign to email recipient
        'extract_financials' => true,
        'financial_patterns' => array(
            'revenue' => array('revenue', 'sales', 'income'),
            'ebitda' => array('ebitda', 'earnings'),
            'asking_price' => array('asking price', 'price', 'valuation'),
        ),
    ),
    
    // Attachment handling
    'attachments' => array(
        'process' => true,
        'max_size' => 10485760, // 10MB
        'allowed_types' => array(
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'image/jpeg',
            'image/png',
            'text/plain',
        ),
        'scan_for_data' => false, // OCR/text extraction
    ),
    
    // Email thread tracking
    'thread_tracking' => array(
        'enabled' => true,
        'track_conversations' => true,
        'link_to_existing_deals' => true,
        'subject_similarity_threshold' => 0.8,
    ),
    
    // Notifications
    'notifications' => array(
        'on_success' => true,
        'on_failure' => true,
        'notify_assigned_user' => true,
        'notify_admin_on_failure' => true,
        'summary_report' => array(
            'enabled' => true,
            'frequency' => 'daily', // daily, weekly, monthly
            'recipients' => array(), // email addresses
        ),
    ),
    
    // Performance settings
    'performance' => array(
        'use_background_processing' => true,
        'cache_parsed_data' => true,
        'cache_ttl' => 3600, // 1 hour
        'parallel_processing' => false,
        'max_parallel_jobs' => 5,
    ),
    
    // Industry mapping
    'industry_mapping' => array(
        'technology' => array('tech', 'it', 'software', 'saas', 'hardware'),
        'manufacturing' => array('mfg', 'manufacturing', 'industrial'),
        'retail' => array('retail', 'ecommerce', 'e-commerce', 'store'),
        'healthcare' => array('healthcare', 'medical', 'health', 'pharma'),
        'financial services' => array('finance', 'banking', 'insurance', 'fintech'),
        'real estate' => array('realestate', 'property', 'reit', 'construction'),
        'professional services' => array('consulting', 'legal', 'accounting'),
        'hospitality' => array('hotel', 'restaurant', 'tourism', 'travel'),
        'education' => array('education', 'training', 'edtech', 'school'),
        'transportation' => array('transport', 'logistics', 'shipping', 'delivery'),
    ),
    
    // Error handling
    'error_handling' => array(
        'log_errors' => true,
        'create_error_notes' => true,
        'quarantine_failed_emails' => true,
        'max_error_log_size' => 10485760, // 10MB
    ),
);

// Apply configuration to global scope
$GLOBALS['deals_email_config'] = $deals_email_config;

// Function to get configuration value
function getDealsEmailConfig($key, $default = null)
{
    $config = $GLOBALS['deals_email_config'] ?? array();
    
    // Handle nested keys (e.g., 'processing.enabled')
    $keys = explode('.', $key);
    $value = $config;
    
    foreach ($keys as $k) {
        if (is_array($value) && isset($value[$k])) {
            $value = $value[$k];
        } else {
            return $default;
        }
    }
    
    return $value;
}
?>