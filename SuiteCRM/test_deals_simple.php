<?php
/**
 * Simple test to verify Deals module is working
 */

// Start session properly
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

define('sugarEntry', true);
require_once('include/entryPoint.php');

echo "<!DOCTYPE html><html><head><title>Deals Module Test</title></head><body>";
echo "<h2>Simple Deals Module Test</h2>";

// Test 1: Check if module is registered
global $beanList, $beanFiles, $moduleList;

echo "<h3>Module Registration:</h3>";
echo "<p>Deals in beanList: " . (isset($beanList['Deals']) ? 'Yes (' . $beanList['Deals'] . ')' : 'No') . "</p>";
echo "<p>Deal in beanFiles: " . (isset($beanFiles['Deal']) ? 'Yes (' . $beanFiles['Deal'] . ')' : 'No') . "</p>";
echo "<p>Deals in moduleList: " . (in_array('Deals', $moduleList) ? 'Yes' : 'No') . "</p>";

// Test 2: Try to create a Deal object
echo "<h3>Deal Object Test:</h3>";
try {
    require_once('modules/Opportunities/Opportunity.php');
    
    // Load the Deal class
    if (isset($beanFiles['Deal']) && file_exists($beanFiles['Deal'])) {
        require_once($beanFiles['Deal']);
        echo "<p>✓ Deal class file loaded</p>";
        
        if (class_exists('Deal')) {
            $deal = new Deal();
            echo "<p>✓ Deal object created successfully</p>";
            echo "<p>Module dir: " . $deal->module_dir . "</p>";
            echo "<p>Object name: " . $deal->object_name . "</p>";
            echo "<p>Table name: " . $deal->table_name . "</p>";
        } else {
            echo "<p>✗ Deal class not found</p>";
        }
    } else {
        echo "<p>✗ Deal class file not found in beanFiles</p>";
    }
} catch (Exception $e) {
    echo "<p>✗ Error: " . $e->getMessage() . "</p>";
}

// Test 3: Check if we can navigate to the module
echo "<h3>Module Navigation:</h3>";
echo "<p><a href='index.php?module=Deals&action=index'>Click here to navigate to Deals module</a></p>";
echo "<p><a href='index.php?module=Deals&action=pipeline'>Click here to navigate to Pipeline view</a></p>";

echo "</body></html>";
?>