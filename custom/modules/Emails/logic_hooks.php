<?php
/**
 * Logic hooks for Emails module
 * Updates contact last contact dates when emails are sent
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$hook_version = 1;
$hook_array = array();

// After save hooks
$hook_array['after_save'] = array();
$hook_array['after_save'][] = array(
    1, 
    'Update Contact Last Contact Date', 
    'custom/modules/Contacts/ContactActivityHooks.php', 
    'ContactActivityHooks', 
    'updateContactsAfterEmailSent'
);

// Process deals emails
$hook_array['after_save'][] = array(
    2,
    'Process Deals Email',
    'custom/modules/Deals/DealsEmailLogicHook.php',
    'DealsEmailLogicHook',
    'processDealsEmail'
);