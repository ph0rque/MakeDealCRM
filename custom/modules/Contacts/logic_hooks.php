<?php
/**
 * Logic hooks for Contacts module
 * Handles automatic updates for last contact tracking
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$hook_version = 1;
$hook_array = array();

// After relationship add hooks - for tracking when activities are linked to contacts
$hook_array['after_relationship_add'] = array();
$hook_array['after_relationship_add'][] = array(
    1, 
    'Update Last Contact Date on Activity Link', 
    'custom/modules/Contacts/ContactActivityHooks.php', 
    'ContactActivityHooks', 
    'updateLastContactOnActivityLink'
);

// After save hooks - for tracking direct contact updates
$hook_array['after_save'] = array();
$hook_array['after_save'][] = array(
    1, 
    'Track Contact Updates', 
    'custom/modules/Contacts/ContactActivityHooks.php', 
    'ContactActivityHooks', 
    'trackContactUpdate'
);

// Before save hooks - for validating contact roles
$hook_array['before_save'] = array();
$hook_array['before_save'][] = array(
    1, 
    'Validate Contact Role', 
    'custom/modules/Contacts/ContactActivityHooks.php', 
    'ContactActivityHooks', 
    'validateContactRole'
);