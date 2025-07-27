<?php
/**
 * Language file for Deals module (English)
 */

$mod_strings = array(
    // Module labels
    'LBL_MODULE_NAME' => 'Deals',
    'LBL_MODULE_TITLE' => 'Deals: Home',
    'LBL_MODULE_ID' => 'Deals',
    'LBL_SEARCH_FORM_TITLE' => 'Deal Search',
    'LBL_LIST_FORM_TITLE' => 'Deal List',
    'LBL_NEW_FORM_TITLE' => 'Create Deal',
    
    // Pipeline View
    'LBL_PIPELINE_VIEW' => 'Pipeline View',
    'LBL_PIPELINE_STAGE' => 'Pipeline Stage',
    'LBL_STAGE_ENTERED_DATE' => 'Stage Entered Date',
    'LBL_EXPECTED_CLOSE_DATE' => 'Expected Close Date',
    'LBL_DEAL_SOURCE' => 'Deal Source',
    'LBL_PIPELINE_NOTES' => 'Pipeline Notes',
    'LBL_DAYS_IN_STAGE' => 'Days in Stage',
    
    // Pipeline stages
    'LBL_STAGE_SOURCING' => 'Sourcing',
    'LBL_STAGE_SCREENING' => 'Screening',
    'LBL_STAGE_ANALYSIS_OUTREACH' => 'Analysis & Outreach',
    'LBL_STAGE_DUE_DILIGENCE' => 'Due Diligence',
    'LBL_STAGE_VALUATION_STRUCTURING' => 'Valuation & Structuring',
    'LBL_STAGE_LOI_NEGOTIATION' => 'LOI / Negotiation',
    'LBL_STAGE_FINANCING' => 'Financing',
    'LBL_STAGE_CLOSING' => 'Closing',
    'LBL_STAGE_CLOSED_OWNED_90_DAY' => 'Closed/Owned – 90-Day Plan',
    'LBL_STAGE_CLOSED_OWNED_STABLE' => 'Closed/Owned – Stable Operations',
    'LBL_STAGE_UNAVAILABLE' => 'Unavailable',
    
    // Deal fields
    'LBL_DEAL_NAME' => 'Deal Name',
    'LBL_ACCOUNT_NAME' => 'Account Name',
    'LBL_AMOUNT' => 'Deal Amount',
    'LBL_ASSIGNED_TO' => 'Assigned To',
    'LBL_DATE_CLOSED' => 'Expected Close Date',
    'LBL_PROBABILITY' => 'Probability (%)',
    'LBL_SALES_STAGE' => 'Sales Stage',
    
    // Actions
    'LBL_CREATE_DEAL' => 'Create Deal',
    'LBL_VIEW_PIPELINE' => 'View Pipeline',
    'LBL_LIST_VIEW' => 'List View',
    'LBL_IMPORT_DEALS' => 'Import Deals',
    'LBL_FINANCIAL_DASHBOARD' => 'Financial Dashboard',
    'LBL_EXPORT_DEALS' => 'Export Deals',
    
    // Pipeline actions
    'LBL_REFRESH_PIPELINE' => 'Refresh Pipeline',
    'LBL_COMPACT_VIEW' => 'Compact View',
    'LBL_SHOW_FOCUSED' => 'Show Focused',
    'LBL_SHOW_ALL' => 'Show All',
    'LBL_MARK_AS_FOCUSED' => 'Mark as Focused',
    'LBL_REMOVE_FOCUS' => 'Remove Focus',
    
    // Messages
    'LBL_DEAL_MOVED_SUCCESS' => 'Deal moved successfully',
    'LBL_DEAL_MOVE_FAILED' => 'Failed to move deal',
    'LBL_ACCESS_DENIED' => 'Access denied',
    'LBL_DEAL_NOT_FOUND' => 'Deal not found',
    'LBL_WIP_LIMIT_EXCEEDED' => 'WIP limit exceeded for this stage',
    'LBL_NETWORK_ERROR' => 'Network error. Please try again.',
    
    // Focus messages
    'LBL_DEAL_FOCUSED' => 'Deal marked as focused',
    'LBL_FOCUS_REMOVED' => 'Focus removed from deal',
    'LBL_FOCUS_UPDATE_FAILED' => 'Failed to update focus',
    
    // Mobile
    'LBL_SWIPE_HINT' => 'Swipe to see more stages',
    'LBL_DROP_DEALS_HERE' => 'Drop deals here',
    
    // WIP indicators
    'LBL_WIP_LIMIT' => 'WIP Limit',
    'LBL_OVER_LIMIT' => 'Over Limit',
    'LBL_NEAR_LIMIT' => 'Near Limit',
);

