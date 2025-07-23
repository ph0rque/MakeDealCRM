<?php
/**
 * Menu configuration for Deals module
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

global $mod_strings, $app_strings, $sugar_config;

if (ACLController::checkAccess('Deals', 'edit', true)) {
    $module_menu[] = array('index.php?module=Deals&action=EditView&return_module=Deals&return_action=DetailView', $mod_strings['LBL_NEW_FORM_TITLE'], 'Create', 'Deals');
}

if (ACLController::checkAccess('Deals', 'list', true)) {
    $module_menu[] = array('index.php?module=Deals&action=index&return_module=Deals&return_action=DetailView', $mod_strings['LBL_LIST_FORM_TITLE'], 'List', 'Deals');
}

if (ACLController::checkAccess('Deals', 'import', true)) {
    $module_menu[] = array('index.php?module=Import&action=Step1&import_module=Deals&return_module=Deals&return_action=index', $mod_strings['LBL_IMPORT_DEALS'], 'Import', 'Deals');
}

// Add Reports menu item
if (ACLController::checkAccess('Deals', 'list', true)) {
    $module_menu[] = array('index.php?module=Deals&action=Reports', 'Deal Reports', 'Reports', 'Deals');
}

// Add Pipeline View
if (ACLController::checkAccess('Deals', 'list', true)) {
    $module_menu[] = array('index.php?module=Deals&action=Pipeline', 'Sales Pipeline', 'Pipeline', 'Deals');
}