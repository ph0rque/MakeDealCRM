// MakeDealCRM AWS Deployment Wizard JavaScript

let currentStep = 1;
const totalSteps = 7;
let deploymentConfig = {
    awsCredentials: {},
    instanceConfig: {},
    features: {},
    costEstimate: 0
};

// Step navigation
function changeStep(direction) {
    const currentStepElement = document.getElementById(`step${currentStep}`);
    currentStepElement.classList.add('d-none');
    
    currentStep += direction;
    
    // Ensure we stay within bounds
    if (currentStep < 1) currentStep = 1;
    if (currentStep > totalSteps) currentStep = totalSteps;
    
    const nextStepElement = document.getElementById(`step${currentStep}`);
    nextStepElement.classList.remove('d-none');
    
    updateProgressBar();
    updateNavigationButtons();
    
    // Execute step-specific actions
    if (currentStep === 4) {
        calculateCosts();
    } else if (currentStep === 5) {
        updateSummary();
    }
}

// Update progress bar
function updateProgressBar() {
    const progressBar = document.getElementById('progressBar');
    const progress = (currentStep / totalSteps) * 100;
    progressBar.style.width = `${progress}%`;
    progressBar.setAttribute('aria-valuenow', progress);
    progressBar.textContent = `Step ${currentStep} of ${totalSteps}`;
}

// Update navigation buttons
function updateNavigationButtons() {
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const deployBtn = document.getElementById('deployBtn');
    
    // Previous button
    prevBtn.disabled = currentStep === 1;
    
    // Next button
    if (currentStep === totalSteps) {
        nextBtn.classList.add('d-none');
    } else if (currentStep === 5) {
        nextBtn.classList.add('d-none');
        deployBtn.classList.remove('d-none');
    } else {
        nextBtn.classList.remove('d-none');
        deployBtn.classList.add('d-none');
    }
    
    // Disable navigation during deployment
    if (currentStep === 6) {
        prevBtn.disabled = true;
        nextBtn.disabled = true;
    }
}

// Test AWS connection
async function testAWSConnection() {
    const statusDiv = document.getElementById('connectionStatus');
    const accessKey = document.getElementById('awsAccessKey').value;
    const secretKey = document.getElementById('awsSecretKey').value;
    const region = document.getElementById('awsRegion').value;
    
    if (!accessKey || !secretKey || !region) {
        statusDiv.innerHTML = '<div class="alert alert-warning">Please fill in all fields</div>';
        return;
    }
    
    statusDiv.innerHTML = '<div class="connection-testing"><i class="fas fa-spinner fa-spin me-2"></i>Testing connection...</div>';
    
    try {
        const response = await fetch('api/deploy.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'testConnection',
                accessKey: accessKey,
                secretKey: secretKey,
                region: region
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            statusDiv.innerHTML = `<div class="connection-success">
                <i class="fas fa-check-circle me-2"></i>Connection successful!
                <br><small>Account ID: ${result.accountId}</small>
            </div>`;
            deploymentConfig.awsCredentials = { accessKey, secretKey, region };
        } else {
            statusDiv.innerHTML = `<div class="connection-error">
                <i class="fas fa-times-circle me-2"></i>Connection failed: ${result.error}
            </div>`;
        }
    } catch (error) {
        statusDiv.innerHTML = `<div class="connection-error">
            <i class="fas fa-times-circle me-2"></i>Connection test failed: ${error.message}
        </div>`;
    }
}

