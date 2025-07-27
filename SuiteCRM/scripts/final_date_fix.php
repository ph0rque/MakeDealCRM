<?php
/**
 * Final fix for date_closed
 */

if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');

global $db;

echo "Fixing date_closed fields...\n";

// Fix date_closed
$query = "UPDATE opportunities 
          SET date_closed = DATE_ADD(CURDATE(), INTERVAL 30 DAY)
          WHERE (date_closed IS NULL OR date_closed = '' OR date_closed = '0000-00-00') 
          AND deleted = 0";
$db->query($query);

// Verify
$query = "SELECT id, name, date_closed FROM opportunities WHERE deleted = 0 LIMIT 5";
$result = $db->query($query);
while ($row = $db->fetchByAssoc($result)) {
    echo "- {$row['name']}: {$row['date_closed']}\n";
}

echo "\nDone! Deals should now appear in the pipeline.\n";