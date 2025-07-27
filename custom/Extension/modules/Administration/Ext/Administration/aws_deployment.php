<?php
/**
 * Add AWS Deployment to Admin Menu
 */

$admin_option_defs = array();
$admin_option_defs['Administration']['aws_deployment'] = array(
    'Administration',
    'LBL_AWS_DEPLOYMENT',
    'LBL_AWS_DEPLOYMENT_DESC',
    'index.php?module=Administration&action=aws_deployment'
);

$admin_group_header[] = array(
    'LBL_AWS_DEPLOYMENT_HEADER',
    '',
    false,
    $admin_option_defs,
    'LBL_AWS_DEPLOYMENT_DESC'
);