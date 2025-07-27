<?php
/**
 * Seed Pre-built Checklist Templates
 * Run this script to create the default templates
 */

if (!defined('sugarEntry')) define('sugarEntry', true);

// Change to SuiteCRM root directory
chdir(dirname(__FILE__) . '/../../../');

require_once('include/entryPoint.php');
require_once('modules/Administration/QuickRepairAndRebuild.php');
require_once('custom/modules/ChecklistTemplates/ChecklistTemplate.php');
require_once('custom/modules/ChecklistItems/ChecklistItem.php');

global $current_user, $db;
$current_user = BeanFactory::getBean('Users', '1');

// Pre-built templates data
$templates = array(
    array(
        'name' => 'Quick-Screen Checklist',
        'description' => 'Initial evaluation checklist for quick deal screening',
        'category' => 'quick_screen',
        'is_public' => true,
        'is_active' => true,
        'items' => array(
            array(
                'title' => 'Initial Financial Review',
                'description' => 'Review basic financial metrics (Revenue, EBITDA, Cash Flow)',
                'type' => 'checkbox',
                'order_number' => 1,
                'is_required' => true,
                'due_days' => 2
            ),
            array(
                'title' => 'Industry Analysis',
                'description' => 'Research industry trends and competitive landscape',
                'type' => 'checkbox',
                'order_number' => 2,
                'is_required' => true,
                'due_days' => 3
            ),
            array(
                'title' => 'Management Team Assessment',
                'description' => 'Initial assessment of key management personnel',
                'type' => 'checkbox',
                'order_number' => 3,
                'is_required' => false,
                'due_days' => 3
            ),
            array(
                'title' => 'Deal Structure Overview',
                'description' => 'Review proposed deal structure and terms',
                'type' => 'checkbox',
                'order_number' => 4,
                'is_required' => true,
                'due_days' => 2
            ),
            array(
                'title' => 'Red Flag Identification',
                'description' => 'Document any initial concerns or red flags',
                'type' => 'textarea',
                'order_number' => 5,
                'is_required' => false,
                'due_days' => 3
            )
        )
    ),
    array(
        'name' => 'Full Financial Due Diligence',
        'description' => 'Comprehensive financial review checklist for detailed analysis',
        'category' => 'financial',
        'is_public' => true,
        'is_active' => true,
        'items' => array(
            array(
                'title' => 'Historical Financial Statements (3 years)',
                'description' => 'Obtain and review P&L, Balance Sheet, Cash Flow for last 3 years',
                'type' => 'file',
                'order_number' => 1,
                'is_required' => true,
                'due_days' => 5
            ),
            array(
                'title' => 'Tax Returns (3 years)',
                'description' => 'Review federal and state tax returns',
                'type' => 'file',
                'order_number' => 2,
                'is_required' => true,
                'due_days' => 7
            ),
            array(
                'title' => 'Bank Statements Verification',
                'description' => 'Verify cash balances and reconcile with financial statements',
                'type' => 'checkbox',
                'order_number' => 3,
                'is_required' => true,
                'due_days' => 10
            ),
            array(
                'title' => 'Accounts Receivable Aging',
                'description' => 'Review AR aging report and collection history',
                'type' => 'file',
                'order_number' => 4,
                'is_required' => true,
                'due_days' => 7
            ),
            array(
                'title' => 'Accounts Payable Review',
                'description' => 'Analyze AP aging and vendor relationships',
                'type' => 'checkbox',
                'order_number' => 5,
                'is_required' => true,
                'due_days' => 7
            ),
            array(
                'title' => 'Inventory Analysis',
                'description' => 'Review inventory levels, turnover, and obsolescence',
                'type' => 'checkbox',
                'order_number' => 6,
                'is_required' => false,
                'due_days' => 10
            ),
            array(
                'title' => 'Working Capital Analysis',
                'description' => 'Calculate and analyze working capital requirements',
                'type' => 'checkbox',
                'order_number' => 7,
                'is_required' => true,
                'due_days' => 10
            ),
            array(
                'title' => 'Quality of Earnings Analysis',
                'description' => 'Assess sustainability and quality of reported earnings',
                'type' => 'checkbox',
                'order_number' => 8,
                'is_required' => true,
                'due_days' => 14
            ),
            array(
                'title' => 'Budget vs Actual Analysis',
                'description' => 'Review budget accuracy and variance analysis',
                'type' => 'checkbox',
                'order_number' => 9,
                'is_required' => false,
                'due_days' => 10
            ),
            array(
                'title' => 'Customer Concentration Analysis',
                'description' => 'Review revenue concentration by customer',
                'type' => 'checkbox',
                'order_number' => 10,
                'is_required' => true,
                'due_days' => 7
            )
        )
    ),
    array(
        'name' => 'Legal Due Diligence',
        'description' => 'Legal documentation and compliance review checklist',
        'category' => 'legal',
        'is_public' => true,
        'is_active' => true,
        'items' => array(
            array(
                'title' => 'Corporate Documents',
                'description' => 'Articles of incorporation, bylaws, operating agreements',
                'type' => 'file',
                'order_number' => 1,
                'is_required' => true,
                'due_days' => 5
            ),
            array(
                'title' => 'Material Contracts Review',
                'description' => 'Review all material customer, vendor, and partnership agreements',
                'type' => 'checkbox',
                'order_number' => 2,
                'is_required' => true,
                'due_days' => 10
            ),
            array(
                'title' => 'Lease Agreements',
                'description' => 'Review all real estate and equipment leases',
                'type' => 'file',
                'order_number' => 3,
                'is_required' => true,
                'due_days' => 7
            ),
            array(
                'title' => 'Employment Agreements',
                'description' => 'Review key employee contracts and non-competes',
                'type' => 'file',
                'order_number' => 4,
                'is_required' => true,
                'due_days' => 7
            ),
            array(
                'title' => 'Litigation Review',
                'description' => 'Identify any current or threatened litigation',
                'type' => 'checkbox',
                'order_number' => 5,
                'is_required' => true,
                'due_days' => 5
            ),
            array(
                'title' => 'Intellectual Property',
                'description' => 'Review patents, trademarks, copyrights, trade secrets',
                'type' => 'checkbox',
                'order_number' => 6,
                'is_required' => false,
                'due_days' => 10
            ),
            array(
                'title' => 'Regulatory Compliance',
                'description' => 'Verify compliance with applicable regulations',
                'type' => 'checkbox',
                'order_number' => 7,
                'is_required' => true,
                'due_days' => 10
            ),
            array(
                'title' => 'Insurance Policies',
                'description' => 'Review all insurance coverage and claims history',
                'type' => 'file',
                'order_number' => 8,
                'is_required' => true,
                'due_days' => 5
            ),
            array(
                'title' => 'Environmental Compliance',
                'description' => 'Environmental assessments and compliance documentation',
                'type' => 'checkbox',
                'order_number' => 9,
                'is_required' => false,
                'due_days' => 14
            ),
            array(
                'title' => 'Data Privacy Compliance',
                'description' => 'Review data privacy policies and GDPR/CCPA compliance',
                'type' => 'checkbox',
                'order_number' => 10,
                'is_required' => false,
                'due_days' => 7
            )
        )
    ),
    array(
        'name' => 'Operational Review',
        'description' => 'Business operations assessment checklist',
        'category' => 'operational',
        'is_public' => true,
        'is_active' => true,
        'items' => array(
            array(
                'title' => 'Organizational Chart',
                'description' => 'Review company structure and reporting lines',
                'type' => 'file',
                'order_number' => 1,
                'is_required' => true,
                'due_days' => 3
            ),
            array(
                'title' => 'Key Personnel Interviews',
                'description' => 'Conduct interviews with department heads',
                'type' => 'checkbox',
                'order_number' => 2,
                'is_required' => true,
                'due_days' => 10
            ),
            array(
                'title' => 'Sales Process Review',
                'description' => 'Analyze sales cycle, pipeline, and conversion rates',
                'type' => 'checkbox',
                'order_number' => 3,
                'is_required' => true,
                'due_days' => 7
            ),
            array(
                'title' => 'Operations Workflow Analysis',
                'description' => 'Document and analyze key operational processes',
                'type' => 'checkbox',
                'order_number' => 4,
                'is_required' => true,
                'due_days' => 10
            ),
            array(
                'title' => 'Technology Infrastructure',
                'description' => 'Review IT systems, software, and infrastructure',
                'type' => 'checkbox',
                'order_number' => 5,
                'is_required' => true,
                'due_days' => 7
            ),
            array(
                'title' => 'Supply Chain Analysis',
                'description' => 'Review supplier relationships and dependencies',
                'type' => 'checkbox',
                'order_number' => 6,
                'is_required' => false,
                'due_days' => 10
            ),
            array(
                'title' => 'Customer Satisfaction Review',
                'description' => 'Analyze customer feedback and retention metrics',
                'type' => 'checkbox',
                'order_number' => 7,
                'is_required' => false,
                'due_days' => 7
            ),
            array(
                'title' => 'Competitive Analysis',
                'description' => 'Assess competitive position and differentiation',
                'type' => 'checkbox',
                'order_number' => 8,
                'is_required' => true,
                'due_days' => 5
            ),
            array(
                'title' => 'Growth Opportunities',
                'description' => 'Identify potential growth initiatives and synergies',
                'type' => 'textarea',
                'order_number' => 9,
                'is_required' => false,
                'due_days' => 14
            ),
            array(
                'title' => 'Risk Assessment',
                'description' => 'Identify and evaluate operational risks',
                'type' => 'textarea',
                'order_number' => 10,
                'is_required' => true,
                'due_days' => 10
            )
        )
    )
);

