<?php
/**
 * Pipelines Module Menu
 */

global $mod_strings, $app_strings, $sugar_config;

if(ACLController::checkAccess('Pipelines', 'view', true)){
    $module_menu[] = Array("index.php?module=Pipelines&action=kanban", $mod_strings['LBL_PIPELINE_VIEW'],"pipeline");
}

if(ACLController::checkAccess('Pipelines', 'edit', true)){
    $module_menu[] = Array("index.php?module=Pipelines&action=EditView&return_module=Pipelines&return_action=DetailView", $mod_strings['LBL_CREATE_PIPELINE'],"create");
}

if(ACLController::checkAccess('Pipelines', 'list', true)){
    $module_menu[] = Array("index.php?module=Pipelines&action=index&return_module=Pipelines&return_action=DetailView", $mod_strings['LBL_LIST_PIPELINES'],"list");
}

if(ACLController::checkAccess('Pipelines', 'import', true)){
    $module_menu[] = Array("index.php?module=Import&action=Step1&import_module=Pipelines&return_module=Pipelines&return_action=index", $app_strings['LBL_IMPORT'],"import");
}
?>