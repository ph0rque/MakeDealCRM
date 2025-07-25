<?php
/**
 * Fix AJAX loading issues for Deals module
 */

// Start session properly
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

define('sugarEntry', true);
require_once('include/entryPoint.php');

echo "<!DOCTYPE html><html><head><title>Fix AJAX Loading</title></head><body>";
echo "<h2>Fixing AJAX Loading Issues</h2>";

// Check if we need to fix the modules.ext.php file
$extFile = 'custom/application/Ext/Include/modules.ext.php';
if (file_exists($extFile)) {
    echo "<p>Rebuilding modules.ext.php...</p>";
    
    // Force rebuild
    if (unlink($extFile)) {
        echo "<p>✓ Removed old cache file</p>";
    }
}

// Clear other cache files
$cacheFiles = [
    'cache/file_map.php',
    'cache/class_map.php',
    'cache/smarty/templates_c/*',
    'cache/modules/Deals/*',
    'cache/jsLanguage/Deals/*'
];

foreach ($cacheFiles as $pattern) {
    $files = glob($pattern);
    if ($files) {
        foreach ($files as $file) {
            if (is_file($file) && unlink($file)) {
                echo "<p>✓ Cleared: $file</p>";
            }
        }
    }
}

// Test direct module loading
echo "<h3>Testing Direct Module Access:</h3>";
echo '<p><a href="index.php?module=Deals&action=index&ajaxUI=false">Try Deals Module (no AJAX)</a></p>';
echo '<p><a href="index.php?module=Deals&action=pipeline&ajaxUI=false">Try Pipeline View (no AJAX)</a></p>';

echo "<h3>Actions:</h3>";
echo "<ol>";
echo "<li>Click one of the links above to test without AJAX</li>";
echo "<li>If that works, run Quick Repair and Rebuild again</li>";
echo "<li>Clear browser cache (Ctrl+F5)</li>";
echo "</ol>";

echo "</body></html>";
?>