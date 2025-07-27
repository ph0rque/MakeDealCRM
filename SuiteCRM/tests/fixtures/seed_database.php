<?php
/**
 * Quick Database Seeder for E2E Tests
 * 
 * This script provides a simple way to seed test data
 * without the full framework overhead
 * 
 * @package MakeDealCRM
 * @subpackage Tests
 */

if (!defined('sugarEntry')) define('sugarEntry', true);

// Include required files
require_once(dirname(__FILE__) . '/../../include/entryPoint.php');

class QuickSeeder
{
    private $db;
    private $testPrefix = 'E2E_TEST_';
    private $createdIds = [];
    
    public function __construct()
    {
        global $db;
        $this->db = $db;
    }
    
    /**
     * Seed essential test data
     */
    public function seedEssentialData()
    {
        echo "Seeding essential test data...\n";
        
        // Create test accounts
        $accountIds = $this->createTestAccounts();
        echo "Created " . count($accountIds) . " test accounts\n";
        
        // Create test contacts
        $contactIds = $this->createTestContacts($accountIds);
        echo "Created " . count($contactIds) . " test contacts\n";
        
        // Create test deals
        $dealIds = $this->createTestDeals($accountIds, $contactIds);
        echo "Created " . count($dealIds) . " test deals\n";
        
        // Create pipeline test data
        $this->createPipelineData();
        echo "Created pipeline test data\n";
        
        $this->saveCreatedIds();
        
        echo "\nSeeding completed!\n";
        echo "Total records created: " . array_sum(array_map('count', $this->createdIds)) . "\n";
    }
    
    /**
     * Create test accounts
     */
    private function createTestAccounts()
    {
        $accounts = [
            [
                'name' => $this->testPrefix . 'Acme Manufacturing Corp',
                'account_type' => 'Customer',
                'industry' => 'Manufacturing',
                'annual_revenue' => '25000000',
                'employees' => '150',
                'phone_office' => '(555) 123-4567',
                'website' => 'https://acmemfg.com'
            ],
            [
                'name' => $this->testPrefix . 'TechStart Solutions LLC',
                'account_type' => 'Prospect',
                'industry' => 'Technology',
                'annual_revenue' => '5000000',
                'employees' => '45',
                'phone_office' => '(555) 234-5678',
                'website' => 'https://techstart.com'
            ],
            [
                'name' => $this->testPrefix . 'Regional Healthcare Group',
                'account_type' => 'Customer',
                'industry' => 'Healthcare',
                'annual_revenue' => '40000000',
                'employees' => '300',
                'phone_office' => '(555) 345-6789',
                'website' => 'https://regionalhealthcare.com'
            ]
        ];
        
        $accountIds = [];
        
        foreach ($accounts as $account) {
            $id = create_guid();
            $account['id'] = $id;
            $account['date_entered'] = gmdate('Y-m-d H:i:s');
            $account['date_modified'] = gmdate('Y-m-d H:i:s');
            $account['created_by'] = '1';
            $account['modified_user_id'] = '1';
            $account['deleted'] = 0;
            
            $this->insertRecord('accounts', $account);
            $accountIds[] = $id;
        }
        
        $this->createdIds['accounts'] = $accountIds;
        return $accountIds;
    }
    
    /**
     * Create test contacts
     */
    private function createTestContacts($accountIds)
    {
        $contacts = [
            [
                'first_name' => 'John',
                'last_name' => 'Smith',
                'title' => 'CEO',
                'email1' => 'john.smith@acmemfg.com',
                'phone_work' => '(555) 123-4567',
                'account_id' => $accountIds[0]
            ],
            [
                'first_name' => 'Sarah',
                'last_name' => 'Johnson',
                'title' => 'CFO',
                'email1' => 'sarah.johnson@acmemfg.com',
                'phone_work' => '(555) 123-4568',
                'account_id' => $accountIds[0]
            ],
            [
                'first_name' => 'Mike',
                'last_name' => 'Chen',
                'title' => 'Founder',
                'email1' => 'mike@techstart.com',
                'phone_work' => '(555) 234-5678',
                'account_id' => $accountIds[1]
            ],
            [
                'first_name' => 'Dr. Emily',
                'last_name' => 'Williams',
                'title' => 'Medical Director',
                'email1' => 'ewilliams@regionalhealthcare.com',
                'phone_work' => '(555) 345-6789',
                'account_id' => $accountIds[2]
            ]
        ];
        
        $contactIds = [];
        
        foreach ($contacts as $contact) {
            $id = create_guid();
            $contact['id'] = $id;
            $contact['date_entered'] = gmdate('Y-m-d H:i:s');
            $contact['date_modified'] = gmdate('Y-m-d H:i:s');
            $contact['created_by'] = '1';
            $contact['modified_user_id'] = '1';
            $contact['deleted'] = 0;
            
            $this->insertRecord('contacts', $contact);
            $contactIds[] = $id;
        }
        
        $this->createdIds['contacts'] = $contactIds;
        return $contactIds;
    }
    