// Create templates
$created = 0;
$errors = 0;

foreach ($templates as $templateData) {
    try {
        // Check if template already exists
        $existing = $db->query("SELECT id FROM checklist_templates WHERE name = '{$templateData['name']}' AND deleted = 0");
        if ($db->fetchByAssoc($existing)) {
            echo "Template '{$templateData['name']}' already exists, skipping...\n";
            continue;
        }
        
        // Create template
        $template = new ChecklistTemplate();
        $template->name = $templateData['name'];
        $template->description = $templateData['description'];
        $template->category = $templateData['category'];
        $template->is_public = $templateData['is_public'];
        $template->is_active = $templateData['is_active'];
        $template->created_by = $current_user->id;
        $template->assigned_user_id = $current_user->id;
        $template->item_count = count($templateData['items']);
        $template->save();
        
        // Create template items
        foreach ($templateData['items'] as $itemData) {
            $item = new ChecklistItem();
            $item->template_id = $template->id;
            $item->title = $itemData['title'];
            $item->description = $itemData['description'];
            $item->type = $itemData['type'];
            $item->order_number = $itemData['order_number'];
            $item->is_required = $itemData['is_required'];
            $item->due_days = $itemData['due_days'];
            $item->save();
        }
        
        echo "Created template: {$template->name}\n";
        $created++;
        
    } catch (Exception $e) {
        echo "Error creating template '{$templateData['name']}': " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n";
echo "Templates created: $created\n";
echo "Errors: $errors\n";

// Run Quick Repair and Rebuild
echo "\nRunning Quick Repair and Rebuild...\n";
$randc = new RepairAndClear();
$randc->repairAndClearAll(array('clearAll'), array(translate('LBL_ALL_MODULES')), true, false);

echo "\nDone!\n";