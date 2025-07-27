<?php
/**
 * AWS Deployment Integration for SuiteCRM Admin
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

global $current_user, $sugar_config;

// Check admin permissions
if (!is_admin($current_user)) {
    sugar_die('Unauthorized access to administration.');
}

// Set page title
echo '<h2>AWS Deployment Wizard</h2>';

// Create iframe that loads the AWS deployment wizard
$wizardPath = $sugar_config['site_url'] . '/aws-deploy/wizard/';

?>
<style>
    .aws-deployment-container {
        width: 100%;
        height: 800px;
        border: 1px solid #ddd;
        border-radius: 5px;
        overflow: hidden;
        background: #f5f5f5;
    }
    
    .aws-deployment-iframe {
        width: 100%;
        height: 100%;
        border: none;
    }
    
    .aws-deployment-header {
        background: #f8f8f8;
        padding: 15px;
        border-bottom: 1px solid #ddd;
        margin-bottom: 20px;
    }
    
    .aws-deployment-info {
        background: #e8f4f8;
        border: 1px solid #bee5eb;
        border-radius: 4px;
        padding: 15px;
        margin-bottom: 20px;
    }
    
    .aws-deployment-warning {
        background: #fff3cd;
        border: 1px solid #ffeeba;
        border-radius: 4px;
        padding: 15px;
        margin-bottom: 20px;
    }
</style>

<div class="aws-deployment-header">
    <h3>Deploy MakeDealCRM to AWS</h3>
    <p>Use this wizard to deploy a new instance of MakeDealCRM to Amazon Web Services (AWS).</p>
</div>

<div class="aws-deployment-info">
    <h4>Prerequisites:</h4>
    <ul>
        <li>AWS Account with administrative access</li>
        <li>AWS Access Key ID and Secret Access Key</li>
        <li>Basic understanding of AWS services and pricing</li>
    </ul>
</div>

<div class="aws-deployment-warning">
    <strong>Important:</strong> This deployment will create AWS resources that incur charges. 
    Make sure you understand AWS pricing before proceeding.
</div>

<div class="aws-deployment-container">
    <iframe 
        src="<?php echo $wizardPath; ?>" 
        class="aws-deployment-iframe"
        id="aws-deployment-frame">
    </iframe>
</div>

<script>
// Handle messages from the iframe if needed
window.addEventListener('message', function(e) {
    if (e.origin !== '<?php echo $sugar_config['site_url']; ?>') {
        return;
    }
    
    // Handle deployment completion or other events
    if (e.data.type === 'deployment-complete') {
        alert('AWS Deployment completed successfully!');
    }
});
</script>