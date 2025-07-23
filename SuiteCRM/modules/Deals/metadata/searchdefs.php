<?php
/**
 * Search Definitions for Deals Module
 * 
 * @package MakeDealCRM
 * @subpackage Deals
 */

$module_name = 'Deals';
$searchdefs[$module_name] = array(
    'layout' => array(
        'basic_search' => array(
            'name' => array(
                'name' => 'name',
                'default' => true,
                'width' => '10%',
            ),
            'status' => array(
                'name' => 'status',
                'default' => true,
                'width' => '10%',
            ),
            'assigned_user_id' => array(
                'name' => 'assigned_user_id',
                'label' => 'LBL_ASSIGNED_TO',
                'type' => 'enum',
                'function' => array(
                    'name' => 'get_user_array',
                    'params' => array(false),
                ),
                'default' => true,
                'width' => '10%',
            ),
            'current_user_only' => array(
                'name' => 'current_user_only',
                'label' => 'LBL_CURRENT_USER_FILTER',
                'type' => 'bool',
                'default' => true,
                'width' => '10%',
            ),
        ),
        'advanced_search' => array(
            'name' => array(
                'name' => 'name',
                'default' => true,
                'width' => '10%',
            ),
            'status' => array(
                'name' => 'status',
                'default' => true,
                'width' => '10%',
            ),
            'source' => array(
                'name' => 'source',
                'default' => true,
                'width' => '10%',
            ),
            'deal_value' => array(
                'name' => 'deal_value',
                'default' => true,
                'width' => '10%',
            ),
            'at_risk_status' => array(
                'name' => 'at_risk_status',
                'default' => true,
                'width' => '10%',
            ),
            'focus_c' => array(
                'name' => 'focus_c',
                'label' => 'LBL_FOCUS',
                'type' => 'bool',
                'default' => true,
                'width' => '10%',
            ),
            'asking_price_c' => array(
                'name' => 'asking_price_c',
                'default' => false,
                'width' => '10%',
            ),
            'ttm_revenue_c' => array(
                'name' => 'ttm_revenue_c',
                'default' => false,
                'width' => '10%',
            ),
            'ttm_ebitda_c' => array(
                'name' => 'ttm_ebitda_c',
                'default' => false,
                'width' => '10%',
            ),
            'proposed_valuation_c' => array(
                'name' => 'proposed_valuation_c',
                'default' => false,
                'width' => '10%',
            ),
            'target_multiple_c' => array(
                'name' => 'target_multiple_c',
                'default' => false,
                'width' => '10%',
            ),
            'assigned_user_id' => array(
                'name' => 'assigned_user_id',
                'label' => 'LBL_ASSIGNED_TO',
                'type' => 'enum',
                'function' => array(
                    'name' => 'get_user_array',
                    'params' => array(false),
                ),
                'default' => true,
                'width' => '10%',
            ),
            'date_entered' => array(
                'name' => 'date_entered',
                'default' => true,
                'width' => '10%',
            ),
            'date_modified' => array(
                'name' => 'date_modified',
                'default' => true,
                'width' => '10%',
            ),
            'created_by' => array(
                'name' => 'created_by',
                'label' => 'LBL_CREATED',
                'type' => 'enum',
                'function' => array(
                    'name' => 'get_user_array',
                    'params' => array(false),
                ),
                'default' => false,
                'width' => '10%',
            ),
            'modified_user_id' => array(
                'name' => 'modified_user_id',
                'label' => 'LBL_MODIFIED',
                'type' => 'enum',
                'function' => array(
                    'name' => 'get_user_array',
                    'params' => array(false),
                ),
                'default' => false,
                'width' => '10%',
            ),
            'current_user_only' => array(
                'name' => 'current_user_only',
                'label' => 'LBL_CURRENT_USER_FILTER',
                'type' => 'bool',
                'default' => false,
                'width' => '10%',
            ),
        ),
    ),
    'templateMeta' => array(
        'maxColumns' => '3',
        'maxColumnsBasic' => '4',
        'widths' => array(
            'label' => '10',
            'field' => '30',
        ),
    ),
);