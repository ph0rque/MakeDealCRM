<?php
/**
 * Checklist fields extension for Opportunities module (Deal base)
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

// Add checklist templates relationship field
$dictionary['Opportunity']['fields']['checklist_templates'] = array(
    'name' => 'checklist_templates',
    'type' => 'link',
    'relationship' => 'deals_checklist_templates',
    'source' => 'non-db',
    'vname' => 'LBL_CHECKLIST_TEMPLATES',
    'studio' => 'visible',
    'side' => 'left',
);

// Add checklist items relationship field
$dictionary['Opportunity']['fields']['checklist_items'] = array(
    'name' => 'checklist_items',
    'type' => 'link',
    'relationship' => 'deals_checklist_items',
    'source' => 'non-db',
    'vname' => 'LBL_CHECKLIST_ITEMS',
    'studio' => 'visible',
    'side' => 'left',
);

// Add computed field for checklist completion percentage
$dictionary['Opportunity']['fields']['checklist_completion_c'] = array(
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