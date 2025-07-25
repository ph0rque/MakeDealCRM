<?php
/**
 * Email Logic Hook for Deals Module
 * Automatically processes emails forwarded to deals@mycrm
 * 
 * @package MakeDealCRM
 * @subpackage Deals
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once('custom/modules/Deals/EmailProcessor.php');

class DealsEmailLogicHook
{
    private $processor;
    private $log;
    private $retryAttempts = 3;
    private $retryDelay = 5; // seconds
    
    public function __construct()
    {
        global $log;
        $this->log = $log;
    }
    
    /**
     * Process email after save
     * Called by after_save logic hook
     * 
     * @param SugarBean $bean The email bean
     * @param string $event The event type
     * @param array $arguments Hook arguments
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
     * Check if email should be processed
     */
    private function isDealsEmail($email)
    {
        // Check all address fields
        $allAddresses = $email->to_addrs . ' ' . $email->cc_addrs . ' ' . $email->bcc_addrs;
        
        // Look for deals@mycrm
        return (stripos($allAddresses, 'deals@mycrm') !== false);
    }
    
    /**
     * Process email with retry logic
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
                    $this->processor = new DealsEmailProcessor();
                }
                
                // Process email
                $result = $this->processor->processEmail($email);
                
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
     * Log successful processing
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
     * Log processing failure
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
     * Create activity record for tracking
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
     * Send notification about processed email
     */
    private function sendNotification($email, $result)
    {
        global $sugar_config;
        
        // Check if notifications are enabled
        if (empty($sugar_config['deals_email_notifications'])) {
            return;
        }
        
        try {
            // Get notification recipients
            $recipients = $this->getNotificationRecipients($result);
            
            if (empty($recipients)) {
                return;
            }
            
            // Create notification email
            $notification = BeanFactory::newBean('Emails');
            $notification->name = "Deal Created/Updated from Email: " . $email->name;
            $notification->type = 'out';
            $notification->status = 'sent';
            $notification->intent = 'pick';
            
            // Build email body
            $body = $this->buildNotificationBody($email, $result);
            $notification->description = $body;
            $notification->description_html = nl2br($body);
            
            // Set recipients
            $notification->from_addr = $sugar_config['notify_fromaddress'];
            $notification->from_name = $sugar_config['notify_fromname'];
            $notification->to_addrs = implode('; ', $recipients);
            
            // Save and send
            $notification->save();
            
        } catch (Exception $e) {
            $this->log->error("DealsEmailLogicHook: Failed to send notification - " . $e->getMessage());
        }
    }
    
    /**
     * Send failure notification
     */
    private function sendFailureNotification($email, $error)
    {
        global $sugar_config;
        
        // Check if failure notifications are enabled
        if (empty($sugar_config['deals_email_failure_notifications'])) {
            return;
        }
        
        try {
            // Send to system administrators
            $adminEmail = $sugar_config['site_admin_email'] ?? $sugar_config['notify_fromaddress'];
            
            if (empty($adminEmail)) {
                return;
            }
            
            $notification = BeanFactory::newBean('Emails');
            $notification->name = "Failed to Process Deals Email: " . $email->name;
            $notification->type = 'out';
            $notification->status = 'sent';
            
            $body = "Failed to process deals email after multiple attempts.\n\n";
            $body .= "Email Subject: " . $email->name . "\n";
            $body .= "From: " . $email->from_addr . "\n";
            $body .= "Date: " . $email->date_sent . "\n";
            $body .= "Error: " . $error . "\n\n";
            $body .= "Please check the email and process manually if needed.";
            
            $notification->description = $body;
            $notification->description_html = nl2br($body);
            $notification->from_addr = $sugar_config['notify_fromaddress'];
            $notification->from_name = 'Deals Email Processor';
            $notification->to_addrs = $adminEmail;
            
            $notification->save();
            
        } catch (Exception $e) {
            $this->log->error("DealsEmailLogicHook: Failed to send failure notification - " . $e->getMessage());
        }
    }
    
    /**
     * Get notification recipients based on result
     */
    private function getNotificationRecipients($result)
    {
        $recipients = array();
        
        if (!empty($result['deal_id'])) {
            // Get deal assigned user
            $deal = BeanFactory::getBean('Opportunities', $result['deal_id']);
            if ($deal && !empty($deal->assigned_user_id)) {
                $user = BeanFactory::getBean('Users', $deal->assigned_user_id);
                if ($user && !empty($user->email1)) {
                    $recipients[] = $user->email1;
                }
            }
        }
        
        return array_unique($recipients);
    }
    
    /**
     * Build notification email body
     */
    private function buildNotificationBody($email, $result)
    {
        $body = "A deal has been " . $result['action'] . " from an email.\n\n";
        
        $body .= "Original Email:\n";
        $body .= "Subject: " . $email->name . "\n";
        $body .= "From: " . $email->from_addr . "\n";
        $body .= "Date: " . $email->date_sent . "\n\n";
        
        $body .= "Deal Information:\n";
        if (!empty($result['deal_id'])) {
            $deal = BeanFactory::getBean('Opportunities', $result['deal_id']);
            if ($deal) {
                $body .= "Name: " . $deal->name . "\n";
                $body .= "Amount: $" . number_format($deal->amount, 2) . "\n";
                $body .= "Stage: " . $deal->sales_stage . "\n";
                $body .= "Link: " . $GLOBALS['sugar_config']['site_url'] . 
                        "/index.php?module=Opportunities&action=DetailView&record=" . $deal->id . "\n";
            }
        }
        
        $body .= "\nProcessing Results:\n";
        if (!empty($result['contacts_created'])) {
            $body .= "New contacts created: " . $result['contacts_created'] . "\n";
        }
        if (!empty($result['contacts_linked'])) {
            $body .= "Contacts linked: " . $result['contacts_linked'] . "\n";
        }
        if (!empty($result['attachments_linked'])) {
            $body .= "Attachments linked: " . $result['attachments_linked'] . "\n";
        }
        
        return $body;
    }
}
?>