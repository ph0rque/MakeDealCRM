<?php
/**
 * Script to rebuild Deals module cache and fix permissions
 */

// SuiteCRM bootstrap
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');

global $current_user, $db, $sugar_config;

// Set current user to admin
$current_user = new User();
$current_user->getSystemUser();

echo "Rebuilding Deals module cache...\n\n";

// 1. Clear module cache
echo "1. Clearing module cache...\n";
if (file_exists('cache/modules/Deals')) {
    $files = glob('cache/modules/Deals/*');
    foreach($files as $file) {
        if(is_file($file)) {
            unlink($file);
        }
    }
}

// 2. Rebuild Extensions
echo "2. Rebuilding extensions...\n";
require_once('ModuleInstall/ModuleInstaller.php');
$mi = new ModuleInstaller();
$mi->rebuild_all();

// 3. Clear vardefs cache
echo "3. Clearing vardefs cache...\n";
VardefManager::clearVardef('Deals', 'Deal');
VardefManager::refreshVardefs('Deals', 'Deal');

// 4. Rebuild relationships
echo "4. Rebuilding relationships...\n";
if (file_exists('modules/Administration/RebuildRelationship.php')) {
    require_once('modules/Administration/RebuildRelationship.php');
    if (function_exists('rebuildRelations')) {
        @rebuildRelations(array('Deals'));
    }
}

// 5. Clear smarty cache
echo "5. Clearing smarty cache...\n";
$files = glob('cache/smarty/templates_c/*.php');
foreach($files as $file) {
    if(is_file($file)) {
        unlink($file);
    }
}

// 6. Clear JavaScript cache
echo "6. Clearing JavaScript cache...\n";
$files = glob('cache/javascript/*.js');
foreach($files as $file) {
    if(is_file($file)) {
        unlink($file);
    }
}

// 7. Rebuild ACL cache
echo "7. Rebuilding ACL cache...\n";
require_once('modules/ACLActions/actiondefs.php');
ACLAction::removeActions('Deals');
ACLAction::addActions('Deals');

// 8. Verify Deals module is active
echo "\n8. Verifying Deals module status...\n";
$checkModule = "SELECT * FROM modules WHERE name = 'Deals' AND deleted = 0";
$result = $db->query($checkModule);
if ($row = $db->fetchByAssoc($result)) {
    echo "   - Deals module is active\n";
    echo "   - Tab enabled: " . ($row['tab'] ? 'Yes' : 'No') . "\n";
} else {
    echo "   - ERROR: Deals module not found! Creating it...\n";
    $insertModule = "INSERT INTO modules (id, name, date_entered, date_modified, modified_user_id, created_by, deleted, tab) 
                     VALUES (UUID(), 'Deals', NOW(), NOW(), '1', '1', 0, 1)";
    $db->query($insertModule);
}

// 9. Test ACL permissions
echo "\n9. Testing ACL permissions:\n";
$actions = ['list', 'view', 'edit', 'delete', 'import', 'export'];
foreach ($actions as $action) {
    $hasAccess = ACLController::checkAccess('Deals', $action, true);
    echo "   - $action: " . ($hasAccess ? 'ALLOWED' : 'DENIED') . "\n";
}

// 10. Clear all caches
echo "\n10. Clearing all caches...\n";
require_once('modules/Administration/QuickRepairAndRebuild.php');
$rac = new RepairAndClear();
$rac->clearTpls();
$rac->clearJsFiles();
$rac->clearDashlets();
$rac->clearThemeCache();
$rac->clearJsLangFiles();
// $rac->clearModuleCache(); // Method doesn't exist in this version

echo "\nDone! Please refresh your browser and try again.\n";
echo "You may need to logout and login again for the changes to take effect.\n";