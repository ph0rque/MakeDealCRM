<?php
define('sugarEntry', true);
require_once('config.php');
require_once('data/SugarBean.php');
require_once('modules/Deals/Deal.php');

$deal = new Deal();

echo "<h2>Deal Bean Fields:</h2><pre>";

// Show field defs
echo "Field definitions from vardefs:\n";
global $dictionary;
if (isset($dictionary['Deal']['fields'])) {
    foreach ($dictionary['Deal']['fields'] as $field => $def) {
        if (strpos($field, 'focus') !== false) {
            echo "  $field => " . json_encode($def) . "\n";
        }
    }
}

echo "\n\nActual object properties:\n";
$vars = get_object_vars($deal);
foreach ($vars as $var => $value) {
    if (strpos($var, 'focus') !== false) {
        echo "  $var => $value\n";
    }
}

echo "\n\nColumn fields mapping:\n";
if (isset($deal->column_fields) && is_array($deal->column_fields)) {
    foreach ($deal->column_fields as $field) {
        if (strpos($field, 'focus') !== false) {
            echo "  $field\n";
        }
    }
}

echo "</pre>";
?>