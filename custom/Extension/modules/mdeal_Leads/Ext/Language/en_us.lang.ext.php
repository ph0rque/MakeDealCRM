<?php
/**
 * Language file for mdeal_Leads module
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$mod_strings = array(
    // Module Labels
    'LBL_MODULE_NAME' => 'Leads',
    'LBL_MODULE_TITLE' => 'Leads: Home',
    'LBL_SEARCH_FORM_TITLE' => 'Lead Search',
    'LBL_LIST_FORM_TITLE' => 'Lead List',
    'LBL_NEW_FORM_TITLE' => 'Create Lead',

    // Navigation
    'LNK_NEW_LEAD' => 'Create Lead',
    'LNK_LEAD_LIST' => 'View Leads',
    'LNK_IMPORT_LEADS' => 'Import Leads',

    // Basic Field Labels
    'LBL_NAME' => 'Name',
    'LBL_ID' => 'ID',
    'LBL_DATE_ENTERED' => 'Date Created',
    'LBL_DATE_MODIFIED' => 'Date Modified',
    'LBL_MODIFIED_BY' => 'Modified By',
    'LBL_CREATED_BY' => 'Created By',
    'LBL_DESCRIPTION' => 'Description',
    'LBL_DELETED' => 'Deleted',
    'LBL_ASSIGNED_TO' => 'Assigned to',
    'LBL_ASSIGNED_USER_ID' => 'Assigned User ID',

    // Contact Information
    'LBL_FIRST_NAME' => 'First Name',
    'LBL_LAST_NAME' => 'Last Name',
    'LBL_TITLE' => 'Title',
    'LBL_OFFICE_PHONE' => 'Office Phone',
    'LBL_MOBILE_PHONE' => 'Mobile Phone',
    'LBL_EMAIL_ADDRESS' => 'Email Address',
    'LBL_WEBSITE' => 'Website',
    'LBL_CONTACT_INFORMATION' => 'Contact Information',

    // Company Information
    'LBL_COMPANY_NAME' => 'Company Name',
    'LBL_INDUSTRY' => 'Industry',
    'LBL_ANNUAL_REVENUE' => 'Annual Revenue',
    'LBL_EMPLOYEE_COUNT' => 'Employee Count',
    'LBL_YEARS_IN_BUSINESS' => 'Years in Business',
    'LBL_COMPANY_INFORMATION' => 'Company Information',

    // Lead Qualification
    'LBL_LEAD_SOURCE' => 'Lead Source',
    'LBL_LEAD_SOURCE_DESCRIPTION' => 'Lead Source Description',
    'LBL_STATUS' => 'Status',
    'LBL_STATUS_DESCRIPTION' => 'Status Description',
    'LBL_RATING' => 'Rating',
    'LBL_QUALIFICATION' => 'Qualification',

    // Pipeline Integration
    'LBL_PIPELINE_STAGE' => 'Pipeline Stage',
    'LBL_DAYS_IN_STAGE' => 'Days in Stage',
    'LBL_DATE_ENTERED_STAGE' => 'Date Entered Stage',
    'LBL_QUALIFICATION_SCORE' => 'Qualification Score',
    'LBL_CONVERTED_DEAL' => 'Converted Deal',
    'LBL_PIPELINE_INFORMATION' => 'Pipeline Information',

    // Address Information
    'LBL_PRIMARY_ADDRESS_STREET' => 'Primary Address Street',
    'LBL_PRIMARY_ADDRESS_CITY' => 'Primary Address City',
    'LBL_PRIMARY_ADDRESS_STATE' => 'Primary Address State',
    'LBL_PRIMARY_ADDRESS_POSTALCODE' => 'Primary Address Postal Code',
    'LBL_PRIMARY_ADDRESS_COUNTRY' => 'Primary Address Country',
    'LBL_PRIMARY_ADDRESS' => 'Primary Address',
    'LBL_ADDRESS_INFORMATION' => 'Address Information',

    // Additional Tracking
    'LBL_DO_NOT_CALL' => 'Do Not Call',
    'LBL_EMAIL_OPT_OUT' => 'Email Opt Out',
    'LBL_INVALID_EMAIL' => 'Invalid Email',
    'LBL_LAST_ACTIVITY_DATE' => 'Last Activity Date',
    'LBL_NEXT_FOLLOW_UP_DATE' => 'Next Follow-up Date',
    'LBL_ADDITIONAL_INFORMATION' => 'Additional Information',

    // Subpanels
    'LBL_ACTIVITIES_SUBPANEL_TITLE' => 'Activities',
    'LBL_HISTORY_SUBPANEL_TITLE' => 'History',
    'LBL_NOTES_SUBPANEL_TITLE' => 'Notes',
    'LBL_DOCUMENTS_SUBPANEL_TITLE' => 'Documents',

    // Actions
    'LBL_CONVERT_LEAD' => 'Convert Lead',
    'LBL_CONVERT_TO_DEAL' => 'Convert to Deal',
    'LBL_VIEW_CONVERTED_DEAL' => 'View Converted Deal',
    'LBL_LEAD_CONVERTED' => 'Lead Converted',

    // Messages
    'MSG_DUPLICATE_WARN' => 'This lead might be a duplicate. Leads with similar names are listed below.',
    'MSG_SHOW_DUPLICATES' => 'Show possible duplicates',
    'MSG_LEAD_CONVERTED_SUCCESS' => 'Lead successfully converted to deal',
    'MSG_CONVERSION_FAILED' => 'Lead conversion failed',
    'MSG_QUALIFICATION_INCOMPLETE' => 'Lead qualification is incomplete. Please update required fields.',

    // Errors
    'ERR_DELETE_RECORD' => 'A record number must be specified to delete the lead.',
    'ERR_CONVERTED_LEAD' => 'Cannot modify a converted lead.',
    'ERR_MISSING_REQUIRED_FIELDS' => 'The following required fields are missing:',

    // List View Headers
    'LBL_LIST_COMPANY_NAME' => 'Company',
    'LBL_LIST_CONTACT_NAME' => 'Contact Name',
    'LBL_LIST_STATUS' => 'Status',
    'LBL_LIST_RATING' => 'Rating',
    'LBL_LIST_LEAD_SOURCE' => 'Source',
    'LBL_LIST_ASSIGNED_USER' => 'Assigned to',
    'LBL_LIST_LAST_ACTIVITY' => 'Last Activity',
    'LBL_LIST_NEXT_FOLLOW_UP' => 'Next Follow-up',
    'LBL_LIST_PIPELINE_STAGE' => 'Stage',
    'LBL_LIST_QUALIFICATION_SCORE' => 'Score',

    // Search Form
    'LBL_SEARCH_BUTTON' => 'Search',
    'LBL_CLEAR_BUTTON' => 'Clear',
    'LBL_BASIC_SEARCH' => 'Basic Search',
    'LBL_ADVANCED_SEARCH' => 'Advanced Search',
    'LBL_CURRENT_USER_FILTER' => 'My Items',
    'LBL_FAVORITES_FILTER' => 'My Favorites',

    // Panel Labels
    'LBL_PANEL_1' => 'Lead Information',
    'LBL_PANEL_CONTACT' => 'Contact Details',
    'LBL_PANEL_COMPANY' => 'Company Details',
    'LBL_PANEL_QUALIFICATION' => 'Qualification',
    'LBL_PANEL_PIPELINE' => 'Pipeline',
    'LBL_PANEL_ADDRESS' => 'Address',
    'LBL_PANEL_OTHER' => 'Other Information',

    // Help Text
    'LBL_HELP_QUALIFICATION_SCORE' => 'Automatically calculated score based on industry fit, revenue size, engagement level, and lead source quality (0-100 scale)',
    'LBL_HELP_PIPELINE_STAGE' => 'Current position in the lead qualification pipeline',
    'LBL_HELP_CONVERSION' => 'Leads can be converted to deals when they reach the "Ready to Convert" stage and have a qualification score above 70',
);

// Dropdown option labels
$app_list_strings['lead_source_dom'] = array(
    '' => '',
    'broker_network' => 'Broker Network',
    'direct_outreach' => 'Direct Outreach',
    'inbound_inquiry' => 'Inbound Inquiry',
    'referral' => 'Referral',
    'conference_event' => 'Conference/Event',
    'online_marketplace' => 'Online Marketplace',
    'other' => 'Other',
);

$app_list_strings['lead_status_dom'] = array(
    '' => '',
    'new' => 'New',
    'contacted' => 'Contacted',
    'qualified' => 'Qualified',
    'unqualified' => 'Unqualified',
    'converted' => 'Converted',
    'dead' => 'Dead',
);

$app_list_strings['lead_rating_dom'] = array(
    '' => '',
    'hot' => 'Hot',
    'warm' => 'Warm',
    'cold' => 'Cold',
);

$app_list_strings['lead_pipeline_stage_dom'] = array(
    '' => '',
    'initial_contact' => 'Initial Contact',
    'qualification' => 'Qualification',
    'initial_interest' => 'Initial Interest',
    'ready_to_convert' => 'Ready to Convert',
);

$app_list_strings['moduleList']['mdeal_Leads'] = 'Leads';
$app_list_strings['moduleListSingular']['mdeal_Leads'] = 'Lead';