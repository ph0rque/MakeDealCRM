<?php
/**
 * Email Processor Service for Deals Module
 * 
 * Centralized service for all email processing operations including:
 * - Parsing incoming emails to create/update deals
 * - Thread tracking and conversation management
 * - Sending automated email notifications
 * - Template management and parsing
 * - Contact extraction and management
 * - Attachment handling
 * 
 * @package MakeDealCRM
 * @subpackage Deals
 * @author MakeDealCRM Team
 * @version 1.0.0
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once 'include/SugarEmailAddress/SugarEmailAddress.php';
require_once 'include/OutboundEmail/OutboundEmail.php';
require_once 'modules/EmailTemplates/EmailTemplate.php';
require_once 'modules/Contacts/Contact.php';
require_once 'modules/Notes/Note.php';

class EmailProcessorService
{
    /**
     * @var Database connection
     */
    private $db;
    
    /**
     * @var Logger instance
     */
    private $log;
    
    /**
     * @var Current user
     */
    private $currentUser;
    
    /**
     * @var Email configuration
     */
    private $config;
    
    /**
     * @var Email patterns for parsing
     */
    private $patterns;
    
    /**
     * @var Thread cache for performance
     */
    private $threadCache = array();
    
    /**
     * @var Template cache
     */
    private $templateCache = array();
    
    /**
     * @var Singleton instance
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     * 
     * @return EmailProcessorService
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct()
    {
        global $db, $log, $current_user;
        $this->db = $db;
        $this->log = $log;
        $this->currentUser = $current_user;
        
        // Load configuration
        $this->loadConfiguration();
        
        // Initialize patterns
        $this->initializePatterns();
        
        // Ensure required tables exist
        $this->ensureTablesExist();
    }
    
    /**
     * Load email configuration
     */
    private function loadConfiguration()
    {
        // Include configuration file
        if (file_exists('custom/modules/Deals/config/email_config.php')) {
            require_once 'custom/modules/Deals/config/email_config.php';
        }
        
        $this->config = $GLOBALS['deals_email_config'] ?? array(
            'monitor_address' => 'deals@mycrm',
            'processing' => array(
                'enabled' => true,
                'retry_attempts' => 3,
                'retry_delay' => 5,
                'max_email_age' => 30,
                'batch_size' => 50,
            ),
            'duplicate_detection' => array(
                'enabled' => true,
                'similarity_threshold' => 0.7,
                'check_window' => 7,
                'check_fields' => array('name', 'account_name', 'amount'),
            ),
            'contact_extraction' => array(
                'enabled' => true,
                'extract_from_signature' => true,
                'extract_from_body' => true,
                'auto_assign_roles' => true,
            ),
            'notifications' => array(
                'on_success' => true,
                'on_failure' => true,
                'notify_assigned_user' => true,
                'notify_admin_on_failure' => true,
            ),
        );
    }
    
    /**
     * Initialize regex patterns for parsing
     */
    private function initializePatterns()
    {
        $this->patterns = array(
            'email' => array(
                'address' => '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
                'phone' => '/(\+?1?[-.\s]?)?\(?([0-9]{3})\)?[-.\s]?([0-9]{3})[-.\s]?([0-9]{4})/',
                'name' => '/(?:From|To|CC|Sender):\s*"?([^<"\n]+)"?\s*<?[^>]*>?/i',
                'signature' => '/(?:Best regards|Sincerely|Thanks|Regards|Best),?\s*\n+([^\n]+)(?:\n|$)/i'
            ),
            'deal' => array(
                'amount' => '/\$\s?([0-9]{1,3}(?:,?[0-9]{3})*(?:\.[0-9]{2})?)[MmKk]?/i',
                'company' => '/(?:Company|Business|Firm|Corporation):\s*([^\n]+)/i',
                'industry' => '/(?:Industry|Sector|Market):\s*([^\n]+)/i',
                'revenue' => '/(?:Revenue|Sales|Income):\s*\$?\s?([0-9,]+(?:\.[0-9]{2})?)[MmKk]?/i',
                'ebitda' => '/(?:EBITDA|Earnings):\s*\$?\s?([0-9,]+(?:\.[0-9]{2})?)[MmKk]?/i',
                'asking_price' => '/(?:Asking Price|Price|Valuation):\s*\$?\s?([0-9,]+(?:\.[0-9]{2})?)[MmKk]?/i'
            ),
            'contact_roles' => array(
                'seller' => '/(?:Seller|Owner|Proprietor):\s*([^\n]+)/i',
                'broker' => '/(?:Broker|Agent|Representative):\s*([^\n]+)/i',
                'attorney' => '/(?:Attorney|Lawyer|Counsel):\s*([^\n]+)/i',
                'accountant' => '/(?:Accountant|CPA|CFO):\s*([^\n]+)/i',
                'buyer' => '/(?:Buyer|Purchaser|Investor):\s*([^\n]+)/i'
            )
        );
    }
    
    /**
     * Ensure required database tables exist
     */
    private function ensureTablesExist()
    {
        // Email thread tracking table
        $threadTableQuery = "CREATE TABLE IF NOT EXISTS email_thread_deals (
            id CHAR(36) NOT NULL PRIMARY KEY,
            thread_id VARCHAR(255) NOT NULL,
            message_id VARCHAR(255),
            email_id CHAR(36) NOT NULL,
            deal_id CHAR(36) NOT NULL,
            subject VARCHAR(255),
            from_addr VARCHAR(255),
            date_sent DATETIME,
            in_reply_to VARCHAR(255),
            references TEXT,
            date_entered DATETIME,
            date_modified DATETIME,
            deleted TINYINT(1) DEFAULT 0,
            KEY idx_thread_id (thread_id),
            KEY idx_message_id (message_id),
            KEY idx_email_id (email_id),
            KEY idx_deal_id (deal_id),
            KEY idx_date_sent (date_sent)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        
        $this->db->query($threadTableQuery);
        
        // Email processing log table
        $logTableQuery = "CREATE TABLE IF NOT EXISTS email_processing_log (
            id CHAR(36) NOT NULL PRIMARY KEY,
            email_id CHAR(36) NOT NULL,
            deal_id CHAR(36),
            status VARCHAR(50),
            action VARCHAR(50),
            message TEXT,
            contacts_created INT DEFAULT 0,
            contacts_linked INT DEFAULT 0,
            attachments_linked INT DEFAULT 0,
            error_message TEXT,
            processing_time FLOAT,
            date_processed DATETIME,
            date_entered DATETIME,
            deleted TINYINT(1) DEFAULT 0,
            KEY idx_email_id (email_id),
            KEY idx_deal_id (deal_id),
            KEY idx_status (status),
            KEY idx_date_processed (date_processed)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        
        $this->db->query($logTableQuery);
    }
    
    /**
     * Process an incoming email
     * 
     * @param SugarBean $email The email bean
     * @return array Processing result
     */
    public function processIncomingEmail($email)
    {
        $startTime = microtime(true);
        $result = array(
            'success' => false,
            'deal_id' => null,
            'action' => null,
            'message' => '',
            'contacts_created' => 0,
            'contacts_linked' => 0,
            'attachments_linked' => 0,
            'processing_time' => 0
        );
        
        try {
            // Check if email should be processed
            if (!$this->shouldProcessEmail($email)) {
                $result['message'] = 'Email does not meet processing criteria';
                return $result;
            }
            
            // Parse email content
            $parsedData = $this->parseEmail($email);
            
            if (!$parsedData['success']) {
                $result['message'] = 'Failed to parse email';
                $result['errors'] = $parsedData['errors'];
                return $result;
            }
            
            // Check for existing thread
            $threadInfo = $this->getThreadInfo($email);
            
            // Handle duplicate detection
            if ($parsedData['is_duplicate'] && !$threadInfo) {
                $result = $this->handleDuplicateDeal($parsedData, $email);
            }
            // Create or update deal
            else {
                if ($threadInfo && !empty($threadInfo['deal_id'])) {
                    // Update existing deal from thread
                    $result = $this->updateDealFromEmail($threadInfo['deal_id'], $parsedData, $email);
                } else {
                    // Create new deal
                    $result = $this->createDealFromEmail($parsedData, $email);
                }
            }
            
            // Process contacts and attachments
            if ($result['success'] && !empty($result['deal_id'])) {
                // Process contacts
                $contactsResult = $this->processContacts($parsedData['contacts'], $result['deal_id']);
                $result['contacts_created'] = $contactsResult['created'];
                $result['contacts_linked'] = $contactsResult['linked'];
                
                // Process attachments
                $attachmentsResult = $this->processAttachments($parsedData['attachments'], $result['deal_id'], $email->id);
                $result['attachments_linked'] = $attachmentsResult['linked'];
                
                // Update thread tracking
                $this->trackEmailThread($email, $result['deal_id']);
                
                // Link email to deal
                $this->linkEmailToDeal($email->id, $result['deal_id']);
            }
            
        } catch (Exception $e) {
            $this->log->error("EmailProcessorService: Error processing email - " . $e->getMessage());
            $result['message'] = 'Error processing email: ' . $e->getMessage();
            $result['error_message'] = $e->getMessage();
        }
        
        // Calculate processing time
        $result['processing_time'] = microtime(true) - $startTime;
        
        // Log processing result
        $this->logProcessingResult($email, $result);
        
        return $result;
    }
    
    /**
     * Send file request email
     * 
     * @param array $requestData File request data
     * @param SugarBean $deal Deal bean
     * @param string $templateType Template type to use
     * @return array Send result
     */
    public function sendFileRequestEmail($requestData, $deal, $templateType = 'general')
    {
        $result = array(
            'success' => false,
            'message' => '',
            'email_id' => null
        );
        
        try {
            // Get email template
            $template = $this->getEmailTemplate($templateType);
            
            // Prepare template variables
            $variables = $this->prepareTemplateVariables($requestData, $deal);
            
            // Parse template
            $emailContent = $this->parseTemplate($template, $variables);
            
            // Send email
            $sendResult = $this->sendEmail(
                $requestData['recipient_email'],
                $requestData['recipient_name'],
                $emailContent['subject'],
                $emailContent['body_html'],
                $emailContent['body_text']
            );
            
            if ($sendResult['success']) {
                $result['success'] = true;
                $result['message'] = 'Email sent successfully';
                $result['email_id'] = $sendResult['email_id'];
                
                // Log email sent
                $this->logEmailSent(
                    $requestData['id'],
                    $requestData['recipient_email'],
                    $templateType
                );
            } else {
                $result['message'] = $sendResult['message'];
            }
            
        } catch (Exception $e) {
            $this->log->error("EmailProcessorService: Error sending file request email - " . $e->getMessage());
            $result['message'] = 'Error sending email: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Send notification email
     * 
     * @param string $type Notification type
     * @param array $data Notification data
     * @return array Send result
     */
    public function sendNotification($type, $data)
    {
        $result = array(
            'success' => false,
            'message' => ''
        );
        
        try {
            // Determine recipients based on notification type
            $recipients = $this->getNotificationRecipients($type, $data);
            
            if (empty($recipients)) {
                $result['message'] = 'No recipients configured for notification type: ' . $type;
                return $result;
            }
            
            // Get notification template
            $template = $this->getNotificationTemplate($type);
            
            // Parse template with data
            $emailContent = $this->parseTemplate($template, $data);
            
            // Send to each recipient
            $sentCount = 0;
            foreach ($recipients as $recipient) {
                $sendResult = $this->sendEmail(
                    $recipient['email'],
                    $recipient['name'],
                    $emailContent['subject'],
                    $emailContent['body_html'],
                    $emailContent['body_text']
                );
                
                if ($sendResult['success']) {
                    $sentCount++;
                }
            }
            
            if ($sentCount > 0) {
                $result['success'] = true;
                $result['message'] = "Notification sent to {$sentCount} recipient(s)";
            } else {
                $result['message'] = 'Failed to send notification to any recipients';
            }
            
        } catch (Exception $e) {
            $this->log->error("EmailProcessorService: Error sending notification - " . $e->getMessage());
            $result['message'] = 'Error sending notification: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Parse an email and extract deal information
     * 
     * @param SugarBean $email Email to parse
     * @return array Parsed data
     */
    private function parseEmail($email)
    {
        $result = array(
            'success' => false,
            'deal_data' => array(),
            'contacts' => array(),
            'attachments' => array(),
            'is_duplicate' => false,
            'duplicate_id' => null,
            'errors' => array()
        );
        
        try {
            // Extract email content
            $emailData = $this->extractEmailData($email);
            $body = $this->getEmailBody($emailData);
            
            // Extract deal information
            $dealData = $this->extractDealData($emailData['name'], $body);
            
            // Extract contacts
            $contacts = $this->extractContacts($body, $emailData['from_addr'], $emailData['to_addrs']);
            
            // Process attachments
            $attachments = $this->extractAttachments($email->id);
            
            // Check for duplicates
            $duplicate = $this->checkForDuplicateDeal($dealData);
            
            // Prepare result
            $result['success'] = true;
            $result['deal_data'] = $dealData;
            $result['contacts'] = $contacts;
            $result['attachments'] = $attachments;
            $result['is_duplicate'] = $duplicate !== false;
            $result['duplicate_id'] = $duplicate;
            
        } catch (Exception $e) {
            $result['errors'][] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Extract email data into structured array
     */
    private function extractEmailData($email)
    {
        return array(
            'id' => $email->id,
            'name' => $email->name,
            'description' => $email->description,
            'description_html' => $email->description_html,
            'from_addr' => $email->from_addr,
            'to_addrs' => $email->to_addrs,
            'cc_addrs' => $email->cc_addrs,
            'date_sent' => $email->date_sent,
            'message_id' => $email->message_id,
            'reply_to_addr' => $email->reply_to_addr
        );
    }
    
    /**
     * Get email body from different formats
     */
    private function getEmailBody($emailData)
    {
        $body = '';
        
        // Try HTML body first
        if (!empty($emailData['description_html'])) {
            $body = html_entity_decode(strip_tags($emailData['description_html']));
        }
        // Fall back to plain text
        elseif (!empty($emailData['description'])) {
            $body = $emailData['description'];
        }
        
        return $body;
    }
    
    /**
     * Extract deal data from email content
     */
    private function extractDealData($subject, $body)
    {
        $dealData = array(
            'name' => $this->cleanSubject($subject),
            'pipeline_stage_c' => 'sourcing',
            'sales_stage' => 'Prospecting',
            'probability' => 10,
            'deal_source_c' => 'Email',
            'lead_source' => 'Email',
            'date_entered' => date('Y-m-d H:i:s'),
            'date_closed' => date('Y-m-d', strtotime('+90 days'))
        );
        
        // Combine subject and body for parsing
        $content = $subject . "\n" . $body;
        
        // Extract amounts
        if (preg_match($this->patterns['deal']['asking_price'], $content, $matches)) {
            $dealData['amount'] = $this->parseAmount($matches[1]);
        } elseif (preg_match($this->patterns['deal']['amount'], $content, $matches)) {
            $dealData['amount'] = $this->parseAmount($matches[1]);
        }
        
        // Extract company name
        if (preg_match($this->patterns['deal']['company'], $content, $matches)) {
            $dealData['account_name'] = trim($matches[1]);
        }
        
        // Extract industry
        if (preg_match($this->patterns['deal']['industry'], $content, $matches)) {
            $dealData['industry'] = $this->normalizeIndustry(trim($matches[1]));
        }
        
        // Extract financial metrics
        if (preg_match($this->patterns['deal']['revenue'], $content, $matches)) {
            $dealData['annual_revenue_c'] = $this->parseAmount($matches[1]);
        }
        
        if (preg_match($this->patterns['deal']['ebitda'], $content, $matches)) {
            $dealData['ebitda_c'] = $this->parseAmount($matches[1]);
        }
        
        // Set description
        $dealData['description'] = "Deal created from email: " . $subject . "\n\n" . 
                                  substr($body, 0, 1000) . (strlen($body) > 1000 ? '...' : '');
        
        return $dealData;
    }
    
    /**
     * Extract contacts from email content
     */
    private function extractContacts($body, $fromAddress, $toAddresses)
    {
        $contacts = array();
        
        if (!$this->config['contact_extraction']['enabled']) {
            return $contacts;
        }
        
        // Extract from sender
        if (!empty($fromAddress)) {
            $senderContact = $this->parseEmailAddress($fromAddress);
            if ($senderContact) {
                $senderContact['source'] = 'sender';
                $contacts[] = $senderContact;
            }
        }
        
        // Extract from email body using role patterns
        if ($this->config['contact_extraction']['extract_from_body']) {
            foreach ($this->patterns['contact_roles'] as $role => $pattern) {
                if (preg_match($pattern, $body, $matches)) {
                    $contactInfo = $this->parseContactInfo($matches[1]);
                    if ($contactInfo) {
                        $contactInfo['role'] = $role;
                        $contactInfo['source'] = 'body';
                        $contacts[] = $contactInfo;
                    }
                }
            }
        }
        
        // Extract from email signature
        if ($this->config['contact_extraction']['extract_from_signature']) {
            if (preg_match($this->patterns['email']['signature'], $body, $matches)) {
                $signatureContact = $this->parseSignature($matches[0]);
                if ($signatureContact) {
                    $signatureContact['source'] = 'signature';
                    $contacts[] = $signatureContact;
                }
            }
        }
        
        // Find all email addresses in body
        if (preg_match_all($this->patterns['email']['address'], $body, $matches)) {
            foreach ($matches[0] as $emailAddr) {
                $existingContact = false;
                foreach ($contacts as $contact) {
                    if (isset($contact['email']) && $contact['email'] == $emailAddr) {
                        $existingContact = true;
                        break;
                    }
                }
                if (!$existingContact) {
                    $contacts[] = array(
                        'email' => $emailAddr,
                        'source' => 'body_scan'
                    );
                }
            }
        }
        
        return $this->deduplicateContacts($contacts);
    }
    
    /**
     * Parse email address into name and email
     */
    private function parseEmailAddress($emailString)
    {
        $contact = array();
        
        // Pattern: "Name" <email@domain.com>
        if (preg_match('/"?([^"<]+)"?\s*<([^>]+)>/', $emailString, $matches)) {
            $contact['name'] = trim($matches[1]);
            $contact['email'] = trim($matches[2]);
        }
        // Pattern: email@domain.com
        elseif (filter_var($emailString, FILTER_VALIDATE_EMAIL)) {
            $contact['email'] = $emailString;
        }
        
        // Split name into first and last
        if (!empty($contact['name'])) {
            $nameParts = explode(' ', $contact['name']);
            $contact['first_name'] = array_shift($nameParts);
            $contact['last_name'] = implode(' ', $nameParts);
        }
        
        return $contact;
    }
    
    /**
     * Parse contact information from text
     */
    private function parseContactInfo($text)
    {
        $contact = array();
        
        // Extract email
        if (preg_match($this->patterns['email']['address'], $text, $matches)) {
            $contact['email'] = $matches[0];
        }
        
        // Extract phone
        if (preg_match($this->patterns['email']['phone'], $text, $matches)) {
            $contact['phone'] = $this->formatPhoneNumber($matches[0]);
        }
        
        // Extract name (remove email and phone from text first)
        $nameText = $text;
        if (!empty($contact['email'])) {
            $nameText = str_replace($contact['email'], '', $nameText);
        }
        if (!empty($contact['phone'])) {
            $nameText = str_replace($matches[0], '', $nameText);
        }
        
        $nameText = trim(strip_tags($nameText));
        if (!empty($nameText)) {
            $nameParts = preg_split('/\s+/', $nameText);
            if (count($nameParts) > 0) {
                $contact['first_name'] = array_shift($nameParts);
                $contact['last_name'] = implode(' ', $nameParts);
            }
        }
        
        return empty($contact) ? null : $contact;
    }
    
    /**
     * Parse email signature
     */
    private function parseSignature($signatureText)
    {
        $contact = array();
        
        $lines = explode("\n", $signatureText);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines
            if (empty($line)) continue;
            
            // Extract email
            if (preg_match($this->patterns['email']['address'], $line, $matches)) {
                $contact['email'] = $matches[0];
            }
            
            // Extract phone
            if (preg_match($this->patterns['email']['phone'], $line, $matches)) {
                $contact['phone'] = $this->formatPhoneNumber($matches[0]);
            }
            
            // First non-email, non-phone line might be name
            if (empty($contact['name']) && 
                !isset($contact['email']) && 
                !isset($contact['phone']) &&
                !preg_match('/^(Best|Regards|Sincerely|Thanks)/i', $line)) {
                $contact['name'] = $line;
            }
        }
        
        // Parse name
        if (!empty($contact['name'])) {
            $nameParts = preg_split('/\s+/', $contact['name']);
            $contact['first_name'] = array_shift($nameParts);
            $contact['last_name'] = implode(' ', $nameParts);
            unset($contact['name']);
        }
        
        return empty($contact) ? null : $contact;
    }
    
    /**
     * Extract attachments from email
     */
    private function extractAttachments($emailId)
    {
        $attachments = array();
        
        if (empty($emailId)) {
            return $attachments;
        }
        
        // Query for attachments
        $query = "SELECT n.id, n.name, n.filename, n.file_mime_type 
                  FROM notes n
                  JOIN emails_beans eb ON n.id = eb.bean_id
                  WHERE eb.email_id = '{$emailId}' 
                  AND eb.bean_module = 'Notes'
                  AND n.deleted = 0
                  AND eb.deleted = 0";
        
        $result = $this->db->query($query);
        
        while ($row = $this->db->fetchByAssoc($result)) {
            $attachments[] = array(
                'id' => $row['id'],
                'name' => $row['name'],
                'filename' => $row['filename'],
                'mime_type' => $row['file_mime_type']
            );
        }
        
        return $attachments;
    }
    
    /**
     * Check for duplicate deals
     */
    private function checkForDuplicateDeal($dealData)
    {
        if (!$this->config['duplicate_detection']['enabled']) {
            return false;
        }
        
        if (empty($dealData['name']) && empty($dealData['account_name'])) {
            return false;
        }
        
        $conditions = array();
        
        // Exact name match
        if (!empty($dealData['name'])) {
            $conditions[] = "o.name = '" . $this->db->quote($dealData['name']) . "'";
        }
        
        // Account name match
        if (!empty($dealData['account_name'])) {
            $conditions[] = "a.name = '" . $this->db->quote($dealData['account_name']) . "'";
        }
        
        // Similar amount (within 10%)
        if (!empty($dealData['amount'])) {
            $minAmount = $dealData['amount'] * 0.9;
            $maxAmount = $dealData['amount'] * 1.1;
            $conditions[] = "(o.amount BETWEEN $minAmount AND $maxAmount)";
        }
        
        if (empty($conditions)) {
            return false;
        }
        
        $checkWindow = $this->config['duplicate_detection']['check_window'] ?? 7;
        $dateLimit = date('Y-m-d H:i:s', strtotime("-{$checkWindow} days"));
        
        $query = "SELECT o.id, o.name, o.amount, a.name as account_name,
                         o.date_entered, o.sales_stage
                  FROM opportunities o
                  LEFT JOIN accounts a ON o.account_id = a.id
                  WHERE o.deleted = 0
                  AND o.date_entered >= '{$dateLimit}'
                  AND (" . implode(' OR ', $conditions) . ")
                  ORDER BY o.date_entered DESC
                  LIMIT 1";
        
        $result = $this->db->query($query);
        $row = $this->db->fetchByAssoc($result);
        
        if ($row) {
            // Calculate similarity score
            $score = $this->calculateSimilarityScore($dealData, $row);
            $threshold = $this->config['duplicate_detection']['similarity_threshold'] ?? 0.7;
            
            // If similarity is above threshold, consider it a duplicate
            if ($score > $threshold) {
                return $row['id'];
            }
        }
        
        return false;
    }
    
    /**
     * Calculate similarity score between two deals
     */
    private function calculateSimilarityScore($deal1, $deal2)
    {
        $score = 0;
        $factors = 0;
        
        // Name similarity
        if (!empty($deal1['name']) && !empty($deal2['name'])) {
            $nameSimilarity = $this->calculateStringSimilarity($deal1['name'], $deal2['name']);
            $score += $nameSimilarity * 0.3;
            $factors += 0.3;
        }
        
        // Account name similarity
        if (!empty($deal1['account_name']) && !empty($deal2['account_name'])) {
            $accountSimilarity = $this->calculateStringSimilarity($deal1['account_name'], $deal2['account_name']);
            $score += $accountSimilarity * 0.3;
            $factors += 0.3;
        }
        
        // Amount similarity
        if (!empty($deal1['amount']) && !empty($deal2['amount'])) {
            $amountDiff = abs($deal1['amount'] - $deal2['amount']) / max($deal1['amount'], $deal2['amount']);
            $amountSimilarity = 1 - $amountDiff;
            $score += $amountSimilarity * 0.2;
            $factors += 0.2;
        }
        
        // Date proximity (deals created within check window)
        if (!empty($deal2['date_entered'])) {
            $checkWindow = $this->config['duplicate_detection']['check_window'] ?? 7;
            $daysDiff = abs(time() - strtotime($deal2['date_entered'])) / (60 * 60 * 24);
            if ($daysDiff <= $checkWindow) {
                $dateSimilarity = 1 - ($daysDiff / $checkWindow);
                $score += $dateSimilarity * 0.2;
                $factors += 0.2;
            }
        }
        
        return $factors > 0 ? $score / $factors : 0;
    }
    
    /**
     * Calculate string similarity using Levenshtein distance
     */
    private function calculateStringSimilarity($str1, $str2)
    {
        $str1 = strtolower(trim($str1));
        $str2 = strtolower(trim($str2));
        
        if ($str1 === $str2) {
            return 1.0;
        }
        
        $maxLen = max(strlen($str1), strlen($str2));
        if ($maxLen === 0) {
            return 0.0;
        }
        
        $distance = levenshtein($str1, $str2);
        return 1 - ($distance / $maxLen);
    }
    
    /**
     * Create new deal from email
     */
    private function createDealFromEmail($parsedData, $email)
    {
        $result = array(
            'success' => false,
            'deal_id' => null,
            'action' => 'created'
        );
        
        try {
            $deal = BeanFactory::newBean('Opportunities');
            
            // Set deal data from parsed email
            foreach ($parsedData['deal_data'] as $field => $value) {
                if (property_exists($deal, $field) || isset($deal->field_defs[$field])) {
                    $deal->$field = $value;
                }
            }
            
            // Set assigned user
            if (empty($deal->assigned_user_id)) {
                $deal->assigned_user_id = $this->getAssignedUserId($email);
            }
            
            // Add email reference to description
            $deal->description .= "\n\nEmail ID: " . $email->id;
            
            // Save deal
            $deal->save();
            
            $result['success'] = true;
            $result['deal_id'] = $deal->id;
            $result['message'] = "Deal created: " . $deal->name;
            
            $this->log->info("EmailProcessorService: Created deal {$deal->id} from email {$email->id}");
            
        } catch (Exception $e) {
            $this->log->error("EmailProcessorService: Failed to create deal - " . $e->getMessage());
            $result['message'] = 'Failed to create deal: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Update existing deal from email
     */
    private function updateDealFromEmail($dealId, $parsedData, $email)
    {
        $result = array(
            'success' => false,
            'deal_id' => $dealId,
            'action' => 'updated'
        );
        
        try {
            $deal = BeanFactory::getBean('Opportunities', $dealId);
            
            if (empty($deal->id)) {
                throw new Exception("Deal not found: $dealId");
            }
            
            // Update deal with new information
            $updated = false;
            
            // Update amount if higher
            if (!empty($parsedData['deal_data']['amount']) && 
                $parsedData['deal_data']['amount'] > $deal->amount) {
                $deal->amount = $parsedData['deal_data']['amount'];
                $updated = true;
            }
            
            // Update financial metrics if provided
            foreach (['annual_revenue_c', 'ebitda_c'] as $field) {
                if (!empty($parsedData['deal_data'][$field]) && 
                    empty($deal->$field)) {
                    $deal->$field = $parsedData['deal_data'][$field];
                    $updated = true;
                }
            }
            
            // Append to description
            $deal->description .= "\n\n--- Update from email (" . date('Y-m-d H:i:s') . ") ---\n";
            $deal->description .= substr($email->description, 0, 500);
            $deal->description .= "\nEmail ID: " . $email->id;
            
            // Update pipeline notes
            if (!empty($deal->pipeline_notes_c)) {
                $deal->pipeline_notes_c .= "\n";
            }
            $deal->pipeline_notes_c .= "Email update: " . $email->name . " (" . date('Y-m-d') . ")";
            
            // Save if updated
            if ($updated || true) { // Always save to update description
                $deal->save();
                $result['success'] = true;
                $result['message'] = "Deal updated: " . $deal->name;
                
                $this->log->info("EmailProcessorService: Updated deal {$deal->id} from email {$email->id}");
            } else {
                $result['success'] = true;
                $result['message'] = "No updates needed for deal: " . $deal->name;
            }
            
        } catch (Exception $e) {
            $this->log->error("EmailProcessorService: Failed to update deal - " . $e->getMessage());
            $result['message'] = 'Failed to update deal: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Handle duplicate deal detection
     */
    private function handleDuplicateDeal($parsedData, $email)
    {
        $result = array(
            'success' => true,
            'deal_id' => $parsedData['duplicate_id'],
            'action' => 'duplicate_found'
        );
        
        // Update the existing deal with thread info
        $deal = BeanFactory::getBean('Opportunities', $parsedData['duplicate_id']);
        
        if ($deal && !empty($deal->id)) {
            // Add note about duplicate email
            $note = BeanFactory::newBean('Notes');
            $note->name = 'Duplicate email received';
            $note->description = "Duplicate email detected:\n" .
                               "Subject: " . $email->name . "\n" .
                               "From: " . $email->from_addr . "\n" .
                               "Date: " . $email->date_sent . "\n\n" .
                               "Email was not processed as it appears to be a duplicate.";
            $note->parent_type = 'Opportunities';
            $note->parent_id = $deal->id;
            $note->save();
            
            $result['message'] = "Duplicate detected - linked to existing deal: " . $deal->name;
        }
        
        return $result;
    }
    
    /**
     * Process contacts from parsed email
     */
    private function processContacts($contacts, $dealId)
    {
        $result = array(
            'created' => 0,
            'linked' => 0,
            'updated' => 0
        );
        
        if (empty($contacts) || empty($dealId)) {
            return $result;
        }
        
        $deal = BeanFactory::getBean('Opportunities', $dealId);
        if (empty($deal->id)) {
            return $result;
        }
        
        foreach ($contacts as $contactData) {
            try {
                $contact = $this->findOrCreateContact($contactData);
                
                if ($contact) {
                    // Link to deal
                    $deal->load_relationship('contacts');
                    $deal->contacts->add($contact->id);
                    $result['linked']++;
                    
                    // Set contact role if specified
                    if (!empty($contactData['role']) && $this->config['contact_extraction']['auto_assign_roles']) {
                        $this->setContactRole($contact->id, $contactData['role']);
                    }
                    
                    // Track if new or existing
                    if (!empty($contactData['_created'])) {
                        $result['created']++;
                    } else {
                        $result['updated']++;
                    }
                }
            } catch (Exception $e) {
                $this->log->error("EmailProcessorService: Failed to process contact - " . $e->getMessage());
            }
        }
        
        return $result;
    }
    
    /**
     * Find or create contact
     */
    private function findOrCreateContact($contactData)
    {
        // Try to find existing contact by email
        if (!empty($contactData['email'])) {
            $contact = $this->findContactByEmail($contactData['email']);
            if ($contact) {
                // Update contact if new info available
                $this->updateContactIfNeeded($contact, $contactData);
                return $contact;
            }
        }
        
        // Try to find by name if no email
        if (empty($contactData['email']) && 
            !empty($contactData['first_name']) && 
            !empty($contactData['last_name'])) {
            $contact = $this->findContactByName($contactData['first_name'], $contactData['last_name']);
            if ($contact) {
                $this->updateContactIfNeeded($contact, $contactData);
                return $contact;
            }
        }
        
        // Create new contact
        return $this->createContact($contactData);
    }
    
    /**
     * Find contact by email
     */
    private function findContactByEmail($email)
    {
        $sea = new SugarEmailAddress();
        $contacts = $sea->getBeansByEmailAddress($email);
        
        foreach ($contacts as $contact) {
            if ($contact->module_dir == 'Contacts' && !$contact->deleted) {
                return $contact;
            }
        }
        
        return null;
    }
    
    /**
     * Find contact by name
     */
    private function findContactByName($firstName, $lastName)
    {
        $query = "SELECT id FROM contacts 
                  WHERE first_name = '" . $this->db->quote($firstName) . "'
                  AND last_name = '" . $this->db->quote($lastName) . "'
                  AND deleted = 0
                  LIMIT 1";
        
        $result = $this->db->query($query);
        $row = $this->db->fetchByAssoc($result);
        
        if ($row) {
            return BeanFactory::getBean('Contacts', $row['id']);
        }
        
        return null;
    }
    
    /**
     * Create new contact
     */
    private function createContact($contactData)
    {
        $contact = BeanFactory::newBean('Contacts');
        
        // Set basic fields
        $contact->first_name = $contactData['first_name'] ?? '';
        $contact->last_name = $contactData['last_name'] ?? 'Unknown';
        $contact->phone_work = $contactData['phone'] ?? '';
        $contact->lead_source = 'Email';
        $contact->assigned_user_id = $this->currentUser->id;
        
        // Set email
        if (!empty($contactData['email'])) {
            $contact->email1 = $contactData['email'];
        }
        
        // Set role
        if (!empty($contactData['role'])) {
            $contact->contact_role_c = $contactData['role'];
        }
        
        // Set description
        $contact->description = "Contact extracted from email";
        if (!empty($contactData['source'])) {
            $contact->description .= " (source: " . $contactData['source'] . ")";
        }
        
        $contact->save();
        
        // Mark as created
        $contactData['_created'] = true;
        
        return $contact;
    }
    
    /**
     * Update contact if new information available
     */
    private function updateContactIfNeeded($contact, $contactData)
    {
        $updated = false;
        
        // Update phone if empty
        if (empty($contact->phone_work) && !empty($contactData['phone'])) {
            $contact->phone_work = $contactData['phone'];
            $updated = true;
        }
        
        // Update role if empty
        if (empty($contact->contact_role_c) && !empty($contactData['role'])) {
            $contact->contact_role_c = $contactData['role'];
            $updated = true;
        }
        
        // Update email if empty
        if (empty($contact->email1) && !empty($contactData['email'])) {
            $contact->email1 = $contactData['email'];
            $updated = true;
        }
        
        if ($updated) {
            $contact->save();
        }
        
        return $contact;
    }
    
    /**
     * Set contact role in custom table
     */
    private function setContactRole($contactId, $role)
    {
        if (class_exists('ContactRoleManager')) {
            ContactRoleManager::updateContactRole($contactId, $role);
        }
    }
    
    /**
     * Process attachments
     */
    private function processAttachments($attachments, $dealId, $emailId)
    {
        $result = array(
            'linked' => 0,
            'errors' => 0
        );
        
        if (empty($attachments) || empty($dealId)) {
            return $result;
        }
        
        foreach ($attachments as $attachment) {
            try {
                // Update note to link to deal
                $note = BeanFactory::getBean('Notes', $attachment['id']);
                if ($note && !empty($note->id)) {
                    $note->parent_type = 'Opportunities';
                    $note->parent_id = $dealId;
                    $note->save();
                    $result['linked']++;
                }
            } catch (Exception $e) {
                $this->log->error("EmailProcessorService: Failed to link attachment - " . $e->getMessage());
                $result['errors']++;
            }
        }
        
        return $result;
    }
    
    /**
     * Link email to deal
     */
    private function linkEmailToDeal($emailId, $dealId)
    {
        try {
            $deal = BeanFactory::getBean('Opportunities', $dealId);
            if ($deal && !empty($deal->id)) {
                $deal->load_relationship('emails');
                $deal->emails->add($emailId);
            }
        } catch (Exception $e) {
            $this->log->error("EmailProcessorService: Failed to link email to deal - " . $e->getMessage());
        }
    }
    
    /**
     * Get assigned user ID for deal
     */
    private function getAssignedUserId($email)
    {
        // Try to get from email assigned user
        if (!empty($email->assigned_user_id)) {
            return $email->assigned_user_id;
        }
        
        // Default to current user
        return $this->currentUser->id;
    }
    
    /**
     * Check if email should be processed
     */
    private function shouldProcessEmail($email)
    {
        // Check if processing is enabled
        if (empty($this->config['processing']['enabled'])) {
            return false;
        }
        
        // Check if email is sent to monitor address
        $monitorAddress = $this->config['monitor_address'] ?? 'deals@mycrm';
        $toAddresses = $email->to_addrs . ' ' . $email->cc_addrs . ' ' . $email->bcc_addrs;
        if (!preg_match('/' . preg_quote($monitorAddress, '/') . '/i', $toAddresses)) {
            return false;
        }
        
        // Check if email is already processed
        if ($this->isEmailProcessed($email->id)) {
            return false;
        }
        
        // Check if email is too old
        $maxAge = $this->config['processing']['max_email_age'] ?? 30;
        $emailDate = strtotime($email->date_sent ?? $email->date_entered);
        if ($emailDate < strtotime("-{$maxAge} days")) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if email has already been processed
     */
    private function isEmailProcessed($emailId)
    {
        $query = "SELECT COUNT(*) as count 
                  FROM emails_beans 
                  WHERE email_id = '" . $this->db->quote($emailId) . "'
                  AND bean_module = 'Opportunities'
                  AND deleted = 0";
        
        $result = $this->db->query($query);
        $row = $this->db->fetchByAssoc($result);
        
        return $row['count'] > 0;
    }
    
    /**
     * Get thread information for an email
     */
    private function getThreadInfo($email)
    {
        // Check cache first
        if (!empty($email->message_id) && isset($this->threadCache[$email->message_id])) {
            return $this->threadCache[$email->message_id];
        }
        
        $threadInfo = null;
        
        // Try to find thread by Message-ID references
        if (!empty($email->raw_source)) {
            $threadInfo = $this->findThreadByReferences($email);
        }
        
        // Try to find by In-Reply-To header
        if (!$threadInfo && !empty($email->reply_to_addr)) {
            $threadInfo = $this->findThreadByReplyTo($email);
        }
        
        // Try to find by subject similarity
        if (!$threadInfo) {
            $threadInfo = $this->findThreadBySubject($email);
        }
        
        // Cache result
        if (!empty($email->message_id)) {
            $this->threadCache[$email->message_id] = $threadInfo;
        }
        
        return $threadInfo;
    }
    
    /**
     * Find thread by email references
     */
    private function findThreadByReferences($email)
    {
        $headers = $this->extractEmailHeaders($email);
        
        if (empty($headers['references']) && empty($headers['in_reply_to'])) {
            return null;
        }
        
        // Parse references
        $references = $this->parseReferences($headers['references']);
        if (!empty($headers['in_reply_to'])) {
            $references[] = $headers['in_reply_to'];
        }
        
        if (empty($references)) {
            return null;
        }
        
        // Look for any of these message IDs in our tracking
        $quotedRefs = array_map(array($this->db, 'quote'), $references);
        $refList = "'" . implode("','", $quotedRefs) . "'";
        
        $query = "SELECT thread_id, deal_id 
                  FROM email_thread_deals 
                  WHERE message_id IN ({$refList})
                  AND deleted = 0
                  ORDER BY date_sent DESC
                  LIMIT 1";
        
        $result = $this->db->query($query);
        $row = $this->db->fetchByAssoc($result);
        
        if ($row) {
            return array(
                'thread_id' => $row['thread_id'],
                'deal_id' => $row['deal_id']
            );
        }
        
        return null;
    }
    
    /**
     * Find thread by In-Reply-To header
     */
    private function findThreadByReplyTo($email)
    {
        $headers = $this->extractEmailHeaders($email);
        
        if (empty($headers['in_reply_to'])) {
            return null;
        }
        
        $query = "SELECT thread_id, deal_id 
                  FROM email_thread_deals 
                  WHERE message_id = '" . $this->db->quote($headers['in_reply_to']) . "'
                  AND deleted = 0
                  LIMIT 1";
        
        $result = $this->db->query($query);
        $row = $this->db->fetchByAssoc($result);
        
        if ($row) {
            return array(
                'thread_id' => $row['thread_id'],
                'deal_id' => $row['deal_id']
            );
        }
        
        return null;
    }
    
    /**
     * Find thread by subject similarity
     */
    private function findThreadBySubject($email)
    {
        if (!$this->config['thread_tracking']['enabled']) {
            return null;
        }
        
        // Clean subject for comparison
        $cleanSubject = $this->cleanSubject($email->name);
        
        if (empty($cleanSubject)) {
            return null;
        }
        
        // Look for similar subjects in recent emails
        $checkWindow = $this->config['duplicate_detection']['check_window'] ?? 7;
        $dateLimit = date('Y-m-d H:i:s', strtotime("-{$checkWindow} days"));
        
        $query = "SELECT thread_id, deal_id, subject,
                         CASE 
                            WHEN subject = '" . $this->db->quote($email->name) . "' THEN 100
                            WHEN subject LIKE '%" . $this->db->quote($cleanSubject) . "%' THEN 80
                            ELSE 0
                         END as similarity_score
                  FROM email_thread_deals
                  WHERE date_sent >= '{$dateLimit}'
                  AND from_addr IN (
                      '" . $this->db->quote($email->from_addr) . "',
                      '" . $this->db->quote($email->to_addrs) . "'
                  )
                  AND deleted = 0
                  HAVING similarity_score > 70
                  ORDER BY similarity_score DESC, date_sent DESC
                  LIMIT 1";
        
        $result = $this->db->query($query);
        $row = $this->db->fetchByAssoc($result);
        
        if ($row) {
            return array(
                'thread_id' => $row['thread_id'],
                'deal_id' => $row['deal_id']
            );
        }
        
        return null;
    }
    
    /**
     * Track email in thread
     */
    private function trackEmailThread($email, $dealId)
    {
        if (!$this->config['thread_tracking']['enabled']) {
            return;
        }
        
        // Generate or get thread ID
        $threadId = $this->getOrCreateThreadId($email, $dealId);
        
        // Extract email headers
        $headers = $this->extractEmailHeaders($email);
        
        // Create tracking record
        $id = create_guid();
        $now = date('Y-m-d H:i:s');
        
        $query = "INSERT INTO email_thread_deals (
                    id, thread_id, message_id, email_id, deal_id,
                    subject, from_addr, date_sent, in_reply_to, 
                    `references`, date_entered, date_modified, deleted
                  ) VALUES (
                    '{$id}',
                    '" . $this->db->quote($threadId) . "',
                    '" . $this->db->quote($headers['message_id']) . "',
                    '" . $this->db->quote($email->id) . "',
                    '" . $this->db->quote($dealId) . "',
                    '" . $this->db->quote($email->name) . "',
                    '" . $this->db->quote($email->from_addr) . "',
                    '" . $this->db->quote($email->date_sent ?? $now) . "',
                    '" . $this->db->quote($headers['in_reply_to']) . "',
                    '" . $this->db->quote($headers['references']) . "',
                    '{$now}',
                    '{$now}',
                    0
                  )";
        
        $this->db->query($query);
        
        $this->log->info("EmailProcessorService: Tracked email {$email->id} in thread {$threadId} for deal {$dealId}");
    }
    
    /**
     * Get or create thread ID
     */
    private function getOrCreateThreadId($email, $dealId)
    {
        // Check if email already has a thread
        $threadInfo = $this->getThreadInfo($email);
        
        if ($threadInfo && !empty($threadInfo['thread_id'])) {
            return $threadInfo['thread_id'];
        }
        
        // Generate new thread ID
        // Use message ID if available, otherwise generate
        if (!empty($email->message_id)) {
            return 'thread_' . md5($email->message_id);
        } else {
            return 'thread_' . md5($dealId . '_' . time());
        }
    }
    
    /**
     * Extract email headers
     */
    private function extractEmailHeaders($email)
    {
        $headers = array(
            'message_id' => '',
            'in_reply_to' => '',
            'references' => ''
        );
        
        // Try to get from email bean
        if (!empty($email->message_id)) {
            $headers['message_id'] = $this->cleanMessageId($email->message_id);
        }
        
        // Try to parse from raw source if available
        if (!empty($email->raw_source)) {
            // Extract Message-ID
            if (preg_match('/^Message-ID:\s*(.+?)$/mi', $email->raw_source, $matches)) {
                $headers['message_id'] = $this->cleanMessageId($matches[1]);
            }
            
            // Extract In-Reply-To
            if (preg_match('/^In-Reply-To:\s*(.+?)$/mi', $email->raw_source, $matches)) {
                $headers['in_reply_to'] = $this->cleanMessageId($matches[1]);
            }
            
            // Extract References
            if (preg_match('/^References:\s*(.+?)$/mi', $email->raw_source, $matches)) {
                $headers['references'] = trim($matches[1]);
            }
        }
        
        return $headers;
    }
    
    /**
     * Parse references header
     */
    private function parseReferences($referencesStr)
    {
        if (empty($referencesStr)) {
            return array();
        }
        
        // Extract all message IDs from references
        preg_match_all('/<([^>]+)>/', $referencesStr, $matches);
        
        return array_unique($matches[1]);
    }
    
    /**
     * Clean message ID
     */
    private function cleanMessageId($messageId)
    {
        // Remove angle brackets if present
        $messageId = trim($messageId, '<>');
        
        // Remove any whitespace
        $messageId = trim($messageId);
        
        return $messageId;
    }
    
    /**
     * Get email template
     */
    private function getEmailTemplate($type)
    {
        // Check cache first
        if (isset($this->templateCache[$type])) {
            return $this->templateCache[$type];
        }
        
        // Load template
        $template = $this->loadEmailTemplate($type);
        
        // Cache template
        $this->templateCache[$type] = $template;
        
        return $template;
    }
    
    /**
     * Load email template
     */
    private function loadEmailTemplate($type)
    {
        // Try to load from EmailTemplate module
        $templateBean = BeanFactory::newBean('EmailTemplates');
        $templateBean->retrieve_by_string_fields(array(
            'name' => 'File Request: ' . ucfirst($type),
            'deleted' => 0
        ));
        
        if (!empty($templateBean->id)) {
            return array(
                'subject' => $templateBean->subject,
                'body_html' => $templateBean->body_html,
                'body_text' => $templateBean->body
            );
        }
        
        // Fall back to default templates
        return $this->getDefaultTemplate($type);
    }
    
    /**
     * Get default email template
     */
    private function getDefaultTemplate($type)
    {
        $templates = array(
            'general' => array(
                'subject' => 'Document Request for {deal_name}',
                'body_html' => '<p>Dear {recipient_name},</p>
                               <p>We need the following documents for {deal_name}:</p>
                               {file_list_html}
                               <p>Please upload the documents using the following secure link:</p>
                               <p><a href="{upload_url}">Upload Documents</a></p>
                               <p>The deadline for submission is {due_date}.</p>
                               <p>Thank you for your cooperation.</p>',
                'body_text' => 'Dear {recipient_name},
                               
                               We need the following documents for {deal_name}:
                               {file_list_text}
                               
                               Please upload the documents using the following secure link:
                               {upload_url}
                               
                               The deadline for submission is {due_date}.
                               
                               Thank you for your cooperation.'
            ),
            'reminder' => array(
                'subject' => 'Reminder: Document Request for {deal_name}',
                'body_html' => '<p>Dear {recipient_name},</p>
                               <p>This is a reminder that we still need the following documents for {deal_name}:</p>
                               {file_list_html}
                               <p>Please upload the documents as soon as possible using this link:</p>
                               <p><a href="{upload_url}">Upload Documents</a></p>
                               <p>The deadline is {due_date}.</p>',
                'body_text' => 'Dear {recipient_name},
                               
                               This is a reminder that we still need the following documents for {deal_name}:
                               {file_list_text}
                               
                               Please upload the documents as soon as possible using this link:
                               {upload_url}
                               
                               The deadline is {due_date}.'
            ),
            'completion' => array(
                'subject' => 'File Request Completed for {deal_name}',
                'body_html' => '<p>Dear {requestor_name},</p>
                               <p>All requested documents for {deal_name} have been received.</p>
                               <p>You can review the uploaded files in the CRM system.</p>',
                'body_text' => 'Dear {requestor_name},
                               
                               All requested documents for {deal_name} have been received.
                               
                               You can review the uploaded files in the CRM system.'
            )
        );
        
        return $templates[$type] ?? $templates['general'];
    }
    
    /**
     * Prepare template variables
     */
    private function prepareTemplateVariables($requestData, $deal)
    {
        global $sugar_config;
        
        $variables = array(
            'deal_name' => $deal->name,
            'deal_amount' => '$' . number_format($deal->amount, 2),
            'recipient_name' => $requestData['recipient_name'],
            'recipient_email' => $requestData['recipient_email'],
            'due_date' => date('F j, Y', strtotime($requestData['due_date'])),
            'priority' => ucfirst($requestData['priority']),
            'upload_url' => $this->generateUploadUrl($requestData['upload_token']),
            'site_url' => $sugar_config['site_url'],
            'current_date' => date('F j, Y'),
            'requestor_name' => $this->currentUser->full_name,
            'requestor_email' => $this->currentUser->email1
        );
        
        // Add file list
        $fileListHtml = '<ul>';
        $fileListText = '';
        
        if (!empty($requestData['file_items'])) {
            foreach ($requestData['file_items'] as $file) {
                $required = $file['required'] ? ' (Required)' : ' (Optional)';
                $fileListHtml .= '<li>' . htmlspecialchars($file['file_name']) . $required . '</li>';
                $fileListText .= '- ' . $file['file_name'] . $required . "\n";
            }
        }
        $fileListHtml .= '</ul>';
        
        $variables['file_list_html'] = $fileListHtml;
        $variables['file_list_text'] = $fileListText;
        
        return $variables;
    }
    
    /**
     * Parse template with variables
     */
    private function parseTemplate($template, $variables)
    {
        $parsed = array();
        
        foreach (['subject', 'body_html', 'body_text'] as $field) {
            $content = $template[$field] ?? '';
            foreach ($variables as $key => $value) {
                $content = str_replace('{' . $key . '}', $value, $content);
            }
            $parsed[$field] = $content;
        }
        
        return $parsed;
    }
    
    /**
     * Send email
     */
    private function sendEmail($toEmail, $toName, $subject, $bodyHtml, $bodyText)
    {
        $result = array(
            'success' => false,
            'message' => '',
            'email_id' => null
        );
        
        try {
            $outboundEmail = new OutboundEmail();
            $systemSettings = $outboundEmail->getSystemMailerSettings();
            
            if (!$systemSettings) {
                $result['message'] = 'Email system not configured';
                return $result;
            }
            
            $mail = $systemSettings->create_new_sugar_phpmailer();
            $mail->setFrom($systemSettings->smtp_from_addr, $systemSettings->smtp_from_name);
            $mail->addAddress($toEmail, $toName);
            $mail->Subject = $subject;
            $mail->Body = $bodyHtml;
            $mail->AltBody = $bodyText;
            $mail->isHTML(true);
            
            $sent = $mail->send();
            
            if ($sent) {
                // Create email record
                $emailBean = BeanFactory::newBean('Emails');
                $emailBean->name = $subject;
                $emailBean->type = 'out';
                $emailBean->status = 'sent';
                $emailBean->intent = 'pick';
                $emailBean->from_addr = $systemSettings->smtp_from_addr;
                $emailBean->to_addrs = $toEmail;
                $emailBean->description = $bodyText;
                $emailBean->description_html = $bodyHtml;
                $emailBean->date_sent = date('Y-m-d H:i:s');
                $emailBean->save();
                
                $result['success'] = true;
                $result['message'] = 'Email sent successfully';
                $result['email_id'] = $emailBean->id;
            } else {
                $result['message'] = 'Failed to send email: ' . $mail->ErrorInfo;
            }
            
        } catch (Exception $e) {
            $result['message'] = 'Email error: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Get notification recipients
     */
    private function getNotificationRecipients($type, $data)
    {
        $recipients = array();
        
        switch ($type) {
            case 'deal_created':
            case 'deal_updated':
                if (!empty($data['deal_id']) && $this->config['notifications']['notify_assigned_user']) {
                    $deal = BeanFactory::getBean('Opportunities', $data['deal_id']);
                    if ($deal && !empty($deal->assigned_user_id)) {
                        $user = BeanFactory::getBean('Users', $deal->assigned_user_id);
                        if ($user && !empty($user->email1)) {
                            $recipients[] = array(
                                'email' => $user->email1,
                                'name' => $user->full_name
                            );
                        }
                    }
                }
                break;
                
            case 'processing_failed':
                if ($this->config['notifications']['notify_admin_on_failure']) {
                    global $sugar_config;
                    $adminEmail = $sugar_config['site_admin_email'] ?? $sugar_config['notify_fromaddress'];
                    if (!empty($adminEmail)) {
                        $recipients[] = array(
                            'email' => $adminEmail,
                            'name' => 'System Administrator'
                        );
                    }
                }
                break;
        }
        
        return $recipients;
    }
    
    /**
     * Get notification template
     */
    private function getNotificationTemplate($type)
    {
        $templates = array(
            'deal_created' => array(
                'subject' => 'New Deal Created from Email: {deal_name}',
                'body_html' => '<p>A new deal has been created from an email.</p>
                               <p><strong>Deal:</strong> {deal_name}<br>
                               <strong>Amount:</strong> {deal_amount}<br>
                               <strong>Stage:</strong> {sales_stage}</p>
                               <p><a href="{deal_url}">View Deal</a></p>',
                'body_text' => 'A new deal has been created from an email.
                               
                               Deal: {deal_name}
                               Amount: {deal_amount}
                               Stage: {sales_stage}
                               
                               View Deal: {deal_url}'
            ),
            'deal_updated' => array(
                'subject' => 'Deal Updated from Email: {deal_name}',
                'body_html' => '<p>An existing deal has been updated from an email.</p>
                               <p><strong>Deal:</strong> {deal_name}<br>
                               <strong>Update:</strong> {update_summary}</p>
                               <p><a href="{deal_url}">View Deal</a></p>',
                'body_text' => 'An existing deal has been updated from an email.
                               
                               Deal: {deal_name}
                               Update: {update_summary}
                               
                               View Deal: {deal_url}'
            ),
            'processing_failed' => array(
                'subject' => 'Failed to Process Deals Email',
                'body_html' => '<p>Failed to process an email sent to the deals address.</p>
                               <p><strong>Email Subject:</strong> {email_subject}<br>
                               <strong>From:</strong> {email_from}<br>
                               <strong>Error:</strong> {error_message}</p>',
                'body_text' => 'Failed to process an email sent to the deals address.
                               
                               Email Subject: {email_subject}
                               From: {email_from}
                               Error: {error_message}'
            )
        );
        
        return $templates[$type] ?? array(
            'subject' => 'Email Processing Notification',
            'body_html' => '<p>{message}</p>',
            'body_text' => '{message}'
        );
    }
    
    /**
     * Log processing result
     */
    private function logProcessingResult($email, $result)
    {
        $id = create_guid();
        $now = date('Y-m-d H:i:s');
        
        $query = "INSERT INTO email_processing_log (
                    id, email_id, deal_id, status, action, message,
                    contacts_created, contacts_linked, attachments_linked,
                    error_message, processing_time, date_processed,
                    date_entered, deleted
                  ) VALUES (
                    '{$id}',
                    '" . $this->db->quote($email->id) . "',
                    " . ($result['deal_id'] ? "'" . $this->db->quote($result['deal_id']) . "'" : "NULL") . ",
                    '" . ($result['success'] ? 'success' : 'failed') . "',
                    '" . $this->db->quote($result['action'] ?? '') . "',
                    '" . $this->db->quote($result['message'] ?? '') . "',
                    " . (int)($result['contacts_created'] ?? 0) . ",
                    " . (int)($result['contacts_linked'] ?? 0) . ",
                    " . (int)($result['attachments_linked'] ?? 0) . ",
                    " . ($result['error_message'] ? "'" . $this->db->quote($result['error_message']) . "'" : "NULL") . ",
                    " . (float)($result['processing_time'] ?? 0) . ",
                    '{$now}',
                    '{$now}',
                    0
                  )";
        
        $this->db->query($query);
    }
    
    /**
     * Log email sent
     */
    private function logEmailSent($requestId, $recipientEmail, $templateType)
    {
        $note = BeanFactory::newBean('Notes');
        $note->name = 'Email Sent: ' . ucfirst($templateType);
        $note->description = "Email sent to: " . $recipientEmail . "\n" .
                           "Template: " . $templateType . "\n" .
                           "Request ID: " . $requestId . "\n" .
                           "Sent at: " . date('Y-m-d H:i:s');
        $note->save();
    }
    
    /**
     * Generate upload URL
     */
    private function generateUploadUrl($token)
    {
        global $sugar_config;
        $baseUrl = $sugar_config['site_url'] ?? 'http://localhost';
        return $baseUrl . '/custom/modules/Deals/upload.php?token=' . $token;
    }
    
    /**
     * Clean email subject
     */
    private function cleanSubject($subject)
    {
        // Remove common email prefixes
        $subject = preg_replace('/^(Re:|RE:|Fwd:|FWD:|Fw:|FW:)\s*/i', '', $subject);
        
        // Remove deal-specific keywords that might be redundant
        $subject = preg_replace('/\s*(deal|opportunity|proposal|inquiry)\s*/i', ' ', $subject);
        
        // Clean up whitespace
        $subject = trim(preg_replace('/\s+/', ' ', $subject));
        
        return $subject;
    }
    
    /**
     * Parse amount string into numeric value
     */
    private function parseAmount($amountStr)
    {
        // Remove currency symbols and spaces
        $amount = preg_replace('/[\$,\s]/', '', $amountStr);
        
        // Handle K, M suffixes
        if (preg_match('/([0-9.]+)([KkMm])/', $amount, $matches)) {
            $value = floatval($matches[1]);
            $suffix = strtoupper($matches[2]);
            
            if ($suffix === 'K') {
                $value *= 1000;
            } elseif ($suffix === 'M') {
                $value *= 1000000;
            }
            
            return $value;
        }
        
        return floatval($amount);
    }
    
    /**
     * Normalize industry names
     */
    private function normalizeIndustry($industry)
    {
        $industryMap = $this->config['industry_mapping'] ?? array();
        
        $normalized = strtolower(trim($industry));
        
        foreach ($industryMap as $standard => $variations) {
            if (in_array($normalized, array_map('strtolower', $variations))) {
                return ucwords($standard);
            }
        }
        
        return $industry;
    }
    
    /**
     * Format phone number
     */
    private function formatPhoneNumber($phone)
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Format as (XXX) XXX-XXXX for US numbers
        if (strlen($phone) === 10) {
            return '(' . substr($phone, 0, 3) . ') ' . substr($phone, 3, 3) . '-' . substr($phone, 6);
        }
        
        return $phone;
    }
    
    /**
     * Deduplicate contacts array
     */
    private function deduplicateContacts($contacts)
    {
        $unique = array();
        $seen = array();
        
        foreach ($contacts as $contact) {
            // Use email as primary key
            if (!empty($contact['email']) && !in_array($contact['email'], $seen)) {
                $seen[] = $contact['email'];
                $unique[] = $contact;
            }
            // Use name as secondary key
            elseif (!empty($contact['first_name']) && !empty($contact['last_name'])) {
                $nameKey = $contact['first_name'] . '|' . $contact['last_name'];
                if (!in_array($nameKey, $seen)) {
                    $seen[] = $nameKey;
                    $unique[] = $contact;
                }
            }
        }
        
        return $unique;
    }
    
    /**
     * Get thread summary
     */
    public function getThreadSummary($threadId)
    {
        $query = "SELECT 
                    COUNT(*) as email_count,
                    MIN(date_sent) as first_email,
                    MAX(date_sent) as last_email,
                    GROUP_CONCAT(DISTINCT from_addr SEPARATOR ', ') as participants,
                    deal_id
                  FROM email_thread_deals
                  WHERE thread_id = '" . $this->db->quote($threadId) . "'
                  AND deleted = 0
                  GROUP BY thread_id, deal_id";
        
        $result = $this->db->query($query);
        $row = $this->db->fetchByAssoc($result);
        
        if ($row) {
            return array(
                'thread_id' => $threadId,
                'deal_id' => $row['deal_id'],
                'email_count' => $row['email_count'],
                'first_email' => $row['first_email'],
                'last_email' => $row['last_email'],
                'participants' => explode(', ', $row['participants']),
                'duration_days' => round((strtotime($row['last_email']) - strtotime($row['first_email'])) / 86400)
            );
        }
        
        return null;
    }
    
    /**
     * Get deal conversations
     */
    public function getDealConversations($dealId)
    {
        $conversations = array();
        
        $query = "SELECT DISTINCT thread_id, 
                         MIN(date_sent) as first_email,
                         MAX(date_sent) as last_email,
                         COUNT(*) as email_count,
                         GROUP_CONCAT(DISTINCT from_addr SEPARATOR ', ') as participants
                  FROM email_thread_deals
                  WHERE deal_id = '" . $this->db->quote($dealId) . "'
                  AND deleted = 0
                  GROUP BY thread_id
                  ORDER BY last_email DESC";
        
        $result = $this->db->query($query);
        
        while ($row = $this->db->fetchByAssoc($result)) {
            $conversations[] = array(
                'thread_id' => $row['thread_id'],
                'first_email' => $row['first_email'],
                'last_email' => $row['last_email'],
                'email_count' => $row['email_count'],
                'participants' => explode(', ', $row['participants']),
                'emails' => $this->getThreadEmails($row['thread_id'])
            );
        }
        
        return $conversations;
    }
    
    /**
     * Get thread emails
     */
    private function getThreadEmails($threadId)
    {
        $emails = array();
        
        $query = "SELECT email_id, date_sent 
                  FROM email_thread_deals 
                  WHERE thread_id = '" . $this->db->quote($threadId) . "'
                  AND deleted = 0
                  ORDER BY date_sent ASC";
        
        $result = $this->db->query($query);
        
        while ($row = $this->db->fetchByAssoc($result)) {
            $emails[] = $row['email_id'];
        }
        
        return $emails;
    }
}
?>