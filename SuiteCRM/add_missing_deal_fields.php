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

echo "<h2>Adding Missing Fields to Opportunities Table:</h2><pre>";

// Define the fields to add
$fields_to_add = [
    'status' => "ALTER TABLE opportunities ADD COLUMN status varchar(255) DEFAULT 'Sourcing'",
    'date_in_current_stage' => "ALTER TABLE opportunities ADD COLUMN date_in_current_stage datetime",
    'asking_price_c' => "ALTER TABLE opportunities ADD COLUMN asking_price_c decimal(26,6)",
    'ttm_revenue_c' => "ALTER TABLE opportunities ADD COLUMN ttm_revenue_c decimal(26,6)",
    'ttm_ebitda_c' => "ALTER TABLE opportunities ADD COLUMN ttm_ebitda_c decimal(26,6)",
    'sde_c' => "ALTER TABLE opportunities ADD COLUMN sde_c decimal(26,6)",
    'proposed_valuation_c' => "ALTER TABLE opportunities ADD COLUMN proposed_valuation_c decimal(26,6)",
    'target_multiple_c' => "ALTER TABLE opportunities ADD COLUMN target_multiple_c decimal(10,2)",
    'equity_c' => "ALTER TABLE opportunities ADD COLUMN equity_c decimal(26,6)",
    'senior_debt_c' => "ALTER TABLE opportunities ADD COLUMN senior_debt_c decimal(26,6)",
    'seller_note_c' => "ALTER TABLE opportunities ADD COLUMN seller_note_c decimal(26,6)",
    'at_risk_status' => "ALTER TABLE opportunities ADD COLUMN at_risk_status varchar(50) DEFAULT 'Normal'",
    'days_in_stage' => "ALTER TABLE opportunities ADD COLUMN days_in_stage int(11) DEFAULT 0",
    'source' => "ALTER TABLE opportunities ADD COLUMN source varchar(50)",
    'deal_value' => "ALTER TABLE opportunities ADD COLUMN deal_value decimal(26,6)"
];

// Check which fields already exist
$result = mysqli_query($db, 'DESCRIBE opportunities');
$existing_fields = [];
while ($row = mysqli_fetch_array($result)) {
    $existing_fields[] = $row['Field'];
}

// Add missing fields
foreach ($fields_to_add as $field => $query) {
    if (!in_array($field, $existing_fields)) {
        if (mysqli_query($db, $query)) {
            echo "✓ Added field: $field\n";
        } else {
            echo "✗ Error adding field $field: " . mysqli_error($db) . "\n";
        }
    } else {
        echo "- Field already exists: $field\n";
    }
}

// Note: focus_c is not added because focus_flag_c already exists and serves the same purpose

echo "\n<strong>Done!</strong></pre>";

echo "<p><a href='index.php?module=Deals&action=EditView'>Try creating a deal again</a></p>";

mysqli_close($db);
?>