<?php
/**
 * Deal List View - Simple version without export functionality
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('modules/Opportunities/views/view.list.php');

class DealsViewList extends OpportunitiesViewList
{
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * Display the list view
     */
    public function display()
    {
        parent::display();
    }
}