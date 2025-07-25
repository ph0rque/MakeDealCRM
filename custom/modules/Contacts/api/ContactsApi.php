<?php
/**
 * ContactsApi
 * REST API endpoints for stakeholder management
 * Provides API access to contact role management and communication tracking
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once('include/api/SugarApi.php');
require_once('custom/modules/Contacts/ContactRoleManager.php');
require_once('custom/modules/Contacts/StakeholderRelationshipService.php');
require_once('custom/modules/Contacts/EmailTemplateManager.php');
require_once('custom/modules/Contacts/CommunicationHistoryService.php');

class ContactsApi extends SugarApi
{
    public function registerApiRest()
    {
        return array(
            // Contact role management endpoints
            'getContactsByRole' => array(
                'reqType' => 'GET',
                'path' => array('Contacts', 'byRole', '?'),
                'pathVars' => array('', '', 'role'),
                'method' => 'getContactsByRole',
                'shortHelp' => 'Get contacts by role',
                'longHelp' => 'Returns all contacts with the specified role',
            ),
            
            'updateContactRole' => array(
                'reqType' => 'PUT',
                'path' => array('Contacts', '?', 'role'),
                'pathVars' => array('', 'contact_id', ''),
                'method' => 'updateContactRole',
                'shortHelp' => 'Update contact role',
                'longHelp' => 'Updates the role for a specific contact',
            ),
            
            // Last contact tracking endpoints
            'getInactiveContacts' => array(
                'reqType' => 'GET',
                'path' => array('Contacts', 'inactive', '?'),
                'pathVars' => array('', '', 'days'),
                'method' => 'getInactiveContacts',
                'shortHelp' => 'Get contacts not contacted in X days',
                'longHelp' => 'Returns contacts who have not been contacted in specified days',
            ),
            
            'updateLastContact' => array(
                'reqType' => 'POST',
                'path' => array('Contacts', '?', 'lastContact'),
                'pathVars' => array('', 'contact_id', ''),
                'method' => 'updateLastContact',
                'shortHelp' => 'Update last contact date',
                'longHelp' => 'Updates the last contact date for a contact',
            ),
            
            'getUpcomingFollowUps' => array(
                'reqType' => 'GET',
                'path' => array('Contacts', 'followups'),
                'pathVars' => array('', ''),
                'method' => 'getUpcomingFollowUps',
                'shortHelp' => 'Get upcoming follow-ups',
                'longHelp' => 'Returns contacts with upcoming follow-up dates',
            ),
            
            // Stakeholder relationship endpoints
            'getDealStakeholders' => array(
                'reqType' => 'GET',
                'path' => array('Deals', '?', 'stakeholders'),
                'pathVars' => array('', 'deal_id', ''),
                'method' => 'getDealStakeholders',
                'shortHelp' => 'Get stakeholders for a deal',
                'longHelp' => 'Returns all stakeholders associated with a deal',
            ),
            
            'addStakeholderToDeal' => array(
                'reqType' => 'POST',
                'path' => array('Deals', '?', 'stakeholders'),
                'pathVars' => array('', 'deal_id', ''),
                'method' => 'addStakeholderToDeal',
                'shortHelp' => 'Add stakeholder to deal',
                'longHelp' => 'Associates a contact as stakeholder to a deal',
            ),
            
            'removeStakeholderFromDeal' => array(
                'reqType' => 'DELETE',
                'path' => array('Deals', '?', 'stakeholders', '?'),
                'pathVars' => array('', 'deal_id', '', 'contact_id'),
                'method' => 'removeStakeholderFromDeal',
                'shortHelp' => 'Remove stakeholder from deal',
                'longHelp' => 'Removes a stakeholder association from a deal',
            ),
            
            // Communication history endpoints
            'getContactCommunicationHistory' => array(
                'reqType' => 'GET',
                'path' => array('Contacts', '?', 'communications'),
                'pathVars' => array('', 'contact_id', ''),
                'method' => 'getContactCommunicationHistory',
                'shortHelp' => 'Get communication history',
                'longHelp' => 'Returns communication history for a contact',
            ),
            
            'recordCommunication' => array(
                'reqType' => 'POST',
                'path' => array('Contacts', '?', 'communications'),
                'pathVars' => array('', 'contact_id', ''),
                'method' => 'recordCommunication',
                'shortHelp' => 'Record communication activity',
                'longHelp' => 'Records a new communication activity for a contact',
            ),
            
            'getDealCommunicationSummary' => array(
                'reqType' => 'GET',
                'path' => array('Deals', '?', 'communications', 'summary'),
                'pathVars' => array('', 'deal_id', '', ''),
                'method' => 'getDealCommunicationSummary',
                'shortHelp' => 'Get deal communication summary',
                'longHelp' => 'Returns communication summary for all stakeholders in a deal',
            ),
            
            // Email template endpoints
            'generateIntroductionEmail' => array(
                'reqType' => 'POST',
                'path' => array('Deals', '?', 'emails', 'introduction'),
                'pathVars' => array('', 'deal_id', '', ''),
                'method' => 'generateIntroductionEmail',
                'shortHelp' => 'Generate introduction email',
                'longHelp' => 'Generates multi-party introduction email for deal stakeholders',
            ),
            
            'generateFollowUpEmail' => array(
                'reqType' => 'POST',
                'path' => array('Contacts', '?', 'emails', 'followup'),
                'pathVars' => array('', 'contact_id', '', ''),
                'method' => 'generateFollowUpEmail',
                'shortHelp' => 'Generate follow-up email',
                'longHelp' => 'Generates follow-up email template for a contact',
            ),
        );
    }
    
    /**
     * Get contacts by role
     */
    public function getContactsByRole($api, $args)
    {
        $role = $args['role'];
        
        if (!ContactRoleManager::isValidRole($role)) {
            throw new SugarApiExceptionInvalidParameter('Invalid role specified');
        }
        
        $contacts = ContactRoleManager::getContactsByRole($role);
        
        return array(
            'role' => $role,
            'role_display_name' => ContactRoleManager::getRoleDisplayName($role),
            'count' => count($contacts),
            'contacts' => $contacts
        );
    }
    
    /**
     * Update contact role
     */
    public function updateContactRole($api, $args)
    {
        $contactId = $args['contact_id'];
        $role = $args['role'] ?? null;
        
        if (!ContactRoleManager::isValidRole($role)) {
            throw new SugarApiExceptionInvalidParameter('Invalid role specified');
        }
        
        $success = ContactRoleManager::updateContactRole($contactId, $role);
        
        return array(
            'success' => $success,
            'contact_id' => $contactId,
            'role' => $role
        );
    }
    
    /**
     * Get inactive contacts
     */
    public function getInactiveContacts($api, $args)
    {
        $days = (int)$args['days'];
        $role = $args['role'] ?? null;
        
        if ($days <= 0) {
            throw new SugarApiExceptionInvalidParameter('Days must be greater than 0');
        }
        
        $contacts = ContactRoleManager::getContactsNotContactedInDays($days, $role);
        
        return array(
            'days' => $days,
            'role' => $role,
            'count' => count($contacts),
            'contacts' => $contacts
        );
    }
    
    /**
     * Update last contact date
     */
    public function updateLastContact($api, $args)
    {
        $contactId = $args['contact_id'];
        $date = $args['date'] ?? null;
        $interactionType = $args['interaction_type'] ?? null;
        
        $success = ContactRoleManager::updateLastContactDate($contactId, $date, $interactionType);
        
        return array(
            'success' => $success,
            'contact_id' => $contactId,
            'last_contact_date' => $date ?: date('Y-m-d H:i:s'),
            'interaction_type' => $interactionType
        );
    }
    
    /**
     * Get upcoming follow-ups
     */
    public function getUpcomingFollowUps($api, $args)
    {
        $role = $args['role'] ?? null;
        $daysAhead = $args['days_ahead'] ?? 7;
        
        $contacts = ContactRoleManager::getUpcomingFollowUps($role, $daysAhead);
        
        return array(
            'days_ahead' => $daysAhead,
            'role' => $role,
            'count' => count($contacts),
            'contacts' => $contacts
        );
    }
    
    /**
     * Get deal stakeholders
     */
    public function getDealStakeholders($api, $args)
    {
        $dealId = $args['deal_id'];
        $role = $args['role'] ?? null;
        
        $stakeholders = StakeholderRelationshipService::getDealStakeholders($dealId, $role);
        $summary = StakeholderRelationshipService::getDealStakeholderSummary($dealId);
        
        return array(
            'deal_id' => $dealId,
            'summary' => $summary,
            'stakeholders' => $stakeholders
        );
    }
    
    /**
     * Add stakeholder to deal
     */
    public function addStakeholderToDeal($api, $args)
    {
        $dealId = $args['deal_id'];
        $contactId = $args['contact_id'];
        $role = $args['role'] ?? null;
        
        if (empty($contactId)) {
            throw new SugarApiExceptionInvalidParameter('Contact ID is required');
        }
        
        $success = StakeholderRelationshipService::addStakeholderToDeal($dealId, $contactId, $role);
        
        return array(
            'success' => $success,
            'deal_id' => $dealId,
            'contact_id' => $contactId,
            'role' => $role
        );
    }
    
    /**
     * Remove stakeholder from deal
     */
    public function removeStakeholderFromDeal($api, $args)
    {
        $dealId = $args['deal_id'];
        $contactId = $args['contact_id'];
        
        $success = StakeholderRelationshipService::removeStakeholderFromDeal($dealId, $contactId);
        
        return array(
            'success' => $success,
            'deal_id' => $dealId,
            'contact_id' => $contactId
        );
    }
    
    /**
     * Get contact communication history
     */
    public function getContactCommunicationHistory($api, $args)
    {
        $contactId = $args['contact_id'];
        $limit = $args['limit'] ?? 50;
        $type = $args['type'] ?? null;
        
        $history = CommunicationHistoryService::getContactCommunicationHistory($contactId, $limit, $type);
        $stats = ContactRoleManager::getContactCommunicationStats($contactId);
        
        return array(
            'contact_id' => $contactId,
            'stats' => $stats,
            'history' => $history
        );
    }
    
    /**
     * Record communication
     */
    public function recordCommunication($api, $args)
    {
        $contactId = $args['contact_id'];
        $type = $args['type'];
        $details = $args['details'] ?? array();
        
        if (empty($type)) {
            throw new SugarApiExceptionInvalidParameter('Communication type is required');
        }
        
        $success = CommunicationHistoryService::recordCommunication($contactId, $type, $details);
        
        return array(
            'success' => $success,
            'contact_id' => $contactId,
            'type' => $type
        );
    }
    
    /**
     * Get deal communication summary
     */
    public function getDealCommunicationSummary($api, $args)
    {
        $dealId = $args['deal_id'];
        $days = $args['days'] ?? 30;
        
        $summary = CommunicationHistoryService::getDealCommunicationSummary($dealId, $days);
        
        return array(
            'deal_id' => $dealId,
            'days' => $days,
            'summary' => $summary
        );
    }
    
    /**
     * Generate introduction email
     */
    public function generateIntroductionEmail($api, $args)
    {
        $dealId = $args['deal_id'];
        $recipientIds = $args['recipient_ids'] ?? array();
        $options = $args['options'] ?? array();
        
        if (empty($recipientIds)) {
            throw new SugarApiExceptionInvalidParameter('Recipient IDs are required');
        }
        
        $emailData = EmailTemplateManager::generateIntroductionEmail($dealId, $recipientIds, $options);
        
        return $emailData;
    }
    
    /**
     * Generate follow-up email
     */
    public function generateFollowUpEmail($api, $args)
    {
        $contactId = $args['contact_id'];
        $dealId = $args['deal_id'] ?? null;
        $options = $args['options'] ?? array();
        
        if (empty($dealId)) {
            throw new SugarApiExceptionInvalidParameter('Deal ID is required');
        }
        
        $emailData = EmailTemplateManager::generateFollowUpEmail($dealId, $contactId, $options);
        
        return $emailData;
    }
}