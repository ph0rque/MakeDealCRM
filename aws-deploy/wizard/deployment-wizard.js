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
    
    // Simulate API call
    setTimeout(() => {
        // In real implementation, this would make an API call to verify credentials
        const success = Math.random() > 0.2; // 80% success rate for demo
        
        if (success) {
            statusDiv.innerHTML = '<div class="connection-success"><i class="fas fa-check-circle me-2"></i>Connection successful!</div>';
            deploymentConfig.awsCredentials = { accessKey, secretKey, region };
        } else {
            statusDiv.innerHTML = '<div class="connection-error"><i class="fas fa-times-circle me-2"></i>Connection failed. Please check your credentials.</div>';
        }
    }, 2000);
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
    
    // Simulate deployment steps
    const deploymentSteps = [
        { id: 'deploy-vpc', duration: 3000 },
        { id: 'deploy-security', duration: 2000 },
        { id: 'deploy-rds', duration: 5000 },
        { id: 'deploy-ec2', duration: 4000 },
        { id: 'deploy-docker', duration: 6000 },
        { id: 'deploy-app', duration: 8000 },
        { id: 'deploy-ssl', duration: 3000 },
        { id: 'deploy-backup', duration: 2000 },
        { id: 'deploy-monitoring', duration: 2000 }
    ];
    
    let completedSteps = 0;
    const totalDuration = deploymentSteps.reduce((sum, step) => sum + step.duration, 0);
    let elapsedTime = 0;
    
    for (const step of deploymentSteps) {
        const stepElement = document.getElementById(step.id);
        const icon = stepElement.querySelector('i');
        
        // Update icon to spinning
        icon.className = 'fas fa-spinner fa-spin me-2';
        
        await new Promise(resolve => setTimeout(resolve, step.duration));
        
        // Mark as completed
        icon.className = 'fas fa-check-circle me-2 text-success';
        stepElement.classList.add('completed');
        
        completedSteps++;
        elapsedTime += step.duration;
        
        // Update progress bar
        const progress = (completedSteps / deploymentSteps.length) * 100;
        const progressBar = document.getElementById('deploymentProgressBar');
        progressBar.style.width = `${progress}%`;
        progressBar.setAttribute('aria-valuenow', progress);
        progressBar.textContent = `${Math.round(progress)}%`;
        
        // Update time remaining
        const remainingTime = Math.ceil((totalDuration - elapsedTime) / 60000);
        document.getElementById('timeRemaining').textContent = `${remainingTime} minutes`;
    }
    
    // Deployment complete - generate access details
    setTimeout(() => {
        generateAccessDetails();
        changeStep(1); // Move to completion step
    }, 2000);
}

// Generate access details
function generateAccessDetails() {
    // Generate random IP for demo
    const ip = `${Math.floor(Math.random() * 255)}.${Math.floor(Math.random() * 255)}.${Math.floor(Math.random() * 255)}.${Math.floor(Math.random() * 255)}`;
    const region = deploymentConfig.awsCredentials.region || 'us-east-1';
    const url = `https://ec2-${ip.replace(/\./g, '-')}.${region}.compute.amazonaws.com`;
    
    document.getElementById('appUrl').href = url;
    document.getElementById('appUrl').textContent = url;
    
    // Generate random password
    const password = generatePassword();
    document.getElementById('adminPassword').setAttribute('data-password', password);
    
    // Generate AWS resources JSON
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
    
    // Create downloadable JSON file
    const blob = new Blob([JSON.stringify(awsResources, null, 2)], { type: 'application/json' });
    const downloadUrl = URL.createObjectURL(blob);
    const link = document.querySelector('a[href="aws-resources.json"]');
    link.href = downloadUrl;
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