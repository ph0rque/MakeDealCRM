<?php
/**
 * Logic hooks for Calls module
 * Updates contact last contact dates when calls are logged
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
    'updateContactsAfterCall'
);