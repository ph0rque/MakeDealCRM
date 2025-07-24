<?php
/**
 * Redirect controller for Opportunities module
 * Ensures AJAX calls work with both module names
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

// Include the Deals controller
require_once('custom/modules/Deals/controller.php');

// Extend it for Opportunities module
class OpportunitiesController extends DealsController
{
    // All functionality is inherited from DealsController
}