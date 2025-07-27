<?php
/**
 * AWS Deployment API
 * Handles AWS deployment requests from the wizard frontend
 */

session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to frontend
ini_set('log_errors', 1);

// Set JSON response header
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust for production
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action'])) {
    sendJsonResponse(['success' => false, 'error' => 'Invalid request'], 400);
}

// Route actions
switch ($input['action']) {
    case 'testConnection':
        handleTestConnection($input);
        break;
        
    case 'startDeployment':
        handleStartDeployment($input);
        break;
        
    case 'checkDeploymentStatus':
        handleCheckDeploymentStatus($input);
        break;
        
    case 'getDeploymentLogs':
        handleGetDeploymentLogs($input);
        break;
        
    default:
        sendJsonResponse(['success' => false, 'error' => 'Unknown action'], 400);
}

/**
 * Test AWS connection
 */
function handleTestConnection($input) {
    $accessKey = $input['accessKey'] ?? '';
    $secretKey = $input['secretKey'] ?? '';
    $region = $input['region'] ?? 'us-east-1';
    
    if (empty($accessKey) || empty($secretKey)) {
        sendJsonResponse(['success' => false, 'error' => 'Missing credentials']);
        return;
    }
    
    // Create temporary credentials file
    $credentialsFile = tempnam(sys_get_temp_dir(), 'aws_creds_');
    $configFile = tempnam(sys_get_temp_dir(), 'aws_config_');
    
    file_put_contents($credentialsFile, "[default]\n" .
        "aws_access_key_id = $accessKey\n" .
        "aws_secret_access_key = $secretKey\n");
        
    file_put_contents($configFile, "[default]\n" .
        "region = $region\n");
    
    // Test AWS connection using AWS CLI
    $cmd = sprintf(
        'AWS_SHARED_CREDENTIALS_FILE=%s AWS_CONFIG_FILE=%s aws sts get-caller-identity 2>&1',
        escapeshellarg($credentialsFile),
        escapeshellarg($configFile)
    );
    
    exec($cmd, $output, $returnCode);
    
    // Clean up temporary files
    unlink($credentialsFile);
    unlink($configFile);
    
    if ($returnCode === 0) {
        // Parse the output to get account info
        $outputStr = implode("\n", $output);
        $accountInfo = json_decode($outputStr, true);
        
        sendJsonResponse([
            'success' => true,
            'accountId' => $accountInfo['Account'] ?? 'Unknown',
            'arn' => $accountInfo['Arn'] ?? 'Unknown'
        ]);
    } else {
        $errorMessage = implode("\n", $output);
        
        // Parse common AWS errors
        if (strpos($errorMessage, 'InvalidClientTokenId') !== false) {
            $errorMessage = 'Invalid AWS Access Key ID';
        } elseif (strpos($errorMessage, 'SignatureDoesNotMatch') !== false) {
            $errorMessage = 'Invalid AWS Secret Access Key';
        } elseif (strpos($errorMessage, 'command not found') !== false) {
            $errorMessage = 'AWS CLI not installed on server';
        }
        
        sendJsonResponse(['success' => false, 'error' => $errorMessage]);
    }
}

/**
 * Start AWS deployment
 */
