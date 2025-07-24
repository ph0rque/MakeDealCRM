<?php
/**
 * Subpanel definitions for mdeal_Accounts module
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$layout_defs['mdeal_Accounts'] = array(
    // Standard relationships
    'subpanel_setup' => array(
        'mdeal_contacts' => array(
            'order' => 10,
            'module' => 'mdeal_Contacts',
            'subpanel_name' => 'default',
            'sort_order' => 'asc',
            'sort_by' => 'last_name',
            'title_key' => 'LBL_CONTACTS_SUBPANEL_TITLE',
            'get_subpanel_data' => 'mdeal_contacts',
            'top_buttons' => array(
                array('widget_class' => 'SubPanelTopCreateButton'),
                array('widget_class' => 'SubPanelTopSelectButton', 'popup_module' => 'mdeal_Contacts'),
            ),
        ),
        
        'mdeal_deals' => array(
            'order' => 20,
            'module' => 'mdeal_Deals',
            'subpanel_name' => 'default',
            'sort_order' => 'desc',
            'sort_by' => 'date_modified',
            'title_key' => 'LBL_DEALS_SUBPANEL_TITLE',
            'get_subpanel_data' => 'mdeal_deals',
            'top_buttons' => array(
                array('widget_class' => 'SubPanelTopCreateButton'),
                array('widget_class' => 'SubPanelTopSelectButton', 'popup_module' => 'mdeal_Deals'),
            ),
        ),

        'subsidiaries' => array(
            'order' => 30,
            'module' => 'mdeal_Accounts',
            'subpanel_name' => 'ForAccounts',
            'sort_order' => 'asc',
            'sort_by' => 'name',
            'title_key' => 'LBL_SUBSIDIARIES_SUBPANEL_TITLE',
            'get_subpanel_data' => 'function:get_subsidiary_accounts',
            'function_parameters' => array('import_function_file' => 'custom/modules/mdeal_Accounts/SubpanelHelpers.php'),
            'top_buttons' => array(
                array('widget_class' => 'SubPanelTopCreateButton'),
                array('widget_class' => 'SubPanelTopSelectButton', 'popup_module' => 'mdeal_Accounts'),
            ),
        ),

        'opportunities' => array(
            'order' => 40,
            'module' => 'Opportunities',
            'subpanel_name' => 'default',
            'sort_order' => 'desc',
            'sort_by' => 'date_modified',
            'title_key' => 'LBL_OPPORTUNITIES_SUBPANEL_TITLE',
            'get_subpanel_data' => 'opportunities',
            'top_buttons' => array(
                array('widget_class' => 'SubPanelTopCreateButton'),
                array('widget_class' => 'SubPanelTopSelectButton', 'popup_module' => 'Opportunities'),
            ),
        ),

        'cases' => array(
            'order' => 50,
            'module' => 'Cases',
            'subpanel_name' => 'default',
            'sort_order' => 'desc',
            'sort_by' => 'case_number',
            'title_key' => 'LBL_CASES_SUBPANEL_TITLE',
            'get_subpanel_data' => 'cases',
            'top_buttons' => array(
                array('widget_class' => 'SubPanelTopCreateButton'),
                array('widget_class' => 'SubPanelTopSelectButton', 'popup_module' => 'Cases'),
            ),
        ),

        'calls' => array(
            'order' => 60,
            'module' => 'Calls',
            'subpanel_name' => 'default',
            'sort_order' => 'desc',
            'sort_by' => 'date_start',
            'title_key' => 'LBL_CALLS_SUBPANEL_TITLE',
            'get_subpanel_data' => 'calls',
            'top_buttons' => array(
                array('widget_class' => 'SubPanelTopCreateButton'),
                array('widget_class' => 'SubPanelTopScheduleButton'),
            ),
        ),

        'meetings' => array(
            'order' => 70,
            'module' => 'Meetings',
            'subpanel_name' => 'default',
            'sort_order' => 'desc',
            'sort_by' => 'date_start',
            'title_key' => 'LBL_MEETINGS_SUBPANEL_TITLE',
            'get_subpanel_data' => 'meetings',
            'top_buttons' => array(
                array('widget_class' => 'SubPanelTopCreateButton'),
                array('widget_class' => 'SubPanelTopScheduleButton'),
            ),
        ),

        'tasks' => array(
            'order' => 80,
            'module' => 'Tasks',
            'subpanel_name' => 'default',
            'sort_order' => 'desc',
            'sort_by' => 'date_due',
            'title_key' => 'LBL_TASKS_SUBPANEL_TITLE',
            'get_subpanel_data' => 'tasks',
            'top_buttons' => array(
                array('widget_class' => 'SubPanelTopCreateButton'),
            ),
        ),

        'notes' => array(
            'order' => 90,
            'module' => 'Notes',
            'subpanel_name' => 'default',
            'sort_order' => 'desc',
            'sort_by' => 'date_modified',
            'title_key' => 'LBL_NOTES_SUBPANEL_TITLE',
            'get_subpanel_data' => 'notes',
            'top_buttons' => array(
                array('widget_class' => 'SubPanelTopCreateButton'),
            ),
        ),

        'emails' => array(
            'order' => 100,
            'module' => 'Emails',
            'subpanel_name' => 'default',
            'sort_order' => 'desc',
            'sort_by' => 'date_start',
            'title_key' => 'LBL_EMAILS_SUBPANEL_TITLE',
            'get_subpanel_data' => 'emails',
            'top_buttons' => array(
                array('widget_class' => 'SubPanelTopComposeEmailButton'),
            ),
        ),

        'documents' => array(
            'order' => 110,
            'module' => 'Documents',
            'subpanel_name' => 'default',
            'sort_order' => 'desc',
            'sort_by' => 'date_modified',
            'title_key' => 'LBL_DOCUMENTS_SUBPANEL_TITLE',
            'get_subpanel_data' => 'documents',
            'top_buttons' => array(
                array('widget_class' => 'SubPanelTopCreateButton'),
                array('widget_class' => 'SubPanelTopSelectButton', 'popup_module' => 'Documents'),
            ),
        ),

        'projects' => array(
            'order' => 120,
            'module' => 'Project',
            'subpanel_name' => 'default',
            'sort_order' => 'desc',
            'sort_by' => 'date_modified',
            'title_key' => 'LBL_PROJECTS_SUBPANEL_TITLE',
            'get_subpanel_data' => 'projects',
            'top_buttons' => array(
                array('widget_class' => 'SubPanelTopCreateButton'),
                array('widget_class' => 'SubPanelTopSelectButton', 'popup_module' => 'Project'),
            ),
        ),

        'campaigns' => array(
            'order' => 130,
            'module' => 'Campaigns',
            'subpanel_name' => 'default',
            'sort_order' => 'desc',
            'sort_by' => 'date_modified',
            'title_key' => 'LBL_CAMPAIGNS_SUBPANEL_TITLE',
            'get_subpanel_data' => 'campaigns',
            'top_buttons' => array(
                array('widget_class' => 'SubPanelTopSelectButton', 'popup_module' => 'Campaigns'),
            ),
        ),

        'prospects' => array(
            'order' => 140,
            'module' => 'Prospects',
            'subpanel_name' => 'default',
            'sort_order' => 'desc',
            'sort_by' => 'date_modified',
            'title_key' => 'LBL_PROSPECTS_SUBPANEL_TITLE',
            'get_subpanel_data' => 'prospects',
            'top_buttons' => array(
                array('widget_class' => 'SubPanelTopCreateButton'),
                array('widget_class' => 'SubPanelTopSelectButton', 'popup_module' => 'Prospects'),
            ),
        ),

        'mdeal_leads' => array(
            'order' => 150,
            'module' => 'mdeal_Leads',
            'subpanel_name' => 'default',
            'sort_order' => 'desc',
            'sort_by' => 'date_modified',
            'title_key' => 'LBL_LEADS_SUBPANEL_TITLE',
            'get_subpanel_data' => 'mdeal_leads',
            'top_buttons' => array(
                array('widget_class' => 'SubPanelTopCreateButton'),
                array('widget_class' => 'SubPanelTopSelectButton', 'popup_module' => 'mdeal_Leads'),
            ),
        ),

        'activities' => array(
            'order' => 160,
            'module' => 'Activities',
            'subpanel_name' => 'default',
            'sort_order' => 'desc',
            'sort_by' => 'date_due',
            'title_key' => 'LBL_ACTIVITIES_SUBPANEL_TITLE',
            'get_subpanel_data' => 'activities',
            'top_buttons' => array(
                array('widget_class' => 'SubPanelTopScheduleButton'),
                array('widget_class' => 'SubPanelTopCreateTaskButton'),
            ),
        ),

        'history' => array(
            'order' => 170,
            'module' => 'History',
            'subpanel_name' => 'default',
            'sort_order' => 'desc',
            'sort_by' => 'date_modified',
            'title_key' => 'LBL_HISTORY_SUBPANEL_TITLE',
            'get_subpanel_data' => 'history',
            'top_buttons' => array(),
        ),
    ),
);

// Custom subpanel for subsidiary accounts
$layout_defs['mdeal_Accounts']['subpanel_setup']['subsidiaries']['layout_def'] = array(
    'list_fields' => array(
        'name' => array(
            'name' => 'name',
            'vname' => 'LBL_LIST_ACCOUNT_NAME',
            'widget_class' => 'SubPanelDetailViewLink',
            'width' => '25%',
            'default' => true,
        ),
        'account_type' => array(
            'name' => 'account_type',
            'vname' => 'LBL_LIST_ACCOUNT_TYPE',
            'width' => '15%',
            'default' => true,
        ),
        'industry' => array(
            'name' => 'industry',
            'vname' => 'LBL_LIST_INDUSTRY',
            'width' => '15%',
            'default' => true,
        ),
        'annual_revenue' => array(
            'name' => 'annual_revenue',
            'vname' => 'LBL_LIST_ANNUAL_REVENUE',
            'width' => '15%',
            'default' => true,
            'customCode' => '{if $ANNUAL_REVENUE}${$ANNUAL_REVENUE|number_format:0}{/if}',
        ),
        'employee_count' => array(
            'name' => 'employee_count',
            'vname' => 'LBL_LIST_EMPLOYEE_COUNT',
            'width' => '10%',
            'default' => true,
        ),
        'account_status' => array(
            'name' => 'account_status',
            'vname' => 'LBL_LIST_ACCOUNT_STATUS',
            'width' => '10%',
            'default' => true,
        ),
        'edit_button' => array(
            'vname' => 'LBL_EDIT_BUTTON',
            'widget_class' => 'SubPanelEditButton',
            'module' => 'mdeal_Accounts',
            'width' => '5%',
            'default' => true,
        ),
        'remove_button' => array(
            'vname' => 'LBL_REMOVE',
            'widget_class' => 'SubPanelRemoveButton',
            'module' => 'mdeal_Accounts',
            'width' => '5%',
            'default' => true,
        ),
    ),
);

// Enhanced contacts subpanel with relationship fields
$layout_defs['mdeal_Accounts']['subpanel_setup']['mdeal_contacts']['layout_def'] = array(
    'list_fields' => array(
        'full_name' => array(
            'name' => 'full_name',
            'vname' => 'LBL_LIST_NAME',
            'widget_class' => 'SubPanelDetailViewLink',
            'module' => 'mdeal_Contacts',
            'width' => '20%',
            'default' => true,
        ),
        'title' => array(
            'name' => 'title',
            'vname' => 'LBL_LIST_TITLE',
            'width' => '15%',
            'default' => true,
        ),
        'contact_type' => array(
            'name' => 'contact_type',
            'vname' => 'LBL_LIST_CONTACT_TYPE',
            'width' => '12%',
            'default' => true,
        ),
        'decision_role' => array(
            'name' => 'decision_role',
            'vname' => 'LBL_LIST_DECISION_ROLE',
            'width' => '12%',
            'default' => true,
        ),
        'phone_work' => array(
            'name' => 'phone_work',
            'vname' => 'LBL_LIST_PHONE',
            'width' => '10%',
            'default' => true,
        ),
        'email_address' => array(
            'name' => 'email_address',
            'vname' => 'LBL_LIST_EMAIL_ADDRESS',
            'width' => '15%',
            'default' => true,
            'customCode' => '{if $EMAIL_ADDRESS}<a href="mailto:{$EMAIL_ADDRESS}">{$EMAIL_ADDRESS}</a>{/if}',
        ),
        'last_interaction_date' => array(
            'name' => 'last_interaction_date',
            'vname' => 'LBL_LAST_INTERACTION_DATE',
            'width' => '10%',
            'default' => false,
        ),
        'edit_button' => array(
            'vname' => 'LBL_EDIT_BUTTON',
            'widget_class' => 'SubPanelEditButton',
            'module' => 'mdeal_Contacts',
            'width' => '3%',
            'default' => true,
        ),
        'remove_button' => array(
            'vname' => 'LBL_REMOVE',
            'widget_class' => 'SubPanelRemoveButton',
            'module' => 'mdeal_Contacts',
            'width' => '3%',
            'default' => true,
        ),
    ),
);

// Enhanced deals subpanel with M&A specific fields
$layout_defs['mdeal_Accounts']['subpanel_setup']['mdeal_deals']['layout_def'] = array(
    'list_fields' => array(
        'name' => array(
            'name' => 'name',
            'vname' => 'LBL_LIST_DEAL_NAME',
            'widget_class' => 'SubPanelDetailViewLink',
            'module' => 'mdeal_Deals',
            'width' => '25%',
            'default' => true,
        ),
        'deal_type' => array(
            'name' => 'deal_type',
            'vname' => 'LBL_LIST_DEAL_TYPE',
            'width' => '12%',
            'default' => true,
        ),
        'stage' => array(
            'name' => 'stage',
            'vname' => 'LBL_LIST_STAGE',
            'width' => '12%',
            'default' => true,
        ),
        'deal_value' => array(
            'name' => 'deal_value',
            'vname' => 'LBL_LIST_DEAL_VALUE',
            'width' => '12%',
            'default' => true,
            'customCode' => '{if $DEAL_VALUE}${$DEAL_VALUE|number_format:0}{/if}',
        ),
        'probability' => array(
            'name' => 'probability',
            'vname' => 'LBL_LIST_PROBABILITY',
            'width' => '8%',
            'default' => true,
            'customCode' => '{if $PROBABILITY}{$PROBABILITY}%{/if}',
        ),
        'close_date' => array(
            'name' => 'close_date',
            'vname' => 'LBL_LIST_CLOSE_DATE',
            'width' => '10%',
            'default' => true,
        ),
        'assigned_user_name' => array(
            'name' => 'assigned_user_name',
            'vname' => 'LBL_LIST_ASSIGNED_USER',
            'width' => '10%',
            'default' => false,
        ),
        'edit_button' => array(
            'vname' => 'LBL_EDIT_BUTTON',
            'widget_class' => 'SubPanelEditButton',
            'module' => 'mdeal_Deals',
            'width' => '3%',
            'default' => true,
        ),
        'remove_button' => array(
            'vname' => 'LBL_REMOVE',
            'widget_class' => 'SubPanelRemoveButton',
            'module' => 'mdeal_Deals',
            'width' => '3%',
            'default' => true,
        ),
    ),
);