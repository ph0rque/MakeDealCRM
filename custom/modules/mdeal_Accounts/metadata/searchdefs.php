<?php
/**
 * Search definitions for mdeal_Accounts module
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$searchdefs['mdeal_Accounts'] = array(
    'templateMeta' => array(
        'maxColumns' => '3',
        'maxColumnsBasic' => '4',
        'widths' => array('label' => '10', 'field' => '30'),
    ),
    'layout' => array(
        'basic_search' => array(
            'name' => array(
                'name' => 'name',
                'default' => true,
                'width' => '10%'
            ),
            'account_type' => array(
                'name' => 'account_type',
                'default' => true,
                'width' => '10%'
            ),
            'industry' => array(
                'name' => 'industry',
                'default' => true,
                'width' => '10%'
            ),
            'current_user_only' => array(
                'name' => 'current_user_only',
                'label' => 'LBL_CURRENT_USER_FILTER',
                'type' => 'bool',
                'default' => true,
                'width' => '10%'
            ),
        ),
        'advanced_search' => array(
            'name' => array(
                'name' => 'name',
                'default' => true,
                'width' => '10%'
            ),
            'account_type' => array(
                'name' => 'account_type',
                'default' => true,
                'width' => '10%'
            ),
            'industry' => array(
                'name' => 'industry',
                'default' => true,
                'width' => '10%'
            ),
            'sub_industry' => array(
                'name' => 'sub_industry',
                'default' => false,
                'width' => '10%'
            ),
            'website' => array(
                'name' => 'website',
                'default' => false,
                'width' => '10%'
            ),
            'phone_office' => array(
                'name' => 'phone_office',
                'label' => 'LBL_ANY_PHONE',
                'type' => 'name',
                'default' => false,
                'width' => '10%'
            ),
            'email' => array(
                'name' => 'email',
                'label' => 'LBL_ANY_EMAIL',
                'type' => 'name',
                'default' => false,
                'width' => '10%'
            ),
            'billing_address_street' => array(
                'name' => 'billing_address_street',
                'label' => 'LBL_ANY_ADDRESS',
                'type' => 'name',
                'default' => false,
                'width' => '10%'
            ),
            'billing_address_city' => array(
                'name' => 'billing_address_city',
                'default' => false,
                'width' => '10%'
            ),
            'billing_address_state' => array(
                'name' => 'billing_address_state',
                'default' => false,
                'width' => '10%'
            ),
            'billing_address_postalcode' => array(
                'name' => 'billing_address_postalcode',
                'default' => false,
                'width' => '10%'
            ),
            'billing_address_country' => array(
                'name' => 'billing_address_country',
                'default' => false,
                'width' => '10%'
            ),
            'parent_name' => array(
                'name' => 'parent_name',
                'default' => false,
                'width' => '10%'
            ),
            'ownership_type' => array(
                'name' => 'ownership_type',
                'default' => false,
                'width' => '10%'
            ),
            'ticker_symbol' => array(
                'name' => 'ticker_symbol',
                'default' => false,
                'width' => '10%'
            ),
            'annual_revenue' => array(
                'name' => 'annual_revenue',
                'type' => 'currency',
                'default' => false,
                'width' => '10%'
            ),
            'range_annual_revenue' => array(
                'name' => 'range_annual_revenue',
                'label' => 'LBL_ANNUAL_REVENUE',
                'type' => 'range',
                'options' => 'range_annual_revenue_options',
                'default' => false,
                'width' => '10%'
            ),
            'employee_count' => array(
                'name' => 'employee_count',
                'default' => false,
                'width' => '10%'
            ),
            'range_employee_count' => array(
                'name' => 'range_employee_count',
                'label' => 'LBL_EMPLOYEE_COUNT',
                'type' => 'range',
                'options' => 'range_employee_count_options',
                'default' => false,
                'width' => '10%'
            ),
            'year_established' => array(
                'name' => 'year_established',
                'default' => false,
                'width' => '10%'
            ),
            'range_year_established' => array(
                'name' => 'range_year_established',
                'label' => 'LBL_YEAR_ESTABLISHED',
                'type' => 'range',
                'options' => 'range_year_established_options',
                'default' => false,
                'width' => '10%'
            ),
            'ebitda' => array(
                'name' => 'ebitda',
                'type' => 'currency',
                'default' => false,
                'width' => '10%'
            ),
            'current_valuation' => array(
                'name' => 'current_valuation',
                'type' => 'currency',
                'default' => false,
                'width' => '10%'
            ),
            'rating' => array(
                'name' => 'rating',
                'default' => false,
                'width' => '10%'
            ),
            'account_status' => array(
                'name' => 'account_status',
                'default' => false,
                'width' => '10%'
            ),
            'risk_assessment' => array(
                'name' => 'risk_assessment',
                'default' => false,
                'width' => '10%'
            ),
            'compliance_status' => array(
                'name' => 'compliance_status',
                'default' => false,
                'width' => '10%'
            ),
            'acquisition_date' => array(
                'name' => 'acquisition_date',
                'default' => false,
                'width' => '10%'
            ),
            'range_acquisition_date' => array(
                'name' => 'range_acquisition_date',
                'label' => 'LBL_ACQUISITION_DATE',
                'type' => 'daterange',
                'default' => false,
                'width' => '10%'
            ),
            'exit_strategy' => array(
                'name' => 'exit_strategy',
                'default' => false,
                'width' => '10%'
            ),
            'integration_status' => array(
                'name' => 'integration_status',
                'default' => false,
                'width' => '10%'
            ),
            'deal_count' => array(
                'name' => 'deal_count',
                'default' => false,
                'width' => '10%'
            ),
            'range_deal_count' => array(
                'name' => 'range_deal_count',
                'label' => 'LBL_DEAL_COUNT',
                'type' => 'range',
                'options' => 'range_deal_count_options',
                'default' => false,
                'width' => '10%'
            ),
            'total_deal_value' => array(
                'name' => 'total_deal_value',
                'type' => 'currency',
                'default' => false,
                'width' => '10%'
            ),
            'assigned_user_id' => array(
                'name' => 'assigned_user_id',
                'label' => 'LBL_ASSIGNED_TO',
                'type' => 'enum',
                'function' => 'get_user_array',
                'default' => false,
                'width' => '10%'
            ),
            'favorites_only' => array(
                'name' => 'favorites_only',
                'label' => 'LBL_FAVORITES_FILTER',
                'type' => 'bool',
                'default' => false,
                'width' => '10%'
            ),
        ),
    ),
);

// Custom range options for search dropdowns
$app_list_strings['range_annual_revenue_options'] = array(
    '' => '',
    'less_than_1m' => 'Less than $1M',
    '1m_to_10m' => '$1M - $10M',
    '10m_to_50m' => '$10M - $50M',
    '50m_to_100m' => '$50M - $100M',
    '100m_to_500m' => '$100M - $500M',
    '500m_to_1b' => '$500M - $1B',
    'greater_than_1b' => 'Greater than $1B',
);

$app_list_strings['range_employee_count_options'] = array(
    '' => '',
    'less_than_10' => 'Less than 10',
    '10_to_50' => '10 - 50',
    '50_to_100' => '50 - 100',
    '100_to_500' => '100 - 500',
    '500_to_1000' => '500 - 1,000',
    '1000_to_5000' => '1,000 - 5,000',
    'greater_than_5000' => 'Greater than 5,000',
);

$app_list_strings['range_year_established_options'] = array(
    '' => '',
    'before_1950' => 'Before 1950',
    '1950_to_1980' => '1950 - 1980',
    '1980_to_2000' => '1980 - 2000',
    '2000_to_2010' => '2000 - 2010',
    '2010_to_2020' => '2010 - 2020',
    'after_2020' => 'After 2020',
);

$app_list_strings['range_deal_count_options'] = array(
    '' => '',
    'none' => 'No Deals',
    'one' => '1 Deal',
    'two_to_five' => '2 - 5 Deals',
    'six_to_ten' => '6 - 10 Deals',
    'more_than_ten' => 'More than 10 Deals',
);