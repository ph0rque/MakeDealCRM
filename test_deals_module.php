<?php
/**
 * Test file to diagnose Deals module loading issue
 */

// Set error reporting to show all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('sugarEntry', true);

// Change to SuiteCRM directory
chdir('/Users/andrewgauntlet/Desktop/MakeDealCRM/SuiteCRM');

// Include SuiteCRM bootstrap
require_once('include/entryPoint.php');

echo "=== Testing Deals Module Loading ===\n\n";

// Test 1: Check if Deal class file exists
$dealClassFile = 'custom/modules/Deals/Deal.php';
echo "1. Checking Deal class file:\n";
echo "   File path: $dealClassFile\n";
echo "   File exists: " . (file_exists($dealClassFile) ? "YES" : "NO") . "\n\n";

// Test 2: Check if Opportunity class exists (parent class)
echo "2. Checking parent Opportunity class:\n";
if (file_exists('modules/Opportunities/Opportunity.php')) {
    require_once('modules/Opportunities/Opportunity.php');
    echo "   Opportunity class loaded: " . (class_exists('Opportunity') ? "YES" : "NO") . "\n\n";
} else {
    echo "   ERROR: Opportunity.php not found!\n\n";
}

// Test 3: Try to load Deal class
echo "3. Loading Deal class:\n";
try {
    if (file_exists($dealClassFile)) {
        require_once($dealClassFile);
        echo "   Deal class loaded: " . (class_exists('Deal') ? "YES" : "NO") . "\n";
        
        if (class_exists('Deal')) {
            $deal = new Deal();
            echo "   Deal instance created: YES\n";
            echo "   Module dir: " . $deal->module_dir . "\n";
            echo "   Table name: " . $deal->table_name . "\n";
            echo "   Object name: " . $deal->object_name . "\n";
        }
    } else {
        echo "   ERROR: Deal.php file not found in custom/modules/Deals/\n";
    }
} catch (Exception $e) {
    echo "   ERROR loading Deal class: " . $e->getMessage() . "\n";
}

echo "\n4. Checking module registration:\n";
// Check beanList
if (isset($GLOBALS['beanList']['Deals'])) {
    echo "   beanList['Deals']: " . $GLOBALS['beanList']['Deals'] . "\n";
} else {
    echo "   ERROR: Deals not in beanList\n";
}

// Check beanFiles
if (isset($GLOBALS['beanFiles']['Deal'])) {
    echo "   beanFiles['Deal']: " . $GLOBALS['beanFiles']['Deal'] . "\n";
} else {
    echo "   ERROR: Deal not in beanFiles\n";
}

// Check moduleList
if (isset($GLOBALS['moduleList']) && in_array('Deals', $GLOBALS['moduleList'])) {
    echo "   Deals in moduleList: YES\n";
} else {
    echo "   ERROR: Deals not in moduleList\n";
}

echo "\n5. Checking database tables:\n";
global $db;
if ($db) {
    // Check opportunities table
    $result = $db->query("SHOW TABLES LIKE 'opportunities'");
    if ($db->fetchByAssoc($result)) {
        echo "   opportunities table exists: YES\n";
        
        // Check for custom fields
        $result = $db->query("SHOW TABLES LIKE 'opportunities_cstm'");
        if ($db->fetchByAssoc($result)) {
            echo "   opportunities_cstm table exists: YES\n";
            
            // Check for pipeline_stage_c field
            $result = $db->query("SHOW COLUMNS FROM opportunities_cstm LIKE 'pipeline_stage_c'");
            if ($db->fetchByAssoc($result)) {
                echo "   pipeline_stage_c field exists: YES\n";
            } else {
                echo "   WARNING: pipeline_stage_c field not found\n";
            }
        } else {
            echo "   WARNING: opportunities_cstm table not found\n";
        }
    } else {
        echo "   ERROR: opportunities table not found\n";
    }
} else {
    echo "   ERROR: Database connection not available\n";
}

echo "\n=== End of Diagnostics ===\n";