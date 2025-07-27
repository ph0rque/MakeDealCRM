<?php
/**
 * Test Data Factory for MakeDealCRM
 * 
 * This class provides comprehensive test data generation for all modules
 * including Deals, Contacts, Checklists, Pipelines, and Email Templates
 * 
 * @package MakeDealCRM
 * @subpackage Tests
 */

if (!defined('sugarEntry')) define('sugarEntry', true);

require_once('include/entryPoint.php');
require_once('include/utils.php');
require_once('modules/Deals/Deal.php');
require_once('modules/Contacts/Contact.php');
require_once('modules/Documents/Document.php');
require_once('modules/EmailTemplates/EmailTemplate.php');
require_once('modules/Accounts/Account.php');
require_once('modules/Calls/Call.php');
require_once('modules/Tasks/Task.php');
require_once('modules/Notes/Note.php');

class TestDataFactory
{
    private $createdRecords = [];
    private $testPrefix = 'TEST_';
    private $testRunId;
    
    // Predefined test data sets
    private $dealStages = [
        'sourcing' => ['days_in_stage' => 5, 'probability' => 5],
        'initial_contact' => ['days_in_stage' => 7, 'probability' => 10],
        'nda_signed' => ['days_in_stage' => 10, 'probability' => 20],
        'indicative_offer' => ['days_in_stage' => 14, 'probability' => 30],
        'loi_submitted' => ['days_in_stage' => 21, 'probability' => 50],
        'due_diligence' => ['days_in_stage' => 30, 'probability' => 70],
        'definitive_agreement' => ['days_in_stage' => 14, 'probability' => 90],
        'closed_won' => ['days_in_stage' => 0, 'probability' => 100],
        'closed_lost' => ['days_in_stage' => 0, 'probability' => 0]
    ];
    
    private $industries = [
        'Business Services', 'Manufacturing', 'Technology', 
        'Healthcare', 'Retail', 'Financial Services',
        'Energy', 'Construction', 'Transportation'
    ];
    
    private $dealSources = [
        'Broker', 'Direct', 'Referral', 'Investment Bank', 
        'Private Equity', 'Website', 'Conference'
    ];
    
    private $contactRoles = [
        'Seller', 'Broker', 'Attorney', 'Accountant', 
        'Advisor', 'Key Employee', 'Buyer Representative'
    ];
    
    public function __construct($testRunId = null)
    {
        global $current_user;
        if (empty($current_user->id)) {
            $current_user = BeanFactory::getBean('Users', '1');
        }
        
        $this->testRunId = $testRunId ?: date('YmdHis') . '_' . uniqid();
    }
    
    /**
     * Create a full test scenario with all related data
     */
    public function createFullTestScenario($scenarioName = 'default')
    {
        $scenario = [];
        
        switch ($scenarioName) {
            case 'pipeline':
                $scenario = $this->createPipelineScenario();
                break;
                
            case 'at_risk':
                $scenario = $this->createAtRiskScenario();
                break;
                
            case 'financial':
                $scenario = $this->createFinancialScenario();
                break;
                
            case 'checklist':
                $scenario = $this->createChecklistScenario();
                break;
                
            case 'edge_cases':
                $scenario = $this->createEdgeCaseScenario();
                break;
                
            default:
                $scenario = $this->createDefaultScenario();
        }
        
        return $scenario;
    }
    
