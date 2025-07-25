<?php
/**
 * Task 19 Comprehensive Testing Script
 * Tests all migrated features after completion
 */

// Initialize SuiteCRM environment
if (!defined('sugarEntry')) {
    define('sugarEntry', true);
}

require_once '../../../../SuiteCRM/include/entryPoint.php';

class Task19ComprehensiveTest {
    
    private $results = [];
    private $errors = [];
    private $warnings = [];
    
    public function __construct() {
        global $current_user;
        if (!$current_user || empty($current_user->id)) {
            $current_user = BeanFactory::getBean('Users', '1');
        }
    }
    
    /**
     * Run all tests
     */
    public function runAllTests() {
        echo "<h1>Task 19 - Comprehensive Feature Testing</h1>";
        echo "<p>Testing all features after migration completion...</p>";
        
        // Core functionality tests
        $this->testPipelineKanbanView();
        $this->testDragAndDropFunctionality();
        $this->testCSSJSAssetLoading();
        $this->testAjaxCalls();
        $this->testModulePermissions();
        $this->testDatabaseConnectivity();
        $this->testCustomFieldsIntegrity();
        $this->testChecklistSystem();
        $this->testStakeholderTracking();
        $this->testFinancialDashboard();
        $this->testExportFunctionality();
        $this->testEmailIntegration();
        $this->testStateManagement();
        $this->testTemplateSystem();
        $this->testPerformanceOptimizations();
        
        // Display results
        $this->displayResults();
    }
    
    /**
     * Test Pipeline Kanban View
     */
    private function testPipelineKanbanView() {
        echo "<h2>Testing Pipeline Kanban View...</h2>";
        
        try {
            // Check if pipeline view file exists
            $pipelineViewPath = '../../../../custom/modules/Deals/views/view.pipeline.php';
            if (file_exists($pipelineViewPath)) {
                $this->results[] = "✅ Pipeline view file exists";
                
                // Check if the file is readable
                if (is_readable($pipelineViewPath)) {
                    $this->results[] = "✅ Pipeline view file is readable";
                    
                    // Check file content
                    $content = file_get_contents($pipelineViewPath);
                    if (strpos($content, 'ViewPipeline') !== false) {
                        $this->results[] = "✅ Pipeline view class defined correctly";
                    } else {
                        $this->errors[] = "❌ Pipeline view class not found in file";
                    }
                } else {
                    $this->errors[] = "❌ Pipeline view file is not readable";
                }
            } else {
                $this->errors[] = "❌ Pipeline view file not found";
            }
            
            // Check if pipeline template exists
            $templatePath = '../../../../custom/modules/Deals/tpls/pipeline.tpl';
            if (file_exists($templatePath)) {
                $this->results[] = "✅ Pipeline template file exists";
            } else {
                $this->errors[] = "❌ Pipeline template file not found";
            }
            
        } catch (Exception $e) {
            $this->errors[] = "❌ Pipeline view test failed: " . $e->getMessage();
        }
    }
    
    /**
     * Test Drag and Drop Functionality
     */
    private function testDragAndDropFunctionality() {
        echo "<h2>Testing Drag and Drop Functionality...</h2>";
        
        try {
            // Check if drag and drop JS exists
            $dragDropJS = '../../../../custom/modules/Deals/js/pipeline.js';
            if (file_exists($dragDropJS)) {
                $this->results[] = "✅ Pipeline JavaScript file exists";
                
                $content = file_get_contents($dragDropJS);
                if (strpos($content, 'dragstart') !== false || strpos($content, 'Sortable') !== false) {
                    $this->results[] = "✅ Drag and drop handlers found in JavaScript";
                } else {
                    $this->warnings[] = "⚠️ Drag and drop handlers might be missing";
                }
            } else {
                $this->errors[] = "❌ Pipeline JavaScript file not found";
            }
            
            // Check for touch support
            $touchJS = '../../../../custom/modules/Deals/test_touch_dragdrop.html';
            if (file_exists($touchJS)) {
                $this->results[] = "✅ Touch drag-drop test file exists";
            }
            
        } catch (Exception $e) {
            $this->errors[] = "❌ Drag and drop test failed: " . $e->getMessage();
        }
    }
    
