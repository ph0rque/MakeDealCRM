<?php
/**
 * Language file for mdeal_Contacts module
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$mod_strings = array(
    // Module Labels
    'LBL_MODULE_NAME' => 'Contacts',
    'LBL_MODULE_TITLE' => 'Contacts: Home',
    'LBL_SEARCH_FORM_TITLE' => 'Contact Search',
    'LBL_LIST_FORM_TITLE' => 'Contact List',
    'LBL_NEW_FORM_TITLE' => 'Create Contact',

    // Navigation
    'LNK_NEW_CONTACT' => 'Create Contact',
    'LNK_CONTACT_LIST' => 'View Contacts',
    'LNK_IMPORT_CONTACTS' => 'Import Contacts',

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

    // Person Information (inherited from Person)
    'LBL_SALUTATION' => 'Salutation',
    'LBL_FIRST_NAME' => 'First Name',
    'LBL_LAST_NAME' => 'Last Name',
    'LBL_FULL_NAME' => 'Full Name',
    'LBL_TITLE' => 'Title',
    'LBL_DEPARTMENT' => 'Department',
    'LBL_OFFICE_PHONE' => 'Office Phone',
    'LBL_MOBILE_PHONE' => 'Mobile Phone',
    'LBL_HOME_PHONE' => 'Home Phone',
    'LBL_OTHER_PHONE' => 'Other Phone',
    'LBL_FAX_PHONE' => 'Fax',
    'LBL_EMAIL_ADDRESS' => 'Email Address',
    'LBL_EMAIL_ADDRESS2' => 'Secondary Email',
    'LBL_ASSISTANT' => 'Assistant',
    'LBL_ASSISTANT_PHONE' => 'Assistant Phone',

    // Contact-Specific Fields
    'LBL_CONTACT_TYPE' => 'Contact Type',
    'LBL_CONTACT_SUBTYPE' => 'Contact Subtype',
    'LBL_ACCOUNT_ID' => 'Account ID',
    'LBL_ACCOUNT_NAME' => 'Account Name',
    'LBL_PRIMARY_ACCOUNT' => 'Primary Account',
    'LBL_REPORTS_TO_ID' => 'Reports To ID',
    'LBL_REPORTS_TO' => 'Reports To',
    'LBL_LEAD_SOURCE' => 'Lead Source',
    'LBL_LINKEDIN_URL' => 'LinkedIn Profile',

    // Communication Preferences
    'LBL_PREFERRED_CONTACT_METHOD' => 'Preferred Contact Method',
    'LBL_BEST_TIME_TO_CONTACT' => 'Best Time to Contact',
    'LBL_TIMEZONE' => 'Timezone',
    'LBL_COMMUNICATION_STYLE' => 'Communication Style',
    'LBL_DO_NOT_CALL' => 'Do Not Call',
    'LBL_EMAIL_OPT_OUT' => 'Email Opt Out',
    'LBL_INVALID_EMAIL' => 'Invalid Email',

    // Decision Making & Influence
    'LBL_DECISION_ROLE' => 'Decision Role',
    'LBL_INFLUENCE_LEVEL' => 'Influence Level',
    'LBL_RELATIONSHIP_STRENGTH' => 'Relationship Strength',
    'LBL_TRUST_LEVEL' => 'Trust Level',

    // Interaction Tracking
    'LBL_LAST_INTERACTION_DATE' => 'Last Interaction Date',
    'LBL_INTERACTION_COUNT' => 'Interaction Count',
    'LBL_RESPONSE_RATE' => 'Response Rate',
    'LBL_DAYS_SINCE_INTERACTION' => 'Days Since Last Interaction',

    // Address Information
    'LBL_PRIMARY_ADDRESS_STREET' => 'Primary Address Street',
    'LBL_PRIMARY_ADDRESS_CITY' => 'Primary Address City',
    'LBL_PRIMARY_ADDRESS_STATE' => 'Primary Address State',
    'LBL_PRIMARY_ADDRESS_POSTALCODE' => 'Primary Address Postal Code',
    'LBL_PRIMARY_ADDRESS_COUNTRY' => 'Primary Address Country',
    'LBL_ALT_ADDRESS_STREET' => 'Alternate Address Street',
    'LBL_ALT_ADDRESS_CITY' => 'Alternate Address City',
    'LBL_ALT_ADDRESS_STATE' => 'Alternate Address State',
    'LBL_ALT_ADDRESS_POSTALCODE' => 'Alternate Address Postal Code',
    'LBL_ALT_ADDRESS_COUNTRY' => 'Alternate Address Country',
    'LBL_PRIMARY_ADDRESS' => 'Primary Address',
    'LBL_ALT_ADDRESS' => 'Alternate Address',
    'LBL_ADDRESS_INFORMATION' => 'Address Information',

    // Additional Information
    'LBL_BIRTHDATE' => 'Birth Date',
    'LBL_PICTURE' => 'Picture',
    'LBL_CONFIDENTIALITY_AGREEMENT' => 'Confidentiality Agreement',
    'LBL_BACKGROUND_CHECK_COMPLETED' => 'Background Check Completed',
    'LBL_BACKGROUND_CHECK_DATE' => 'Background Check Date',
    'LBL_NOTES_PRIVATE' => 'Private Notes',

    // Relationships
    'LBL_DEALS' => 'Deals',
    'LBL_ACCOUNTS' => 'Accounts',
    'LBL_DIRECT_REPORTS' => 'Direct Reports',
    'LBL_ORGANIZATION_CHART' => 'Organization Chart',

    // Subpanels
    'LBL_ACTIVITIES_SUBPANEL_TITLE' => 'Activities',
    'LBL_HISTORY_SUBPANEL_TITLE' => 'History',
    'LBL_NOTES_SUBPANEL_TITLE' => 'Notes',
    'LBL_DOCUMENTS_SUBPANEL_TITLE' => 'Documents',
    'LBL_DEALS_SUBPANEL_TITLE' => 'Related Deals',
    'LBL_ACCOUNTS_SUBPANEL_TITLE' => 'Related Accounts',
    'LBL_REPORTS_SUBPANEL_TITLE' => 'Direct Reports',

    // Actions
    'LBL_ADD_TO_DEAL' => 'Add to Deal',
    'LBL_ADD_TO_ACCOUNT' => 'Add to Account',
    'LBL_VIEW_ORGANIZATION_CHART' => 'View Org Chart',
    'LBL_CALCULATE_INFLUENCE' => 'Calculate Influence Score',
    'LBL_SCHEDULE_FOLLOW_UP' => 'Schedule Follow-up',

    // Messages
    'MSG_DUPLICATE_WARN' => 'This contact might be a duplicate. Contacts with similar names are listed below.',
    'MSG_SHOW_DUPLICATES' => 'Show possible duplicates',
    'MSG_HIERARCHY_CIRCULAR' => 'Circular reference detected in reporting hierarchy.',
    'MSG_INFLUENCE_CALCULATED' => 'Influence score calculated successfully.',
    'MSG_FOLLOW_UP_NEEDED' => 'This contact needs follow-up based on interaction history.',

    // Errors
    'ERR_DELETE_RECORD' => 'A record number must be specified to delete the contact.',
    'ERR_MISSING_REQUIRED_FIELDS' => 'The following required fields are missing:',
    'ERR_INVALID_HIERARCHY' => 'Invalid hierarchy relationship detected.',
    'ERR_TRUST_LEVEL_RANGE' => 'Trust level must be between 1 and 10.',

    // List View Headers
    'LBL_LIST_FULL_NAME' => 'Name',
    'LBL_LIST_ACCOUNT_NAME' => 'Account',
    'LBL_LIST_TITLE' => 'Title',
    'LBL_LIST_CONTACT_TYPE' => 'Type',
    'LBL_LIST_EMAIL' => 'Email',
    'LBL_LIST_PHONE' => 'Phone',
    'LBL_LIST_DECISION_ROLE' => 'Decision Role',
    'LBL_LIST_INFLUENCE_LEVEL' => 'Influence',
    'LBL_LIST_RELATIONSHIP_STRENGTH' => 'Relationship',
    'LBL_LIST_LAST_INTERACTION' => 'Last Interaction',
    'LBL_LIST_ASSIGNED_USER' => 'Assigned to',

    // Search Form
    'LBL_SEARCH_BUTTON' => 'Search',
    'LBL_CLEAR_BUTTON' => 'Clear',
    'LBL_BASIC_SEARCH' => 'Basic Search',
    'LBL_ADVANCED_SEARCH' => 'Advanced Search',
    'LBL_CURRENT_USER_FILTER' => 'My Items',
    'LBL_FAVORITES_FILTER' => 'My Favorites',

    // Panel Labels
    'LBL_PANEL_1' => 'Contact Information',
    'LBL_PANEL_PERSONAL' => 'Personal Information',
    'LBL_PANEL_PROFESSIONAL' => 'Professional Information',
    'LBL_PANEL_CONTACT_DETAILS' => 'Contact Details',
    'LBL_PANEL_COMMUNICATION' => 'Communication Preferences',
    'LBL_PANEL_DECISION_MAKING' => 'Decision Making & Influence',
    'LBL_PANEL_RELATIONSHIP' => 'Relationship Management',
    'LBL_PANEL_ADDRESS' => 'Address Information',
    'LBL_PANEL_OTHER' => 'Other Information',

    // Role in Deal Labels
    'LBL_CONTACT_ROLE' => 'Role in Deal',
    'LBL_PRIMARY_CONTACT' => 'Primary Contact',
    'LBL_CONTACT_ROLE_SELLER' => 'Seller',
    'LBL_CONTACT_ROLE_BROKER' => 'Broker',
    'LBL_CONTACT_ROLE_ATTORNEY' => 'Attorney',
    'LBL_CONTACT_ROLE_ACCOUNTANT' => 'Accountant',
    'LBL_CONTACT_ROLE_LENDER' => 'Lender',
    'LBL_CONTACT_ROLE_EMPLOYEE' => 'Key Employee',
    'LBL_CONTACT_ROLE_ADVISOR' => 'Advisor',

    // Help Text
    'LBL_HELP_DECISION_ROLE' => 'Role of this contact in the decision-making process',
    'LBL_HELP_INFLUENCE_LEVEL' => 'Level of influence this contact has within their organization',
    'LBL_HELP_RELATIONSHIP_STRENGTH' => 'Current strength of the business relationship',
    'LBL_HELP_TRUST_LEVEL' => 'Trust level on a scale of 1-10 (10 being highest trust)',
    'LBL_HELP_REPORTS_TO' => 'Select the person this contact reports to in the organizational hierarchy',
    'LBL_HELP_COMMUNICATION_STYLE' => 'Notes about how this person prefers to communicate and their communication style',
    'LBL_HELP_INTERACTION_COUNT' => 'Automatically tracked count of all interactions with this contact',
    'LBL_HELP_RESPONSE_RATE' => 'Automatically calculated percentage of emails that receive responses',
);

// Dropdown option labels
$app_list_strings['contact_type_dom'] = array(
    '' => '',
    'seller' => 'Seller',
    'broker' => 'Broker',
    'attorney' => 'Attorney',
    'accountant' => 'Accountant',
    'lender' => 'Lender',
    'advisor' => 'Advisor',
    'employee' => 'Employee',
    'vendor' => 'Vendor',
    'customer' => 'Customer',
    'investor' => 'Investor',
    'other' => 'Other',
);

$app_list_strings['contact_method_dom'] = array(
    '' => '',
    'email' => 'Email',
    'phone_mobile' => 'Mobile Phone',
    'phone_work' => 'Work Phone',
    'text_message' => 'Text Message',
    'in_person' => 'In Person',
);

$app_list_strings['decision_role_dom'] = array(
    '' => '',
    'decision_maker' => 'Decision Maker',
    'influencer' => 'Influencer',
    'gatekeeper' => 'Gatekeeper',
    'champion' => 'Champion',
    'technical_evaluator' => 'Technical Evaluator',
    'financial_approver' => 'Financial Approver',
    'end_user' => 'End User',
);

$app_list_strings['influence_level_dom'] = array(
    '' => '',
    'high' => 'High',
    'medium' => 'Medium',
    'low' => 'Low',
);

$app_list_strings['relationship_strength_dom'] = array(
    '' => '',
    'strong' => 'Strong',
    'good' => 'Good',
    'developing' => 'Developing',
    'weak' => 'Weak',
    'damaged' => 'Damaged',
);

$app_list_strings['moduleList']['mdeal_Contacts'] = 'Contacts';
$app_list_strings['moduleListSingular']['mdeal_Contacts'] = 'Contact';