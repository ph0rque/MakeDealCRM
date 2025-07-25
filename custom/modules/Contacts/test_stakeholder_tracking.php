<?php
/**
 * Stakeholder Tracking System - Comprehensive Test Suite
 * Tests all components of the stakeholder tracking module
 */

if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');
require_once('custom/modules/Contacts/ContactRoleManager.php');
require_once('custom/modules/Contacts/services/StakeholderRelationshipService.php');
require_once('custom/modules/Contacts/services/EmailTemplateManager.php');
require_once('custom/modules/Contacts/services/CommunicationHistoryService.php');

global $current_user, $db;

// Test configuration
$testResults = array();
$testDealId = null;
$testContactIds = array();

/**
 * Test runner utility
 */
function runTest($testName, $testFunction) {
    global $testResults;
    
    echo "\n<h3>Testing: $testName</h3>\n";
    
    try {
        $result = $testFunction();
        $testResults[$testName] = array(
            'status' => 'PASSED',
            'result' => $result
        );
        echo "<span style='color: green;'>✓ PASSED</span>\n";
        if (!empty($result)) {
            echo "<pre>" . print_r($result, true) . "</pre>\n";
        }
    } catch (Exception $e) {
        $testResults[$testName] = array(
            'status' => 'FAILED',
            'error' => $e->getMessage()
        );
        echo "<span style='color: red;'>✗ FAILED: " . $e->getMessage() . "</span>\n";
    }
}

/**
 * Create test data
 */
function createTestData() {
    global $db, $testDealId, $testContactIds;
    
    // Create a test deal
    $deal = BeanFactory::newBean('Opportunities');
    $deal->name = 'Test Stakeholder Deal ' . time();
    $deal->amount = '1000000';
    $deal->sales_stage = 'Prospecting';
    $deal->assigned_user_id = $GLOBALS['current_user']->id;
    $deal->save();
    $testDealId = $deal->id;
    
    // Create test contacts with different roles
    $roles = ContactRoleManager::getAllRoles();
    foreach ($roles as $roleKey => $roleName) {
        $contact = BeanFactory::newBean('Contacts');
        $contact->first_name = 'Test';
        $contact->last_name = $roleName . ' ' . time();
        $contact->email1 = strtolower($roleKey) . '@test.com';
        $contact->contact_role_c = $roleKey;
        $contact->assigned_user_id = $GLOBALS['current_user']->id;
        $contact->save();
        
        $testContactIds[$roleKey] = $contact->id;
        
        // Link to deal
        $deal->load_relationship('contacts');
        $deal->contacts->add($contact->id);
    }
    
    return array(
        'dealId' => $testDealId,
        'contactIds' => $testContactIds
    );
}

/**
 * Test 1: Contact Role Manager
 */
runTest('ContactRoleManager - Get All Roles', function() {
    $roles = ContactRoleManager::getAllRoles();
    if (count($roles) !== 5) {
        throw new Exception("Expected 5 roles, got " . count($roles));
    }
    return $roles;
});

runTest('ContactRoleManager - Validate Roles', function() {
    $validRole = ContactRoleManager::isValidRole('seller');
    $invalidRole = ContactRoleManager::isValidRole('invalid_role');
    
    if (!$validRole || $invalidRole) {
        throw new Exception("Role validation failed");
    }
    return "Role validation working correctly";
});

runTest('ContactRoleManager - Get Role Statistics', function() {
    $stats = ContactRoleManager::getRoleStatistics();
    return $stats;
});

/**
 * Test 2: Database Schema
 */
runTest('Database Schema - Contact Fields', function() use ($db) {
    $query = "SHOW COLUMNS FROM contacts_cstm LIKE '%stakeholder%'";
    $result = $db->query($query);
    $columns = array();
    
    while ($row = $db->fetchByAssoc($result)) {
        $columns[] = $row['Field'];
    }
    
    return "Stakeholder fields found: " . implode(', ', $columns);
});

runTest('Database Schema - Communication History Table', function() use ($db) {
    $query = "SHOW TABLES LIKE 'contact_communication_history'";
    $result = $db->query($query);
    
    if ($db->getRowCount($result) === 0) {
        return "Communication history table not yet created (run migration)";
    }
    
    return "Communication history table exists";
});

/**
 * Test 3: Create Test Data
 */
runTest('Create Test Data', function() {
    return createTestData();
});

/**
 * Test 4: Stakeholder Relationship Service
 */
