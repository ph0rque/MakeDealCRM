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

// Pipeline stage options
$app_list_strings['pipeline_stage_list'] = array(
    'sourcing' => 'Sourcing',
    'screening' => 'Screening',
    'analysis_outreach' => 'Analysis & Outreach',
    'due_diligence' => 'Due Diligence',
    'valuation_structuring' => 'Valuation & Structuring',
    'loi_negotiation' => 'LOI / Negotiation',
    'financing' => 'Financing',
    'closing' => 'Closing',
    'closed_owned_90_day' => 'Closed/Owned – 90-Day Plan',
    'closed_owned_stable' => 'Closed/Owned – Stable Operations',
    'unavailable' => 'Unavailable',
);