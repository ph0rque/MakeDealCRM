<?php
/**
 * Pipeline language strings for Deals module
 */

$mod_strings['LBL_PIPELINE'] = 'Deal Pipeline';
$mod_strings['LBL_PIPELINE_VIEW'] = 'Pipeline View';
$mod_strings['LBL_PIPELINE_STAGE'] = 'Pipeline Stage';
$mod_strings['LBL_STAGE_ENTERED_DATE'] = 'Stage Entered Date';
$mod_strings['LBL_DAYS_IN_STAGE'] = 'Days in Stage';
$mod_strings['LBL_AT_RISK_STATUS'] = 'At Risk Status';
$mod_strings['LBL_WIP_POSITION'] = 'WIP Position';
$mod_strings['LBL_IS_ARCHIVED'] = 'Archived';
$mod_strings['LBL_WIP_LIMIT'] = 'WIP Limit';
$mod_strings['LBL_FOCUS_FLAG'] = 'Focus';
$mod_strings['LBL_DRAG_TO_MOVE'] = 'Drag to move deal between stages';
$mod_strings['LBL_MOBILE_SWIPE'] = 'Swipe left/right to navigate stages';
$mod_strings['LBL_DAYS'] = 'days';
$mod_strings['LBL_AT_RISK'] = 'At Risk';
$mod_strings['LBL_OVERDUE'] = 'Overdue';
$mod_strings['LBL_WIP_LIMIT_REACHED'] = 'WIP limit reached for this stage';
$mod_strings['LBL_REFRESH_PIPELINE'] = 'Refresh Pipeline';

// Pipeline stages
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

// At risk status
$app_list_strings['at_risk_status_list'] = array(
    'normal' => 'Normal',
    'warning' => 'Warning (14+ days)',
    'alert' => 'Alert (30+ days)',
);