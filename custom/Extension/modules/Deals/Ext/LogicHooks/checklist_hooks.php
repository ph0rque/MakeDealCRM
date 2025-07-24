<?php
/**
 * Logic hooks for Deal-Checklist relationship management
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

$hook_version = 1;
$hook_array = Array();

// Hook for updating checklist completion when deal is saved
$hook_array['after_save'] = Array();
$hook_array['after_save'][] = Array(
    1,
    'Update Checklist Completion',
    'custom/modules/Deals/ChecklistLogicHook.php',
    'ChecklistLogicHook',
    'updateChecklistCompletion'
);

// Hook for cascading deal deletion to checklists
$hook_array['before_delete'] = Array();
$hook_array['before_delete'][] = Array(
    1,
    'Cascade Delete Checklists',
    'custom/modules/Deals/ChecklistLogicHook.php',
    'ChecklistLogicHook',
    'cascadeDeleteChecklists'
);

// Hook for security validation on checklist operations
$hook_array['before_relationship_add'] = Array();
$hook_array['before_relationship_add'][] = Array(
    1,
    'Validate Checklist Relationship',
    'custom/modules/Deals/ChecklistLogicHook.php',
    'ChecklistLogicHook',
    'validateChecklistRelationship'
);

// Hook for updating pipeline stage based on checklist completion
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