<?php
/**
 * Test Script for Email Processing System
 * Tests email parsing, contact extraction, and deal creation
 * 
 * @package MakeDealCRM
 * @subpackage Deals
 */

if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');
require_once('custom/modules/Deals/EmailParser.php');
require_once('custom/modules/Deals/EmailProcessor.php');
require_once('custom/modules/Deals/EmailThreadTracker.php');

global $current_user, $db;

// Test configuration
$testResults = array();
$testEmails = array();

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
 * Create test email data
 */
function createTestEmailData($scenario = 'basic') {
    $testData = array();
    
    switch ($scenario) {
        case 'basic':
            $testData = array(
                'id' => create_guid(),
                'name' => 'New Business Opportunity - ABC Manufacturing',
                'description' => "Hi,\n\nI'm reaching out regarding the sale of ABC Manufacturing.\n\n" .
                               "Company: ABC Manufacturing Inc.\n" .
                               "Industry: Manufacturing\n" .
                               "Asking Price: $2.5M\n" .
                               "Annual Revenue: $5M\n" .
                               "EBITDA: $750K\n\n" .
                               "Please let me know if you're interested.\n\n" .
                               "Best regards,\n" .
                               "John Smith\n" .
                               "Business Broker\n" .
                               "john.smith@brokerage.com\n" .
                               "(555) 123-4567",
                'from_addr' => '"John Smith" <john.smith@brokerage.com>',
                'to_addrs' => 'deals@mycrm',
                'date_sent' => date('Y-m-d H:i:s'),
                'message_id' => '<' . uniqid() . '@brokerage.com>'
            );
            break;
            
        case 'complex':
            $testData = array(
                'id' => create_guid(),
                'name' => 'Re: Tech Startup Acquisition Opportunity',
                'description_html' => '<html><body>' .
                    '<p>Following up on our previous conversation about the tech startup.</p>' .
                    '<ul>' .
                    '<li><strong>Company:</strong> TechStart Solutions</li>' .
                    '<li><strong>Industry:</strong> Software/SaaS</li>' .
                    '<li><strong>Revenue:</strong> $3.2M TTM</li>' .
                    '<li><strong>EBITDA:</strong> $480K</li>' .
                    '<li><strong>Asking Price:</strong> $4.5M (3x revenue)</li>' .
                    '</ul>' .
                    '<p>Seller: Mike Johnson (CEO) - mike@techstart.com</p>' .
                    '<p>Attorney: Sarah Williams, Esq. - swilliams@lawfirm.com</p>' .
                    '<p>Accountant: Robert Chen, CPA - rchen@accounting.com</p>' .
                    '<hr>' .
                    '<p>Jane Doe<br>Senior Investment Analyst<br>jane.doe@investmentfirm.com<br>(555) 987-6543</p>' .
                    '</body></html>',
                'from_addr' => 'jane.doe@investmentfirm.com',
                'to_addrs' => 'deals@mycrm; team@mycrm',
                'cc_addrs' => 'assistant@mycrm',
                'date_sent' => date('Y-m-d H:i:s'),
                'message_id' => '<' . uniqid() . '@investmentfirm.com>',
                'reply_to_addr' => 'jane.doe@investmentfirm.com'
            );
            break;
            
        case 'thread':
            $testData = array(
                'id' => create_guid(),
                'name' => 'Re: Manufacturing Business - Additional Information',
                'description' => "Thanks for your interest in ABC Manufacturing.\n\n" .
                               "Here's the additional information you requested:\n" .
                               "- Current owner looking to retire\n" .
                               "- 25 employees, all willing to stay\n" .
                               "- Long-term contracts with major clients\n" .
                               "- Updated asking price: $2.8M\n\n" .
                               "Let me know if you need anything else.",
                'from_addr' => 'john.smith@brokerage.com',
                'to_addrs' => 'deals@mycrm',
                'date_sent' => date('Y-m-d H:i:s'),
                'message_id' => '<' . uniqid() . '@brokerage.com>',
                'raw_source' => "From: john.smith@brokerage.com\r\n" .
                              "To: deals@mycrm\r\n" .
                              "Subject: Re: Manufacturing Business - Additional Information\r\n" .
                              "In-Reply-To: <abc123@brokerage.com>\r\n" .
                              "References: <xyz789@mycrm> <abc123@brokerage.com>\r\n"
            );
            break;
    }
    
    return $testData;
}

/**
 * Test 1: Email Parser - Basic
 */
