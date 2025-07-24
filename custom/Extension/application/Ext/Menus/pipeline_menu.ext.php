<?php
/**
 * Add Pipeline to Main Menu
 */

if (ACLController::checkAccess('mdeal_Deals', 'list', true)) {
    $module_menu[] = array(
        'index.php?module=Pipelines&action=KanbanView',
        $app_strings['LBL_PIPELINE_KANBAN'],
        'Pipeline',
        'Pipelines'
    );
}