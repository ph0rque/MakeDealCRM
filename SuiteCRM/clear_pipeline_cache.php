<?php
/**
 * Clear pipeline cache
 */

if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');

echo "Clearing pipeline cache...\n\n";

// Clear SugarCache
if (class_exists('SugarCache')) {
    $cache = SugarCache::instance();
    $cache->flush();
    echo "✅ SugarCache cleared\n";
}

// Clear template cache
$templateCache = 'cache/smarty/templates_c/*';
$files = glob($templateCache);
foreach ($files as $file) {
    if (is_file($file)) {
        unlink($file);
    }
}
echo "✅ Template cache cleared\n";

// Clear any pipeline-specific cache
global $db;
$db->query("DELETE FROM config WHERE category = 'pipeline_cache'");
echo "✅ Pipeline-specific cache cleared\n";

echo "\n✅ All caches cleared successfully!\n";
echo "Please refresh your browser to see the changes.\n";
?>