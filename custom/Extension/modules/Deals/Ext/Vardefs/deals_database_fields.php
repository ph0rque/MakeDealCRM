<?php
/**
 * Extended Vardefs for Deals Module Database Integration
 * Purpose: Define all custom fields and relationships for database schema
 * Agent: Database Migration Specialist
 * Date: 2025-07-24
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

// ============================================================================
// PIPELINE MANAGEMENT FIELDS
// ============================================================================

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

$dictionary['Deal']['fields']['time_in_stage'] = array(
    'name' => 'time_in_stage',
    'vname' => 'LBL_TIME_IN_STAGE',
    'type' => 'int',
    'len' => 11,
    'comment' => 'Time spent in current stage (days)',
    'required' => false,
    'reportable' => true,
    'audited' => false,
    'importable' => false,
    'duplicate_merge' => 'disabled',
    'massupdate' => false,
    'default' => 0,
);

$dictionary['Deal']['fields']['wip_position'] = array(
    'name' => 'wip_position',
    'vname' => 'LBL_WIP_POSITION',
    'type' => 'int',
    'len' => 11,
    'comment' => 'Position within stage for WIP limit ordering',
    'required' => false,
    'reportable' => false,
    'audited' => false,
    'importable' => false,
    'duplicate_merge' => 'disabled',
    'massupdate' => false,
);

$dictionary['Deal']['fields']['is_archived'] = array(
    'name' => 'is_archived',
    'vname' => 'LBL_IS_ARCHIVED',
    'type' => 'bool',
    'comment' => 'Whether the deal is archived',
    'required' => false,
    'reportable' => true,
    'audited' => true,
    'importable' => true,
    'duplicate_merge' => 'disabled',
    'massupdate' => true,
    'default' => 0,
);

$dictionary['Deal']['fields']['last_stage_update'] = array(
    'name' => 'last_stage_update',
    'vname' => 'LBL_LAST_STAGE_UPDATE',
    'type' => 'datetime',
    'comment' => 'Last time the stage was updated',
    'required' => false,
    'reportable' => true,
    'audited' => false,
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

// ============================================================================
// CHECKLIST MANAGEMENT FIELDS
// ============================================================================

$dictionary['Deal']['fields']['checklist_completion_c'] = array(
    'name' => 'checklist_completion_c',
    'vname' => 'LBL_CHECKLIST_COMPLETION',
    'type' => 'decimal',
    'len' => '5,2',
    'comment' => 'Overall checklist completion percentage',
    'required' => false,
    'reportable' => true,
    'audited' => true,
    'importable' => false,
    'duplicate_merge' => 'disabled',
    'massupdate' => false,
    'default' => '0.00',
);

$dictionary['Deal']['fields']['active_checklists_count_c'] = array(
    'name' => 'active_checklists_count_c',
    'vname' => 'LBL_ACTIVE_CHECKLISTS_COUNT',
    'type' => 'int',
    'len' => 11,
    'comment' => 'Number of active checklists',
    'required' => false,
    'reportable' => true,
    'audited' => false,
    'importable' => false,
    'duplicate_merge' => 'disabled',
    'massupdate' => false,
    'default' => 0,
);

$dictionary['Deal']['fields']['overdue_checklist_items_c'] = array(
    'name' => 'overdue_checklist_items_c',
    'vname' => 'LBL_OVERDUE_CHECKLIST_ITEMS',
    'type' => 'int',
    'len' => 11,
    'comment' => 'Number of overdue checklist items',
    'required' => false,
    'reportable' => true,
    'audited' => false,
    'importable' => false,
    'duplicate_merge' => 'disabled',
    'massupdate' => false,
    'default' => 0,
);

// ============================================================================
// RELATIONSHIPS
// ============================================================================

// Relationship to pipeline stage history
$dictionary['Deal']['relationships']['deal_pipeline_history'] = array(
    'lhs_module' => 'Deals',
    'lhs_table' => 'opportunities',
    'lhs_key' => 'id',
    'rhs_module' => 'PipelineHistory',
    'rhs_table' => 'pipeline_stage_history',
    'rhs_key' => 'deal_id',
    'relationship_type' => 'one-to-many',
);

// Relationship to deal stage transitions
$dictionary['Deal']['relationships']['deal_stage_transitions'] = array(
    'lhs_module' => 'Deals',
    'lhs_table' => 'opportunities',
    'lhs_key' => 'id',
    'rhs_module' => 'DealStageTransitions',
    'rhs_table' => 'deal_stage_transitions',
    'rhs_key' => 'deal_id',
    'relationship_type' => 'one-to-many',
);

// Relationship to checklist templates (many-to-many)
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

// Relationship to checklist items (one-to-many)
$dictionary['Deal']['relationships']['deals_checklist_items'] = array(
    'lhs_module' => 'Deals',
    'lhs_table' => 'opportunities',
    'lhs_key' => 'id',
    'rhs_module' => 'ChecklistItems',
    'rhs_table' => 'deals_checklist_items',
    'rhs_key' => 'deal_id',
    'relationship_type' => 'one-to-many',
);

// Relationship to task generation requests
$dictionary['Deal']['relationships']['deal_task_requests'] = array(
    'lhs_module' => 'Deals',
    'lhs_table' => 'opportunities',
    'lhs_key' => 'id',
    'rhs_module' => 'TaskGenerationRequests',
    'rhs_table' => 'task_generation_requests',
    'rhs_key' => 'deal_id',
    'relationship_type' => 'one-to-many',
);

// Relationship to generated tasks
$dictionary['Deal']['relationships']['deal_generated_tasks'] = array(
    'lhs_module' => 'Deals',
    'lhs_table' => 'opportunities',
    'lhs_key' => 'id',
    'rhs_module' => 'GeneratedTasks',
    'rhs_table' => 'generated_tasks',
    'rhs_key' => 'deal_id',
    'relationship_type' => 'one-to-many',
);

// Relationship to file requests
$dictionary['Deal']['relationships']['deal_file_requests'] = array(
    'lhs_module' => 'Deals',
    'lhs_table' => 'opportunities',
    'lhs_key' => 'id',
    'rhs_module' => 'FileRequests',
    'rhs_table' => 'file_requests',
    'rhs_key' => 'deal_id',
    'relationship_type' => 'one-to-many',
);

// Enhanced relationship to contacts with roles
$dictionary['Deal']['relationships']['deals_contacts_detailed'] = array(
    'lhs_module' => 'Deals',
    'lhs_table' => 'opportunities',
    'lhs_key' => 'id',
    'rhs_module' => 'Contacts',
    'rhs_table' => 'contacts',
    'rhs_key' => 'id',
    'relationship_type' => 'many-to-many',
    'join_table' => 'deals_contacts_relationships',
    'join_key_lhs' => 'deal_id',
    'join_key_rhs' => 'contact_id',
);

// Relationship to communication history
$dictionary['Deal']['relationships']['deal_communications'] = array(
    'lhs_module' => 'Deals',
    'lhs_table' => 'opportunities',
    'lhs_key' => 'id',
    'rhs_module' => 'CommunicationHistory',
    'rhs_table' => 'communication_history',
    'rhs_key' => 'deal_id',
    'relationship_type' => 'one-to-many',
);

// ============================================================================
// INDEXES FOR PERFORMANCE
// ============================================================================

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

$dictionary['Deal']['indices'][] = array(
    'name' => 'idx_deals_checklist_completion',
    'type' => 'index',
    'fields' => array('checklist_completion_c'),
);

$dictionary['Deal']['indices'][] = array(
    'name' => 'idx_deals_active_checklists',
    'type' => 'index',
    'fields' => array('active_checklists_count_c'),
);

$dictionary['Deal']['indices'][] = array(
    'name' => 'idx_deals_overdue_items',
    'type' => 'index',
    'fields' => array('overdue_checklist_items_c'),
);

$dictionary['Deal']['indices'][] = array(
    'name' => 'idx_deals_deal_source',
    'type' => 'index',
    'fields' => array('deal_source_c', 'deleted'),
);

$dictionary['Deal']['indices'][] = array(
    'name' => 'idx_deals_expected_close',
    'type' => 'index',
    'fields' => array('expected_close_date_c'),
);

$dictionary['Deal']['indices'][] = array(
    'name' => 'idx_deals_archived',
    'type' => 'index',
    'fields' => array('is_archived', 'deleted'),
);

// ============================================================================
// DROPDOWN LISTS (Options)
// ============================================================================

// Pipeline stages dropdown
$GLOBALS['app_list_strings']['pipeline_stage_list'] = array(
    'sourcing' => 'Sourcing',
    'initial_contact' => 'Initial Contact',
    'marketing_review' => 'Marketing Package Review',
    'management_call' => 'Management Call',
    'loi_submitted' => 'LOI Submitted',
    'due_diligence' => 'Due Diligence',
    'final_negotiation' => 'Final Negotiation',
    'purchase_agreement' => 'Purchase Agreement',
    'closing' => 'Closing',
    'closed' => 'Closed',
    'unavailable' => 'Unavailable',
);

// Deal sources dropdown
$GLOBALS['app_list_strings']['deal_source_list'] = array(
    'broker' => 'Business Broker',
    'direct' => 'Direct from Owner',
    'referral' => 'Referral',
    'website' => 'Website Inquiry',
    'networking' => 'Networking',
    'cold_outreach' => 'Cold Outreach',
    'online_marketplace' => 'Online Marketplace',
    'advertisement' => 'Advertisement',
    'other' => 'Other',
);

// Checklist completion status dropdown
$GLOBALS['app_list_strings']['checklist_completion_status_list'] = array(
    'pending' => 'Pending',
    'in_progress' => 'In Progress',
    'completed' => 'Completed',
    'not_applicable' => 'Not Applicable',
    'blocked' => 'Blocked',
);

// Task generation status dropdown
$GLOBALS['app_list_strings']['task_generation_status_list'] = array(
    'pending' => 'Pending',
    'processing' => 'Processing',
    'completed' => 'Completed',
    'failed' => 'Failed',
);

// File request status dropdown
$GLOBALS['app_list_strings']['file_request_status_list'] = array(
    'pending' => 'Pending',
    'sent' => 'Sent',
    'viewed' => 'Viewed',
    'uploaded' => 'Uploaded',  
    'completed' => 'Completed',
    'expired' => 'Expired',
);

// Contact relationship strength dropdown
$GLOBALS['app_list_strings']['relationship_strength_list'] = array(
    'weak' => 'Weak',
    'moderate' => 'Moderate',
    'strong' => 'Strong',
);

// Communication types dropdown
$GLOBALS['app_list_strings']['communication_type_list'] = array(
    'email' => 'Email',
    'call' => 'Phone Call',
    'meeting' => 'Meeting',
    'note' => 'Note',
    'document' => 'Document',
);

// Template categories dropdown
$GLOBALS['app_list_strings']['template_category_list'] = array(
    'screening' => 'Screening',
    'due_diligence' => 'Due Diligence',
    'closing' => 'Closing',
    'post_closing' => 'Post-Closing',
    'general' => 'General',
);

// ============================================================================
// LANGUAGE LABELS
// ============================================================================

$GLOBALS['mod_strings']['LBL_PIPELINE_STAGE'] = 'Pipeline Stage';
$GLOBALS['mod_strings']['LBL_STAGE_ENTERED_DATE'] = 'Stage Entered Date';
$GLOBALS['mod_strings']['LBL_TIME_IN_STAGE'] = 'Time in Stage';
$GLOBALS['mod_strings']['LBL_WIP_POSITION'] = 'WIP Position';
$GLOBALS['mod_strings']['LBL_IS_ARCHIVED'] = 'Archived';
$GLOBALS['mod_strings']['LBL_LAST_STAGE_UPDATE'] = 'Last Stage Update';
$GLOBALS['mod_strings']['LBL_EXPECTED_CLOSE_DATE'] = 'Expected Close Date';
$GLOBALS['mod_strings']['LBL_DEAL_SOURCE'] = 'Deal Source';
$GLOBALS['mod_strings']['LBL_PIPELINE_NOTES'] = 'Pipeline Notes';
$GLOBALS['mod_strings']['LBL_DAYS_IN_STAGE'] = 'Days in Stage';
$GLOBALS['mod_strings']['LBL_CHECKLIST_COMPLETION'] = 'Checklist Completion %';
$GLOBALS['mod_strings']['LBL_ACTIVE_CHECKLISTS_COUNT'] = 'Active Checklists';
$GLOBALS['mod_strings']['LBL_OVERDUE_CHECKLIST_ITEMS'] = 'Overdue Items';

?>