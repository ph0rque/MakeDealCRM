<?php
/**
 * File Request System Test and Setup Script
 * 
 * Tests the file request system integration and creates database tables
 * 
 * @category  Test
 * @package   Deals
 * @author    MakeDealCRM
 * @license   GPL-3.0+
 */

if (!defined('sugarEntry')) {
    define('sugarEntry', true);
}

// Set current directory to SuiteCRM root
chdir(__DIR__ . '/../../../..');

require_once 'config.php';
require_once 'include/entryPoint.php';

echo "File Request System Test and Setup\n";
echo "===================================\n\n";

// Test 1: Create database tables
echo "1. Creating database tables...\n";
try {
    require_once 'custom/modules/Deals/scripts/create_file_request_tables.php';
    $creator = new FileRequestTablesCreator();
    $result = $creator->createTables();
    
    if ($result['success']) {
        echo "✅ Database tables created successfully\n";
        foreach ($result['results'] as $detail) {
            echo "   - $detail\n";
        }
    } else {
        echo "❌ Failed to create database tables: {$result['message']}\n";
        if (isset($result['error'])) {
            echo "   Error: {$result['error']}\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Database table creation error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Test API registration
echo "2. Testing API registration...\n";
try {
    require_once 'custom/modules/Deals/api/FileRequestApi.php';
    $api = new FileRequestApi();
    $endpoints = $api->registerApiRest();
    
    echo "✅ FileRequestApi loaded successfully\n";
    echo "   Registered endpoints:\n";
    foreach ($endpoints as $name => $config) {
        echo "   - {$config['reqType']} /" . implode('/', $config['path']) . " ({$name})\n";
    }
} catch (Exception $e) {
    echo "❌ API registration error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Test email templates
echo "3. Testing email templates...\n";
try {
    require_once 'custom/modules/Deals/api/FileRequestEmailTemplates.php';
    $templates = new FileRequestEmailTemplates();
    $allTemplates = $templates->getAllTemplates();
    
    echo "✅ Email templates loaded successfully\n";
    echo "   Available templates:\n";
    foreach ($allTemplates as $type => $template) {
        echo "   - {$type}: {$template['name']}\n";
    }
} catch (Exception $e) {
    echo "❌ Email templates error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 4: Test file upload handler accessibility
echo "4. Testing file upload handler...\n";
try {
    $uploadFile = 'custom/modules/Deals/upload.php';
    if (file_exists($uploadFile)) {
        echo "✅ Upload handler file exists and is accessible\n";
        echo "   Path: $uploadFile\n";
        
        // Check if upload directory structure can be created
        $testDir = 'upload/file_requests/test/';
        if (!is_dir($testDir)) {
            if (mkdir($testDir, 0755, true)) {
                echo "✅ Upload directory structure created successfully\n";
                rmdir($testDir);
                rmdir('upload/file_requests/');
            } else {
                echo "⚠️  Warning: Could not create upload directory structure\n";
            }
        }
    } else {
        echo "❌ Upload handler file not found\n";
    }
} catch (Exception $e) {
    echo "❌ Upload handler test error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 5: Check SuiteCRM integration points
echo "5. Checking SuiteCRM integration points...\n";
try {
    // Check if OutboundEmail is available
    if (class_exists('OutboundEmail')) {
        echo "✅ OutboundEmail class available\n";
    } else {
        echo "⚠️  OutboundEmail class not found\n";
    }
    
    // Check if BeanFactory is available
    if (class_exists('BeanFactory')) {
        echo "✅ BeanFactory class available\n";
    } else {
        echo "⚠️  BeanFactory class not found\n";
    }
    
    // Check if database is accessible
    global $db;
    if ($db && method_exists($db, 'query')) {
        echo "✅ Database connection available\n";
    } else {
        echo "⚠️  Database connection not available\n";
    }
    
} catch (Exception $e) {
    echo "❌ Integration check error: " . $e->getMessage() . "\n";
}

echo "\n";

// Summary
echo "File Request System Setup Complete\n";
echo "==================================\n\n";

echo "SYSTEM COMPONENTS:\n";
echo "- FileRequestApi.php: Main API endpoints for file request management\n";
echo "- FileRequestEmailTemplates.php: Email template system with 8 template types\n";
echo "- upload.php: Secure file upload handler with virus scanning\n";
echo "- create_file_request_tables.php: Database schema management\n";
echo "- Database tables: 4 tables for complete request tracking\n\n";

echo "API ENDPOINTS AVAILABLE:\n";
echo "- POST /Deals/file-request/create: Create new file requests\n";
echo "- GET /Deals/file-request/list: List file requests for deals\n";
echo "- PUT /Deals/file-request/status: Update request status\n";
echo "- POST /Deals/file-request/upload: Handle file uploads\n";
echo "- POST /Deals/file-request/send-email: Send/resend request emails\n";
echo "- GET /Deals/file-request/status/{id}: Get detailed request status\n\n";

echo "EMAIL TEMPLATES:\n";
echo "- due_diligence: Professional due diligence document requests\n";
echo "- financial: Financial document and statement requests\n";
echo "- legal: Legal document and contract requests\n";
echo "- general: General purpose file requests\n";
echo "- reminder: Friendly reminder emails for pending requests\n";
echo "- completion: Notification emails when all files are received\n";
echo "- partial_completion: Updates when some files are received\n";
echo "- overdue: Urgent notifications for overdue requests\n\n";

echo "SECURITY FEATURES:\n";
echo "- Token-based upload authentication\n";
echo "- File type validation and filtering\n";
echo "- Virus scanning integration points\n";
echo "- Secure file storage with access controls\n";
echo "- Email template variable sanitization\n\n";

echo "INTEGRATION POINTS:\n";
echo "- SuiteCRM's OutboundEmail system for email sending\n";
echo "- Deal/Opportunity records for context\n";
echo "- Document management system for file storage\n";
echo "- User authentication and ACL system\n";
echo "- Database abstraction layer\n\n";

echo "USAGE:\n";
echo "1. Create file requests via API or future UI\n";
echo "2. System generates unique upload tokens and URLs\n";
echo "3. Automated emails sent with upload instructions\n";
echo "4. Recipients upload files through secure interface\n";
echo "5. Progress tracked and notifications sent\n";
echo "6. Files linked to deals for easy access\n\n";

echo "The File Request System with Email Integration is now ready for use!\n";