runTest('Email Parser - Basic Deal Extraction', function() {
    $parser = new DealsEmailParser();
    $emailData = createTestEmailData('basic');
    
    $result = $parser->parseEmail($emailData);
    
    if (!$result['success']) {
        throw new Exception("Failed to parse email");
    }
    
    // Verify deal data extraction
    if ($result['deal_data']['amount'] != 2500000) {
        throw new Exception("Incorrect amount parsing");
    }
    
    if ($result['deal_data']['annual_revenue_c'] != 5000000) {
        throw new Exception("Incorrect revenue parsing");
    }
    
    // Verify contact extraction
    if (count($result['contacts']) < 1) {
        throw new Exception("No contacts extracted");
    }
    
    $broker = null;
    foreach ($result['contacts'] as $contact) {
        if ($contact['email'] == 'john.smith@brokerage.com') {
            $broker = $contact;
            break;
        }
    }
    
    if (!$broker) {
        throw new Exception("Broker contact not extracted");
    }
    
    return $result;
});

/**
 * Test 2: Email Parser - Complex HTML
 */
runTest('Email Parser - Complex HTML Email', function() {
    $parser = new DealsEmailParser();
    $emailData = createTestEmailData('complex');
    
    $result = $parser->parseEmail($emailData);
    
    if (!$result['success']) {
        throw new Exception("Failed to parse HTML email");
    }
    
    // Check multiple contacts extracted
    if (count($result['contacts']) < 3) {
        throw new Exception("Expected at least 3 contacts, got " . count($result['contacts']));
    }
    
    // Check roles identified
    $roles = array_column($result['contacts'], 'role');
    if (!in_array('seller', $roles)) {
        throw new Exception("Seller role not identified");
    }
    if (!in_array('attorney', $roles)) {
        throw new Exception("Attorney role not identified");
    }
    
    return $result;
});

/**
 * Test 3: Duplicate Detection
 */
runTest('Duplicate Detection', function() {
    global $db;
    
    // Create a test deal first
    $deal = BeanFactory::newBean('Opportunities');
    $deal->name = 'ABC Manufacturing Acquisition';
    $deal->amount = 2500000;
    $deal->account_name = 'ABC Manufacturing Inc.';
    $deal->save();
    
    // Test duplicate detection
    $parser = new DealsEmailParser();
    $emailData = createTestEmailData('basic');
    
    $result = $parser->parseEmail($emailData);
    
    if (!$result['is_duplicate']) {
        // Clean up
        $deal->mark_deleted($deal->id);
        throw new Exception("Duplicate not detected");
    }
    
    // Clean up
    $deal->mark_deleted($deal->id);
    
    return "Duplicate correctly detected";
});

/**
 * Test 4: Email Processor - Deal Creation
 */
runTest('Email Processor - Deal Creation', function() {
    global $testEmails;
    
    $processor = new DealsEmailProcessor();
    
    // Create mock email bean
    $email = BeanFactory::newBean('Emails');
    $emailData = createTestEmailData('basic');
    foreach ($emailData as $field => $value) {
        $email->$field = $value;
    }
    $email->type = 'inbound';
    
    $result = $processor->processEmail($email);
    
    if (!$result['success']) {
        throw new Exception("Failed to process email: " . $result['message']);
    }
    
    if (empty($result['deal_id'])) {
        throw new Exception("No deal ID returned");
    }
    
    // Store for cleanup
    $testEmails[] = $result['deal_id'];
    
    // Verify deal created
    $deal = BeanFactory::getBean('Opportunities', $result['deal_id']);
    if (empty($deal->id)) {
        throw new Exception("Deal not found in database");
    }
    
    return $result;
});

/**
 * Test 5: Thread Tracking
 */
runTest('Email Thread Tracking', function() {
    $tracker = new EmailThreadTracker();
    
    // Create mock email
    $email = BeanFactory::newBean('Emails');
    $emailData = createTestEmailData('thread');
    foreach ($emailData as $field => $value) {
        $email->$field = $value;
    }
    
    // Track email
    $dealId = create_guid();
    $threadId = $tracker->trackEmail($email, $dealId);
    
    if (empty($threadId)) {
        throw new Exception("No thread ID returned");
    }
    
    // Get thread info
    $threadInfo = $tracker->getThreadInfo($email);
    
    if (empty($threadInfo)) {
        throw new Exception("Thread info not found");
    }
    
    if ($threadInfo['deal_id'] != $dealId) {
        throw new Exception("Incorrect deal ID in thread info");
    }
    
    return array(
        'thread_id' => $threadId,
        'thread_info' => $threadInfo
    );
});

/**
 * Test 6: Attachment Processing
 */
