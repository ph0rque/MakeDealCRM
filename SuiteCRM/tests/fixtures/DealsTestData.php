<?php
/**
 * Test Data Setup for Deals Module
 * 
 * This script creates test data for comprehensive testing
 * 
 * @package MakeDealCRM
 * @subpackage Tests
 */

if (!defined('sugarEntry')) define('sugarEntry', true);

require_once('include/entryPoint.php');
require_once('modules/Deals/Deal.php');
require_once('modules/Contacts/Contact.php');
require_once('modules/Documents/Document.php');

class DealsTestData
{
    private $createdDeals = [];
    private $createdContacts = [];
    private $createdDocuments = [];
    
    /**
     * Create test deals with various statuses and data
     */
    public function createTestDeals()
    {
        global $current_user;
        
        // Deal stages for testing
        $stages = [
            'initial_contact',
            'nda_signed',
            'indicative_offer',
            'loi_submitted',
            'due_diligence',
            'definitive_agreement',
            'closed_won',
            'closed_lost'
        ];
        
        // Create deals in different stages
        foreach ($stages as $index => $stage) {
            $deal = new Deal();
            $deal->name = "Test Deal - {$stage} - " . uniqid();
            $deal->status = $stage;
            $deal->source = $index % 2 == 0 ? 'Broker' : 'Direct';
            $deal->assigned_user_id = $current_user->id;
            
            // Set financial data
            $deal->deal_value = rand(1, 20) * 1000000; // $1M - $20M
            $deal->asking_price_c = $deal->deal_value * 1.2;
            $deal->ttm_revenue_c = $deal->deal_value * 2;
            $deal->ttm_ebitda_c = $deal->ttm_revenue_c * 0.2; // 20% margin
            $deal->sde_c = $deal->ttm_ebitda_c * 1.1;
            $deal->target_multiple_c = rand(3, 7);
            
            // Set capital stack
            $deal->equity_c = $deal->deal_value * 0.3;
            $deal->senior_debt_c = $deal->deal_value * 0.5;
            $deal->seller_note_c = $deal->deal_value * 0.2;
            
            // Set focus
            $focusOptions = ['Business Services', 'Manufacturing', 'Technology', 'Healthcare', 'Retail'];
            $deal->focus_c = $focusOptions[array_rand($focusOptions)];
            
            // Vary the date in stage for at-risk testing
            if ($index < 3) {
                // Normal - recent
                $deal->date_in_current_stage = date('Y-m-d H:i:s', strtotime('-' . rand(1, 10) . ' days'));
            } elseif ($index < 5) {
                // Warning - 14-29 days
                $deal->date_in_current_stage = date('Y-m-d H:i:s', strtotime('-' . rand(14, 29) . ' days'));
            } else {
                // Alert - 30+ days
                $deal->date_in_current_stage = date('Y-m-d H:i:s', strtotime('-' . rand(30, 60) . ' days'));
            }
            
            $deal->description = "Test deal created for stage: {$stage}. This is a sample deal for testing purposes.";
            
            $deal->save();
            $this->createdDeals[] = $deal->id;
            
            // Create related records
            $this->createRelatedContacts($deal);
            $this->createRelatedDocuments($deal);
            $this->createRelatedActivities($deal);
        }
        
        // Create deals with edge cases
        $this->createEdgeCaseDeals();
        
        echo "Created " . count($this->createdDeals) . " test deals\n";
    }
    
