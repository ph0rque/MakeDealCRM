<?php
define('sugarEntry', true);
require_once('config.php');
require_once('include/entryPoint.php');

// Test creating a deal using the Deal bean
require_once('modules/Deals/Deal.php');

$deal = new Deal();
$deal->module_dir = 'Deals';
$deal->module_name = 'Deals';

// Override the create_new_list_query method to debug
class DealFixed extends Deal {
    public function create_new_list_query($order_by, $where, $filter = array(), $params = array(), $show_deleted = 0, $join_type = '', $return_array = false, $parentbean = null, $singleSelect = false, $ifListForExport = false) {
        // Simple query for testing
        $query = "SELECT * FROM opportunities WHERE deleted = 0";
        if ($order_by) {
            $query .= " ORDER BY $order_by";
        }
        return $query;
    }
}

echo "<h2>Testing Fixed Deal List Query</h2>";

$dealFixed = new DealFixed();
$query = $dealFixed->create_new_list_query('name', '');
echo "<h3>Query generated:</h3><pre>" . $query . "</pre>";

// Test the query
global $db;
$result = $db->query($query . " LIMIT 5");
echo "<h3>Results:</h3><pre>";
while ($row = $db->fetchByAssoc($result)) {
    echo "ID: " . $row['id'] . " - Name: " . $row['name'] . "\n";
}
echo "</pre>";

// Test if we can properly list deals
$list = $dealFixed->get_list('name', '', 0, 5);
echo "<h3>get_list results:</h3><pre>";
echo "Row count: " . $list['row_count'] . "\n";
if ($list['list']) {
    foreach ($list['list'] as $deal) {
        echo "ID: " . $deal->id . " - Name: " . $deal->name . "\n";
    }
}
echo "</pre>";
?>