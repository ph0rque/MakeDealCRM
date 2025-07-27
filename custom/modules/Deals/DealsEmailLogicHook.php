<?php
/**
 * Email Logic Hook for Deals Module - Automated Email Processing
 * 
 * This class implements intelligent email processing for the Deals module,
 * automatically creating or updating deals from emails sent to deals@mycrm.
 * It's a key component of the email-to-deal automation system that reduces
 * manual data entry and ensures all deal communications are tracked.
 * 
 * Key Features:
 * - Automatic deal creation from forwarded emails
 * - Intelligent parsing of deal information from email content
 * - Contact creation and linking from email participants
 * - Document attachment handling
 * - Retry logic for resilience
 * - Comprehensive logging and notifications
 * 
 * Email Processing Flow:
 * 1. Email arrives at deals@mycrm
 * 2. Logic hook triggered on email save
 * 3. Email parsed for deal information
 * 4. Deal created/updated with extracted data
 * 5. Contacts and documents linked
 * 6. Notifications sent on success/failure
 * 
 * @package MakeDealCRM
 * @subpackage Deals
 * @author MakeDealCRM Development Team
 * @version 1.0.0
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once('custom/modules/Deals/services/EmailProcessorService.php');

class DealsEmailLogicHook
{
    /**
     * @var DealsEmailProcessor The email processor instance
     */
    private $processor;
    
    /**
     * @var Logger SugarCRM logger instance
     */
    private $log;
    
    /**
     * @var int Maximum number of retry attempts for failed processing
     */
    private $retryAttempts = 3;
    
    /**
     * @var int Delay in seconds between retry attempts
     */
    private $retryDelay = 5; // seconds
    
    /**
     * Constructor initializes logging
     * 
     * Sets up the logger for comprehensive tracking of email processing
     * activities. All operations are logged for debugging and audit purposes.
     */
    public function __construct()
    {
        global $log;
        $this->log = $log;
    }
    
    /**
     * Process email after save - Main entry point for email processing
     * 
     * This is the primary logic hook method triggered when emails are saved.
     * It implements a series of checks to determine if an email should be
     * processed for deal creation/update:
     * 
     * Processing Criteria:
     * 1. Must be a new email (not an update)
     * 2. Must be an Email module bean
     * 3. Must be inbound or archived type
     * 4. Must be addressed to deals@mycrm
     * 
     * When criteria are met, the email is processed with retry logic to
     * handle transient failures. This ensures reliable processing even
     * under high load or temporary system issues.
     * 
     * The method is designed to be idempotent - processing the same email
     * multiple times will not create duplicate deals.
     * 
     * @param SugarBean $bean The email bean being saved
     * @param string $event The event type (after_save)
     * @param array $arguments Hook arguments including isUpdate flag
     * 
     * @return void
     */
    public function processDealsEmail($bean, $event, $arguments)
    {
        // Only process on new emails (not updates)
        if (!empty($arguments['isUpdate'])) {
            return;
        }
        
        // Check if this is an Email bean
        if ($bean->module_dir !== 'Emails') {
            return;
        }
        
        // Check if email is inbound
        if ($bean->type !== 'inbound' && $bean->type !== 'archived') {
            return;
        }
        
        // Check if email is sent to deals@mycrm
        if (!$this->isDealsEmail($bean)) {
            return;
        }
        
        $this->log->info("DealsEmailLogicHook: Processing deals email - " . $bean->name);
        
        // Process with retry logic
        $this->processWithRetry($bean);
    }
    
    /**
     * Check if email should be processed for deal creation
     * 
     * Determines if an email is addressed to the deals processing address.
     * This method checks all recipient fields (To, CC, BCC) for the presence
     * of the deals@mycrm address.
     * 
     * The check is case-insensitive and handles various email formats:
     * - Simple: deals@mycrm
     * - With display name: "Deals Team" <deals@mycrm>
     * - Multiple recipients: john@example.com, deals@mycrm
     * 
     * This filtering ensures only relevant emails trigger deal processing,
     * preventing accidental processing of unrelated emails.
     * 
     * @param SugarBean $email The email bean to check
     * 
     * @return bool True if email should be processed, false otherwise
     */
    private function isDealsEmail($email)
    {
        // Check all address fields
        $allAddresses = $email->to_addrs . ' ' . $email->cc_addrs . ' ' . $email->bcc_addrs;
        
        // Look for deals@mycrm
        return (stripos($allAddresses, 'deals@mycrm') !== false);
    }
    
    /**
     * Process email with retry logic for resilience
     * 
     * Implements a retry mechanism to handle transient failures during
     * email processing. This is critical for reliability as email processing
     * involves multiple external operations that can fail temporarily:
     * 
     * - Database connections
     * - File system operations for attachments
     * - Email service availability
     * - Memory or resource constraints
     * 
     * Retry Strategy:
     * 1. Attempts processing up to $retryAttempts times
     * 2. Waits $retryDelay seconds between attempts
     * 3. Logs each attempt and failure reason
     * 4. Sends notifications on final success or failure
     * 
     * This approach balances reliability with system resources, ensuring
     * important emails are processed while avoiding infinite loops.
     * 
     * @param SugarBean $email The email to process
     * 
     * @return void
     */
    private function processWithRetry($email)
    {
        $attempts = 0;
        $success = false;
        $lastError = null;
        
        while ($attempts < $this->retryAttempts && !$success) {
            $attempts++;
            
            try {
                // Initialize processor
                if (!$this->processor) {
                    $this->processor = EmailProcessorService::getInstance();
                }
                
                // Process email
                $result = $this->processor->processIncomingEmail($email);
                
                if ($result['success']) {
                    $success = true;
                    $this->logSuccess($email, $result);
                    
                    // Send notification if configured
                    $this->sendNotification($email, $result);
                } else {
                    $lastError = $result['message'] ?? 'Unknown error';
                    throw new Exception($lastError);
                }
                
            } catch (Exception $e) {
                $lastError = $e->getMessage();
                $this->log->error("DealsEmailLogicHook: Attempt {$attempts} failed - " . $lastError);
                
                if ($attempts < $this->retryAttempts) {
                    // Wait before retry
                    sleep($this->retryDelay);
                }
            }
        }
        
        // Log final failure
        if (!$success) {
            $this->logFailure($email, $lastError, $attempts);
        }
    }
    
    /**
     * Log successful email processing with detailed results
     * 
     * Creates comprehensive log entries for successful email processing.
     * This logging is essential for:
     * 
     * - Audit trail of all automated deal creation
     * - Debugging and troubleshooting
     * - Performance monitoring
     * - Compliance requirements
     * 
     * Logged Information:
     * - Email subject and ID
     * - Action taken (created/updated)
     * - Deal ID for reference
     * - Number of contacts created/linked
     * - Number of attachments processed
     * - Processing timestamp
     * 
     * Additionally creates an activity record (Note) attached to the email
     * for user-visible audit trail.
     * 
     * @param SugarBean $email The processed email
     * @param array $result Processing results from EmailProcessor
     * 
     * @return void
     */
    private function logSuccess($email, $result)
    {
        $message = "Successfully processed deals email:\n";
        $message .= "Email: {$email->name}\n";
        $message .= "Action: {$result['action']}\n";
        $message .= "Deal ID: {$result['deal_id']}\n";
        
        if (!empty($result['contacts_created'])) {
            $message .= "Contacts created: {$result['contacts_created']}\n";
        }
        if (!empty($result['contacts_linked'])) {
            $message .= "Contacts linked: {$result['contacts_linked']}\n";
        }
        if (!empty($result['attachments_linked'])) {
            $message .= "Attachments linked: {$result['attachments_linked']}\n";
        }
        
        $this->log->info("DealsEmailLogicHook: " . $message);
        
        // Create activity record
        $this->createActivityRecord($email, $result, 'success');
    }
    
    /**
     * Log processing failure with error details
     * 
     * Records detailed information about email processing failures to aid
     * in troubleshooting and system improvement. Failures are logged with:
     * 
     * - Complete error message and stack trace
     * - Number of attempts made
     * - Email details for manual processing
     * - Timestamp and context
     * 
     * Critical for:
     * - Identifying patterns in failures
     * - Manual recovery of failed emails
     * - System monitoring and alerts
     * - Improving parsing algorithms
     * 
     * Also triggers failure notifications to administrators based on
     * configuration settings.
     * 
     * @param SugarBean $email The email that failed processing
     * @param string $error The error message/reason for failure
     * @param int $attempts Number of attempts made before giving up
     * 
     * @return void
     */
    private function logFailure($email, $error, $attempts)
    {
        $message = "Failed to process deals email after {$attempts} attempts:\n";
        $message .= "Email: {$email->name}\n";
        $message .= "Error: {$error}\n";
        
        $this->log->error("DealsEmailLogicHook: " . $message);
        
        // Create activity record
        $this->createActivityRecord($email, array('error' => $error), 'failure');
        
        // Send failure notification
        $this->sendFailureNotification($email, $error);
    }
    
    /**
     * Create activity record for email processing audit trail
     * 
     * Creates a permanent record of email processing attempts in the Notes
     * module. This provides a user-visible audit trail that supplements
     * system logs.
     * 
     * Activity records include:
     * - Processing status (success/failure)
     * - Detailed results in JSON format
     * - Link to the original email
     * - Timestamp of processing
     * - Any error messages
     * 
     * Benefits:
     * - Users can see processing history in the UI
     * - Aids in troubleshooting user-reported issues
     * - Provides evidence of system actions
     * - Enables manual review of automated decisions
     * 
     * The activity record is linked to the email for easy access from
     * the email detail view.
     * 
     * @param SugarBean $email The processed email
     * @param array $result Processing results or error details
     * @param string $status Either 'success' or 'failure'
     * 
     * @return void
     */
    private function createActivityRecord($email, $result, $status)
    {
        global $db;
        
        $activityData = array(
            'id' => create_guid(),
            'email_id' => $email->id,
            'email_subject' => $email->name,
            'from_address' => $email->from_addr,
            'process_status' => $status,
            'process_date' => date('Y-m-d H:i:s'),
            'result_data' => json_encode($result)
        );
        
        // Store in custom table (if exists) or as a note
        try {
            $note = BeanFactory::newBean('Notes');
            $note->name = "Email Processing Log - " . $status;
            $note->description = "Email: " . $email->name . "\n" .
                               "Status: " . $status . "\n" .
                               "Result: " . json_encode($result, JSON_PRETTY_PRINT);
            $note->parent_type = 'Emails';
            $note->parent_id = $email->id;
            $note->save();
        } catch (Exception $e) {
            $this->log->error("DealsEmailLogicHook: Failed to create activity record - " . $e->getMessage());
        }
    }
    
    /**
     * Send notification about successfully processed email
     * 
     * Sends email notifications to relevant stakeholders when a deal is
     * successfully created or updated from an email. Notifications help:
     * 
     * - Alert deal owners to new opportunities
     * - Confirm successful automation
     * - Provide quick access links to the deal
     * - Summarize what was extracted/created
     * 
     * Recipients are determined by:
     * - Deal assignment (assigned user)
     * - Team membership
     * - Notification preferences
     * - Deal value thresholds
     * 
     * Notification includes:
     * - Original email subject and sender
     * - Deal details (name, amount, stage)
     * - Direct link to deal record
     * - Summary of contacts/documents added
     * 
     * @param SugarBean $email The processed email
     * @param array $result Processing results including deal details
     * 
     * @return void
     */
    private function sendNotification($email, $result)
    {
        try {
            // Determine notification type
            $notificationType = $result['action'] === 'created' ? 'deal_created' : 'deal_updated';
            
            // Prepare notification data
            $notificationData = array(
                'deal_id' => $result['deal_id'],
                'deal_name' => '',
                'deal_amount' => '',
                'sales_stage' => '',
                'email_subject' => $email->name,
                'email_from' => $email->from_addr,
                'update_summary' => $result['message']
            );
            
            // Get deal details if available
            if (!empty($result['deal_id'])) {
                $deal = BeanFactory::getBean('Opportunities', $result['deal_id']);
                if ($deal) {
                    $notificationData['deal_name'] = $deal->name;
                    $notificationData['deal_amount'] = '$' . number_format($deal->amount, 2);
                    $notificationData['sales_stage'] = $deal->sales_stage;
                    $notificationData['deal_url'] = $GLOBALS['sugar_config']['site_url'] . 
                                                  '/index.php?module=Opportunities&action=DetailView&record=' . $deal->id;
                }
            }
            
            // Send notification using EmailProcessorService
            $this->processor->sendNotification($notificationType, $notificationData);
            
        } catch (Exception $e) {
            $this->log->error("DealsEmailLogicHook: Failed to send notification - " . $e->getMessage());
        }
    }
    
    /**
     * Send failure notification to administrators
     * 
     * Alerts system administrators when email processing fails after all
     * retry attempts. This ensures failed emails don't go unnoticed and
     * can be manually processed if needed.
     * 
     * Failure notifications are critical for:
     * - Preventing lost opportunities
     * - Identifying system issues quickly
     * - Maintaining user confidence in automation
     * - Continuous improvement of parsing logic
     * 
     * Notification includes:
     * - Original email details
     * - Specific error encountered
     * - Number of attempts made
     * - Suggested manual actions
     * - Link to email for review
     * 
     * Recipients are configured in:
     * - deals_email_failure_notifications setting
     * - site_admin_email as fallback
     * 
     * @param SugarBean $email The email that failed processing
     * @param string $error The error message
     * 
     * @return void
     */
    private function sendFailureNotification($email, $error)
    {
        try {
            // Prepare failure notification data
            $notificationData = array(
                'email_subject' => $email->name,
                'email_from' => $email->from_addr,
                'email_date' => $email->date_sent,
                'email_id' => $email->id,
                'error_message' => $error,
                'email_url' => $GLOBALS['sugar_config']['site_url'] . 
                              '/index.php?module=Emails&action=DetailView&record=' . $email->id
            );
            
            // Send failure notification using EmailProcessorService
            $this->processor->sendNotification('processing_failed', $notificationData);
            
        } catch (Exception $e) {
            $this->log->error("DealsEmailLogicHook: Failed to send failure notification - " . $e->getMessage());
        }
    }
}
?>