<?php
/**
 * Script to properly register Deals module in database
 */

// SuiteCRM bootstrap
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');

global $current_user, $db;

// Set current user to admin
$current_user = new User();
$current_user->getSystemUser();

echo "Registering Deals module in database...\n\n";

// Check if module already exists
$checkQuery = "SELECT * FROM modules WHERE name = 'Deals'";
$result = $db->query($checkQuery);
$existingModule = $db->fetchByAssoc($result);

if ($existingModule) {
    if ($existingModule['deleted'] == 1) {
        // Undelete it
        echo "Module exists but is deleted. Restoring it...\n";
        $updateQuery = "UPDATE modules SET deleted = 0, tab = 1, date_modified = NOW() WHERE name = 'Deals'";
        $db->query($updateQuery);
    } else {
        // Ensure tab is enabled
        echo "Module exists. Ensuring tab is enabled...\n";
        $updateQuery = "UPDATE modules SET tab = 1, date_modified = NOW() WHERE name = 'Deals'";
        $db->query($updateQuery);
    }
} else {
    // Create new module entry
    echo "Creating new module entry...\n";
    $insertQuery = "INSERT INTO modules (id, name, date_entered, date_modified, modified_user_id, created_by, deleted, tab) 
                    VALUES (UUID(), 'Deals', NOW(), NOW(), '1', '1', 0, 1)";
    $db->query($insertQuery);
}

// Verify the module is registered
$verifyQuery = "SELECT * FROM modules WHERE name = 'Deals' AND deleted = 0";
$result = $db->query($verifyQuery);
if ($row = $db->fetchByAssoc($result)) {
    echo "\nModule successfully registered:\n";
    echo "- ID: " . $row['id'] . "\n";
    echo "- Name: " . $row['name'] . "\n";
    echo "- Tab enabled: " . ($row['tab'] ? 'YES' : 'NO') . "\n";
} else {
    echo "\nERROR: Failed to register module!\n";
}

// Clear cache
echo "\nClearing cache...\n";
if (file_exists('cache/modules/Deals')) {
    $files = glob('cache/modules/Deals/*');
    foreach($files as $file) {
        if(is_file($file)) {
            unlink($file);
        }
    }
}

// Rebuild menus
echo "Rebuilding menus...\n";
require_once('ModuleInstall/ModuleInstaller.php');
$mi = new ModuleInstaller();
$mi->rebuild_menus();

echo "\nDone! Please logout and login again for changes to take effect.\n";