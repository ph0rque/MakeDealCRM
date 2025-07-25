<?php
/**
 * Application Language Extensions for Deals Module
 * 
 * @package MakeDealCRM
 * @module Deals
 * @language en_us
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

// Module name
$app_list_strings['moduleList']['Deals'] = 'Deals';
$app_list_strings['moduleListSingular']['Deals'] = 'Deal';

// Pipeline stage dropdown list
$app_list_strings['pipeline_stage_list'] = array(
    '' => '',
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

// Deal source dropdown list
$app_list_strings['deal_source_list'] = array(
    '' => '',
    'direct_inquiry' => 'Direct Inquiry',
    'broker_referral' => 'Broker Referral',
    'network_referral' => 'Network Referral',
    'cold_outreach' => 'Cold Outreach',
    'conference_event' => 'Conference/Event',
    'online_marketplace' => 'Online Marketplace',
    'internal_sourcing' => 'Internal Sourcing',
    'other' => 'Other',
);

// Deal type dropdown list
$app_list_strings['deal_type_list'] = array(
    '' => '',
    'acquisition' => 'Acquisition',
    'merger' => 'Merger',
    'asset_purchase' => 'Asset Purchase',
    'stock_purchase' => 'Stock Purchase',
    'joint_venture' => 'Joint Venture',
    'strategic_partnership' => 'Strategic Partnership',
    'divestiture' => 'Divestiture',
    'other' => 'Other',
);

// Record type display
$app_list_strings['record_type_display']['Deals'] = 'Deal';
$app_list_strings['record_type_display_notes']['Deals'] = 'Deal';

// Parent type dropdown extensions
if (!isset($app_list_strings['parent_type_display'])) {
    $app_list_strings['parent_type_display'] = array();
}
$app_list_strings['parent_type_display']['Deals'] = 'Deal';

// Related modules dropdown
if (!isset($app_list_strings['record_type_display_related'])) {
    $app_list_strings['record_type_display_related'] = array();
}
$app_list_strings['record_type_display_related']['Deals'] = 'Deals';

// Quick create menu
$app_list_strings['quickcreate_enabled']['Deals'] = true;