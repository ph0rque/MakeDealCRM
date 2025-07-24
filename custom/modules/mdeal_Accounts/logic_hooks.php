<?php
/**
 * Logic hooks registration for mdeal_Accounts module
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$hook_version = 1;
$hook_array = array();

// Include the logic hooks class
require_once('custom/modules/mdeal_Accounts/AccountLogicHooks.php');

// Before save hooks - validation and preparation
$hook_array['before_save'][] = array(
    1,                                    // Processing order
    'Account Hierarchy Validation',       // Hook label
    'custom/modules/mdeal_Accounts/AccountLogicHooks.php',
    'AccountLogicHooks',                 // Class name
    'validateHierarchy'                  // Method name
);

$hook_array['before_save'][] = array(
    5,
    'Account Health Calculation',
    'custom/modules/mdeal_Accounts/AccountLogicHooks.php',
    'AccountLogicHooks',
    'calculateHealthScore'
);

$hook_array['before_save'][] = array(
    10,
    'Compliance Tracking',
    'custom/modules/mdeal_Accounts/AccountLogicHooks.php',
    'AccountLogicHooks',
    'trackCompliance'
);

// After save hooks - updates and notifications
$hook_array['after_save'][] = array(
    1,
    'Deal Metrics Update',
    'custom/modules/mdeal_Accounts/AccountLogicHooks.php',
    'AccountLogicHooks',
    'updateDealMetrics'
);

$hook_array['after_save'][] = array(
    5,
    'Portfolio Metrics Update',
    'custom/modules/mdeal_Accounts/AccountLogicHooks.php',
    'AccountLogicHooks',
    'updatePortfolioMetrics'
);

$hook_array['after_save'][] = array(
    10,
    'Relationship Score Calculation',
    'custom/modules/mdeal_Accounts/AccountLogicHooks.php',
    'AccountLogicHooks',
    'calculateRelationshipScore'
);

$hook_array['after_save'][] = array(
    15,
    'Change Notifications',
    'custom/modules/mdeal_Accounts/AccountLogicHooks.php',
    'AccountLogicHooks',
    'sendChangeNotifications'
);

// Before delete hooks - validation
$hook_array['before_delete'][] = array(
    1,
    'Prevent Deletion with Active Relationships',
    'custom/modules/mdeal_Accounts/AccountLogicHooks.php',
    'AccountLogicHooks',
    'preventDeletionWithActiveRelationships'
);

// Relationship hooks - when related records are added/removed
$hook_array['after_relationship_add'][] = array(
    1,
    'Update Metrics on Relationship Add',
    'custom/modules/mdeal_Accounts/AccountLogicHooks.php',
    'AccountLogicHooks',
    'updateDealMetrics'
);

$hook_array['after_relationship_delete'][] = array(
    1,
    'Update Metrics on Relationship Delete',
    'custom/modules/mdeal_Accounts/AccountLogicHooks.php',
    'AccountLogicHooks',
    'updateDealMetrics'
);