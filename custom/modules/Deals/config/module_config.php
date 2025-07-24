<?php
/**
 * Module configuration for Deals
 * Forces pipeline view as default
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

// Set the default action for the Deals module
$moduleConfig['Deals'] = array(
    'default_action' => 'pipeline',
    'default_view' => 'pipeline',
    'disable_list_view' => false,
    'force_pipeline_view' => true
);

// Also ensure the module menu uses pipeline as default
$moduleMenuConfig['Deals'] = array(
    'default_link' => 'index.php?module=Deals&action=pipeline'
);