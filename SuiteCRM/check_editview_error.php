<?php
/**
 * Check for EditView errors
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();
define('sugarEntry', true);

// Change to SuiteCRM directory
chdir(dirname(__FILE__));

echo "<!DOCTYPE html><html><head><title>EditView Error Check</title></head><body>";
echo "<h1>EditView Error Check</h1>";

// Test 1: Check if we can load entryPoint
echo "<h2>Test 1: Loading entryPoint.php</h2>";
try {
    require_once('include/entryPoint.php');
    echo "<p style='color:green'>✓ entryPoint.php loaded successfully</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Error loading entryPoint.php: " . $e->getMessage() . "</p>";
}

// Test 2: Force admin login
echo "<h2>Test 2: Authentication</h2>";
try {
    require_once('modules/Users/User.php');
    global $current_user;
    $current_user = new User();
    $current_user->retrieve('1');
    $current_user->authenticated = true;
    $_SESSION['authenticated_user_id'] = $current_user->id;
    echo "<p style='color:green'>✓ Admin user authenticated</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Authentication error: " . $e->getMessage() . "</p>";
}

// Test 3: Check if ViewEdit class exists
echo "<h2>Test 3: ViewEdit Class</h2>";
$viewEditFile = 'include/MVC/View/views/view.edit.php';
if (file_exists($viewEditFile)) {
    echo "<p style='color:green'>✓ view.edit.php file exists</p>";
    require_once($viewEditFile);
    if (class_exists('ViewEdit')) {
        echo "<p style='color:green'>✓ ViewEdit class loaded</p>";
    } else {
        echo "<p style='color:red'>✗ ViewEdit class not found</p>";
    }
} else {
    echo "<p style='color:red'>✗ view.edit.php file not found</p>";
}

// Test 4: Check DealsViewEdit
echo "<h2>Test 4: DealsViewEdit</h2>";
$dealsViewEditFile = 'modules/Deals/views/view.edit.php';
if (file_exists($dealsViewEditFile)) {
    echo "<p style='color:green'>✓ Deals view.edit.php exists</p>";
    require_once($dealsViewEditFile);
    if (class_exists('DealsViewEdit')) {
        echo "<p style='color:green'>✓ DealsViewEdit class loaded</p>";
    } else {
        echo "<p style='color:red'>✗ DealsViewEdit class not found</p>";
    }
} else {
    echo "<p style='color:red'>✗ Deals view.edit.php not found</p>";
}

// Test 5: Try to instantiate the view
echo "<h2>Test 5: Instantiate DealsViewEdit</h2>";
try {
    if (class_exists('DealsViewEdit')) {
        $view = new DealsViewEdit();
        echo "<p style='color:green'>✓ DealsViewEdit instantiated</p>";
        
        // Set required properties
        $view->module = 'Deals';
        $view->bean = BeanFactory::newBean('Deals');
        $view->action = 'EditView';
        
        echo "<p style='color:green'>✓ View properties set</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Error instantiating view: " . $e->getMessage() . "</p>";
}

// Test 6: Check metadata
echo "<h2>Test 6: Check Metadata</h2>";
$metadataFile = 'modules/Deals/metadata/editviewdefs.php';
if (file_exists($metadataFile)) {
    echo "<p style='color:green'>✓ editviewdefs.php exists</p>";
    
    // Try to load it
    $viewdefs = array();
    include($metadataFile);
    
    if (!empty($viewdefs)) {
        echo "<p style='color:green'>✓ Metadata loaded successfully</p>";
        echo "<p>Panels defined: " . count($viewdefs['Deals']['EditView']['panels'] ?? []) . "</p>";
    } else {
        echo "<p style='color:red'>✗ No metadata found</p>";
    }
} else {
    echo "<p style='color:red'>✗ editviewdefs.php not found</p>";
}

// Test 7: Check for any output buffer issues
echo "<h2>Test 7: Output Buffer</h2>";
$ob_level = ob_get_level();
echo "<p>Output buffer level: " . $ob_level . "</p>";
if ($ob_level > 0) {
    echo "<p style='color:orange'>⚠ Output buffering is active</p>";
}

// Test 8: Check error log
echo "<h2>Test 8: Recent Errors</h2>";
$errorLog = ini_get('error_log');
if ($errorLog && file_exists($errorLog)) {
    $errors = file_get_contents($errorLog);
    $lines = explode("\n", $errors);
    $recentErrors = array_slice($lines, -10);
    
    echo "<p>Last 10 error log entries:</p>";
    echo "<pre style='background:#f0f0f0; padding:10px; overflow:auto;'>";
    foreach ($recentErrors as $error) {
        if (trim($error)) {
            echo htmlspecialchars($error) . "\n";
        }
    }
    echo "</pre>";
} else {
    echo "<p>Error log not accessible</p>";
}

echo "</body></html>";
?>