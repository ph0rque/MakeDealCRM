<?php
/**
 * Menu definition for Deals module with comprehensive CRUD operations
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

global $mod_strings, $app_strings, $sugar_config;

// Initialize module menu array
if (!isset($module_menu)) {
    $module_menu = array();
}

// Create New Deal
if (ACLController::checkAccess('Deals', 'edit', true)) {
    $module_menu[] = array(
        "index.php?module=Deals&action=EditView&return_module=Deals&return_action=pipeline",
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

// Advanced Search
if (ACLController::checkAccess('Deals', 'list', true)) {
    $module_menu[] = array(
        "index.php?module=Deals&action=index&search_form=true&advanced=true",
        $mod_strings['LBL_ADVANCED_SEARCH'],
        "Search",
        'Deals'
    );
}

// List View (legacy - redirects to pipeline)
if (ACLController::checkAccess('Deals', 'list', true)) {
    $module_menu[] = array(
        "index.php?module=Deals&action=index&return_module=Deals&return_action=pipeline",
        $mod_strings['LBL_LIST_FORM_TITLE'],
        "Deals",
        'Deals'
    );
}

// Reports
if (ACLController::checkAccess('Reports', 'list', true)) {
    $module_menu[] = array(
        "index.php?module=Reports&action=index&query_module=Deals",
        $mod_strings['LBL_REPORTS'],
        "Reports",
        'Reports'
    );
}

// Pipeline Analytics
if (ACLController::checkAccess('Deals', 'list', true)) {
    $module_menu[] = array(
        "index.php?module=Deals&action=analytics",
        $mod_strings['LBL_PIPELINE_ANALYTICS'],
        "Analytics",
        'Deals'
    );
}

// Financial Dashboard
if (ACLController::checkAccess('Deals', 'view', true)) {
    $module_menu[] = array(
        "index.php?module=Deals&action=financialdashboard",
        isset($mod_strings['LBL_FINANCIAL_DASHBOARD']) ? $mod_strings['LBL_FINANCIAL_DASHBOARD'] : 'Financial Dashboard',
        "FinancialDashboard",
        'Deals'
    );
}

// Import Deals
if (ACLController::checkAccess('Deals', 'import', true)) {
    $module_menu[] = array(
        "index.php?module=Import&action=Step1&import_module=Deals&return_module=Deals&return_action=pipeline",
        $app_strings['LBL_IMPORT'],
        "Import",
        'Import'
    );
}

// Export Deals
if (ACLController::checkAccess('Deals', 'export', true)) {
    $module_menu[] = array(
        "index.php?module=Deals&action=export",
        $app_strings['LBL_EXPORT'],
        "Export",
        'Export'
    );
}

// Bulk Operations (Admin only)
if (is_admin($GLOBALS['current_user']) && ACLController::checkAccess('Deals', 'edit', true)) {
    $module_menu[] = array(
        "index.php?module=Deals&action=bulk_operations",
        $mod_strings['LBL_BULK_OPERATIONS'],
        "BulkUpdate",
        'Deals'
    );
}

// Configuration (Admin only)
if (is_admin($GLOBALS['current_user'])) {
    $module_menu[] = array(
        "index.php?module=Deals&action=configure",
        $mod_strings['LBL_CONFIGURE_MODULE'],
        "Configure",
        'Administration'
    );
}