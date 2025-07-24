<?php
/**
 * Search definitions for mdeal_Leads module
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$searchdefs['mdeal_Leads'] = array(
    'templateMeta' => array(
        'maxColumns' => '3',
        'maxColumnsBasic' => '4',
        'widths' => array(
            'label' => '10',
            'field' => '30'
        ),
    ),
    'layout' => array(
        'basic_search' => array(
            'company_name' => array(
                'name' => 'company_name',
                'default' => true,
                'width' => '10%',
            ),
            'last_name' => array(
                'name' => 'last_name',
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
                'type' => 'enum',
                'label' => 'LBL_ASSIGNED_TO',
                'function' => array(
                    'name' => 'get_user_array',
                    'params' => array(false)
                ),
                'default' => true,
                'width' => '10%',
            ),
            array(
                'name' => 'favorites_only',
                'label' => 'LBL_FAVORITES_FILTER',
                'type' => 'bool',
                'default' => false,
                'width' => '10%',
            ),
        ),
        'advanced_search' => array(
            'company_name' => array(
                'name' => 'company_name',
                'default' => true,
            ),
            'first_name' => array(
                'name' => 'first_name',
                'default' => true,
            ),
            'last_name' => array(
                'name' => 'last_name',
                'default' => true,
            ),
            'status' => array(
                'name' => 'status',
                'default' => true,
            ),
            'rating' => array(
                'name' => 'rating',
                'default' => true,
            ),
            'pipeline_stage' => array(
                'name' => 'pipeline_stage',
                'default' => true,
            ),
            'lead_source' => array(
                'name' => 'lead_source',
                'default' => true,
            ),
            'industry' => array(
                'name' => 'industry',
                'default' => true,
            ),
            'phone_work' => array(
                'name' => 'phone_work',
                'label' => 'LBL_OFFICE_PHONE',
                'type' => 'phone',
                'default' => true,
            ),
            'phone_mobile' => array(
                'name' => 'phone_mobile',
                'label' => 'LBL_MOBILE_PHONE',
                'type' => 'phone',
                'default' => true,
            ),
            'email_address' => array(
                'name' => 'email_address',
                'default' => true,
            ),
            'website' => array(
                'name' => 'website',
                'default' => true,
            ),
            'annual_revenue' => array(
                'name' => 'annual_revenue',
                'default' => true,
            ),
            'employee_count' => array(
                'name' => 'employee_count',
                'default' => true,
            ),
            'qualification_score' => array(
                'name' => 'qualification_score',
                'default' => true,
            ),
            'primary_address_city' => array(
                'name' => 'primary_address_city',
                'label' => 'LBL_PRIMARY_ADDRESS_CITY',
                'default' => true,
            ),
            'primary_address_state' => array(
                'name' => 'primary_address_state',
                'label' => 'LBL_PRIMARY_ADDRESS_STATE',
                'default' => true,
            ),
            'primary_address_country' => array(
                'name' => 'primary_address_country',
                'label' => 'LBL_PRIMARY_ADDRESS_COUNTRY',
                'default' => true,
            ),
            'assigned_user_id' => array(
                'name' => 'assigned_user_id',
                'type' => 'enum',
                'label' => 'LBL_ASSIGNED_TO',
                'function' => array(
                    'name' => 'get_user_array',
                    'params' => array(false)
                ),
                'default' => true,
            ),
            'date_entered' => array(
                'name' => 'date_entered',
                'default' => true,
            ),
            'date_modified' => array(
                'name' => 'date_modified',
                'default' => true,
            ),
            'last_activity_date' => array(
                'name' => 'last_activity_date',
                'default' => true,
            ),
            'next_follow_up_date' => array(
                'name' => 'next_follow_up_date',
                'default' => true,
            ),
            array(
                'name' => 'favorites_only',
                'label' => 'LBL_FAVORITES_FILTER',
                'type' => 'bool',
                'default' => false,
            ),
        ),
    ),
);