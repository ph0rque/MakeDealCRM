<?php
define('sugarEntry', true);
require_once('config.php');
require_once('include/entryPoint.php');
require_once('data/SugarBean.php');
require_once('modules/Deals/Deal.php');
require_once('include/ListView/ListViewData.php');

echo "<h2>Debugging Deals List View</h2>";

// Test 1: Check if Deal bean can retrieve records
echo "<h3>Test 1: Direct Bean Query</h3>";
$deal = new Deal();
$where = "";
$query = $deal->create_new_list_query('', $where);
echo "<pre>Query: " . htmlspecialchars($query) . "</pre>";

// Test the query directly
global $db;
$result = $db->query($query . " LIMIT 5");
$count = 0;
while ($row = $db->fetchByAssoc($result)) {
    $count++;
    echo "Row $count: " . $row['name'] . " (ID: " . $row['id'] . ")<br>";
}

// Test 2: Check ListViewData
echo "<h3>Test 2: ListViewData Query</h3>";
$listViewData = new ListViewData();
$searchWhere = "";
$params = array(
    'massupdate' => false,
    'module' => 'Deals'
);

// Get the data like the list view would
$listData = $listViewData->getListViewData($deal, $searchWhere, 0, -1, array('name'), $params);
echo "<pre>List Data Count: " . count($listData['data']) . "</pre>";

if (count($listData['data']) > 0) {
    echo "First 5 records:<br>";
    $i = 0;
    foreach ($listData['data'] as $record) {
        echo ($i+1) . ". " . $record['NAME'] . " (ID: " . $record['ID'] . ")<br>";
        $i++;
        if ($i >= 5) break;
    }
} else {
    echo "No data returned from getListViewData<br>";
    echo "Query used: " . htmlspecialchars($listData['query']) . "<br>";
}

// Test 3: Check module configuration
echo "<h3>Test 3: Module Configuration</h3>";
global $beanList, $beanFiles, $moduleList;
echo "Module in moduleList: " . (in_array('Deals', $moduleList) ? 'Yes' : 'No') . "<br>";
echo "Bean class: " . ($beanList['Deals'] ?? 'Not set') . "<br>";
echo "Bean file: " . ($beanFiles['Deal'] ?? 'Not set') . "<br>";
echo "Module dir: " . $deal->module_dir . "<br>";
echo "Module name: " . $deal->module_name . "<br>";
echo "Object name: " . $deal->object_name . "<br>";
echo "Table name: " . $deal->table_name . "<br>";

// Test 4: Check ACL
echo "<h3>Test 4: ACL Check</h3>";
global $current_user;
echo "Current user: " . $current_user->user_name . "<br>";
echo "Is admin: " . ($current_user->is_admin ? 'Yes' : 'No') . "<br>";
echo "ACL list access: " . ($deal->ACLAccess('list') ? 'Yes' : 'No') . "<br>";

// Test 5: Check if we're getting the right list view def
echo "<h3>Test 5: List View Definitions</h3>";
$metaDataFile = 'custom/modules/Deals/metadata/listviewdefs.php';
if (file_exists($metaDataFile)) {
    echo "Custom listviewdefs found at: $metaDataFile<br>";
    require($metaDataFile);
    if (isset($listViewDefs['Deals'])) {
        echo "List view defs loaded successfully<br>";
        echo "Fields defined: " . implode(', ', array_keys($listViewDefs['Deals'])) . "<br>";
    }
} else {
    echo "No custom listviewdefs found<br>";
}
?>