    /**
     * Create deals with edge case data
     */
    private function createEdgeCaseDeals()
    {
        global $current_user;
        
        // Deal with zero values
        $deal = new Deal();
        $deal->name = "Test Deal - Zero Values";
        $deal->status = 'initial_contact';
        $deal->deal_value = 0;
        $deal->ttm_ebitda_c = 0;
        $deal->target_multiple_c = 0;
        $deal->assigned_user_id = $current_user->id;
        $deal->save();
        $this->createdDeals[] = $deal->id;
        
        // Deal with very large values
        $deal = new Deal();
        $deal->name = "Test Deal - Large Values";
        $deal->status = 'due_diligence';
        $deal->deal_value = 999999999;
        $deal->ttm_revenue_c = 999999999;
        $deal->ttm_ebitda_c = 200000000;
        $deal->target_multiple_c = 10;
        $deal->assigned_user_id = $current_user->id;
        $deal->save();
        $this->createdDeals[] = $deal->id;
        
        // Deal with negative EBITDA
        $deal = new Deal();
        $deal->name = "Test Deal - Negative EBITDA";
        $deal->status = 'indicative_offer';
        $deal->deal_value = 5000000;
        $deal->ttm_revenue_c = 10000000;
        $deal->ttm_ebitda_c = -500000;
        $deal->assigned_user_id = $current_user->id;
        $deal->save();
        $this->createdDeals[] = $deal->id;
        
        // Deal with special characters in name
        $deal = new Deal();
        $deal->name = "Test Deal - Special Chars !@#$%^&*()";
        $deal->status = 'nda_signed';
        $deal->deal_value = 2000000;
        $deal->assigned_user_id = $current_user->id;
        $deal->save();
        $this->createdDeals[] = $deal->id;
        
        // Deal with very long description
        $deal = new Deal();
        $deal->name = "Test Deal - Long Description";
        $deal->status = 'loi_submitted';
        $deal->description = str_repeat("This is a very long description. ", 500);
        $deal->deal_value = 3000000;
        $deal->assigned_user_id = $current_user->id;
        $deal->save();
        $this->createdDeals[] = $deal->id;
    }
    
    /**
     * Create related contacts for a deal
     */
    private function createRelatedContacts($deal)
    {
        $contactTypes = ['Seller', 'Broker', 'Attorney', 'Accountant', 'Advisor'];
        
        $numContacts = rand(1, 3);
        for ($i = 0; $i < $numContacts; $i++) {
            $contact = new Contact();
            $contact->first_name = "Test";
            $contact->last_name = $contactTypes[array_rand($contactTypes)] . "_" . uniqid();
            $contact->email1 = strtolower($contact->first_name . "." . $contact->last_name . "@example.com");
            $contact->phone_work = "555-" . rand(1000, 9999);
            $contact->title = $contactTypes[array_rand($contactTypes)];
            $contact->account_name = "Test Company " . uniqid();
            $contact->save();
            
            $this->createdContacts[] = $contact->id;
            
            // Relate to deal
            $deal->load_relationship('contacts');
            $deal->contacts->add($contact->id);
        }
    }
    
    /**
     * Create related documents for a deal
     */
    private function createRelatedDocuments($deal)
    {
        $docTypes = [
            'Financial Statement',
            'Tax Return',
            'NDA',
            'LOI',
            'Purchase Agreement',
            'Due Diligence Report'
        ];
        
        $numDocs = rand(0, 3);
        for ($i = 0; $i < $numDocs; $i++) {
            $doc = new Document();
            $doc->document_name = $docTypes[array_rand($docTypes)] . " - " . date('Y');
            $doc->filename = strtolower(str_replace(' ', '_', $doc->document_name)) . ".pdf";
            $doc->file_ext = 'pdf';
            $doc->file_mime_type = 'application/pdf';
            $doc->category_id = 'Financial';
            $doc->status_id = 'Active';
            $doc->save();
            
            $this->createdDocuments[] = $doc->id;
            
            // Relate to deal
            $deal->load_relationship('documents');
            $deal->documents->add($doc->id);
        }
    }
    
