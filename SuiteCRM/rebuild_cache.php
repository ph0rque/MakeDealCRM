<?php
/**
 * Cache Rebuild Script for SuiteCRM
 * This script clears the cache and rebuilds extensions
 */

if (!defined('sugarEntry')) {
    define('sugarEntry', true);
}

// Set up the environment
chdir(dirname(__FILE__));
require_once('include/entryPoint.php');

// Clear cache
echo "Clearing SuiteCRM cache...\n";
$cache = SugarCache::instance();
$cache->flush();

// Clear file cache
if (is_dir('cache')) {
    exec('rm -rf cache/*');
    echo "File cache cleared.\n";
}

// Rebuild extensions
echo "Rebuilding extensions...\n";
require_once('ModuleInstall/ModuleInstaller.php');
$moduleInstaller = new ModuleInstaller();
$moduleInstaller->silent = true;

// Rebuild language files
$moduleInstaller->rebuild_languages(array('en_us' => 'en_us'));
echo "Language files rebuilt.\n";

// Rebuild relationships
$moduleInstaller->rebuild_relationships();
echo "Relationships rebuilt.\n";

// Rebuild extensions
$moduleInstaller->rebuild_extensions();
echo "Extensions rebuilt.\n";

// Clear additional caches
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OpCache cleared.\n";
}

echo "Cache rebuild completed successfully!\n";
?>