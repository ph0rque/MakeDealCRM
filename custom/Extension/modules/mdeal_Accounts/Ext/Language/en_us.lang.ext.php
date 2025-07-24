<?php
/**
 * Language file for mdeal_Accounts module - M&A specific terminology
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$mod_strings = array(
    // Module labels
    'LBL_MODULE_NAME' => 'Accounts',
    'LBL_MODULE_TITLE' => 'M&A Accounts',
    'LBL_SEARCH_FORM_TITLE' => 'Account Search',
    'LBL_LIST_FORM_TITLE' => 'Account List',
    'LBL_NEW_FORM_TITLE' => 'Create Account',
    'LBL_ACCOUNT' => 'Account:',
    'LBL_ACCOUNTS' => 'Accounts',
    'LBL_LIST_ACCOUNT_NAME' => 'Account Name',
    'LBL_LIST_WEBSITE' => 'Website',
    'LBL_LIST_PHONE' => 'Phone',
    'LBL_LIST_CONTACT_NAME' => 'Contact Name',
    'LBL_LIST_EMAIL_ADDRESS' => 'Email',

    // Basic Information
    'LBL_NAME' => 'Company Name',
    'LBL_WEBSITE' => 'Website',
    'LBL_PHONE' => 'Main Phone',
    'LBL_PHONE_OFFICE' => 'Office Phone',
    'LBL_PHONE_ALTERNATE' => 'Alternate Phone',
    'LBL_PHONE_FAX' => 'Fax',
    'LBL_EMAIL' => 'Email',
    'LBL_DESCRIPTION' => 'Description',
    'LBL_ASSIGNED_TO' => 'Deal Manager',
    'LBL_ASSIGNED_USER_ID' => 'Deal Manager ID',

    // Account Classification
    'LBL_ACCOUNT_TYPE' => 'Account Type',
    'LBL_INDUSTRY' => 'Industry',
    'LBL_SUB_INDUSTRY' => 'Sub-Industry',
    'LBL_NAICS_CODE' => 'NAICS Code',
    'LBL_SIC_CODE' => 'SIC Code',

    // Company Information  
    'LBL_TICKER_SYMBOL' => 'Ticker Symbol',
    'LBL_OWNERSHIP_TYPE' => 'Ownership Structure',
    'LBL_YEAR_ESTABLISHED' => 'Year Established',
    'LBL_DBA_NAME' => 'DBA Name',
    'LBL_TAX_ID' => 'Tax ID / EIN',
    'LBL_DUNS_NUMBER' => 'DUNS Number',

    // Financial Information
    'LBL_ANNUAL_REVENUE' => 'Annual Revenue',
    'LBL_REVENUE_CURRENCY' => 'Revenue Currency',
    'LBL_EBITDA' => 'EBITDA',
    'LBL_EMPLOYEE_COUNT' => 'Employee Count',
    'LBL_FACILITY_COUNT' => 'Facilities Count',

    // Hierarchical Structure
    'LBL_PARENT_ACCOUNT_ID' => 'Parent Company ID',
    'LBL_PARENT_ACCOUNT' => 'Parent Company',
    'LBL_IS_PARENT' => 'Is Parent Company',
    'LBL_HIERARCHY_LEVEL' => 'Hierarchy Level',

    // Deal-related fields
    'LBL_RATING' => 'Deal Rating',
    'LBL_ACCOUNT_STATUS' => 'Account Status',
    'LBL_DEAL_COUNT' => 'Active Deals',
    'LBL_TOTAL_DEAL_VALUE' => 'Total Deal Value',
    'LBL_LAST_DEAL_DATE' => 'Last Deal Activity',

    // Compliance & Risk
    'LBL_CREDIT_RATING' => 'Credit Rating',
    'LBL_CREDIT_LIMIT' => 'Credit Limit',
    'LBL_PAYMENT_TERMS' => 'Payment Terms',
    'LBL_RISK_ASSESSMENT' => 'Risk Assessment',
    'LBL_COMPLIANCE_STATUS' => 'Compliance Status',
    'LBL_INSURANCE_COVERAGE' => 'Insurance Coverage',
    'LBL_INSURANCE_EXPIRY' => 'Insurance Expiry',

    // Portfolio-specific fields
    'LBL_ACQUISITION_DATE' => 'Acquisition Date',
    'LBL_ACQUISITION_PRICE' => 'Acquisition Price',
    'LBL_CURRENT_VALUATION' => 'Current Valuation',
    'LBL_EXIT_STRATEGY' => 'Exit Strategy',
    'LBL_PLANNED_EXIT_DATE' => 'Planned Exit Date',
    'LBL_INTEGRATION_STATUS' => 'Integration Status',

    // Address Information
    'LBL_BILLING_ADDRESS_STREET' => 'Billing Street',
    'LBL_BILLING_ADDRESS_CITY' => 'Billing City',
    'LBL_BILLING_ADDRESS_STATE' => 'Billing State',
    'LBL_BILLING_ADDRESS_POSTALCODE' => 'Billing Postal Code',
    'LBL_BILLING_ADDRESS_COUNTRY' => 'Billing Country',
    'LBL_SHIPPING_ADDRESS_STREET' => 'Shipping Street',
    'LBL_SHIPPING_ADDRESS_CITY' => 'Shipping City',
    'LBL_SHIPPING_ADDRESS_STATE' => 'Shipping State',
    'LBL_SHIPPING_ADDRESS_POSTALCODE' => 'Shipping Postal Code',
    'LBL_SHIPPING_ADDRESS_COUNTRY' => 'Shipping Country',
    'LBL_SAME_AS_BILLING' => 'Copy Billing Address',

    // Panel Labels
    'LBL_ACCOUNT_INFORMATION' => 'Account Information',
    'LBL_CONTACT_INFORMATION' => 'Contact Information', 
    'LBL_DESCRIPTION_INFORMATION' => 'Description',
    'LBL_BILLING_ADDRESS' => 'Billing Address',
    'LBL_SHIPPING_ADDRESS' => 'Shipping Address',
    'LBL_FINANCIAL_INFORMATION' => 'Financial Information',
    'LBL_HIERARCHY_INFORMATION' => 'Company Hierarchy',
    'LBL_DEAL_INFORMATION' => 'Deal Activity',
    'LBL_PORTFOLIO_INFORMATION' => 'Portfolio Management',
    'LBL_COMPLIANCE_INFORMATION' => 'Compliance & Risk',

    // Buttons and Actions
    'LBL_SAVE_BUTTON_LABEL' => 'Save',
    'LBL_SAVE_NEW_BUTTON_LABEL' => 'Save & Create New',
    'LBL_CANCEL_BUTTON_LABEL' => 'Cancel',
    'LBL_DELETE_BUTTON_LABEL' => 'Delete',
    'LBL_DUPLICATE_BUTTON_LABEL' => 'Duplicate',
    'LBL_VIEW_HIERARCHY_BUTTON' => 'View Hierarchy',
    'LBL_CALCULATE_HEALTH_BUTTON' => 'Calculate Health Score',
    'LBL_PORTFOLIO_METRICS_BUTTON' => 'Portfolio Metrics',

    // Subpanel titles
    'LBL_CONTACTS_SUBPANEL_TITLE' => 'Contacts',
    'LBL_DEALS_SUBPANEL_TITLE' => 'Deals',
    'LBL_SUBSIDIARIES_SUBPANEL_TITLE' => 'Subsidiaries',
    'LBL_OPPORTUNITIES_SUBPANEL_TITLE' => 'Opportunities',
    'LBL_ACTIVITIES_SUBPANEL_TITLE' => 'Activities',
    'LBL_HISTORY_SUBPANEL_TITLE' => 'History',
    'LBL_DOCUMENTS_SUBPANEL_TITLE' => 'Documents',
    'LBL_PROJECTS_SUBPANEL_TITLE' => 'Projects',

    // Search labels
    'LBL_SEARCH_BUTTON' => 'Search',
    'LBL_CLEAR_BUTTON' => 'Clear',
    'LBL_BASIC_SEARCH' => 'Basic Search',
    'LBL_ADVANCED_SEARCH' => 'Advanced Search',
    'LBL_SAVED_SEARCH' => 'Saved Searches',

    // List view labels
    'LBL_LIST_INDUSTRY' => 'Industry',
    'LBL_LIST_ACCOUNT_TYPE' => 'Type',
    'LBL_LIST_ANNUAL_REVENUE' => 'Revenue',
    'LBL_LIST_EMPLOYEE_COUNT' => 'Employees',
    'LBL_LIST_PARENT_NAME' => 'Parent Company',
    'LBL_LIST_DEAL_COUNT' => 'Deals',
    'LBL_LIST_RATING' => 'Rating',
    'LBL_LIST_ACCOUNT_STATUS' => 'Status',

    // Validation messages
    'LBL_CIRCULAR_HIERARCHY_ERROR' => 'Error: Circular reference detected in company hierarchy',
    'LBL_INVALID_REVENUE_ERROR' => 'Error: Annual revenue must be a positive number',
    'LBL_INVALID_EMPLOYEE_COUNT_ERROR' => 'Error: Employee count must be a positive integer',

    // Help text
    'LBL_HELP_ACCOUNT_TYPE' => 'Specify the role of this company in the M&A process',
    'LBL_HELP_PARENT_ACCOUNT' => 'Select the parent company if this is a subsidiary',
    'LBL_HELP_RATING' => 'Rate the potential of this account for future deals',
    'LBL_HELP_EXIT_STRATEGY' => 'For portfolio companies, specify the planned exit approach',

    // Error messages
    'ERR_DELETE_RECORD' => 'You must specify a record number to delete the account.',
    'ERR_MISSING_REQUIRED_FIELDS' => 'Missing required field(s):',
    'ERR_INVALID_EMAIL' => 'Please enter a valid email address.',
    'ERR_DUPLICATE_TAX_ID' => 'Tax ID already exists for another account.',

    // Success messages
    'LBL_ACCOUNT_SAVED' => 'Account saved successfully.',
    'LBL_HIERARCHY_CALCULATED' => 'Hierarchy levels calculated successfully.',
    'LBL_HEALTH_SCORE_UPDATED' => 'Health score updated successfully.',
);

// Dropdown options
$app_list_strings['account_type_dom'] = array(
    '' => '',
    'target' => 'Target Company',
    'portfolio' => 'Portfolio Company',
    'acquirer' => 'Acquirer',
    'strategic_partner' => 'Strategic Partner',
    'broker' => 'Broker/Intermediary',
    'lender' => 'Lender/Financial Institution',
    'legal_counsel' => 'Legal Counsel',
    'advisor' => 'Advisor/Consultant',
    'service_provider' => 'Service Provider',
    'vendor' => 'Vendor/Supplier',
    'customer' => 'Customer/Client',
    'competitor' => 'Competitor',
    'other' => 'Other',
);

$app_list_strings['ownership_type_dom'] = array(
    '' => '',
    'public' => 'Publicly Traded',
    'private' => 'Private Company',
    'subsidiary' => 'Subsidiary',
    'partnership' => 'Partnership',
    'llc' => 'Limited Liability Company',
    'corporation' => 'Corporation',
    'non_profit' => 'Non-Profit',
    'government' => 'Government Entity',
    'trust' => 'Trust',
    'other' => 'Other',
);

$app_list_strings['account_rating_dom'] = array(
    '' => '',
    'hot' => 'Hot Prospect',
    'warm' => 'Warm Prospect',
    'cold' => 'Cold Prospect',
    'strategic' => 'Strategic Target',
    'opportunistic' => 'Opportunistic',
    'platform' => 'Platform Investment',
    'add_on' => 'Add-On Acquisition',
    'exit_candidate' => 'Exit Candidate',
    'hold' => 'Hold',
    'divest' => 'Divest',
);

$app_list_strings['account_status_dom'] = array(
    'active' => 'Active',
    'inactive' => 'Inactive',
    'prospect' => 'Prospect',
    'under_review' => 'Under Review',
    'due_diligence' => 'Due Diligence',
    'negotiation' => 'Negotiation',
    'closed_won' => 'Acquired',
    'closed_lost' => 'Passed',
    'on_hold' => 'On Hold',
    'dead' => 'Dead',
);

$app_list_strings['risk_assessment_dom'] = array(
    '' => '',
    'low' => 'Low Risk',
    'medium' => 'Medium Risk',
    'high' => 'High Risk',
    'critical' => 'Critical Risk',
);

$app_list_strings['compliance_status_dom'] = array(
    '' => '',
    'compliant' => 'Compliant',
    'pending_review' => 'Pending Review',
    'non_compliant' => 'Non-Compliant',
    'exempted' => 'Exempted',
    'unknown' => 'Unknown',
);

$app_list_strings['exit_strategy_dom'] = array(
    '' => '',
    'ipo' => 'Initial Public Offering',
    'strategic_sale' => 'Strategic Sale',
    'financial_buyer' => 'Financial Buyer Sale',
    'management_buyout' => 'Management Buyout',
    'recapitalization' => 'Recapitalization',
    'dividend' => 'Dividend',
    'hold_long_term' => 'Long-Term Hold',
    'liquidation' => 'Liquidation',
    'other' => 'Other',
);

$app_list_strings['integration_status_dom'] = array(
    '' => '',
    'not_started' => 'Not Started',
    'planning' => 'Planning',
    'in_progress' => 'In Progress',
    'delayed' => 'Delayed',
    'completed' => 'Completed',
    'on_hold' => 'On Hold',
    'cancelled' => 'Cancelled',
);