    /**
     * Create test deals
     */
    private function createTestDeals($accountIds, $contactIds)
    {
        $deals = [
            [
                'name' => $this->testPrefix . 'Acme Manufacturing Acquisition',
                'status' => 'due_diligence',
                'source' => 'Broker',
                'deal_value' => 15000000,
                'assigned_user_id' => '1',
                'account_id' => $accountIds[0],
                'date_in_current_stage' => gmdate('Y-m-d H:i:s', strtotime('-10 days'))
            ],
            [
                'name' => $this->testPrefix . 'TechStart SaaS Platform',
                'status' => 'loi_submitted',
                'source' => 'Direct',
                'deal_value' => 8000000,
                'assigned_user_id' => '1',
                'account_id' => $accountIds[1],
                'date_in_current_stage' => gmdate('Y-m-d H:i:s', strtotime('-5 days'))
            ],
            [
                'name' => $this->testPrefix . 'Healthcare Services Group',
                'status' => 'initial_contact',
                'source' => 'Investment Bank',
                'deal_value' => 25000000,
                'assigned_user_id' => '1',
                'account_id' => $accountIds[2],
                'date_in_current_stage' => gmdate('Y-m-d H:i:s', strtotime('-2 days'))
            ],
            [
                'name' => $this->testPrefix . 'Stalled Deal - At Risk',
                'status' => 'due_diligence',
                'source' => 'Broker',
                'deal_value' => 5000000,
                'assigned_user_id' => '1',
                'date_in_current_stage' => gmdate('Y-m-d H:i:s', strtotime('-35 days'))
            ]
        ];
        
        $dealIds = [];
        
        foreach ($deals as $index => $deal) {
            $id = create_guid();
            $deal['id'] = $id;
            $deal['date_entered'] = gmdate('Y-m-d H:i:s');
            $deal['date_modified'] = gmdate('Y-m-d H:i:s');
            $deal['created_by'] = '1';
            $deal['modified_user_id'] = '1';
            $deal['deleted'] = 0;
            
            $this->insertRecord('deals', $deal);
            
            // Create deal-contact relationship
            if (isset($contactIds[$index])) {
                $this->createRelationship('deals_contacts', $id, $contactIds[$index]);
            }
            
            $dealIds[] = $id;
        }
        
        $this->createdIds['deals'] = $dealIds;
        return $dealIds;
    }
    
    /**
     * Create pipeline test data
     */
    private function createPipelineData()
    {
        $stages = [
            'sourcing' => 3,
            'initial_contact' => 2,
            'nda_signed' => 2,
            'indicative_offer' => 1,
            'loi_submitted' => 1,
            'due_diligence' => 1
        ];
        
        $pipelineIds = [];
        
        foreach ($stages as $stage => $count) {
            for ($i = 0; $i < $count; $i++) {
                $id = create_guid();
                $deal = [
                    'id' => $id,
                    'name' => $this->testPrefix . ucfirst(str_replace('_', ' ', $stage)) . ' Deal ' . ($i + 1),
                    'status' => $stage,
                    'source' => 'Broker',
                    'deal_value' => rand(1000000, 10000000),
                    'assigned_user_id' => '1',
                    'date_entered' => gmdate('Y-m-d H:i:s'),
                    'date_modified' => gmdate('Y-m-d H:i:s'),
                    'created_by' => '1',
                    'modified_user_id' => '1',
                    'deleted' => 0,
                    'date_in_current_stage' => gmdate('Y-m-d H:i:s', strtotime('-' . rand(1, 14) . ' days'))
                ];
                
                $this->insertRecord('deals', $deal);
                $pipelineIds[] = $id;
            }
        }
        
        $this->createdIds['pipeline_deals'] = $pipelineIds;
    }
    