    /**
     * Test CSS/JS Asset Loading
     */
    private function testCSSJSAssetLoading() {
        echo "<h2>Testing CSS/JS Asset Loading...</h2>";
        
        $cssFiles = [
            'pipeline.css',
            'progress-indicators.css',
            'stakeholder-badges.css',
            'financial-dashboard.css',
            'wip-limits.css',
            'theme-integration.css'
        ];
        
        $jsFiles = [
            'pipeline.js',
            'state-manager.js',
            'progress-indicators.js',
            'stakeholder-integration.js',
            'financial-dashboard-init.js',
            'asset-loader.js'
        ];
        
        // Check CSS files
        foreach ($cssFiles as $css) {
            $path = "../../../../custom/modules/Deals/css/$css";
            if (file_exists($path)) {
                $this->results[] = "✅ CSS file exists: $css";
            } else {
                $this->errors[] = "❌ CSS file missing: $css";
            }
        }
        
        // Check JS files
        foreach ($jsFiles as $js) {
            $path = "../../../../custom/modules/Deals/js/$js";
            if (file_exists($path)) {
                $this->results[] = "✅ JS file exists: $js";
            } else {
                $this->errors[] = "❌ JS file missing: $js";
            }
        }
    }
    
    /**
     * Test AJAX Calls
     */
    private function testAjaxCalls() {
        echo "<h2>Testing AJAX Functionality...</h2>";
        
        try {
            // Check API files
            $apiFiles = [
                'PipelineApi.php',
                'OptimizedPipelineApi.php',
                'StakeholderIntegrationApi.php',
                'TaskGenerationApi.php',
                'TemplateApi.php'
            ];
            
            foreach ($apiFiles as $api) {
                $path = "../../../../custom/modules/Deals/api/$api";
                if (file_exists($path)) {
                    $this->results[] = "✅ API file exists: $api";
                    
                    // Check if file contains proper class definition
                    $content = file_get_contents($path);
                    $className = str_replace('.php', '', $api);
                    if (strpos($content, "class $className") !== false) {
                        $this->results[] = "✅ API class defined: $className";
                    }
                } else {
                    $this->errors[] = "❌ API file missing: $api";
                }
            }
            
        } catch (Exception $e) {
            $this->errors[] = "❌ AJAX test failed: " . $e->getMessage();
        }
    }
    
    /**
     * Test Module Permissions
     */
    private function testModulePermissions() {
        echo "<h2>Testing Module Permissions...</h2>";
        
        try {
            global $current_user;
            
            // Check if user can access Deals module
            if ($current_user->isAdmin()) {
                $this->results[] = "✅ Current user is admin";
            }
            
            // Check ACL for Deals module
            require_once '../../../../SuiteCRM/modules/ACLActions/ACLAction.php';
            
            $modules = ['Deals', 'Pipelines', 'mdeal_Deals'];
            
            foreach ($modules as $module) {
                $access = ACLAction::getUserAccessLevel($current_user->id, $module, 'access');
                if ($access >= 0) {
                    $this->results[] = "✅ Module access granted: $module";
                } else {
                    $this->warnings[] = "⚠️ Module access restricted: $module";
                }
            }
            
        } catch (Exception $e) {
            $this->errors[] = "❌ Permission test failed: " . $e->getMessage();
        }
    }
    
    /**
     * Test Database Connectivity
     */
    private function testDatabaseConnectivity() {
        echo "<h2>Testing Database Connectivity...</h2>";
        
        try {
            global $db;
            
            // Test connection
            if ($db && $db->checkConnection()) {
                $this->results[] = "✅ Database connection active";
            } else {
                $this->errors[] = "❌ Database connection failed";
                return;
            }
            
            // Check custom tables
            $customTables = [
                'pipeline_stages',
                'deals_pipeline_tracking',
                'deals_checklist_templates',
                'deals_checklist_items',
                'deals_state_management',
                'deals_pipeline_stage_history'
            ];
            
            foreach ($customTables as $table) {
                $query = "SHOW TABLES LIKE '$table'";
                $result = $db->query($query);
                if ($result && $db->fetchByAssoc($result)) {
                    $this->results[] = "✅ Table exists: $table";
                } else {
                    $this->errors[] = "❌ Table missing: $table";
                }
            }
            
        } catch (Exception $e) {
            $this->errors[] = "❌ Database test failed: " . $e->getMessage();
        }
    }
    
