<?php
/**
 * Logic hooks class for mdeal_Contacts module
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

class ContactLogicHooks
{
    /**
     * Validate hierarchy relationships to prevent circular references
     */
    public function validateHierarchy($bean, $event, $arguments)
    {
        if (empty($bean->reports_to_id) || $bean->reports_to_id == $bean->id) {
            return; // No hierarchy or self-reference
        }

        // Check for circular reference
        if (!$this->isValidHierarchy($bean)) {
            throw new Exception('Circular reference detected in reporting hierarchy');
        }
    }

    /**
     * Update interaction metrics when contact is modified
     */
    public function updateInteractionMetrics($bean, $event, $arguments)
    {
        if (!empty($bean->fetched_row)) {
            // Check if contact info changed
            if ($this->hasContactInfoChanged($bean)) {
                // Increment interaction count
                $bean->interaction_count = ($bean->fetched_row['interaction_count'] ?? 0) + 1;
                
                // Update response rate calculation
                $this->calculateResponseRate($bean);
            }
        }
    }

    /**
     * Set full name field for searches and displays
     */
    public function setFullName($bean, $event, $arguments)
    {
        $bean->full_name = trim($bean->first_name . ' ' . $bean->last_name);
    }

    /**
     * Update last interaction date
     */
    public function updateLastInteractionDate($bean, $event, $arguments)
    {
        if (!empty($bean->fetched_row) && $this->hasContactInfoChanged($bean)) {
            $bean->last_interaction_date = date('Y-m-d H:i:s');
            
            // Save without triggering hooks again
            $bean->save(false);
        }
    }

    /**
     * Send follow-up notifications based on interaction history
     */
    public function sendFollowUpNotifications($bean, $event, $arguments)
    {
        // Check if contact needs follow-up
        if ($bean->needsFollowUp()) {
            $this->createFollowUpTask($bean);
            $this->sendFollowUpNotification($bean);
        }
    }

    /**
     * Update related account's contact count
     */
    public function updateAccountContactCount($bean, $event, $arguments)
    {
        if (!empty($bean->account_id)) {
            $this->updateAccountMetrics($bean->account_id);
        }

        // Also update if account changed
        if (!empty($bean->fetched_row['account_id']) && 
            $bean->account_id != $bean->fetched_row['account_id']) {
            $this->updateAccountMetrics($bean->fetched_row['account_id']);
        }
    }

    /**
     * Prevent deletion of contacts with active relationships
     */
    public function preventDeletionWithActiveRelationships($bean, $event, $arguments)
    {
        // Check for active deal relationships
        $dealCount = $this->getRelatedRecordCount($bean->id, 'mdeal_contacts_deals', 'contact_id');
        if ($dealCount > 0) {
            throw new Exception('Cannot delete contact with active deal relationships. Please remove deal relationships first.');
        }

        // Check for direct reports
        $reportCount = $this->getDirectReportsCount($bean->id);
        if ($reportCount > 0) {
            throw new Exception('Cannot delete contact with direct reports. Please reassign direct reports first.');
        }
    }

    /**
     * Handle when contact is added to a deal
     */
    public function handleDealRelationshipAdded($bean, $event, $arguments)
    {
        if ($arguments['module'] == 'mdeal_Deals' && $arguments['related_module'] == 'mdeal_Contacts') {
            // Log the relationship
            $this->logRelationshipChange($arguments['id'], $arguments['related_id'], 'deal_added');
            
            // Update interaction metrics
            $this->incrementInteractionCount($arguments['related_id']);
        }
    }

    /**
     * Handle when contact is added to an account
     */
    public function handleAccountRelationshipAdded($bean, $event, $arguments)
    {
        if ($arguments['module'] == 'mdeal_Accounts' && $arguments['related_module'] == 'mdeal_Contacts') {
            // Update account metrics
            $this->updateAccountMetrics($arguments['id']);
            
            // Log the relationship
            $this->logRelationshipChange($arguments['id'], $arguments['related_id'], 'account_added');
        }
    }

    /**
     * Handle when relationships are removed
     */
    public function handleRelationshipRemoved($bean, $event, $arguments)
    {
        if ($arguments['related_module'] == 'mdeal_Contacts') {
            if ($arguments['module'] == 'mdeal_Deals') {
                $this->logRelationshipChange($arguments['id'], $arguments['related_id'], 'deal_removed');
            } elseif ($arguments['module'] == 'mdeal_Accounts') {
                $this->updateAccountMetrics($arguments['id']);
                $this->logRelationshipChange($arguments['id'], $arguments['related_id'], 'account_removed');
            }
        }
    }

    /**
     * Check if hierarchy is valid (no circular references)
     */
    protected function isValidHierarchy($bean)
    {
        $currentId = $bean->reports_to_id;
        $visited = [$bean->id];
        $maxDepth = 10;
        $depth = 0;

        while ($currentId && $depth < $maxDepth) {
            if (in_array($currentId, $visited)) {
                return false; // Circular reference
            }

            $visited[] = $currentId;

            // Get the next level up
            $contact = BeanFactory::getBean('mdeal_Contacts', $currentId);
            if (!$contact || empty($contact->reports_to_id)) {
                break;
            }

            $currentId = $contact->reports_to_id;
            $depth++;
        }

        return true;
    }

    /**
     * Check if contact information has changed
     */
    protected function hasContactInfoChanged($bean)
    {
        $contactFields = [
            'email_address', 'phone_work', 'phone_mobile', 'title', 
            'department', 'first_name', 'last_name', 'account_id'
        ];
        
        foreach ($contactFields as $field) {
            if (isset($bean->fetched_row[$field]) && 
                $bean->$field != $bean->fetched_row[$field]) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Calculate response rate based on email activities
     */
    protected function calculateResponseRate($bean)
    {
        // In a real implementation, this would query email activities
        // For now, use a simplified calculation
        if (!empty($bean->interaction_count) && $bean->interaction_count > 3) {
            // Mock calculation based on interaction patterns
            $baseRate = 25; // Base response rate
            $interactionBonus = min(50, $bean->interaction_count * 5);
            $bean->response_rate = min(95, $baseRate + $interactionBonus + rand(-10, 15));
        }
    }

    /**
     * Create follow-up task for contact
     */
    protected function createFollowUpTask($bean)
    {
        $task = BeanFactory::newBean('Tasks');
        $task->name = "Follow up with {$bean->first_name} {$bean->last_name}";
        $task->description = "Contact has not been contacted in over 30 days. Schedule follow-up.";
        $task->parent_type = 'mdeal_Contacts';
        $task->parent_id = $bean->id;
        $task->assigned_user_id = $bean->assigned_user_id;
        $task->priority = 'Medium';
        $task->status = 'Not Started';
        
        // Set due date to tomorrow
        $dueDate = new DateTime();
        $dueDate->add(new DateInterval('P1D'));
        $task->date_due = $dueDate->format('Y-m-d');
        
        $task->save();
        
        return $task->id;
    }

    /**
     * Send follow-up notification email
     */
    protected function sendFollowUpNotification($bean)
    {
        if (empty($bean->assigned_user_id)) {
            return;
        }

        $user = BeanFactory::getBean('Users', $bean->assigned_user_id);
        if (!$user || empty($user->email1)) {
            return;
        }

        require_once('include/SugarPHPMailer.php');
        
        $mail = new SugarPHPMailer();
        $mail->setMailerForSystem();
        
        $mail->AddAddress($user->email1, $user->full_name);
        $mail->Subject = "Follow-up Needed: {$bean->first_name} {$bean->last_name}";
        
        $daysSince = $bean->getDaysSinceLastInteraction();
        
        $body = "
        <p>Dear {$user->first_name},</p>
        
        <p>The contact <strong>{$bean->first_name} {$bean->last_name}</strong> needs follow-up attention.</p>
        
        <p><strong>Contact Details:</strong></p>
        <ul>
            <li>Name: {$bean->first_name} {$bean->last_name}</li>
            <li>Title: {$bean->title}</li>
            <li>Account: {$bean->account_name}</li>
            <li>Days since last interaction: {$daysSince}</li>
            <li>Interaction count: {$bean->interaction_count}</li>
        </ul>
        
        <p><a href=\"{$GLOBALS['sugar_config']['site_url']}/index.php?module=mdeal_Contacts&action=DetailView&record={$bean->id}\">View Contact</a></p>
        
        <p>Best regards,<br/>MakeDeal CRM</p>
        ";
        
        $mail->Body = $body;
        $mail->isHTML(true);
        
        try {
            $mail->send();
        } catch (Exception $e) {
            $GLOBALS['log']->error("Failed to send follow-up notification: " . $e->getMessage());
        }
    }

    /**
     * Update account contact metrics
     */
    protected function updateAccountMetrics($accountId)
    {
        if (empty($accountId)) {
            return;
        }

        // Count contacts for this account
        $contactCount = $this->getRelatedRecordCount($accountId, 'mdeal_contacts', 'account_id');
        
        // Update account with contact count (assuming account has this field)
        $account = BeanFactory::getBean('mdeal_Accounts', $accountId);
        if ($account) {
            $account->contact_count = $contactCount;
            $account->save(false); // Don't trigger hooks
        }
    }

    /**
     * Get count of related records
     */
    protected function getRelatedRecordCount($id, $table, $foreignKey)
    {
        global $db;
        
        $query = "SELECT COUNT(*) as count FROM {$table} WHERE {$foreignKey} = ? AND deleted = 0";
        $result = $db->pQuery($query, [$id]);
        $row = $db->fetchByAssoc($result);
        
        return $row['count'] ?? 0;
    }

    /**
     * Get count of direct reports
     */
    protected function getDirectReportsCount($contactId)
    {
        return $this->getRelatedRecordCount($contactId, 'mdeal_contacts', 'reports_to_id');
    }

    /**
     * Increment interaction count for a contact
     */
    protected function incrementInteractionCount($contactId)
    {
        global $db;
        
        $query = "UPDATE mdeal_contacts 
                  SET interaction_count = COALESCE(interaction_count, 0) + 1,
                      last_interaction_date = NOW()
                  WHERE id = ? AND deleted = 0";
        
        $db->pQuery($query, [$contactId]);
    }

    /**
     * Log relationship changes
     */
    protected function logRelationshipChange($parentId, $contactId, $changeType)
    {
        $GLOBALS['log']->info(
            "Contact relationship change: {$changeType} - Parent: {$parentId}, Contact: {$contactId}"
        );
        
        // In a full implementation, this could write to an audit table
        // or trigger workflow notifications
    }
}