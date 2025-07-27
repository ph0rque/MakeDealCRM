<?php
/**
 * Simple cache clear script
 */

// SuiteCRM bootstrap
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');

echo "=== Simple Cache Clear ===\n\n";

// Clear specific cache files that might affect dropdown menus
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
                echo "Deleted: $file\n";
            }
        }
    }
}

// Rebuild extensions
echo "\nRebuilding extensions...\n";
require_once('ModuleInstall/ModuleInstaller.php');
$mi = new ModuleInstaller();
$mi->rebuild_menus();
$mi->rebuild_vardefs();

echo "\nCache cleared successfully!\n";
echo "\nPlease:\n";
echo "1. Clear your browser cache\n";
echo "2. Logout and login again\n";
echo "3. Test the Deals module\n";