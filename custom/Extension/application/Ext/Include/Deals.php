<?php
/**
 * Deals Module Loader Entry
 * 
 * This file registers the Deals module with SuiteCRM's module loader
 * 
 * @package MakeDealCRM
 * @module Deals
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

// Register module with beanList
$beanList['Deals'] = 'Deal';
// Point to the Deal bean file in SuiteCRM
$beanFiles['Deal'] = 'SuiteCRM/modules/Deals/Deal.php';

// Add module to moduleList (makes it visible in the UI)
$moduleList[] = 'Deals';

// Remove from modInvisList to ensure visibility
// $modInvisList[] = 'Deals';

// Tab configuration
$modules_exempt_from_availability_check['Deals'] = 'Deals';

// Add module to system tabs
$system_tabs['Deals'] = 'Deals';

// Register module for mobile
$mobile_modules[] = 'Deals';

// Register module portal visibility
$portal_modules[] = 'Deals';

// Add module to import modules
$import_modules[] = 'Deals';

// Add module to global search with extended fields
$unified_search_modules['Deals'] = array(
    'searchfields' => array(
        'name',
        'account_name',
        'amount',
        'pipeline_stage_c',
        'assigned_user_name',
        'date_entered',
        'date_modified',
        'expected_close_date_c',
        'deal_source_c'
    )
);

// Register for workflow
$workflow_object_list['Deals'] = 'Deal';
$workflow_modules[] = 'Deals';

// Register for reporting
$report_modules[] = 'Deals';

// Add to module groups for better organization
$moduleGroups['Sales']['modules'][] = 'Deals';