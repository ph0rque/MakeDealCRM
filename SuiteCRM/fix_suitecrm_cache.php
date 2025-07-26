<?php
/**
 * Fix SuiteCRM Cache and Language Issues
 * 
 * This script rebuilds all necessary caches and language files
 */

// Set up SuiteCRM environment
if (!defined('sugarEntry')) define('sugarEntry', true);
// Check if we need to change directory
if (file_exists('SuiteCRM/include/entryPoint.php')) {
    chdir('SuiteCRM');
}
require_once('include/entryPoint.php');
require_once('modules/Administration/QuickRepairAndRebuild.php');

echo "=== FIXING SUITECRM CACHE AND LANGUAGE ISSUES ===\n\n";

// 1. Clear all cache directories
echo "1. Clearing cache directories...\n";
$cacheDirs = array(
    'cache',
    'custom/modules/*/Ext',
    'custom/application/Ext',
    'cache/themes',
    'cache/jsLanguage',
    'cache/modules'
);

foreach ($cacheDirs as $dir) {
    $files = glob($dir . '/*');
    if (is_array($files)) {
        foreach ($files as $file) {
            if (is_dir($file)) {
                deleteDirectory($file);
            } else {
                @unlink($file);
            }
        }
    }
}
echo "   - Cache directories cleared\n";

// 2. Create necessary cache directories
echo "\n2. Creating cache directories...\n";
$dirs = array(
    'cache',
    'cache/images',
    'cache/layout',
    'cache/pdf',
    'cache/upload',
    'cache/xml',
    'cache/include',
    'cache/modules',
    'cache/jsLanguage',
    'cache/themes'
);

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}
echo "   - Cache directories created\n";

// 3. Run Quick Repair and Rebuild
echo "\n3. Running Quick Repair and Rebuild...\n";
$_REQUEST['repair_silent'] = '1';
$repair = new RepairAndClear();
$repair->repairAndClearAll(
    array('clearAll'),
    array(translate('LBL_ALL_MODULES')),
    true,
    false
);
echo "   - Quick Repair completed\n";

// 4. Rebuild .htaccess
echo "\n4. Rebuilding .htaccess...\n";
require_once('modules/Administration/UpgradeAccess.php');
rebuildhtaccess();
echo "   - .htaccess rebuilt\n";

// 5. Rebuild Config
echo "\n5. Rebuilding configuration...\n";
require_once('modules/Configurator/Configurator.php');
$configurator = new Configurator();
$configurator->loadConfig();
$configurator->handleOverride();
$configurator->saveConfig();
echo "   - Configuration rebuilt\n";

// 6. Rebuild Languages
echo "\n6. Rebuilding language files...\n";
require_once('modules/Administration/RebuildJSLang.php');
rebuildLanguages();
echo "   - Language files rebuilt\n";

// 7. Rebuild Sprites
echo "\n7. Rebuilding sprites...\n";
require_once('modules/UpgradeWizard/uw_utils.php');
rebuildSprites(true);
echo "   - Sprites rebuilt\n";

// 8. Clear opcache if available
echo "\n8. Clearing PHP opcache...\n";
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "   - Opcache cleared\n";
} else {
    echo "   - Opcache not available\n";
}

// 9. Set proper permissions
echo "\n9. Setting permissions...\n";
$files_to_chmod = array(
    'cache' => 0775,
    'custom' => 0775,
    'modules' => 0775,
    'themes' => 0775,
    'config.php' => 0644,
    'config_override.php' => 0644,
    '.htaccess' => 0644
);

foreach ($files_to_chmod as $file => $mode) {
    if (file_exists($file)) {
        @chmod($file, $mode);
    }
}
echo "   - Permissions set\n";

echo "\n=== FIX COMPLETE ===\n";
echo "\nNext steps:\n";
echo "1. Clear your browser cache (Ctrl+F5 or Cmd+Shift+R)\n";
echo "2. If issues persist, restart Apache/PHP service\n";
echo "3. Check that mod_rewrite is enabled in Apache\n";

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

function rebuildLanguages() {
    global $mod_strings, $sugar_config;
    
    require_once('include/utils/file_utils.php');
    require_once('include/utils/sugar_file_utils.php');
    
    $languages = get_languages();
    
    foreach ($languages as $lang_key => $lang_display) {
        // Rebuild app strings
        $app_list_strings = return_app_list_strings_language($lang_key);
        $app_strings = return_application_language($lang_key);
        
        // Create cache directories if needed
        if (!is_dir("cache/jsLanguage/{$lang_key}")) {
            mkdir_recursive("cache/jsLanguage/{$lang_key}");
        }
    }
    
    // Rebuild JS language files
    require_once('include/language/jsLanguage.php');
    jsLanguage::createAppStringsCache();
}