function handleStartDeployment($input) {
    // Validate input
    $required = ['awsCredentials', 'instanceConfig', 'features'];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            sendJsonResponse(['success' => false, 'error' => "Missing field: $field"]);
            return;
        }
    }
    
    $credentials = $input['awsCredentials'];
    $config = $input['instanceConfig'];
    $features = $input['features'];
    
    // Generate deployment ID
    $deploymentId = uniqid('deploy_');
    
    // Create deployment directory
    $deploymentDir = __DIR__ . "/../../deployments/$deploymentId";
    if (!mkdir($deploymentDir, 0755, true)) {
        sendJsonResponse(['success' => false, 'error' => 'Failed to create deployment directory']);
        return;
    }
    
    // Save deployment configuration
    $deploymentConfig = [
        'id' => $deploymentId,
        'status' => 'initializing',
        'startedAt' => date('c'),
        'config' => $input,
        'logs' => []
    ];
    
    file_put_contents("$deploymentDir/config.json", json_encode($deploymentConfig, JSON_PRETTY_PRINT));
    
    // Create environment variables file for deployment script
    $envContent = "#!/bin/bash\n";
    $envContent .= "export AWS_ACCESS_KEY_ID=" . escapeshellarg($credentials['accessKey']) . "\n";
    $envContent .= "export AWS_SECRET_ACCESS_KEY=" . escapeshellarg($credentials['secretKey']) . "\n";
    $envContent .= "export AWS_REGION=" . escapeshellarg($credentials['region']) . "\n";
    $envContent .= "export INSTANCE_TYPE=" . escapeshellarg($config['instanceSize']) . "\n";
    $envContent .= "export KEY_PAIR_NAME=" . escapeshellarg("makedealcrm-{$config['instanceName']}") . "\n";
    $envContent .= "export ADMIN_EMAIL=admin@makedealcrm.local\n"; // Default for now
    $envContent .= "export STACK_NAME=" . escapeshellarg("makedealcrm-{$config['instanceName']}") . "\n";
    
    if (!empty($config['domainName'])) {
        $envContent .= "export DOMAIN_NAME=" . escapeshellarg($config['domainName']) . "\n";
    }
    
    if ($features['backups']) {
        $envContent .= "export BACKUP_RETENTION=7\n";
    }
    
    if ($features['highAvailability']) {
        $envContent .= "export ENABLE_HA=true\n";
    }
    
    file_put_contents("$deploymentDir/env.sh", $envContent);
    chmod("$deploymentDir/env.sh", 0700);
    
    // Create key pair if it doesn't exist
    $createKeyPairCmd = sprintf(
        'source %s && aws ec2 create-key-pair --key-name %s --query "KeyMaterial" --output text > %s 2>&1 || true',
        escapeshellarg("$deploymentDir/env.sh"),
        escapeshellarg("makedealcrm-{$config['instanceName']}"),
        escapeshellarg("$deploymentDir/key.pem")
    );
    
    exec($createKeyPairCmd, $output, $returnCode);
    
    if (file_exists("$deploymentDir/key.pem")) {
        chmod("$deploymentDir/key.pem", 0600);
    }
    
    // Start deployment in background
    $deployScript = realpath(__DIR__ . '/../../scripts/deploy.sh');
    $logFile = "$deploymentDir/deployment.log";
    
    // Create log file first
    touch($logFile);
    chmod($logFile, 0664);
    
    // Check if deploy script exists
    if (!file_exists($deployScript)) {
        file_put_contents($logFile, "[ERROR] Deploy script not found at: $deployScript\n");
        $deploymentConfig['status'] = 'failed';
        $deploymentConfig['error'] = 'Deploy script not found';
        file_put_contents("$deploymentDir/config.json", json_encode($deploymentConfig, JSON_PRETTY_PRINT));
        sendJsonResponse(['success' => false, 'error' => 'Deploy script not found']);
        return;
    }
    
    // Set deployment directory environment variable
    // Write a wrapper script to handle environment and logging
    $wrapperScript = "$deploymentDir/deploy-wrapper.sh";
    $wrapperContent = "#!/bin/bash\n";
    $wrapperContent .= "export DEPLOYMENT_DIR=" . escapeshellarg($deploymentDir) . "\n";
    $wrapperContent .= "source " . escapeshellarg("$deploymentDir/env.sh") . "\n";
    $wrapperContent .= "exec bash " . escapeshellarg($deployScript) . " >> " . escapeshellarg($logFile) . " 2>&1\n";
    
    file_put_contents($wrapperScript, $wrapperContent);
    chmod($wrapperScript, 0755);
    
    $deployCmd = sprintf(
        'nohup bash %s & echo $!',
        escapeshellarg($wrapperScript)
    );
    
    $pid = exec($deployCmd);
    
    // Save PID for monitoring
    file_put_contents("$deploymentDir/pid", $pid);
    
    // Update status
    $deploymentConfig['status'] = 'deploying';
    $deploymentConfig['pid'] = $pid;
    file_put_contents("$deploymentDir/config.json", json_encode($deploymentConfig, JSON_PRETTY_PRINT));
    
    // Store deployment ID in session
    $_SESSION['current_deployment'] = $deploymentId;
    
    sendJsonResponse([
        'success' => true,
        'deploymentId' => $deploymentId,
        'message' => 'Deployment started successfully'
    ]);
}

/**
 * Check deployment status
 */
