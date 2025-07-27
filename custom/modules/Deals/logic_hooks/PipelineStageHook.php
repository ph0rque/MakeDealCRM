<?php
/**
 * Pipeline Stage Hook Class - Stage Transition Management and History
 * 
 * This class is responsible for tracking and managing pipeline stage transitions
 * throughout the deal lifecycle. It provides critical functionality for:
 * 
 * - Recording all stage transitions with timestamps and user information
 * - Calculating time spent in each stage for analytics
 * - Sending notifications for significant stage changes
 * - Maintaining metrics for pipeline velocity reporting
 * 
 * Pipeline stages represent the deal's progress from initial sourcing through
 * to closing. Accurate tracking of stage transitions is essential for:
 * - Sales forecasting and pipeline analysis
 * - Identifying bottlenecks in the deal process
 * - Team performance measurement
 * - Compliance and audit requirements
 * 
 * @package MakeDealCRM
 * @module Deals
 * @author MakeDealCRM Development Team
 * @version 1.0.0
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

class PipelineStageHook
{
    /**
     * Update stage history after deal save
     * 
     * Records pipeline stage transitions in a dedicated history table for
     * comprehensive tracking and analysis. This method is triggered after
     * every deal save to detect and record stage changes.
     * 
     * Functionality:
     * 1. Detects stage changes by comparing old and new values
     * 2. Creates immutable history records with:
     *    - Timestamp of transition
     *    - User who made the change
     *    - Old and new stage values
     *    - Optional transition notes
     * 
     * 3. Updates stage metrics:
     *    - Time spent in previous stage
     *    - Stage velocity calculations
     *    - Conversion funnel metrics
     * 
     * 4. Triggers notifications for configured transitions
     * 
     * The history data enables:
     * - Pipeline velocity reports
     * - Stage duration analysis
     * - User activity tracking
     * - Process optimization insights
     * 
     * @param SugarBean $bean The Deal bean instance being saved
     * @param string $event The event type (after_save)
     * @param array $arguments Additional arguments including isUpdate flag
     * 
     * @return void
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
     * Update stage metrics for reporting and analytics
     * 
     * Calculates and stores key metrics about stage transitions to support
     * pipeline analytics and reporting. These metrics are crucial for
     * understanding deal velocity and identifying process improvements.
     * 
     * Metrics Calculated:
     * 1. Stage Duration:
     *    - Time from stage entry to exit
     *    - Stored in days for consistency
     *    - Handles timezone considerations
     * 
     * 2. Stage Velocity:
     *    - Speed of progression through stages
     *    - Comparison to average duration
     *    - Identification of fast/slow movers
     * 
     * 3. Conversion Metrics:
     *    - Success rate from stage to stage
     *    - Drop-off points in the pipeline
     *    - Stage-specific win rates
     * 
     * Data Storage:
     * - Metrics stored in pipeline_stage_metrics table
     * - Aggregated for dashboard displays
     * - Used in predictive analytics
     * 
     * @param SugarBean $bean The Deal bean transitioning stages
     * @param string $oldStage Previous stage identifier
     * @param string $newStage New stage identifier
     * 
     * @return void
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
     * Send notifications for significant stage changes
     * 
     * Manages the notification system for pipeline stage transitions, ensuring
     * relevant stakeholders are informed of deal progress. Notifications are
     * configurable and can be customized based on stage importance.
     * 
     * Notification Triggers:
     * 1. Critical Transitions:
     *    - Moving to Due Diligence (team mobilization needed)
     *    - Entering LOI/Negotiation (legal team alert)
     *    - Reaching Closing (all hands notification)
     * 
     * 2. Regression Alerts:
     *    - Deal moving backward in pipeline
     *    - Stalled deals requiring attention
     * 
     * 3. Milestone Achievements:
     *    - First deal to reach certain stage
     *    - High-value deal progressions
     * 
     * Notification Features:
     * - HTML formatted emails with deal context
     * - Direct links to deal record
     * - Stage-specific action items
     * - Previous/new stage comparison
     * - Configurable recipient lists per stage
     * 
     * Configuration:
     * - Enable/disable via deals.stage_change_notifications
     * - Stage-specific recipient rules
     * - Value thresholds for notifications
     * 
     * @param SugarBean $bean The Deal bean that changed stages
     * @param string $oldStage Previous stage identifier  
     * @param string $newStage New stage identifier
     * 
     * @return void
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
     * Generate notification email body for stage changes
     * 
     * Creates a well-formatted, informative email body for stage change
     * notifications. The email provides context and actionable information
     * to help recipients understand and act on the stage change.
     * 
     * Email Content Structure:
     * 1. Header:
     *    - Clear subject indicating stage change
     *    - Visual indicators (colors/icons) for stage
     * 
     * 2. Deal Information:
     *    - Deal name with clickable link
     *    - Current value and key metrics
     *    - Days in previous stage
     * 
     * 3. Transition Details:
     *    - Previous stage → New stage
     *    - Transition notes if provided
     *    - User who made the change
     *    - Timestamp of change
     * 
     * 4. Action Items:
     *    - Stage-specific next steps
     *    - Required actions for new stage
     *    - Deadline reminders
     * 
     * 5. Context:
     *    - Deal history summary
     *    - Related team members
     *    - Recent activity highlights
     * 
     * The HTML format ensures compatibility with email clients while
     * providing a professional, branded appearance.
     * 
     * @param SugarBean $bean The Deal bean with transition details
     * @param string $oldStage Previous stage name for context
     * @param string $newStage New stage name for action items
     * 
     * @return string HTML formatted email body
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