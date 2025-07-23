<?php
/**
 * Deals module Detail View
 * Custom layout with stage progress and quick actions
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('include/MVC/View/views/view.detail.php');

class DealsViewDetail extends ViewDetail
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Display the detail view with custom enhancements
     */
    public function display()
    {
        global $mod_strings, $app_strings;
        
        // Add custom CSS and JavaScript
        echo '<link rel="stylesheet" type="text/css" href="modules/Deals/tpls/deals.css">';
        echo '<script type="text/javascript" src="modules/Deals/javascript/DealsDetailView.js"></script>';
        
        // Add stage progress visualization
        $this->displayStageProgress();
        
        // Add quick actions panel
        $this->displayQuickActions();
        
        parent::display();
        
        // Add activity timeline
        $this->displayActivityTimeline();
    }

    /**
     * Display sales stage progress bar
     */
    protected function displayStageProgress()
    {
        $stages = array(
            'Prospecting' => 10,
            'Qualification' => 20,
            'Needs Analysis' => 30,
            'Value Proposition' => 40,
            'Id. Decision Makers' => 50,
            'Perception Analysis' => 60,
            'Proposal/Price Quote' => 70,
            'Negotiation/Review' => 80,
            'Closed Won' => 100,
            'Closed Lost' => 0
        );
        
        $currentStage = $this->bean->sales_stage;
        $progress = isset($stages[$currentStage]) ? $stages[$currentStage] : 0;
        
        echo '<div class="stage-progress-container">';
        echo '<h4>' . $GLOBALS['mod_strings']['LBL_SALES_STAGE_PROGRESS'] . '</h4>';
        echo '<div class="stage-progress-bar">';
        echo '<div class="stage-progress-fill" style="width: ' . $progress . '%;">';
        echo $currentStage . ' (' . $progress . '%)';
        echo '</div>';
        echo '</div>';
        echo '<div class="stage-info">';
        echo '<small>Deal Amount: ' . $this->bean->amount . ' | Probability: ' . $this->bean->probability . '%</small>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Display quick actions panel
     */
    protected function displayQuickActions()
    {
        global $mod_strings, $current_user;
        
        echo '<div class="quick-actions-panel">';
        echo '<h4>' . $mod_strings['LBL_QUICK_ACTIONS'] . '</h4>';
        
        // Log Call button
        echo '<button type="button" class="button quick-action-button" onclick="DealsDetailView.logCall(\'' . $this->bean->id . '\');">';
        echo '<img src="themes/default/images/CreateCalls.gif" border="0" align="absmiddle"> ' . $mod_strings['LBL_LOG_CALL'];
        echo '</button>';
        
        // Schedule Meeting button
        echo '<button type="button" class="button quick-action-button" onclick="DealsDetailView.scheduleMeeting(\'' . $this->bean->id . '\');">';
        echo '<img src="themes/default/images/CreateMeetings.gif" border="0" align="absmiddle"> ' . $mod_strings['LBL_SCHEDULE_MEETING'];
        echo '</button>';
        
        // Create Task button
        echo '<button type="button" class="button quick-action-button" onclick="DealsDetailView.createTask(\'' . $this->bean->id . '\');">';
        echo '<img src="themes/default/images/CreateTasks.gif" border="0" align="absmiddle"> ' . $mod_strings['LBL_CREATE_TASK'];
        echo '</button>';
        
        // Send Email button
        echo '<button type="button" class="button quick-action-button" onclick="DealsDetailView.sendEmail(\'' . $this->bean->id . '\');">';
        echo '<img src="themes/default/images/CreateEmails.gif" border="0" align="absmiddle"> ' . $mod_strings['LBL_SEND_EMAIL'];
        echo '</button>';
        
        // Convert to Quote button (if in appropriate stage)
        if (!in_array($this->bean->sales_stage, array('Closed Won', 'Closed Lost'))) {
            echo '<button type="button" class="button quick-action-button" onclick="DealsDetailView.convertToQuote(\'' . $this->bean->id . '\');">';
            echo '<img src="themes/default/images/CreateQuotes.gif" border="0" align="absmiddle"> ' . $mod_strings['LBL_CONVERT_TO_QUOTE'];
            echo '</button>';
        }
        
        echo '</div>';
    }

    /**
     * Display activity timeline
     */
    protected function displayActivityTimeline()
    {
        echo '<div class="activity-timeline-container" id="activityTimeline">';
        echo '<h4>' . $GLOBALS['mod_strings']['LBL_ACTIVITY_TIMELINE'] . '</h4>';
        echo '<div class="activity-timeline" id="timelineContent">';
        echo '<div class="loading-indicator">Loading activities...</div>';
        echo '</div>';
        echo '</div>';
        
        // Initialize timeline
        echo '<script type="text/javascript">
            $(document).ready(function() {
                DealsDetailView.loadActivityTimeline("' . $this->bean->id . '");
            });
        </script>';
    }

    /**
     * Pre-display setup
     */
    public function preDisplay()
    {
        parent::preDisplay();
        
        // Add custom metadata panels if needed
        if (!isset($this->dv->defs['panels']['LBL_PANEL_ADVANCED'])) {
            $this->dv->defs['panels']['LBL_PANEL_ADVANCED'] = array(
                array(
                    array(
                        'name' => 'lead_source',
                        'label' => 'LBL_LEAD_SOURCE',
                    ),
                    array(
                        'name' => 'campaign_name',
                        'label' => 'LBL_CAMPAIGN',
                    ),
                ),
                array(
                    array(
                        'name' => 'created_by_name',
                        'label' => 'LBL_CREATED',
                    ),
                    array(
                        'name' => 'modified_by_name',
                        'label' => 'LBL_MODIFIED',
                    ),
                ),
            );
        }
    }
}