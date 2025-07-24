<?php
/**
 * Extension to add Focus Flag field to Opportunities module
 * This allows users to mark high-priority deals and reorder them within stages
 */

// Focus Flag field
$dictionary['Opportunity']['fields']['focus_flag_c'] = array(
    'name' => 'focus_flag_c',
    'vname' => 'LBL_FOCUS_FLAG',
    'type' => 'bool',
    'default' => '0',
    'comment' => 'Flag to mark high-priority/focused deals',
    'required' => false,
    'reportable' => true,
    'audited' => true,
    'importable' => true,
    'duplicate_merge' => 'enabled',
    'massupdate' => true,
);

// Focus Order field (for ordering focused items within a stage)
$dictionary['Opportunity']['fields']['focus_order_c'] = array(
    'name' => 'focus_order_c',
    'vname' => 'LBL_FOCUS_ORDER',
    'type' => 'int',
    'len' => 11,
    'default' => '0',
    'comment' => 'Order of focused deals within a stage',
    'required' => false,
    'reportable' => false,
    'audited' => false,
    'importable' => false,
    'duplicate_merge' => 'disabled',
    'massupdate' => false,
);

// Focus Date field (when the deal was marked as focused)
$dictionary['Opportunity']['fields']['focus_date_c'] = array(
    'name' => 'focus_date_c',
    'vname' => 'LBL_FOCUS_DATE',
    'type' => 'datetime',
    'comment' => 'Date when the deal was marked as focused',
    'required' => false,
    'reportable' => true,
    'audited' => true,
    'importable' => false,
    'duplicate_merge' => 'disabled',
    'massupdate' => false,
);