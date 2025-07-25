<?php
/**
 * Simple Deals Edit View
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('include/MVC/View/views/view.edit.php');

class DealsViewEdit extends ViewEdit
{
    public function __construct()
    {
        parent::__construct();
    }

    public function display()
    {
        // Just call parent display without any custom additions
        parent::display();
    }
}
?>