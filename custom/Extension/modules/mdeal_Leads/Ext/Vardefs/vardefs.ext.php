<?php
/**
 * Variable definitions for mdeal_Leads module
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$dictionary['mdeal_Leads'] = array(
    'table' => 'mdeal_leads',
    'audited' => true,
    'duplicate_merge' => true,
    'fields' => array(
        // Basic fields
        'name' => array(
            'name' => 'name',
            'vname' => 'LBL_NAME',
            'type' => 'name',
            'link' => true,
            'dbType' => 'varchar',
            'len' => 255,
            'comment' => 'The Name of the Lead',
            'unified_search' => true,
            'merge_filter' => 'disabled',
            'full_text_search' => array(
                'boost' => 3,
                'enabled' => true
            ),
            'required' => false,
            'importable' => 'false',
            'readonly' => true,
        ),

        // Contact Information Fields
        'first_name' => array(
            'name' => 'first_name',
            'vname' => 'LBL_FIRST_NAME',
            'type' => 'varchar',
            'len' => 100,
            'comment' => 'First name of the lead contact',
            'required' => false,
            'audited' => true,
        ),
        'last_name' => array(
            'name' => 'last_name',
            'vname' => 'LBL_LAST_NAME',
            'type' => 'varchar',
            'len' => 100,
            'comment' => 'Last name of the lead contact',
            'required' => true,
            'audited' => true,
        ),
        'title' => array(
            'name' => 'title',
            'vname' => 'LBL_TITLE',
            'type' => 'varchar',
            'len' => 100,
            'comment' => 'Contact title/job position',
        ),
        'phone_work' => array(
            'name' => 'phone_work',
            'vname' => 'LBL_OFFICE_PHONE',
            'type' => 'phone',
            'dbType' => 'varchar',
            'len' => 100,
            'comment' => 'Work phone number',
        ),
        'phone_mobile' => array(
            'name' => 'phone_mobile',
            'vname' => 'LBL_MOBILE_PHONE',
            'type' => 'phone',
            'dbType' => 'varchar',
            'len' => 100,
            'comment' => 'Mobile phone number',
        ),
        'email_address' => array(
            'name' => 'email_address',
            'vname' => 'LBL_EMAIL_ADDRESS',
            'type' => 'varchar',
            'len' => 100,
            'comment' => 'Primary email address',
        ),
        'website' => array(
            'name' => 'website',
            'vname' => 'LBL_WEBSITE',
            'type' => 'url',
            'dbType' => 'varchar',
            'len' => 255,
            'comment' => 'Company website',
        ),

        // Company Information
        'company_name' => array(
            'name' => 'company_name',
            'vname' => 'LBL_COMPANY_NAME',
            'type' => 'varchar',
            'len' => 255,
            'comment' => 'Business/company name',
            'required' => true,
            'audited' => true,
            'unified_search' => true,
        ),
        'industry' => array(
            'name' => 'industry',
            'vname' => 'LBL_INDUSTRY',
            'type' => 'enum',
            'options' => 'industry_dom',
            'len' => 100,
            'comment' => 'Industry classification',
            'audited' => true,
        ),
        'annual_revenue' => array(
            'name' => 'annual_revenue',
            'vname' => 'LBL_ANNUAL_REVENUE',
            'type' => 'decimal',
            'precision' => 26,
            'scale' => 6,
            'comment' => 'Estimated annual revenue',
            'audited' => true,
        ),
        'employee_count' => array(
            'name' => 'employee_count',
            'vname' => 'LBL_EMPLOYEE_COUNT',
            'type' => 'int',
            'comment' => 'Number of employees',
        ),
        'years_in_business' => array(
            'name' => 'years_in_business',
            'vname' => 'LBL_YEARS_IN_BUSINESS',
            'type' => 'int',
            'comment' => 'Years company has been in business',
        ),

        // Lead Qualification
        'lead_source' => array(
            'name' => 'lead_source',
            'vname' => 'LBL_LEAD_SOURCE',
            'type' => 'enum',
            'options' => 'lead_source_dom',
            'len' => 100,
            'comment' => 'Source of the lead',
            'audited' => true,
        ),
        'lead_source_description' => array(
            'name' => 'lead_source_description',
            'vname' => 'LBL_LEAD_SOURCE_DESCRIPTION',
            'type' => 'text',
            'comment' => 'Additional details about lead source',
        ),
        'status' => array(
            'name' => 'status',
            'vname' => 'LBL_STATUS',
            'type' => 'enum',
            'options' => 'lead_status_dom',
            'len' => 100,
            'default' => 'new',
            'comment' => 'Lead status',
            'audited' => true,
            'required' => true,
        ),
        'status_description' => array(
            'name' => 'status_description',
            'vname' => 'LBL_STATUS_DESCRIPTION',
            'type' => 'text',
            'comment' => 'Additional status details or reason',
        ),
        'rating' => array(
            'name' => 'rating',
            'vname' => 'LBL_RATING',
            'type' => 'enum',
            'options' => 'lead_rating_dom',
            'len' => 20,
            'comment' => 'Lead quality rating',
            'audited' => true,
        ),

        // Pipeline Integration
        'pipeline_stage' => array(
            'name' => 'pipeline_stage',
            'vname' => 'LBL_PIPELINE_STAGE',
            'type' => 'enum',
            'options' => 'lead_pipeline_stage_dom',
            'len' => 100,
            'default' => 'initial_contact',
            'comment' => 'Current pipeline stage',
            'audited' => true,
        ),
        'days_in_stage' => array(
            'name' => 'days_in_stage',
            'vname' => 'LBL_DAYS_IN_STAGE',
            'type' => 'int',
            'default' => 0,
            'comment' => 'Number of days in current stage',
            'readonly' => true,
        ),
        'date_entered_stage' => array(
            'name' => 'date_entered_stage',
            'vname' => 'LBL_DATE_ENTERED_STAGE',
            'type' => 'datetime',
            'comment' => 'Date when lead entered current stage',
            'readonly' => true,
        ),
        'qualification_score' => array(
            'name' => 'qualification_score',
            'vname' => 'LBL_QUALIFICATION_SCORE',
            'type' => 'int',
            'comment' => 'Lead qualification score (0-100)',
            'readonly' => true,
        ),
        'converted_deal_id' => array(
            'name' => 'converted_deal_id',
            'vname' => 'LBL_CONVERTED_DEAL',
            'type' => 'relate',
            'module' => 'mdeal_Deals',
            'id_name' => 'converted_deal_id',
            'rname' => 'name',
            'table' => 'mdeal_deals',
            'comment' => 'Deal created from this lead',
            'readonly' => true,
        ),

        // Address Information
        'primary_address_street' => array(
            'name' => 'primary_address_street',
            'vname' => 'LBL_PRIMARY_ADDRESS_STREET',
            'type' => 'varchar',
            'len' => 150,
            'comment' => 'Primary street address',
        ),
        'primary_address_city' => array(
            'name' => 'primary_address_city',
            'vname' => 'LBL_PRIMARY_ADDRESS_CITY',
            'type' => 'varchar',
            'len' => 100,
            'comment' => 'Primary city',
        ),
        'primary_address_state' => array(
            'name' => 'primary_address_state',
            'vname' => 'LBL_PRIMARY_ADDRESS_STATE',
            'type' => 'varchar',
            'len' => 100,
            'comment' => 'Primary state/province',
        ),
        'primary_address_postalcode' => array(
            'name' => 'primary_address_postalcode',
            'vname' => 'LBL_PRIMARY_ADDRESS_POSTALCODE',
            'type' => 'varchar',
            'len' => 20,
            'comment' => 'Primary postal/zip code',
        ),
        'primary_address_country' => array(
            'name' => 'primary_address_country',
            'vname' => 'LBL_PRIMARY_ADDRESS_COUNTRY',
            'type' => 'varchar',
            'len' => 100,
            'comment' => 'Primary country',
        ),

        // Additional Tracking
        'do_not_call' => array(
            'name' => 'do_not_call',
            'vname' => 'LBL_DO_NOT_CALL',
            'type' => 'bool',
            'default' => '0',
            'comment' => 'Do not call flag',
        ),
        'email_opt_out' => array(
            'name' => 'email_opt_out',
            'vname' => 'LBL_EMAIL_OPT_OUT',
            'type' => 'bool',
            'default' => '0',
            'comment' => 'Email opt-out flag',
        ),
        'invalid_email' => array(
            'name' => 'invalid_email',
            'vname' => 'LBL_INVALID_EMAIL',
            'type' => 'bool',
            'default' => '0',
            'comment' => 'Email validation flag',
        ),
        'last_activity_date' => array(
            'name' => 'last_activity_date',
            'vname' => 'LBL_LAST_ACTIVITY_DATE',
            'type' => 'datetime',
            'comment' => 'Date of last activity',
            'readonly' => true,
        ),
        'next_follow_up_date' => array(
            'name' => 'next_follow_up_date',
            'vname' => 'LBL_NEXT_FOLLOW_UP_DATE',
            'type' => 'date',
            'comment' => 'Next scheduled follow-up date',
        ),
    ),

    'relationships' => array(
        'mdeal_leads_assigned_user' => array(
            'lhs_module' => 'Users',
            'lhs_table' => 'users',
            'lhs_key' => 'id',
            'rhs_module' => 'mdeal_Leads',
            'rhs_table' => 'mdeal_leads',
            'rhs_key' => 'assigned_user_id',
            'relationship_type' => 'one-to-many'
        ),
        'mdeal_leads_modified_user' => array(
            'lhs_module' => 'Users',
            'lhs_table' => 'users',
            'lhs_key' => 'id',
            'rhs_module' => 'mdeal_Leads',
            'rhs_table' => 'mdeal_leads',
            'rhs_key' => 'modified_user_id',
            'relationship_type' => 'one-to-many'
        ),
        'mdeal_leads_created_by' => array(
            'lhs_module' => 'Users',
            'lhs_table' => 'users',
            'lhs_key' => 'id',
            'rhs_module' => 'mdeal_Leads',
            'rhs_table' => 'mdeal_leads',
            'rhs_key' => 'created_by',
            'relationship_type' => 'one-to-many'
        ),
        'mdeal_leads_activities' => array(
            'lhs_module' => 'mdeal_Leads',
            'lhs_table' => 'mdeal_leads',
            'lhs_key' => 'id',
            'rhs_module' => 'Activities',
            'rhs_table' => 'activities',
            'rhs_key' => 'parent_id',
            'relationship_type' => 'one-to-many',
            'relationship_role_column' => 'parent_type',
            'relationship_role_column_value' => 'mdeal_Leads'
        ),
        'mdeal_leads_notes' => array(
            'lhs_module' => 'mdeal_Leads',
            'lhs_table' => 'mdeal_leads',
            'lhs_key' => 'id',
            'rhs_module' => 'Notes',
            'rhs_table' => 'notes',
            'rhs_key' => 'parent_id',
            'relationship_type' => 'one-to-many',
            'relationship_role_column' => 'parent_type',
            'relationship_role_column_value' => 'mdeal_Leads'
        ),
        'mdeal_leads_tasks' => array(
            'lhs_module' => 'mdeal_Leads',
            'lhs_table' => 'mdeal_leads',
            'lhs_key' => 'id',
            'rhs_module' => 'Tasks',
            'rhs_table' => 'tasks',
            'rhs_key' => 'parent_id',
            'relationship_type' => 'one-to-many',
            'relationship_role_column' => 'parent_type',
            'relationship_role_column_value' => 'mdeal_Leads'
        ),
        'mdeal_leads_calls' => array(
            'lhs_module' => 'mdeal_Leads',
            'lhs_table' => 'mdeal_leads',
            'lhs_key' => 'id',
            'rhs_module' => 'Calls',
            'rhs_table' => 'calls',
            'rhs_key' => 'parent_id',
            'relationship_type' => 'one-to-many',
            'relationship_role_column' => 'parent_type',
            'relationship_role_column_value' => 'mdeal_Leads'
        ),
        'mdeal_leads_meetings' => array(
            'lhs_module' => 'mdeal_Leads',
            'lhs_table' => 'mdeal_leads',
            'lhs_key' => 'id',
            'rhs_module' => 'Meetings',
            'rhs_table' => 'meetings',
            'rhs_key' => 'parent_id',
            'relationship_type' => 'one-to-many',
            'relationship_role_column' => 'parent_type',
            'relationship_role_column_value' => 'mdeal_Leads'
        ),
        'mdeal_leads_emails' => array(
            'lhs_module' => 'mdeal_Leads',
            'lhs_table' => 'mdeal_leads',
            'lhs_key' => 'id',
            'rhs_module' => 'Emails',
            'rhs_table' => 'emails',
            'rhs_key' => 'parent_id',
            'relationship_type' => 'one-to-many',
            'relationship_role_column' => 'parent_type',
            'relationship_role_column_value' => 'mdeal_Leads'
        ),
    ),

    'indices' => array(
        array(
            'name' => 'idx_mdeal_leads_company_name',
            'type' => 'index',
            'fields' => array('company_name', 'deleted')
        ),
        array(
            'name' => 'idx_mdeal_leads_status',
            'type' => 'index',
            'fields' => array('status', 'deleted')
        ),
        array(
            'name' => 'idx_mdeal_leads_assigned',
            'type' => 'index',
            'fields' => array('assigned_user_id', 'deleted')
        ),
        array(
            'name' => 'idx_mdeal_leads_source',
            'type' => 'index',
            'fields' => array('lead_source', 'deleted')
        ),
        array(
            'name' => 'idx_mdeal_leads_rating',
            'type' => 'index',
            'fields' => array('rating', 'deleted')
        ),
        array(
            'name' => 'idx_mdeal_leads_pipeline',
            'type' => 'index',
            'fields' => array('pipeline_stage', 'deleted')
        ),
        array(
            'name' => 'idx_mdeal_leads_email',
            'type' => 'index',
            'fields' => array('email_address')
        ),
        array(
            'name' => 'idx_mdeal_leads_converted',
            'type' => 'index',
            'fields' => array('status', 'converted_deal_id')
        ),
        array(
            'name' => 'idx_mdeal_leads_last_name',
            'type' => 'index',
            'fields' => array('last_name', 'first_name', 'deleted')
        ),
    ),
);

VardefManager::createVardef('mdeal_Leads', 'mdeal_Leads', array('basic', 'assignable', 'security_groups'));