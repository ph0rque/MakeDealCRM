<?php
/**
 * StakeholderRelationshipService
 * Manages relationships between deals and contacts (stakeholders)
 * Provides functionality for stakeholder management within deals
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once('custom/modules/Contacts/ContactRoleManager.php');

class StakeholderRelationshipService
{
    /**
     * Add a stakeholder to a deal
     * @param string $dealId The deal ID
     * @param string $contactId The contact ID
     * @param string $role The stakeholder role
     * @param array $additionalData Additional relationship data
     * @return bool Success status
     */
    public static function addStakeholderToDeal($dealId, $contactId, $role = null, $additionalData = array())
    {
        global $db;
        
        if (empty($dealId) || empty($contactId)) {
            return false;
        }
        
        // First, check if relationship already exists
        $existingQuery = "SELECT id FROM deals_contacts 
                         WHERE deal_id = '{$db->quote($dealId)}' 
                         AND contact_id = '{$db->quote($contactId)}' 
                         AND deleted = 0";
        
        $result = $db->query($existingQuery);
        if ($db->fetchByAssoc($result)) {
            // Relationship exists, update it instead
            return self::updateStakeholderRelationship($dealId, $contactId, $role, $additionalData);
        }
        
        // Create new relationship
        $id = create_guid();
        $now = date('Y-m-d H:i:s');
        
        $insertQuery = "INSERT INTO deals_contacts 
                       (id, deal_id, contact_id, date_modified, deleted) 
                       VALUES 
                       ('{$id}', '{$db->quote($dealId)}', '{$db->quote($contactId)}', '{$now}', 0)";
        
        $success = $db->query($insertQuery);
        
        // Update contact role if provided
        if ($success && $role && ContactRoleManager::isValidRole($role)) {
            ContactRoleManager::updateContactRole($contactId, $role);
        }
        
        // Log the addition
        if ($success) {
            self::logStakeholderActivity($dealId, $contactId, 'added', $role);
        }
        
        return $success;
    }
    
    /**
     * Remove a stakeholder from a deal
     * @param string $dealId The deal ID
     * @param string $contactId The contact ID
     * @return bool Success status
     */
    public static function removeStakeholderFromDeal($dealId, $contactId)
    {
        global $db;
        
        if (empty($dealId) || empty($contactId)) {
            return false;
        }
        
        $query = "UPDATE deals_contacts 
                  SET deleted = 1, date_modified = NOW() 
                  WHERE deal_id = '{$db->quote($dealId)}' 
                  AND contact_id = '{$db->quote($contactId)}'";
        
        $success = $db->query($query);
        
        // Log the removal
        if ($success) {
            self::logStakeholderActivity($dealId, $contactId, 'removed');
        }
        
        return $success;
    }
    
    /**
     * Update stakeholder relationship
     * @param string $dealId The deal ID
     * @param string $contactId The contact ID
     * @param string $role The stakeholder role
     * @param array $additionalData Additional relationship data
     * @return bool Success status
     */
    public static function updateStakeholderRelationship($dealId, $contactId, $role = null, $additionalData = array())
    {
        global $db;
        
        if (empty($dealId) || empty($contactId)) {
            return false;
        }
        
        // Update the relationship timestamp
        $query = "UPDATE deals_contacts 
                  SET date_modified = NOW() 
                  WHERE deal_id = '{$db->quote($dealId)}' 
                  AND contact_id = '{$db->quote($contactId)}' 
                  AND deleted = 0";
        
        $success = $db->query($query);
        
        // Update contact role if provided
        if ($success && $role && ContactRoleManager::isValidRole($role)) {
            ContactRoleManager::updateContactRole($contactId, $role);
        }
        
        // Log the update
        if ($success) {
            self::logStakeholderActivity($dealId, $contactId, 'updated', $role);
        }
        
        return $success;
    }
    
    /**
     * Get all stakeholders for a deal
     * @param string $dealId The deal ID
     * @param string $role Optional role filter
     * @return array Array of stakeholders
     */
    public static function getDealStakeholders($dealId, $role = null)
    {
        global $db;
        
        if (empty($dealId)) {
            return array();
        }
        
        $query = "SELECT c.id, c.first_name, c.last_name, c.email1, c.phone_work, 
                         c.contact_role_c, c.account_name, c.title,
                         cc.last_contact_date_c, cc.last_interaction_type_c,
                         dc.date_modified as relationship_date
                  FROM contacts c
                  INNER JOIN deals_contacts dc ON dc.contact_id = c.id
                  LEFT JOIN contacts_cstm cc ON c.id = cc.id_c
                  WHERE dc.deal_id = '{$db->quote($dealId)}' 
                  AND c.deleted = 0 
                  AND dc.deleted = 0";
        
        if ($role && ContactRoleManager::isValidRole($role)) {
            $query .= " AND c.contact_role_c = '{$db->quote($role)}'";
        }
        
        $query .= " ORDER BY c.contact_role_c, c.last_name, c.first_name";
        
        $result = $db->query($query);
        $stakeholders = array();
        
        while ($row = $db->fetchByAssoc($result)) {
            $row['role_display_name'] = ContactRoleManager::getRoleDisplayName($row['contact_role_c']);
            $row['communication_stats'] = ContactRoleManager::getContactCommunicationStats($row['id']);
            $stakeholders[] = $row;
        }
        
        return $stakeholders;
    }
    
    /**
     * Get stakeholder summary for a deal
     * @param string $dealId The deal ID
     * @return array Summary statistics
     */
    public static function getDealStakeholderSummary($dealId)
    {
        global $db;
        
        if (empty($dealId)) {
            return array();
        }
        
        $summary = array(
            'total_stakeholders' => 0,
            'by_role' => array(),
            'recent_communications' => array(),
            'overdue_followups' => 0
        );
        
        // Get stakeholder count by role
        $query = "SELECT c.contact_role_c, COUNT(*) as count
                  FROM contacts c
                  INNER JOIN deals_contacts dc ON dc.contact_id = c.id
                  WHERE dc.deal_id = '{$db->quote($dealId)}' 
                  AND c.deleted = 0 
                  AND dc.deleted = 0
                  GROUP BY c.contact_role_c";
        
        $result = $db->query($query);
        while ($row = $db->fetchByAssoc($result)) {
            $roleName = ContactRoleManager::getRoleDisplayName($row['contact_role_c']);
            $summary['by_role'][$roleName] = (int)$row['count'];
            $summary['total_stakeholders'] += (int)$row['count'];
        }
        
        // Get recent communications
        $query = "SELECT c.id, c.first_name, c.last_name, 
                         cc.last_contact_date_c, cc.last_interaction_type_c
                  FROM contacts c
                  INNER JOIN deals_contacts dc ON dc.contact_id = c.id
                  LEFT JOIN contacts_cstm cc ON c.id = cc.id_c
                  WHERE dc.deal_id = '{$db->quote($dealId)}' 
                  AND c.deleted = 0 
                  AND dc.deleted = 0
                  AND cc.last_contact_date_c IS NOT NULL
                  ORDER BY cc.last_contact_date_c DESC
                  LIMIT 5";
        
        $result = $db->query($query);
        while ($row = $db->fetchByAssoc($result)) {
            $summary['recent_communications'][] = $row;
        }
        
        // Count overdue follow-ups
        $query = "SELECT COUNT(*) as count
                  FROM contacts c
                  INNER JOIN deals_contacts dc ON dc.contact_id = c.id
                  INNER JOIN contacts_cstm cc ON c.id = cc.id_c
                  WHERE dc.deal_id = '{$db->quote($dealId)}' 
                  AND c.deleted = 0 
                  AND dc.deleted = 0
                  AND cc.follow_up_date_c < CURDATE()
                  AND cc.follow_up_date_c IS NOT NULL";
        
        $result = $db->query($query);
        $row = $db->fetchByAssoc($result);
        $summary['overdue_followups'] = (int)$row['count'];
        
        return $summary;
    }
    
    /**
     * Bulk add stakeholders to a deal
     * @param string $dealId The deal ID
     * @param array $stakeholders Array of stakeholder data (contactId => role)
     * @return array Results array with success/failure for each
     */
    public static function bulkAddStakeholders($dealId, $stakeholders)
    {
        $results = array();
        
        foreach ($stakeholders as $contactId => $role) {
            $success = self::addStakeholderToDeal($dealId, $contactId, $role);
            $results[$contactId] = $success;
        }
        
        return $results;
    }
    
    /**
     * Get deals for a contact
     * @param string $contactId The contact ID
     * @param bool $activeOnly Show only active deals
     * @return array Array of deals
     */
    public static function getDealsForContact($contactId, $activeOnly = true)
    {
        global $db;
        
        if (empty($contactId)) {
            return array();
        }
        
        $query = "SELECT d.id, d.name, d.sales_stage, d.amount, d.date_closed,
                         dc.date_modified as relationship_date
                  FROM opportunities d
                  INNER JOIN deals_contacts dc ON dc.deal_id = d.id
                  WHERE dc.contact_id = '{$db->quote($contactId)}' 
                  AND d.deleted = 0 
                  AND dc.deleted = 0";
        
        if ($activeOnly) {
            $query .= " AND d.sales_stage NOT IN ('Closed Won', 'Closed Lost')";
        }
        
        $query .= " ORDER BY d.date_entered DESC";
        
        $result = $db->query($query);
        $deals = array();
        
        while ($row = $db->fetchByAssoc($result)) {
            $deals[] = $row;
        }
        
        return $deals;
    }
    
    /**
     * Log stakeholder activity
     * @param string $dealId The deal ID
     * @param string $contactId The contact ID
     * @param string $action The action performed
     * @param string $role Optional role
     */
    private static function logStakeholderActivity($dealId, $contactId, $action, $role = null)
    {
        global $db, $current_user;
        
        $id = create_guid();
        $now = date('Y-m-d H:i:s');
        $userId = $current_user ? $current_user->id : null;
        
        $description = "Stakeholder {$action}";
        if ($role) {
            $description .= " with role: " . ContactRoleManager::getRoleDisplayName($role);
        }
        
        // Log to audit table if available
        $query = "INSERT INTO audit_log 
                 (id, parent_id, parent_type, field_name, data_type, 
                  before_value_string, after_value_string, created_by, date_created)
                 VALUES 
                 ('{$id}', '{$db->quote($dealId)}', 'Opportunities', 'stakeholder_change', 'text',
                  '', '{$db->quote($description)}', '{$userId}', '{$now}')";
        
        // Execute query but don't fail if audit table doesn't exist
        @$db->query($query);
    }
    
    /**
     * Get stakeholder communication matrix for a deal
     * @param string $dealId The deal ID
     * @return array Communication matrix
     */
    public static function getStakeholderCommunicationMatrix($dealId)
    {
        $stakeholders = self::getDealStakeholders($dealId);
        $matrix = array();
        
        foreach ($stakeholders as $stakeholder) {
            $stats = ContactRoleManager::getContactCommunicationStats($stakeholder['id']);
            
            $matrix[] = array(
                'contact_id' => $stakeholder['id'],
                'name' => $stakeholder['first_name'] . ' ' . $stakeholder['last_name'],
                'role' => $stakeholder['role_display_name'],
                'last_contact' => $stats['last_contact_date'],
                'days_since_contact' => $stats['days_since_contact'],
                'total_interactions' => $stats['total_interactions'],
                'emails' => $stats['emails'],
                'calls' => $stats['calls'],
                'meetings' => $stats['meetings']
            );
        }
        
        // Sort by days since contact (longest first)
        usort($matrix, function($a, $b) {
            $aDays = $a['days_since_contact'] ?: 9999;
            $bDays = $b['days_since_contact'] ?: 9999;
            return $bDays - $aDays;
        });
        
        return $matrix;
    }
}