// Calculate costs based on configuration
function calculateCosts() {
    const instanceSize = document.querySelector('input[name="instanceSize"]:checked').value;
    const enableBackups = document.getElementById('enableBackups').checked;
    const enableMonitoring = document.getElementById('enableMonitoring').checked;
    const enableHA = document.getElementById('enableHA').checked;
    
    let baseCost = 75; // t3.small
    if (instanceSize === 't3.medium') baseCost = 125;
    if (instanceSize === 't3.large') baseCost = 200;
    
    let totalCost = baseCost + 10 + 5; // Base + Storage + Transfer
    
    document.getElementById('selectedInstance').textContent = instanceSize;
    document.getElementById('instanceCost').textContent = baseCost;
    
    // Add optional features
    if (enableBackups) {
        totalCost += 10;
        document.getElementById('backupCostRow').style.display = 'table-row';
    } else {
        document.getElementById('backupCostRow').style.display = 'none';
    }
    
    if (enableMonitoring) {
        totalCost += 5;
        document.getElementById('monitoringCostRow').style.display = 'table-row';
    } else {
        document.getElementById('monitoringCostRow').style.display = 'none';
    }
    
    if (enableHA) {
        totalCost += 50;
        document.getElementById('haCostRow').classList.remove('d-none');
    } else {
        document.getElementById('haCostRow').classList.add('d-none');
    }
    
    document.getElementById('totalCost').textContent = totalCost;
    deploymentConfig.costEstimate = totalCost;
}

// Update deployment summary
function updateSummary() {
    const region = document.getElementById('awsRegion').value;
    const instanceName = document.getElementById('instanceName').value;
    const instanceSize = document.querySelector('input[name="instanceSize"]:checked').value;
    const domainName = document.getElementById('domainName').value || 'AWS Default';
    
    const features = [];
    if (document.getElementById('enableBackups').checked) features.push('Automated Backups');
    if (document.getElementById('enableMonitoring').checked) features.push('CloudWatch Monitoring');
    if (document.getElementById('enableHA').checked) features.push('High Availability');
    
    document.getElementById('summaryRegion').textContent = region;
    document.getElementById('summaryInstanceName').textContent = instanceName;
    document.getElementById('summaryInstanceSize').textContent = instanceSize;
    document.getElementById('summaryDomain').textContent = domainName;
    document.getElementById('summaryFeatures').textContent = features.join(', ') || 'None';
    document.getElementById('summaryTotalCost').textContent = deploymentConfig.costEstimate;
    
    // Store configuration
    deploymentConfig.instanceConfig = {
        region, instanceName, instanceSize, domainName
    };
    deploymentConfig.features = {
        backups: document.getElementById('enableBackups').checked,
        monitoring: document.getElementById('enableMonitoring').checked,
        highAvailability: document.getElementById('enableHA').checked
    };
}

