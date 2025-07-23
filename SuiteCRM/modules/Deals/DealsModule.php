<?php
/**
 * Module Registration for Deals
 * 
 * This file helps register the Deals module with SuiteCRM
 * 
 * @package MakeDealCRM
 * @subpackage Deals
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

// Module information
$module_name = 'Deals';
$object_name = 'Deal';

// Register the module
$moduleList[] = $module_name;
$beanList[$module_name] = $object_name;
$beanFiles[$object_name] = 'modules/Deals/Deal.php';

// Register module for ACL
$modules_exempt_from_availability_check[$module_name] = $module_name;
$modInvisList[] = $module_name;

// Add to the module menu
if (isset($GLOBALS['current_user']) && is_admin($GLOBALS['current_user'])) {
    $module_menu[] = array('index.php?module=Deals&action=index', 'Deals', 'Deals');
}

// Define module tab
$GLOBALS['moduleTabMap'][$module_name] = $module_name;