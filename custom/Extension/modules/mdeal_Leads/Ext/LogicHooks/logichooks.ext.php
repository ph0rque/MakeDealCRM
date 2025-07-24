<?php
/**
 * Logic hooks for mdeal_Leads module
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$hook_version = 1;
$hook_array = array();

// Logic hooks for mdeal_Leads module
$hook_array['before_save'] = array();
$hook_array['before_save'][] = array(
    1,
    'Update qualification score on save',
    'custom/modules/mdeal_Leads/LeadLogicHooks.php',
    'LeadLogicHooks',
    'updateQualificationScore'
);

$hook_array['before_save'][] = array(
    2,
    'Update pipeline stage timing',
    'custom/modules/mdeal_Leads/LeadLogicHooks.php',
    'LeadLogicHooks',
    'updatePipelineStageData'
);

$hook_array['after_save'] = array();
$hook_array['after_save'][] = array(
    1,
    'Update last activity date',
    'custom/modules/mdeal_Leads/LeadLogicHooks.php',
    'LeadLogicHooks',
    'updateLastActivityDate'
);

$hook_array['after_save'][] = array(
    2,
    'Create follow-up tasks based on stage',
    'custom/modules/mdeal_Leads/LeadLogicHooks.php',
    'LeadLogicHooks',
    'createFollowUpTasks'
);

$hook_array['after_save'][] = array(
    3,
    'Send stage transition notifications',
    'custom/modules/mdeal_Leads/LeadLogicHooks.php',
    'LeadLogicHooks',
    'sendStageNotifications'
);

$hook_array['before_delete'] = array();
$hook_array['before_delete'][] = array(
    1,
    'Prevent deletion of converted leads',
    'custom/modules/mdeal_Leads/LeadLogicHooks.php',
    'LeadLogicHooks',
    'preventConvertedLeadDeletion'
);