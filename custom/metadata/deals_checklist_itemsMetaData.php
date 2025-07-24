<?php
/**
 * Relationship metadata for deals_checklist_items
 * One-to-many relationship between Deals and Checklist Items
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

$dictionary['deals_checklist_items'] = array(
    'table' => 'deals_checklist_items',
    'fields' => array(
        array('name' => 'id', 'type' => 'varchar', 'len' => '36'),
        array('name' => 'deal_id', 'type' => 'varchar', 'len' => '36'),
        array('name' => 'item_id', 'type' => 'varchar', 'len' => '36'),
        array('name' => 'template_instance_id', 'type' => 'varchar', 'len' => '36'),
        array('name' => 'completion_status', 'type' => 'enum', 'options' => 'checklist_item_status_list', 'default' => 'pending'),
        array('name' => 'completion_date', 'type' => 'datetime'),
        array('name' => 'due_date', 'type' => 'date'),
        array('name' => 'priority', 'type' => 'enum', 'options' => 'checklist_priority_list', 'default' => 'medium'),
        array('name' => 'notes', 'type' => 'text'),
        array('name' => 'document_requested', 'type' => 'bool', 'len' => '1', 'default' => '0'),
        array('name' => 'document_received', 'type' => 'bool', 'len' => '1', 'default' => '0'),
        array('name' => 'assigned_user_id', 'type' => 'varchar', 'len' => '36'),
        array('name' => 'date_modified', 'type' => 'datetime'),
        array('name' => 'date_entered', 'type' => 'datetime'),
        array('name' => 'modified_user_id', 'type' => 'varchar', 'len' => '36'),
        array('name' => 'created_by', 'type' => 'varchar', 'len' => '36'),
        array('name' => 'deleted', 'type' => 'bool', 'len' => '1', 'required' => false, 'default' => '0')
    ),
    'indices' => array(
        array('name' => 'deals_checklist_itemspk', 'type' => 'primary', 'fields' => array('id')),
        array('name' => 'idx_deal_item', 'type' => 'alternate_key', 'fields' => array('deal_id', 'item_id')),
        array('name' => 'idx_deal_status', 'type' => 'index', 'fields' => array('deal_id', 'completion_status', 'deleted')),
        array('name' => 'idx_template_instance', 'type' => 'index', 'fields' => array('template_instance_id', 'deleted')),
        array('name' => 'idx_due_date_priority', 'type' => 'index', 'fields' => array('due_date', 'priority'))
    ),
    'relationships' => array(
        'deals_checklist_items' => array(
            'lhs_module' => 'Deals', 
            'lhs_table' => 'opportunities', 
            'lhs_key' => 'id',
            'rhs_module' => 'ChecklistItems', 
            'rhs_table' => 'checklist_items', 
            'rhs_key' => 'id',
            'relationship_type' => 'one-to-many',
            'join_table' => 'deals_checklist_items', 
            'join_key_lhs' => 'deal_id', 
            'join_key_rhs' => 'item_id'
        )
    )
);