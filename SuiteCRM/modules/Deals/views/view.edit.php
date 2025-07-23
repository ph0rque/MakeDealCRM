<?php
/**
 * Deals module Edit View
 * Includes custom duplicate checking functionality
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('include/MVC/View/views/view.edit.php');

class DealsViewEdit extends ViewEdit
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Display the edit view with duplicate checking
     */
    public function display()
    {
        global $mod_strings, $app_strings, $sugar_config;
        
        // Add custom JavaScript for duplicate checking
        echo '<script type="text/javascript" src="modules/Deals/javascript/DealsEditView.js"></script>';
        echo '<link rel="stylesheet" type="text/css" href="modules/Deals/tpls/deals.css">';
        
        // Add duplicate check container
        echo '<div id="duplicateCheckResults" style="display:none;" class="duplicate-check-container">
                <h3>' . $mod_strings['LBL_DUPLICATE_CHECK_TITLE'] . '</h3>
                <div id="duplicatesList"></div>
                <div class="duplicate-actions">
                    <button type="button" id="continueWithDuplicate" class="button">' . $mod_strings['LBL_CONTINUE_WITH_DUPLICATE'] . '</button>
                    <button type="button" id="cancelDuplicate" class="button">' . $mod_strings['LBL_CANCEL'] . '</button>
                </div>
              </div>';
        
        parent::display();
        
        // Add duplicate check trigger
        echo '<script type="text/javascript">
            $(document).ready(function() {
                DealsEditView.init();
            });
        </script>';
    }

    /**
     * Setup the view
     */
    public function preDisplay()
    {
        parent::preDisplay();
        
        // Add custom metadata for duplicate fields
        $this->ev->defs['panels']['default'][] = array(
            array(
                'name' => 'duplicate_check_fields',
                'type' => 'hidden',
                'value' => 'name,account_name,amount,email1',
            ),
        );
    }
}