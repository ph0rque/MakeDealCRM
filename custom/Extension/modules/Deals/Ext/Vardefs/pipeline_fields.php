<?php
/**
 * Pipeline fields for Deals module
 */

$dictionary['Deal']['fields']['pipeline_stage_c'] = array(
    'name' => 'pipeline_stage_c',
    'vname' => 'LBL_PIPELINE_STAGE',
    'type' => 'enum',
    'options' => 'pipeline_stage_list',
    'default' => 'sourcing',
    'len' => 50,
    'required' => false,
    'reportable' => true,
    'audited' => true,
    'unified_search' => false,
    'merge_filter' => 'disabled',
    'massupdate' => true,
);

$dictionary['Deal']['fields']['stage_entered_date_c'] = array(
    'name' => 'stage_entered_date_c',
    'vname' => 'LBL_STAGE_ENTERED_DATE',
    'type' => 'datetime',
    'required' => false,
    'reportable' => true,
    'audited' => true,
);

$dictionary['Deal']['fields']['days_in_stage'] = array(
    'name' => 'days_in_stage',
    'vname' => 'LBL_DAYS_IN_STAGE',
    'type' => 'int',
    'source' => 'non-db',
    'reportable' => true,
);

$dictionary['Deal']['fields']['at_risk_status'] = array(
    'name' => 'at_risk_status',
    'vname' => 'LBL_AT_RISK_STATUS',
    'type' => 'enum',
    'options' => 'at_risk_status_list',
    'source' => 'non-db',
    'reportable' => true,
);

$dictionary['Deal']['fields']['wip_position'] = array(
    'name' => 'wip_position',
    'vname' => 'LBL_WIP_POSITION',
    'type' => 'int',
    'required' => false,
    'reportable' => false,
);

$dictionary['Deal']['fields']['is_archived'] = array(
    'name' => 'is_archived',
    'vname' => 'LBL_IS_ARCHIVED',
    'type' => 'bool',
    'default' => '0',
    'required' => false,
    'reportable' => true,
);

// Add indices for pipeline performance
$dictionary['Deal']['indices'][] = array(
    'name' => 'idx_pipeline_stage',
    'type' => 'index',
    'fields' => array('pipeline_stage_c', 'deleted'),
);

$dictionary['Deal']['indices'][] = array(
    'name' => 'idx_stage_tracking',
    'type' => 'index',
    'fields' => array('pipeline_stage_c', 'stage_entered_date_c', 'deleted'),
);

$dictionary['Deal']['indices'][] = array(
    'name' => 'idx_focus_pipeline',
    'type' => 'index',
    'fields' => array('focus_c', 'pipeline_stage_c', 'deleted'),
);