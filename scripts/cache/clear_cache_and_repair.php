<?php
/**
 * Clear cache and run Quick Repair
 * This ensures the Deals module defaults to pipeline view
 */

define('sugarEntry', true);
require_once('include/entryPoint.php');

global $current_user;
$current_user = BeanFactory::getBean('Users', '1'); // Admin user

echo "🧹 Clearing SuiteCRM cache and running repairs...\n\n";

// 1. Clear the cache
echo "1️⃣ Clearing cache directories...\n";
$cacheDirectories = [
    'cache/smarty/templates_c',
    'cache/smarty/cache',
    'cache/modules',
    'cache/jsLanguage',
    'cache/api',
    'cache/include'
];

foreach ($cacheDirectories as $dir) {
    if (is_dir($dir)) {
        $files = glob($dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        echo "   ✅ Cleared: $dir\n";
    }
}

// 2. Clear module routing cache
echo "\n2️⃣ Clearing module routing cache...\n";
if (file_exists('cache/file_map.php')) {
    unlink('cache/file_map.php');
    echo "   ✅ Removed file_map.php\n";
}

// 3. Run Quick Repair and Rebuild
echo "\n3️⃣ Running Quick Repair and Rebuild...\n";
require_once('modules/Administration/QuickRepairAndRebuild.php');
$repair = new RepairAndClear();
$repair->repairAndClearAll(['clearAll'], ['Deals'], false, true);
echo "   ✅ Quick Repair completed\n";

// 4. Rebuild Extensions
echo "\n4️⃣ Rebuilding Extensions...\n";
require_once('ModuleInstall/ModuleInstaller.php');
$moduleInstaller = new ModuleInstaller();
$moduleInstaller->rebuild_all(true);
echo "   ✅ Extensions rebuilt\n";

// 5. Clear Smarty cache specifically
echo "\n5️⃣ Clearing Smarty compiled templates...\n";
require_once('include/Smarty/Smarty.class.php');
$smarty = new Sugar_Smarty();
$smarty->clear_all_cache();
$smarty->clearCompiledTemplate();
echo "   ✅ Smarty cache cleared\n";

echo "\n✨ All caches cleared and repairs completed!\n";
echo "\nNext steps:\n";
echo "1. Navigate to the Deals module in your browser\n";
echo "2. The pipeline view should now be the default\n";
echo "3. If not, try logging out and back in\n";
echo "4. Clear your browser cache as well\n";
?>