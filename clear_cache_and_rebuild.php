<?php
/**
 * Clear cache and rebuild script
 */

// Set up SuiteCRM environment
if (!defined('sugarEntry')) define('sugarEntry', true);
chdir('SuiteCRM');
require_once('include/entryPoint.php');

echo "=== Clearing Cache and Rebuilding ===\n\n";

// 1. Clear all cache
echo "1. Clearing cache directories...\n";
$cache_dirs = array(
    'cache',
    'custom/modules/*/Ext',
    'custom/application/Ext'
);

foreach ($cache_dirs as $dir) {
    $pattern = $dir . '/*';
    $files = glob($pattern, GLOB_MARK);
    foreach ($files as $file) {
        if (is_dir($file)) {
            deleteDirectory($file);
        } else {
            @unlink($file);
        }
    }
}
echo "   - Cache cleared\n";

// 2. Rebuild extensions
echo "\n2. Rebuilding extensions...\n";
require_once('ModuleInstall/ModuleInstaller.php');
$mi = new ModuleInstaller();
$mi->rebuild_all(true);
echo "   - Extensions rebuilt\n";

// 3. Rebuild relationships
echo "\n3. Rebuilding relationships...\n";
require_once('modules/Administration/RebuildRelationship.php');
rebuildRelationships();
echo "   - Relationships rebuilt\n";

// 4. Clear JS cache
echo "\n4. Clearing JavaScript cache...\n";
require_once('modules/Administration/QuickRepairAndRebuild.php');
$rac = new RepairAndClear();
$rac->clearJsFiles();
echo "   - JavaScript cache cleared\n";

// 5. Quick repair and rebuild
echo "\n5. Running Quick Repair and Rebuild...\n";
$actions = array('clearAll');
$rac->repairAndClearAll($actions, array('All Modules'), false, true);
echo "   - Quick Repair completed\n";

echo "\n=== Cache Clear and Rebuild Complete ===\n";
echo "\nNext steps:\n";
echo "1. Navigate to Admin → Display Modules and Subpanels\n";
echo "2. Ensure 'Opportunities' is unchecked\n";
echo "3. Ensure 'Deals' is checked\n";
echo "4. Save the configuration\n";

function deleteDirectory($dir) {
    if (!file_exists($dir)) {
        return true;
    }

    if (!is_dir($dir)) {
        return unlink($dir);
    }

    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }

        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }

    return rmdir($dir);
}