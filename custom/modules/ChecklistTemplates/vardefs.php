<?php
/**
 * Variable Definitions for ChecklistTemplates Module
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

$dictionary['ChecklistTemplate'] = array(
    'table' => 'checklist_templates',
    'audited' => true,
    'duplicate_merge' => true,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'vname' => 'LBL_ID',
            'type' => 'id',
            'required' => true,
            'reportable' => true,
        ),
        'name' => array(
            'name' => 'name',
            'vname' => 'LBL_NAME',
            'type' => 'name',
            'dbType' => 'varchar',
            'len' => '255',
            'unified_search' => true,
            'required' => true,
            'importable' => 'required',
        ),
        'date_entered' => array(
            'name' => 'date_entered',
            'vname' => 'LBL_DATE_ENTERED',
            'type' => 'datetime',
            'enable_range_search' => true,
        ),
        'date_modified' => array(
            'name' => 'date_modified',
            'vname' => 'LBL_DATE_MODIFIED',
            'type' => 'datetime',
            'enable_range_search' => true,
        ),
        'modified_user_id' => array(
            'name' => 'modified_user_id',
            'rname' => 'user_name',
            'id_name' => 'modified_user_id',
            'vname' => 'LBL_MODIFIED',
            'type' => 'assigned_user_name',
            'table' => 'users',
            'reportable' => true,
            'dbType' => 'id',
        ),
        'created_by' => array(
            'name' => 'created_by',
            'rname' => 'user_name',
            'id_name' => 'modified_user_id',
            'vname' => 'LBL_CREATED',
            'type' => 'assigned_user_name',
            'table' => 'users',
            'reportable' => true,
            'dbType' => 'id',
        ),
        'description' => array(
            'name' => 'description',
            'vname' => 'LBL_DESCRIPTION',
            'type' => 'text',
            'rows' => '4',
            'cols' => '60',
        ),
        'deleted' => array(
            'name' => 'deleted',
            'vname' => 'LBL_DELETED',
            'type' => 'bool',
            'default' => '0',
            'reportable' => false,
        ),
        'assigned_user_id' => array(
            'name' => 'assigned_user_id',
            'rname' => 'user_name',
            'id_name' => 'assigned_user_id',
            'vname' => 'LBL_ASSIGNED_TO',
            'type' => 'assigned_user_name',
            'table' => 'users',
            'reportable' => true,
            'dbType' => 'id',
        ),
        
        // Custom fields
        'category' => array(
            'name' => 'category',
            'vname' => 'LBL_CATEGORY',
            'type' => 'enum',
            'options' => 'checklist_category_list',
            'len' => '100',
            'default' => 'general',
            'required' => true,
            'massupdate' => true,
        ),
        'is_active' => array(
            'name' => 'is_active',
            'vname' => 'LBL_IS_ACTIVE',
            'type' => 'bool',
            'default' => '1',
            'required' => false,
            'massupdate' => true,
        ),
        'is_public' => array(
            'name' => 'is_public',
            'vname' => 'LBL_IS_PUBLIC',
            'type' => 'bool',
            'default' => '0',
            'required' => false,
            'massupdate' => true,
            'help' => 'Public templates can be used by all users',
        ),
        'version' => array(
            'name' => 'version',
            'vname' => 'LBL_VERSION',
            'type' => 'int',
            'default' => '1',
            'required' => false,
            'readonly' => true,
        ),
        'template_data' => array(
            'name' => 'template_data',
            'vname' => 'LBL_TEMPLATE_DATA',
            'type' => 'text',
            'dbType' => 'text',
            'comment' => 'JSON data for template configuration',
        ),
        'item_count' => array(
            'name' => 'item_count',
            'vname' => 'LBL_ITEM_COUNT',
            'type' => 'int',
            'default' => '0',
            'required' => false,
            'readonly' => true,
        ),
        
        // Relationships
        'checklist_items' => array(
            'name' => 'checklist_items',
            'type' => 'link',
            'relationship' => 'template_checklist_items',
            'module' => 'ChecklistItems',
            'bean_name' => 'ChecklistItem',
            'source' => 'non-db',
            'vname' => 'LBL_CHECKLIST_ITEMS',
        ),
        'deals' => array(
            'name' => 'deals',
            'type' => 'link',
            'relationship' => 'deals_checklist_templates',
            'module' => 'Deals',
            'bean_name' => 'Deal',
            'source' => 'non-db',
            'vname' => 'LBL_DEALS',
        ),
    ),
    
    'relationships' => array(
        'template_checklist_items' => array(
            'lhs_module' => 'ChecklistTemplates',
            'lhs_table' => 'checklist_templates',
            'lhs_key' => 'id',
            'rhs_module' => 'ChecklistItems',
            'rhs_table' => 'checklist_items',
            'rhs_key' => 'template_id',
            'relationship_type' => 'one-to-many',
        ),
    ),
    
    'indices' => array(
        array('name' => 'idx_checklist_templates_pk', 'type' => 'primary', 'fields' => array('id')),
        array('name' => 'idx_checklist_templates_name', 'type' => 'index', 'fields' => array('name')),
        array('name' => 'idx_checklist_templates_category', 'type' => 'index', 'fields' => array('category')),
        array('name' => 'idx_checklist_templates_active', 'type' => 'index', 'fields' => array('is_active', 'deleted')),
        array('name' => 'idx_checklist_templates_public', 'type' => 'index', 'fields' => array('is_public', 'deleted')),
        array('name' => 'idx_checklist_templates_assigned', 'type' => 'index', 'fields' => array('assigned_user_id')),
    ),
);