function handleCheckDeploymentStatus($input) {
    $deploymentId = $input['deploymentId'] ?? $_SESSION['current_deployment'] ?? null;
    
    if (!$deploymentId) {
        sendJsonResponse(['success' => false, 'error' => 'No deployment ID provided']);
        return;
    }
    
    $deploymentDir = __DIR__ . "/../../deployments/$deploymentId";
    $configFile = "$deploymentDir/config.json";
    
    if (!file_exists($configFile)) {
        sendJsonResponse(['success' => false, 'error' => 'Deployment not found']);
        return;
    }
    
    $config = json_decode(file_get_contents($configFile), true);
    
    // Check if process is still running
    if (isset($config['pid'])) {
        $pidRunning = posix_kill($config['pid'], 0);
        
        if (!$pidRunning && $config['status'] === 'deploying') {
            // Process finished, check results
            $config['status'] = 'completed';
            $config['completedAt'] = date('c');
            
            // Check if deployment-info.json was created (indicates success)
            $deploymentInfoFile = "$deploymentDir/deployment-info.json";
            if (file_exists($deploymentInfoFile)) {
                $config['deploymentInfo'] = json_decode(file_get_contents($deploymentInfoFile), true);
                $config['success'] = true;
            } else {
                $config['success'] = false;
                $config['error'] = 'Deployment failed. Check logs for details.';
            }
            
            file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
        }
    }
    
    // Parse deployment log for progress
    $logFile = "$deploymentDir/deployment.log";
    $progress = [];
    
    if (file_exists($logFile)) {
        $logs = file_get_contents($logFile);
        
        // Parse progress from log
        if (strpos($logs, 'Checking prerequisites...') !== false) {
            $progress[] = ['step' => 'prerequisites', 'status' => 'completed'];
        }
        if (strpos($logs, 'Creating new stack...') !== false || strpos($logs, 'Stack exists, updating...') !== false) {
            $progress[] = ['step' => 'stack', 'status' => 'in_progress'];
        }
        if (strpos($logs, 'Stack deployment completed successfully!') !== false) {
            $progress[] = ['step' => 'stack', 'status' => 'completed'];
        }
        if (strpos($logs, 'Waiting for application to be ready...') !== false) {
            $progress[] = ['step' => 'application', 'status' => 'in_progress'];
        }
        if (strpos($logs, 'Application is ready!') !== false) {
            $progress[] = ['step' => 'application', 'status' => 'completed'];
        }
        if (strpos($logs, 'Admin credentials saved') !== false) {
            $progress[] = ['step' => 'credentials', 'status' => 'completed'];
        }
    }
    
    $response = [
        'success' => true,
        'status' => $config['status'],
        'progress' => $progress
    ];
    
    if ($config['status'] === 'completed') {
        $response['deploymentSuccess'] = $config['success'] ?? false;
        
        if ($config['success'] && isset($config['deploymentInfo'])) {
            $response['deploymentInfo'] = $config['deploymentInfo'];
            
            // Read admin credentials if available
            $credentialsFile = "$deploymentDir/admin-credentials.txt";
            if (file_exists($credentialsFile)) {
                $credentials = file_get_contents($credentialsFile);
                if (preg_match('/Password: (.+)/', $credentials, $matches)) {
                    $response['adminPassword'] = $matches[1];
                }
            }
        } else {
            $response['error'] = $config['error'] ?? 'Deployment failed';
        }
    }
    
    sendJsonResponse($response);
}

/**
 * Get deployment logs
 */
function handleGetDeploymentLogs($input) {
    $deploymentId = $input['deploymentId'] ?? $_SESSION['current_deployment'] ?? null;
    
    if (!$deploymentId) {
        sendJsonResponse(['success' => false, 'error' => 'No deployment ID provided']);
        return;
    }
    
    $logFile = __DIR__ . "/../../deployments/$deploymentId/deployment.log";
    
    if (!file_exists($logFile)) {
        sendJsonResponse(['success' => false, 'error' => 'Log file not found']);
        return;
    }
    
    $logs = file_get_contents($logFile);
    
    // Parse logs to remove sensitive information
    $logs = preg_replace('/aws_access_key_id = .+/', 'aws_access_key_id = ****', $logs);
    $logs = preg_replace('/aws_secret_access_key = .+/', 'aws_secret_access_key = ****', $logs);
    
    sendJsonResponse([
        'success' => true,
        'logs' => $logs,
        'lastUpdated' => date('c', filemtime($logFile))
    ]);
}

/**
 * Send JSON response
 */
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}