<?php
/**
 * Force rebuild all caches and system files
 */

// SuiteCRM bootstrap
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');

global $current_user;

// Set current user to admin
$current_user = new User();
$current_user->getSystemUser();

echo "=== Force Rebuilding All System Files ===\n\n";

// 1. Clear all cache directories
echo "1. Clearing all cache directories...\n";
$cacheDirs = [
    'cache/',
    'cache/api/',
    'cache/csv/',
    'cache/dashlets/',
    'cache/diagnostic/',
    'cache/dynamic_fields/',
    'cache/email/',
    'cache/images/',
    'cache/import/',
    'cache/include/',
    'cache/javascript/',
    'cache/jsLanguage/',
    'cache/layout/',
    'cache/modules/',
    'cache/pdf/',
    'cache/smarty/',
    'cache/themes/',
    'cache/upload/',
    'cache/xml/'
];

foreach ($cacheDirs as $dir) {
    if (is_dir($dir)) {
        $files = glob($dir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
}

// 2. Run Quick Repair and Rebuild
echo "\n2. Running Quick Repair and Rebuild...\n";
require_once('modules/Administration/QuickRepairAndRebuild.php');
$rac = new RepairAndClear();
$rac->repairAndClearAll(['clearAll'], ['Deals'], false, true);

// 3. Rebuild extensions
echo "\n3. Rebuilding extensions...\n";
require_once('ModuleInstall/ModuleInstaller.php');
$mi = new ModuleInstaller();
$mi->rebuild_all();

// 4. Clear compiled templates
echo "\n4. Clearing compiled templates...\n";
if (is_dir('cache/smarty/templates_c/')) {
    $files = glob('cache/smarty/templates_c/*.php');
    foreach ($files as $file) {
        @unlink($file);
    }
}

// 5. Rebuild ACL cache
echo "\n5. Rebuilding ACL cache...\n";
require_once('modules/ACLActions/actiondefs.php');
ACLAction::removeActions('Deals');
ACLAction::addActions('Deals');

// 6. Clear theme cache
echo "\n6. Clearing theme cache...\n";
if (is_dir('cache/themes/')) {
    $files = glob('cache/themes/*/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

// 7. Final verification
echo "\n7. Final verification:\n";
echo "   - Deals in moduleList: " . (in_array('Deals', $GLOBALS['moduleList']) ? 'YES' : 'NO') . "\n";
echo "   - Deals ACL edit permission: " . (ACLController::checkAccess('Deals', 'edit', true) ? 'YES' : 'NO') . "\n";
echo "   - EditView exists: " . (file_exists('custom/modules/Deals/views/view.edit.php') ? 'YES' : 'NO') . "\n";

echo "\n=== Rebuild Complete ===\n\n";
echo "IMPORTANT: You MUST now:\n";
echo "1. Close ALL browser tabs with SuiteCRM\n";
echo "2. Clear your browser cache and cookies\n";
echo "3. Open a new browser window\n";
echo "4. Login to SuiteCRM again\n";
echo "5. Navigate to: http://localhost:8080/index.php?module=Deals&action=Pipeline\n";
echo "\nThe system should now work correctly.\n";