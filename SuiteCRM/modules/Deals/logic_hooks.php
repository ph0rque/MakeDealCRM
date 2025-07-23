<?php
/**
 * Logic Hooks Configuration for Deals Module
 * 
 * @package MakeDealCRM
 * @subpackage Deals
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$hook_version = 1;
$hook_array = array();

// After save hooks
$hook_array['after_save'] = array();
$hook_array['after_save'][] = array(
    1,
    'Update At Risk Status',
    'modules/Deals/logic_hooks/DealsLogicHooks.php',
    'DealsLogicHooks',
    'updateAtRiskStatus'
);
$hook_array['after_save'][] = array(
    2,
    'Calculate Financial Metrics',
    'modules/Deals/logic_hooks/DealsLogicHooks.php',
    'DealsLogicHooks',
    'calculateFinancialMetrics'
);

// After relationship add hooks
$hook_array['after_relationship_add'] = array();
$hook_array['after_relationship_add'][] = array(
    1,
    'Process Email Import',
    'modules/Deals/logic_hooks/DealsLogicHooks.php',
    'DealsLogicHooks',
    'processEmailImport'
);

// Before save hooks
$hook_array['before_save'] = array();
$hook_array['before_save'][] = array(
    1,
    'Check for Duplicates',
    'modules/Deals/logic_hooks/DealsLogicHooks.php',
    'DealsLogicHooks',
    'checkForDuplicates'
);

// Process record hooks
$hook_array['process_record'] = array();
$hook_array['process_record'][] = array(
    1,
    'Format List View Fields',
    'modules/Deals/logic_hooks/DealsLogicHooks.php',
    'DealsLogicHooks',
    'formatListViewFields'
);