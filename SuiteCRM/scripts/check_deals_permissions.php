<?php
/**
 * Script to check Deals module permissions
 */

// SuiteCRM bootstrap
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');
require_once('modules/ACL/ACLController.php');

global $current_user, $db;

// Set current user to admin for testing
$current_user = new User();
$current_user->getSystemUser();

echo "Checking Deals module permissions...\n\n";

// Check if Deals module exists
echo "1. Checking if Deals module exists:\n";
$modulesQuery = "SELECT name, tab FROM modules WHERE name IN ('Deals', 'Opportunities', 'mdeal_Deals') AND deleted = 0";
$result = $db->query($modulesQuery);
while ($row = $db->fetchByAssoc($result)) {
    echo "   - Module: {$row['name']}, Tab enabled: {$row['tab']}\n";
}

echo "\n2. Checking ACL permissions for admin user:\n";
$actions = ['list', 'view', 'edit', 'delete', 'import', 'export'];
foreach ($actions as $action) {
    $dealsPerm = ACLController::checkAccess('Deals', $action, true);
    $oppPerm = ACLController::checkAccess('Opportunities', $action, true);
    echo "   - $action: Deals=" . ($dealsPerm ? 'YES' : 'NO') . ", Opportunities=" . ($oppPerm ? 'YES' : 'NO') . "\n";
}

echo "\n3. Checking ACL roles:\n";
$rolesQuery = "SELECT id, name FROM acl_roles WHERE deleted = 0";
$result = $db->query($rolesQuery);
while ($row = $db->fetchByAssoc($result)) {
    echo "   - Role: {$row['name']} (ID: {$row['id']})\n";
}

echo "\n4. Checking if Deals module is in module list:\n";
global $moduleList;
if (in_array('Deals', $moduleList)) {
    echo "   - Deals is in moduleList\n";
} else {
    echo "   - Deals is NOT in moduleList\n";
}
if (in_array('Opportunities', $moduleList)) {
    echo "   - Opportunities is in moduleList\n";
}

echo "\n5. Checking Deal bean:\n";
$deal = BeanFactory::newBean('Deals');
if ($deal) {
    echo "   - Deal bean created successfully\n";
    echo "   - Module name: " . $deal->module_name . "\n";
    echo "   - Table name: " . $deal->table_name . "\n";
} else {
    echo "   - Failed to create Deal bean\n";
}

echo "\nDone!\n";