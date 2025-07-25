<?php
/**
 * Deals Module Entry Point
 * This file handles the initial module loading and redirects to the appropriate action
 */

if (!defined('sugarEntry')) {
    define('sugarEntry', true);
}

// Change to SuiteCRM directory for proper includes
chdir('../../../SuiteCRM');

// Include the SuiteCRM entry point
require_once('include/entryPoint.php');

// Now redirect to the actual Deals module with the correct action
$action = !empty($_REQUEST['action']) ? $_REQUEST['action'] : 'index';

// Build the redirect URL
$redirect_url = 'index.php?module=Deals&action=' . $action;

// Preserve any additional parameters
foreach ($_REQUEST as $key => $value) {
    if ($key !== 'module' && $key !== 'action') {
        $redirect_url .= '&' . urlencode($key) . '=' . urlencode($value);
    }
}

// Redirect to the proper location
header('Location: ' . $redirect_url);
exit();