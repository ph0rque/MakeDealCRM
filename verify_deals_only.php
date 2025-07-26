<?php
/**
 * Verification script to ensure only Deals module is active
 * and Opportunities module is properly disabled
 */

// Set up SuiteCRM environment
if (!defined('sugarEntry')) define('sugarEntry', true);
chdir('SuiteCRM');
require_once('include/entryPoint.php');

echo "=== Verifying Deals-Only Configuration ===\n\n";

// 1. Check module lists
echo "1. Checking Module Lists:\n";
global $moduleList, $modInvisList, $beanList, $beanFiles;

$opportunitiesInModuleList = in_array('Opportunities', $moduleList);
$dealsInModuleList = in_array('Deals', $moduleList);

echo "   - Deals in moduleList: " . ($dealsInModuleList ? "YES ✓" : "NO ✗") . "\n";
echo "   - Opportunities in moduleList: " . ($opportunitiesInModuleList ? "YES ✗ (Should be removed)" : "NO ✓") . "\n";

// 2. Check config_override settings
echo "\n2. Checking config_override.php settings:\n";
global $sugar_config;

if (isset($sugar_config['disabled_modules']) && in_array('Opportunities', $sugar_config['disabled_modules'])) {
    echo "   - Opportunities is in disabled_modules: YES ✓\n";
} else {
    echo "   - Opportunities is in disabled_modules: NO ✗\n";
}

if (isset($sugar_config['hide_tabs']) && in_array('Opportunities', $sugar_config['hide_tabs'])) {
    echo "   - Opportunities is in hide_tabs: YES ✓\n";
} else {
    echo "   - Opportunities is in hide_tabs: NO ✗\n";
}

// 3. Check database tables
echo "\n3. Checking Database Tables:\n";
global $db;

$tables_query = "SHOW TABLES LIKE '%deals%'";
$result = $db->query($tables_query);
$deals_tables = [];
while ($row = $db->fetchByAssoc($result)) {
    $deals_tables[] = current($row);
}
echo "   - Deals tables found: " . count($deals_tables) . "\n";
foreach ($deals_tables as $table) {
    echo "     • $table\n";
}

// 4. Check for deals in the database
echo "\n4. Checking Deals Data:\n";
$count_query = "SELECT COUNT(*) as count FROM deals WHERE deleted = 0";
$result = $db->query($count_query);
$row = $db->fetchByAssoc($result);
echo "   - Active deals in database: " . $row['count'] . "\n";

// 5. Check pipeline view file
echo "\n5. Checking Pipeline View Configuration:\n";
$pipeline_file = 'custom/modules/Deals/views/view.pipeline.php';
if (file_exists($pipeline_file)) {
    $content = file_get_contents($pipeline_file);
    if (strpos($content, 'FROM deals') !== false) {
        echo "   - Pipeline view queries 'deals' table: YES ✓\n";
    } else {
        echo "   - Pipeline view queries 'deals' table: NO ✗\n";
    }
    if (strpos($content, 'FROM opportunities') !== false) {
        echo "   - Pipeline view still queries 'opportunities' table: YES ✗ (Should be updated)\n";
    } else {
        echo "   - Pipeline view does not query 'opportunities' table: YES ✓\n";
    }
}

// 6. Summary
echo "\n=== Summary ===\n";
echo "Configuration changes needed:\n";
if ($opportunitiesInModuleList) {
    echo "- Remove Opportunities from moduleList\n";
}
if (!isset($sugar_config['disabled_modules']) || !in_array('Opportunities', $sugar_config['disabled_modules'])) {
    echo "- Add Opportunities to disabled_modules in config_override.php\n";
}
echo "\nTo apply changes:\n";
echo "1. Clear cache: rm -rf SuiteCRM/cache/*\n";
echo "2. Run Quick Repair and Rebuild in Admin panel\n";
echo "3. Update Display Modules and Subpanels in Admin panel\n";

echo "\n=== Verification Complete ===\n";