// Start deployment process
async function startDeployment() {
    const confirmCheckbox = document.getElementById('confirmDeploy');
    if (!confirmCheckbox.checked) {
        alert('Please confirm that you understand AWS charges will apply.');
        return;
    }
    
    changeStep(1); // Move to deployment progress step
    
    try {
        // Start deployment via API
        const response = await fetch('api/deploy.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'startDeployment',
                ...deploymentConfig
            })
        });
        
        const result = await response.json();
        
        if (!result.success) {
            // Handle different error scenarios
            if (result.requiresStackDeletion) {
                // Stack is in ROLLBACK_COMPLETE state
                const modalHtml = `
                <div class="modal fade" id="stackDeletionModal" tabindex="-1" role="dialog">
                    <div class="modal-dialog modal-lg" role="document">
                        <div class="modal-content">
                            <div class="modal-header bg-warning">
                                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Stack Deletion Required</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-warning">
                                    <i class="fas fa-info-circle me-2"></i>
                                    The CloudFormation stack <strong>${result.stackName}</strong> is in <strong>${result.stackStatus}</strong> state.
                                    This means a previous deployment failed and the stack must be deleted before you can redeploy.
                                </div>
                                
                                <h6>To resolve this issue:</h6>
                                <ol>
                                    <li>Open your AWS Console or use AWS CLI</li>
                                    <li>Delete the existing stack using the command below:</li>
                                    <li>Wait for deletion to complete (usually 5-10 minutes)</li>
                                    <li>Retry the deployment</li>
                                </ol>
                                
                                <div class="bg-dark text-light p-3 rounded mt-3">
                                    <code id="deleteCommand">${result.deleteCommand}</code>
                                    <button class="btn btn-sm btn-secondary float-end" onclick="copyToClipboard('deleteCommand')">
                                        <i class="fas fa-copy"></i> Copy
                                    </button>
                                </div>
                                
                                <div class="mt-3">
                                    <p><strong>Alternative:</strong> Use our cleanup script:</p>
                                    <div class="bg-dark text-light p-3 rounded">
                                        <code>cd SuiteCRM/aws-deploy/scripts && ./delete-deployment.sh ${result.stackName}</code>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <a href="https://console.aws.amazon.com/cloudformation/" target="_blank" class="btn btn-primary">
                                    <i class="fas fa-external-link-alt me-2"></i>Open AWS Console
                                </a>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>`;
                
                // Add modal to page if not exists
                if (!document.getElementById('stackDeletionModal')) {
                    document.body.insertAdjacentHTML('beforeend', modalHtml);
                }
                
                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('stackDeletionModal'));
                modal.show();
                
            } else if (result.stackInProgress) {
                // Stack operation in progress
                alert(`The CloudFormation stack has an operation in progress (${result.stackStatus}). Please wait for it to complete before retrying.`);
            } else {
                // Other errors
                alert('Failed to start deployment: ' + result.error);
            }
            
            changeStep(-1); // Go back to review step
            return;
        }
        
        const deploymentId = result.deploymentId;
        
        // Monitor deployment progress
        const deploymentSteps = [
            { id: 'deploy-vpc', label: 'Creating VPC and network configuration', status: 'pending' },
            { id: 'deploy-security', label: 'Setting up security groups', status: 'pending' },
            { id: 'deploy-rds', label: 'Creating RDS database instance', status: 'pending' },
            { id: 'deploy-ec2', label: 'Launching EC2 instance', status: 'pending' },
            { id: 'deploy-docker', label: 'Installing Docker and pulling images', status: 'pending' },
            { id: 'deploy-app', label: 'Deploying MakeDealCRM application', status: 'pending' },
            { id: 'deploy-ssl', label: 'Configuring SSL certificate', status: 'pending' },
            { id: 'deploy-backup', label: 'Setting up automated backups', status: 'pending' },
            { id: 'deploy-monitoring', label: 'Configuring monitoring and alerts', status: 'pending' }
        ];
        
        // Check deployment status periodically
        const checkInterval = setInterval(async () => {
            try {
                const statusResponse = await fetch('api/deploy.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'checkDeploymentStatus',
                        deploymentId: deploymentId
                    })
                });
                
                const statusResult = await statusResponse.json();
                
                if (statusResult.success) {
                    // Update progress based on actual deployment status
                    updateDeploymentProgress(deploymentSteps, statusResult.progress);
                    
                    if (statusResult.status === 'completed') {
                        clearInterval(checkInterval);
                        
                        if (statusResult.deploymentSuccess) {
                            // Deployment succeeded
                            deploymentConfig.deploymentInfo = statusResult.deploymentInfo;
                            deploymentConfig.adminPassword = statusResult.adminPassword;
                            
                            // Add deployment info logging for debugging
                            console.log('Deployment Info:', statusResult.deploymentInfo);
                            
                            setTimeout(() => {
                                generateAccessDetails();
                                changeStep(1); // Move to completion step
                            }, 2000);
                        } else {
                            // Deployment failed
                            alert('Deployment failed: ' + (statusResult.error || 'Unknown error'));
                            
                            // Show option to view logs
                            if (confirm('Would you like to view the deployment logs?')) {
                                viewDeploymentLogs(deploymentId);
                            }
                            
                            // Don't change step here - let user navigate manually
                        }
                    }
                }
            } catch (error) {
                console.error('Error checking deployment status:', error);
            }
        }, 5000); // Check every 5 seconds
        
    } catch (error) {
        alert('Failed to start deployment: ' + error.message);
        changeStep(-1); // Go back to review step
    }
}