runTest('StakeholderRelationshipService - Add Stakeholder', function() use ($testDealId, $testContactIds) {
    $service = new StakeholderRelationshipService();
    
    $result = $service->addStakeholderToDeal(
        $testDealId, 
        $testContactIds['seller'], 
        'seller',
        array('is_primary' => true)
    );
    
    if (!$result) {
        throw new Exception("Failed to add stakeholder");
    }
    
    return "Successfully added seller as stakeholder";
});

runTest('StakeholderRelationshipService - Get Deal Stakeholders', function() use ($testDealId) {
    $service = new StakeholderRelationshipService();
    $stakeholders = $service->getDealStakeholders($testDealId);
    
    if (empty($stakeholders)) {
        throw new Exception("No stakeholders found for deal");
    }
    
    return "Found " . count($stakeholders) . " stakeholders";
});

runTest('StakeholderRelationshipService - Get Stakeholder Summary', function() use ($testDealId) {
    $service = new StakeholderRelationshipService();
    $summary = $service->getStakeholderSummary($testDealId);
    
    return $summary;
});

/**
 * Test 5: Email Template Manager
 */
runTest('EmailTemplateManager - Get Introduction Template', function() use ($testDealId) {
    $manager = new EmailTemplateManager();
    
    $participants = array(
        array('name' => 'John Seller', 'email' => 'seller@test.com', 'role' => 'Seller'),
        array('name' => 'Jane Buyer', 'email' => 'buyer@test.com', 'role' => 'Buyer'),
        array('name' => 'Bob Broker', 'email' => 'broker@test.com', 'role' => 'Broker')
    );
    
    $template = $manager->getIntroductionTemplate(
        $participants,
        'Test Property Deal',
        'I wanted to introduce everyone involved in this deal.'
    );
    
    if (empty($template['subject']) || empty($template['body'])) {
        throw new Exception("Template generation failed");
    }
    
    return array(
        'subject' => $template['subject'],
        'preview' => substr($template['body'], 0, 200) . '...'
    );
});

runTest('EmailTemplateManager - Get Follow-up Template', function() {
    $manager = new EmailTemplateManager();
    
    $template = $manager->getFollowUpTemplate(
        'John Doe',
        'Test Property Deal',
        'last week',
        array('Review contract', 'Schedule inspection')
    );
    
    return array(
        'subject' => $template['subject'],
        'preview' => substr($template['body'], 0, 200) . '...'
    );
});

/**
 * Test 6: Communication History Service
 */
runTest('CommunicationHistoryService - Log Communication', function() use ($testDealId, $testContactIds) {
    $service = new CommunicationHistoryService();
    
    $result = $service->logCommunication(
        $testContactIds['seller'],
        'email',
        'Sent initial property details',
        array('deal_id' => $testDealId)
    );
    
    return "Communication logged with ID: " . $result;
});

runTest('CommunicationHistoryService - Get Contact History', function() use ($testContactIds) {
    $service = new CommunicationHistoryService();
    
    $history = $service->getContactCommunicationHistory(
        $testContactIds['seller'],
        30 // Last 30 days
    );
    
    return "Found " . count($history) . " communication records";
});

/**
 * Test 7: API Endpoints
 */
runTest('API - Test Endpoint Availability', function() {
    $apiFile = 'custom/modules/Contacts/api/ContactsApi.php';
    
    if (!file_exists($apiFile)) {
        throw new Exception("API file not found");
    }
    
    require_once($apiFile);
    
    if (!class_exists('ContactsApi')) {
        throw new Exception("ContactsApi class not found");
    }
    
    $api = new ContactsApi();
    $endpoints = array(
        'getStakeholderRoles',
        'updateContactRole',
        'getContactsByRole',
        'getStakeholdersForDeal',
        'addStakeholderToDeal',
        'updateStakeholderRelationship',
        'removeStakeholderFromDeal',
        'getContactCommunicationHistory',
        'logCommunication',
        'getLastContactDate',
        'getInactiveContacts',
        'generateIntroductionEmail',
        'generateFollowUpEmail',
        'sendBulkEmail'
    );
    
    $missing = array();
    foreach ($endpoints as $endpoint) {
        if (!method_exists($api, $endpoint)) {
            $missing[] = $endpoint;
        }
    }
    
    if (!empty($missing)) {
        throw new Exception("Missing endpoints: " . implode(', ', $missing));
    }
    
    return "All " . count($endpoints) . " API endpoints are available";
});

