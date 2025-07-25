<?php
/**
 * Enhanced Deals Module Logic Hooks with Comprehensive CRUD Support
 * 
 * @package MakeDealCRM
 * @module Deals
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

$hook_version = 1;
$hook_array = array();

// Before Save Hooks
$hook_array['before_save'] = array();

// Deal Validation Hook
$hook_array['before_save'][] = array(
    95,
    'Validate deal data before save',
    'custom/modules/Deals/logic_hooks/ValidationHook.php',
    'ValidationHook',
    'validateDealBeforeSave'
);

// WIP Limit Validation
$hook_array['before_save'][] = array(
    98,
    'Validate WIP limits for pipeline stages',
    'custom/modules/Deals/logic_hooks/WIPLimitHook.php',
    'WIPLimitHook',
    'validateWIPLimit'
);

// After Save Hooks
$hook_array['after_save'] = array();

// Workflow Manager - Deal Creation/Updates
$hook_array['after_save'][] = array(
    96,
    'Handle deal workflow processes',
    'custom/modules/Deals/workflow/DealWorkflowManager.php',
    'DealWorkflowManager',
    'onDealCreate'
);

// Workflow Manager - Stage Changes
$hook_array['after_save'][] = array(
    97,
    'Handle pipeline stage changes',
    'custom/modules/Deals/workflow/DealWorkflowManager.php',
    'DealWorkflowManager',
    'onStageChange'
);

// Pipeline Stage History
$hook_array['after_save'][] = array(
    99,
    'Update pipeline stage history',
    'custom/modules/Deals/logic_hooks/PipelineStageHook.php',
    'PipelineStageHook',
    'updateStageHistory'
);

// Checklist Progress Update
$hook_array['after_save'][] = array(
    100,
    'Update checklist completion percentage',
    'custom/modules/Deals/ChecklistLogicHook.php',
    'ChecklistLogicHook',
    'updateChecklistProgress'
);

// Before Delete Hooks
$hook_array['before_delete'] = array();

// Workflow Manager - Pre-delete
$hook_array['before_delete'][] = array(
    85,
    'Handle deal pre-deletion workflow',
    'custom/modules/Deals/workflow/DealWorkflowManager.php',
    'DealWorkflowManager',
    'onDealDelete'
);

// Clean up related records
$hook_array['before_delete'][] = array(
    90,
    'Clean up pipeline history records',
    'custom/modules/Deals/logic_hooks/CleanupHook.php',
    'CleanupHook',
    'cleanupPipelineHistory'
);

// After Retrieve Hooks
$hook_array['after_retrieve'] = array();

// Enhanced field calculation
$hook_array['after_retrieve'][] = array(
    75,
    'Calculate enhanced deal metrics',
    'custom/modules/Deals/logic_hooks/MetricsHook.php',
    'MetricsHook',
    'calculateDealMetrics'
);

// Security validation on retrieve
$hook_array['after_retrieve'][] = array(
    80,
    'Validate deal access permissions',
    'custom/modules/Deals/logic_hooks/SecurityHook.php',
    'SecurityHook',
    'validateAccess'
);

// Calculate dynamic fields
$hook_array['after_retrieve'][] = array(
    85,
    'Calculate days in current stage',
    'custom/modules/Deals/logic_hooks/CalculationHook.php',
    'CalculationHook',
    'calculateDaysInStage'
);

// Process Record Hooks
$hook_array['process_record'] = array();

// Add pipeline-specific data for list views
$hook_array['process_record'][] = array(
    80,
    'Add pipeline data for list view',
    'custom/modules/Deals/logic_hooks/ProcessRecordHook.php',
    'ProcessRecordHook',
    'addPipelineData'
);

// After Delete Hooks
$hook_array['after_delete'] = array();

// Post-deletion cleanup and notifications
$hook_array['after_delete'][] = array(
    90,
    'Handle post-deletion tasks',
    'custom/modules/Deals/logic_hooks/PostDeleteHook.php',
    'PostDeleteHook',
    'handlePostDeletion'
);

// Before Relationship Add/Remove Hooks
$hook_array['before_relationship_add'] = array();
$hook_array['after_relationship_add'] = array();
$hook_array['before_relationship_delete'] = array();
$hook_array['after_relationship_delete'] = array();

// Validate checklist relationships
$hook_array['before_relationship_add'][] = array(
    90,
    'Validate checklist relationship before adding',
    'custom/modules/Deals/ChecklistLogicHook.php',
    'ChecklistLogicHook',
    'validateChecklistRelationship'
);

// Update deal metrics when relationships change
$hook_array['after_relationship_add'][] = array(
    85,
    'Update deal metrics after relationship add',
    'custom/modules/Deals/logic_hooks/RelationshipHook.php',
    'RelationshipHook',
    'updateMetricsOnAdd'
);

$hook_array['after_relationship_delete'][] = array(
    85,
    'Update deal metrics after relationship delete',
    'custom/modules/Deals/logic_hooks/RelationshipHook.php',
    'RelationshipHook',
    'updateMetricsOnDelete'
);