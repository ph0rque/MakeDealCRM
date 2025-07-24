<?php
/**
 * Redirect to Deals module Pipeline view
 * This ensures compatibility with both module names
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

// Include the Deals Pipeline view
require_once('custom/modules/Deals/views/view.pipeline.php');

// Extend it for Opportunities module
class OpportunitiesViewPipeline extends DealsViewPipeline
{
    // All functionality is inherited from DealsViewPipeline
}