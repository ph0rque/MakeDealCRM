<?php
/**
 * Seed Deals Data Script
 * Creates sample deals for testing and development
 */

if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');

global $current_user, $db;

// Set current user to admin
$current_user = new User();
$current_user->getSystemUser();

echo "Starting deals seeding process...\n";

// First, ensure opportunities_cstm table exists with pipeline_stage_c column
$checkCustomTable = "SHOW TABLES LIKE 'opportunities_cstm'";
$result = $db->query($checkCustomTable);
if ($db->getRowCount($result) == 0) {
    echo "Creating opportunities_cstm table...\n";
    $createTable = "CREATE TABLE opportunities_cstm (
        id_c CHAR(36) NOT NULL PRIMARY KEY,
        pipeline_stage_c VARCHAR(100) DEFAULT NULL
    )";
    $db->query($createTable);
}

// Check if pipeline_stage_c column exists
$checkColumn = "SHOW COLUMNS FROM opportunities_cstm LIKE 'pipeline_stage_c'";
$result = $db->query($checkColumn);
if ($db->getRowCount($result) == 0) {
    echo "Adding pipeline_stage_c column...\n";
    $addColumn = "ALTER TABLE opportunities_cstm ADD COLUMN pipeline_stage_c VARCHAR(100) DEFAULT NULL";
    $db->query($addColumn);
}

// Sample deal data
$deals = [
    [
        'name' => 'Enterprise Software License - Acme Corp',
        'sales_stage' => 'Prospecting',
        'pipeline_stage_c' => 'sourcing',
        'amount' => 125000,
        'probability' => 20,
        'description' => 'Large enterprise software license deal with Acme Corporation',
        'lead_source' => 'Partner',
        'date_closed' => '+45 days'
    ],
    [
        'name' => 'Cloud Migration Services - Tech Startup',
        'sales_stage' => 'Qualification',
        'pipeline_stage_c' => 'qualifying',
        'amount' => 75000,
        'probability' => 30,
        'description' => 'Cloud migration and consulting services',
        'lead_source' => 'Web Site',
        'date_closed' => '+30 days'
    ],
    [
        'name' => 'Annual Support Contract - Global Finance',
        'sales_stage' => 'Needs Analysis',
        'pipeline_stage_c' => 'pitching',
        'amount' => 50000,
        'probability' => 40,
        'description' => 'Annual support and maintenance contract renewal',
        'lead_source' => 'Existing Customer',
        'date_closed' => '+60 days'
    ],
    [
        'name' => 'Custom Development Project - Healthcare Co',
        'sales_stage' => 'Value Proposition',
        'pipeline_stage_c' => 'negotiating',
        'amount' => 200000,
        'probability' => 50,
        'description' => 'Custom healthcare platform development',
        'lead_source' => 'Trade Show',
        'date_closed' => '+90 days'
    ],
    [
        'name' => 'Security Audit Services - Bank of Commerce',
        'sales_stage' => 'Id. Decision Makers',
        'pipeline_stage_c' => 'closing',
        'amount' => 35000,
        'probability' => 60,
        'description' => 'Comprehensive security audit and penetration testing',
        'lead_source' => 'Cold Call',
        'date_closed' => '+15 days'
    ],
    [
        'name' => 'Data Analytics Platform - Retail Chain',
        'sales_stage' => 'Perception Analysis',
        'pipeline_stage_c' => 'won',
        'amount' => 95000,
        'probability' => 70,
        'description' => 'Advanced analytics and reporting platform',
        'lead_source' => 'Campaign',
        'date_closed' => '+21 days'
    ],
    [
        'name' => 'Mobile App Development - StartupXYZ',
        'sales_stage' => 'Proposal/Price Quote',
        'pipeline_stage_c' => 'negotiating',
        'amount' => 45000,
        'probability' => 75,
        'description' => 'Mobile application for iOS and Android platforms',
        'lead_source' => 'Partner',
        'date_closed' => '+14 days'
    ],
    [
        'name' => 'API Integration Services - Logistics Corp',
        'sales_stage' => 'Negotiation/Review',
        'pipeline_stage_c' => 'closing',
        'amount' => 60000,
        'probability' => 80,
        'description' => 'API integration with third-party logistics systems',
        'lead_source' => 'Web Site',
        'date_closed' => '+7 days'
    ],
    [
        'name' => 'Training and Certification Program',
        'sales_stage' => 'Closed Won',
        'pipeline_stage_c' => 'won',
        'amount' => 25000,
        'probability' => 100,
        'description' => 'Corporate training program for 50 employees',
        'lead_source' => 'Existing Customer',
        'date_closed' => 'today'
    ],
    [
        'name' => 'Infrastructure Upgrade - Media Company',
        'sales_stage' => 'Prospecting',
        'pipeline_stage_c' => 'sourcing',
        'amount' => 150000,
        'probability' => 15,
        'description' => 'Complete infrastructure modernization project',
        'lead_source' => 'Conference',
        'date_closed' => '+120 days'
    ]
];

