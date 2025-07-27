<?php
/**
 * Repair script for Deals module
 */

if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');

global $current_user;

// Set current user to admin
$current_user = new User();
$current_user->getSystemUser();

echo "<!DOCTYPE html><html><head><title>Repair Deals Module</title></head><body>";
echo "<h1>Repairing Deals Module...</h1>";

// 1. Clear cache
echo "<h2>1. Clearing Cache</h2>";
$cacheFiles = [
    'cache/modules/Deals/*',
    'cache/jsLanguage/Deals/*',
    'cache/themes/*/modules/Deals/*',
    'cache/smarty/templates_c/*',
    'cache/javascript/*',
    'cache/api/metadata/*'
];

foreach ($cacheFiles as $pattern) {
    $files = glob($pattern);
    if ($files) {
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
echo "<p>Cache cleared successfully!</p>";

// 2. Rebuild extensions
echo "<h2>2. Rebuilding Extensions</h2>";
require_once('ModuleInstall/ModuleInstaller.php');
$mi = new ModuleInstaller();
$mi->rebuild_all();
echo "<p>Extensions rebuilt!</p>";

// 3. Rebuild ACL
echo "<h2>3. Rebuilding ACL</h2>";
require_once('modules/ACLActions/actiondefs.php');
ACLAction::removeActions('Deals');
ACLAction::addActions('Deals');
echo "<p>ACL rebuilt!</p>";

// 4. Clear vardefs
echo "<h2>4. Clearing Vardefs</h2>";
VardefManager::clearVardef('Deals', 'Deal');
VardefManager::refreshVardefs('Deals', 'Deal');
echo "<p>Vardefs refreshed!</p>";

// 5. Quick Repair
echo "<h2>5. Running Quick Repair</h2>";
require_once('modules/Administration/QuickRepairAndRebuild.php');
$rac = new RepairAndClear();
$rac->clearTpls();
$rac->clearJsFiles();
$rac->clearDashlets();
$rac->clearThemeCache();
$rac->clearJsLangFiles();
echo "<p>Quick repair completed!</p>";

echo "<h2>Repair Complete!</h2>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ol>";
echo "<li>Clear your browser cache (Ctrl+Shift+Delete)</li>";
echo "<li>Logout and login again</li>";
echo "<li><a href='test_deals_buttons.php'>Test the buttons</a></li>";
echo "<li><a href='index.php?module=Deals&action=Pipeline'>Go to Pipeline</a></li>";
echo "</ol>";
echo "</body></html>";