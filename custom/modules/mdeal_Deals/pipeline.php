<?php
/**
 * Pipeline Action for mdeal_Deals
 * Direct action file approach
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

global $db, $current_user, $mod_strings, $app_strings;

// Set up the view
require_once('custom/modules/mdeal_Deals/views/view.pipeline.php');

$view = new mdeal_DealsViewPipeline();
$view->process();
$view->display();