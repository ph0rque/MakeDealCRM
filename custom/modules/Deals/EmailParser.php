<?php
/**
 * Email Parser for Deals Module
 * Handles parsing of forwarded emails to deals@mycrm
 * Extracts deal information, contacts, and attachments
 * 
 * @package MakeDealCRM
 * @subpackage Deals
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once('include/SugarEmailAddress/SugarEmailAddress.php');
require_once('include/upload_file.php');

class DealsEmailParser
{
    private $db;
    private $log;
    private $emailPatterns;
    private $contactPatterns;
    private $dealPatterns;
    
    public function __construct()
    {
        global $db, $log;
        $this->db = $db;
        $this->log = $log;
        $this->initializePatterns();
    }
    
    /**
     * Initialize regex patterns for parsing
     */
    private function initializePatterns()
    {
        // Email patterns for contact extraction
        $this->emailPatterns = array(
            'email' => '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
            'phone' => '/(\+?1?[-.\s]?)?\(?([0-9]{3})\)?[-.\s]?([0-9]{3})[-.\s]?([0-9]{4})/',
            'name' => '/(?:From|To|CC|Sender):\s*"?([^<"\n]+)"?\s*<?[^>]*>?/i',
            'signature' => '/(?:Best regards|Sincerely|Thanks|Regards|Best),?\s*\n+([^\n]+)(?:\n|$)/i'
        );
        
        // Deal-related patterns
        $this->dealPatterns = array(
            'amount' => '/\$\s?([0-9]{1,3}(?:,?[0-9]{3})*(?:\.[0-9]{2})?)[MmKk]?/i',
            'company' => '/(?:Company|Business|Firm|Corporation):\s*([^\n]+)/i',
            'industry' => '/(?:Industry|Sector|Market):\s*([^\n]+)/i',
            'revenue' => '/(?:Revenue|Sales|Income):\s*\$?\s?([0-9,]+(?:\.[0-9]{2})?)[MmKk]?/i',
            'ebitda' => '/(?:EBITDA|Earnings):\s*\$?\s?([0-9,]+(?:\.[0-9]{2})?)[MmKk]?/i',
            'asking_price' => '/(?:Asking Price|Price|Valuation):\s*\$?\s?([0-9,]+(?:\.[0-9]{2})?)[MmKk]?/i'
        );
        
        // Contact role patterns
        $this->contactPatterns = array(
            'seller' => '/(?:Seller|Owner|Proprietor):\s*([^\n]+)/i',
            'broker' => '/(?:Broker|Agent|Representative):\s*([^\n]+)/i',
            'attorney' => '/(?:Attorney|Lawyer|Counsel):\s*([^\n]+)/i',
            'accountant' => '/(?:Accountant|CPA|CFO):\s*([^\n]+)/i',
            'buyer' => '/(?:Buyer|Purchaser|Investor):\s*([^\n]+)/i'
        );
    }
    
    /**
     * Parse email content and extract deal information
     * 
     * @param array $emailData Email data from SuiteCRM
     * @return array Parsed deal data
     */
    public function parseEmail($emailData)
    {
        $result = array(
            'success' => false,
            'deal_data' => array(),
            'contacts' => array(),
            'attachments' => array(),
            'errors' => array()
        );
        
        try {
            // Extract basic email info
            $subject = $emailData['name'] ?? '';
            $body = $this->getEmailBody($emailData);
            $fromAddress = $emailData['from_addr'] ?? '';
            $toAddresses = $emailData['to_addrs'] ?? '';
            
            // Parse deal information
            $dealData = $this->extractDealData($subject, $body);
            
            // Extract contacts
            $contacts = $this->extractContacts($body, $fromAddress, $toAddresses);
            
            // Process attachments
            $attachments = $this->processAttachments($emailData);
            
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
            $this->log->error("DealsEmailParser: Error parsing email - " . $e->getMessage());
            $result['errors'][] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Extract email body from different formats
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
            'probability' => 10
        );
        
        // Combine subject and body for parsing
        $content = $subject . "\n" . $body;
        
        // Extract amounts
        if (preg_match($this->dealPatterns['asking_price'], $content, $matches)) {
            $dealData['amount'] = $this->parseAmount($matches[1]);
        } elseif (preg_match($this->dealPatterns['amount'], $content, $matches)) {
            $dealData['amount'] = $this->parseAmount($matches[1]);
        }
        
        // Extract company name
        if (preg_match($this->dealPatterns['company'], $content, $matches)) {
            $dealData['account_name'] = trim($matches[1]);
        }
        
        // Extract industry
        if (preg_match($this->dealPatterns['industry'], $content, $matches)) {
            $dealData['industry'] = $this->normalizeIndustry(trim($matches[1]));
        }
        
        // Extract financial metrics
        if (preg_match($this->dealPatterns['revenue'], $content, $matches)) {
            $dealData['annual_revenue_c'] = $this->parseAmount($matches[1]);
        }
        
        if (preg_match($this->dealPatterns['ebitda'], $content, $matches)) {
            $dealData['ebitda_c'] = $this->parseAmount($matches[1]);
        }
        
        // Set description
        $dealData['description'] = "Deal created from email: " . $subject . "\n\n" . 
                                  substr($body, 0, 1000) . (strlen($body) > 1000 ? '...' : '');
        
        // Set source
        $dealData['deal_source_c'] = 'Email';
        $dealData['lead_source'] = 'Email';
        
        // Set dates
        $dealData['date_entered'] = date('Y-m-d H:i:s');
        $dealData['date_closed'] = date('Y-m-d', strtotime('+90 days'));
        
        return $dealData;
    }
    
    /**
     * Extract contacts from email content
     */
    private function extractContacts($body, $fromAddress, $toAddresses)
    {
        $contacts = array();
        
        // Extract from sender
        if (!empty($fromAddress)) {
            $senderContact = $this->parseEmailAddress($fromAddress);
            if ($senderContact) {
                $senderContact['source'] = 'sender';
                $contacts[] = $senderContact;
            }
        }
        
        // Extract from email body using patterns
        foreach ($this->contactPatterns as $role => $pattern) {
            if (preg_match($pattern, $body, $matches)) {
                $contactInfo = $this->parseContactInfo($matches[1]);
                if ($contactInfo) {
                    $contactInfo['role'] = $role;
                    $contactInfo['source'] = 'body';
                    $contacts[] = $contactInfo;
                }
            }
        }
        
        // Extract from email signature
        if (preg_match($this->emailPatterns['signature'], $body, $matches)) {
            $signatureContact = $this->parseSignature($matches[0]);
            if ($signatureContact) {
                $signatureContact['source'] = 'signature';
                $contacts[] = $signatureContact;
            }
        }
        
        // Find all email addresses in body
        if (preg_match_all($this->emailPatterns['email'], $body, $matches)) {
            foreach ($matches[0] as $email) {
                $existingContact = false;
                foreach ($contacts as $contact) {
                    if (isset($contact['email']) && $contact['email'] == $email) {
                        $existingContact = true;
                        break;
                    }
                }
                if (!$existingContact) {
                    $contacts[] = array(
                        'email' => $email,
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
        if (preg_match($this->emailPatterns['email'], $text, $matches)) {
            $contact['email'] = $matches[0];
        }
        
        // Extract phone
        if (preg_match($this->emailPatterns['phone'], $text, $matches)) {
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
            if (preg_match($this->emailPatterns['email'], $line, $matches)) {
                $contact['email'] = $matches[0];
            }
            
            // Extract phone
            if (preg_match($this->emailPatterns['phone'], $line, $matches)) {
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
     * Process email attachments
     */
    private function processAttachments($emailData)
    {
        $attachments = array();
        
        if (empty($emailData['id'])) {
            return $attachments;
        }
        
        // Query for attachments
        $query = "SELECT n.id, n.name, n.filename, n.file_mime_type 
                  FROM notes n
                  JOIN emails_beans eb ON n.id = eb.bean_id
                  WHERE eb.email_id = '{$emailData['id']}' 
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
     * Check for duplicate deals using fuzzy matching
     */
    private function checkForDuplicateDeal($dealData)
    {
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
        
        $query = "SELECT o.id, o.name, o.amount, a.name as account_name,
                         o.date_entered, o.sales_stage
                  FROM opportunities o
                  LEFT JOIN accounts a ON o.account_id = a.id
                  WHERE o.deleted = 0
                  AND (" . implode(' OR ', $conditions) . ")
                  ORDER BY o.date_entered DESC
                  LIMIT 1";
        
        $result = $this->db->query($query);
        $row = $this->db->fetchByAssoc($result);
        
        if ($row) {
            // Calculate similarity score
            $score = $this->calculateSimilarityScore($dealData, $row);
            
            // If similarity is above threshold, consider it a duplicate
            if ($score > 0.7) {
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
        
        // Date proximity (deals created within 7 days)
        if (!empty($deal2['date_entered'])) {
            $daysDiff = abs(time() - strtotime($deal2['date_entered'])) / (60 * 60 * 24);
            if ($daysDiff <= 7) {
                $dateSimilarity = 1 - ($daysDiff / 7);
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
     * Clean email subject for deal name
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
        $industryMap = array(
            'tech' => 'Technology',
            'it' => 'Technology',
            'software' => 'Technology',
            'mfg' => 'Manufacturing',
            'manufacturing' => 'Manufacturing',
            'retail' => 'Retail',
            'ecommerce' => 'Retail',
            'healthcare' => 'Healthcare',
            'medical' => 'Healthcare',
            'finance' => 'Financial Services',
            'banking' => 'Financial Services',
            'realestate' => 'Real Estate',
            'property' => 'Real Estate'
        );
        
        $normalized = strtolower(trim($industry));
        
        return isset($industryMap[$normalized]) ? $industryMap[$normalized] : $industry;
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
}
?>