    /**
     * Create default test scenario with variety of data
     */
    private function createDefaultScenario()
    {
        $scenario = [
            'accounts' => [],
            'contacts' => [],
            'deals' => [],
            'checklists' => [],
            'templates' => []
        ];
        
        // Create test accounts
        for ($i = 0; $i < 3; $i++) {
            $account = $this->createAccount([
                'name' => $this->testPrefix . "Test Company " . ($i + 1),
                'industry' => $this->industries[array_rand($this->industries)],
                'annual_revenue' => rand(1000000, 50000000)
            ]);
            $scenario['accounts'][] = $account;
        }
        
        // Create test contacts for each account
        foreach ($scenario['accounts'] as $account) {
            for ($j = 0; $j < rand(2, 4); $j++) {
                $contact = $this->createContact([
                    'account_id' => $account->id,
                    'title' => $this->contactRoles[array_rand($this->contactRoles)]
                ]);
                $scenario['contacts'][] = $contact;
            }
        }
        
        // Create deals in various stages
        foreach ($this->dealStages as $stage => $stageInfo) {
            $accountIndex = array_rand($scenario['accounts']);
            $account = $scenario['accounts'][$accountIndex];
            
            $deal = $this->createDeal([
                'status' => $stage,
                'account_id' => $account->id,
                'date_in_current_stage' => date('Y-m-d H:i:s', 
                    strtotime('-' . $stageInfo['days_in_stage'] . ' days'))
            ]);
            
            // Relate contacts
            $numContacts = rand(1, 3);
            for ($k = 0; $k < $numContacts; $k++) {
                $contactIndex = array_rand($scenario['contacts']);
                $this->relateBeans($deal, $scenario['contacts'][$contactIndex]);
            }
            
            // Create activities
            $this->createActivitiesForDeal($deal);
            
            $scenario['deals'][] = $deal;
        }
        
        // Create checklist templates
        $scenario['templates'] = $this->createChecklistTemplates();
        
        // Create email templates
        $this->createEmailTemplates();
        
        return $scenario;
    }
    
    /**
     * Create pipeline test scenario
     */
    private function createPipelineScenario()
    {
        $scenario = [
            'pipelines' => [],
            'deals' => []
        ];
        
        // Create pipeline stages
        $stages = ['Sourcing', 'Qualification', 'Negotiation', 'Due Diligence', 'Closing'];
        
        foreach ($stages as $index => $stageName) {
            // Create multiple deals per stage
            for ($i = 0; $i < rand(2, 4); $i++) {
                $deal = $this->createDeal([
                    'name' => $this->testPrefix . "{$stageName} Deal " . ($i + 1),
                    'status' => strtolower(str_replace(' ', '_', $stageName)),
                    'pipeline_stage' => $stageName,
                    'pipeline_order' => $index,
                    'deal_value' => rand(500000, 5000000),
                    'probability' => ($index + 1) * 20
                ]);
                
                $scenario['deals'][] = $deal;
            }
        }
        
        return $scenario;
    }
    
    /**
     * Create at-risk deals scenario
     */
    private function createAtRiskScenario()
    {
        $scenario = ['deals' => []];
        
        // Normal deal (recent activity)
        $scenario['deals'][] = $this->createDeal([
            'name' => $this->testPrefix . 'Active Deal - Normal',
            'status' => 'due_diligence',
            'date_in_current_stage' => date('Y-m-d H:i:s', strtotime('-5 days')),
            'at_risk_status' => 'normal'
        ]);
        
        // Warning deal (14-29 days)
        $scenario['deals'][] = $this->createDeal([
            'name' => $this->testPrefix . 'Stagnant Deal - Warning',
            'status' => 'loi_submitted',
            'date_in_current_stage' => date('Y-m-d H:i:s', strtotime('-20 days')),
            'at_risk_status' => 'warning'
        ]);
        
        // Alert deal (30+ days)
        $scenario['deals'][] = $this->createDeal([
            'name' => $this->testPrefix . 'Dormant Deal - Alert',
            'status' => 'indicative_offer',
            'date_in_current_stage' => date('Y-m-d H:i:s', strtotime('-45 days')),
            'at_risk_status' => 'alert'
        ]);
        
        return $scenario;
    }
    
