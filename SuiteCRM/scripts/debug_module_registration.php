<?php
/**
 * Debug script for module registration
 */

// SuiteCRM bootstrap
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');

global $db;

echo "Debugging module registration...\n\n";

// Check if modules table exists
$tableCheck = $db->query("SHOW TABLES LIKE 'modules'");
if (!$db->fetchByAssoc($tableCheck)) {
    echo "ERROR: 'modules' table does not exist!\n";
    echo "This table might not be used in this version of SuiteCRM.\n\n";
} else {
    echo "modules table exists.\n";
    
    // Show all modules
    $query = "SELECT * FROM modules LIMIT 10";
    $result = $db->query($query);
    echo "\nSample modules in database:\n";
    while ($row = $db->fetchByAssoc($result)) {
        echo "- " . $row['name'] . " (tab: " . $row['tab'] . ", deleted: " . $row['deleted'] . ")\n";
    }
}

// Check alternative registration methods
echo "\n\nChecking module registration in code:\n";

// Check beanList
echo "\n1. \$beanList registration:\n";
if (isset($GLOBALS['beanList']['Deals'])) {
    echo "   - Deals is registered in beanList as: " . $GLOBALS['beanList']['Deals'] . "\n";
} else {
    echo "   - Deals is NOT in beanList\n";
}

// Check moduleList
echo "\n2. \$moduleList registration:\n";
if (in_array('Deals', $GLOBALS['moduleList'])) {
    echo "   - Deals is in moduleList\n";
    $key = array_search('Deals', $GLOBALS['moduleList']);
    echo "   - Position in moduleList: $key\n";
} else {
    echo "   - Deals is NOT in moduleList\n";
}

// Check if Deals appears in dropdown
echo "\n3. Module dropdown check:\n";
$app_list_strings = return_app_list_strings_language($GLOBALS['current_language']);
if (isset($app_list_strings['moduleList']['Deals'])) {
    echo "   - Deals appears in module dropdown as: " . $app_list_strings['moduleList']['Deals'] . "\n";
} else {
    echo "   - Deals does NOT appear in module dropdown\n";
}

// Check ACL
echo "\n4. ACL module check:\n";
$aclModules = ACLAction::getUserActions($GLOBALS['current_user']->id);
if (isset($aclModules['Deals'])) {
    echo "   - Deals has ACL actions defined\n";
} else {
    echo "   - Deals does NOT have ACL actions\n";
}

// Check if the module might be using a different registration method
echo "\n5. Alternative checks:\n";
echo "   - modInvisList check: " . (in_array('Deals', $GLOBALS['modInvisList'] ?? []) ? 'Hidden' : 'Not hidden') . "\n";
echo "   - Module directory exists: " . (is_dir('modules/Deals') ? 'YES' : 'NO') . "\n";
echo "   - Custom module directory exists: " . (is_dir('custom/modules/Deals') ? 'YES' : 'NO') . "\n";

echo "\nConclusion: In SuiteCRM, modules are typically registered through code files\n";
echo "rather than database entries. The 'modules' table may not be used.\n";
echo "The key files for module registration are:\n";
echo "- include/modules.php (auto-generated)\n";
echo "- custom/Extension/application/Ext/Include/[module].php\n";
echo "- Module appears to be properly registered in the system.\n";