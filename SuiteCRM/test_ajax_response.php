<?php
// Test script to check AJAX response format
define('sugarEntry', true);
require_once('config.php');
require_once('include/entryPoint.php');

// Simulate AJAX request
$_REQUEST['module'] = 'Deals';
$_REQUEST['action'] = 'index';
$_REQUEST['ajax'] = '1';
$_SERVER['REQUEST_METHOD'] = 'GET';

// Capture output
ob_start();

// Try to process the request
try {
    $controller = ControllerFactory::getController('Deals');
    
    // Check what view is being set
    echo "Controller class: " . get_class($controller) . "\n";
    echo "Action: " . $controller->action . "\n";
    
    // Process the request
    $controller->execute();
    
    $output = ob_get_contents();
    ob_end_clean();
    
    echo "Output length: " . strlen($output) . "\n";
    echo "First 500 chars:\n";
    echo substr($output, 0, 500) . "\n";
    
    // Check if it's valid JSON
    $jsonStart = strpos($output, '{');
    if ($jsonStart !== false) {
        $jsonContent = substr($output, $jsonStart);
        $decoded = json_decode($jsonContent, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "\nValid JSON detected:\n";
            print_r($decoded);
        } else {
            echo "\nInvalid JSON: " . json_last_error_msg() . "\n";
            echo "JSON content:\n" . substr($jsonContent, 0, 200) . "\n";
        }
    } else {
        echo "\nNo JSON content found in output\n";
    }
    
} catch (Exception $e) {
    ob_end_clean();
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}