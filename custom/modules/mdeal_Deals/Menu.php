<?php
/**
 * Menu for mdeal_Deals Module
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

global $mod_strings, $app_strings, $sugar_config;

if (ACLController::checkAccess('mdeal_Deals', 'edit', true)) {
    $module_menu[] = array('index.php?module=mdeal_Deals&action=EditView&return_module=mdeal_Deals&return_action=DetailView', $mod_strings['LNK_NEW_RECORD'], 'Add', 'mdeal_Deals');
}

if (ACLController::checkAccess('mdeal_Deals', 'list', true)) {
    $module_menu[] = array('index.php?module=mdeal_Deals&action=index', $mod_strings['LNK_LIST'], 'View', 'mdeal_Deals');
}

if (ACLController::checkAccess('mdeal_Deals', 'list', true)) {
    $module_menu[] = array('index.php?module=mdeal_Deals&action=pipeline', 'Pipeline View', 'Pipeline', 'mdeal_Deals');
}

if (ACLController::checkAccess('mdeal_Deals', 'import', true)) {
    $module_menu[] = array('index.php?module=Import&action=Step1&import_module=mdeal_Deals&return_module=mdeal_Deals&return_action=index', $app_strings['LBL_IMPORT'], 'Import', 'mdeal_Deals');
}