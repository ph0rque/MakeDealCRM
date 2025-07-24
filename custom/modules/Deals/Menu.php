<?php
/**
 * Menu definition for Deals module
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

global $mod_strings, $app_strings, $sugar_config;

// Check ACL access
if (ACLController::checkAccess('Deals', 'edit', true)) {
    $module_menu[] = array(
        "index.php?module=Deals&action=EditView&return_module=Deals&return_action=DetailView",
        $mod_strings['LBL_NEW_FORM_TITLE'],
        "CreateDeals",
        'Deals'
    );
}

// Pipeline View - Primary view for Deals (default)
if (ACLController::checkAccess('Deals', 'list', true)) {
    $module_menu[] = array(
        "index.php?module=Deals&action=pipeline",
        $mod_strings['LBL_PIPELINE_VIEW'],
        "Pipeline",
        'Deals'
    );
}

// List View (legacy - redirects to pipeline)
if (ACLController::checkAccess('Deals', 'list', true)) {
    $module_menu[] = array(
        "index.php?module=Deals&action=index&return_module=Deals&return_action=DetailView",
        $mod_strings['LBL_LIST_FORM_TITLE'],
        "Deals",
        'Deals'
    );
}

// Import
if (ACLController::checkAccess('Deals', 'import', true)) {
    $module_menu[] = array(
        "index.php?module=Import&action=Step1&import_module=Deals&return_module=Deals&return_action=index",
        $app_strings['LBL_IMPORT'],
        "Import",
        'Deals'
    );
}