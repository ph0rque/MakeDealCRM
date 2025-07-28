<?php
/**
 * AWS EC2 Instance Management for MakeDealCRM
 * This page allows administrators to view and manage EC2 instances
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

global $current_user, $mod_strings, $app_strings;

// Check if user is admin
if (!is_admin($current_user)) {
    sugar_die($app_strings['ERR_NOT_ADMIN']);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'refresh':
            // Run the instance discovery script
            $output = [];
            $return_var = 0;
            $script_path = realpath(__DIR__ . '/../../../aws-deploy/scripts/manage-makedealcrm-instances.sh');
            
            if (file_exists($script_path)) {
                exec("bash $script_path 2>&1", $output, $return_var);
                $_SESSION['aws_instances_output'] = implode("\n", $output);
                $_SESSION['aws_instances_status'] = $return_var === 0 ? 'success' : 'error';
            } else {
                $_SESSION['aws_instances_status'] = 'error';
                $_SESSION['aws_instances_output'] = 'Instance management script not found.';
            }
            
            header('Location: index.php?module=Administration&action=aws_instances');
            exit;
            break;
            
        case 'clean_terminated':
            // Clean up terminated instances
            $output = [];
            $return_var = 0;
            $script_path = realpath(__DIR__ . '/../../../aws-deploy/scripts/manage-makedealcrm-instances.sh');
            
            if (file_exists($script_path)) {
                exec("bash $script_path --clean-terminated --force 2>&1", $output, $return_var);
                $_SESSION['aws_instances_output'] = implode("\n", $output);
                $_SESSION['aws_instances_status'] = $return_var === 0 ? 'success' : 'error';
            } else {
                $_SESSION['aws_instances_status'] = 'error';
                $_SESSION['aws_instances_output'] = 'Instance management script not found.';
            }
            
            header('Location: index.php?module=Administration&action=aws_instances');
            exit;
            break;
    }
}

// Get any stored output
$output = isset($_SESSION['aws_instances_output']) ? $_SESSION['aws_instances_output'] : '';
$status = isset($_SESSION['aws_instances_status']) ? $_SESSION['aws_instances_status'] : '';
unset($_SESSION['aws_instances_output']);
unset($_SESSION['aws_instances_status']);

// HTML output
?>
<!DOCTYPE html>
<html>
<head>
    <title>AWS EC2 Instance Management</title>
    <style>
        .aws-management-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .aws-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eee;
        }
        
        .aws-title {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        
        .aws-actions {
            display: flex;
            gap: 10px;
        }
        
        .aws-button {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .aws-button-primary {
            background: #007bff;
            color: white;
        }
        
        .aws-button-primary:hover {
            background: #0056b3;
        }
        
        .aws-button-danger {
            background: #dc3545;
            color: white;
        }
        
        .aws-button-danger:hover {
            background: #c82333;
        }
        
        .aws-button-secondary {
            background: #6c757d;
            color: white;
        }
        
        .aws-button-secondary:hover {
            background: #5a6268;
        }
        
        .aws-output {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 20px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.5;
            white-space: pre-wrap;
            word-wrap: break-word;
            max-height: 600px;
            overflow-y: auto;
        }
        
        .aws-alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .aws-alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .aws-alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .aws-alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        
        .aws-instructions {
            background: #e9ecef;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .aws-instructions h4 {
            margin-top: 0;
            color: #495057;
        }
        
        .aws-instructions ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .aws-instructions code {
            background: #fff;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="aws-management-container">
        <div class="aws-header">
            <h1 class="aws-title">AWS EC2 Instance Management</h1>
            <div class="aws-actions">
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="refresh">
                    <button type="submit" class="aws-button aws-button-primary">
                        <i class="glyphicon glyphicon-refresh"></i> Refresh Instances
                    </button>
                </form>
                <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to clean up terminated instances? This action cannot be undone.');">
                    <input type="hidden" name="action" value="clean_terminated">
                    <button type="submit" class="aws-button aws-button-danger">
                        <i class="glyphicon glyphicon-trash"></i> Clean Terminated Instances
                    </button>
                </form>
                <a href="index.php?module=Administration&action=index" class="aws-button aws-button-secondary">
                    <i class="glyphicon glyphicon-arrow-left"></i> Back to Admin
                </a>
            </div>
        </div>
        
        <?php if ($status === 'success'): ?>
            <div class="aws-alert aws-alert-success">
                <strong>Success!</strong> Operation completed successfully.
            </div>
        <?php elseif ($status === 'error'): ?>
            <div class="aws-alert aws-alert-error">
                <strong>Error!</strong> Operation failed. Please check the output below for details.
            </div>
        <?php endif; ?>
        
        <div class="aws-instructions">
            <h4>Instance Management Instructions</h4>
            <ul>
                <li><strong>Refresh Instances:</strong> Scan all AWS regions for MakeDealCRM EC2 instances (excludes terminated by default)</li>
                <li><strong>Clean Terminated Instances:</strong> Remove terminated instances from the display</li>
                <li>Terminated instances are automatically removed by AWS after a certain period</li>
                <li>To view terminated instances from command line, run: <code>bash aws-deploy/scripts/manage-makedealcrm-instances.sh --show-terminated</code></li>
            </ul>
        </div>
        
        <?php if ($output): ?>
            <h3>Command Output:</h3>
            <div class="aws-output"><?php echo htmlspecialchars($output); ?></div>
        <?php else: ?>
            <div class="aws-alert aws-alert-info">
                Click "Refresh Instances" to view your AWS EC2 instances.
            </div>
        <?php endif; ?>
    </div>
</body>
</html>