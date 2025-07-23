<?php
/**
 * Logic Hooks Implementation for Deals Module
 * 
 * @package MakeDealCRM
 * @subpackage Deals
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once('include/SugarEmailAddress/SugarEmailAddress.php');

class DealsLogicHooks
{
    /**
     * Update at-risk status based on days in stage
     */
    public function updateAtRiskStatus($bean, $event, $arguments)
    {
        // This is already handled in the Deal bean save method
        // This hook is here for any additional processing needed
    }
    
    /**
     * Calculate financial metrics
     */
    public function calculateFinancialMetrics($bean, $event, $arguments)
    {
        // Calculate total capital stack
        $total_capital = 0;
        if (!empty($bean->equity_c)) {
            $total_capital += floatval($bean->equity_c);
        }
        if (!empty($bean->senior_debt_c)) {
            $total_capital += floatval($bean->senior_debt_c);
        }
        if (!empty($bean->seller_note_c)) {
            $total_capital += floatval($bean->seller_note_c);
        }
        
        // Store in a custom field if needed
        // $bean->total_capital_c = $total_capital;
        
        // Calculate debt coverage ratio if we have EBITDA
        if (!empty($bean->ttm_ebitda_c) && !empty($bean->senior_debt_c)) {
            $debt_coverage = floatval($bean->ttm_ebitda_c) / floatval($bean->senior_debt_c);
            // Store this ratio if needed
        }
    }
    
    /**
     * Process email import and create/update deal
     */
    public function processEmailImport($bean, $event, $arguments)
    {
        if ($arguments['related_module'] == 'Emails' && $arguments['module'] == 'Deals') {
            $email_id = $arguments['related_id'];
            $deal_id = $arguments['id'];
            
            require_once('modules/Emails/Email.php');
            $email = BeanFactory::getBean('Emails', $email_id);
            
            if ($email) {
                // Parse email for deal information
                $this->parseEmailForDealInfo($bean, $email);
                
                // Attach any documents from email
                $this->attachEmailDocuments($bean, $email);
                
                // Create contacts from email addresses
                $this->createContactsFromEmail($bean, $email);
            }
        }
    }
    
    /**
     * Check for duplicate deals
     */
    public function checkForDuplicates($bean, $event, $arguments)
    {
        if (empty($bean->fetched_row['id'])) { // New record
            global $db;
            
            // Check for similar deal names
            $name_check = $db->quote($bean->name);
            $query = "SELECT id, name FROM deals 
                     WHERE deleted = 0 
                     AND name LIKE '%{$name_check}%' 
                     AND id != '{$bean->id}'
                     LIMIT 5";
            
            $result = $db->query($query);
            $duplicates = array();
            
            while ($row = $db->fetchByAssoc($result)) {
                similar_text($bean->name, $row['name'], $percent);
                if ($percent > 80) { // 80% similarity threshold
                    $duplicates[] = $row;
                }
            }
            
            if (!empty($duplicates)) {
                // Store duplicates for display in UI
                $_SESSION['deal_duplicates'] = $duplicates;
            }
        }
    }
    
    /**
     * Format fields for list view display
     */
    public function formatListViewFields($bean, $event, $arguments)
    {
        // Format currency fields
        global $locale;
        
        if (!empty($bean->deal_value)) {
            $bean->deal_value_formatted = currency_format_number($bean->deal_value);
        }
        
        if (!empty($bean->asking_price_c)) {
            $bean->asking_price_c_formatted = currency_format_number($bean->asking_price_c);
        }
        
        // Add CSS classes for at-risk status
        if ($bean->at_risk_status == 'Alert') {
            $bean->at_risk_css_class = 'alert-danger';
        } elseif ($bean->at_risk_status == 'Warning') {
            $bean->at_risk_css_class = 'alert-warning';
        } else {
            $bean->at_risk_css_class = 'alert-success';
        }
    }
    
    /**
     * Parse email content for deal information
     */
    private function parseEmailForDealInfo(&$deal, $email)
    {
        $content = $email->description . ' ' . $email->description_html;
        
        // Extract deal value if mentioned
        if (preg_match('/\$([0-9,]+(?:\.[0-9]{2})?)[kKmM]?/', $content, $matches)) {
            $value = str_replace(',', '', $matches[1]);
            if (stripos($matches[0], 'k') !== false) {
                $value *= 1000;
            } elseif (stripos($matches[0], 'm') !== false) {
                $value *= 1000000;
            }
            
            if (empty($deal->deal_value)) {
                $deal->deal_value = $value;
            }
        }
        
        // Extract revenue if mentioned
        if (preg_match('/revenue[:\s]+\$([0-9,]+(?:\.[0-9]{2})?)[kKmM]?/i', $content, $matches)) {
            $revenue = str_replace(',', '', $matches[1]);
            if (stripos($matches[0], 'k') !== false) {
                $revenue *= 1000;
            } elseif (stripos($matches[0], 'm') !== false) {
                $revenue *= 1000000;
            }
            
            if (empty($deal->ttm_revenue_c)) {
                $deal->ttm_revenue_c = $revenue;
            }
        }
        
        // Set source to Email if not already set
        if (empty($deal->source)) {
            $deal->source = 'Email';
        }
        
        // Add email subject to description if not already there
        if (!empty($email->name) && strpos($deal->description, $email->name) === false) {
            $deal->description = "Email Subject: {$email->name}\n\n" . $deal->description;
        }
    }
    
    /**
     * Attach email documents to deal
     */
    private function attachEmailDocuments(&$deal, $email)
    {
        // Get email attachments
        $email->retrieve($email->id);
        $attachments = $email->getAttachments();
        
        if (!empty($attachments)) {
            $deal->load_relationship('documents');
            
            foreach ($attachments as $attachment) {
                // Create document record for each attachment
                require_once('modules/Documents/Document.php');
                $doc = new Document();
                $doc->document_name = $attachment['name'];
                $doc->filename = $attachment['filename'];
                $doc->file_ext = pathinfo($attachment['filename'], PATHINFO_EXTENSION);
                $doc->file_mime_type = $attachment['type'];
                $doc->save();
                
                // Relate to deal
                $deal->documents->add($doc->id);
            }
        }
    }
    
    /**
     * Create contacts from email addresses
     */
    private function createContactsFromEmail(&$deal, $email)
    {
        require_once('modules/Contacts/Contact.php');
        $sea = new SugarEmailAddress();
        
        // Get all email addresses from the email
        $addresses = array();
        
        // From address
        if (!empty($email->from_addr)) {
            $addresses[] = $email->from_addr;
        }
        
        // To addresses
        if (!empty($email->to_addrs)) {
            $to_addrs = $sea->splitEmailAddress($email->to_addrs);
            $addresses = array_merge($addresses, $to_addrs);
        }
        
        // CC addresses
        if (!empty($email->cc_addrs)) {
            $cc_addrs = $sea->splitEmailAddress($email->cc_addrs);
            $addresses = array_merge($addresses, $cc_addrs);
        }
        
        // Process each email address
        $deal->load_relationship('contacts');
        
        foreach ($addresses as $email_addr) {
            $email_addr = trim($email_addr);
            if (!empty($email_addr)) {
                // Check if contact exists
                $contact = $this->findContactByEmail($email_addr);
                
                if (!$contact) {
                    // Create new contact
                    $contact = new Contact();
                    
                    // Parse email for name
                    if (preg_match('/"?([^"<]+)"?\s*<(.+)>/', $email_addr, $matches)) {
                        $name_parts = explode(' ', trim($matches[1]));
                        $contact->first_name = array_shift($name_parts);
                        $contact->last_name = implode(' ', $name_parts) ?: 'Unknown';
                        $email_addr = $matches[2];
                    } else {
                        $email_parts = explode('@', $email_addr);
                        $name_parts = explode('.', $email_parts[0]);
                        $contact->first_name = ucfirst($name_parts[0]);
                        $contact->last_name = isset($name_parts[1]) ? ucfirst($name_parts[1]) : 'Unknown';
                    }
                    
                    $contact->email1 = $email_addr;
                    $contact->lead_source = 'Email';
                    $contact->save();
                }
                
                // Relate to deal
                $deal->contacts->add($contact->id);
            }
        }
    }
    
    /**
     * Find contact by email address
     */
    private function findContactByEmail($email_addr)
    {
        global $db;
        
        $email_addr = $db->quote($email_addr);
        $query = "SELECT c.id 
                 FROM contacts c
                 JOIN email_addr_bean_rel eabr ON eabr.bean_id = c.id AND eabr.bean_module = 'Contacts'
                 JOIN email_addresses ea ON ea.id = eabr.email_address_id
                 WHERE ea.email_address = '{$email_addr}'
                 AND c.deleted = 0
                 AND eabr.deleted = 0
                 LIMIT 1";
        
        $result = $db->query($query);
        if ($row = $db->fetchByAssoc($result)) {
            return BeanFactory::getBean('Contacts', $row['id']);
        }
        
        return false;
    }
}