    /**
     * Create financial scenario with various financial metrics
     */
    private function createFinancialScenario()
    {
        $scenario = ['deals' => []];
        
        // Deal with positive EBITDA
        $scenario['deals'][] = $this->createDeal([
            'name' => $this->testPrefix . 'Profitable Business',
            'deal_value' => 10000000,
            'ttm_revenue_c' => 15000000,
            'ttm_ebitda_c' => 3000000,
            'sde_c' => 3500000,
            'target_multiple_c' => 4.5,
            'equity_c' => 3000000,
            'senior_debt_c' => 5000000,
            'seller_note_c' => 2000000
        ]);
        
        // Deal with negative EBITDA
        $scenario['deals'][] = $this->createDeal([
            'name' => $this->testPrefix . 'Turnaround Opportunity',
            'deal_value' => 2000000,
            'ttm_revenue_c' => 5000000,
            'ttm_ebitda_c' => -500000,
            'sde_c' => 200000,
            'target_multiple_c' => 2.0
        ]);
        
        // High-value deal
        $scenario['deals'][] = $this->createDeal([
            'name' => $this->testPrefix . 'Large Enterprise Deal',
            'deal_value' => 100000000,
            'ttm_revenue_c' => 150000000,
            'ttm_ebitda_c' => 30000000,
            'target_multiple_c' => 8.0
        ]);
        
        return $scenario;
    }
    
    /**
     * Create checklist scenario
     */
    private function createChecklistScenario()
    {
        $scenario = [
            'templates' => [],
            'deals' => [],
            'checklists' => []
        ];
        
        // Create checklist templates
        $templates = [
            [
                'name' => 'Due Diligence Checklist',
                'stage' => 'due_diligence',
                'items' => [
                    'Review Financial Statements',
                    'Verify Tax Returns',
                    'Check Legal Compliance',
                    'Assess Customer Contracts',
                    'Evaluate Employee Agreements'
                ]
            ],
            [
                'name' => 'LOI Checklist',
                'stage' => 'loi_submitted',
                'items' => [
                    'Draft LOI Terms',
                    'Internal Approval',
                    'Legal Review',
                    'Submit to Seller'
                ]
            ]
        ];
        
        foreach ($templates as $templateData) {
            $template = $this->createChecklistTemplate($templateData);
            $scenario['templates'][] = $template;
            
            // Create a deal with this checklist
            $deal = $this->createDeal([
                'name' => $this->testPrefix . 'Deal with ' . $templateData['name'],
                'status' => $templateData['stage']
            ]);
            
            // Apply checklist to deal
            $checklist = $this->applyChecklistToDeal($template, $deal);
            
            $scenario['deals'][] = $deal;
            $scenario['checklists'][] = $checklist;
        }
        
        return $scenario;
    }
    
    /**
     * Create edge case scenario
     */
    private function createEdgeCaseScenario()
    {
        $scenario = ['deals' => []];
        
        // Deal with zero values
        $scenario['deals'][] = $this->createDeal([
            'name' => $this->testPrefix . 'Zero Value Deal',
            'deal_value' => 0,
            'ttm_revenue_c' => 0,
            'ttm_ebitda_c' => 0
        ]);
        
        // Deal with very long name
        $scenario['deals'][] = $this->createDeal([
            'name' => $this->testPrefix . str_repeat('Long Name ', 20),
            'deal_value' => 1000000
        ]);
        
        // Deal with special characters
        $scenario['deals'][] = $this->createDeal([
            'name' => $this->testPrefix . 'Special Deal !@#$%^&*()',
            'description' => "Deal with special chars: <script>alert('test')</script>"
        ]);
        
        // Deal with maximum values
        $scenario['deals'][] = $this->createDeal([
            'name' => $this->testPrefix . 'Max Value Deal',
            'deal_value' => 999999999,
            'ttm_revenue_c' => 999999999,
            'target_multiple_c' => 99.9
        ]);
        
        return $scenario;
    }
    
    /**
     * Create a test account
     */
    public function createAccount($data = [])
    {
        $account = BeanFactory::newBean('Accounts');
        
        $defaults = [
            'name' => $this->testPrefix . 'Test Account ' . uniqid(),
            'account_type' => 'Customer',
            'industry' => $this->industries[array_rand($this->industries)],
            'annual_revenue' => rand(1000000, 10000000),
            'employees' => rand(10, 1000),
            'website' => 'https://www.testcompany' . uniqid() . '.com',
            'phone_office' => $this->generatePhone(),
            'billing_address_street' => rand(100, 9999) . ' Test Street',
            'billing_address_city' => 'Test City',
            'billing_address_state' => 'CA',
            'billing_address_postalcode' => rand(10000, 99999),
            'billing_address_country' => 'USA',
            'description' => 'Test account created for E2E testing'
        ];
        
        foreach (array_merge($defaults, $data) as $field => $value) {
            $account->$field = $value;
        }
        
        $account->save();
        $this->trackRecord('Accounts', $account->id);
        
        return $account;
    }
    