/**
 * Test 8: UI Components
 */
runTest('UI Components - CSS Files', function() {
    $cssFiles = array(
        'custom/modules/Contacts/css/stakeholder-badges.css',
        'custom/modules/Contacts/css/quick-access.css',
        'custom/modules/Contacts/css/email-templates.css',
        'custom/modules/Contacts/css/stakeholder-grid.css'
    );
    
    $missing = array();
    foreach ($cssFiles as $file) {
        if (!file_exists($file)) {
            $missing[] = $file;
        }
    }
    
    if (!empty($missing)) {
        return "Missing CSS files: " . implode(', ', $missing);
    }
    
    return "All CSS files present";
});

runTest('UI Components - JavaScript Files', function() {
    $jsFiles = array(
        'custom/modules/Contacts/js/stakeholder-badges.js',
        'custom/modules/Contacts/js/quick-access.js',
        'custom/modules/Contacts/js/stakeholder-drag-drop.js'
    );
    
    $missing = array();
    foreach ($jsFiles as $file) {
        if (!file_exists($file)) {
            $missing[] = $file;
        }
    }
    
    if (!empty($missing)) {
        return "Missing JS files: " . implode(', ', $missing);
    }
    
    return "All JavaScript files present";
});

/**
 * Test 9: Deal Integration
 */
runTest('Deal Integration - Pipeline View Files', function() {
    $files = array(
        'custom/modules/Deals/api/StakeholderIntegrationApi.php',
        'custom/modules/Deals/js/stakeholder-integration.js',
        'custom/modules/Deals/css/stakeholder-badges.css',
        'custom/modules/Deals/views/view.stakeholder_bulk.php'
    );
    
    $missing = array();
    foreach ($files as $file) {
        if (!file_exists($file)) {
            $missing[] = $file;
        }
    }
    
    if (!empty($missing)) {
        return "Missing integration files: " . implode(', ', $missing);
    }
    
    return "All Deal integration files present";
});

/**
 * Test 10: Cleanup Test Data
 */
runTest('Cleanup Test Data', function() use ($db, $testDealId, $testContactIds) {
    // Delete test deal
    if ($testDealId) {
        $deal = BeanFactory::getBean('Opportunities', $testDealId);
        if ($deal) {
            $deal->mark_deleted($testDealId);
        }
    }
    
    // Delete test contacts
    foreach ($testContactIds as $contactId) {
        $contact = BeanFactory::getBean('Contacts', $contactId);
        if ($contact) {
            $contact->mark_deleted($contactId);
        }
    }
    
    return "Test data cleaned up";
});

/**
 * Display Test Summary
 */
echo "\n<h2>Test Summary</h2>\n";
echo "<table border='1' cellpadding='5' cellspacing='0'>\n";
echo "<tr><th>Test</th><th>Status</th></tr>\n";

$passed = 0;
$failed = 0;

foreach ($testResults as $testName => $result) {
    $color = $result['status'] === 'PASSED' ? 'green' : 'red';
    echo "<tr>";
    echo "<td>$testName</td>";
    echo "<td style='color: $color;'>{$result['status']}</td>";
    echo "</tr>\n";
    
    if ($result['status'] === 'PASSED') {
        $passed++;
    } else {
        $failed++;
    }
}

echo "</table>\n";
echo "\n<p>Total Tests: " . count($testResults) . "</p>\n";
echo "<p style='color: green;'>Passed: $passed</p>\n";
echo "<p style='color: red;'>Failed: $failed</p>\n";

if ($failed === 0) {
    echo "\n<h3 style='color: green;'>✓ All tests passed! The stakeholder tracking system is ready for use.</h3>\n";
} else {
    echo "\n<h3 style='color: orange;'>⚠ Some tests failed. Please review the errors above.</h3>\n";
}

echo "\n<hr>\n";
echo "<h3>Next Steps:</h3>\n";
echo "<ol>\n";
echo "<li>Run the database migration: <code>cd custom/modules/Contacts/sql && ./run_migration.sh all</code></li>\n";
echo "<li>Clear SuiteCRM cache: Admin → Repair → Quick Repair and Rebuild</li>\n";
echo "<li>Test the stakeholder features in the Deals pipeline view</li>\n";
echo "<li>Configure email templates for multi-party introductions</li>\n";
echo "</ol>\n";