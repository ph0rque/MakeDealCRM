<?php
/**
 * Custom tab configuration for Deals module
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

// Add Deals to the tab structure
$GLOBALS['tabStructure']['LBL_TABGROUP_SALES']['modules'][] = 'Deals';

// Make sure Deals is visible in navigation
$GLOBALS['modListHeader'][] = 'Deals';

// Override the default system tab config to include Deals
$GLOBALS['system_tabs']['Deals'] = 'Deals';