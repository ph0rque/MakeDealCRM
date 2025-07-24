<?php
/**
 * Deals Module Variable Definitions
 * Extends the Opportunities module with additional fields and relationships
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

// Include the base Opportunities vardefs
require_once('modules/Opportunities/vardefs.php');

// Extend the dictionary for Deals
$dictionary['Deal'] = $dictionary['Opportunity'];
$dictionary['Deal']['table'] = 'opportunities';
$dictionary['Deal']['module'] = 'Deals';
$dictionary['Deal']['object_name'] = 'Deal';
$dictionary['Deal']['importable'] = true;
$dictionary['Deal']['unified_search'] = true;
$dictionary['Deal']['unified_search_default_enabled'] = true;
$dictionary['Deal']['comment'] = 'Deals module extending Opportunities with pipeline management';

// Override the module name in fields
$dictionary['Deal']['fields']['name']['vname'] = 'LBL_DEAL_NAME';

// Add custom fields for pipeline management
$dictionary['Deal']['fields']['pipeline_stage_c'] = array(
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

$dictionary['Deal']['fields']['stage_entered_date_c'] = array(
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

$dictionary['Deal']['fields']['expected_close_date_c'] = array(
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

$dictionary['Deal']['fields']['deal_source_c'] = array(
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

$dictionary['Deal']['fields']['pipeline_notes_c'] = array(
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

$dictionary['Deal']['fields']['days_in_stage_c'] = array(
    'name' => 'days_in_stage_c',
    'vname' => 'LBL_DAYS_IN_STAGE',
    'type' => 'int',
    'len' => 11,
    'comment' => 'Number of days in current stage',
    'required' => false,
    'reportable' => true,
    'audited' => false,
    'importable' => false,
    'duplicate_merge' => 'disabled',
    'massupdate' => false,
    'calculated' => true,
    'formula' => 'daysUntil($stage_entered_date_c)',
);

// Add indices for performance
$dictionary['Deal']['indices'][] = array(
    'name' => 'idx_deals_pipeline_stage',
    'type' => 'index',
    'fields' => array('pipeline_stage_c', 'deleted'),
);

$dictionary['Deal']['indices'][] = array(
    'name' => 'idx_deals_stage_date',
    'type' => 'index',
    'fields' => array('stage_entered_date_c'),
);

// Add relationship to pipeline history
$dictionary['Deal']['relationships']['deal_pipeline_history'] = array(
    'lhs_module' => 'Deals',
    'lhs_table' => 'opportunities',
    'lhs_key' => 'id',
    'rhs_module' => 'PipelineHistory',
    'rhs_table' => 'pipeline_stage_history',
    'rhs_key' => 'deal_id',
    'relationship_type' => 'one-to-many',
);

// Add relationship to checklist templates (many-to-many)
$dictionary['Deal']['relationships']['deals_checklist_templates'] = array(
    'lhs_module' => 'Deals',
    'lhs_table' => 'opportunities',
    'lhs_key' => 'id',
    'rhs_module' => 'ChecklistTemplates',
    'rhs_table' => 'checklist_templates',
    'rhs_key' => 'id',
    'relationship_type' => 'many-to-many',
    'join_table' => 'deals_checklist_templates',
    'join_key_lhs' => 'deal_id',
    'join_key_rhs' => 'template_id',
);

// Add relationship to checklist items (one-to-many)
$dictionary['Deal']['relationships']['deals_checklist_items'] = array(
    'lhs_module' => 'Deals',
    'lhs_table' => 'opportunities',
    'lhs_key' => 'id',
    'rhs_module' => 'ChecklistItems',
    'rhs_table' => 'checklist_items',
    'rhs_key' => 'deal_id',
    'relationship_type' => 'one-to-many',
);