// Deal source options
$app_list_strings['deal_source_list'] = array(
    '' => '',
    'cold_call' => 'Cold Call',
    'existing_customer' => 'Existing Customer',
    'self_generated' => 'Self Generated',
    'employee' => 'Employee',
    'partner' => 'Partner',
    'public_relations' => 'Public Relations',
    'direct_mail' => 'Direct Mail',
    'conference' => 'Conference',
    'trade_show' => 'Trade Show',
    'web_site' => 'Web Site',
    'word_of_mouth' => 'Word of Mouth',
    'email' => 'Email',
    'campaign' => 'Campaign',
    'other' => 'Other',
);

// Add additional module strings
$mod_strings['LBL_CHECKLIST_COMPLETION'] = 'Checklist Completion';
$mod_strings['LBL_CHECKLIST_PROGRESS'] = 'Checklist Progress';
$mod_strings['LBL_VIEW_CHECKLISTS'] = 'View Checklists';
$mod_strings['LBL_STAKEHOLDERS'] = 'Stakeholders';
$mod_strings['LBL_STAGE_HISTORY'] = 'Stage History';

// Subpanel titles
$mod_strings['LBL_CONTACTS_SUBPANEL_TITLE'] = 'Stakeholders';
$mod_strings['LBL_DOCUMENTS_SUBPANEL_TITLE'] = 'Documents';
$mod_strings['LBL_TASKS_SUBPANEL_TITLE'] = 'Tasks';
$mod_strings['LBL_NOTES_SUBPANEL_TITLE'] = 'Notes';
$mod_strings['LBL_MEETINGS_SUBPANEL_TITLE'] = 'Meetings';
$mod_strings['LBL_CALLS_SUBPANEL_TITLE'] = 'Calls';
$mod_strings['LBL_EMAILS_SUBPANEL_TITLE'] = 'Emails';
$mod_strings['LBL_CHECKLIST_ITEMS_SUBPANEL_TITLE'] = 'Checklist Items';
$mod_strings['LBL_APPLY_CHECKLIST_TEMPLATE'] = 'Apply Checklist Template';
$mod_strings['LBL_MANAGE_TEMPLATES'] = 'Manage Templates';
$mod_strings['LBL_CHECKLISTS_SUBPANEL_TITLE'] = 'Checklists';

// Search filters
$mod_strings['LBL_SEARCH_BY_STAGE'] = 'Search by Stage';
$mod_strings['LBL_SEARCH_BY_SOURCE'] = 'Search by Source';
$mod_strings['LBL_SEARCH_BY_DATE_RANGE'] = 'Search by Date Range';

// Dashlets
$mod_strings['LBL_PIPELINE_METRICS'] = 'Pipeline Metrics';
$mod_strings['LBL_STAGE_DURATION'] = 'Average Stage Duration';
$mod_strings['LBL_CONVERSION_RATES'] = 'Stage Conversion Rates';
$mod_strings['LBL_DEALS_BY_STAGE'] = 'Deals by Stage';
$mod_strings['LBL_DEALS_BY_SOURCE'] = 'Deals by Source';
// Menu items
$mod_strings['LBL_ADVANCED_SEARCH'] = 'Advanced Search';
$mod_strings['LBL_REPORTS'] = 'Reports';
$mod_strings['LBL_PIPELINE_ANALYTICS'] = 'Pipeline Analytics';
$mod_strings['LBL_BULK_OPERATIONS'] = 'Bulk Operations';
$mod_strings['LBL_CONFIGURE_MODULE'] = 'Configure Module';

// CRUD Operations
$mod_strings['LBL_CREATE_DEAL'] = 'Create Deal';
$mod_strings['LBL_EDIT_DEAL'] = 'Edit Deal';
$mod_strings['LBL_DELETE_DEAL'] = 'Delete Deal';
$mod_strings['LBL_DUPLICATE_DEAL'] = 'Duplicate Deal';
$mod_strings['LBL_SAVE_DEAL'] = 'Save Deal';
$mod_strings['LBL_CANCEL'] = 'Cancel';

// Validation Messages
$mod_strings['LBL_REQUIRED_FIELD'] = 'This field is required';
$mod_strings['LBL_INVALID_FORMAT'] = 'Invalid format';
$mod_strings['LBL_INVALID_AMOUNT'] = 'Please enter a valid amount';
$mod_strings['LBL_INVALID_DATE'] = 'Please enter a valid date';
$mod_strings['LBL_INVALID_PROBABILITY'] = 'Probability must be between 0 and 100';

// Search
$mod_strings['LBL_SEARCH_RESULTS'] = 'Search Results';
$mod_strings['LBL_NO_RESULTS'] = 'No results found';
$mod_strings['LBL_SEARCH_CRITERIA'] = 'Search Criteria';
$mod_strings['LBL_CLEAR_SEARCH'] = 'Clear Search';

// Workflow Messages
$mod_strings['LBL_WORKFLOW_TRIGGERED'] = 'Workflow triggered';
$mod_strings['LBL_STAGE_CHANGE_LOGGED'] = 'Stage change logged';
$mod_strings['LBL_VALIDATION_PASSED'] = 'Validation passed';
$mod_strings['LBL_VALIDATION_FAILED'] = 'Validation failed';
?>