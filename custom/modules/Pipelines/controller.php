<?php
/**
 * Pipelines Module Controller
 */

require_once('include/MVC/Controller/SugarController.php');

class PipelinesController extends SugarController
{
    public function action_kanbanview()
    {
        $this->view = 'kanban';
    }
    
    public function action_kanban() 
    {
        $this->view = 'kanban';
    }
}