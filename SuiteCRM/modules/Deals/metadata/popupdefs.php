<?php
/**
 * Popup Definitions for Deals Module
 * 
 * @package MakeDealCRM
 * @subpackage Deals
 */

global $mod_strings;

$popupMeta = array(
    'moduleMain' => 'Deal',
    'varName' => 'DEAL',
    'orderBy' => 'deals.name',
    'whereClauses' => array(
        'name' => 'deals.name',
        'status' => 'deals.status',
        'assigned_user_name' => 'deals.assigned_user_name',
    ),
    'searchInputs' => array('name', 'status', 'assigned_user_name'),
    'listviewdefs' => array(
        'NAME' => array(
            'width' => '30%',
            'label' => 'LBL_LIST_NAME',
            'link' => true,
            'default' => true,
        ),
        'STATUS' => array(
            'width' => '15%',
            'label' => 'LBL_LIST_STATUS',
            'default' => true,
        ),
        'DEAL_VALUE' => array(
            'width' => '15%',
            'label' => 'LBL_LIST_DEAL_VALUE',
            'currency_format' => true,
            'default' => true,
        ),
        'ASSIGNED_USER_NAME' => array(
            'width' => '15%',
            'label' => 'LBL_LIST_ASSIGNED_USER',
            'default' => true,
        ),
        'DATE_ENTERED' => array(
            'width' => '15%',
            'label' => 'LBL_LIST_DATE_ENTERED',
            'default' => true,
        ),
    ),
    'searchdefs' => array(
        'name',
        array(
            'name' => 'status',
            'type' => 'enum',
            'label' => 'LBL_STATUS',
        ),
        array(
            'name' => 'assigned_user_id',
            'label' => 'LBL_ASSIGNED_TO',
            'type' => 'enum',
            'function' => array(
                'name' => 'get_user_array',
                'params' => array(false),
            ),
        ),
    ),
);