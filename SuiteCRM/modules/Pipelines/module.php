<?php
/**
 * Pipelines Module Definition
 */

$module_name = 'Pipelines';

require_once 'modules/DynamicFields/FieldCases.php';

$GLOBALS['dictionary'][$module_name] = array(
    'table' => 'pipelines',
    'audited' => false,
    'unified_search' => false,
    'full_text_search' => false,
    'unified_search_default_enabled' => false,
    'duplicate_merge' => false,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'type' => 'id',
            'required' => true,
        ),
        'name' => array(
            'name' => 'name',
            'type' => 'varchar',
            'len' => 255,
            'required' => true,
        ),
        'description' => array(
            'name' => 'description',
            'type' => 'text',
        ),
        'date_entered' => array(
            'name' => 'date_entered',
            'type' => 'datetime',
        ),
        'date_modified' => array(
            'name' => 'date_modified',
            'type' => 'datetime',
        ),
        'created_by' => array(
            'name' => 'created_by',
            'type' => 'assigned_user_name',
            'table' => 'users',
            'isnull' => 'false',
            'dbType' => 'id',
        ),
        'modified_user_id' => array(
            'name' => 'modified_user_id',
            'type' => 'assigned_user_name',
            'table' => 'users',
            'isnull' => 'false',
            'dbType' => 'id',
        ),
        'deleted' => array(
            'name' => 'deleted',
            'type' => 'bool',
            'default' => '0',
        ),
    ),
    'indices' => array(
        array(
            'name' => 'idx_pipelines_name',
            'type' => 'index',
            'fields' => array('name'),
        ),
    ),
);

VardefManager::createVardef($module_name, 'Pipeline');
?>