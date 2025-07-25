<?php
/**
 * Direct Deals Module Test - Bypasses login
 */

// Start session
session_start();
define('sugarEntry', true);
require_once('include/entryPoint.php');
require_once('modules/Users/User.php');

// Force admin authentication
global $current_user;
$current_user = new User();
$current_user->retrieve('1');
$current_user->authenticated = true;
$_SESSION['authenticated_user_id'] = $current_user->id;

?>
<!DOCTYPE html>
<html>
<head>
    <title>Deals Module Direct Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .section { margin: 20px 0; padding: 20px; background: #f5f5f5; border-radius: 5px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { color: blue; }
        button { padding: 10px 20px; margin: 5px; cursor: pointer; }
        .pipeline-container { display: flex; gap: 10px; overflow-x: auto; margin: 20px 0; }
        .stage { min-width: 200px; background: white; border: 1px solid #ccc; padding: 10px; border-radius: 5px; }
        .stage h3 { margin-top: 0; }
        .deal { background: #e0e0e0; padding: 10px; margin: 5px 0; border-radius: 3px; cursor: pointer; }
        .deal:hover { background: #d0d0d0; }
    </style>
</head>
<body>

<h1>Deals Module Direct Test</h1>

<div class="section">
    <h2>Test 1: Module Access</h2>
    <?php
    global $beanList, $beanFiles, $moduleList;
    
    if (isset($beanList['Deals']) && isset($beanFiles['Deal']) && in_array('Deals', $moduleList)) {
        echo '<p class="success">✓ Module is properly registered</p>';
        echo '<p>Bean: ' . $beanList['Deals'] . '</p>';
        echo '<p>File: ' . $beanFiles['Deal'] . '</p>';
    } else {
        echo '<p class="error">✗ Module registration issue</p>';
    }
    ?>
    
    <button onclick="window.location.href='index.php?module=Deals&action=index'">Go to Deals Module</button>
    <button onclick="window.location.href='index.php?module=Deals&action=pipeline'">Go to Pipeline View</button>
</div>

<div class="section">
    <h2>Test 2: Create Test Deal</h2>
    <form method="post">
        <p>
            Deal Name: <input type="text" name="deal_name" value="Test Deal <?php echo date('Y-m-d H:i:s'); ?>" size="40">
        </p>
        <p>
            Amount: <input type="text" name="deal_amount" value="50000" size="20">
        </p>
        <p>
            Pipeline Stage: 
            <select name="pipeline_stage">
                <option value="sourcing">Sourcing</option>
                <option value="screening">Screening</option>
                <option value="analysis_outreach">Analysis & Outreach</option>
                <option value="due_diligence">Due Diligence</option>
                <option value="closing">Closing</option>
            </select>
        </p>
        <button type="submit" name="create_deal">Create Deal</button>
    </form>
    
    <?php
    if (isset($_POST['create_deal'])) {
        $deal = BeanFactory::newBean('Deals');
        $deal->name = $_POST['deal_name'];
        $deal->amount = $_POST['deal_amount'];
        $deal->pipeline_stage_c = $_POST['pipeline_stage'];
        $deal->assigned_user_id = $current_user->id;
        $deal->save();
        
        if (!empty($deal->id)) {
            echo '<p class="success">✓ Deal created successfully! ID: ' . $deal->id . '</p>';
            echo '<p><a href="index.php?module=Deals&action=DetailView&record=' . $deal->id . '">View Deal</a></p>';
        } else {
            echo '<p class="error">✗ Failed to create deal</p>';
        }
    }
    ?>
</div>

<div class="section">
    <h2>Test 3: Pipeline Display</h2>
    
    <?php
    $stages = array(
        'sourcing' => 'Sourcing',
        'screening' => 'Screening',
        'analysis_outreach' => 'Analysis & Outreach',
        'due_diligence' => 'Due Diligence',
        'closing' => 'Closing'
    );
    
    echo '<div class="pipeline-container">';
    
    foreach ($stages as $stage_key => $stage_name) {
        echo '<div class="stage">';
        echo '<h3>' . $stage_name . '</h3>';
        
        // Get deals for this stage
        $query = "SELECT o.id, o.name, o.amount 
                  FROM opportunities o
                  LEFT JOIN opportunities_cstm oc ON o.id = oc.id_c
                  WHERE o.deleted = 0 
                  AND (oc.pipeline_stage_c = '$stage_key' OR (oc.pipeline_stage_c IS NULL AND '$stage_key' = 'sourcing'))
                  ORDER BY o.date_modified DESC
                  LIMIT 5";
        
        $result = $GLOBALS['db']->query($query);
        $count = 0;
        
        while ($row = $GLOBALS['db']->fetchByAssoc($result)) {
            $count++;
            echo '<div class="deal" onclick="window.location.href=\'index.php?module=Deals&action=DetailView&record=' . $row['id'] . '\'">';
            echo '<strong>' . htmlspecialchars($row['name']) . '</strong><br>';
            echo 'Amount: $' . number_format($row['amount'], 2);
            echo '</div>';
        }
        
        if ($count == 0) {
            echo '<p class="info">No deals in this stage</p>';
        } else {
            echo '<p class="info">Total: ' . $count . ' deals</p>';
        }
        
        echo '</div>';
    }
    
    echo '</div>';
    ?>
</div>

<div class="section">
    <h2>Test 4: Recent Deals</h2>
    <?php
    $query = "SELECT o.id, o.name, o.amount, o.date_modified, oc.pipeline_stage_c
              FROM opportunities o
              LEFT JOIN opportunities_cstm oc ON o.id = oc.id_c
              WHERE o.deleted = 0
              ORDER BY o.date_modified DESC
              LIMIT 10";
    
    $result = $GLOBALS['db']->query($query);
    
    echo '<table border="1" cellpadding="5" cellspacing="0">';
    echo '<tr><th>Name</th><th>Amount</th><th>Stage</th><th>Modified</th><th>Actions</th></tr>';
    
    while ($row = $GLOBALS['db']->fetchByAssoc($result)) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['name']) . '</td>';
        echo '<td>$' . number_format($row['amount'], 2) . '</td>';
        echo '<td>' . ($stages[$row['pipeline_stage_c']] ?? 'Unknown') . '</td>';
        echo '<td>' . $row['date_modified'] . '</td>';
        echo '<td>';
        echo '<a href="index.php?module=Deals&action=DetailView&record=' . $row['id'] . '">View</a> | ';
        echo '<a href="index.php?module=Deals&action=EditView&record=' . $row['id'] . '">Edit</a>';
        echo '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    ?>
</div>

<div class="section">
    <h2>Quick Actions</h2>
    <button onclick="window.location.href='index.php?module=Deals&action=EditView'">Create New Deal</button>
    <button onclick="window.location.href='index.php?module=Deals&action=index'">View Pipeline</button>
    <button onclick="window.location.href='index.php?module=Deals&action=ListView'">List View</button>
    <button onclick="window.location.href='index.php?module=Administration&action=index'">Admin Panel</button>
</div>

</body>
</html>