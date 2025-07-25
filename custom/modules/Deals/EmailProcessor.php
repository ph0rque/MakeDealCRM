<?php
/**
 * Email Processor for Deals Module
 * Processes parsed emails and creates/updates deals with contacts
 * 
 * @package MakeDealCRM
 * @subpackage Deals
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once('custom/modules/Deals/EmailParser.php');
require_once('custom/modules/Deals/EmailThreadTracker.php');
require_once('modules/Contacts/Contact.php');
require_once('modules/Notes/Note.php');

class DealsEmailProcessor
{
    private $parser;
    private $threadTracker;
    private $db;
    private $log;
    private $currentUser;
    
    public function __construct()
    {
        global $db, $log, $current_user;
        $this->db = $db;
        $this->log = $log;
        $this->currentUser = $current_user;
        $this->parser = new DealsEmailParser();
        $this->threadTracker = new EmailThreadTracker();
    }
    
    /**
     * Process an incoming email
     * 
     * @param SugarBean $email The email bean
     * @return array Processing result
     */
    public function processEmail($email)
    {
        $result = array(
            'success' => false,
            'deal_id' => null,
            'action' => null,
            'message' => '',
            'contacts_created' => 0,
            'attachments_linked' => 0
        );
        
        try {
            // Check if email should be processed
            if (!$this->shouldProcessEmail($email)) {
                $result['message'] = 'Email does not meet processing criteria';
                return $result;
            }
            
            // Parse email content
            $emailData = $this->prepareEmailData($email);
            $parsedData = $this->parser->parseEmail($emailData);
            
            if (!$parsedData['success']) {
                $result['message'] = 'Failed to parse email';
                $result['errors'] = $parsedData['errors'];
                return $result;
            }
            
            // Check if this is part of an existing thread
            $threadInfo = $this->threadTracker->getThreadInfo($email);
            
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
            
            // Process contacts regardless of deal creation/update
            if ($result['success'] && !empty($result['deal_id'])) {
                $contactsResult = $this->processContacts($parsedData['contacts'], $result['deal_id']);
                $result['contacts_created'] = $contactsResult['created'];
                $result['contacts_linked'] = $contactsResult['linked'];
                
                // Process attachments
                $attachmentsResult = $this->processAttachments($parsedData['attachments'], $result['deal_id'], $email->id);
                $result['attachments_linked'] = $attachmentsResult['linked'];
                
                // Update thread tracking
                $this->threadTracker->trackEmail($email, $result['deal_id']);
            }
            
            // Link email to deal
            if ($result['success'] && !empty($result['deal_id'])) {
                $this->linkEmailToDeal($email->id, $result['deal_id']);
            }
            
        } catch (Exception $e) {
            $this->log->error("DealsEmailProcessor: Error processing email - " . $e->getMessage());
            $result['message'] = 'Error processing email: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Check if email should be processed
     */
    private function shouldProcessEmail($email)
    {
        // Check if email is sent to deals@mycrm
        $toAddresses = $email->to_addrs . ' ' . $email->cc_addrs . ' ' . $email->bcc_addrs;
        if (!preg_match('/deals@mycrm/i', $toAddresses)) {
            return false;
        }
        
        // Check if email is already processed
        if ($this->isEmailProcessed($email->id)) {
            return false;
        }
        
        // Check if email is too old (older than 30 days)
        $emailDate = strtotime($email->date_sent ?? $email->date_entered);
        if ($emailDate < strtotime('-30 days')) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Prepare email data for parser
     */
    private function prepareEmailData($email)
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
            
            $this->log->info("DealsEmailProcessor: Created deal {$deal->id} from email {$email->id}");
            
        } catch (Exception $e) {
            $this->log->error("DealsEmailProcessor: Failed to create deal - " . $e->getMessage());
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
                
                $this->log->info("DealsEmailProcessor: Updated deal {$deal->id} from email {$email->id}");
            } else {
                $result['success'] = true;
                $result['message'] = "No updates needed for deal: " . $deal->name;
            }
            
        } catch (Exception $e) {
            $this->log->error("DealsEmailProcessor: Failed to update deal - " . $e->getMessage());
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
                    if (!empty($contactData['role'])) {
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
                $this->log->error("DealsEmailProcessor: Failed to process contact - " . $e->getMessage());
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
                $this->log->error("DealsEmailProcessor: Failed to link attachment - " . $e->getMessage());
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
            $this->log->error("DealsEmailProcessor: Failed to link email to deal - " . $e->getMessage());
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
}
?>