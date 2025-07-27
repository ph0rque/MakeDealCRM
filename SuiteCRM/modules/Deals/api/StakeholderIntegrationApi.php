<?php
/**
 * Stakeholder Integration API
 * 
 * Provides endpoints for integrating stakeholder tracking with the Deal pipeline
 * Manages contact relationships, roles, and communication history
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

// Check if RestService exists before requiring it
if (file_exists('include/api/RestService.php')) {
    require_once 'include/api/RestService.php';
    $baseClass = 'RestService';
} else {
    // Fallback to a basic class if RestService doesn't exist
    $baseClass = 'stdClass';
}

class StakeholderIntegrationApi extends stdClass
{
    /**
     * Register API endpoints
     */
    public function registerApiRest()
    {
        return array(
            'getStakeholders' => array(
                'reqType' => 'GET',
                'path' => array('Deals', 'stakeholders', '?'),
                'pathVars' => array('', '', 'deal_id'),
                'method' => 'getStakeholders',
                'shortHelp' => 'Get stakeholders for a deal',
                'longHelp' => 'Returns all contacts associated with a deal with their roles and communication history',
            ),
            'addStakeholder' => array(
                'reqType' => 'POST',
                'path' => array('Deals', 'stakeholders'),
                'pathVars' => array('', ''),
                'method' => 'addStakeholder',
                'shortHelp' => 'Add a stakeholder to a deal',
                'longHelp' => 'Associates a contact with a deal and assigns a role',
            ),
            'updateStakeholderRole' => array(
                'reqType' => 'PUT',
                'path' => array('Deals', 'stakeholders', '?'),
                'pathVars' => array('', '', 'relationship_id'),
                'method' => 'updateStakeholderRole',
                'shortHelp' => 'Update stakeholder role',
                'longHelp' => 'Updates the role of a contact in a deal',
            ),
            'removeStakeholder' => array(
                'reqType' => 'DELETE',
                'path' => array('Deals', 'stakeholders', '?'),
                'pathVars' => array('', '', 'relationship_id'),
                'method' => 'removeStakeholder',
                'shortHelp' => 'Remove a stakeholder from a deal',
                'longHelp' => 'Removes the association between a contact and a deal',
            ),
            'bulkUpdateStakeholders' => array(
                'reqType' => 'PUT',
                'path' => array('Deals', 'stakeholders', 'bulk'),
                'pathVars' => array('', '', ''),
                'method' => 'bulkUpdateStakeholders',
                'shortHelp' => 'Bulk update stakeholders',
                'longHelp' => 'Add or update multiple stakeholders for multiple deals',
            ),
            'getStakeholderCommunication' => array(
                'reqType' => 'GET',
                'path' => array('Deals', 'stakeholders', '?', 'communication'),
                'pathVars' => array('', '', 'deal_id', ''),
                'method' => 'getStakeholderCommunication',
                'shortHelp' => 'Get communication history for deal stakeholders',
                'longHelp' => 'Returns emails, calls, meetings related to deal stakeholders',
            ),
        );
    }

    /**
     * Get all stakeholders for a deal
     */
    public function getStakeholders($api, $args)
    {
        global $db;
        
        $deal_id = $db->quote($args['deal_id']);
        
        $query = "SELECT 
                    oc.id as relationship_id,
                    oc.contact_id,
                    oc.contact_role,
                    oc.date_modified,
                    c.first_name,
                    c.last_name,
                    c.title,
                    c.phone_work,
                    c.phone_mobile,
                    c.email1 as email,
                    a.name as account_name,
                    c.account_id,
                    c.description,
                    c.department,
                    c.assigned_user_id,
                    CONCAT(u.first_name, ' ', u.last_name) as assigned_user_name
                FROM opportunities_contacts oc
                INNER JOIN contacts c ON oc.contact_id = c.id AND c.deleted = 0
                LEFT JOIN accounts a ON c.account_id = a.id AND a.deleted = 0
                LEFT JOIN users u ON c.assigned_user_id = u.id
                WHERE oc.opportunity_id = '{$deal_id}'
                AND oc.deleted = 0
                ORDER BY 
                    CASE oc.contact_role
                        WHEN 'Decision Maker' THEN 1
                        WHEN 'Executive Champion' THEN 2
                        WHEN 'Technical Evaluator' THEN 3
                        WHEN 'Business User' THEN 4
                        WHEN 'Other' THEN 5
                        ELSE 6
                    END,
                    c.last_name, c.first_name";
        
        $result = $db->query($query);
        $stakeholders = array();
        
        while ($row = $db->fetchByAssoc($result)) {
            // Get communication count for each stakeholder
            $comm_count = $this->getStakeholderCommunicationCount($row['contact_id'], $deal_id);
            $row['communication_count'] = $comm_count;
            
            // Get last activity date
            $row['last_activity_date'] = $this->getLastActivityDate($row['contact_id'], $deal_id);
            
            $stakeholders[] = $row;
        }
        
        return array(
            'success' => true,
            'stakeholders' => $stakeholders,
            'total' => count($stakeholders)
        );
    }

    /**
     * Add a stakeholder to a deal
     */
    public function addStakeholder($api, $args)
    {
        global $db, $current_user;
        
        $required = array('deal_id', 'contact_id', 'contact_role');
        foreach ($required as $field) {
            if (empty($args[$field])) {
                return array('success' => false, 'error' => "Missing required field: {$field}");
            }
        }
        
        // Check if relationship already exists
        $existing = $this->checkExistingRelationship($args['deal_id'], $args['contact_id']);
        if ($existing) {
            return array('success' => false, 'error' => 'This stakeholder is already associated with this deal');
        }
        
        $id = create_guid();
        $now = $db->now();
        
        $query = "INSERT INTO opportunities_contacts 
                  (id, opportunity_id, contact_id, contact_role, date_modified, deleted)
                  VALUES ('{$id}', '{$db->quote($args['deal_id'])}', '{$db->quote($args['contact_id'])}', 
                          '{$db->quote($args['contact_role'])}', {$now}, 0)";
        
        $db->query($query);
        
        // Log the activity
        $this->logStakeholderActivity('added', $args['deal_id'], $args['contact_id'], $args['contact_role']);
        
        return array(
            'success' => true,
            'relationship_id' => $id,
            'message' => 'Stakeholder added successfully'
        );
    }

    /**
     * Update stakeholder role
     */
    public function updateStakeholderRole($api, $args)
    {
        global $db;
        
        if (empty($args['contact_role'])) {
            return array('success' => false, 'error' => 'Missing required field: contact_role');
        }
        
        $query = "UPDATE opportunities_contacts 
                  SET contact_role = '{$db->quote($args['contact_role'])}',
                      date_modified = {$db->now()}
                  WHERE id = '{$db->quote($args['relationship_id'])}'
                  AND deleted = 0";
        
        $db->query($query);
        
        return array(
            'success' => true,
            'message' => 'Stakeholder role updated successfully'
        );
    }

    /**
     * Remove stakeholder from deal
     */
    public function removeStakeholder($api, $args)
    {
        global $db;
        
        $query = "UPDATE opportunities_contacts 
                  SET deleted = 1,
                      date_modified = {$db->now()}
                  WHERE id = '{$db->quote($args['relationship_id'])}'";
        
        $db->query($query);
        
        return array(
            'success' => true,
            'message' => 'Stakeholder removed successfully'
        );
    }

    /**
     * Bulk update stakeholders
     */
    public function bulkUpdateStakeholders($api, $args)
    {
        if (empty($args['operations']) || !is_array($args['operations'])) {
            return array('success' => false, 'error' => 'No operations provided');
        }
        
        $results = array();
        $success_count = 0;
        $error_count = 0;
        
        foreach ($args['operations'] as $operation) {
            try {
                switch ($operation['action']) {
                    case 'add':
                        $result = $this->addStakeholder($api, $operation);
                        break;
                    case 'update':
                        $result = $this->updateStakeholderRole($api, $operation);
                        break;
                    case 'remove':
                        $result = $this->removeStakeholder($api, $operation);
                        break;
                    default:
                        $result = array('success' => false, 'error' => 'Invalid action');
                }
                
                if ($result['success']) {
                    $success_count++;
                } else {
                    $error_count++;
                }
                
                $results[] = $result;
            } catch (Exception $e) {
                $error_count++;
                $results[] = array('success' => false, 'error' => $e->getMessage());
            }
        }
        
        return array(
            'success' => $error_count === 0,
            'results' => $results,
            'summary' => array(
                'total' => count($args['operations']),
                'success' => $success_count,
                'errors' => $error_count
            )
        );
    }

    /**
     * Get communication history for deal stakeholders
     */
    public function getStakeholderCommunication($api, $args)
    {
        global $db;
        
        $deal_id = $db->quote($args['deal_id']);
        $limit = isset($args['limit']) ? (int)$args['limit'] : 50;
        $offset = isset($args['offset']) ? (int)$args['offset'] : 0;
        
        // Get all stakeholder IDs
        $stakeholder_ids = $this->getStakeholderIds($deal_id);
        if (empty($stakeholder_ids)) {
            return array('success' => true, 'communications' => array(), 'total' => 0);
        }
        
        $contact_ids = "'" . implode("','", $stakeholder_ids) . "'";
        
        // Combined query for emails, calls, and meetings
        $query = "
            (SELECT 
                'email' as type,
                e.id,
                e.name as subject,
                e.date_sent as date_start,
                e.date_sent as date_end,
                e.status,
                e.assigned_user_id,
                eabr.bean_id as contact_id,
                CONCAT(u.first_name, ' ', u.last_name) as assigned_user_name
            FROM emails e
            INNER JOIN emails_beans eabr ON e.id = eabr.email_id 
                AND eabr.bean_module = 'Contacts' 
                AND eabr.bean_id IN ({$contact_ids})
                AND eabr.deleted = 0
            LEFT JOIN users u ON e.assigned_user_id = u.id
            WHERE e.deleted = 0
            AND e.date_sent IS NOT NULL)
            
            UNION ALL
            
            (SELECT 
                'call' as type,
                c.id,
                c.name as subject,
                c.date_start,
                c.date_end,
                c.status,
                c.assigned_user_id,
                cc.contact_id,
                CONCAT(u.first_name, ' ', u.last_name) as assigned_user_name
            FROM calls c
            INNER JOIN calls_contacts cc ON c.id = cc.call_id 
                AND cc.contact_id IN ({$contact_ids})
                AND cc.deleted = 0
            LEFT JOIN users u ON c.assigned_user_id = u.id
            WHERE c.deleted = 0)
            
            UNION ALL
            
            (SELECT 
                'meeting' as type,
                m.id,
                m.name as subject,
                m.date_start,
                m.date_end,
                m.status,
                m.assigned_user_id,
                mc.contact_id,
                CONCAT(u.first_name, ' ', u.last_name) as assigned_user_name
            FROM meetings m
            INNER JOIN meetings_contacts mc ON m.id = mc.meeting_id 
                AND mc.contact_id IN ({$contact_ids})
                AND mc.deleted = 0
            LEFT JOIN users u ON m.assigned_user_id = u.id
            WHERE m.deleted = 0)
            
            ORDER BY date_start DESC
            LIMIT {$limit} OFFSET {$offset}";
        
        $result = $db->query($query);
        $communications = array();
        
        while ($row = $db->fetchByAssoc($result)) {
            // Get contact name
            $contact_info = $this->getContactInfo($row['contact_id']);
            $row['contact_name'] = $contact_info['name'];
            $row['contact_role'] = $contact_info['role'];
            
            $communications[] = $row;
        }
        
        // Get total count
        $count_query = str_replace("LIMIT {$limit} OFFSET {$offset}", "", $query);
        $count_result = $db->query("SELECT COUNT(*) as total FROM ({$count_query}) as comm");
        $count_row = $db->fetchByAssoc($count_result);
        
        return array(
            'success' => true,
            'communications' => $communications,
            'total' => (int)$count_row['total'],
            'limit' => $limit,
            'offset' => $offset
        );
    }

    /**
     * Helper: Check if relationship exists
     */
    private function checkExistingRelationship($deal_id, $contact_id)
    {
        global $db;
        
        $query = "SELECT id FROM opportunities_contacts 
                  WHERE opportunity_id = '{$db->quote($deal_id)}'
                  AND contact_id = '{$db->quote($contact_id)}'
                  AND deleted = 0";
        
        $result = $db->query($query);
        return $db->fetchByAssoc($result) !== false;
    }

    /**
     * Helper: Get stakeholder communication count
     */
    private function getStakeholderCommunicationCount($contact_id, $deal_id)
    {
        global $db;
        
        $count = 0;
        
        // Count emails
        $query = "SELECT COUNT(*) as count FROM emails e
                  INNER JOIN emails_beans eb ON e.id = eb.email_id
                  WHERE eb.bean_module = 'Contacts' 
                  AND eb.bean_id = '{$contact_id}'
                  AND e.deleted = 0 AND eb.deleted = 0";
        $result = $db->query($query);
        $row = $db->fetchByAssoc($result);
        $count += (int)$row['count'];
        
        // Count calls
        $query = "SELECT COUNT(*) as count FROM calls c
                  INNER JOIN calls_contacts cc ON c.id = cc.call_id
                  WHERE cc.contact_id = '{$contact_id}'
                  AND c.deleted = 0 AND cc.deleted = 0";
        $result = $db->query($query);
        $row = $db->fetchByAssoc($result);
        $count += (int)$row['count'];
        
        // Count meetings
        $query = "SELECT COUNT(*) as count FROM meetings m
                  INNER JOIN meetings_contacts mc ON m.id = mc.meeting_id
                  WHERE mc.contact_id = '{$contact_id}'
                  AND m.deleted = 0 AND mc.deleted = 0";
        $result = $db->query($query);
        $row = $db->fetchByAssoc($result);
        $count += (int)$row['count'];
        
        return $count;
    }

    /**
     * Helper: Get last activity date
     */
    private function getLastActivityDate($contact_id, $deal_id)
    {
        global $db;
        
        $dates = array();
        
        // Last email
        $query = "SELECT MAX(e.date_sent) as last_date FROM emails e
                  INNER JOIN emails_beans eb ON e.id = eb.email_id
                  WHERE eb.bean_module = 'Contacts' 
                  AND eb.bean_id = '{$contact_id}'
                  AND e.deleted = 0 AND eb.deleted = 0";
        $result = $db->query($query);
        $row = $db->fetchByAssoc($result);
        if ($row['last_date']) $dates[] = $row['last_date'];
        
        // Last call
        $query = "SELECT MAX(c.date_start) as last_date FROM calls c
                  INNER JOIN calls_contacts cc ON c.id = cc.call_id
                  WHERE cc.contact_id = '{$contact_id}'
                  AND c.deleted = 0 AND cc.deleted = 0";
        $result = $db->query($query);
        $row = $db->fetchByAssoc($result);
        if ($row['last_date']) $dates[] = $row['last_date'];
        
        // Last meeting
        $query = "SELECT MAX(m.date_start) as last_date FROM meetings m
                  INNER JOIN meetings_contacts mc ON m.id = mc.meeting_id
                  WHERE mc.contact_id = '{$contact_id}'
                  AND m.deleted = 0 AND mc.deleted = 0";
        $result = $db->query($query);
        $row = $db->fetchByAssoc($result);
        if ($row['last_date']) $dates[] = $row['last_date'];
        
        if (empty($dates)) return null;
        
        return max($dates);
    }

    /**
     * Helper: Get stakeholder IDs for a deal
     */
    private function getStakeholderIds($deal_id)
    {
        global $db;
        
        $query = "SELECT contact_id FROM opportunities_contacts 
                  WHERE opportunity_id = '{$deal_id}' 
                  AND deleted = 0";
        
        $result = $db->query($query);
        $ids = array();
        
        while ($row = $db->fetchByAssoc($result)) {
            $ids[] = $row['contact_id'];
        }
        
        return $ids;
    }

    /**
     * Helper: Get contact info
     */
    private function getContactInfo($contact_id)
    {
        global $db;
        
        $query = "SELECT c.first_name, c.last_name, oc.contact_role
                  FROM contacts c
                  LEFT JOIN opportunities_contacts oc ON c.id = oc.contact_id AND oc.deleted = 0
                  WHERE c.id = '{$contact_id}'
                  AND c.deleted = 0
                  LIMIT 1";
        
        $result = $db->query($query);
        $row = $db->fetchByAssoc($result);
        
        return array(
            'name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'role' => $row['contact_role'] ?: 'Other'
        );
    }

    /**
     * Helper: Log stakeholder activity
     */
    private function logStakeholderActivity($action, $deal_id, $contact_id, $role)
    {
        // This could be expanded to create audit log entries
        // For now, we'll rely on the date_modified field
    }
}