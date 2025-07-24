<?php
/**
 * Checklist relationship fields for Deals module
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

// Add checklist templates relationship field
$dictionary['Deal']['fields']['checklist_templates'] = array(
    'name' => 'checklist_templates',
    'type' => 'link',
    'relationship' => 'deals_checklist_templates',
    'source' => 'non-db',
    'vname' => 'LBL_CHECKLIST_TEMPLATES',
    'studio' => 'visible',
    'side' => 'left',
);

// Add checklist items relationship field
$dictionary['Deal']['fields']['checklist_items'] = array(
    'name' => 'checklist_items',
    'type' => 'link',
    'relationship' => 'deals_checklist_items',
    'source' => 'non-db',
    'vname' => 'LBL_CHECKLIST_ITEMS',
    'studio' => 'visible',
    'side' => 'left',
);

// Add computed field for checklist completion percentage
$dictionary['Deal']['fields']['checklist_completion_c'] = array(
    'name' => 'checklist_completion_c',
    'vname' => 'LBL_CHECKLIST_COMPLETION',
    'type' => 'decimal',
    'precision' => 5,
    'scale' => 2,
    'comment' => 'Overall checklist completion percentage',
    'required' => false,
    'reportable' => true,
    'audited' => false,
    'importable' => false,
    'duplicate_merge' => 'disabled',
    'massupdate' => false,
    'calculated' => true,
    'source' => 'non-db',
    'studio' => 'visible',
);

// Add field for active checklist count
$dictionary['Deal']['fields']['active_checklists_count_c'] = array(
    'name' => 'active_checklists_count_c',
    'vname' => 'LBL_ACTIVE_CHECKLISTS_COUNT',
    'type' => 'int',
    'len' => 11,
    'comment' => 'Number of active checklists for this deal',
    'required' => false,
    'reportable' => true,
    'audited' => false,
    'importable' => false,
    'duplicate_merge' => 'disabled',
    'massupdate' => false,
    'calculated' => true,
    'source' => 'non-db',
    'studio' => 'visible',
);

// Add field for overdue checklist items count
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
    'calculated' => true,
    'source' => 'non-db',
    'studio' => 'visible',
);