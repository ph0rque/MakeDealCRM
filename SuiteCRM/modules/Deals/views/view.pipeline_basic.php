<?php
/**
 * Basic Pipeline View - Minimal version without advanced features
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('include/MVC/View/SugarView.php');

class DealsViewPipeline_basic extends SugarView
{
    private $stages = array(
        'sourcing' => 'Sourcing',
        'screening' => 'Screening',
        'analysis_outreach' => 'Analysis & Outreach',
        'due_diligence' => 'Due Diligence',
        'valuation_structuring' => 'Valuation & Structuring',
        'loi_negotiation' => 'LOI / Negotiation',
        'financing' => 'Financing',
        'closing' => 'Closing'
    );

    public function display()
    {
        global $db, $current_user, $mod_strings;
        
        // Start output
        echo '<div id="pipeline-container" style="padding: 20px;">';
        echo '<h2>Deals Pipeline</h2>';
        
        // Add basic CSS
        echo '<style>
            .pipeline-board { display: flex; gap: 15px; overflow-x: auto; padding: 20px 0; }
            .pipeline-column { 
                min-width: 300px; 
                background: #f5f5f5; 
                border: 1px solid #ddd; 
                border-radius: 5px; 
                padding: 10px;
            }
            .pipeline-header { 
                font-weight: bold; 
                margin-bottom: 10px; 
                padding: 10px;
                background: #e0e0e0;
                border-radius: 3px;
            }
            .deal-card { 
                background: white; 
                border: 1px solid #ccc; 
                border-radius: 3px; 
                padding: 10px; 
                margin-bottom: 10px;
                cursor: pointer;
            }
            .deal-card:hover { 
                background: #f9f9f9; 
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            .deal-title { font-weight: bold; color: #333; }
            .deal-amount { color: #2a6e2a; margin-top: 5px; }
            .deal-account { color: #666; font-size: 0.9em; }
            .empty-stage { color: #999; font-style: italic; padding: 20px; text-align: center; }
        </style>';
        
        // Get deals grouped by stage
        echo '<div class="pipeline-board">';
        
        foreach ($this->stages as $stage_key => $stage_name) {
            echo '<div class="pipeline-column" data-stage="' . htmlspecialchars($stage_key) . '">';
            echo '<div class="pipeline-header">' . htmlspecialchars($stage_name) . '</div>';
            echo '<div class="pipeline-deals" id="stage-' . htmlspecialchars($stage_key) . '">';
            
            // Query deals for this stage
            $query = "SELECT 
                        o.id, 
                        o.name, 
                        o.amount, 
                        a.name as account_name,
                        o.assigned_user_id,
                        o.date_modified,
                        oc.pipeline_stage_c
                      FROM opportunities o
                      LEFT JOIN opportunities_cstm oc ON o.id = oc.id_c
                      LEFT JOIN accounts_opportunities ao ON o.id = ao.opportunity_id AND ao.deleted = 0
                      LEFT JOIN accounts a ON ao.account_id = a.id AND a.deleted = 0
                      WHERE o.deleted = 0 
                      AND (oc.pipeline_stage_c = '" . $db->quote($stage_key) . "' 
                           OR (oc.pipeline_stage_c IS NULL AND '" . $db->quote($stage_key) . "' = 'sourcing'))
                      ORDER BY o.date_modified DESC
                      LIMIT 20";
            
            $result = $db->query($query);
            $has_deals = false;
            
            while ($row = $db->fetchByAssoc($result)) {
                $has_deals = true;
                echo '<div class="deal-card" data-id="' . htmlspecialchars($row['id']) . '" onclick="window.location.href=\'index.php?module=Deals&action=DetailView&record=' . htmlspecialchars($row['id']) . '\'">';
                echo '<div class="deal-title">' . htmlspecialchars($row['name']) . '</div>';
                if ($row['account_name']) {
                    echo '<div class="deal-account">' . htmlspecialchars($row['account_name']) . '</div>';
                }
                if ($row['amount']) {
                    echo '<div class="deal-amount">$' . number_format($row['amount'], 2) . '</div>';
                }
                echo '</div>';
            }
            
            if (!$has_deals) {
                echo '<div class="empty-stage">No deals in this stage</div>';
            }
            
            echo '</div>'; // pipeline-deals
            echo '</div>'; // pipeline-column
        }
        
        echo '</div>'; // pipeline-board
        
        // Add create button
        echo '<div style="margin-top: 20px;">';
        echo '<a href="index.php?module=Deals&action=EditView&return_module=Deals&return_action=pipeline_basic" class="button primary">Create New Deal</a>';
        echo '</div>';
        
        echo '</div>'; // pipeline-container
    }
}
?>