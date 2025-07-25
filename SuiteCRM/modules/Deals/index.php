<?php
/**
 * Deals Module Entry Point Router
 * This file routes all Deals module requests to the appropriate action
 * Default action is 'pipeline' to show the Kanban board
 */

if (!defined('sugarEntry')) {
    define('sugarEntry', true);
}

// Include SuiteCRM framework
require_once('include/entryPoint.php');

// Get the requested action, default to pipeline
$action = !empty($_REQUEST['action']) ? $_REQUEST['action'] : 'pipeline';

// Map certain actions to pipeline for consistency
$pipeline_actions = array('index', 'ListView', 'listview', '');
if (in_array($action, $pipeline_actions)) {
    $action = 'pipeline';
}

// Build redirect URL to use SuiteCRM's proper routing
$redirect_params = array(
    'module' => 'Deals',
    'action' => $action
);

// Preserve other parameters
foreach ($_REQUEST as $key => $value) {
    if (!in_array($key, array('module', 'action'))) {
        $redirect_params[$key] = $value;
    }
}

// Build query string
$query_string = http_build_query($redirect_params);

// Redirect using SuiteCRM's index.php
$redirect_url = 'index.php?' . $query_string;

// Perform the redirect
header('Location: ' . $redirect_url);
exit();