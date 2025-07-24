<?php
/**
 * Logic hooks class for mdeal_Leads module
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

class LeadLogicHooks
{
    /**
     * Update qualification score when relevant fields change
     */
    public function updateQualificationScore($bean, $event, $arguments)
    {
        if (empty($bean->id)) {
            // New record, calculate initial score
            $bean->calculateQualificationScore();
        } else {
            // Check if scoring-relevant fields changed
            $scoringFields = ['industry', 'annual_revenue', 'status', 'lead_source'];
            $fieldsChanged = false;
            
            foreach ($scoringFields as $field) {
                if (isset($bean->fetched_row[$field]) && 
                    $bean->$field != $bean->fetched_row[$field]) {
                    $fieldsChanged = true;
                    break;
                }
            }
            
            if ($fieldsChanged) {
                $bean->calculateQualificationScore();
            }
        }
    }

    /**
     * Update pipeline stage timing data
     */
    public function updatePipelineStageData($bean, $event, $arguments)
    {
        // Track pipeline stage changes
        if (!empty($bean->pipeline_stage)) {
            if (empty($bean->fetched_row['pipeline_stage']) || 
                $bean->pipeline_stage != $bean->fetched_row['pipeline_stage']) {
                
                // Stage changed, update timing
                $bean->date_entered_stage = date('Y-m-d H:i:s');
                $bean->days_in_stage = 0;
                
                // Log the stage transition
                $this->logStageTransition($bean);
            }
        }
    }

    /**
     * Update last activity date based on related activities
     */
    public function updateLastActivityDate($bean, $event, $arguments)
    {
        // This will be called by activities when they're created/modified
        // For now, just update to current time when lead is modified
        if (!empty($bean->id) && $bean->fetched_row) {
            $bean->last_activity_date = date('Y-m-d H:i:s');
            
            // Don't trigger hooks again for this update
            $bean->save(false);
        }
    }

    /**
     * Create follow-up tasks based on pipeline stage
     */
    public function createFollowUpTasks($bean, $event, $arguments)
    {
        // Only for stage transitions
        if (!empty($bean->pipeline_stage) && 
            !empty($bean->fetched_row['pipeline_stage']) &&
            $bean->pipeline_stage != $bean->fetched_row['pipeline_stage']) {
            
            $this->createStageSpecificTasks($bean);
        }
    }

    /**
     * Send notifications on stage transitions
     */
    public function sendStageNotifications($bean, $event, $arguments)
    {
        // Check for stage changes
        if (!empty($bean->pipeline_stage) && 
            !empty($bean->fetched_row['pipeline_stage']) &&
            $bean->pipeline_stage != $bean->fetched_row['pipeline_stage']) {
            
            $this->sendNotificationEmail($bean);
        }
    }

    /**
     * Prevent deletion of converted leads
     */
    public function preventConvertedLeadDeletion($bean, $event, $arguments)
    {
        if (!empty($bean->converted_deal_id)) {
            global $mod_strings;
            sugar_die($mod_strings['ERR_CONVERTED_LEAD'] ?? 'Cannot delete a converted lead.');
        }
    }

    /**
     * Log stage transitions to audit table
     */
    protected function logStageTransition($bean)
    {
        if (empty($bean->fetched_row['pipeline_stage'])) {
            return; // New record, no transition to log
        }

        $fromStage = $bean->fetched_row['pipeline_stage'];
        $toStage = $bean->pipeline_stage;
        
        global $current_user;
        $userId = $current_user->id ?? '';

        // Insert into pipeline_transitions table (if it exists)
        $sql = "INSERT INTO pipeline_transitions 
                (id, deal_id, from_stage, to_stage, transition_date, transition_by, transition_type, reason) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            create_guid(),
            $bean->id,
            $fromStage,
            $toStage,
            date('Y-m-d H:i:s'),
            $userId,
            'manual',
            'Lead stage transition'
        ];

        try {
            $bean->db->pQuery($sql, $params);
        } catch (Exception $e) {
            $GLOBALS['log']->error("Failed to log stage transition: " . $e->getMessage());
        }
    }

    /**
     * Create tasks specific to pipeline stage
     */
    protected function createStageSpecificTasks($bean)
    {
        $stageTasks = $this->getStageTaskTemplates();
        
        if (!isset($stageTasks[$bean->pipeline_stage])) {
            return;
        }

        $tasks = $stageTasks[$bean->pipeline_stage];
        
        foreach ($tasks as $taskTemplate) {
            $task = BeanFactory::newBean('Tasks');
            $task->name = $taskTemplate['name'];
            $task->description = $taskTemplate['description'];
            $task->parent_type = 'mdeal_Leads';
            $task->parent_id = $bean->id;
            $task->assigned_user_id = $bean->assigned_user_id;
            $task->priority = $taskTemplate['priority'];
            $task->status = 'Not Started';
            
            // Calculate due date
            $dueDate = new DateTime();
            $dueDate->add(new DateInterval('P' . $taskTemplate['days_from_now'] . 'D'));
            $task->date_due = $dueDate->format('Y-m-d');
            
            $task->save();
        }
    }

    /**
     * Get task templates for each stage
     */
    protected function getStageTaskTemplates()
    {
        return [
            'initial_contact' => [
                [
                    'name' => 'Research Company Background',
                    'description' => 'Research company history, ownership, and basic financials',
                    'priority' => 'Medium',
                    'days_from_now' => 1
                ],
                [
                    'name' => 'Initial Outreach Call',
                    'description' => 'Make initial contact call to introduce services',
                    'priority' => 'High',
                    'days_from_now' => 2
                ]
            ],
            'qualification' => [
                [
                    'name' => 'Verify Financial Information',
                    'description' => 'Verify annual revenue and employee count',
                    'priority' => 'High',
                    'days_from_now' => 3
                ],
                [
                    'name' => 'Assess Seller Motivation',
                    'description' => 'Understand timeline and motivation for potential sale',
                    'priority' => 'High',
                    'days_from_now' => 5
                ]
            ],
            'initial_interest' => [
                [
                    'name' => 'Schedule Management Meeting',
                    'description' => 'Set up meeting with key decision makers',
                    'priority' => 'High',
                    'days_from_now' => 7
                ],
                [
                    'name' => 'Prepare NDA',
                    'description' => 'Prepare and send non-disclosure agreement',
                    'priority' => 'Medium',
                    'days_from_now' => 5
                ]
            ],
            'ready_to_convert' => [
                [
                    'name' => 'Create Deal Record',
                    'description' => 'Convert lead to deal and create initial deal structure',
                    'priority' => 'High',
                    'days_from_now' => 1
                ]
            ]
        ];
    }

    /**
     * Send notification email on stage transitions
     */
    protected function sendNotificationEmail($bean)
    {
        global $current_user;
        
        // Get assigned user
        if (!empty($bean->assigned_user_id) && $bean->assigned_user_id != $current_user->id) {
            $assignedUser = BeanFactory::getBean('Users', $bean->assigned_user_id);
            
            if ($assignedUser && !empty($assignedUser->email1)) {
                $this->sendStageTransitionEmail($bean, $assignedUser);
            }
        }
        
        // Notify manager if stage is critical
        if (in_array($bean->pipeline_stage, ['ready_to_convert', 'initial_interest'])) {
            $this->notifyManager($bean);
        }
    }

    /**
     * Send stage transition email
     */
    protected function sendStageTransitionEmail($bean, $user)
    {
        require_once('include/SugarPHPMailer.php');
        
        $mail = new SugarPHPMailer();
        $mail->setMailerForSystem();
        
        $mail->AddAddress($user->email1, $user->full_name);
        $mail->Subject = "Lead Stage Updated: {$bean->company_name}";
        
        $body = "
        <p>Dear {$user->first_name},</p>
        
        <p>The lead <strong>{$bean->company_name}</strong> has been moved to stage: <strong>{$bean->pipeline_stage}</strong></p>
        
        <p><strong>Lead Details:</strong></p>
        <ul>
            <li>Company: {$bean->company_name}</li>
            <li>Contact: {$bean->first_name} {$bean->last_name}</li>
            <li>New Stage: {$bean->pipeline_stage}</li>
            <li>Qualification Score: {$bean->qualification_score}%</li>
        </ul>
        
        <p><a href=\"{$GLOBALS['sugar_config']['site_url']}/index.php?module=mdeal_Leads&action=DetailView&record={$bean->id}\">View Lead</a></p>
        
        <p>Best regards,<br/>MakeDeal CRM</p>
        ";
        
        $mail->Body = $body;
        $mail->isHTML(true);
        
        try {
            $mail->send();
        } catch (Exception $e) {
            $GLOBALS['log']->error("Failed to send stage transition email: " . $e->getMessage());
        }
    }

    /**
     * Notify manager of critical stage transitions
     */
    protected function notifyManager($bean)
    {
        // This would implement manager notification logic
        // For now, just log the event
        $GLOBALS['log']->info("Lead {$bean->company_name} reached critical stage: {$bean->pipeline_stage}");
    }
}