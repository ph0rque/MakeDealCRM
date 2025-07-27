<?php
/**
 * Logic Hooks for Deal-Checklist Relationship Management
 * 
 * This extension file registers specific logic hooks for managing the relationship
 * between deals and checklists. It's loaded through SuiteCRM's extension framework
 * and provides focused hooks for checklist-related operations.
 * 
 * These hooks complement the main deal logic hooks by providing specialized
 * handling for checklist operations, ensuring proper separation of concerns
 * and maintainability.
 * 
 * Hook Registration:
 * - after_save: Updates checklist metrics when deals are saved
 * - before_delete: Cascades deletion to related checklists
 * - before_relationship_add: Validates checklist assignments
 * - after_relationship_add/delete: Triggers pipeline updates based on completion
 * 
 * @package MakeDealCRM
 * @subpackage Deals
 * @author MakeDealCRM Development Team
 * @version 1.0.0
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

$hook_version = 1;
$hook_array = Array();

/**
 * Hook for updating checklist completion when deal is saved
 * 
 * This hook ensures checklist metrics are recalculated whenever a deal is saved.
 * It maintains real-time accuracy of completion percentages and counts that are
 * displayed throughout the application.
 * 
 * @order 1 - Runs with highest priority for checklist operations
 */
$hook_array['after_save'] = Array();
$hook_array['after_save'][] = Array(
    1,
    'Update Checklist Completion',
    'custom/modules/Deals/ChecklistLogicHook.php',
    'ChecklistLogicHook',
    'updateChecklistCompletion'
);

/**
 * Hook for cascading deal deletion to checklists
 * 
 * Ensures all checklist data is properly cleaned up when a deal is deleted.
 * Uses soft deletion to maintain audit trails while removing active relationships.
 * This prevents orphaned checklist data and maintains referential integrity.
 * 
 * @order 1 - Runs early to ensure cleanup happens before deal deletion
 */
$hook_array['before_delete'] = Array();
$hook_array['before_delete'][] = Array(
    1,
    'Cascade Delete Checklists',
    'custom/modules/Deals/ChecklistLogicHook.php',
    'ChecklistLogicHook',
    'cascadeDeleteChecklists'
);

/**
 * Hook for security validation on checklist operations
 * 
 * Validates that checklist relationships meet security and business requirements
 * before they are created. This includes permission checks, data validation,
 * and business rule enforcement to maintain data quality and security.
 * 
 * @order 1 - Runs first to prevent invalid relationships from being created
 */
$hook_array['before_relationship_add'] = Array();
$hook_array['before_relationship_add'][] = Array(
    1,
    'Validate Checklist Relationship',
    'custom/modules/Deals/ChecklistLogicHook.php',
    'ChecklistLogicHook',
    'validateChecklistRelationship'
);

/**
 * Hooks for updating pipeline stage based on checklist completion
 * 
 * These paired hooks monitor checklist relationship changes and can trigger
 * automatic pipeline stage advancement when checklist completion thresholds
 * are met. They work together to ensure the pipeline accurately reflects
 * deal readiness based on checklist progress.
 * 
 * after_relationship_add: Triggered when checklist items are completed
 * after_relationship_delete: Triggered when checklist items are removed
 * 
 * Both hooks can result in stage advancement or regression based on the
 * resulting completion percentage and configured business rules.
 * 
 * @order 1 - Runs immediately after relationship changes
 */
$hook_array['after_relationship_add'] = Array();
$hook_array['after_relationship_add'][] = Array(
    1,
    'Update Pipeline on Checklist Change',
    'custom/modules/Deals/ChecklistLogicHook.php',
    'ChecklistLogicHook',
    'updatePipelineStage'
);

$hook_array['after_relationship_delete'] = Array();
$hook_array['after_relationship_delete'][] = Array(
    1,
    'Update Pipeline on Checklist Remove',
    'custom/modules/Deals/ChecklistLogicHook.php',
    'ChecklistLogicHook',
    'updatePipelineStage'
);