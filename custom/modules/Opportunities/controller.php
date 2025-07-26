<?php
/**
 * Redirect controller for Opportunities module
 * Redirects all Opportunities module access to Deals module
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('include/MVC/Controller/SugarController.php');

class OpportunitiesController extends SugarController
{
    /**
     * Override process to redirect to Deals module
     */
    public function process()
    {
        // Get the current action
        $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'index';
        
        // Build redirect URL to Deals module with same action
        $redirect_url = 'index.php?module=Deals&action=' . $action;
        
        // Copy over any additional parameters
        $params_to_copy = array('record', 'return_module', 'return_action', 'return_id', 'ajax_load', 'ajaxLoad');
        foreach ($params_to_copy as $param) {
            if (isset($_REQUEST[$param])) {
                $redirect_url .= '&' . $param . '=' . urlencode($_REQUEST[$param]);
            }
        }
        
        // Special handling for pipeline view
        if ($action == 'pipeline') {
            $redirect_url = 'index.php?module=Deals&action=pipeline';
        }
        
        // Perform redirect
        SugarApplication::redirect($redirect_url);
    }
}