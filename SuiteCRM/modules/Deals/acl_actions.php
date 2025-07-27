<?php
/**
 * ACL Actions for Deals Module
 * Defines the access control list actions for the Deals module
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

$acl_actions = array(
    'module' => array(
        'access' => array('aclaccess' => 90),
        'view' => array('aclaccess' => 90),
        'list' => array('aclaccess' => 90),
        'edit' => array('aclaccess' => 90),
        'delete' => array('aclaccess' => 90),
        'import' => array('aclaccess' => 90),
        'export' => array('aclaccess' => 90),
    ),
);
?>