    /**
     * Create a test contact
     */
    public function createContact($data = [])
    {
        $contact = BeanFactory::newBean('Contacts');
        
        $firstNames = ['John', 'Jane', 'Robert', 'Sarah', 'Michael', 'Emma'];
        $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Davis'];
        
        $firstName = $firstNames[array_rand($firstNames)];
        $lastName = $lastNames[array_rand($lastNames)];
        
        $defaults = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'salutation' => rand(0, 1) ? 'Mr.' : 'Ms.',
            'title' => $this->contactRoles[array_rand($this->contactRoles)],
            'department' => 'Executive',
            'email1' => strtolower($firstName . '.' . $lastName . uniqid() . '@testcompany.com'),
            'phone_work' => $this->generatePhone(),
            'phone_mobile' => $this->generatePhone(),
            'primary_address_street' => rand(100, 9999) . ' Business Ave',
            'primary_address_city' => 'Test City',
            'primary_address_state' => 'CA',
            'primary_address_postalcode' => rand(10000, 99999),
            'primary_address_country' => 'USA',
            'description' => 'Test contact for E2E testing'
        ];
        
        foreach (array_merge($defaults, $data) as $field => $value) {
            $contact->$field = $value;
        }
        
        $contact->save();
        $this->trackRecord('Contacts', $contact->id);
        
