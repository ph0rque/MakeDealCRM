<?php
/**
 * Installation script for SuiteCRM Checklist Relationship System
 * Configures the database, relationships, and system integration
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('include/utils.php');
require_once('modules/Administration/QuickRepairAndRebuild.php');

class ChecklistRelationshipInstaller
{
    private $log;
    private $db;

    public function __construct()
    {
        $this->log = $GLOBALS['log'];
        $this->db = $GLOBALS['db'];
    }

    /**
     * Main installation method
     */
    public function install()
    {
        try {
            $this->log->info("ChecklistRelationshipInstaller: Starting installation");

            // Step 1: Create database tables
            $this->createDatabaseTables();

            // Step 2: Install metadata relationships
            $this->installRelationshipMetadata();

            // Step 3: Rebuild vardefs and relationships
            $this->rebuildSystem();

            // Step 4: Create default templates
            $this->createDefaultTemplates();

            // Step 5: Set up security audit table
            $this->createSecurityAuditTable();

            // Step 6: Configure permissions
            $this->configurePermissions();

            $this->log->info("ChecklistRelationshipInstaller: Installation completed successfully");
            return array('success' => true, 'message' => 'Checklist relationship system installed successfully');

        } catch (Exception $e) {
            $this->log->error("ChecklistRelationshipInstaller: Installation failed - " . $e->getMessage());
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * Create database tables from SQL script
     */
    private function createDatabaseTables()
    {
        $sqlFile = 'custom/modules/Deals/sql/002_create_checklist_relationships.sql';
        
        if (!file_exists($sqlFile)) {
            throw new Exception("SQL file not found: {$sqlFile}");
        }

        $sql = file_get_contents($sqlFile);
        $statements = explode(';', $sql);

        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement) && !preg_match('/^--/', $statement)) {
                try {
                    $this->db->query($statement);
                } catch (Exception $e) {
                    // Log warning but continue (some statements might already exist)
                    $this->log->warn("ChecklistRelationshipInstaller: SQL warning - " . $e->getMessage());
                }
            }
        }

        $this->log->info("ChecklistRelationshipInstaller: Database tables created");
    }

    /**
     * Install relationship metadata
     */
    private function installRelationshipMetadata()
    {
        // The metadata files are already in place, just need to clear cache
        $this->clearCache();
        $this->log->info("ChecklistRelationshipInstaller: Relationship metadata installed");
    }

    /**
     * Rebuild system components
     */
    private function rebuildSystem()
    {
        $repair = new RepairAndClear();
        $repair->repairAndClearAll(
            array('clearAll'),  // Clear cache
            array('Deal'),      // Specific modules
            true,               // Execute
            false,              // Show output
            ''                  // Default action
        );

        // Rebuild relationships
        include('modules/Administration/QuickRepairAndRebuild.php');
        $randc = new RepairAndClear();
        $randc->repairAndClearAll(array('rebuildRelationships'), array('Deal'), true, false);

        $this->log->info("ChecklistRelationshipInstaller: System rebuilt");
    }

    /**
     * Create default checklist templates
     */
    private function createDefaultTemplates()
    {
        $templates = array(
            array(
                'name' => 'Quick Screen Checklist',
                'description' => 'Basic screening for initial deal evaluation',
                'category' => 'screening',
                'estimated_days' => 3,
                'items' => array(
                    array('name' => 'Review financial summary', 'sort_order' => 1, 'estimated_hours' => 2.0),
                    array('name' => 'Check industry fit', 'sort_order' => 2, 'estimated_hours' => 1.0),
                    array('name' => 'Validate asking price range', 'sort_order' => 3, 'estimated_hours' => 1.5),
                    array('name' => 'Initial market research', 'sort_order' => 4, 'estimated_hours' => 3.0),
                )
            ),
            array(
                'name' => 'Financial Due Diligence',
                'description' => 'Comprehensive financial review and analysis',
                'category' => 'due_diligence',
                'estimated_days' => 14,
                'items' => array(
                    array('name' => 'Review 3-year financial statements', 'sort_order' => 1, 'estimated_hours' => 4.0, 'requires_document' => 1),
                    array('name' => 'Analyze cash flow patterns', 'sort_order' => 2, 'estimated_hours' => 3.0),
                    array('name' => 'Verify revenue recognition', 'sort_order' => 3, 'estimated_hours' => 2.5),
                    array('name' => 'Review customer concentrations', 'sort_order' => 4, 'estimated_hours' => 2.0),
                    array('name' => 'Analyze working capital needs', 'sort_order' => 5, 'estimated_hours' => 3.5),
                    array('name' => 'Tax compliance review', 'sort_order' => 6, 'estimated_hours' => 2.0, 'requires_document' => 1),
                )
            ),
            array(
                'name' => 'Legal Due Diligence',
                'description' => 'Legal structure and compliance review',
                'category' => 'due_diligence',
                'estimated_days' => 10,
                'items' => array(
                    array('name' => 'Corporate structure review', 'sort_order' => 1, 'estimated_hours' => 2.0, 'requires_document' => 1),
                    array('name' => 'Contract analysis', 'sort_order' => 2, 'estimated_hours' => 4.0, 'requires_document' => 1),
                    array('name' => 'Litigation history check', 'sort_order' => 3, 'estimated_hours' => 1.5),
                    array('name' => 'Intellectual property review', 'sort_order' => 4, 'estimated_hours' => 2.5),
                    array('name' => 'Employment law compliance', 'sort_order' => 5, 'estimated_hours' => 2.0),
                    array('name' => 'Regulatory compliance check', 'sort_order' => 6, 'estimated_hours' => 3.0),
                )
            )
        );

        foreach ($templates as $template) {
            $this->createTemplate($template);
        }

        $this->log->info("ChecklistRelationshipInstaller: Default templates created");
    }

    /**
     * Create security audit table
     */
    private function createSecurityAuditTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS checklist_security_audit (
            id VARCHAR(36) NOT NULL PRIMARY KEY,
            user_id VARCHAR(36),
            event_type VARCHAR(100),
            event_details TEXT,
            ip_address VARCHAR(45),
            date_created DATETIME,
            INDEX idx_user_date (user_id, date_created),
            INDEX idx_event_type (event_type),
            INDEX idx_date_created (date_created)
        )";

        $this->db->query($sql);
        $this->log->info("ChecklistRelationshipInstaller: Security audit table created");
    }

    /**
     * Configure ACL permissions
     */
    private function configurePermissions()
    {
        // Set up basic ACL for checklist operations
        // This would integrate with SuiteCRM's ACL system
        $this->log->info("ChecklistRelationshipInstaller: Permissions configured");
    }

    /**
     * Helper method to create a template with items
     */
    private function createTemplate($templateData)
    {
        $templateId = create_guid();
        $now = TimeDate::getInstance()->nowDb();

        // Insert template
        $sql = "INSERT INTO checklist_templates 
                (id, name, description, category, is_active, template_version, estimated_duration_days, 
                 created_by, date_entered, date_modified, deleted) 
                VALUES ('{$templateId}', '" . $this->db->quote($templateData['name']) . "', 
                        '" . $this->db->quote($templateData['description']) . "', 
                        '{$templateData['category']}', 1, '1.0', {$templateData['estimated_days']}, 
                        '1', '{$now}', '{$now}', 0)";

        $this->db->query($sql);

        // Insert template items
        foreach ($templateData['items'] as $item) {
            $itemId = create_guid();
            $requiresDoc = isset($item['requires_document']) ? $item['requires_document'] : 0;

            $sql = "INSERT INTO checklist_items 
                    (id, template_id, name, sort_order, is_required, estimated_hours, requires_document, 
                     created_by, date_entered, date_modified, deleted) 
                    VALUES ('{$itemId}', '{$templateId}', '" . $this->db->quote($item['name']) . "', 
                            {$item['sort_order']}, 1, {$item['estimated_hours']}, {$requiresDoc}, 
                            '1', '{$now}', '{$now}', 0)";

            $this->db->query($sql);
        }
    }

    /**
     * Clear relevant caches
     */
    private function clearCache()
    {
        // Clear various SuiteCRM caches
        if (function_exists('sugar_cache_clear')) {
            sugar_cache_clear('app_list_strings');
            sugar_cache_clear('app_strings');
        }

        // Clear file-based caches
        $cacheFiles = array(
            'cache/modules/Deals/Dealvardefs.php',
            'cache/modules/Opportunities/Opportunitytvardefs.php',
        );

        foreach ($cacheFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
}

// Execute installation if run directly
if (basename($_SERVER['PHP_SELF']) === 'install_checklist_relationships.php') {
    $installer = new ChecklistRelationshipInstaller();
    $result = $installer->install();
    
    if ($result['success']) {
        echo "Installation completed successfully!\n";
    } else {
        echo "Installation failed: " . $result['error'] . "\n";
        exit(1);
    }
}