<?php
/**
 * Set default action for Deals module to pipeline view
 */

// Override the default action for Deals module
$moduleList[] = 'Deals';
$beanList['Deals'] = 'Deal';
$beanFiles['Deal'] = 'modules/Deals/Deal.php';

// Set pipeline as the default action when clicking on Deals in the header
$module_routing['Deals'] = array(
    'default_action' => 'pipeline'
);

// Also update the application menu to use pipeline as default
if (isset($app_list_strings['moduleList']['Deals'])) {
    // Module already exists in list
} else {
    $app_list_strings['moduleList']['Deals'] = 'Deals';
}

// Ensure the module is visible in the header navigation
// $modInvisList[] = 'Deals'; // Commented out to make module visible