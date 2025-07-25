<?php
/**
 * CommunicationHistoryService
 * Tracks and manages all communication history for stakeholders
 * Provides comprehensive activity tracking and reporting
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once('custom/modules/Contacts/ContactRoleManager.php');

class CommunicationHistoryService
{
    // Communication type constants
    const TYPE_EMAIL = 'email';
    const TYPE_CALL = 'call';
    const TYPE_MEETING = 'meeting';
    const TYPE_NOTE = 'note';
    const TYPE_TASK = 'task';
    const TYPE_DOCUMENT = 'document';
    
    /**
     * Record a communication activity
     * @param string $contactId The contact ID
     * @param string $type Communication type
     * @param array $details Activity details
     * @return bool Success status
     */
    public static function recordCommunication($contactId, $type, $details = array())
    {
        global $db, $current_user;
        
        if (empty($contactId) || empty($type)) {
            return false;
        }
        
        // Update last contact date
        ContactRoleManager::updateLastContactDate($contactId, null, $type);
        
        // Create activity record based on type
        switch ($type) {
            case self::TYPE_EMAIL:
                return self::recordEmailActivity($contactId, $details);
            case self::TYPE_CALL:
                return self::recordCallActivity($contactId, $details);
            case self::TYPE_MEETING:
                return self::recordMeetingActivity($contactId, $details);
            case self::TYPE_NOTE:
                return self::recordNoteActivity($contactId, $details);
            case self::TYPE_TASK:
                return self::recordTaskActivity($contactId, $details);
            default:
                return false;
        }
    }
    
    /**
     * Get communication history for a contact
     * @param string $contactId The contact ID
     * @param int $limit Number of records to return
     * @param string $type Optional type filter
     * @return array Communication history
     */
    public static function getContactCommunicationHistory($contactId, $limit = 50, $type = null)
    {
        global $db;
        
        if (empty($contactId)) {
            return array();
        }
        
        $history = array();
        
        // Get emails
        if (!$type || $type == self::TYPE_EMAIL) {
            $query = "SELECT e.id, e.name as subject, e.date_sent as date_activity,
                             e.description, 'email' as type, e.status,
                             e.from_addr_name as from_name, e.to_addrs_names as to_names
                      FROM emails e
                      INNER JOIN emails_beans eb ON e.id = eb.email_id
                      WHERE eb.bean_module = 'Contacts' 
                      AND eb.bean_id = '{$db->quote($contactId)}'
                      AND e.deleted = 0 AND eb.deleted = 0
                      ORDER BY e.date_sent DESC
                      LIMIT {$limit}";
            
            $result = $db->query($query);
            while ($row = $db->fetchByAssoc($result)) {
                $row['date_display'] = $this->formatDate($row['date_activity']);
                $history[] = $row;
            }
        }
        
        // Get calls
        if (!$type || $type == self::TYPE_CALL) {
            $query = "SELECT c.id, c.name, c.date_start as date_activity,
                             c.description, 'call' as type, c.status,
                             c.duration_hours, c.duration_minutes, c.direction
                      FROM calls c
                      INNER JOIN calls_contacts cc ON c.id = cc.call_id
                      WHERE cc.contact_id = '{$db->quote($contactId)}'
                      AND c.deleted = 0 AND cc.deleted = 0
                      ORDER BY c.date_start DESC
                      LIMIT {$limit}";
            
            $result = $db->query($query);
            while ($row = $db->fetchByAssoc($result)) {
                $row['date_display'] = $this->formatDate($row['date_activity']);
                $row['duration'] = self::formatDuration($row['duration_hours'], $row['duration_minutes']);
                $history[] = $row;
            }
        }
        
        // Get meetings
        if (!$type || $type == self::TYPE_MEETING) {
            $query = "SELECT m.id, m.name, m.date_start as date_activity,
                             m.description, 'meeting' as type, m.status,
                             m.duration_hours, m.duration_minutes, m.location
                      FROM meetings m
                      INNER JOIN meetings_contacts mc ON m.id = mc.meeting_id
                      WHERE mc.contact_id = '{$db->quote($contactId)}'
                      AND m.deleted = 0 AND mc.deleted = 0
                      ORDER BY m.date_start DESC
                      LIMIT {$limit}";
            
            $result = $db->query($query);
            while ($row = $db->fetchByAssoc($result)) {
                $row['date_display'] = $this->formatDate($row['date_activity']);
                $row['duration'] = self::formatDuration($row['duration_hours'], $row['duration_minutes']);
                $history[] = $row;
            }
        }
        
        // Get notes
        if (!$type || $type == self::TYPE_NOTE) {
            $query = "SELECT n.id, n.name, n.date_entered as date_activity,
                             n.description, 'note' as type, '' as status
                      FROM notes n
                      WHERE n.parent_type = 'Contacts' 
                      AND n.parent_id = '{$db->quote($contactId)}'
                      AND n.deleted = 0
                      ORDER BY n.date_entered DESC
                      LIMIT {$limit}";
            
            $result = $db->query($query);
            while ($row = $db->fetchByAssoc($result)) {
                $row['date_display'] = $this->formatDate($row['date_activity']);
                $history[] = $row;
            }
        }
        
        // Sort all activities by date
        usort($history, function($a, $b) {
            return strtotime($b['date_activity']) - strtotime($a['date_activity']);
        });
        
        // Limit to requested number
        return array_slice($history, 0, $limit);
    }
    
    /**
     * Get communication summary for a deal
     * @param string $dealId The deal ID
     * @param int $days Number of days to look back
     * @return array Summary statistics
     */
    public static function getDealCommunicationSummary($dealId, $days = 30)
    {
        global $db;
        
        $cutoffDate = date('Y-m-d', strtotime("-{$days} days"));
        
        $summary = array(
            'total_communications' => 0,
            'emails_sent' => 0,
            'calls_made' => 0,
            'meetings_held' => 0,
            'stakeholders_contacted' => array(),
            'most_active_stakeholder' => null,
            'least_active_stakeholder' => null,
            'communication_timeline' => array()
        );
        
        // Get all stakeholders for the deal
        $stakeholderQuery = "SELECT c.id, c.first_name, c.last_name
                            FROM contacts c
                            INNER JOIN deals_contacts dc ON dc.contact_id = c.id
                            WHERE dc.deal_id = '{$db->quote($dealId)}'
                            AND c.deleted = 0 AND dc.deleted = 0";
        
        $stakeholderResult = $db->query($stakeholderQuery);
        $stakeholderCounts = array();
        
        while ($stakeholder = $db->fetchByAssoc($stakeholderResult)) {
            $stakeholderId = $stakeholder['id'];
            $stakeholderName = $stakeholder['first_name'] . ' ' . $stakeholder['last_name'];
            $count = 0;
            
            // Count emails
            $emailQuery = "SELECT COUNT(*) as count
                          FROM emails e
                          INNER JOIN emails_beans eb ON e.id = eb.email_id
                          WHERE eb.bean_module = 'Contacts' 
                          AND eb.bean_id = '{$stakeholderId}'
                          AND e.date_sent >= '{$cutoffDate}'
                          AND e.deleted = 0 AND eb.deleted = 0";
            
            $result = $db->query($emailQuery);
            $row = $db->fetchByAssoc($result);
            $emailCount = (int)$row['count'];
            $summary['emails_sent'] += $emailCount;
            $count += $emailCount;
            
            // Count calls
            $callQuery = "SELECT COUNT(*) as count
                         FROM calls c
                         INNER JOIN calls_contacts cc ON c.id = cc.call_id
                         WHERE cc.contact_id = '{$stakeholderId}'
                         AND c.date_start >= '{$cutoffDate}'
                         AND c.deleted = 0 AND cc.deleted = 0";
            
            $result = $db->query($callQuery);
            $row = $db->fetchByAssoc($result);
            $callCount = (int)$row['count'];
            $summary['calls_made'] += $callCount;
            $count += $callCount;
            
            // Count meetings
            $meetingQuery = "SELECT COUNT(*) as count
                            FROM meetings m
                            INNER JOIN meetings_contacts mc ON m.id = mc.meeting_id
                            WHERE mc.contact_id = '{$stakeholderId}'
                            AND m.date_start >= '{$cutoffDate}'
                            AND m.deleted = 0 AND mc.deleted = 0";
            
            $result = $db->query($meetingQuery);
            $row = $db->fetchByAssoc($result);
            $meetingCount = (int)$row['count'];
            $summary['meetings_held'] += $meetingCount;
            $count += $meetingCount;
            
            if ($count > 0) {
                $summary['stakeholders_contacted'][] = $stakeholderName;
                $stakeholderCounts[$stakeholderName] = $count;
            }
        }
        
        $summary['total_communications'] = $summary['emails_sent'] + 
                                          $summary['calls_made'] + 
                                          $summary['meetings_held'];
        
        // Determine most and least active stakeholders
        if (!empty($stakeholderCounts)) {
            arsort($stakeholderCounts);
            $keys = array_keys($stakeholderCounts);
            $summary['most_active_stakeholder'] = $keys[0] . ' (' . $stakeholderCounts[$keys[0]] . ' activities)';
            $summary['least_active_stakeholder'] = end($keys) . ' (' . $stakeholderCounts[end($keys)] . ' activities)';
        }
        
        // Build communication timeline
        $summary['communication_timeline'] = self::buildCommunicationTimeline($dealId, $days);
        
        return $summary;
    }
    
    /**
     * Build communication timeline for a deal
     * @param string $dealId The deal ID
     * @param int $days Number of days
     * @return array Timeline data
     */
    private static function buildCommunicationTimeline($dealId, $days)
    {
        global $db;
        
        $timeline = array();
        $cutoffDate = date('Y-m-d', strtotime("-{$days} days"));
        
        // Get all stakeholder IDs for the deal
        $stakeholderIds = array();
        $query = "SELECT contact_id FROM deals_contacts 
                  WHERE deal_id = '{$db->quote($dealId)}' AND deleted = 0";
        $result = $db->query($query);
        while ($row = $db->fetchByAssoc($result)) {
            $stakeholderIds[] = $row['contact_id'];
        }
        
        if (empty($stakeholderIds)) {
            return $timeline;
        }
        
        $stakeholderIdList = "'" . implode("','", $stakeholderIds) . "'";
        
        // Group communications by week
        for ($i = 0; $i < $days; $i += 7) {
            $weekStart = date('Y-m-d', strtotime("-" . ($i + 6) . " days"));
            $weekEnd = date('Y-m-d', strtotime("-{$i} days"));
            
            $weekData = array(
                'week' => date('M j', strtotime($weekStart)) . ' - ' . date('M j', strtotime($weekEnd)),
                'emails' => 0,
                'calls' => 0,
                'meetings' => 0
            );
            
            // Count emails for the week
            $query = "SELECT COUNT(*) as count
                      FROM emails e
                      INNER JOIN emails_beans eb ON e.id = eb.email_id
                      WHERE eb.bean_module = 'Contacts' 
                      AND eb.bean_id IN ({$stakeholderIdList})
                      AND e.date_sent BETWEEN '{$weekStart}' AND '{$weekEnd}'
                      AND e.deleted = 0 AND eb.deleted = 0";
            
            $result = $db->query($query);
            $row = $db->fetchByAssoc($result);
            $weekData['emails'] = (int)$row['count'];
            
            // Similar counts for calls and meetings...
            $weekData['total'] = $weekData['emails'] + $weekData['calls'] + $weekData['meetings'];
            
            $timeline[] = $weekData;
        }
        
        return array_reverse($timeline);
    }
    
    /**
     * Record email activity
     * @param string $contactId Contact ID
     * @param array $details Email details
     * @return bool Success
     */
    private static function recordEmailActivity($contactId, $details)
    {
        global $db, $current_user;
        
        $emailId = create_guid();
        $now = date('Y-m-d H:i:s');
        
        // Create email record
        $query = "INSERT INTO emails 
                 (id, name, date_entered, date_modified, modified_user_id, created_by,
                  description, deleted, assigned_user_id, type, status, date_sent)
                 VALUES 
                 ('{$emailId}', '{$db->quote($details['subject'])}', '{$now}', '{$now}',
                  '{$current_user->id}', '{$current_user->id}', '{$db->quote($details['body'])}',
                  0, '{$current_user->id}', 'out', 'sent', '{$now}')";
        
        $db->query($query);
        
        // Link to contact
        $linkId = create_guid();
        $linkQuery = "INSERT INTO emails_beans 
                     (id, email_id, bean_id, bean_module, deleted)
                     VALUES 
                     ('{$linkId}', '{$emailId}', '{$contactId}', 'Contacts', 0)";
        
        return $db->query($linkQuery);
    }
    
    /**
     * Record call activity
     * @param string $contactId Contact ID
     * @param array $details Call details
     * @return bool Success
     */
    private static function recordCallActivity($contactId, $details)
    {
        global $db, $current_user;
        
        $callId = create_guid();
        $now = date('Y-m-d H:i:s');
        
        // Create call record
        $query = "INSERT INTO calls 
                 (id, name, date_entered, date_modified, modified_user_id, created_by,
                  description, deleted, assigned_user_id, duration_hours, duration_minutes,
                  date_start, direction, status)
                 VALUES 
                 ('{$callId}', '{$db->quote($details['subject'])}', '{$now}', '{$now}',
                  '{$current_user->id}', '{$current_user->id}', '{$db->quote($details['notes'])}',
                  0, '{$current_user->id}', {$details['duration_hours']}, {$details['duration_minutes']},
                  '{$now}', '{$details['direction']}', 'Held')";
        
        $db->query($query);
        
        // Link to contact
        $linkId = create_guid();
        $linkQuery = "INSERT INTO calls_contacts 
                     (id, call_id, contact_id, deleted)
                     VALUES 
                     ('{$linkId}', '{$callId}', '{$contactId}', 0)";
        
        return $db->query($linkQuery);
    }
    
    /**
     * Record meeting activity
     * @param string $contactId Contact ID
     * @param array $details Meeting details
     * @return bool Success
     */
    private static function recordMeetingActivity($contactId, $details)
    {
        global $db, $current_user;
        
        $meetingId = create_guid();
        $now = date('Y-m-d H:i:s');
        
        // Create meeting record
        $query = "INSERT INTO meetings 
                 (id, name, date_entered, date_modified, modified_user_id, created_by,
                  description, deleted, assigned_user_id, location, duration_hours, 
                  duration_minutes, date_start, status)
                 VALUES 
                 ('{$meetingId}', '{$db->quote($details['subject'])}', '{$now}', '{$now}',
                  '{$current_user->id}', '{$current_user->id}', '{$db->quote($details['notes'])}',
                  0, '{$current_user->id}', '{$db->quote($details['location'])}',
                  {$details['duration_hours']}, {$details['duration_minutes']},
                  '{$details['date_start']}', 'Held')";
        
        $db->query($query);
        
        // Link to contact
        $linkId = create_guid();
        $linkQuery = "INSERT INTO meetings_contacts 
                     (id, meeting_id, contact_id, deleted)
                     VALUES 
                     ('{$linkId}', '{$meetingId}', '{$contactId}', 0)";
        
        return $db->query($linkQuery);
    }
    
    /**
     * Record note activity
     * @param string $contactId Contact ID
     * @param array $details Note details
     * @return bool Success
     */
    private static function recordNoteActivity($contactId, $details)
    {
        global $db, $current_user;
        
        $noteId = create_guid();
        $now = date('Y-m-d H:i:s');
        
        $query = "INSERT INTO notes 
                 (id, name, date_entered, date_modified, modified_user_id, created_by,
                  description, deleted, assigned_user_id, parent_type, parent_id)
                 VALUES 
                 ('{$noteId}', '{$db->quote($details['subject'])}', '{$now}', '{$now}',
                  '{$current_user->id}', '{$current_user->id}', '{$db->quote($details['note'])}',
                  0, '{$current_user->id}', 'Contacts', '{$contactId}')";
        
        return $db->query($query);
    }
    
    /**
     * Record task activity
     * @param string $contactId Contact ID
     * @param array $details Task details
     * @return bool Success
     */
    private static function recordTaskActivity($contactId, $details)
    {
        global $db, $current_user;
        
        $taskId = create_guid();
        $now = date('Y-m-d H:i:s');
        
        $query = "INSERT INTO tasks 
                 (id, name, date_entered, date_modified, modified_user_id, created_by,
                  description, deleted, assigned_user_id, status, date_due, parent_type, parent_id)
                 VALUES 
                 ('{$taskId}', '{$db->quote($details['subject'])}', '{$now}', '{$now}',
                  '{$current_user->id}', '{$current_user->id}', '{$db->quote($details['description'])}',
                  0, '{$current_user->id}', '{$details['status']}', '{$details['due_date']}',
                  'Contacts', '{$contactId}')";
        
        return $db->query($query);
    }
    
    /**
     * Format date for display
     * @param string $date Date string
     * @return string Formatted date
     */
    private static function formatDate($date)
    {
        if (empty($date)) {
            return '';
        }
        
        $timestamp = strtotime($date);
        $today = strtotime('today');
        $yesterday = strtotime('yesterday');
        
        if ($timestamp >= $today) {
            return 'Today ' . date('g:i A', $timestamp);
        } elseif ($timestamp >= $yesterday) {
            return 'Yesterday ' . date('g:i A', $timestamp);
        } else {
            return date('M j, Y g:i A', $timestamp);
        }
    }
    
    /**
     * Format duration
     * @param int $hours Hours
     * @param int $minutes Minutes
     * @return string Formatted duration
     */
    private static function formatDuration($hours, $minutes)
    {
        $duration = '';
        
        if ($hours > 0) {
            $duration .= $hours . ' hour' . ($hours > 1 ? 's' : '');
        }
        
        if ($minutes > 0) {
            if ($duration) $duration .= ' ';
            $duration .= $minutes . ' minute' . ($minutes > 1 ? 's' : '');
        }
        
        return $duration ?: '0 minutes';
    }
}