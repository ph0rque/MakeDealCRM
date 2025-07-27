<?php
/**
 * WIP (Work In Progress) Limit Hook Class - Pipeline Flow Control
 * 
 * This class implements Work In Progress (WIP) limits for pipeline stages,
 * a key principle from Kanban methodology that helps teams maintain focus
 * and quality by limiting concurrent work.
 * 
 * WIP Limits Benefits:
 * - Prevents team overload and burnout
 * - Improves deal quality through focused attention
 * - Identifies bottlenecks in the pipeline
 * - Encourages deal completion before starting new ones
 * - Maintains sustainable workflow pace
 * 
 * How It Works:
 * - Each pipeline stage has a configurable WIP limit
 * - System prevents moving deals into stages at capacity
 * - Administrators can override limits when necessary
 * - Clear messaging guides users to complete existing deals
 * 
 * This implementation follows Lean principles to optimize deal flow
 * and ensure teams can deliver quality outcomes consistently.
 * 
 * @package MakeDealCRM
 * @module Deals
 * @author MakeDealCRM Development Team
 * @version 1.0.0
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

class WIPLimitHook
{
    /**
     * Validate WIP limits before saving deal
     * 
     * Enforces Work In Progress limits to maintain pipeline health and team
     * efficiency. This method is called before save to prevent stages from
     * becoming overloaded with too many concurrent deals.
     * 
     * Validation Process:
     * 1. Skip validation for:
     *    - New records without stage
     *    - No stage change on existing records
     *    - Closed stages (no limits)
     * 
     * 2. Check WIP limit for target stage:
     *    - Retrieve configured limit
     *    - Count current deals in stage
     *    - Compare against limit
     * 
     * 3. Handle limit exceeded:
     *    - Check override permissions
     *    - Provide clear error message
     *    - Suggest alternative actions
     *    - Revert stage if needed
     * 
     * Error Handling:
     * - API calls: Throws SugarApiExceptionNotAuthorized
     * - UI: Shows error message via SugarApplication
     * - Preserves original stage to prevent invalid state
     * 
     * This validation helps teams work sustainably by preventing pipeline
     * stages from becoming bottlenecks due to too many concurrent deals.
     * 
     * @param SugarBean $bean The Deal bean instance being saved
     * @param string $event The event type (before_save)
     * @param array $arguments Additional arguments
     * 
     * @throws SugarApiExceptionNotAuthorized When WIP limit exceeded and no override permission
     * @return void
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
     * Retrieves the configured Work In Progress limit for a pipeline stage.
     * Limits can be set through configuration or use defaults that reflect
     * typical team capacity and stage complexity.
     * 
     * Configuration Sources (in priority order):
     * 1. Custom configuration in config_override.php
     * 2. Database configuration table
     * 3. Default limits defined in this class
     * 
     * The method returns null for stages without limits, typically:
     * - Closed/Won stages (no ongoing work)
     * - Closed/Lost stages (no ongoing work)
     * - Stages configured for unlimited capacity
     * 
     * @param string $stage Stage identifier (e.g., 'due_diligence')
     * 
     * @return int|null WIP limit or null if no limit set
     */
    protected function getStageWIPLimit($stage)
    {
        $stageConfig = $this->getStageConfiguration($stage);
        return isset($stageConfig['wip_limit']) ? (int)$stageConfig['wip_limit'] : null;
    }
    
    /**
     * Get stage configuration including WIP limits and metadata
     * 
     * Retrieves comprehensive configuration for a pipeline stage, including
     * WIP limits, display names, and other stage-specific settings. This
     * centralizes stage configuration for consistency across the application.
     * 
     * Default WIP Limits by Stage:
     * - Sourcing: 20 (high volume, low touch)
     * - Screening: 15 (initial evaluation)
     * - Analysis & Outreach: 10 (deeper investigation)
     * - Due Diligence: 8 (intensive work required)
     * - Valuation & Structuring: 6 (complex analysis)
     * - LOI/Negotiation: 5 (active negotiations)
     * - Financing: 5 (coordination intensive)
     * - Closing: 5 (final push)
     * - Closed stages: null (no limit)
     * 
     * These defaults reflect:
     * - Decreasing limits as complexity increases
     * - Resource requirements per stage
     * - Typical team capacity
     * - Need for focus in later stages
     * 
     * Configuration can be customized per deployment to match specific
     * team sizes and business processes.
     * 
     * @param string $stage Stage identifier
     * 
     * @return array Stage configuration with 'name' and 'wip_limit' keys
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
     * Count active deals in a specific pipeline stage
     * 
     * Accurately counts the number of deals currently in a pipeline stage
     * for WIP limit comparison. The count excludes closed deals and can
     * exclude a specific deal (useful when moving a deal between stages).
     * 
     * Count Criteria:
     * 1. Deals in the specified pipeline stage
     * 2. Not deleted (active records only)
     * 3. Not in closed sales stages (Closed Won/Lost)
     * 4. Optionally excludes a specific deal ID
     * 
     * Exclusions:
     * - Closed deals don't count against WIP limits
     * - Deleted deals are ignored
     * - The deal being moved is excluded to prevent counting it twice
     * 
     * Performance Considerations:
     * - Uses parameterized queries for security
     * - Leverages database indexes on stage and deleted fields
     * - Single query for efficiency
     * 
     * @param string $stage Stage identifier to count deals in
     * @param string $excludeId Optional deal ID to exclude from count
     * 
     * @return int Number of active deals in the stage
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
     * Determines whether the current user has permission to bypass WIP limits.
     * This flexibility allows authorized users to handle exceptional cases
     * while maintaining limits for general users.
     * 
     * Override Permission Hierarchy:
     * 1. System Administrators:
     *    - Always have override capability
     *    - Can configure limits and permissions
     * 
     * 2. Role-based Permission:
     *    - Specific ACL permission 'override_wip_limit'
     *    - Can be granted to team leads or managers
     *    - Configured through Role Management
     * 
     * 3. Future Extensions:
     *    - Stage-specific override permissions
     *    - Temporary override grants
     *    - Override with approval workflow
     * 
     * Override Usage:
     * - Should be exception, not rule
     * - Logged for audit purposes
     * - May trigger notifications
     * - Can require justification
     * 
     * This permission structure balances flexibility with control,
     * ensuring WIP limits are respected while allowing for business needs.
     * 
     * @return bool True if user can override WIP limits
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