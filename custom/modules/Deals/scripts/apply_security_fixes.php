<?php
/**
 * Security Fixes Application Script
 * Run this script to apply all security fixes to the Deals module
 * 
 * Usage: php apply_security_fixes.php
 */

if (!defined('sugarEntry')) define('sugarEntry', true);

// Change to SuiteCRM root directory
chdir(dirname(__FILE__) . '/../../../../');

require_once('include/entryPoint.php');
require_once('modules/Administration/QuickRepairAndRebuild.php');

echo "=== Deals Module Security Fixes Application ===\n\n";

// Check if running as admin
global $current_user;
if (empty($current_user->id) || !$current_user->is_admin) {
    die("ERROR: This script must be run by an administrator user.\n");
}

$errors = [];
$warnings = [];
$success = [];

// Step 1: Backup current files
echo "Step 1: Creating backups...\n";
$backup_dir = 'custom/modules/Deals/backup_' . date('Y-m-d_H-i-s');
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

$files_to_backup = [
    'custom/modules/Deals/Deal.php',
    'custom/modules/Deals/controller.php',
    'custom/modules/Deals/views/view.pipeline.php',
    'custom/modules/Deals/tpls/pipeline.tpl'
];

foreach ($files_to_backup as $file) {
    if (file_exists($file)) {
        $backup_file = $backup_dir . '/' . basename($file);
        if (copy($file, $backup_file)) {
            $success[] = "Backed up: $file";
        } else {
            $warnings[] = "Failed to backup: $file";
        }
    }
}

// Step 2: Check for secure files
echo "\nStep 2: Checking for secure files...\n";
$secure_files = [
    'DealsSecurityHelper.php' => 'custom/modules/Deals/DealsSecurityHelper.php',
    'Deal_secure.php' => 'custom/modules/Deals/Deal_secure.php',
    'controller_secure_full.php' => 'custom/modules/Deals/controller_secure_full.php',
    'view.pipeline_secure.php' => 'custom/modules/Deals/views/view.pipeline_secure.php',
    'pipeline_secure.tpl' => 'custom/modules/Deals/tpls/pipeline_secure.tpl'
];

$all_files_exist = true;
foreach ($secure_files as $name => $path) {
    if (!file_exists($path)) {
        $errors[] = "Missing secure file: $name at $path";
        $all_files_exist = false;
    } else {
        $success[] = "Found secure file: $name";
    }
}

if (!$all_files_exist) {
    echo "\nERROR: Not all secure files are present. Please ensure all files are uploaded.\n";
    printResults();
    exit(1);
}

// Step 3: Apply security fixes
echo "\nStep 3: Applying security fixes...\n";

// Copy security helper (new file, no replacement needed)
if (!file_exists('custom/modules/Deals/DealsSecurityHelper.php')) {
    // Already exists from our check above
    $success[] = "Security helper already in place";
}

// Replace Deal.php with secure version
if (copy('custom/modules/Deals/Deal_secure.php', 'custom/modules/Deals/Deal.php')) {
    $success[] = "Updated Deal.php with secure version";
} else {
    $errors[] = "Failed to update Deal.php";
}

// Replace controller.php with secure version
if (copy('custom/modules/Deals/controller_secure_full.php', 'custom/modules/Deals/controller.php')) {
    $success[] = "Updated controller.php with secure version";
} else {
    $errors[] = "Failed to update controller.php";
}

// Replace view.pipeline.php with secure version
if (copy('custom/modules/Deals/views/view.pipeline_secure.php', 'custom/modules/Deals/views/view.pipeline.php')) {
    $success[] = "Updated view.pipeline.php with secure version";
} else {
    $errors[] = "Failed to update view.pipeline.php";
}

// Replace pipeline.tpl with secure version
if (copy('custom/modules/Deals/tpls/pipeline_secure.tpl', 'custom/modules/Deals/tpls/pipeline.tpl')) {
    $success[] = "Updated pipeline.tpl with secure version";
} else {
    $errors[] = "Failed to update pipeline.tpl";
}

// Step 4: Create security log table
echo "\nStep 4: Creating security log table...\n";
global $db;

