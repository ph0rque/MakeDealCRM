<?php
/**
 * Search Fields for Deals Module
 * 
 * @package MakeDealCRM
 * @subpackage Deals
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$searchFields['Deals'] = array(
    'name' => array('query_type' => 'default'),
    'status' => array('query_type' => 'default'),
    'source' => array('query_type' => 'default'),
    'deal_value' => array('query_type' => 'default', 'db_field' => array('deal_value')),
    'at_risk_status' => array('query_type' => 'default'),
    'focus_c' => array('query_type' => 'default'),
    'current_user_only' => array(
        'query_type' => 'default',
        'db_field' => array('assigned_user_id'),
        'my_items' => true,
        'vname' => 'LBL_CURRENT_USER_FILTER',
        'type' => 'bool'
    ),
    'assigned_user_id' => array(
        'query_type' => 'default'
    ),
    'range_date_entered' => array(
        'query_type' => 'default',
        'enable_range_search' => true,
        'is_date_field' => true
    ),
    'start_range_date_entered' => array(
        'query_type' => 'default',
        'enable_range_search' => true,
        'is_date_field' => true
    ),
    'end_range_date_entered' => array(
        'query_type' => 'default',
        'enable_range_search' => true,
        'is_date_field' => true
    ),
    'range_date_modified' => array(
        'query_type' => 'default',
        'enable_range_search' => true,
        'is_date_field' => true
    ),
    'start_range_date_modified' => array(
        'query_type' => 'default',
        'enable_range_search' => true,
        'is_date_field' => true
    ),
    'end_range_date_modified' => array(
        'query_type' => 'default',
        'enable_range_search' => true,
        'is_date_field' => true
    ),
    'range_deal_value' => array(
        'query_type' => 'default',
        'enable_range_search' => true
    ),
    'start_range_deal_value' => array(
        'query_type' => 'default',
        'enable_range_search' => true
    ),
    'end_range_deal_value' => array(
        'query_type' => 'default',
        'enable_range_search' => true
    ),
    'range_asking_price_c' => array(
        'query_type' => 'default',
        'enable_range_search' => true
    ),
    'start_range_asking_price_c' => array(
        'query_type' => 'default',
        'enable_range_search' => true
    ),
    'end_range_asking_price_c' => array(
        'query_type' => 'default',
        'enable_range_search' => true
    ),
);