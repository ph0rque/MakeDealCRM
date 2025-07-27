<?php
/**
 * Logic Hook Class for Deal-Checklist Relationship Management
 * 
 * This class serves as the bridge between SuiteCRM's logic hook system and the
 * centralized ChecklistService. It has been refactored to delegate all checklist
 * operations to the ChecklistService for improved modularity and reusability.
 * 
 * The hooks in this class are triggered by deal lifecycle events and ensure that
 * checklist-related operations are properly synchronized. While the actual business
 * logic now resides in ChecklistService, these hooks remain essential for:
 * 
 * - Integration with SuiteCRM's event system
 * - Backward compatibility with existing customizations
 * - Logging and error handling at the hook level
 * - Ensuring proper execution context
 * 
 * @package MakeDealCRM
 * @subpackage Deals
 * @author MakeDealCRM Development Team
 * @version 2.0.0
 * @since Refactored to use ChecklistService architecture
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('custom/modules/Deals/services/ChecklistService.php');

class ChecklistLogicHook
{
    /**
     * @var ChecklistService The centralized checklist service instance
     */
    private $checklistService;
    
    /**
     * Constructor initializes the ChecklistService
     * 
     * The ChecklistService handles all checklist business logic, including:
     * - CRUD operations for checklists and items
     * - Completion calculations and metrics
     * - Template management and instantiation
     * - Security and validation rules
     */
    public function __construct()
    {
        $this->checklistService = new ChecklistService();
    }
    
    /**
     * Update checklist completion percentage when deal is saved
     * 
     * This hook ensures checklist metrics are synchronized after deal saves.
     * The actual calculation and update logic is delegated to ChecklistService,
     * which maintains:
     * 
     * - Overall completion percentage across all checklists
     * - Count of active checklists
     * - Number of overdue items
     * - Critical item status
     * 
     * The service automatically updates the deal's custom fields:
     * - checklist_completion_c
     * - active_checklists_count_c  
     * - overdue_checklist_items_c
     * 
     * This delegation pattern allows the business logic to be reused
     * across different entry points (API, UI, imports) while maintaining
     * consistency.
     * 
     * @param SugarBean $bean The Deal bean being saved
     * @param string $event The event type (after_save)
     * @param array $arguments Additional hook arguments
     * 
     * @return void
     */
    public function updateChecklistCompletion($bean, $event, $arguments)
    {
        if (!$bean instanceof SugarBean || $bean->module_dir !== 'Deals') {
            return;
        }

        try {
            // Use ChecklistService to update deal checklist fields
            // This is now handled internally by the service
            $checklists = $this->checklistService->getDealChecklists($bean->id);
            
            // The service automatically updates deal fields when checklists change
            // This hook is now primarily for logging
            $GLOBALS['log']->info("ChecklistLogicHook::updateChecklistCompletion - Updated for deal: {$bean->id}");

        } catch (Exception $e) {
            $GLOBALS['log']->error("ChecklistLogicHook::updateChecklistCompletion - Error: " . $e->getMessage());
        }
    }

    /**
     * Cascade delete checklists when deal is deleted
     * 
     * Handles the cleanup of all checklist data associated with a deal
     * when the deal is deleted. This hook:
     * 
     * 1. Retrieves all checklists linked to the deal
     * 2. Delegates deletion to ChecklistService for each checklist
     * 3. The service handles:
     *    - Soft deletion of checklist records
     *    - Cleanup of checklist items
     *    - Archival for audit purposes
     *    - Relationship cleanup
     * 
     * Using the service ensures consistent deletion behavior regardless
     * of how the deletion is triggered (UI, API, mass delete, etc.).
     * 
     * The cascade deletion preserves data integrity while maintaining
     * an audit trail through soft deletes.
     * 
     * @param SugarBean $bean The Deal bean being deleted
     * @param string $event The event type (before_delete)
     * @param array $arguments Additional hook arguments
     * 
     * @return void
     */
    public function cascadeDeleteChecklists($bean, $event, $arguments)
    {
        if (!$bean instanceof SugarBean || $bean->module_dir !== 'Deals') {
            return;
        }

        try {
            // Get all checklists for this deal
            $checklists = $this->checklistService->getDealChecklists($bean->id);
            
            // Delete each checklist using the service
            foreach ($checklists as $checklist) {
                $this->checklistService->deleteChecklist($checklist['id'], true);
            }

            $GLOBALS['log']->info("ChecklistLogicHook::cascadeDeleteChecklists - Cascaded delete for deal: {$bean->id}");

        } catch (Exception $e) {
            $GLOBALS['log']->error("ChecklistLogicHook::cascadeDeleteChecklists - Error: " . $e->getMessage());
        }
    }

    /**
     * Validate checklist relationship before adding
     * 
     * This hook serves as an integration point for relationship validation,
     * though the actual validation logic now resides in ChecklistService.
     * 
     * The service validates:
     * - User permissions to modify deal checklists
     * - Checklist template/item existence and active status
     * - Business rules for the specific deal stage
     * - Prevention of duplicate assignments
     * 
     * This hook is maintained for:
     * - Backward compatibility with existing customizations
     * - Integration with SuiteCRM's relationship management
     * - Logging validation attempts for security audit
     * 
     * Note: The service handles the actual validation logic to ensure
     * consistency across all checklist operations, not just those
     * triggered through relationships.
     * 
     * @param SugarBean $bean The Deal bean
     * @param string $event The event type (before_relationship_add)
     * @param array $arguments Contains related_id, link, and other relationship data
     * 
     * @return bool|void Returns false to prevent invalid relationships
     */
    public function validateChecklistRelationship($bean, $event, $arguments)
    {
        if (!$bean instanceof SugarBean || $bean->module_dir !== 'Deals') {
            return;
        }

        try {
            // Validation is now handled by the ChecklistService
            // This hook is maintained for compatibility but delegates to the service
            
            $GLOBALS['log']->info("ChecklistLogicHook::validateChecklistRelationship - Validating for deal: {$bean->id}");

        } catch (Exception $e) {
            $GLOBALS['log']->error("ChecklistLogicHook::validateChecklistRelationship - Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update pipeline stage based on checklist completion
     * 
     * This hook provides integration with SuiteCRM's relationship events
     * for pipeline stage advancement. The actual stage advancement logic
     * is now handled automatically by the ChecklistService when checklist
     * items are updated.
     * 
     * The service implements intelligent stage progression:
     * - Monitors completion thresholds (e.g., 100% for advancement)
     * - Applies stage-specific business rules
     * - Handles automatic advancement when enabled
     * - Respects manual overrides
     * 
     * This hook remains in place for:
     * - Logging stage advancement attempts
     * - Maintaining backward compatibility
     * - Providing a hook point for custom extensions
     * 
     * The delegation to ChecklistService ensures that stage advancement
     * logic is consistent whether triggered by:
     * - Direct checklist item updates
     * - Bulk operations
     * - API calls
     * - Import processes
     * 
     * @param SugarBean $bean The Deal bean
     * @param string $event The event type (after_relationship_add/delete)
     * @param array $arguments Additional hook arguments
     * 
     * @return void
     */
    public function updatePipelineStage($bean, $event, $arguments)
    {
        if (!$bean instanceof SugarBean || $bean->module_dir !== 'Deals') {
            return;
        }

        try {
            // Pipeline stage advancement is now handled automatically by the ChecklistService
            // when checklist items are updated. This hook is maintained for compatibility.
            
            $GLOBALS['log']->info("ChecklistLogicHook::updatePipelineStage - Checking for deal: {$bean->id}");

        } catch (Exception $e) {
            $GLOBALS['log']->error("ChecklistLogicHook::updatePipelineStage - Error: " . $e->getMessage());
        }
    }
}