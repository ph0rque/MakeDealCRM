<?php
/**
 * Fixed debug script for Deals module
 */

// Clean output buffer and handle session before any output
while (ob_get_level()) {
    ob_end_clean();
}

// Start session before any output
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define sugarEntry before including files
define('sugarEntry', true);

// Start output buffering
ob_start();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Deals Module Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        h2, h3 { color: #333; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>

<h2>Deals Module Debug Script</h2>

<?php
// Step 1: Load SuiteCRM
echo "<h3>Step 1: Loading SuiteCRM</h3>";
try {
    require_once('include/entryPoint.php');
    echo "<p class='success'>✓ SuiteCRM loaded successfully</p>";
} catch (Exception $e) {
    echo "<p class='error'>✗ Error loading SuiteCRM: " . $e->getMessage() . "</p>";
    exit;
}

// Step 2: Check database connection
echo "<h3>Step 2: Database Check</h3>";
global $db;

// Check if $db object exists
if (!isset($db) || !is_object($db)) {
    echo "<p class='error'>✗ Database object not initialized</p>";
    
    // Try to initialize it
    echo "<p>Attempting to initialize database connection...</p>";
    require_once('include/database/DBManagerFactory.php');
    $db = DBManagerFactory::getInstance();
}

// Check connection using a simple query
try {
    $testQuery = "SELECT 1";
    $result = $db->query($testQuery);
    if ($result) {
        echo "<p class='success'>✓ Database query successful</p>";
        
        // Check tables
        $result = $db->query("SHOW TABLES LIKE 'opportunities'");
        if ($db->fetchByAssoc($result)) {
            echo "<p class='success'>✓ opportunities table exists</p>";
            
            // Check custom table
            $result = $db->query("SHOW TABLES LIKE 'opportunities_cstm'");
            if ($db->fetchByAssoc($result)) {
                echo "<p class='success'>✓ opportunities_cstm table exists</p>";
                
                // Check pipeline_stage_c field
                $result = $db->query("SHOW COLUMNS FROM opportunities_cstm LIKE 'pipeline_stage_c'");
                if ($db->fetchByAssoc($result)) {
                    echo "<p class='success'>✓ pipeline_stage_c field exists</p>";
                } else {
                    echo "<p class='error'>✗ pipeline_stage_c field NOT FOUND</p>";
                    echo "<p class='warning'>Run this SQL to create it:</p>";
                    echo "<pre>ALTER TABLE opportunities_cstm ADD COLUMN pipeline_stage_c VARCHAR(50) DEFAULT 'sourcing';</pre>";
                }
            } else {
                echo "<p class='error'>✗ opportunities_cstm table NOT FOUND</p>";
                echo "<p class='warning'>Run this SQL to create it:</p>";
                echo "<pre>CREATE TABLE opportunities_cstm (
    id_c char(36) NOT NULL PRIMARY KEY,
    pipeline_stage_c VARCHAR(50) DEFAULT 'sourcing'
);</pre>";
            }
        } else {
            echo "<p class='error'>✗ opportunities table NOT FOUND</p>";
        }
    } else {
        echo "<p class='error'>✗ Database query failed</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Database error: " . $e->getMessage() . "</p>";
}

// Step 3: Check module files
echo "<h3>Step 3: Module Files Check</h3>";

// Check Deal class file
$dealFile = 'custom/modules/Deals/Deal.php';
if (file_exists($dealFile)) {
    echo "<p class='success'>✓ Deal.php exists</p>";
    
    // Check dependencies first
    $suitecrm_root = dirname(__FILE__);
    
    // Check required files exist
    $requiredFiles = [
        'modules/Opportunities/Opportunity.php' => 'Opportunity base class',
        'include/SugarLogger/SugarLogger.php' => 'SugarLogger',
        'modules/ACL/ACLController.php' => 'ACL Controller',
        'include/utils.php' => 'Utils'
    ];
    
    foreach ($requiredFiles as $file => $name) {
        if (file_exists($file)) {
            echo "<p class='success'>✓ $name found</p>";
        } else {
            echo "<p class='error'>✗ $name NOT FOUND at: $file</p>";
        }
    }
    
    // Try to load Deal class
    try {
        // Load dependencies first
        require_once('modules/Opportunities/Opportunity.php');
        require_once($dealFile);
        
        if (class_exists('Deal')) {
            echo "<p class='success'>✓ Deal class loaded successfully</p>";
            
            // Try to instantiate
            try {
                $deal = new Deal();
                echo "<p class='success'>✓ Deal object created successfully</p>";
            } catch (Exception $e) {
                echo "<p class='error'>✗ Error creating Deal object: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p class='error'>✗ Deal class NOT defined</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>✗ Error loading Deal.php: " . $e->getMessage() . "</p>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
    }
} else {
    echo "<p class='error'>✗ Deal.php NOT FOUND at: $dealFile</p>";
}

// Step 4: Check module registration
echo "<h3>Step 4: Module Registration Check</h3>";
global $beanList, $beanFiles, $moduleList;

if (isset($beanList['Deals'])) {
    echo "<p class='success'>✓ Deals registered in beanList as: " . $beanList['Deals'] . "</p>";
} else {
    echo "<p class='error'>✗ Deals NOT registered in beanList</p>";
}

if (isset($beanFiles['Deal'])) {
    echo "<p class='success'>✓ Deal registered in beanFiles as: " . $beanFiles['Deal'] . "</p>";
} else {
    echo "<p class='error'>✗ Deal NOT registered in beanFiles</p>";
}

if (in_array('Deals', $moduleList)) {
    echo "<p class='success'>✓ Deals is in moduleList</p>";
} else {
    echo "<p class='error'>✗ Deals NOT in moduleList</p>";
}

// Step 5: Check Extension cache
echo "<h3>Step 5: Extension Cache Check</h3>";
$cacheFile = 'custom/application/Ext/Include/modules.ext.php';
if (file_exists($cacheFile)) {
    $content = file_get_contents($cacheFile);
    if (strpos($content, 'Deals') !== false) {
        echo "<p class='success'>✓ Deals found in modules.ext.php cache</p>";
    } else {
        echo "<p class='error'>✗ Deals NOT in cache - Quick Repair needed</p>";
    }
} else {
    echo "<p class='error'>✗ Cache file not found - Quick Repair needed</p>";
}

// Summary
echo "<h3>Summary and Actions</h3>";
echo "<div style='background: #f0f0f0; padding: 15px; border-radius: 5px;'>";

if (!isset($beanList['Deals']) || !isset($beanFiles['Deal'])) {
    echo "<p><strong>Action Required:</strong></p>";
    echo "<ol>";
    echo "<li>Run Quick Repair and Rebuild from Admin panel</li>";
    echo "<li>Clear cache: rm -rf cache/*</li>";
    echo "<li>If still not working, check custom/Extension/application/Ext/Include/Deals.php exists</li>";
    echo "</ol>";
}

echo "<p><strong>File Paths:</strong></p>";
echo "<ul>";
echo "<li>Deal class: custom/modules/Deals/Deal.php</li>";
echo "<li>Controller: custom/modules/Deals/controller.php</li>";
echo "<li>Views: custom/modules/Deals/views/</li>";
echo "<li>Extension: custom/Extension/application/Ext/Include/Deals.php</li>";
echo "</ul>";

echo "</div>";

// Output buffer end
$output = ob_get_clean();
echo $output;
?>

</body>
</html>