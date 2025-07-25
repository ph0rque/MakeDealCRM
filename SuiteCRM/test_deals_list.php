<?php
define('sugarEntry', true);
require_once('config.php');
require_once('include/entryPoint.php');
require_once('include/database/DBManagerFactory.php');

global $db;

echo "<h2>Testing Deals List Query</h2>";

// Test regular opportunities query
$query1 = "SELECT * FROM opportunities WHERE deleted = 0 LIMIT 5";
$result1 = $db->query($query1);
echo "<h3>Opportunities table (first 5 records):</h3><pre>";
while ($row = $db->fetchByAssoc($result1)) {
    echo "ID: " . $row['id'] . " - Name: " . $row['name'] . " - Amount: $" . number_format($row['amount'], 2) . "\n";
}
echo "</pre>";

// Test if records exist
$countQuery = "SELECT COUNT(*) as total FROM opportunities WHERE deleted = 0";
$result2 = $db->query($countQuery);
$row = $db->fetchByAssoc($result2);
echo "<p>Total non-deleted opportunities: " . $row['total'] . "</p>";

// Test the bean factory approach
require_once('data/BeanFactory.php');
$bean = BeanFactory::newBean('Deals');
echo "<h3>Bean Details:</h3><pre>";
echo "Module: " . $bean->module_name . "\n";
echo "Object: " . $bean->object_name . "\n";
echo "Table: " . $bean->table_name . "\n";
echo "</pre>";

// Try to get deals using bean
$bean = BeanFactory::newBean('Deals');
$bean_list = $bean->get_full_list();
echo "<h3>Deals via Bean (get_full_list):</h3><pre>";
if ($bean_list) {
    foreach ($bean_list as $deal) {
        echo "ID: " . $deal->id . " - Name: " . $deal->name . "\n";
    }
} else {
    echo "No results from get_full_list\n";
}
echo "</pre>";

// Check module configuration
global $moduleList, $beanList, $beanFiles;
echo "<h3>Module Configuration:</h3><pre>";
echo "In moduleList: " . (in_array('Deals', $moduleList) ? 'Yes' : 'No') . "\n";
echo "Bean class: " . ($beanList['Deals'] ?? 'Not set') . "\n";
echo "Bean file: " . ($beanFiles['Deal'] ?? 'Not set') . "\n";
echo "</pre>";
?>