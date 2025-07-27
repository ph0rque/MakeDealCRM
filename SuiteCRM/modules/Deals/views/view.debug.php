<?php
/**
 * Debug view to test basic functionality
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('include/MVC/View/SugarView.php');

class DealsViewDebug extends SugarView
{
    public function display()
    {
        echo '<div style="padding: 20px;">';
        echo '<h2>Deals Module Debug View</h2>';
        echo '<p>This is a test view to verify the module is working.</p>';
        
        // Show some debug info
        echo '<h3>Debug Information:</h3>';
        echo '<ul>';
        echo '<li>Module: ' . $this->module . '</li>';
        echo '<li>Action: ' . $this->action . '</li>';
        echo '<li>View: ' . $this->view . '</li>';
        echo '<li>Current User: ' . $GLOBALS['current_user']->user_name . '</li>';
        echo '</ul>';
        
        // Test Deal bean
        echo '<h3>Deal Bean Test:</h3>';
        try {
            $deal = BeanFactory::newBean('Deals');
            if ($deal) {
                echo '<p style="color: green;">✓ Deal bean created successfully</p>';
                echo '<p>Table: ' . $deal->table_name . '</p>';
                echo '<p>Module: ' . $deal->module_dir . '</p>';
            } else {
                echo '<p style="color: red;">✗ Failed to create Deal bean</p>';
            }
        } catch (Exception $e) {
            echo '<p style="color: red;">✗ Error: ' . $e->getMessage() . '</p>';
        }
        
        // Links to other views
        echo '<h3>Navigation:</h3>';
        echo '<ul>';
        echo '<li><a href="index.php?module=Deals&action=index">Index View</a></li>';
        echo '<li><a href="index.php?module=Deals&action=listview">List View</a></li>';
        echo '<li><a href="index.php?module=Deals&action=pipeline">Pipeline View</a></li>';
        echo '</ul>';
        
        echo '</div>';
    }
}
?>