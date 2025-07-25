<?php
/**
 * Simple Deals Module Testing Script
 * Tests basic functionality without triggering logic hooks
 */

// Initialize test environment
define('sugarEntry', true);
require_once('include/entryPoint.php');
require_once('modules/Users/User.php');

// Force admin login for testing
global $current_user;
$current_user = new User();
$current_user->retrieve('1'); // Admin user ID
$current_user->authenticated = true;
$_SESSION['authenticated_user_id'] = $current_user->id;

// Disable logic hooks temporarily
$GLOBALS['disable_date_format'] = true;
$GLOBALS['logic_hook'] = new stdClass();

echo "<!DOCTYPE html><html><head><title>Simple Deals Testing</title></head><body>";
echo "<h1>Deals Module Simple Testing</h1>";

// Test 1: Module Registration
echo "<h2>Test 1: Module Registration</h2>";
global $beanList, $beanFiles, $moduleList;

if (isset($beanList['Deals']) && isset($beanFiles['Deal']) && in_array('Deals', $moduleList)) {
    echo "<p style='color:green'>✓ Module is properly registered</p>";
    echo "<p>Bean: " . $beanList['Deals'] . "</p>";
    echo "<p>File: " . $beanFiles['Deal'] . "</p>";
} else {
    echo "<p style='color:red'>✗ Module registration issue</p>";
}

// Test 2: Bean Instantiation
echo "<h2>Test 2: Bean Instantiation</h2>";
try {
    $deal = BeanFactory::newBean('Deals');
    if ($deal && $deal->module_dir == 'Deals') {
        echo "<p style='color:green'>✓ Deal bean created successfully</p>";
    } else {
        echo "<p style='color:red'>✗ Deal bean creation failed</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Exception: " . $e->getMessage() . "</p>";
}

// Test 3: Database Tables
echo "<h2>Test 3: Database Tables</h2>";
global $db;

// Check opportunities table
$result = $db->query("SHOW TABLES LIKE 'opportunities'");
if ($db->fetchByAssoc($result)) {
    echo "<p style='color:green'>✓ opportunities table exists</p>";
} else {
    echo "<p style='color:red'>✗ opportunities table not found</p>";
}

// Check opportunities_cstm table
$result = $db->query("SHOW TABLES LIKE 'opportunities_cstm'");
if ($db->fetchByAssoc($result)) {
    echo "<p style='color:green'>✓ opportunities_cstm table exists</p>";
} else {
    echo "<p style='color:red'>✗ opportunities_cstm table not found</p>";
}

// Test 4: List Existing Deals
echo "<h2>Test 4: Existing Deals</h2>";
$query = "SELECT o.id, o.name, o.amount, oc.pipeline_stage_c 
          FROM opportunities o
          LEFT JOIN opportunities_cstm oc ON o.id = oc.id_c
          WHERE o.deleted = 0
          LIMIT 5";

$result = $db->query($query);
$count = 0;
echo "<table border='1'><tr><th>Name</th><th>Amount</th><th>Pipeline Stage</th></tr>";
while ($row = $db->fetchByAssoc($result)) {
    $count++;
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['name']) . "</td>";
    echo "<td>$" . number_format($row['amount'], 2) . "</td>";
    echo "<td>" . ($row['pipeline_stage_c'] ?? 'Not set') . "</td>";
    echo "</tr>";
}
echo "</table>";
echo "<p>Found $count deals</p>";

// Test 5: Pipeline View Access
echo "<h2>Test 5: View Access</h2>";
echo "<p><a href='index.php?module=Deals&action=pipeline'>Open Pipeline View</a></p>";
echo "<p><a href='index.php?module=Deals&action=ListView'>Open List View</a></p>";
echo "<p><a href='index.php?module=Deals&action=EditView'>Create New Deal</a></p>";

echo "</body></html>";
?>