<?php
require_once('config.php');

$db = mysqli_connect(
    $sugar_config['dbconfig']['db_host_name'],
    $sugar_config['dbconfig']['db_user_name'],
    $sugar_config['dbconfig']['db_password'],
    $sugar_config['dbconfig']['db_name']
);

if (!$db) {
    die('Could not connect: ' . mysqli_error($db));
}

echo "<h2>Opportunities Table Structure:</h2><pre>";
$result = mysqli_query($db, 'DESCRIBE opportunities');
while ($row = mysqli_fetch_array($result)) {
    echo str_pad($row['Field'], 30) . " | " . $row['Type'] . "\n";
}
echo "</pre>";

// Check which custom fields exist
echo "<h2>Missing Custom Fields for Deals Module:</h2><pre>";
$required_fields = [
    'status' => 'varchar(255)',
    'focus_c' => 'tinyint(1)',
    'date_in_current_stage' => 'datetime',
    'pipeline_stage_c' => 'varchar(255)',
    'is_archived' => 'tinyint(1)',
    'asking_price_c' => 'decimal(26,6)',
    'ttm_revenue_c' => 'decimal(26,6)',
    'ttm_ebitda_c' => 'decimal(26,6)',
    'sde_c' => 'decimal(26,6)',
    'proposed_valuation_c' => 'decimal(26,6)',
    'target_multiple_c' => 'decimal(10,2)',
    'equity_c' => 'decimal(26,6)',
    'senior_debt_c' => 'decimal(26,6)',
    'seller_note_c' => 'decimal(26,6)',
    'at_risk_status' => 'varchar(50)',
    'days_in_stage' => 'int(11)',
    'source' => 'varchar(50)',
    'deal_value' => 'decimal(26,6)'
];

$result = mysqli_query($db, 'DESCRIBE opportunities');
$existing_fields = [];
while ($row = mysqli_fetch_array($result)) {
    $existing_fields[] = $row['Field'];
}

foreach ($required_fields as $field => $type) {
    if (!in_array($field, $existing_fields)) {
        echo "Missing field: $field ($type)\n";
    }
}
echo "</pre>";

mysqli_close($db);
?>