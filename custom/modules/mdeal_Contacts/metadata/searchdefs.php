<?php
/**
 * Search definitions for mdeal_Contacts module
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$searchdefs['mdeal_Contacts'] = array(
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
            'first_name' => array(
                'name' => 'first_name',
                'default' => true,
                'width' => '10%',
            ),
            'last_name' => array(
                'name' => 'last_name',
                'default' => true,
                'width' => '10%',
            ),
            'account_name' => array(
                'name' => 'account_name',
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
            'first_name' => array(
                'name' => 'first_name',
                'default' => true,
            ),
            'last_name' => array(
                'name' => 'last_name',
                'default' => true,
            ),
            'account_name' => array(
                'name' => 'account_name',
                'default' => true,
            ),
            'title' => array(
                'name' => 'title',
                'default' => true,
            ),
            'department' => array(
                'name' => 'department',
                'default' => true,
            ),
            'contact_type' => array(
                'name' => 'contact_type',
                'default' => true,
            ),
            'decision_role' => array(
                'name' => 'decision_role',
                'default' => true,
            ),
            'influence_level' => array(
                'name' => 'influence_level',
                'default' => true,
            ),
            'relationship_strength' => array(
                'name' => 'relationship_strength',
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
            'reports_to_name' => array(
                'name' => 'reports_to_name',
                'default' => true,
            ),
            'lead_source' => array(
                'name' => 'lead_source',
                'default' => true,
            ),
            'preferred_contact_method' => array(
                'name' => 'preferred_contact_method',
                'default' => true,
            ),
            'trust_level' => array(
                'name' => 'trust_level',
                'default' => true,
            ),
            'confidentiality_agreement' => array(
                'name' => 'confidentiality_agreement',
                'default' => true,
            ),
            'background_check_completed' => array(
                'name' => 'background_check_completed',
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
            'last_interaction_date' => array(
                'name' => 'last_interaction_date',
                'default' => true,
            ),
            'interaction_count' => array(
                'name' => 'interaction_count',
                'default' => true,
            ),
            'response_rate' => array(
                'name' => 'response_rate',
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
            array(
                'name' => 'favorites_only',
                'label' => 'LBL_FAVORITES_FILTER',
                'type' => 'bool',
                'default' => false,
            ),
        ),
    ),
);