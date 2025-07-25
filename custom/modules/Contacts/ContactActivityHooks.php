<?php
/**
 * ContactActivityHooks
 * Logic hook handlers for automatic last contact date tracking
 * and contact role validation
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once('custom/modules/Contacts/ContactRoleManager.php');
require_once('custom/modules/Contacts/CommunicationHistoryService.php');

class ContactActivityHooks
{
    /**
     * Update last contact date when an activity is linked to a contact
     * @param SugarBean $bean The contact bean
     * @param string $event The event type
     * @param array $arguments Hook arguments
     */
    public function updateLastContactOnActivityLink($bean, $event, $arguments)
    {
        // Check if this is a relevant relationship
        $activityModules = array(
            'emails' => 'email',
            'calls' => 'call',
            'meetings' => 'meeting',
            'notes' => 'note',
            'tasks' => 'task'
        );
        
        $relatedModule = $arguments['related_module'];
        
        if (isset($activityModules[$relatedModule])) {
            $interactionType = $activityModules[$relatedModule];
            
            // Update last contact date
            ContactRoleManager::updateLastContactDate($bean->id, null, $interactionType);
            
            // Log the activity
            $GLOBALS['log']->info("ContactActivityHooks: Updated last contact date for {$bean->id} due to {$interactionType} link");
        }
    }
    
    /**
     * Track when a contact is directly updated
     * @param SugarBean $bean The contact bean
     * @param string $event The event type
     * @param array $arguments Hook arguments
     */
    public function trackContactUpdate($bean, $event, $arguments)
    {
        // Only track if this is an update (not new record)
        if (!$arguments['isUpdate']) {
            return;
        }
        
        // Check if any communication-related fields were changed
        $communicationFields = array('phone_work', 'phone_mobile', 'email1', 'description');
        $fieldsChanged = false;
        
        foreach ($communicationFields as $field) {
            if (isset($bean->fetched_row[$field]) && 
                $bean->fetched_row[$field] != $bean->$field) {
                $fieldsChanged = true;
                break;
            }
        }
        
        // If communication fields changed, record it as a note
        if ($fieldsChanged) {
            $details = array(
                'subject' => 'Contact Information Updated',
                'note' => 'Contact details were updated by ' . $GLOBALS['current_user']->name
            );
            
            CommunicationHistoryService::recordCommunication(
                $bean->id, 
                CommunicationHistoryService::TYPE_NOTE, 
                $details
            );
        }
    }
    
    /**
     * Validate contact role before save
     * @param SugarBean $bean The contact bean
     * @param string $event The event type
     * @param array $arguments Hook arguments
     */
    public function validateContactRole($bean, $event, $arguments)
    {
        // Check if contact_role_c field exists and is set
        if (isset($bean->contact_role_c) && !empty($bean->contact_role_c)) {
            if (!ContactRoleManager::isValidRole($bean->contact_role_c)) {
                // Log invalid role
                $GLOBALS['log']->error("ContactActivityHooks: Invalid role '{$bean->contact_role_c}' for contact {$bean->id}");
                
                // Clear invalid role
                $bean->contact_role_c = '';
                
                // Optionally, you could throw an exception here to prevent save
                // throw new SugarApiExceptionInvalidParameter("Invalid contact role specified");
            }
        }
    }
    
    /**
     * Hook for after an email is sent
     * Called from Emails module logic hooks
     * @param SugarBean $email The email bean
     * @param string $event The event type
     * @param array $arguments Hook arguments
     */
    public static function updateContactsAfterEmailSent($email, $event, $arguments)
    {
        // Get all contacts related to this email
        $email->load_relationship('contacts');
        $contactIds = $email->contacts->get();
        
        foreach ($contactIds as $contactId) {
            ContactRoleManager::updateLastContactDate($contactId, null, 'email');
        }
        
        $GLOBALS['log']->info("ContactActivityHooks: Updated last contact date for " . count($contactIds) . " contacts after email sent");
    }
    
    /**
     * Hook for after a call is logged
     * Called from Calls module logic hooks
     * @param SugarBean $call The call bean
     * @param string $event The event type
     * @param array $arguments Hook arguments
     */
    public static function updateContactsAfterCall($call, $event, $arguments)
    {
        // Only update if call status is 'Held'
        if ($call->status != 'Held') {
            return;
        }
        
        // Get all contacts related to this call
        $call->load_relationship('contacts');
        $contactIds = $call->contacts->get();
        
        foreach ($contactIds as $contactId) {
            ContactRoleManager::updateLastContactDate($contactId, $call->date_start, 'call');
        }
        
        $GLOBALS['log']->info("ContactActivityHooks: Updated last contact date for " . count($contactIds) . " contacts after call");
    }
    
    /**
     * Hook for after a meeting is held
     * Called from Meetings module logic hooks
     * @param SugarBean $meeting The meeting bean
     * @param string $event The event type
     * @param array $arguments Hook arguments
     */
    public static function updateContactsAfterMeeting($meeting, $event, $arguments)
    {
        // Only update if meeting status is 'Held'
        if ($meeting->status != 'Held') {
            return;
        }
        
        // Get all contacts related to this meeting
        $meeting->load_relationship('contacts');
        $contactIds = $meeting->contacts->get();
        
        foreach ($contactIds as $contactId) {
            ContactRoleManager::updateLastContactDate($contactId, $meeting->date_start, 'meeting');
        }
        
        $GLOBALS['log']->info("ContactActivityHooks: Updated last contact date for " . count($contactIds) . " contacts after meeting");
    }
}