// Get list of users for random assignment
$userQuery = "SELECT id FROM users WHERE deleted = 0 AND status = 'Active' LIMIT 10";
$userResult = $db->query($userQuery);
$userIds = [];
while ($row = $db->fetchByAssoc($userResult)) {
    $userIds[] = $row['id'];
}

// If no active users, use admin
if (empty($userIds)) {
    $userIds = [$current_user->id];
}

// Create deals
$createdCount = 0;
foreach ($deals as $dealData) {
    try {
        $deal = BeanFactory::newBean('Deals');
        
        // Set basic fields
        $deal->name = $dealData['name'];
        $deal->sales_stage = $dealData['sales_stage'];
        $deal->amount = $dealData['amount'];
        $deal->probability = $dealData['probability'];
        $deal->description = $dealData['description'];
        $deal->lead_source = $dealData['lead_source'];
        
        // Calculate close date
        if ($dealData['date_closed'] === 'today') {
            $deal->date_closed = date('Y-m-d');
        } else {
            $deal->date_closed = date('Y-m-d', strtotime($dealData['date_closed']));
        }
        
        // Assign to random user
        $deal->assigned_user_id = $userIds[array_rand($userIds)];
        
        // Set currency
        $deal->currency_id = '-99'; // Default currency
        $deal->amount_usdollar = $dealData['amount'];
        
        // Save the deal
        $dealId = $deal->save();
        
        if ($dealId) {
            // Set custom field using direct SQL to ensure it's saved
            $updateCustom = "INSERT INTO opportunities_cstm (id_c, pipeline_stage_c) 
                           VALUES ('$dealId', '{$dealData['pipeline_stage_c']}')
                           ON DUPLICATE KEY UPDATE pipeline_stage_c = '{$dealData['pipeline_stage_c']}'";
            $db->query($updateCustom);
            
            $createdCount++;
            echo "Created deal: {$dealData['name']} (ID: $dealId)\n";
        }
        
    } catch (Exception $e) {
        echo "Error creating deal '{$dealData['name']}': " . $e->getMessage() . "\n";
    }
}

echo "\nSeeding completed! Created $createdCount deals.\n";

// Verify the results
$countQuery = "SELECT COUNT(*) as total FROM opportunities WHERE deleted = 0";
$result = $db->query($countQuery);
$row = $db->fetchByAssoc($result);
echo "Total deals in database: {$row['total']}\n";

// Clear cache to ensure fresh data
echo "\nClearing cache...\n";
$cacheDirectories = [
    'cache/modules/Deals/',
    'cache/themes/',
    'cache/smarty/templates_c/'
];

foreach ($cacheDirectories as $dir) {
    if (is_dir($dir)) {
        $files = glob($dir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
}

echo "Cache cleared successfully!\n";
echo "\nYou can now access deals at: http://localhost:8080/index.php?module=Deals&action=index\n";