        return $contact;
    }
    
    /**
     * Create a test deal
     */
    public function createDeal($data = [])
    {
        global $current_user;
        
        $deal = BeanFactory::newBean('Deals');
        
        $dealValue = isset($data['deal_value']) ? $data['deal_value'] : rand(1000000, 20000000);
        $ttmRevenue = isset($data['ttm_revenue_c']) ? $data['ttm_revenue_c'] : $dealValue * 2;
        $ttmEbitda = isset($data['ttm_ebitda_c']) ? $data['ttm_ebitda_c'] : $ttmRevenue * 0.2;
        
        $defaults = [
            'name' => $this->testPrefix . 'Test Deal ' . uniqid(),
            'status' => 'initial_contact',
            'source' => $this->dealSources[array_rand($this->dealSources)],
            'deal_value' => $dealValue,
            'ttm_revenue_c' => $ttmRevenue,
            'ttm_ebitda_c' => $ttmEbitda,
            'sde_c' => $ttmEbitda * 1.1,
            'target_multiple_c' => rand(30, 70) / 10,
            'asking_price_c' => $ttmEbitda * 4,
            'focus_c' => $this->industries[array_rand($this->industries)],
            'equity_c' => $dealValue * 0.3,
            'senior_debt_c' => $dealValue * 0.5,
            'seller_note_c' => $dealValue * 0.2,
            'date_in_current_stage' => date('Y-m-d H:i:s'),
            'assigned_user_id' => $current_user->id,
            'description' => 'Test deal created for E2E testing'
        ];
        
        foreach (array_merge($defaults, $data) as $field => $value) {
            $deal->$field = $value;
        }
        
        $deal->save();
        $this->trackRecord('Deals', $deal->id);
        
        return $deal;
    }
    
    /**
     * Create checklist template
     */
    public function createChecklistTemplate($data = [])
    {
        if (!class_exists('ChecklistTemplate')) {
            // Create a mock if the class doesn't exist
            return $this->createMockChecklistTemplate($data);
        }
        
        $template = BeanFactory::newBean('ChecklistTemplates');
        
        $defaults = [
            'name' => $this->testPrefix . 'Test Checklist Template',
            'stage' => 'due_diligence',
            'is_active' => 1,
            'description' => 'Test checklist template'
        ];
        
        foreach (array_merge($defaults, $data) as $field => $value) {
            if ($field !== 'items') {
                $template->$field = $value;
            }
        }
        
        $template->save();
        $this->trackRecord('ChecklistTemplates', $template->id);
        
        // Create checklist items
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $index => $itemName) {
                $this->createChecklistItem([
                    'template_id' => $template->id,
                    'name' => $itemName,
                    'order_number' => $index + 1
                ]);
            }
        }
        
        return $template;
    }
    
    /**
     * Create mock checklist template for testing
     */
    private function createMockChecklistTemplate($data)
    {
        $template = new stdClass();
        $template->id = 'mock_template_' . uniqid();
        $template->name = $data['name'] ?? 'Mock Template';
        $template->stage = $data['stage'] ?? 'due_diligence';
        $template->items = $data['items'] ?? [];
        
        return $template;
    }
    
    /**
     * Create checklist item
     */
    public function createChecklistItem($data = [])
    {
        if (!class_exists('ChecklistItem')) {
            return null;
        }
        
        $item = BeanFactory::newBean('ChecklistItems');
        
        $defaults = [
            'name' => 'Test Checklist Item',
            'description' => 'Test item description',
            'is_required' => 1,
            'order_number' => 1
        ];
        
        foreach (array_merge($defaults, $data) as $field => $value) {
            $item->$field = $value;
        }
        
        $item->save();
        $this->trackRecord('ChecklistItems', $item->id);
        
        return $item;
    }
    
    /**
     * Apply checklist template to deal
     */
    public function applyChecklistToDeal($template, $deal)
    {
        if (!class_exists('DealChecklist')) {
            return null;
        }
        
        $checklist = BeanFactory::newBean('DealChecklists');
        $checklist->deal_id = $deal->id;
        $checklist->template_id = $template->id;
        $checklist->name = $template->name;
        $checklist->save();
        
        $this->trackRecord('DealChecklists', $checklist->id);
        
        return $checklist;
    }
    
    /**
     * Create activities for a deal
     */
    public function createActivitiesForDeal($deal)
    {
        global $current_user;
        
        // Create a call
        $call = BeanFactory::newBean('Calls');
        $call->name = "Call regarding " . $deal->name;
        $call->date_start = date('Y-m-d H:i:s', strtotime('+' . rand(1, 7) . ' days'));
        $call->duration_hours = 1;
        $call->duration_minutes = 0;
        $call->status = 'Planned';
        $call->parent_type = 'Deals';
        $call->parent_id = $deal->id;
        $call->assigned_user_id = $current_user->id;
        $call->save();
        $this->trackRecord('Calls', $call->id);
        
        // Create a task
        $task = BeanFactory::newBean('Tasks');
        $task->name = "Review documents for " . $deal->name;
        $task->date_due = date('Y-m-d', strtotime('+' . rand(1, 14) . ' days'));
        $task->status = 'Not Started';
        $task->priority = 'High';
        $task->parent_type = 'Deals';
        $task->parent_id = $deal->id;
        $task->assigned_user_id = $current_user->id;
        $task->save();
        $this->trackRecord('Tasks', $task->id);
        
        // Create a note
        $note = BeanFactory::newBean('Notes');
        $note->name = "Meeting notes for " . $deal->name;
        $note->description = "Initial assessment:\n- Strong financials\n- Good market position\n- Due diligence required";
        $note->parent_type = 'Deals';
        $note->parent_id = $deal->id;
        $note->save();
        $this->trackRecord('Notes', $note->id);
        
        return [$call, $task, $note];
    }
    
    /**
     * Create email templates
     */
    public function createEmailTemplates()
    {
        $templates = [
            [
                'name' => 'Deal Introduction',
                'subject' => 'Introduction - {DEAL_NAME}',
                'body' => "Dear {CONTACT_NAME},\n\nI wanted to introduce you to {DEAL_NAME}.\n\nDeal Value: {DEAL_VALUE}\nStatus: {DEAL_STATUS}\n\nBest regards,\n{ASSIGNED_USER}"
            ],
            [
                'name' => 'NDA Request',
                'subject' => 'NDA Required - {DEAL_NAME}',
                'body' => "Dear {CONTACT_NAME},\n\nTo proceed with {DEAL_NAME}, please sign the attached NDA.\n\nThank you,\n{ASSIGNED_USER}"
            ],
            [
                'name' => 'Due Diligence Update',
                'subject' => 'DD Update - {DEAL_NAME}',
                'body' => "Team,\n\nDue diligence for {DEAL_NAME} is progressing.\n\nCompleted items:\n- Financial review\n- Legal review\n\nPending:\n- Customer interviews\n\nRegards,\n{ASSIGNED_USER}"
            ]
        ];
        
        foreach ($templates as $templateData) {
            $template = BeanFactory::newBean('EmailTemplates');
            $template->name = $this->testPrefix . $templateData['name'];
            $template->subject = $templateData['subject'];
            $template->body = $templateData['body'];
            $template->body_html = nl2br($templateData['body']);
            $template->type = 'campaign';
            $template->save();
            
            $this->trackRecord('EmailTemplates', $template->id);
        }
    }
    
    /**
     * Create document for deal
     */
    public function createDocument($deal, $docType = 'Financial Statement')
    {
        $doc = BeanFactory::newBean('Documents');
        $doc->document_name = $docType . ' - ' . $deal->name;
        $doc->filename = strtolower(str_replace(' ', '_', $doc->document_name)) . '.pdf';
        $doc->file_ext = 'pdf';
        $doc->file_mime_type = 'application/pdf';
        $doc->category_id = 'Financial';
        $doc->status_id = 'Active';
        $doc->save();
        
        $this->trackRecord('Documents', $doc->id);
        
        // Relate to deal
        if ($deal->load_relationship('documents')) {
            $deal->documents->add($doc->id);
        }
        
        return $doc;
    }
    
    /**
     * Relate two beans
     */
    public function relateBeans($bean1, $bean2)
    {
        $module2 = $bean2->module_name;
        $relationship = strtolower($module2);
        
        if ($bean1->load_relationship($relationship)) {
            $bean1->$relationship->add($bean2->id);
        }
    }
    
    /**
     * Generate phone number
     */
    private function generatePhone()
    {
        return sprintf('(%03d) %03d-%04d', 
            rand(200, 999), 
            rand(200, 999), 
            rand(1000, 9999)
        );
    }
    
    /**
     * Track created record for cleanup
     */
    private function trackRecord($module, $id)
    {
        $this->createdRecords[] = [
            'module' => $module,
            'id' => $id,
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get all created records
     */
    public function getCreatedRecords()
    {
        return $this->createdRecords;
    }
    
    /**
     * Cleanup all created test data
     */
    public function cleanup()
    {
        echo "Cleaning up " . count($this->createdRecords) . " test records...\n";
        
        // Reverse order to handle dependencies
        $recordsToDelete = array_reverse($this->createdRecords);
        
        foreach ($recordsToDelete as $record) {
            try {
                $bean = BeanFactory::getBean($record['module'], $record['id']);
                if ($bean && !empty($bean->id)) {
                    $bean->mark_deleted($bean->id);
                    echo "Deleted {$record['module']} record: {$record['id']}\n";
                }
            } catch (Exception $e) {
                echo "Failed to delete {$record['module']} record {$record['id']}: " . $e->getMessage() . "\n";
            }
        }
        
        // Clear the tracking array
        $this->createdRecords = [];
        
        echo "Cleanup completed.\n";
    }
    
    /**
     * Export test data configuration
     */
    public function exportConfiguration($filename)
    {
        $config = [
            'test_run_id' => $this->testRunId,
            'created_at' => date('Y-m-d H:i:s'),
            'records' => $this->createdRecords,
            'summary' => $this->getSummary()
        ];
        
        file_put_contents($filename, json_encode($config, JSON_PRETTY_PRINT));
        echo "Test configuration exported to: $filename\n";
    }
    
    /**
     * Get summary of created test data
     */
    public function getSummary()
    {
        $summary = [];
        
        foreach ($this->createdRecords as $record) {
            if (!isset($summary[$record['module']])) {
                $summary[$record['module']] = 0;
            }
            $summary[$record['module']]++;
        }
        
        return $summary;
    }
}