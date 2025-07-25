<?php
/**
 * Deals Module Package Manifest
 * 
 * This manifest file defines the complete installation package for the Deals module
 * which extends the Opportunities module with advanced pipeline management features.
 * 
 * @package MakeDealCRM
 * @module Deals
 * @version 1.2.0
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

$manifest = array(
    // Module compatibility
    'acceptable_sugar_versions' => array(
        'exact_matches' => array(),
        'regex_matches' => array(
            0 => '7\.[89]\..*',
            1 => '7\.1[0-2]\..*',
            2 => '8\.*\.*',
        ),
    ),
    'acceptable_sugar_flavors' => array(
        'CE',
        'PRO',
        'ENT',
    ),
    
    // Module metadata
    'author' => 'MakeDeal CRM Development Team',
    'description' => 'Enhanced Deals module extending Opportunities with advanced pipeline management, due diligence checklists, and stakeholder tracking',
    'icon' => 'custom/modules/Deals/icons/module-icon.png',
    'is_uninstallable' => true,
    'name' => 'Deals Pipeline Management Module',
    'published_date' => '2025-01-24 15:00:00',
    'type' => 'module',
    'version' => '1.2.0',
    'remove_tables' => 'prompt',
    'uninstall_before_upgrade' => false,
);

$installdefs = array(
    'id' => 'Deals',
    
    // Module registration
    'beans' => array(
        array(
            'module' => 'Deals',
            'class' => 'Deal',
            'path' => 'custom/modules/Deals/Deal.php',
            'tab' => true,
        ),
    ),
    
    // Module loader entries
    'module_list' => array(
        array(
            'module' => 'Deals',
            'label' => 'LBL_MODULE_NAME',
        ),
    ),
    
    // Administration components
    'administration' => array(
        array(
            'from' => '<basepath>/SugarModules/administration/deals_admin.php',
        ),
    ),
    
    // Layout definitions
    'layoutdefs' => array(
        array(
            'from' => '<basepath>/SugarModules/relationships/layoutdefs/deals_contacts_Deals.php',
            'to_module' => 'Deals',
        ),
        array(
            'from' => '<basepath>/SugarModules/relationships/layoutdefs/deals_contacts_Contacts.php',
            'to_module' => 'Contacts',
        ),
        array(
            'from' => '<basepath>/SugarModules/relationships/layoutdefs/deals_documents_Deals.php',
            'to_module' => 'Deals',
        ),
        array(
            'from' => '<basepath>/SugarModules/relationships/layoutdefs/deals_tasks_Deals.php',
            'to_module' => 'Deals',
        ),
        array(
            'from' => '<basepath>/SugarModules/relationships/layoutdefs/deals_notes_Deals.php',
            'to_module' => 'Deals',
        ),
        array(
            'from' => '<basepath>/SugarModules/relationships/layoutdefs/deals_meetings_Deals.php',
            'to_module' => 'Deals',
        ),
        array(
            'from' => '<basepath>/SugarModules/relationships/layoutdefs/deals_calls_Deals.php',
            'to_module' => 'Deals',
        ),
        array(
            'from' => '<basepath>/SugarModules/relationships/layoutdefs/deals_emails_Deals.php',
            'to_module' => 'Deals',
        ),
    ),
    
    // Relationship metadata
    'relationships' => array(
        array(
            'meta_data' => '<basepath>/SugarModules/relationships/relationships/deals_contactsMetaData.php',
        ),
        array(
            'meta_data' => '<basepath>/SugarModules/relationships/relationships/deals_documentsMetaData.php',
        ),
        array(
            'meta_data' => '<basepath>/SugarModules/relationships/relationships/deals_tasksMetaData.php',
        ),
        array(
            'meta_data' => '<basepath>/SugarModules/relationships/relationships/deals_notesMetaData.php',
        ),
        array(
            'meta_data' => '<basepath>/SugarModules/relationships/relationships/deals_meetingsMetaData.php',
        ),
        array(
            'meta_data' => '<basepath>/SugarModules/relationships/relationships/deals_callsMetaData.php',
        ),
        array(
            'meta_data' => '<basepath>/SugarModules/relationships/relationships/deals_emailsMetaData.php',
        ),
        array(
            'meta_data' => '<basepath>/custom/metadata/deals_checklist_templatesMetaData.php',
        ),
        array(
            'meta_data' => '<basepath>/custom/metadata/deals_checklist_itemsMetaData.php',
        ),
    ),
    
    // Image/icon files
    'image_dir' => '<basepath>/icons',
    
    // File copies
    'copy' => array(
        // Core module files
        array(
            'from' => '<basepath>/SugarModules/modules/Deals',
            'to' => 'modules/Deals',
        ),
        // Custom module extensions
        array(
            'from' => '<basepath>/SugarModules/custom/modules/Deals',
            'to' => 'custom/modules/Deals',
        ),
        // Custom Extensions
        array(
            'from' => '<basepath>/SugarModules/custom/Extension/modules/Deals',
            'to' => 'custom/Extension/modules/Deals',
        ),
        // Application extensions
        array(
            'from' => '<basepath>/SugarModules/custom/Extension/application',
            'to' => 'custom/Extension/application',
        ),
        // Administration extensions
        array(
            'from' => '<basepath>/SugarModules/custom/Extension/modules/Administration',
            'to' => 'custom/Extension/modules/Administration',
        ),
        // Schedulers extensions
        array(
            'from' => '<basepath>/SugarModules/custom/Extension/modules/Schedulers',
            'to' => 'custom/Extension/modules/Schedulers',
        ),
    ),
    
    // Language files
    'language' => array(
        // Application language
        array(
            'from' => '<basepath>/SugarModules/language/application/en_us.lang.php',
            'to_module' => 'application',
            'language' => 'en_us',
        ),
        // Module language
        array(
            'from' => '<basepath>/SugarModules/language/modules/Deals/en_us.lang.php',
            'to_module' => 'Deals',
            'language' => 'en_us',
        ),
        // Dropdown lists
        array(
            'from' => '<basepath>/SugarModules/language/application/en_us.pipeline_lists.php',
            'to_module' => 'application',
            'language' => 'en_us',
        ),
    ),
    
    // Variable definitions
    'vardefs' => array(
        array(
            'from' => '<basepath>/SugarModules/modules/Deals/vardefs.php',
            'to_module' => 'Deals',
        ),
        // Extension vardefs for relationships
        array(
            'from' => '<basepath>/SugarModules/relationships/vardefs/deals_contacts_Deals.php',
            'to_module' => 'Deals',
        ),
        array(
            'from' => '<basepath>/SugarModules/relationships/vardefs/deals_contacts_Contacts.php',
            'to_module' => 'Contacts',
        ),
    ),
    
    // Schedulers
    'scheduledefs' => array(
        array(
            'from' => '<basepath>/SugarModules/schedulers/PipelineMaintenanceJob.php',
        ),
    ),
    
    // Logic hooks
    'logic_hooks' => array(
        array(
            'module' => 'Deals',
            'hook' => 'after_save',
            'order' => 99,
            'description' => 'Update pipeline stage history',
            'file' => 'custom/modules/Deals/logic_hooks/PipelineStageHook.php',
            'class' => 'PipelineStageHook',
            'function' => 'updateStageHistory',
        ),
        array(
            'module' => 'Deals',
            'hook' => 'before_save',
            'order' => 98,
            'description' => 'Validate WIP limits',
            'file' => 'custom/modules/Deals/logic_hooks/WIPLimitHook.php',
            'class' => 'WIPLimitHook',
            'function' => 'validateWIPLimit',
        ),
    ),
    
    // Custom fields (pipeline-specific)
    'custom_fields' => array(
        // Pipeline stage field
        array(
            'name' => 'pipeline_stage_c',
            'label' => 'LBL_PIPELINE_STAGE',
            'type' => 'enum',
            'module' => 'Opportunities',
            'options' => 'pipeline_stage_list',
            'default_value' => 'sourcing',
            'mass_update' => true,
            'required' => false,
            'reportable' => true,
            'audited' => true,
            'importable' => true,
            'duplicate_merge' => true,
            'help' => 'Current stage in the M&A pipeline',
            'comment' => 'Pipeline stage for deal tracking',
        ),
        // Stage entered date
        array(
            'name' => 'stage_entered_date_c',
            'label' => 'LBL_STAGE_ENTERED_DATE',
            'type' => 'datetime',
            'module' => 'Opportunities',
            'mass_update' => false,
            'required' => false,
            'reportable' => true,
            'audited' => true,
            'importable' => false,
            'duplicate_merge' => false,
            'enable_range_search' => true,
            'options' => 'date_range_search_dom',
        ),
        // Expected close date
        array(
            'name' => 'expected_close_date_c',
            'label' => 'LBL_EXPECTED_CLOSE_DATE',
            'type' => 'date',
            'module' => 'Opportunities',
            'mass_update' => false,
            'required' => false,
            'reportable' => true,
            'audited' => true,
            'importable' => true,
            'duplicate_merge' => true,
            'enable_range_search' => true,
            'options' => 'date_range_search_dom',
        ),
        // Deal source
        array(
            'name' => 'deal_source_c',
            'label' => 'LBL_DEAL_SOURCE',
            'type' => 'enum',
            'module' => 'Opportunities',
            'options' => 'deal_source_list',
            'mass_update' => true,
            'required' => false,
            'reportable' => true,
            'audited' => true,
            'importable' => true,
            'duplicate_merge' => true,
        ),
        // Pipeline notes
        array(
            'name' => 'pipeline_notes_c',
            'label' => 'LBL_PIPELINE_NOTES',
            'type' => 'text',
            'module' => 'Opportunities',
            'mass_update' => false,
            'required' => false,
            'reportable' => true,
            'audited' => true,
            'importable' => true,
            'duplicate_merge' => false,
            'rows' => 4,
            'cols' => 60,
        ),
        // Days in stage (calculated)
        array(
            'name' => 'days_in_stage_c',
            'label' => 'LBL_DAYS_IN_STAGE',
            'type' => 'int',
            'module' => 'Opportunities',
            'mass_update' => false,
            'required' => false,
            'reportable' => true,
            'audited' => false,
            'importable' => false,
            'duplicate_merge' => false,
            'calculated' => true,
            'formula' => 'daysUntil($stage_entered_date_c)',
            'enforced' => true,
        ),
        // Checklist completion percentage
        array(
            'name' => 'checklist_completion_c',
            'label' => 'LBL_CHECKLIST_COMPLETION',
            'type' => 'decimal',
            'module' => 'Opportunities',
            'mass_update' => false,
            'required' => false,
            'reportable' => true,
            'audited' => false,
            'importable' => false,
            'duplicate_merge' => false,
            'precision' => 2,
            'len' => '5,2',
        ),
    ),
    
    // Menu items
    'menu' => array(
        array(
            'from' => '<basepath>/SugarModules/modules/Deals/Menu.php',
            'to_module' => 'Deals',
        ),
    ),
    
    // Database table creation scripts
    'pre_execute' => array(
        '<basepath>/scripts/pre_install.php',
    ),
    
    // Post-installation scripts
    'post_install' => array(
        '<basepath>/scripts/post_install.php',
        '<basepath>/scripts/install_checklist_relationships.php',
        '<basepath>/scripts/repair_pipeline_fields.php',
    ),
    
    // Pre-uninstall scripts
    'pre_uninstall' => array(
        '<basepath>/scripts/pre_uninstall.php',
    ),
    
    // Post-uninstall scripts
    'post_uninstall' => array(
        '<basepath>/scripts/post_uninstall.php',
    ),
);

// Upgrade definitions
$upgrade_manifest = array(
    'upgrade_paths' => array(
        '1.0.0' => '1.2.0',
        '1.1.0' => '1.2.0',
    ),
);