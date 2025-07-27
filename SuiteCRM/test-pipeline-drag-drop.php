<?php
// Test Pipeline Drag and Drop Functionality

define('sugarEntry', true);
require_once('include/entryPoint.php');
global $db, $current_user;

echo "<h1>Testing Pipeline Drag and Drop</h1>\n\n";

// 1. Check if we have deals in the database
echo "<h2>1. Checking for Deals in Database</h2>\n";
$query = "SELECT 
    d.id,
    d.name,
    d.amount,
    d.sales_stage,
    c.pipeline_stage_c as pipeline_stage
FROM deals d
LEFT JOIN deals_cstm c ON d.id = c.id_c
WHERE d.deleted = 0
LIMIT 5";

$result = $db->query($query);
$dealCount = 0;
echo "<pre>";
while ($row = $db->fetchByAssoc($result)) {
    $dealCount++;
    echo "Deal: {$row['name']}\n";
    echo "  ID: {$row['id']}\n";
    echo "  Amount: \${$row['amount']}\n";
    echo "  Sales Stage: {$row['sales_stage']}\n";
    echo "  Pipeline Stage: {$row['pipeline_stage']}\n\n";
}
echo "</pre>";

if ($dealCount == 0) {
    echo "<p style='color: red;'>No deals found! Creating test deals...</p>\n";
    
    // Create test deals
    $testDeals = [
        ['name' => 'Test Deal 1', 'amount' => 1000000, 'stage' => 'sourcing'],
        ['name' => 'Test Deal 2', 'amount' => 2500000, 'stage' => 'screening'],
        ['name' => 'Test Deal 3', 'amount' => 500000, 'stage' => 'analysis_outreach'],
        ['name' => 'Test Deal 4', 'amount' => 3000000, 'stage' => 'due_diligence'],
        ['name' => 'Test Deal 5', 'amount' => 1500000, 'stage' => 'closing']
    ];
    
    foreach ($testDeals as $dealData) {
        $deal = BeanFactory::newBean('Deals');
        $deal->name = $dealData['name'];
        $deal->amount = $dealData['amount'];
        $deal->sales_stage = 'Prospecting';
        $deal->pipeline_stage_c = $dealData['stage'];
        $deal->assigned_user_id = $current_user->id;
        $deal->date_entered = date('Y-m-d H:i:s');
        $deal->date_modified = date('Y-m-d H:i:s');
        $deal->save();
        
        echo "<p>Created: {$dealData['name']} in stage {$dealData['stage']}</p>\n";
    }
} else {
    echo "<p style='color: green;'>Found $dealCount deals in database.</p>\n";
}

// 2. Test the AJAX endpoint
echo "\n<h2>2. Testing AJAX Endpoint</h2>\n";
echo "<p>Endpoint: index.php?module=Pipelines&action=AjaxHandler</p>\n";

// 3. Show pipeline view link
echo "\n<h2>3. Pipeline View</h2>\n";
echo "<p><a href='index.php?module=Pipelines&action=kanban' target='_blank'>Open Pipeline Kanban View</a></p>\n";

// 4. JavaScript test for drag and drop
echo "\n<h2>4. Drag and Drop Test</h2>\n";
echo "<p>When you open the pipeline view:</p>\n";
echo "<ol>\n";
echo "<li>Try dragging a deal card from one column to another</li>\n";
echo "<li>Check the browser console (F12) for any errors</li>\n";
echo "<li>The deal should move to the new stage when dropped</li>\n";
echo "</ol>\n";

// 5. Show current module configurations
echo "\n<h2>5. Module Configuration</h2>\n";
echo "<pre>";
echo "Deals Module: " . (file_exists('modules/Deals') ? 'EXISTS' : 'NOT FOUND') . "\n";
echo "Custom Deals: " . (file_exists('custom/modules/Deals') ? 'EXISTS' : 'NOT FOUND') . "\n";
echo "Pipelines Module: " . (file_exists('custom/modules/Pipelines') ? 'EXISTS' : 'NOT FOUND') . "\n";
echo "</pre>";

echo "\n<h2>6. Test Manual Stage Transition</h2>\n";
// Get first deal to test with
$testQuery = "SELECT d.id, d.name, c.pipeline_stage_c 
              FROM deals d 
              LEFT JOIN deals_cstm c ON d.id = c.id_c 
              WHERE d.deleted = 0 
              LIMIT 1";
$testResult = $db->query($testQuery);
$testDeal = $db->fetchByAssoc($testResult);

if ($testDeal) {
    echo "<p>Testing with deal: {$testDeal['name']} (ID: {$testDeal['id']})</p>\n";
    echo "<p>Current stage: {$testDeal['pipeline_stage_c']}</p>\n";
    
    // Show test button
    echo <<<HTML
<button onclick="testStageTransition('{$testDeal['id']}', '{$testDeal['pipeline_stage_c']}', 'screening')">
    Test Move to Screening
</button>

<script>
function testStageTransition(dealId, fromStage, toStage) {
    console.log('Testing stage transition:', { dealId, fromStage, toStage });
    
    fetch('index.php?module=Pipelines&action=AjaxHandler', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            action: 'executeStageTransition',
            data: {
                dealId: dealId,
                fromStage: fromStage,
                toStage: toStage,
                reason: 'Test transition',
                override: false
            }
        })
    })
    .then(response => response.json())
    .then(data => {
        console.log('Response:', data);
        if (data.success) {
            alert('Success! Deal moved to ' + toStage);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error: ' + error.message);
    });
}
</script>
HTML;
}

?>