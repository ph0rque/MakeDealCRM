<?php
/**
 * Relationship metadata for deals_checklist_templates
 * Many-to-many relationship between Deals and Checklist Templates
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

$dictionary['deals_checklist_templates'] = array(
    'table' => 'deals_checklist_templates',
    'fields' => array(
        array('name' => 'id', 'type' => 'varchar', 'len' => '36'),
        array('name' => 'deal_id', 'type' => 'varchar', 'len' => '36'),
        array('name' => 'template_id', 'type' => 'varchar', 'len' => '36'),
        array('name' => 'applied_date', 'type' => 'datetime'),
        array('name' => 'completion_percentage', 'type' => 'decimal', 'precision' => 5, 'scale' => 2, 'default' => '0.00'),
        array('name' => 'status', 'type' => 'enum', 'options' => 'checklist_status_list', 'default' => 'active'),
        array('name' => 'due_date', 'type' => 'date'),
        array('name' => 'assigned_user_id', 'type' => 'varchar', 'len' => '36'),
        array('name' => 'date_modified', 'type' => 'datetime'),
        array('name' => 'date_entered', 'type' => 'datetime'),
        array('name' => 'modified_user_id', 'type' => 'varchar', 'len' => '36'),
        array('name' => 'created_by', 'type' => 'varchar', 'len' => '36'),
        array('name' => 'deleted', 'type' => 'bool', 'len' => '1', 'required' => false, 'default' => '0')
    ),
    'indices' => array(
        array('name' => 'deals_checklist_templatespk', 'type' => 'primary', 'fields' => array('id')),
        array('name' => 'idx_deal_template', 'type' => 'alternate_key', 'fields' => array('deal_id', 'template_id')),
        array('name' => 'idx_deal_id_del', 'type' => 'index', 'fields' => array('deal_id', 'deleted')),
        array('name' => 'idx_template_id_del', 'type' => 'index', 'fields' => array('template_id', 'deleted')),
        array('name' => 'idx_completion_status', 'type' => 'index', 'fields' => array('completion_percentage', 'status'))
    ),
    'relationships' => array(
        'deals_checklist_templates' => array(
            'lhs_module' => 'Deals', 
            'lhs_table' => 'opportunities', 
            'lhs_key' => 'id',
            'rhs_module' => 'ChecklistTemplates', 
            'rhs_table' => 'checklist_templates', 
            'rhs_key' => 'id',
            'relationship_type' => 'many-to-many',
            'join_table' => 'deals_checklist_templates', 
            'join_key_lhs' => 'deal_id', 
            'join_key_rhs' => 'template_id'
        )
    )
);