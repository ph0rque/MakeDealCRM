<?php
/**
 * Deals Module Manifest
 * Extends Opportunities module with enhanced pipeline functionality
 */

$manifest = array(
    'acceptable_sugar_versions' => array(
        'exact_matches' => array(),
        'regex_matches' => array(
            0 => '7\.*\.*',
            1 => '8\.*\.*',
        ),
    ),
    'acceptable_sugar_flavors' => array(
        'CE',
        'PRO',
        'ENT',
    ),
    'author' => 'MakeDeal CRM',
    'description' => 'Enhanced Deals module extending Opportunities with advanced pipeline management',
    'icon' => '',
    'is_uninstallable' => true,
    'name' => 'Deals Pipeline Module',
    'published_date' => '2025-01-24 15:00:00',
    'type' => 'module',
    'version' => '1.0.0',
    'remove_tables' => 'prompt',
);

$installdefs = array(
    'id' => 'Deals',
    'beans' => array(
        array(
            'module' => 'Deals',
            'class' => 'Deal',
            'path' => 'modules/Deals/Deal.php',
            'tab' => true,
        ),
    ),
    'layoutdefs' => array(),
    'relationships' => array(),
    'image_dir' => '<basepath>/icons',
    'copy' => array(
        array(
            'from' => '<basepath>/SugarModules/modules/Deals',
            'to' => 'modules/Deals',
        ),
        array(
            'from' => '<basepath>/SugarModules/custom/modules/Deals',
            'to' => 'custom/modules/Deals',
        ),
    ),
    'language' => array(
        array(
            'from' => '<basepath>/SugarModules/language/application/en_us.lang.php',
            'to_module' => 'application',
            'language' => 'en_us',
        ),
        array(
            'from' => '<basepath>/SugarModules/language/modules/Deals/en_us.lang.php',
            'to_module' => 'Deals',
            'language' => 'en_us',
        ),
    ),
    'vardefs' => array(
        array(
            'from' => '<basepath>/SugarModules/modules/Deals/vardefs.php',
            'to_module' => 'Deals',
        ),
    ),
    'custom_fields' => array(
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
        ),
        array(
            'name' => 'stage_entered_date_c',
            'label' => 'LBL_STAGE_ENTERED_DATE',
            'type' => 'datetime',
            'module' => 'Opportunities',
            'mass_update' => false,
            'required' => false,
            'reportable' => true,
            'audited' => true,
            'importable' => true,
            'duplicate_merge' => false,
        ),
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
        ),
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
        ),
    ),
    'post_install' => array(
        '<basepath>/scripts/post_install.php',
    ),
);