    /**
     * Insert record into database
     */
    private function insertRecord($table, $data)
    {
        $fields = array_keys($data);
        $values = array_values($data);
        $placeholders = str_repeat('?,', count($values) - 1) . '?';
        
        $sql = "INSERT INTO $table (" . implode(',', $fields) . ") VALUES ($placeholders)";
        
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute($values);
    }
    
    /**
     * Create relationship between records
     */
    private function createRelationship($table, $leftId, $rightId)
    {
        $id = create_guid();
        $data = [
            'id' => $id,
            'date_modified' => gmdate('Y-m-d H:i:s'),
            'deleted' => 0
        ];
        
        if ($table === 'deals_contacts') {
            $data['deal_id'] = $leftId;
            $data['contact_id'] = $rightId;
        } elseif ($table === 'deals_accounts') {
            $data['deal_id'] = $leftId;
            $data['account_id'] = $rightId;
        }
        
        $this->insertRecord($table, $data);
    }
    
    /**
     * Save created IDs for cleanup
     */
    private function saveCreatedIds()
    {
        $config = [
            'timestamp' => gmdate('Y-m-d H:i:s'),
            'prefix' => $this->testPrefix,
            'created_ids' => $this->createdIds
        ];
        
        $configFile = dirname(__FILE__) . '/data/quick_seed_config.json';
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
    }
    
    /**
     * Cleanup seeded data
     */
    public function cleanup()
    {
        echo "Cleaning up seeded test data...\n";
        
        $configFile = dirname(__FILE__) . '/data/quick_seed_config.json';
        
        if (!file_exists($configFile)) {
            echo "No configuration file found. Cleaning by prefix...\n";
            $this->cleanupByPrefix();
            return;
        }
        
        $config = json_decode(file_get_contents($configFile), true);
        $createdIds = $config['created_ids'];
        
        // Delete in reverse order to handle dependencies
        $modules = ['deals', 'contacts', 'accounts'];
        
        foreach ($modules as $module) {
            if (isset($createdIds[$module])) {
                $count = count($createdIds[$module]);
                $placeholders = str_repeat('?,', $count - 1) . '?';
                
                $sql = "UPDATE $module SET deleted = 1 WHERE id IN ($placeholders)";
                $stmt = $this->db->getConnection()->prepare($sql);
                $stmt->execute($createdIds[$module]);
                
                echo "Deleted $count $module records\n";
            }
        }
        
        // Clean up relationships
        foreach (['deals_contacts', 'deals_accounts'] as $table) {
            $this->db->query("DELETE FROM $table WHERE deal_id IN (
                SELECT id FROM deals WHERE deleted = 1 AND name LIKE '{$this->testPrefix}%'
            )");
        }
        
        // Remove config file
        unlink($configFile);
        
        echo "Cleanup completed!\n";
    }
    
    /**
     * Cleanup by prefix (fallback method)
     */
    private function cleanupByPrefix()
    {
        $modules = ['deals', 'contacts', 'accounts'];
        
        foreach ($modules as $module) {
            $sql = "UPDATE $module SET deleted = 1 WHERE name LIKE ?";
            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->execute([$this->testPrefix . '%']);
            
            $count = $stmt->rowCount();
            echo "Deleted $count $module records by prefix\n";
        }
    }
}

// CLI execution
if (php_sapi_name() === 'cli') {
    $seeder = new QuickSeeder();
    
    $action = isset($argv[1]) ? $argv[1] : 'seed';
    
    switch ($action) {
        case 'seed':
            $seeder->seedEssentialData();
            break;
            
        case 'cleanup':
            $seeder->cleanup();
            break;
            
        default:
            echo "Usage: php seed_database.php [seed|cleanup]\n";
            echo "  seed    - Create test data\n";
            echo "  cleanup - Remove test data\n";
    }
}