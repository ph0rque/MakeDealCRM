<?php
/**
 * WIP Limit Hook Class
 * 
 * Validates Work In Progress limits for pipeline stages
 * 
 * @package MakeDealCRM
 * @module Deals
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

class WIPLimitHook
{
    /**
     * Validate WIP limits before saving deal
     * 
     * @param SugarBean $bean The Deal bean instance
     * @param string $event The event type
     * @param array $arguments Additional arguments
     * @throws SugarApiExceptionNotAuthorized When WIP limit would be exceeded
     */
    public function validateWIPLimit($bean, $event, $arguments)
    {
        global $db;
        
        // Skip validation if:
        // 1. This is a new record and no stage is set
        // 2. The stage hasn't changed
        // 3. The deal is being closed (won or lost)
        
        if (empty($bean->pipeline_stage_c)) {
            return;
        }
        
        $isNewRecord = empty($bean->id) || empty($bean->fetched_row);
        $oldStage = $isNewRecord ? null : $bean->fetched_row['pipeline_stage_c'];
        $newStage = $bean->pipeline_stage_c;
        
        // Skip if stage hasn't changed (unless it's a new record)
        if (!$isNewRecord && $oldStage === $newStage) {
            return;
        }
        
        // Skip validation for closed stages
        if (in_array($newStage, ['closed_owned_90_day', 'closed_owned_stable', 'unavailable'])) {
            return;
        }
        
        // Get WIP limit for the target stage
        $wipLimit = $this->getStageWIPLimit($newStage);
        
        if ($wipLimit === null || $wipLimit <= 0) {
            // No limit set for this stage
            return;
        }
        
        // Count current deals in the target stage
        $currentCount = $this->getStageCount($newStage, $bean->id);
        
        if ($currentCount >= $wipLimit) {
            // Check if user has override permission
            if ($this->userCanOverrideWIPLimit()) {
                // Log the override
                $GLOBALS['log']->warn("User {$GLOBALS['current_user']->id} overriding WIP limit for stage {$newStage}");
                return;
            }
            
            // Prepare error message
            $stageConfig = $this->getStageConfiguration($newStage);
            $stageName = !empty($stageConfig['name']) ? $stageConfig['name'] : $newStage;
            
            $errorMessage = sprintf(
                "Cannot move deal to %s stage. WIP limit of %d has been reached (current: %d deals). " .
                "Please complete or move existing deals before adding new ones to this stage.",
                $stageName,
                $wipLimit,
                $currentCount
            );
            
            // For API calls, throw exception
            if (!empty($GLOBALS['service'])) {
                require_once 'include/api/SugarApiException.php';
                throw new SugarApiExceptionNotAuthorized($errorMessage);
            }
            
            // For UI, use SugarApplication to show error
            SugarApplication::appendErrorMessage($errorMessage);
            
            // Revert the stage change
            $bean->pipeline_stage_c = $oldStage;
        }
    }
    
    /**
     * Get WIP limit for a specific stage
     * 
     * @param string $stage Stage identifier
     * @return int|null WIP limit or null if not set
     */
    protected function getStageWIPLimit($stage)
    {
        $stageConfig = $this->getStageConfiguration($stage);
        return isset($stageConfig['wip_limit']) ? (int)$stageConfig['wip_limit'] : null;
    }
    
    /**
     * Get stage configuration
     * 
     * @param string $stage Stage identifier
     * @return array Stage configuration
     */
    protected function getStageConfiguration($stage)
    {
        // Default stage configurations
        $stages = array(
            'sourcing' => array('name' => 'Sourcing', 'wip_limit' => 20),
            'screening' => array('name' => 'Screening', 'wip_limit' => 15),
            'analysis_outreach' => array('name' => 'Analysis & Outreach', 'wip_limit' => 10),
            'due_diligence' => array('name' => 'Due Diligence', 'wip_limit' => 8),
            'valuation_structuring' => array('name' => 'Valuation & Structuring', 'wip_limit' => 6),
            'loi_negotiation' => array('name' => 'LOI / Negotiation', 'wip_limit' => 5),
            'financing' => array('name' => 'Financing', 'wip_limit' => 5),
            'closing' => array('name' => 'Closing', 'wip_limit' => 5),
            'closed_owned_90_day' => array('name' => 'Closed/Owned – 90-Day Plan', 'wip_limit' => null),
            'closed_owned_stable' => array('name' => 'Closed/Owned – Stable Operations', 'wip_limit' => null),
            'unavailable' => array('name' => 'Unavailable', 'wip_limit' => null),
        );
        
        // Check for custom configuration
        $config = SugarConfig::getInstance();
        $customStages = $config->get('deals.pipeline_stages');
        
        if (!empty($customStages) && isset($customStages[$stage])) {
            return $customStages[$stage];
        }
        
        return isset($stages[$stage]) ? $stages[$stage] : array();
    }
    
    /**
     * Count deals in a specific stage
     * 
     * @param string $stage Stage identifier
     * @param string $excludeId Deal ID to exclude from count
     * @return int Number of deals in stage
     */
    protected function getStageCount($stage, $excludeId = null)
    {
        global $db;
        
        $query = "SELECT COUNT(*) as count 
                  FROM opportunities 
                  WHERE pipeline_stage_c = ? 
                  AND deleted = 0";
        
        $params = [$stage];
        
        // Exclude deals that are closed
        $query .= " AND sales_stage NOT IN ('Closed Won', 'Closed Lost')";
        
        // Exclude the current deal if updating
        if (!empty($excludeId)) {
            $query .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $db->getConnection()->prepare($query);
        $stmt->execute($params);
        $row = $stmt->fetch();
        
        return (int)$row['count'];
    }
    
    /**
     * Check if current user can override WIP limits
     * 
     * @return bool True if user can override
     */
    protected function userCanOverrideWIPLimit()
    {
        global $current_user;
        
        // Admins can always override
        if ($current_user->isAdmin()) {
            return true;
        }
        
        // Check for specific role permission
        $aclController = new ACLController();
        return $aclController->checkAccess('Deals', 'override_wip_limit', true);
    }
}