$createTable = "CREATE TABLE IF NOT EXISTS deals_security_log (
    id CHAR(36) NOT NULL PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL,
    message TEXT,
    user_id CHAR(36),
    ip_address VARCHAR(45),
    event_data LONGTEXT,
    created_date DATETIME NOT NULL,
    KEY idx_event_type (event_type),
    KEY idx_user_id (user_id),
    KEY idx_created_date (created_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($db->query($createTable)) {
    $success[] = "Security log table created/verified";
} else {
    $warnings[] = "Could not create security log table (may already exist)";
}

// Step 5: Update JavaScript files for CSRF
echo "\nStep 5: Checking JavaScript files...\n";
$js_files = [
    'custom/modules/Deals/js/pipeline.js',
    'custom/modules/Deals/js/stakeholder-integration.js',
    'custom/modules/Deals/js/wip-limit-manager.js'
];

foreach ($js_files as $js_file) {
    if (file_exists($js_file)) {
        $content = file_get_contents($js_file);
        if (strpos($content, 'csrf_token') === false) {
            $warnings[] = "JavaScript file may need CSRF token updates: $js_file";
        } else {
            $success[] = "JavaScript file appears to have CSRF support: $js_file";
        }
    }
}

// Step 6: Clear caches
echo "\nStep 6: Clearing caches...\n";
if (is_dir('cache')) {
    // Clear Smarty cache
    if (is_dir('cache/smarty')) {
        deleteDirectory('cache/smarty/cache');
        deleteDirectory('cache/smarty/templates_c');
        $success[] = "Cleared Smarty cache";
    }
    
    // Clear other caches
    if (is_dir('cache/modules')) {
        deleteDirectory('cache/modules/Deals');
        $success[] = "Cleared Deals module cache";
    }
}

// Step 7: Run Quick Repair and Rebuild
echo "\nStep 7: Running Quick Repair and Rebuild...\n";
$repair = new RepairAndClear();
$repair->repairAndClearAll(['clearAll'], ['Deals'], false, true);
$success[] = "Completed Quick Repair and Rebuild";

// Print results
printResults();

// Create completion log
$log_entry = [
    'date' => date('Y-m-d H:i:s'),
    'user' => $current_user->user_name,
    'backup_dir' => $backup_dir,
    'errors' => count($errors),
    'warnings' => count($warnings),
    'success' => count($success)
];

file_put_contents(
    'custom/modules/Deals/security_fixes_applied.log',
    json_encode($log_entry) . "\n",
    FILE_APPEND
);

echo "\n=== Security fixes application completed ===\n";
echo "Backup created at: $backup_dir\n";
echo "Log file: custom/modules/Deals/security_fixes_applied.log\n";

if (count($errors) > 0) {
    echo "\nIMPORTANT: There were errors during the process. Please review and fix them.\n";
    exit(1);
} else {
    echo "\nSUCCESS: All security fixes have been applied successfully.\n";
    echo "\nNEXT STEPS:\n";
    echo "1. Test all Deals module functionality\n";
    echo "2. Update JavaScript files to include CSRF tokens if warnings were shown\n";
    echo "3. Monitor security logs for any issues\n";
    echo "4. Review SECURITY_FIXES_COMPLETE.md for detailed information\n";
}

// Helper functions
function deleteDirectory($dir) {
    if (!is_dir($dir)) return;
    
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = "$dir/$file";
        is_dir($path) ? deleteDirectory($path) : unlink($path);
    }
    rmdir($dir);
}

function printResults() {
    global $errors, $warnings, $success;
    
    echo "\n--- Results ---\n";
    
    if (count($success) > 0) {
        echo "\nSuccess (" . count($success) . "):\n";
        foreach ($success as $msg) {
            echo "  ✓ $msg\n";
        }
    }
    
    if (count($warnings) > 0) {
        echo "\nWarnings (" . count($warnings) . "):\n";
        foreach ($warnings as $msg) {
            echo "  ⚠ $msg\n";
        }
    }
    
    if (count($errors) > 0) {
        echo "\nErrors (" . count($errors) . "):\n";
        foreach ($errors as $msg) {
            echo "  ✗ $msg\n";
        }
    }
}