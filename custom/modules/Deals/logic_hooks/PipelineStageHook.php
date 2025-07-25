<?php
/**
 * Pipeline Stage Hook Class
 * 
 * Handles pipeline stage transitions and history tracking
 * 
 * @package MakeDealCRM
 * @module Deals
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

class PipelineStageHook
{
    /**
     * Update stage history after deal save
     * 
     * @param SugarBean $bean The Deal bean instance
     * @param string $event The event type
     * @param array $arguments Additional arguments
     */
    public function updateStageHistory($bean, $event, $arguments)
    {
        global $db, $current_user;
        
        // Check if this is an update and pipeline stage has changed
        if (!$arguments['isUpdate']) {
            return;
        }
        
        $oldStage = isset($bean->fetched_row['pipeline_stage_c']) ? 
                    $bean->fetched_row['pipeline_stage_c'] : null;
        $newStage = $bean->pipeline_stage_c;
        
        // Only log if stage actually changed
        if ($oldStage === $newStage || empty($newStage)) {
            return;
        }
        
        // Create history entry
        $historyId = create_guid();
        $timestamp = date('Y-m-d H:i:s');
        
        $query = "INSERT INTO pipeline_stage_history 
                  (id, deal_id, old_stage, new_stage, changed_by, date_changed, notes, deleted) 
                  VALUES 
                  (?, ?, ?, ?, ?, ?, ?, 0)";
        
        $stmt = $db->getConnection()->prepare($query);
        $notes = !empty($bean->pipeline_notes_c) ? $bean->pipeline_notes_c : '';
        
        $stmt->execute([
            $historyId,
            $bean->id,
            $oldStage,
            $newStage,
            $current_user->id,
            $timestamp,
            $notes
        ]);
        
        // Update stage metrics
        $this->updateStageMetrics($bean, $oldStage, $newStage);
        
        // Send notifications if configured
        $this->sendStageChangeNotifications($bean, $oldStage, $newStage);
        
        // Log activity
        $GLOBALS['log']->info("Deal {$bean->id} moved from stage '{$oldStage}' to '{$newStage}' by user {$current_user->id}");
    }
    
    /**
     * Update stage metrics for reporting
     * 
     * @param SugarBean $bean The Deal bean
     * @param string $oldStage Previous stage
     * @param string $newStage New stage
     */
    protected function updateStageMetrics($bean, $oldStage, $newStage)
    {
        global $db;
        
        // Calculate time spent in old stage
        if (!empty($bean->stage_entered_date_c)) {
            $enterDate = new DateTime($bean->stage_entered_date_c);
            $exitDate = new DateTime();
            $duration = $exitDate->diff($enterDate);
            $daysInStage = $duration->days;
            
            // Store stage duration metric
            $metricId = create_guid();
            $query = "INSERT INTO pipeline_stage_metrics 
                      (id, deal_id, stage, duration_days, date_recorded, deleted) 
                      VALUES 
                      (?, ?, ?, ?, NOW(), 0)";
            
            $stmt = $db->getConnection()->prepare($query);
            $stmt->execute([
                $metricId,
                $bean->id,
                $oldStage,
                $daysInStage
            ]);
        }
    }
    
    /**
     * Send notifications for stage changes
     * 
     * @param SugarBean $bean The Deal bean
     * @param string $oldStage Previous stage
     * @param string $newStage New stage
     */
    protected function sendStageChangeNotifications($bean, $oldStage, $newStage)
    {
        // Check if notifications are enabled
        $config = SugarConfig::getInstance();
        if (!$config->get('deals.stage_change_notifications', false)) {
            return;
        }
        
        // Get assigned user
        if (!empty($bean->assigned_user_id)) {
            $assignedUser = BeanFactory::getBean('Users', $bean->assigned_user_id);
            
            if ($assignedUser && !empty($assignedUser->email1)) {
                // Create notification
                require_once 'include/SugarPHPMailer.php';
                
                $mail = new SugarPHPMailer();
                $mail->setMailerForSystem();
                $mail->Subject = "Deal Stage Change: {$bean->name}";
                $mail->Body = $this->getNotificationBody($bean, $oldStage, $newStage);
                $mail->IsHTML(true);
                $mail->AddAddress($assignedUser->email1);
                
                // Send notification
                if (!$mail->Send()) {
                    $GLOBALS['log']->error("Failed to send stage change notification: " . $mail->ErrorInfo);
                }
            }
        }
    }
    
    /**
     * Generate notification email body
     * 
     * @param SugarBean $bean The Deal bean
     * @param string $oldStage Previous stage
     * @param string $newStage New stage
     * @return string HTML email body
     */
    protected function getNotificationBody($bean, $oldStage, $newStage)
    {
        global $sugar_config;
        
        $siteUrl = rtrim($sugar_config['site_url'], '/');
        $dealUrl = "{$siteUrl}/index.php?module=Deals&action=DetailView&record={$bean->id}";
        
        $body = "<h3>Deal Stage Changed</h3>";
        $body .= "<p><strong>Deal:</strong> <a href='{$dealUrl}'>{$bean->name}</a></p>";
        $body .= "<p><strong>Previous Stage:</strong> {$oldStage}</p>";
        $body .= "<p><strong>New Stage:</strong> {$newStage}</p>";
        
        if (!empty($bean->pipeline_notes_c)) {
            $body .= "<p><strong>Notes:</strong> {$bean->pipeline_notes_c}</p>";
        }
        
        $body .= "<p><strong>Changed By:</strong> {$GLOBALS['current_user']->full_name}</p>";
        $body .= "<p><strong>Date/Time:</strong> " . date('Y-m-d H:i:s') . "</p>";
        
        return $body;
    }
}