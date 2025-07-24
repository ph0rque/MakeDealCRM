<?php
/**
 * List view definitions for Deals module
 */

$module_name = 'Deals';
$listViewDefs[$module_name] = array(
    'NAME' => array(
        'width' => '20%',
        'label' => 'LBL_LIST_DEAL_NAME',
        'link' => true,
        'default' => true,
    ),
    'ACCOUNT_NAME' => array(
        'width' => '20%',
        'label' => 'LBL_LIST_ACCOUNT_NAME',
        'id' => 'ACCOUNT_ID',
        'module' => 'Accounts',
        'link' => true,
        'default' => true,
        'sortable' => true,
        'ACLTag' => 'ACCOUNT',
        'related_fields' => array('account_id'),
    ),
    'PIPELINE_STAGE_C' => array(
        'width' => '15%',
        'label' => 'LBL_LIST_PIPELINE_STAGE',
        'default' => true,
    ),
    'AMOUNT' => array(
        'width' => '10%',
        'label' => 'LBL_LIST_AMOUNT',
        'align' => 'right',
        'default' => true,
        'currency_format' => true,
    ),
    'DAYS_IN_STAGE_C' => array(
        'width' => '8%',
        'label' => 'LBL_LIST_DAYS_IN_STAGE',
        'default' => true,
        'align' => 'center',
        'sortable' => false,
    ),
    'ASSIGNED_USER_NAME' => array(
        'width' => '10%',
        'label' => 'LBL_LIST_ASSIGNED_USER',
        'module' => 'Users',
        'id' => 'ASSIGNED_USER_ID',
        'default' => true,
    ),
    'DATE_ENTERED' => array(
        'width' => '10%',
        'label' => 'LBL_LIST_DATE_ENTERED',
        'default' => true,
    ),
    'EXPECTED_CLOSE_DATE_C' => array(
        'width' => '10%',
        'label' => 'LBL_EXPECTED_CLOSE_DATE',
        'default' => false,
    ),
    'DEAL_SOURCE_C' => array(
        'width' => '10%',
        'label' => 'LBL_DEAL_SOURCE',
        'default' => false,
    ),
);