<?php
/**
 * Module registration for Deals
 */

$beanList['Deals'] = 'Deal';
$beanFiles['Deal'] = 'custom/modules/Deals/Deal.php';
$moduleList[] = 'Deals';

// Add to the displayed modules
$modInvisList[] = 'Deals';

// Tab configuration
$modules_exempt_from_availability_check['Deals'] = 'Deals';

// Add module to global search
$unified_search_modules['Deals'] = array(
    'searchfields' => array(
        'name',
        'account_name',
        'amount',
        'pipeline_stage_c',
        'assigned_user_name'
    )
);