<?php
/**
 * Pipeline Module Bean
 */

require_once('data/SugarBean.php');

class Pipeline extends SugarBean
{
    var $table_name = 'pipelines';
    var $object_name = 'Pipeline';
    var $module_dir = 'Pipelines';
    var $new_schema = true;
    
    var $id;
    var $name;
    var $description;
    var $date_entered;
    var $date_modified;
    var $created_by;
    var $modified_user_id;
    var $deleted;
    
    function Pipeline()
    {
        parent::SugarBean();
    }
    
    function bean_implements($interface)
    {
        switch($interface)
        {
            case 'ACL': return true;
        }
        return false;
    }
}
?>