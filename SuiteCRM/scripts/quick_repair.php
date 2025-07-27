<?php
/**
 * Script to run Quick Repair and Rebuild
 */

// SuiteCRM bootstrap
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');

global $current_user;

// Set current user to admin for permissions
$current_user = new User();
$current_user->getSystemUser();

echo "Running Quick Repair and Rebuild...\n\n";

// Include repair classes
require_once('modules/Administration/QuickRepairAndRebuild.php');

// Create repair instance
$repair = new RepairAndClear();
$repair->repairAndClearAll(array('clearAll'), array($mod_strings['LBL_ALL_MODULES']), true, false);

echo "\nQuick Repair and Rebuild completed!\n";
echo "Language files and caches have been refreshed.\n";