    /**
     * Test Custom Fields Integrity
     */
    private function testCustomFieldsIntegrity() {
        echo "<h2>Testing Custom Fields...</h2>";
        
        try {
            // Check Deals vardefs
            $vardefPath = '../../../../custom/modules/Deals/vardefs.php';
            if (file_exists($vardefPath)) {
                $this->results[] = "✅ Deals vardefs file exists";
                
                // Check for pipeline fields
                require_once $vardefPath;
                global $dictionary;
                
                if (isset($dictionary['Deal'])) {
                    $fields = ['pipeline_stage', 'expected_close_value', 'focus_priority'];
                    foreach ($fields as $field) {
                        if (isset($dictionary['Deal']['fields'][$field])) {
                            $this->results[] = "✅ Field defined: $field";
                        } else {
                            $this->warnings[] = "⚠️ Field might be missing: $field";
                        }
                    }
                }
            } else {
                $this->errors[] = "❌ Deals vardefs file not found";
            }
            
        } catch (Exception $e) {
            $this->errors[] = "❌ Custom fields test failed: " . $e->getMessage();
        }
    }
    
    /**
     * Test Checklist System
     */
    private function testChecklistSystem() {
        echo "<h2>Testing Checklist System...</h2>";
        
        try {
            // Check checklist files
            $checklistFiles = [
                'ChecklistLogicHook.php',
                'ChecklistSecurity.php',
                'ChecklistPermissionManager.php'
            ];
            
            foreach ($checklistFiles as $file) {
                $path = "../../../../custom/modules/Deals/$file";
                if (file_exists($path)) {
                    $this->results[] = "✅ Checklist file exists: $file";
                } else {
                    $this->errors[] = "❌ Checklist file missing: $file";
                }
            }
            
            // Check metadata
            $metadataPath = '../../../../custom/metadata/deals_checklist_templatesMetaData.php';
            if (file_exists($metadataPath)) {
                $this->results[] = "✅ Checklist metadata exists";
            } else {
                $this->errors[] = "❌ Checklist metadata missing";
            }
            
        } catch (Exception $e) {
            $this->errors[] = "❌ Checklist test failed: " . $e->getMessage();
        }
    }
    
    /**
     * Test Stakeholder Tracking
     */
    private function testStakeholderTracking() {
        echo "<h2>Testing Stakeholder Tracking...</h2>";
        
        try {
            // Check stakeholder files
            $stakeholderPath = '../../../../custom/modules/Contacts/StakeholderRelationshipService.php';
            if (file_exists($stakeholderPath)) {
                $this->results[] = "✅ Stakeholder service exists";
            } else {
                $this->errors[] = "❌ Stakeholder service missing";
            }
            
            // Check stakeholder CSS
            $cssPath = '../../../../custom/modules/Deals/css/stakeholder-badges.css';
            if (file_exists($cssPath)) {
                $this->results[] = "✅ Stakeholder CSS exists";
            } else {
                $this->errors[] = "❌ Stakeholder CSS missing";
            }
            
        } catch (Exception $e) {
            $this->errors[] = "❌ Stakeholder test failed: " . $e->getMessage();
        }
    }
    
