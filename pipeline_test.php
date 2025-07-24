<?php
/**
 * Direct Pipeline Test Page
 * Access this at: http://localhost:8080/pipeline_test.php
 */

// Set up SuiteCRM environment
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');

// Check authentication
global $current_user;
if (empty($current_user->id)) {
    die('Please log in to SuiteCRM first');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>M&A Deal Pipeline</title>
    <link rel="stylesheet" href="themes/SuiteP/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="custom/modules/Pipelines/views/pipeline-kanban.css">
</head>
<body style="background: #f8f9fa; padding: 20px;">
    <div class="container-fluid">
        <h2>M&A Deal Pipeline</h2>
        <div id="pipeline-container"></div>
    </div>

    <script src="include/javascript/jquery/jquery-min.js"></script>
    <script>
        // Sample pipeline data
        window.pipelineStages = [
            {name: 'sourcing', display_name: 'Sourcing', wip_limit: 50, probability_default: 10},
            {name: 'screening', display_name: 'Screening', wip_limit: 25, probability_default: 20},
            {name: 'analysis_outreach', display_name: 'Analysis & Outreach', wip_limit: 15, probability_default: 30},
            {name: 'term_sheet', display_name: 'Term Sheet', wip_limit: 10, probability_default: 50},
            {name: 'due_diligence', display_name: 'Due Diligence', wip_limit: 8, probability_default: 70}
        ];
        
        window.pipelineDeals = [
            {
                id: 'test-001',
                name: 'TechCorp Acquisition',
                company_name: 'TechCorp Inc',
                stage: 'sourcing',
                deal_value: 25000000,
                probability: 10,
                days_in_stage: 15,
                health_score: 75,
                is_stale: 0,
                assigned_user_name: '<?php echo $current_user->full_name; ?>'
            },
            {
                id: 'test-002',
                name: 'DataSystems Merger',
                company_name: 'DataSystems LLC',
                stage: 'screening',
                deal_value: 45000000,
                probability: 20,
                days_in_stage: 8,
                health_score: 82,
                is_stale: 0,
                assigned_user_name: '<?php echo $current_user->full_name; ?>'
            },
            {
                id: 'test-003',
                name: 'CloudTech Solutions Deal',
                company_name: 'CloudTech Solutions',
                stage: 'term_sheet',
                deal_value: 75000000,
                probability: 50,
                days_in_stage: 12,
                health_score: 88,
                is_stale: 0,
                assigned_user_name: '<?php echo $current_user->full_name; ?>'
            }
        ];
        
        window.wipLimits = {
            sourcing: 50,
            screening: 25,
            analysis_outreach: 15,
            term_sheet: 10,
            due_diligence: 8
        };
        
        window.currentUser = {
            id: '<?php echo $current_user->id; ?>',
            name: '<?php echo $current_user->full_name; ?>'
        };
        
        window.pipelinePermissions = {
            canCreate: true,
            canEdit: true,
            canDelete: true,
            canExport: true,
            isAdmin: <?php echo $current_user->isAdmin() ? 'true' : 'false'; ?>
        };
    </script>
    
    <script src="custom/modules/Pipelines/views/PipelineKanbanView.js"></script>
</body>
</html>