runTest('Attachment Processing', function() {
    $parser = new DealsEmailParser();
    
    // Create email with mock attachment data
    $emailData = createTestEmailData('basic');
    $emailData['id'] = create_guid();
    
    // We'll simulate attachments by checking the method exists
    $result = $parser->parseEmail($emailData);
    
    if (!isset($result['attachments'])) {
        throw new Exception("Attachments field not in result");
    }
    
    return "Attachment processing structure verified";
});

/**
 * Test 7: Contact Role Assignment
 */
runTest('Contact Role Assignment', function() {
    $parser = new DealsEmailParser();
    $emailData = createTestEmailData('complex');
    
    $result = $parser->parseEmail($emailData);
    
    $rolesFound = array();
    foreach ($result['contacts'] as $contact) {
        if (!empty($contact['role'])) {
            $rolesFound[$contact['role']] = true;
        }
    }
    
    if (empty($rolesFound)) {
        throw new Exception("No contact roles assigned");
    }
    
    return array(
        'roles_found' => array_keys($rolesFound),
        'contact_count' => count($result['contacts'])
    );
});

/**
 * Test 8: Performance - Bulk Email Processing
 */
runTest('Performance - Parse 10 Emails', function() {
    $parser = new DealsEmailParser();
    
    $startTime = microtime(true);
    $results = array();
    
    for ($i = 0; $i < 10; $i++) {
        $emailData = createTestEmailData('basic');
        $emailData['name'] = "Test Deal #$i - " . uniqid();
        $result = $parser->parseEmail($emailData);
        $results[] = $result['success'];
    }
    
    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);
    
    $successCount = array_sum($results);
    
    if ($successCount < 10) {
        throw new Exception("Some emails failed to parse");
    }
    
    return array(
        'emails_processed' => 10,
        'duration_seconds' => $duration,
        'avg_time_per_email' => round($duration / 10, 3)
    );
});

/**
 * Cleanup test data
 */
runTest('Cleanup Test Data', function() use ($testEmails) {
    global $db;
    
    $cleaned = 0;
    
    // Delete test deals
    foreach ($testEmails as $dealId) {
        $deal = BeanFactory::getBean('Opportunities', $dealId);
        if ($deal && !empty($deal->id)) {
            $deal->mark_deleted($dealId);
            $cleaned++;
        }
    }
    
    // Clean up thread tracking test data
    $query = "DELETE FROM email_thread_deals WHERE deal_id LIKE 'test_%'";
    $db->query($query);
    
    return "Cleaned up $cleaned test records";
});

/**
 * Display test summary
 */
echo "\n<h2>Test Summary</h2>\n";
echo "<table border='1' cellpadding='5' cellspacing='0'>\n";
echo "<tr><th>Test</th><th>Status</th><th>Details</th></tr>\n";

$passed = 0;
$failed = 0;

foreach ($testResults as $testName => $result) {
    $color = $result['status'] === 'PASSED' ? 'green' : 'red';
    echo "<tr>";
    echo "<td>$testName</td>";
    echo "<td style='color: $color;'>{$result['status']}</td>";
    echo "<td>" . (isset($result['error']) ? $result['error'] : 'Success') . "</td>";
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
    echo "\n<h3 style='color: green;'>✓ All tests passed! The email processing system is working correctly.</h3>\n";
} else {
    echo "\n<h3 style='color: orange;'>⚠ Some tests failed. Please review the errors above.</h3>\n";
}

echo "\n<hr>\n";
echo "<h3>Configuration Instructions:</h3>\n";
echo "<ol>\n";
echo "<li>Configure email forwarding to send emails to deals@mycrm</li>\n";
echo "<li>Update email configuration in custom/modules/Deals/config/email_config.php</li>\n";
echo "<li>Clear SuiteCRM cache: Admin → Repair → Quick Repair and Rebuild</li>\n";
echo "<li>Test by sending an email with deal information to deals@mycrm</li>\n";
echo "</ol>\n";

echo "\n<h3>Email Format Example:</h3>\n";
echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>\n";
echo "Subject: New Deal - ABC Company Acquisition\n\n";
echo "Company: ABC Company Inc.\n";
echo "Industry: Manufacturing\n";
echo "Asking Price: \$2.5M\n";
echo "Revenue: \$5M\n";
echo "EBITDA: \$750K\n\n";
echo "Seller: John Doe - john@abccompany.com\n";
echo "Broker: Jane Smith - jane@brokerage.com\n\n";
echo "Please review this opportunity.\n";
echo "</pre>\n";
?>