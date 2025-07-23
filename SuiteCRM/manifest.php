<?php
/**
 * Deals Module Manifest
 * 
 * This manifest file registers the Deals module with SuiteCRM
 */

$manifest = array(
    'acceptable_sugar_versions' => array(
        'regex_matches' => array(
            '.*',
        ),
    ),
    'acceptable_sugar_flavors' => array(
        'CE',
        'PRO',
        'ENT'
    ),
    'name' => 'Deals Module',
    'description' => 'Central object for managing M&A deals replacing Opportunities and Leads',
    'author' => 'MakeDealCRM',
    'published_date' => '2025-07-23',
    'version' => '1.0.0',
    'type' => 'module',
    'is_uninstallable' => true,
    'icon' => '',
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
    'layoutdefs' => array(
        array(
            'from' => '<basepath>/SugarModules/relationships/layoutdefs/deals_contacts_Deals.php',
            'to_module' => 'Deals',
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
    ),
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
    ),
    'language' => array(
        array(
            'from' => '<basepath>/SugarModules/language/application/en_us.lang.php',
            'to_module' => 'application',
            'language' => 'en_us',
        ),
        array(
            'from' => '<basepath>/modules/Deals/language/en_us.lang.php',
            'to_module' => 'Deals',
            'language' => 'en_us',
        ),
    ),
    'vardefs' => array(
        array(
            'from' => '<basepath>/modules/Deals/vardefs.php',
            'to_module' => 'Deals',
        ),
    ),
    'copy' => array(
        array(
            'from' => '<basepath>/modules/Deals',
            'to' => 'modules/Deals',
        ),
    ),
);
?>