    /**
     * Test Financial Dashboard
     */
    private function testFinancialDashboard() {
        echo "<h2>Testing Financial Dashboard...</h2>";
        
        try {
            // Check financial dashboard files
            $dashboardView = '../../../../custom/modules/Deals/views/view.financialdashboard.php';
            if (file_exists($dashboardView)) {
                $this->results[] = "✅ Financial dashboard view exists";
            } else {
                $this->errors[] = "❌ Financial dashboard view missing";
            }
            
            // Check financial JS files
            $financialJS = [
                'financial-dashboard-init.js',
                'financial-dashboard-framework.js',
                'financial-calculation-engine.js'
            ];
            
            foreach ($financialJS as $js) {
                $path = "../../../../custom/modules/Deals/js/$js";
                if (file_exists($path)) {
                    $this->results[] = "✅ Financial JS exists: $js";
                } else {
                    $this->errors[] = "❌ Financial JS missing: $js";
                }
            }
            
        } catch (Exception $e) {
            $this->errors[] = "❌ Financial dashboard test failed: " . $e->getMessage();
        }
    }
    
    /**
     * Test Export Functionality
     */
    private function testExportFunctionality() {
        echo "<h2>Testing Export Functionality...</h2>";
        
        try {
            // Check export controller
            $exportController = '../../../../custom/modules/Deals/controllers/ExportController.php';
            if (file_exists($exportController)) {
                $this->results[] = "✅ Export controller exists";
            } else {
                $this->errors[] = "❌ Export controller missing";
            }
            
            // Check export service
            $exportService = '../../../../custom/modules/Deals/services/ExportService.php';
            if (file_exists($exportService)) {
                $this->results[] = "✅ Export service exists";
            } else {
                $this->errors[] = "❌ Export service missing";
            }
            
        } catch (Exception $e) {
            $this->errors[] = "❌ Export test failed: " . $e->getMessage();
        }
    }
    
    /**
     * Test Email Integration
     */
    private function testEmailIntegration() {
        echo "<h2>Testing Email Integration...</h2>";
        
        try {
            // Check email files
            $emailFiles = [
                'EmailProcessor.php',
                'EmailThreadTracker.php',
                'DealsEmailLogicHook.php'
            ];
            
            foreach ($emailFiles as $file) {
                $path = "../../../../custom/modules/Deals/$file";
                if (file_exists($path)) {
                    $this->results[] = "✅ Email file exists: $file";
                } else {
                    $this->errors[] = "❌ Email file missing: $file";
                }
            }
            
        } catch (Exception $e) {
            $this->errors[] = "❌ Email integration test failed: " . $e->getMessage();
        }
    }
    
    /**
     * Test State Management
     */
    private function testStateManagement() {
        echo "<h2>Testing State Management...</h2>";
        
        try {
            // Check state manager JS
            $stateManagerJS = '../../../../custom/modules/Deals/js/state-manager.js';
            if (file_exists($stateManagerJS)) {
                $this->results[] = "✅ State manager JS exists";
                
                $content = file_get_contents($stateManagerJS);
                if (strpos($content, 'StateManager') !== false) {
                    $this->results[] = "✅ StateManager class found";
                }
            } else {
                $this->errors[] = "❌ State manager JS missing";
            }
            
            // Check state sync API
            $stateSyncAPI = '../../../../custom/modules/Deals/api/StateSync.php';
            if (file_exists($stateSyncAPI)) {
                $this->results[] = "✅ State sync API exists";
            } else {
                $this->errors[] = "❌ State sync API missing";
            }
            
        } catch (Exception $e) {
            $this->errors[] = "❌ State management test failed: " . $e->getMessage();
        }
    }
    
    /**
     * Test Template System
     */
    private function testTemplateSystem() {
        echo "<h2>Testing Template System...</h2>";
        
        try {
            // Check template files
            $templateFiles = [
                'api/TemplateApi.php',
                'api/TemplateParser.php',
                'api/TemplateValidator.php',
                'api/TemplateVersioningApi.php'
            ];
            
            foreach ($templateFiles as $file) {
                $path = "../../../../custom/modules/Deals/$file";
                if (file_exists($path)) {
                    $this->results[] = "✅ Template file exists: $file";
                } else {
                    $this->errors[] = "❌ Template file missing: $file";
                }
            }
            
        } catch (Exception $e) {
            $this->errors[] = "❌ Template system test failed: " . $e->getMessage();
        }
    }
    
