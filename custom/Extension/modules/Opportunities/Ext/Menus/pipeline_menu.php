<?php
/**
 * Add Pipeline view to Opportunities/Deals module menu
 */

global $module_menu;

// Add Pipeline menu item after List view
$pipeline_menu = [
    'index.php?module=Deals&action=Pipeline',
    'LBL_PIPELINE_VIEW',
    'Pipeline',
    'Deals'
];

// Insert after the first menu item (usually List)
if (is_array($module_menu) && count($module_menu) > 0) {
    array_splice($module_menu, 1, 0, [$pipeline_menu]);
} else {
    $module_menu[] = $pipeline_menu;
}