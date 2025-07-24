<?php
/**
 * Controller for mdeal_Deals Module
 */

require_once('include/MVC/Controller/SugarController.php');

class mdeal_DealsController extends SugarController
{
    public function action_pipeline()
    {
        $this->view = 'pipeline';
    }
}