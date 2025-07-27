<?php
/**
 * Menu configuration for ChecklistTemplates module
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

global $mod_strings, $app_strings;

if (ACLController::checkAccess('ChecklistTemplates', 'edit', true)) {
    $module_menu[] = array(
        'index.php?module=ChecklistTemplates&action=EditView&return_module=ChecklistTemplates&return_action=DetailView',
        $mod_strings['LBL_CREATE_TEMPLATE'],
        'Create',
        'ChecklistTemplates'
    );
}

if (ACLController::checkAccess('ChecklistTemplates', 'list', true)) {
    $module_menu[] = array(
        'index.php?module=ChecklistTemplates&action=index',
        $mod_strings['LBL_LIST_FORM_TITLE'],
        'List',
        'ChecklistTemplates'
    );
}

if (ACLController::checkAccess('ChecklistTemplates', 'import', true)) {
    $module_menu[] = array(
        'index.php?module=Import&action=Step1&import_module=ChecklistTemplates',
        $mod_strings['LBL_IMPORT_TEMPLATE'],
        'Import',
        'ChecklistTemplates'
    );
}