<?php
/**
 * Simple Pipeline View for testing
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('include/MVC/View/SugarView.php');

class DealsViewPipeline_simple extends SugarView
{
    public function display()
    {
        global $db, $current_user;
        
        echo '<div style="padding: 20px;">';
        echo '<h2>Deals Pipeline View (Simple)</h2>';
        
        // Define stages
        $stages = array(
            'sourcing' => 'Sourcing',
            'screening' => 'Screening',
            'analysis_outreach' => 'Analysis & Outreach',
            'due_diligence' => 'Due Diligence',
            'closing' => 'Closing'
        );
        
        echo '<div style="display: flex; gap: 10px; overflow-x: auto;">';
        
        foreach ($stages as $stage_key => $stage_name) {
            echo '<div style="border: 1px solid #ccc; padding: 10px; min-width: 200px; background: #f5f5f5;">';
            echo '<h3>' . $stage_name . '</h3>';
            
            // Get deals for this stage
            $query = "SELECT id, name, amount FROM opportunities 
                     WHERE deleted = 0 
                     AND pipeline_stage_c = '" . $db->quote($stage_key) . "'
                     LIMIT 5";
            
            $result = $db->query($query);
            
            echo '<div style="min-height: 100px;">';
            while ($row = $db->fetchByAssoc($result)) {
                echo '<div style="background: white; margin: 5px 0; padding: 5px; border: 1px solid #ddd;">';
                echo '<strong>' . htmlspecialchars($row['name']) . '</strong><br>';
                echo 'Amount: $' . number_format($row['amount'], 2);
                echo '</div>';
            }
            echo '</div>';
            
            echo '</div>';
        }
        
        echo '</div>';
        
        // Add some test deals link
        echo '<hr>';
        echo '<p><a href="index.php?module=Deals&action=EditView&return_module=Deals&return_action=pipeline">Create New Deal</a></p>';
        
        echo '</div>';
    }
}
?>