// Update deployment progress UI
function updateDeploymentProgress(steps, progress) {
    // Map progress from API to UI steps
    const progressMap = {
        'prerequisites': ['deploy-vpc', 'deploy-security'],
        'stack': ['deploy-rds', 'deploy-ec2', 'deploy-docker'],
        'application': ['deploy-app', 'deploy-ssl'],
        'credentials': ['deploy-backup', 'deploy-monitoring']
    };
    
    // Update step statuses based on progress
    progress.forEach(item => {
        const affectedSteps = progressMap[item.step] || [];
        affectedSteps.forEach(stepId => {
            const stepElement = document.getElementById(stepId);
            if (stepElement) {
                const icon = stepElement.querySelector('i');
                
                if (item.status === 'completed') {
                    icon.className = 'fas fa-check-circle me-2 text-success';
                    stepElement.classList.add('completed');
                } else if (item.status === 'in_progress') {
                    icon.className = 'fas fa-spinner fa-spin me-2';
                }
            }
        });
    });
    
    // Calculate overall progress
    let completedCount = 0;
    steps.forEach(step => {
        const stepElement = document.getElementById(step.id);
        if (stepElement && stepElement.classList.contains('completed')) {
            completedCount++;
        }
    });
    
    const progressPercent = (completedCount / steps.length) * 100;
    const progressBar = document.getElementById('deploymentProgressBar');
    progressBar.style.width = `${progressPercent}%`;
    progressBar.setAttribute('aria-valuenow', progressPercent);
    progressBar.textContent = `${Math.round(progressPercent)}%`;
    
    // Update time remaining (estimate)
    const estimatedMinutesPerStep = 2;
    const remainingSteps = steps.length - completedCount;
    const remainingMinutes = remainingSteps * estimatedMinutesPerStep;
    document.getElementById('timeRemaining').textContent = `${remainingMinutes} minutes`;
}

