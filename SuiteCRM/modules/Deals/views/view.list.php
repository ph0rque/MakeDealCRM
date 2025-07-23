<?php
/**
 * Deals module List View
 * Custom list view with enhanced filtering and bulk actions
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('include/MVC/View/views/view.list.php');

class DealsViewList extends ViewList
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Display the list view with custom enhancements
     */
    public function display()
    {
        global $mod_strings, $app_strings;
        
        // Add custom CSS
        echo '<link rel="stylesheet" type="text/css" href="modules/Deals/tpls/deals.css">';
        echo '<script type="text/javascript" src="modules/Deals/javascript/DealsListView.js"></script>';
        
        // Add summary statistics
        $this->displaySummaryStats();
        
        parent::display();
        
        // Add custom bulk actions
        $this->addCustomBulkActions();
    }

    /**
     * Display summary statistics at the top of the list
     */
    protected function displaySummaryStats()
    {
        global $db, $current_user;
        
        // Get summary data
        $stats = $this->getDealsStatistics();
        
        echo '<div class="deals-summary-stats">';
        echo '<h3>' . $GLOBALS['mod_strings']['LBL_DEALS_SUMMARY'] . '</h3>';
        echo '<div class="stats-grid">';
        
        // Total deals
        echo '<div class="stat-item">';
        echo '<div class="stat-value">' . $stats['total_count'] . '</div>';
        echo '<div class="stat-label">' . $GLOBALS['mod_strings']['LBL_TOTAL_DEALS'] . '</div>';
        echo '</div>';
        
        // Total value
        echo '<div class="stat-item">';
        echo '<div class="stat-value">$' . number_format($stats['total_amount'], 2) . '</div>';
        echo '<div class="stat-label">' . $GLOBALS['mod_strings']['LBL_TOTAL_VALUE'] . '</div>';
        echo '</div>';
        
        // Average deal size
        echo '<div class="stat-item">';
        echo '<div class="stat-value">$' . number_format($stats['avg_amount'], 2) . '</div>';
        echo '<div class="stat-label">' . $GLOBALS['mod_strings']['LBL_AVG_DEAL_SIZE'] . '</div>';
        echo '</div>';
        
        // Win rate
        echo '<div class="stat-item">';
        echo '<div class="stat-value">' . $stats['win_rate'] . '%</div>';
        echo '<div class="stat-label">' . $GLOBALS['mod_strings']['LBL_WIN_RATE'] . '</div>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Get deals statistics
     */
    protected function getDealsStatistics()
    {
        global $db, $current_user;
        
        $stats = array(
            'total_count' => 0,
            'total_amount' => 0,
            'avg_amount' => 0,
            'win_rate' => 0
        );
        
        // Build where clause based on current list view filters
        $where = $this->where;
        if (empty($where)) {
            $where = "deals.deleted = 0";
        }
        
        // Get total count and sum
        $query = "SELECT COUNT(*) as total_count, 
                         SUM(amount) as total_amount,
                         AVG(amount) as avg_amount
                  FROM deals 
                  WHERE $where";
        
        $result = $db->query($query);
        if ($row = $db->fetchByAssoc($result)) {
            $stats['total_count'] = $row['total_count'];
            $stats['total_amount'] = $row['total_amount'] ?: 0;
            $stats['avg_amount'] = $row['avg_amount'] ?: 0;
        }
        
        // Calculate win rate
        $winQuery = "SELECT 
                        SUM(CASE WHEN sales_stage = 'Closed Won' THEN 1 ELSE 0 END) as won,
                        SUM(CASE WHEN sales_stage IN ('Closed Won', 'Closed Lost') THEN 1 ELSE 0 END) as closed
                     FROM deals 
                     WHERE $where";
        
        $result = $db->query($winQuery);
        if ($row = $db->fetchByAssoc($result)) {
            if ($row['closed'] > 0) {
                $stats['win_rate'] = round(($row['won'] / $row['closed']) * 100, 1);
            }
        }
        
        return $stats;
    }

    /**
     * Add custom bulk actions
     */
    protected function addCustomBulkActions()
    {
        echo '<script type="text/javascript">
            $(document).ready(function() {
                // Add custom bulk action buttons
                var bulkActionDropdown = $("#actionLinkTop .sugar_action_button ul.subnav");
                
                // Mass Update Stage
                bulkActionDropdown.append(\'<li><a href="javascript:void(0)" onclick="DealsListView.massUpdateStage()">Mass Update Stage</a></li>\');
                
                // Mass Assign
                bulkActionDropdown.append(\'<li><a href="javascript:void(0)" onclick="DealsListView.massAssign()">Mass Assign</a></li>\');
                
                // Export to Excel
                bulkActionDropdown.append(\'<li><a href="javascript:void(0)" onclick="DealsListView.exportToExcel()">Export to Excel</a></li>\');
                
                // Generate Report
                bulkActionDropdown.append(\'<li><a href="javascript:void(0)" onclick="DealsListView.generateReport()">Generate Report</a></li>\');
            });
        </script>';
    }

    /**
     * Pre-display setup
     */
    public function preDisplay()
    {
        parent::preDisplay();
        
        // Add custom list view columns
        if (!isset($this->lv->displayColumns['weighted_amount'])) {
            $this->lv->displayColumns['weighted_amount'] = array(
                'width' => '10%',
                'label' => 'LBL_WEIGHTED_AMOUNT',
                'default' => true,
                'name' => 'weighted_amount',
                'sort' => false
            );
        }
    }
}