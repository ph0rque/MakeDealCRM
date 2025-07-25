<?php
/**
 * Test EditView directly
 */

// Initialize
session_start();
define('sugarEntry', true);
require_once('include/entryPoint.php');
require_once('modules/Users/User.php');

// Force admin login
global $current_user;
$current_user = new User();
$current_user->retrieve('1');
$current_user->authenticated = true;
$_SESSION['authenticated_user_id'] = $current_user->id;

// Set up required globals
$GLOBALS['module'] = 'Deals';
$GLOBALS['action'] = 'EditView';
$GLOBALS['record'] = '';

// Check if EditView can be loaded
echo "<!DOCTYPE html><html><head><title>EditView Test</title></head><body>";
echo "<h1>Testing Deals EditView</h1>";

// Test 1: Check module files
echo "<h2>Test 1: Module Files Check</h2>";
$editviewFile = 'modules/Deals/views/view.edit.php';
$customEditviewFile = 'custom/modules/Deals/views/view.edit.php';

if (file_exists($editviewFile)) {
    echo "<p style='color:green'>✓ Default EditView file exists: $editviewFile</p>";
} else {
    echo "<p style='color:orange'>⚠ No default EditView file</p>";
}

if (file_exists($customEditviewFile)) {
    echo "<p style='color:green'>✓ Custom EditView file exists: $customEditviewFile</p>";
} else {
    echo "<p style='color:orange'>⚠ No custom EditView file (using default)</p>";
}

// Test 2: Check metadata
echo "<h2>Test 2: Metadata Check</h2>";
$metadataFile = 'modules/Deals/metadata/editviewdefs.php';
$customMetadataFile = 'custom/modules/Deals/metadata/editviewdefs.php';

if (file_exists($metadataFile)) {
    echo "<p style='color:green'>✓ Default metadata exists: $metadataFile</p>";
} else {
    echo "<p style='color:orange'>⚠ No default metadata</p>";
}

if (file_exists($customMetadataFile)) {
    echo "<p style='color:green'>✓ Custom metadata exists: $customMetadataFile</p>";
} else {
    echo "<p style='color:orange'>⚠ No custom metadata (using default)</p>";
}

// Test 3: Try to create a new Deal bean
echo "<h2>Test 3: Create New Deal Bean</h2>";
try {
    $deal = BeanFactory::newBean('Deals');
    if ($deal) {
        echo "<p style='color:green'>✓ Successfully created new Deal bean</p>";
        echo "<p>Bean class: " . get_class($deal) . "</p>";
        echo "<p>Module dir: " . $deal->module_dir . "</p>";
        echo "<p>Object name: " . $deal->object_name . "</p>";
        echo "<p>Table name: " . $deal->table_name . "</p>";
    } else {
        echo "<p style='color:red'>✗ Failed to create Deal bean</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Exception: " . $e->getMessage() . "</p>";
}

// Test 4: Check if we can access EditView controller
echo "<h2>Test 4: EditView Controller Check</h2>";
try {
    // Check if we can instantiate the controller
    if (class_exists('ViewEdit')) {
        echo "<p style='color:green'>✓ ViewEdit class exists</p>";
        
        // Try to create an instance
        $view = new ViewEdit();
        $view->module = 'Deals';
        $view->bean = BeanFactory::newBean('Deals');
        
        echo "<p style='color:green'>✓ ViewEdit instance created</p>";
    } else {
        echo "<p style='color:red'>✗ ViewEdit class not found</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Exception: " . $e->getMessage() . "</p>";
}

// Test 5: Check vardefs
echo "<h2>Test 5: Vardefs Check</h2>";
$vardefFile = 'modules/Deals/vardefs.php';
$customVardefFile = 'custom/modules/Deals/vardefs.php';

if (file_exists($vardefFile)) {
    echo "<p style='color:green'>✓ Default vardefs exists: $vardefFile</p>";
} else {
    echo "<p style='color:orange'>⚠ No default vardefs</p>";
}

if (file_exists($customVardefFile)) {
    echo "<p style='color:green'>✓ Custom vardefs exists: $customVardefFile</p>";
} else {
    echo "<p style='color:orange'>⚠ No custom vardefs</p>";
}

// Try to load vardefs
global $dictionary;
if (isset($dictionary['Deal'])) {
    echo "<p style='color:green'>✓ Deal vardefs loaded in dictionary</p>";
    echo "<p>Fields defined: " . count($dictionary['Deal']['fields'] ?? []) . "</p>";
} else {
    echo "<p style='color:red'>✗ Deal vardefs not in dictionary</p>";
}

echo "<hr>";
echo "<h2>Quick Links</h2>";
echo "<p><a href='index.php?module=Deals&action=EditView'>Try EditView (Create New)</a></p>";
echo "<p><a href='index.php?module=Deals&action=index'>Go to Deals Module</a></p>";

// Test 6: Direct EditView render attempt
echo "<h2>Test 6: Direct EditView Render</h2>";
echo "<p>Attempting to render EditView form...</p>";

try {
    // Set up minimal environment
    $_REQUEST['module'] = 'Deals';
    $_REQUEST['action'] = 'EditView';
    $_REQUEST['return_module'] = 'Deals';
    $_REQUEST['return_action'] = 'DetailView';
    
    // Create a new deal for the form
    $testDeal = BeanFactory::newBean('Deals');
    
    // Check if we have the view
    $viewFile = 'include/MVC/View/views/view.edit.php';
    if (file_exists($viewFile)) {
        echo "<p style='color:green'>✓ Base EditView file exists</p>";
        
        // Try to include ViewEdit class
        require_once($viewFile);
        
        if (class_exists('ViewEdit')) {
            echo "<p style='color:green'>✓ ViewEdit class loaded successfully</p>";
        }
    } else {
        echo "<p style='color:red'>✗ Base EditView file not found</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Error during render: " . $e->getMessage() . "</p>";
}

echo "</body></html>";
?>