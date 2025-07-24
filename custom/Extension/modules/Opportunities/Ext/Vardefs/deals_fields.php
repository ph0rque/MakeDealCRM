<?php
/**
 * Extension to add Deals fields to Opportunities module
 */

// Pipeline Stage field
$dictionary['Opportunity']['fields']['pipeline_stage_c'] = array(
    'name' => 'pipeline_stage_c',
    'vname' => 'LBL_PIPELINE_STAGE',
    'type' => 'enum',
    'options' => 'pipeline_stage_list',
    'len' => 50,
    'default' => 'sourcing',
    'comment' => 'Current stage in the deal pipeline',
    'required' => false,
    'reportable' => true,
    'audited' => true,
    'importable' => true,
    'duplicate_merge' => 'enabled',
    'massupdate' => true,
);

// Stage Entered Date field
$dictionary['Opportunity']['fields']['stage_entered_date_c'] = array(
    'name' => 'stage_entered_date_c',
    'vname' => 'LBL_STAGE_ENTERED_DATE',
    'type' => 'datetime',
    'comment' => 'Date when the deal entered the current stage',
    'required' => false,
    'reportable' => true,
    'audited' => true,
    'importable' => false,
    'duplicate_merge' => 'disabled',
    'massupdate' => false,
);

// Expected Close Date field
$dictionary['Opportunity']['fields']['expected_close_date_c'] = array(
    'name' => 'expected_close_date_c',
    'vname' => 'LBL_EXPECTED_CLOSE_DATE',
    'type' => 'date',
    'comment' => 'Expected date for deal closure',
    'required' => false,
    'reportable' => true,
    'audited' => true,
    'importable' => true,
    'duplicate_merge' => 'enabled',
    'massupdate' => false,
);

// Deal Source field
$dictionary['Opportunity']['fields']['deal_source_c'] = array(
    'name' => 'deal_source_c',
    'vname' => 'LBL_DEAL_SOURCE',
    'type' => 'enum',
    'options' => 'deal_source_list',
    'len' => 50,
    'comment' => 'Source of the deal',
    'required' => false,
    'reportable' => true,
    'audited' => true,
    'importable' => true,
    'duplicate_merge' => 'enabled',
    'massupdate' => true,
);

// Pipeline Notes field
$dictionary['Opportunity']['fields']['pipeline_notes_c'] = array(
    'name' => 'pipeline_notes_c',
    'vname' => 'LBL_PIPELINE_NOTES',
    'type' => 'text',
    'comment' => 'Notes related to pipeline progression',
    'required' => false,
    'reportable' => true,
    'audited' => true,
    'importable' => true,
    'duplicate_merge' => 'disabled',
    'massupdate' => false,
);