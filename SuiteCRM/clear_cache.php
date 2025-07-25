<?php
/**
 * Clear all caches to ensure changes take effect
 */

// Clear PHP opcache if available
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✓ OpCache cleared<br>";
} else {
    echo "✗ OpCache not available<br>";
}

// Clear SuiteCRM cache directories
$cacheDirectories = [
    'cache/smarty/cache',
    'cache/smarty/templates_c',
    'cache/modules',
    'cache/jsLanguage',
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
        echo "✓ Cleared $dir<br>";
    }
}

// Clear module cache
if (file_exists('custom/application/Ext/Include/modules.ext.php')) {
    unlink('custom/application/Ext/Include/modules.ext.php');
    echo "✓ Cleared modules.ext.php<br>";
}

echo "<br><strong>Cache cleared!</strong><br>";
echo "Now try accessing the Deals module or run the debug script again.";
?>