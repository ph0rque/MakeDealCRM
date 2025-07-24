<?php
/**
 * Pipeline Bean Class (minimal implementation for module registration)
 */

require_once('data/SugarBean.php');

class Pipeline extends SugarBean
{
    public $module_name = 'Pipelines';
    public $object_name = 'Pipeline';
    public $module_dir = 'Pipelines';
    public $table_name = 'pipelines'; // Dummy table name
    
    public function __construct()
    {
        parent::__construct();
        // This is a view-only module, no actual bean operations
    }
}