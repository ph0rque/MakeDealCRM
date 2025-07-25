<?php
/**
 * Tab configuration for Deals module
 * This file ensures the Deals module appears in the SuiteCRM navigation
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

// Add Deals to the Sales tab group
$GLOBALS['tabStructure']['LBL_TABGROUP_SALES']['modules'][] = 'Deals';

// Add Deals to the main module list
$GLOBALS['moduleList'][] = 'Deals';

// Register the module for tab display
$GLOBALS['modListHeader'][] = 'Deals';

// Set the tab order (optional)
$GLOBALS['tab_order']['Deals'] = 5;

// Configure default action for the tab
$GLOBALS['moduleList']['Deals'] = 'Deals';
$GLOBALS['beanList']['Deals'] = 'Deal';
$GLOBALS['beanFiles']['Deal'] = 'modules/Deals/Deal.php';

// Override default action to pipeline
$GLOBALS['default_module_actions']['Deals'] = 'pipeline';