// View deployment logs
async function viewDeploymentLogs(deploymentId) {
    try {
        const response = await fetch('api/deploy.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'getDeploymentLogs',
                deploymentId: deploymentId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Show logs in a modal or new window
            const logsWindow = window.open('', 'DeploymentLogs', 'width=800,height=600');
            logsWindow.document.write(`
                <html>
                <head>
                    <title>Deployment Logs</title>
                    <style>
                        body { font-family: monospace; background: #000; color: #0f0; padding: 20px; }
                        pre { white-space: pre-wrap; }
                        .error { color: #f00; }
                        .warning { color: #ff0; }
                    </style>
                </head>
                <body>
                    <h2>Deployment Logs - ${deploymentId}</h2>
                    <pre>${result.logs || 'No logs available yet.'}</pre>
                    <p>Last updated: ${result.lastUpdated}</p>
                    <button onclick="window.close()">Close</button>
                </body>
                </html>
            `);
        } else {
            alert('Failed to fetch logs: ' + (result.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Failed to fetch deployment logs:', error);
        alert('Failed to fetch deployment logs. Please check the console for details.');
    }
}

// Generate access details
function generateAccessDetails() {
    // Use real deployment info if available
    if (deploymentConfig.deploymentInfo) {
        const info = deploymentConfig.deploymentInfo;
        // Get the actual EC2 public IP from the deployment
        const url = info.applicationUrl || 'http://pending-deployment';
        const actualUrl = url.replace('https://AWS Default', 'http://' + (info.instancePublicIp || 'pending'));
        
        document.getElementById('appUrl').href = actualUrl;
        document.getElementById('appUrl').textContent = actualUrl;
        
        // Use real admin password
        const password = deploymentConfig.adminPassword || generatePassword();
        document.getElementById('adminPassword').setAttribute('data-password', password);
        
        // Generate AWS resources JSON with real data
        const awsResources = {
            deployment: {
                timestamp: info.deployedAt || new Date().toISOString(),
                region: info.region || deploymentConfig.awsCredentials.region,
                configuration: deploymentConfig
            },
            resources: info.resources || {
                vpc: `vpc-${generateId()}`,
                subnet: `subnet-${generateId()}`,
                securityGroup: `sg-${generateId()}`,
                ec2Instance: info.instanceId || `i-${generateId()}`,
                rdsInstance: info.databaseEndpoint || `makedealcrm-db-${generateId()}`,
                s3Bucket: info.backupBucket || `makedealcrm-backups-${generateId()}`
            },
            access: {
                applicationUrl: url,
                adminUsername: 'admin',
                sshCommand: info.sshCommand || 'N/A',
                sshKeyName: `makedealcrm-${deploymentConfig.instanceConfig.instanceName}`
            }
        };
        
        // Create downloadable JSON file
        const blob = new Blob([JSON.stringify(awsResources, null, 2)], { type: 'application/json' });
        const downloadUrl = URL.createObjectURL(blob);
        const link = document.querySelector('a[href="aws-resources.json"]');
        link.href = downloadUrl;
    } else {
        // Fallback to demo data if no real deployment info
        const ip = `${Math.floor(Math.random() * 255)}.${Math.floor(Math.random() * 255)}.${Math.floor(Math.random() * 255)}.${Math.floor(Math.random() * 255)}`;
        const region = deploymentConfig.awsCredentials.region || 'us-east-1';
        const url = `https://ec2-${ip.replace(/\./g, '-')}.${region}.compute.amazonaws.com`;
        
        document.getElementById('appUrl').href = url;
        document.getElementById('appUrl').textContent = url;
        
        const password = generatePassword();
        document.getElementById('adminPassword').setAttribute('data-password', password);
        
        const awsResources = {
            deployment: {
                timestamp: new Date().toISOString(),
                region: region,
                configuration: deploymentConfig
            },
            resources: {
                vpc: `vpc-${generateId()}`,
                subnet: `subnet-${generateId()}`,
                securityGroup: `sg-${generateId()}`,
                ec2Instance: `i-${generateId()}`,
                rdsInstance: `makedealcrm-db-${generateId()}`,
                s3Bucket: `makedealcrm-backups-${generateId()}`
            },
            access: {
                applicationUrl: url,
                adminUsername: 'admin',
                sshKeyName: `makedealcrm-${deploymentConfig.instanceConfig.instanceName}`
            }
        };
        
        const blob = new Blob([JSON.stringify(awsResources, null, 2)], { type: 'application/json' });
        const downloadUrl = URL.createObjectURL(blob);
        const link = document.querySelector('a[href="aws-resources.json"]');
        link.href = downloadUrl;
    }
}

// Utility functions
function generatePassword() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
    let password = '';
    for (let i = 0; i < 16; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return password;
}

function generateId() {
    return Math.random().toString(36).substring(2, 15);
}

function togglePassword() {
    const passwordElement = document.getElementById('adminPassword');
    const actualPassword = passwordElement.getAttribute('data-password');
    
    if (passwordElement.textContent === '****************') {
        passwordElement.textContent = actualPassword;
    } else {
        passwordElement.textContent = '****************';
    }
}

function copyToClipboard(elementId) {
    const element = document.getElementById(elementId);
    let text = element.textContent;
    
    if (elementId === 'adminPassword') {
        text = element.getAttribute('data-password');
    }
    
    navigator.clipboard.writeText(text).then(() => {
        // Show success feedback
        const button = event.target.closest('button');
        const originalHTML = button.innerHTML;
        button.innerHTML = '<i class="fas fa-check"></i>';
        setTimeout(() => {
            button.innerHTML = originalHTML;
        }, 2000);
    });
}

// Form validation
document.addEventListener('DOMContentLoaded', function() {
    // Add form validation for step 2
    const awsForm = document.getElementById('awsCredentialsForm');
    if (awsForm) {
        awsForm.addEventListener('submit', function(e) {
            e.preventDefault();
            testAWSConnection();
        });
    }
    
    // Add validation for next button
    document.getElementById('nextBtn').addEventListener('click', function(e) {
        let canProceed = true;
        
        if (currentStep === 2) {
            // Validate AWS credentials
            const accessKey = document.getElementById('awsAccessKey').value;
            const secretKey = document.getElementById('awsSecretKey').value;
            const region = document.getElementById('awsRegion').value;
            
            if (!accessKey || !secretKey || !region) {
                alert('Please fill in all AWS credential fields and test the connection.');
                canProceed = false;
            }
        } else if (currentStep === 3) {
            // Validate configuration
            const instanceName = document.getElementById('instanceName').value;
            if (!instanceName) {
                alert('Please provide an instance name.');
                canProceed = false;
            }
        }
        
        if (!canProceed) {
            e.preventDefault();
        }
    });
});