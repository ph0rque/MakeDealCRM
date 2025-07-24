<?php
/**
 * Logic hooks for mdeal_Contacts module
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$hook_version = 1;
$hook_array = array();

// Logic hooks for mdeal_Contacts module
$hook_array['before_save'] = array();
$hook_array['before_save'][] = array(
    1,
    'Validate hierarchy relationships',
    'custom/modules/mdeal_Contacts/ContactLogicHooks.php',
    'ContactLogicHooks',
    'validateHierarchy'
);

$hook_array['before_save'][] = array(
    2,
    'Update interaction metrics',
    'custom/modules/mdeal_Contacts/ContactLogicHooks.php',
    'ContactLogicHooks',
    'updateInteractionMetrics'
);

$hook_array['before_save'][] = array(
    3,
    'Set full name field',
    'custom/modules/mdeal_Contacts/ContactLogicHooks.php',
    'ContactLogicHooks',
    'setFullName'
);

$hook_array['after_save'] = array();
$hook_array['after_save'][] = array(
    1,
    'Update last interaction date',
    'custom/modules/mdeal_Contacts/ContactLogicHooks.php',
    'ContactLogicHooks',
    'updateLastInteractionDate'
);

$hook_array['after_save'][] = array(
    2,
    'Send follow-up notifications',
    'custom/modules/mdeal_Contacts/ContactLogicHooks.php',
    'ContactLogicHooks',
    'sendFollowUpNotifications'
);

$hook_array['after_save'][] = array(
    3,
    'Update related account contact count',
    'custom/modules/mdeal_Contacts/ContactLogicHooks.php',
    'ContactLogicHooks',
    'updateAccountContactCount'
);

$hook_array['before_delete'] = array();
$hook_array['before_delete'][] = array(
    1,
    'Prevent deletion with active relationships',
    'custom/modules/mdeal_Contacts/ContactLogicHooks.php',
    'ContactLogicHooks',
    'preventDeletionWithActiveRelationships'
);

$hook_array['after_relationship_add'] = array();
$hook_array['after_relationship_add'][] = array(
    1,
    'Handle deal relationship added',
    'custom/modules/mdeal_Contacts/ContactLogicHooks.php',
    'ContactLogicHooks',
    'handleDealRelationshipAdded'
);

$hook_array['after_relationship_add'][] = array(
    2,
    'Handle account relationship added',
    'custom/modules/mdeal_Contacts/ContactLogicHooks.php',
    'ContactLogicHooks',
    'handleAccountRelationshipAdded'
);

$hook_array['after_relationship_delete'] = array();
$hook_array['after_relationship_delete'][] = array(
    1,
    'Handle relationship removed',
    'custom/modules/mdeal_Contacts/ContactLogicHooks.php',
    'ContactLogicHooks',
    'handleRelationshipRemoved'
);