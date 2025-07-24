<?php
/**
 * Add Pipeline view to Deals module menu
 */

global $mod_strings, $module_menu;

// Add Pipeline view to module menu
if (ACLController::checkAccess('Deals', 'list', true)) {
    $module_menu[] = array(
        'index.php?module=Deals&action=Pipeline',
        $mod_strings['LBL_PIPELINE_VIEW'] ?? 'Pipeline View',
        'Pipeline',
        'Deals'
    );
}