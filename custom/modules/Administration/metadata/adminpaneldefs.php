<?php
/**
 * Custom Administration Panel Definitions
 * Adds AWS EC2 Instance Management to the admin panel
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

// Add AWS Instance Management to the System section
$admin_option_defs = array();

$admin_option_defs['Administration']['aws_instances'] = array(
    'Administration',
    'LBL_AWS_INSTANCES',
    'LBL_AWS_INSTANCES_DESC',
    './index.php?module=Administration&action=aws_instances',
    'aws_instances'
);

$admin_group_header[] = array(
    'LBL_AWS_MANAGEMENT',
    '',
    false,
    $admin_option_defs,
    'LBL_AWS_MANAGEMENT_DESC'
);