    /**
     * Test Performance Optimizations
     */
    private function testPerformanceOptimizations() {
        echo "<h2>Testing Performance Optimizations...</h2>";
        
        try {
            // Check performance optimizer
            $perfOptimizer = '../../../../custom/modules/Pipelines/optimization/PerformanceOptimizer.php';
            if (file_exists($perfOptimizer)) {
                $this->results[] = "✅ Performance optimizer exists";
            } else {
                $this->errors[] = "❌ Performance optimizer missing";
            }
            
            // Check optimized API
            $optimizedAPI = '../../../../custom/modules/Deals/api/OptimizedPipelineApi.php';
            if (file_exists($optimizedAPI)) {
                $this->results[] = "✅ Optimized pipeline API exists";
            } else {
                $this->errors[] = "❌ Optimized pipeline API missing";
            }
            
            // Check service worker
            $serviceWorker = '../../../../custom/modules/Deals/js/sw-pipeline.js';
            if (file_exists($serviceWorker)) {
                $this->results[] = "✅ Service worker exists";
            } else {
                $this->warnings[] = "⚠️ Service worker missing (optional)";
            }
            
        } catch (Exception $e) {
            $this->errors[] = "❌ Performance test failed: " . $e->getMessage();
        }
    }
    
    /**
     * Display test results
     */
    private function displayResults() {
        echo "<hr>";
        echo "<h2>Test Results Summary</h2>";
        
        $totalTests = count($this->results) + count($this->errors) + count($this->warnings);
        $passedTests = count($this->results);
        $failedTests = count($this->errors);
        $warningTests = count($this->warnings);
        
        echo "<p><strong>Total Tests:</strong> $totalTests</p>";
        echo "<p><strong>✅ Passed:</strong> $passedTests</p>";
        echo "<p><strong>❌ Failed:</strong> $failedTests</p>";
        echo "<p><strong>⚠️ Warnings:</strong> $warningTests</p>";
        
        if ($passedTests > 0) {
            echo "<h3>✅ Passed Tests</h3>";
            echo "<ul>";
            foreach ($this->results as $result) {
                echo "<li>$result</li>";
            }
            echo "</ul>";
        }
        
        if ($failedTests > 0) {
            echo "<h3>❌ Failed Tests</h3>";
            echo "<ul>";
            foreach ($this->errors as $error) {
                echo "<li style='color: red;'>$error</li>";
            }
            echo "</ul>";
        }
        
        if ($warningTests > 0) {
            echo "<h3>⚠️ Warnings</h3>";
            echo "<ul>";
            foreach ($this->warnings as $warning) {
                echo "<li style='color: orange;'>$warning</li>";
            }
            echo "</ul>";
        }
        
        // Recommendations
        echo "<hr>";
        echo "<h2>Recommendations</h2>";
        
        if ($failedTests > 0) {
            echo "<p><strong>Critical Issues Found:</strong></p>";
            echo "<ol>";
            
            if (in_array("❌ Pipeline view file not found", $this->errors)) {
                echo "<li>Pipeline view is missing. Run repair scripts or check module deployment.</li>";
            }
            
            if (strpos(implode('', $this->errors), 'Table missing') !== false) {
                echo "<li>Database tables are missing. Run migration scripts in custom/database/migrations/</li>";
            }
            
            if (strpos(implode('', $this->errors), 'CSS file missing') !== false || 
                strpos(implode('', $this->errors), 'JS file missing') !== false) {
                echo "<li>Asset files are missing. Check deployment and file permissions.</li>";
            }
            
            echo "</ol>";
        } else {
            echo "<p style='color: green;'><strong>All critical tests passed! The system appears to be functioning correctly.</strong></p>";
        }
        
        // Console check recommendation
        echo "<p><strong>Next Steps:</strong></p>";
        echo "<ul>";
        echo "<li>Open browser developer console and check for JavaScript errors</li>";
        echo "<li>Test the pipeline view by navigating to Deals > Pipeline</li>";
        echo "<li>Test drag-and-drop by moving deals between stages</li>";
        echo "<li>Verify all custom buttons and actions work</li>";
        echo "<li>Check that all AJAX calls complete successfully</li>";
        echo "</ul>";
    }
}

// Run the tests
$tester = new Task19ComprehensiveTest();
$tester->runAllTests();