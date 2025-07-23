<?php
/**
 * Subpanel definitions for Deals module
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

$layout_defs['Deals'] = array(
    'subpanel_setup' => array(
        
        // Activities subpanel
        'activities' => array(
            'order' => 10,
            'sort_order' => 'desc',
            'sort_by' => 'date_start',
            'title_key' => 'LBL_ACTIVITIES_SUBPANEL_TITLE',
            'type' => 'collection',
            'subpanel_name' => 'activities',
            'header_definition_from_subpanel' => 'meetings',
            'module' => 'Activities',
            'top_buttons' => array(
                array('widget_class' => 'SubPanelTopCreateTaskButton'),
                array('widget_class' => 'SubPanelTopScheduleMeetingButton'),
                array('widget_class' => 'SubPanelTopScheduleCallButton'),
                array('widget_class' => 'SubPanelTopComposeEmailButton'),
            ),
            'collection_list' => array(
                'meetings' => array(
                    'module' => 'Meetings',
                    'subpanel_name' => 'ForActivities',
                    'get_subpanel_data' => 'meetings',
                ),
                'calls' => array(
                    'module' => 'Calls',
                    'subpanel_name' => 'ForActivities',
                    'get_subpanel_data' => 'calls',
                ),
                'tasks' => array(
                    'module' => 'Tasks',
                    'subpanel_name' => 'ForActivities',
                    'get_subpanel_data' => 'tasks',
                ),
            ),
        ),
        
        // History subpanel
        'history' => array(
            'order' => 20,
            'sort_order' => 'desc',
            'sort_by' => 'date_entered',
            'title_key' => 'LBL_HISTORY_SUBPANEL_TITLE',
            'type' => 'collection',
            'subpanel_name' => 'history',
            'header_definition_from_subpanel' => 'meetings',
            'module' => 'History',
            'top_buttons' => array(
                array('widget_class' => 'SubPanelTopCreateNoteButton'),
                array('widget_class' => 'SubPanelTopArchiveEmailButton'),
                array('widget_class' => 'SubPanelTopSummaryButton'),
            ),
            'collection_list' => array(
                'meetings' => array(
                    'module' => 'Meetings',
                    'subpanel_name' => 'ForHistory',
                    'get_subpanel_data' => 'meetings',
                ),
                'calls' => array(
                    'module' => 'Calls',
                    'subpanel_name' => 'ForHistory',
                    'get_subpanel_data' => 'calls',
                ),
                'tasks' => array(
                    'module' => 'Tasks',
                    'subpanel_name' => 'ForHistory',
                    'get_subpanel_data' => 'tasks',
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
        
        // Contacts subpanel
        'contacts' => array(
            'order' => 30,
            'module' => 'Contacts',
            'sort_order' => 'asc',
            'sort_by' => 'last_name, first_name',
            'subpanel_name' => 'default',
            'get_subpanel_data' => 'contacts',
            'add_subpanel_data' => 'contact_id',
            'title_key' => 'LBL_CONTACTS_SUBPANEL_TITLE',
            'top_buttons' => array(
                array('widget_class' => 'SubPanelTopButtonQuickCreate'),
                array('widget_class' => 'SubPanelTopSelectButton', 'mode' => 'MultiSelect'),
            ),
        ),
        
        // Quotes subpanel
        'quotes' => array(
            'order' => 40,
            'module' => 'AOS_Quotes',
            'sort_order' => 'desc',
            'sort_by' => 'date_entered',
            'subpanel_name' => 'default',
            'get_subpanel_data' => 'aos_quotes',
            'add_subpanel_data' => 'deal_id',
            'title_key' => 'LBL_QUOTES_SUBPANEL_TITLE',
            'top_buttons' => array(
                array(
                    'widget_class' => 'SubPanelTopButtonQuickCreate',
                    'additional_form_fields' => array(
                        'deal_id' => 'id',
                        'deal_name' => 'name',
                        'billing_account_id' => 'account_id',
                        'billing_account' => 'account_name',
                    ),
                ),
            ),
        ),
        
        // Documents subpanel
        'documents' => array(
            'order' => 50,
            'module' => 'Documents',
            'subpanel_name' => 'default',
            'sort_order' => 'asc',
            'sort_by' => 'document_name',
            'title_key' => 'LBL_DOCUMENTS_SUBPANEL_TITLE',
            'get_subpanel_data' => 'documents',
            'top_buttons' => array(
                array('widget_class' => 'SubPanelTopButtonQuickCreate'),
                array('widget_class' => 'SubPanelTopSelectButton', 'mode' => 'MultiSelect'),
            ),
        ),
        
        // Products subpanel
        'products' => array(
            'order' => 60,
            'module' => 'AOS_Products',
            'sort_order' => 'asc',
            'sort_by' => 'name',
            'subpanel_name' => 'default',
            'get_subpanel_data' => 'aos_products',
            'add_subpanel_data' => 'deal_id',
            'title_key' => 'LBL_PRODUCTS_SUBPANEL_TITLE',
            'top_buttons' => array(
                array('widget_class' => 'SubPanelTopSelectButton', 'mode' => 'MultiSelect'),
            ),
        ),
        
        // Contracts subpanel
        'contracts' => array(
            'order' => 70,
            'module' => 'AOS_Contracts',
            'sort_order' => 'desc',
            'sort_by' => 'date_entered',
            'subpanel_name' => 'default',
            'get_subpanel_data' => 'aos_contracts',
            'add_subpanel_data' => 'deal_id',
            'title_key' => 'LBL_CONTRACTS_SUBPANEL_TITLE',
            'top_buttons' => array(
                array('widget_class' => 'SubPanelTopButtonQuickCreate'),
            ),
        ),
        
        // Invoices subpanel
        'invoices' => array(
            'order' => 80,
            'module' => 'AOS_Invoices',
            'sort_order' => 'desc',
            'sort_by' => 'date_entered',
            'subpanel_name' => 'default',
            'get_subpanel_data' => 'aos_invoices',
            'add_subpanel_data' => 'deal_id',
            'title_key' => 'LBL_INVOICES_SUBPANEL_TITLE',
            'top_buttons' => array(
                array('widget_class' => 'SubPanelTopButtonQuickCreate'),
            ),
        ),
        
        // Competitors subpanel
        'competitors' => array(
            'order' => 90,
            'module' => 'Accounts',
            'sort_order' => 'asc',
            'sort_by' => 'name',
            'subpanel_name' => 'ForCompetitors',
            'get_subpanel_data' => 'competitors',
            'add_subpanel_data' => 'competitor_id',
            'title_key' => 'LBL_COMPETITORS_SUBPANEL_TITLE',
            'top_buttons' => array(
                array('widget_class' => 'SubPanelTopSelectButton', 'mode' => 'MultiSelect'),
            ),
        ),
        
        // SecurityGroups subpanel
        'securitygroups' => array(
            'order' => 100,
            'module' => 'SecurityGroups',
            'subpanel_name' => 'default',
            'sort_order' => 'asc',
            'sort_by' => 'name',
            'title_key' => 'LBL_SECURITYGROUPS_SUBPANEL_TITLE',
            'get_subpanel_data' => 'SecurityGroups',
            'top_buttons' => array(
                array('widget_class' => 'SubPanelTopSelectButton', 'mode' => 'MultiSelect'),
            ),
        ),
    ),
);