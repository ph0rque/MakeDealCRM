<?php
/**
 * Comprehensive error check for Deals module
 */

// Start session properly
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('sugarEntry', true);

// Capture any errors during loading
ob_start();
$errors = array();

// Custom error handler to capture all errors
set_error_handler(function($severity, $message, $file, $line) use (&$errors) {
    $errors[] = array(
        'type' => $severity,
        'message' => $message,
        'file' => $file,
        'line' => $line
    );
});

?>
<!DOCTYPE html>
<html>
<head>
    <title>Deals Module Error Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .error { background: #fee; padding: 10px; margin: 5px 0; border: 1px solid #fcc; }
        .success { background: #efe; padding: 10px; margin: 5px 0; border: 1px solid #cfc; }
        .info { background: #eef; padding: 10px; margin: 5px 0; border: 1px solid #ccf; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
        h3 { color: #333; margin-top: 20px; }
    </style>
</head>
<body>

<h1>Deals Module Error Check</h1>

<?php
// Load SuiteCRM
try {
    require_once('include/entryPoint.php');
    echo "<div class='success'>✓ SuiteCRM loaded successfully</div>";
} catch (Exception $e) {
    echo "<div class='error'>✗ Error loading SuiteCRM: " . $e->getMessage() . "</div>";
}

// Display any errors captured during loading
if (!empty($errors)) {
    echo "<h3>Errors during loading:</h3>";
    foreach ($errors as $error) {
        echo "<div class='error'>";
        echo "<strong>Error:</strong> " . htmlspecialchars($error['message']) . "<br>";
        echo "<strong>File:</strong> " . htmlspecialchars($error['file']) . "<br>";
        echo "<strong>Line:</strong> " . $error['line'];
        echo "</div>";
    }
}

// Check module registration
echo "<h3>Module Registration Status:</h3>";
global $beanList, $beanFiles, $moduleList;

echo "<div class='info'>";
echo "<strong>Deals in beanList:</strong> " . (isset($beanList['Deals']) ? 'Yes (' . $beanList['Deals'] . ')' : 'No') . "<br>";
echo "<strong>Deal in beanFiles:</strong> " . (isset($beanFiles['Deal']) ? 'Yes (' . $beanFiles['Deal'] . ')' : 'No') . "<br>";
echo "<strong>Deals in moduleList:</strong> " . (in_array('Deals', $moduleList) ? 'Yes' : 'No') . "<br>";
echo "</div>";

// Check if bean file exists
if (isset($beanFiles['Deal'])) {
    $beanFile = $beanFiles['Deal'];
    echo "<h3>Bean File Check:</h3>";
    echo "<div class='info'>";
    echo "<strong>Registered path:</strong> " . htmlspecialchars($beanFile) . "<br>";
    echo "<strong>File exists:</strong> " . (file_exists($beanFile) ? 'Yes' : 'No') . "<br>";
    if (file_exists($beanFile)) {
        echo "<strong>File size:</strong> " . filesize($beanFile) . " bytes<br>";
        echo "<strong>Readable:</strong> " . (is_readable($beanFile) ? 'Yes' : 'No') . "<br>";
    }
    echo "</div>";
}

// Try to load Deal class
echo "<h3>Deal Class Loading Test:</h3>";
$loadErrors = array();
set_error_handler(function($severity, $message, $file, $line) use (&$loadErrors) {
    $loadErrors[] = compact('severity', 'message', 'file', 'line');
});

try {
    // First load Opportunity class
    if (file_exists('modules/Opportunities/Opportunity.php')) {
        require_once('modules/Opportunities/Opportunity.php');
        echo "<div class='success'>✓ Opportunity class loaded</div>";
    }
    
    // Then try to load Deal class
    if (isset($beanFiles['Deal']) && file_exists($beanFiles['Deal'])) {
        require_once($beanFiles['Deal']);
        echo "<div class='success'>✓ Deal file included</div>";
        
        if (class_exists('Deal')) {
            echo "<div class='success'>✓ Deal class exists</div>";
            
            // Try to instantiate
            $deal = new Deal();
            echo "<div class='success'>✓ Deal object created</div>";
            
            // Check properties
            echo "<div class='info'>";
            echo "<strong>Module dir:</strong> " . $deal->module_dir . "<br>";
            echo "<strong>Object name:</strong> " . $deal->object_name . "<br>";
            echo "<strong>Table name:</strong> " . $deal->table_name . "<br>";
            echo "</div>";
        } else {
            echo "<div class='error'>✗ Deal class not found after including file</div>";
        }
    } else {
        echo "<div class='error'>✗ Bean file not found or not registered</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>✗ Exception: " . $e->getMessage() . "</div>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// Display load errors
if (!empty($loadErrors)) {
    echo "<h3>Errors during Deal class loading:</h3>";
    foreach ($loadErrors as $error) {
        echo "<div class='error'>";
        echo "<strong>Error:</strong> " . htmlspecialchars($error['message']) . "<br>";
        echo "<strong>File:</strong> " . htmlspecialchars($error['file']) . "<br>";
        echo "<strong>Line:</strong> " . $error['line'];
        echo "</div>";
    }
}

// Check controller
echo "<h3>Controller Check:</h3>";
$controllerFile = 'custom/modules/Deals/controller.php';
echo "<div class='info'>";
echo "<strong>Controller path:</strong> " . $controllerFile . "<br>";
echo "<strong>File exists:</strong> " . (file_exists($controllerFile) ? 'Yes' : 'No') . "<br>";
if (file_exists($controllerFile)) {
    // Check for syntax errors
    $output = array();
    $return = 0;
    exec("php -l " . escapeshellarg($controllerFile) . " 2>&1", $output, $return);
    if ($return === 0) {
        echo "<strong>Syntax check:</strong> <span style='color:green'>✓ No syntax errors</span><br>";
    } else {
        echo "<strong>Syntax check:</strong> <span style='color:red'>✗ Syntax errors found</span><br>";
        echo "<pre>" . implode("\n", $output) . "</pre>";
    }
}
echo "</div>";

// Check views
echo "<h3>Views Check:</h3>";
$viewsDir = 'custom/modules/Deals/views/';
if (is_dir($viewsDir)) {
    $views = scandir($viewsDir);
    echo "<div class='info'>";
    echo "<strong>Views found:</strong><br>";
    foreach ($views as $view) {
        if ($view != '.' && $view != '..') {
            echo "- " . $view . "<br>";
        }
    }
    echo "</div>";
} else {
    echo "<div class='error'>✗ Views directory not found</div>";
}

// Restore error handler
restore_error_handler();

echo "<h3>Actions:</h3>";
echo "<div class='info'>";
echo "<a href='index.php?module=Deals&action=index'>Try Deals Module</a> | ";
echo "<a href='index.php?module=Deals&action=pipeline'>Try Pipeline View</a> | ";
echo "<a href='index.php?module=Deals&action=listview'>Try List View</a>";
echo "</div>";

// Get output buffer content
$output = ob_get_clean();
echo $output;
?>

</body>
</html>