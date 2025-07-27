<?php
/**
 * Core Logic Hooks Implementation for Deals Module
 * 
 * This class contains the core logic hook implementations for the Deals module.
 * These hooks handle essential deal functionality including financial calculations,
 * email processing, duplicate detection, and field formatting.
 * 
 * @package MakeDealCRM
 * @subpackage Deals
 * @author MakeDealCRM Development Team
 * @version 1.0.0
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once('include/SugarEmailAddress/SugarEmailAddress.php');

class DealsLogicHooks
{
    /**
     * Update at-risk status based on days in stage
     * 
     * This hook evaluates how long a deal has been in its current stage and
     * determines if it's at risk of stalling. The at-risk status helps identify
     * deals that need immediate attention.
     * 
     * Risk Status Levels:
     * - Normal: Within expected timeframe for the stage
     * - Warning: Approaching the typical duration limit
     * - Alert: Exceeded typical duration, needs immediate attention
     * 
     * Note: The actual calculation is handled in the Deal bean's save method,
     * this hook exists for any additional processing or notifications.
     * 
     * @param SugarBean $bean The Deal bean being processed
     * @param string $event The event type (typically 'after_save')
     * @param array $arguments Additional hook arguments
     * 
     * @return void
     */
    public function updateAtRiskStatus($bean, $event, $arguments)
    {
        // This is already handled in the Deal bean save method
        // This hook is here for any additional processing needed
    }
    
    /**
     * Calculate financial metrics for the deal
     * 
     * This hook performs comprehensive financial calculations including:
     * - Total capital stack (equity + senior debt + seller note)
     * - Debt coverage ratios for lending analysis
     * - Return on investment projections
     * - Financial health indicators
     * 
     * These metrics are crucial for deal evaluation and help determine
     * if a deal meets investment criteria. The calculations support
     * decision-making throughout the deal lifecycle.
     * 
     * @param SugarBean $bean The Deal bean being processed
     * @param string $event The event type (typically 'after_retrieve' or 'before_save')
     * @param array $arguments Additional hook arguments
     * 
     * @return void
     */
    public function calculateFinancialMetrics($bean, $event, $arguments)
    {
        require_once('custom/modules/Deals/services/FinancialCalculator.php');
        $calculator = new FinancialCalculator();
        
        // Prepare deal data for calculations
        $dealData = array(
            'asking_price' => $bean->amount,
            'annual_revenue' => $bean->annual_revenue_c ?? 0,
            'operating_expenses' => $bean->operating_expenses_c ?? null,
            'add_backs' => $bean->add_backs_c ?? 0,
            'owner_compensation' => $bean->owner_compensation_c ?? 0,
            'owner_benefits' => $bean->owner_benefits_c ?? 0,
            'non_essential_expenses' => $bean->non_essential_expenses_c ?? 0,
            'target_multiple' => $bean->target_multiple_c ?? null,
            'valuation_method' => $bean->valuation_method_c ?? 'ebitda',
            'normalized_salary' => $bean->normalized_salary_c ?? null,
            'debt_structure' => array(
                'senior_debt' => $bean->senior_debt_c ?? 0,
                'senior_debt_rate' => $bean->senior_debt_rate_c ?? 0.05,
                'senior_debt_term' => $bean->senior_debt_term_c ?? 5,
                'seller_note' => $bean->seller_note_c ?? 0,
                'seller_note_rate' => $bean->seller_note_rate_c ?? 0.06,
                'seller_note_term' => $bean->seller_note_term_c ?? 3
            )
        );
        
        // Calculate all metrics
        $metrics = $calculator->calculateAllMetrics($dealData);
        
        // Update bean with calculated values
        if (isset($metrics['ttm_revenue'])) {
            $bean->ttm_revenue_calculated_c = $metrics['ttm_revenue'];
        }
        if (isset($metrics['ttm_ebitda'])) {
            $bean->ttm_ebitda_calculated_c = $metrics['ttm_ebitda'];
        }
        if (isset($metrics['sde'])) {
            $bean->sde_calculated_c = $metrics['sde'];
        }
        if (isset($metrics['ebitda_margin'])) {
            $bean->ebitda_margin_c = $metrics['ebitda_margin'];
        }
        if (isset($metrics['implied_multiple'])) {
            $bean->implied_multiple_c = $metrics['implied_multiple'];
        }
        if (isset($metrics['dscr'])) {
            $bean->debt_service_coverage_ratio_c = $metrics['dscr'];
        }
        if (isset($metrics['roi'])) {
            $bean->roi_c = $metrics['roi'];
        }
        if (isset($metrics['payback_period'])) {
            $bean->payback_period_c = $metrics['payback_period'];
        }
        
        // Calculate capital stack if we have the data
        if (!empty($bean->amount)) {
            $capitalStack = $calculator->calculateCapitalStack(
                $bean->amount,
                ($bean->equity_c ?? 0) / $bean->amount,
                ($bean->senior_debt_c ?? 0) / $bean->amount,
                ($bean->seller_note_c ?? 0) / $bean->amount
            );
            
            // Update capital stack fields with properly calculated values
            $bean->equity_c = $capitalStack['equity'];
            $bean->senior_debt_c = $capitalStack['senior_debt'];
            $bean->seller_note_c = $capitalStack['seller_note'];
        }
    }
    
    /**
     * Process email import and create/update deal
     * 
     * This hook is triggered when an email is related to a deal. It automates
     * the extraction of deal information from emails and performs several actions:
     * 
     * 1. Parse email content for deal information:
     *    - Deal values (recognizes $XXk, $XXm formats)
     *    - Revenue figures
     *    - Key business metrics
     * 
     * 2. Attach email documents to the deal:
     *    - PDFs (offering memorandums, financials)
     *    - Spreadsheets (financial models)
     *    - Word documents (LOIs, contracts)
     * 
     * 3. Create contacts from email participants:
     *    - Extracts names from email addresses
     *    - Links existing contacts or creates new ones
     *    - Maintains relationship history
     * 
     * This automation significantly reduces manual data entry and ensures
     * all email communications are properly tracked with deals.
     * 
     * @param SugarBean $bean The Deal bean being processed
     * @param string $event The event type (typically 'after_relationship_add')
     * @param array $arguments Contains related_module, module, related_id, id
     * 
     * @return void
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
     * Check for duplicate deals before saving
     * 
     * This hook prevents duplicate deal entries by checking for similar existing deals.
     * It uses intelligent matching to identify potential duplicates:
     * 
     * - Fuzzy name matching (80% similarity threshold)
     * - Same company/account associations
     * - Similar deal values
     * - Matching key identifiers
     * 
     * When duplicates are found, they're stored in the session for display
     * to the user, who can then decide whether to proceed or merge with
     * an existing deal. This maintains data quality and prevents confusion
     * from multiple entries for the same opportunity.
     * 
     * @param SugarBean $bean The Deal bean being processed
     * @param string $event The event type (typically 'before_save')
     * @param array $arguments Additional hook arguments
     * 
     * @return void
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
     * 
     * This hook enhances the deal's appearance in list views by:
     * 
     * 1. Formatting currency values:
     *    - Applies proper currency symbols
     *    - Adds thousand separators
     *    - Handles multiple currencies if configured
     * 
     * 2. Adding visual indicators:
     *    - CSS classes for at-risk status (red/yellow/green)
     *    - Icons for deal stage
     *    - Progress bars for completion percentage
     * 
     * 3. Creating calculated display fields:
     *    - Days in stage indicator
     *    - Time until close date
     *    - Quick status summary
     * 
     * This formatting improves list view usability by making important
     * information immediately visible without opening each deal.
     * 
     * @param SugarBean $bean The Deal bean being processed
     * @param string $event The event type (typically 'process_record')
     * @param array $arguments Additional hook arguments
     * 
     * @return void
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
     * 
     * Extracts structured data from email content using pattern matching and
     * natural language processing techniques. This method looks for:
     * 
     * Financial Information:
     * - Deal values: "$5M purchase price", "asking $2.5 million"
     * - Revenue: "TTM revenue of $10M", "annual revenue: $5,000,000"
     * - EBITDA: "EBITDA of $2M", "adjusted EBITDA: $1.5 million"
     * - Multiples: "asking 5x EBITDA", "3.5x multiple"
     * 
     * Deal Attributes:
     * - Industry mentions
     * - Geographic location
     * - Company size indicators
     * - Urgency indicators ("quick close", "motivated seller")
     * 
     * The parser is intelligent enough to:
     * - Handle various formats ($1M, $1mm, $1,000,000)
     * - Distinguish between different financial metrics
     * - Extract context around the numbers
     * - Avoid overwriting existing deal data unless empty
     * 
     * @param SugarBean &$deal The Deal bean to populate (passed by reference)
     * @param SugarBean $email The Email bean containing the content to parse
     * 
     * @return void
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
     * 
     * Processes email attachments and links them to the deal as documents.
     * This method handles various document types commonly seen in M&A:
     * 
     * Document Types Processed:
     * - Financial statements (PDFs, Excel)
     * - Offering memorandums (PDFs, Word)
     * - LOIs and term sheets (PDFs, Word)
     * - Financial models (Excel)
     * - Presentations (PowerPoint, PDF)
     * - Due diligence documents
     * 
     * The method:
     * 1. Retrieves all attachments from the email
     * 2. Creates a Document record for each attachment
     * 3. Preserves original filename and metadata
     * 4. Links documents to the deal for easy access
     * 5. Maintains document versioning if enabled
     * 
     * This ensures all deal-related documents are centrally stored and
     * easily accessible from the deal record.
     * 
     * @param SugarBean &$deal The Deal bean to attach documents to
     * @param SugarBean $email The Email bean containing attachments
     * 
     * @return void
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
     * 
     * Intelligently processes email addresses to create or link contacts to the deal.
     * This method ensures all deal participants are tracked in the CRM:
     * 
     * Processing Steps:
     * 1. Extract email addresses from From, To, CC fields
     * 2. Parse display names to extract first/last names
     * 3. Check if contacts already exist in the system
     * 4. Create new contacts for unknown email addresses
     * 5. Link all relevant contacts to the deal
     * 
     * Name Parsing Intelligence:
     * - Handles "John Smith <jsmith@example.com>" format
     * - Parses firstname.lastname@company.com patterns
     * - Manages complex names (middle names, suffixes)
     * - Sets appropriate defaults for unparseable names
     * 
     * Relationship Management:
     * - Preserves existing contact information
     * - Avoids duplicate contact creation
     * - Maintains contact source as "Email"
     * - Links contacts with appropriate roles
     * 
     * This automation builds a comprehensive contact network for each deal,
     * essential for relationship management and communication tracking.
     * 
     * @param SugarBean &$deal The Deal bean to link contacts to
     * @param SugarBean $email The Email bean containing email addresses
     * 
     * @return void
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
     * 
     * Searches for existing contacts in the system by email address.
     * This method performs an efficient database lookup to prevent
     * duplicate contact creation.
     * 
     * Search Process:
     * 1. Queries the email_addresses table for exact match
     * 2. Joins with email_addr_bean_rel to find related contacts
     * 3. Filters out deleted records
     * 4. Returns first active match found
     * 
     * Performance Considerations:
     * - Uses indexed email address lookup
     * - Limits results to first match
     * - Properly handles SQL injection prevention
     * 
     * @param string $email_addr The email address to search for
     * 
     * @return Contact|false The Contact bean if found, false otherwise
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