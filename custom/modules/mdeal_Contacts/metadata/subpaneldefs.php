<?php
/**
 * Subpanel definitions for mdeal_Contacts module
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$layout_defs['mdeal_Contacts'] = array(
    'subpanel_setup' => array(
        'activities' => array(
            'order' => 10,
            'sort_order' => 'desc',
            'sort_by' => 'date_modified',
            'title_key' => 'LBL_ACTIVITIES_SUBPANEL_TITLE',
            'type' => 'collection',
            'subpanel_name' => 'activities',
            'header_definition_from_subpanel' => 'activities',
            'module' => 'Activities',
            'top_buttons' => array(
                array(
                    'widget_class' => 'SubPanelTopCreateTaskButton',
                ),
                array(
                    'widget_class' => 'SubPanelTopScheduleMeetingButton',
                ),
                array(
                    'widget_class' => 'SubPanelTopScheduleCallButton',
                ),
                array(
                    'widget_class' => 'SubPanelTopComposeEmailButton',
                ),
            ),
            'collection_list' => array(
                'meetings' => array(
                    'module' => 'Meetings',
                    'subpanel_name' => 'ForActivities',
                    'get_subpanel_data' => 'meetings',
                ),
                'tasks' => array(
                    'module' => 'Tasks',
                    'subpanel_name' => 'ForActivities',
                    'get_subpanel_data' => 'tasks',
                ),
                'calls' => array(
                    'module' => 'Calls',
                    'subpanel_name' => 'ForActivities',
                    'get_subpanel_data' => 'calls',
                ),
            ),
        ),
        'history' => array(
            'order' => 20,
            'sort_order' => 'desc',
            'sort_by' => 'date_modified',
            'title_key' => 'LBL_HISTORY_SUBPANEL_TITLE',
            'type' => 'collection',
            'subpanel_name' => 'history',
            'header_definition_from_subpanel' => 'history',
            'module' => 'History',
            'top_buttons' => array(
                array(
                    'widget_class' => 'SubPanelTopCreateNoteButton',
                ),
                array(
                    'widget_class' => 'SubPanelTopArchiveEmailButton',
                ),
            ),
            'collection_list' => array(
                'meetings' => array(
                    'module' => 'Meetings',
                    'subpanel_name' => 'ForHistory',
                    'get_subpanel_data' => 'meetings',
                ),
                'tasks' => array(
                    'module' => 'Tasks',
                    'subpanel_name' => 'ForHistory',
                    'get_subpanel_data' => 'tasks',
                ),
                'calls' => array(
                    'module' => 'Calls',
                    'subpanel_name' => 'ForHistory',
                    'get_subpanel_data' => 'calls',
                ),
                'notes' => array(
                    'module' => 'Notes',
                    'subpanel_name' => 'ForHistory',
                    'get_subpanel_data' => 'notes',
                ),
                'emails' => array(
                    'module' => 'Emails',
                    'subpanel_name' => 'ForHistory',
                    'get_subpanel_data' => 'emails',
                ),
            ),
        ),
        'mdeal_contacts_deals' => array(
            'order' => 30,
            'module' => 'mdeal_Deals',
            'subpanel_name' => 'default',
            'sort_order' => 'desc',
            'sort_by' => 'date_modified',
            'title_key' => 'LBL_DEALS_SUBPANEL_TITLE',
            'get_subpanel_data' => 'mdeal_contacts_deals',
            'top_buttons' => array(
                array(
                    'widget_class' => 'SubPanelTopButtonQuickCreate',
                    'mode' => 'MultiSelect',
                ),
            ),
        ),
        'mdeal_contacts_accounts' => array(
            'order' => 40,
            'module' => 'mdeal_Accounts',
            'subpanel_name' => 'default',
            'sort_order' => 'desc',
            'sort_by' => 'date_modified',
            'title_key' => 'LBL_ACCOUNTS_SUBPANEL_TITLE',
            'get_subpanel_data' => 'mdeal_contacts_accounts',
            'top_buttons' => array(
                array(
                    'widget_class' => 'SubPanelTopButtonQuickCreate',
                    'mode' => 'MultiSelect',
                ),
            ),
        ),
        'direct_reports' => array(
            'order' => 50,
            'module' => 'mdeal_Contacts',
            'subpanel_name' => 'default',
            'sort_order' => 'asc',
            'sort_by' => 'last_name',
            'title_key' => 'LBL_REPORTS_SUBPANEL_TITLE',
            'get_subpanel_data' => 'direct_reports',
            'get_distinct_data' => true,
            'top_buttons' => array(
                array(
                    'widget_class' => 'SubPanelTopCreateButton',
                ),
                array(
                    'widget_class' => 'SubPanelTopSelectButton',
                    'mode' => 'MultiSelect',
                ),
            ),
        ),
        'documents' => array(
            'order' => 60,
            'module' => 'Documents',
            'subpanel_name' => 'default',
            'sort_order' => 'desc',
            'sort_by' => 'date_modified',
            'title_key' => 'LBL_DOCUMENTS_SUBPANEL_TITLE',
            'get_subpanel_data' => 'documents',
            'top_buttons' => array(
                array(
                    'widget_class' => 'SubPanelTopButtonQuickCreate',
                    'mode' => 'MultiSelect',
                ),
            ),
        ),
    ),
);