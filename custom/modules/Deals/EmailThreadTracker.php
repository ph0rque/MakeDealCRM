<?php
/**
 * Email Thread Tracker
 * Tracks email conversations and links them to deals
 * 
 * @package MakeDealCRM
 * @subpackage Deals
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

class EmailThreadTracker
{
    private $db;
    private $log;
    private $threadCache = array();
    
    public function __construct()
    {
        global $db, $log;
        $this->db = $db;
        $this->log = $log;
        $this->ensureTableExists();
    }
    
    /**
     * Ensure the thread tracking table exists
     */
    private function ensureTableExists()
    {
        $query = "CREATE TABLE IF NOT EXISTS email_thread_deals (
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
        
        $this->db->query($query);
    }
    
    /**
     * Get thread information for an email
     * 
     * @param SugarBean $email
     * @return array|null Thread info including deal_id
     */
    public function getThreadInfo($email)
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
     * Track email in thread
     * 
     * @param SugarBean $email
     * @param string $dealId
     * @return string Thread ID
     */
    public function trackEmail($email, $dealId)
    {
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
        
        $this->log->info("EmailThreadTracker: Tracked email {$email->id} in thread {$threadId} for deal {$dealId}");
        
        return $threadId;
    }
    
    /**
     * Get all emails in a thread
     * 
     * @param string $threadId
     * @return array Email IDs in thread
     */
    public function getThreadEmails($threadId)
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
    
    /**
     * Get conversation history for a deal
     * 
     * @param string $dealId
     * @return array Thread information
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
        // Clean subject for comparison
        $cleanSubject = $this->cleanSubject($email->name);
        
        if (empty($cleanSubject)) {
            return null;
        }
        
        // Look for similar subjects in recent emails (last 7 days)
        $dateLimit = date('Y-m-d H:i:s', strtotime('-7 days'));
        
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
     * Clean subject for comparison
     */
    private function cleanSubject($subject)
    {
        // Remove common prefixes
        $subject = preg_replace('/^(Re:|RE:|Fwd:|FWD:|Fw:|FW:)\s*/i', '', $subject);
        
        // Remove extra whitespace
        $subject = trim(preg_replace('/\s+/', ' ', $subject));
        
        return $subject;
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
}
?>