    /**
     * Create related activities for a deal
     */
    private function createRelatedActivities($deal)
    {
        global $current_user;
        
        // Create a call
        require_once('modules/Calls/Call.php');
        $call = new Call();
        $call->name = "Initial Discussion - " . $deal->name;
        $call->date_start = date('Y-m-d H:i:s', strtotime('+' . rand(1, 7) . ' days'));
        $call->duration_hours = 1;
        $call->duration_minutes = 0;
        $call->status = 'Planned';
        $call->direction = 'Outbound';
        $call->parent_type = 'Deals';
        $call->parent_id = $deal->id;
        $call->assigned_user_id = $current_user->id;
        $call->save();
        
        // Create a task
        require_once('modules/Tasks/Task.php');
        $task = new Task();
        $task->name = "Review Financials - " . $deal->name;
        $task->date_due = date('Y-m-d', strtotime('+' . rand(1, 14) . ' days'));
        $task->status = 'Not Started';
        $task->priority = 'High';
        $task->parent_type = 'Deals';
        $task->parent_id = $deal->id;
        $task->assigned_user_id = $current_user->id;
        $task->save();
        
        // Create a note
        require_once('modules/Notes/Note.php');
        $note = new Note();
        $note->name = "Meeting Notes - " . $deal->name;
        $note->description = "Initial meeting notes:\n- Discussed valuation\n- Reviewed financials\n- Next steps identified";
        $note->parent_type = 'Deals';
        $note->parent_id = $deal->id;
        $note->save();
    }
    
    /**
     * Create test email templates
     */
    public function createTestEmailTemplates()
    {
        require_once('modules/EmailTemplates/EmailTemplate.php');
        
        $templates = [
            [
                'name' => 'Deal Introduction',
                'subject' => 'Introduction - {DEAL_NAME}',
                'body' => 'Dear Contact,\n\nI wanted to introduce you to our current opportunity: {DEAL_NAME}.\n\nCurrent Status: {DEAL_STATUS}\nDeal Value: {DEAL_VALUE}\n\nBest regards,\n{ASSIGNED_USER}'
            ],
            [
                'name' => 'NDA Request',
                'subject' => 'NDA Required - {DEAL_NAME}',
                'body' => 'Dear Contact,\n\nTo proceed with {DEAL_NAME}, we need to execute a Non-Disclosure Agreement.\n\nPlease find the NDA attached for your review and signature.\n\nBest regards,\n{ASSIGNED_USER}'
            ],
            [
                'name' => 'Deal Update',
                'subject' => 'Update on {DEAL_NAME}',
                'body' => 'Dear Contact,\n\nI wanted to provide you with an update on {DEAL_NAME}.\n\nThe deal is currently in {DEAL_STATUS} stage.\n\nLet me know if you have any questions.\n\nBest regards,\n{ASSIGNED_USER}'
            ]
        ];
        
        foreach ($templates as $templateData) {
            $template = new EmailTemplate();
            $template->name = $templateData['name'] . ' - Test';
            $template->subject = $templateData['subject'];
            $template->body = $templateData['body'];
            $template->body_html = nl2br($templateData['body']);
            $template->type = 'email';
            $template->save();
        }
        
        echo "Created " . count($templates) . " test email templates\n";
    }
    
    /**
     * Cleanup test data
     */
    public function cleanup()
    {
        global $db;
        
        // Delete deals
        foreach ($this->createdDeals as $dealId) {
            $deal = BeanFactory::getBean('Deals', $dealId);
            if ($deal) {
                $deal->mark_deleted($dealId);
            }
        }
        
        // Delete contacts
        foreach ($this->createdContacts as $contactId) {
            $contact = BeanFactory::getBean('Contacts', $contactId);
            if ($contact) {
                $contact->mark_deleted($contactId);
            }
        }
        
        // Delete documents
        foreach ($this->createdDocuments as $docId) {
            $doc = BeanFactory::getBean('Documents', $docId);
            if ($doc) {
                $doc->mark_deleted($docId);
            }
        }
        
        echo "Cleaned up test data\n";
    }
}

// Run the test data creation
$testData = new DealsTestData();

// Parse command line arguments
$action = isset($argv[1]) ? $argv[1] : 'create';

switch ($action) {
    case 'create':
        $testData->createTestDeals();
        $testData->createTestEmailTemplates();
        break;
    case 'cleanup':
        $testData->cleanup();
        break;
    default:
        echo "Usage: php DealsTestData.php [create|cleanup]\n";
}