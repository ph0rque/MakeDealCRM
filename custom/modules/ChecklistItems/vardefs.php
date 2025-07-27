<?php
/**
 * Variable Definitions for ChecklistItems Module
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

$dictionary['ChecklistItem'] = array(
    'table' => 'checklist_items',
    'audited' => true,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'vname' => 'LBL_ID',
            'type' => 'id',
            'required' => true,
        ),
        'name' => array(
            'name' => 'name',
            'vname' => 'LBL_NAME',
            'type' => 'name',
            'dbType' => 'varchar',
            'len' => '255',
            'source' => 'non-db',
            'unified_search' => false,
        ),
        'date_entered' => array(
            'name' => 'date_entered',
            'vname' => 'LBL_DATE_ENTERED',
            'type' => 'datetime',
        ),
        'date_modified' => array(
            'name' => 'date_modified',
            'vname' => 'LBL_DATE_MODIFIED',
            'type' => 'datetime',
        ),
        'modified_user_id' => array(
            'name' => 'modified_user_id',
            'rname' => 'user_name',
            'id_name' => 'modified_user_id',
            'vname' => 'LBL_MODIFIED',
            'type' => 'assigned_user_name',
            'table' => 'users',
            'dbType' => 'id',
        ),
        'created_by' => array(
            'name' => 'created_by',
            'rname' => 'user_name',
            'id_name' => 'created_by',
            'vname' => 'LBL_CREATED',
            'type' => 'assigned_user_name',
            'table' => 'users',
            'dbType' => 'id',
        ),
        'description' => array(
            'name' => 'description',
            'vname' => 'LBL_DESCRIPTION',
            'type' => 'text',
        ),
        'deleted' => array(
            'name' => 'deleted',
            'vname' => 'LBL_DELETED',
            'type' => 'bool',
            'default' => '0',
        ),
        
        // Custom fields
        'template_id' => array(
            'name' => 'template_id',
            'vname' => 'LBL_TEMPLATE_ID',
            'type' => 'id',
            'required' => false,
        ),
        'checklist_id' => array(
            'name' => 'checklist_id',
            'vname' => 'LBL_CHECKLIST_ID',
            'type' => 'id',
            'required' => false,
        ),
        'title' => array(
            'name' => 'title',
            'vname' => 'LBL_TITLE',
            'type' => 'varchar',
            'len' => '500',
            'required' => true,
            'unified_search' => true,
        ),
        'type' => array(
            'name' => 'type',
            'vname' => 'LBL_TYPE',
            'type' => 'enum',
            'options' => 'checklist_item_type_list',
            'len' => '50',
            'default' => 'checkbox',
            'required' => true,
        ),
        'order_number' => array(
            'name' => 'order_number',
            'vname' => 'LBL_ORDER_NUMBER',
            'type' => 'int',
            'default' => '0',
            'required' => false,
        ),
        'is_required' => array(
            'name' => 'is_required',
            'vname' => 'LBL_IS_REQUIRED',
            'type' => 'bool',
            'default' => '0',
            'massupdate' => true,
        ),
        'due_days' => array(
            'name' => 'due_days',
            'vname' => 'LBL_DUE_DAYS',
            'type' => 'int',
            'comment' => 'Number of days from checklist start date',
        ),
        'due_date' => array(
            'name' => 'due_date',
            'vname' => 'LBL_DUE_DATE',
            'type' => 'date',
            'enable_range_search' => true,
        ),
        'status' => array(
            'name' => 'status',
            'vname' => 'LBL_STATUS',
            'type' => 'enum',
            'options' => 'checklist_item_status_list',
            'len' => '50',
            'default' => 'pending',
            'required' => true,
            'massupdate' => true,
        ),
        'completed_date' => array(
            'name' => 'completed_date',
            'vname' => 'LBL_COMPLETED_DATE',
            'type' => 'datetime',
        ),
        'completed_by' => array(
            'name' => 'completed_by',
            'rname' => 'user_name',
            'id_name' => 'completed_by',
            'vname' => 'LBL_COMPLETED_BY',
            'type' => 'relate',
            'table' => 'users',
            'module' => 'Users',
            'dbType' => 'id',
        ),
        'notes' => array(
            'name' => 'notes',
            'vname' => 'LBL_NOTES',
            'type' => 'text',
        ),
        'task_id' => array(
            'name' => 'task_id',
            'vname' => 'LBL_TASK_ID',
            'type' => 'id',
        ),
        'file_request_id' => array(
            'name' => 'file_request_id',
            'vname' => 'LBL_FILE_REQUEST_ID',
            'type' => 'id',
        ),
        
        // Relationships
        'template' => array(
            'name' => 'template',
            'type' => 'link',
            'relationship' => 'template_checklist_items',
            'module' => 'ChecklistTemplates',
            'bean_name' => 'ChecklistTemplate',
            'source' => 'non-db',
            'vname' => 'LBL_TEMPLATE',
        ),
        'checklist' => array(
            'name' => 'checklist',
            'type' => 'link',
            'relationship' => 'checklist_items_checklists',
            'module' => 'DealChecklists',
            'bean_name' => 'DealChecklist',
            'source' => 'non-db',
            'vname' => 'LBL_CHECKLIST',
        ),
        'task' => array(
            'name' => 'task',
            'type' => 'link',
            'relationship' => 'checklist_items_tasks',
            'module' => 'Tasks',
            'bean_name' => 'Task',
            'source' => 'non-db',
            'vname' => 'LBL_TASK',
        ),
    ),
    
    'relationships' => array(
        'checklist_items_checklists' => array(
            'lhs_module' => 'DealChecklists',
            'lhs_table' => 'deal_checklists',
            'lhs_key' => 'id',
            'rhs_module' => 'ChecklistItems',
            'rhs_table' => 'checklist_items',
            'rhs_key' => 'checklist_id',
            'relationship_type' => 'one-to-many',
        ),
        'checklist_items_tasks' => array(
            'lhs_module' => 'Tasks',
            'lhs_table' => 'tasks',
            'lhs_key' => 'id',
            'rhs_module' => 'ChecklistItems',
            'rhs_table' => 'checklist_items',
            'rhs_key' => 'task_id',
            'relationship_type' => 'one-to-one',
        ),
    ),
    
    'indices' => array(
        array('name' => 'idx_checklist_items_pk', 'type' => 'primary', 'fields' => array('id')),
        array('name' => 'idx_checklist_items_template', 'type' => 'index', 'fields' => array('template_id')),
        array('name' => 'idx_checklist_items_checklist', 'type' => 'index', 'fields' => array('checklist_id')),
        array('name' => 'idx_checklist_items_status', 'type' => 'index', 'fields' => array('status')),
        array('name' => 'idx_checklist_items_due_date', 'type' => 'index', 'fields' => array('due_date')),
        array('name' => 'idx_checklist_items_order', 'type' => 'index', 'fields' => array('checklist_id', 'order_number')),
    ),
);