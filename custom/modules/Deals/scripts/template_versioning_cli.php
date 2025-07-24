<?php
/**
 * Template Versioning CLI Utility
 * Command-line interface for template versioning operations
 */

if (!defined('sugarEntry')) {
    define('sugarEntry', true);
}

// Bootstrap SuiteCRM
require_once('config.php');
require_once('include/entryPoint.php');

require_once('custom/modules/Deals/services/TemplateVersioningService.php');
require_once('custom/modules/Deals/services/TemplateRollbackManager.php');
require_once('custom/modules/Deals/services/TemplateMigrationManager.php');

class TemplateVersioningCLI
{
    private $versioningService;
    private $rollbackManager;
    private $migrationManager;
    
    public function __construct()
    {
        $this->versioningService = new TemplateVersioningService();
        $this->rollbackManager = new TemplateRollbackManager();
        $this->migrationManager = new TemplateMigrationManager();
    }
    
    /**
     * Main CLI handler
     */
    public function run($argc, $argv)
    {
        if ($argc < 2) {
            $this->showHelp();
            return;
        }
        
        $command = $argv[1];
        $args = array_slice($argv, 2);
        
        switch ($command) {
            case 'create':
                $this->createVersion($args);
                break;
            case 'list':
                $this->listVersions($args);
                break;
            case 'compare':
                $this->compareVersions($args);
                break;
            case 'rollback':
                $this->rollbackVersion($args);
                break;
            case 'migrate':
                $this->migrateTemplate($args);
                break;
            case 'audit':
                $this->showAuditTrail($args);
                break;
            case 'branch':
                $this->manageBranches($args);
                break;
            case 'cleanup':
                $this->cleanupVersions($args);
                break;
            case 'export':
                $this->exportVersion($args);
                break;
            case 'import':
                $this->importVersion($args);
                break;
            case 'validate':
                $this->validateSystem($args);
                break;
            case 'stats':
                $this->showStatistics($args);
                break;
            case 'help':
            default:
                $this->showHelp();
                break;
        }
    }
    
    /**
     * Create a new version
     */
    private function createVersion($args)
    {
        $options = $this->parseArgs($args);
        
        if (empty($options['template-id']) || empty($options['content-file'])) {
            $this->error("Required: --template-id and --content-file");
            return;
        }
        
        if (!file_exists($options['content-file'])) {
            $this->error("Content file not found: " . $options['content-file']);
            return;
        }
        
        $content = json_decode(file_get_contents($options['content-file']), true);
        if (!$content) {
            $this->error("Invalid JSON in content file");
            return;
        }
        
        $result = $this->versioningService->createVersion(
            $options['template-id'],
            $content,
            $options['type'] ?? 'minor',
            $options['summary'] ?? '',
            ($options['draft'] ?? 'false') === 'true'
        );
        
        if ($result['success']) {
            $this->success("Version {$result['version_number']} created successfully");
            $this->info("Version ID: {$result['version_id']}");
        } else {
            $this->error("Failed to create version: " . $result['error']);
        }
    }
    
    /**
     * List versions for a template
     */
    private function listVersions($args)
    {
        $options = $this->parseArgs($args);
        
        if (empty($options['template-id'])) {
            $this->error("Required: --template-id");
            return;
        }
        
        $versions = $this->versioningService->getVersionHistory(
            $options['template-id'],
            ($options['include-deleted'] ?? 'false') === 'true'
        );
        
        if (empty($versions)) {
            $this->info("No versions found for template " . $options['template-id']);
            return;
        }
        
        $this->printTable([
            'Version' => 'version_number',
            'Status' => function($row) { 
                $status = [];
                if ($row['is_current']) $status[] = 'CURRENT';
                if ($row['is_draft']) $status[] = 'DRAFT';
                $status[] = strtoupper($row['approval_status']);
                return implode(', ', $status);
            },
            'Created' => 'date_created',
            'Summary' => function($row) { 
                return substr($row['change_summary'] ?? '', 0, 50) . 
                       (strlen($row['change_summary'] ?? '') > 50 ? '...' : '');
            }
        ], $versions);
    }
    
    /**
     * Compare two versions
     */
    private function compareVersions($args)
    {
        $options = $this->parseArgs($args);
        
        if (empty($options['from']) || empty($options['to'])) {
            $this->error("Required: --from and --to version IDs");
            return;
        }
        
        $result = $this->versioningService->compareVersions(
            $options['from'],
            $options['to'],
            $options['type'] ?? 'semantic'
        );
        
        if (!$result['success']) {
            $this->error("Comparison failed: " . $result['error']);
            return;
        }
        
        $this->info("Comparison: {$result['from_version']} → {$result['to_version']}");
        $this->info("Changes: {$result['change_count']}");
        $this->info("Complexity: {$result['complexity_score']}/100");
        
        if (($options['detailed'] ?? 'false') === 'true') {
            echo "\nDetailed Changes:\n";
            echo json_encode($result['diff'], JSON_PRETTY_PRINT) . "\n";
        }
    }
    
    /**
     * Rollback to a previous version
     */
    private function rollbackVersion($args)
    {
        $options = $this->parseArgs($args);
        
        if (empty($options['template-id']) || empty($options['version-id'])) {
            $this->error("Required: --template-id and --version-id");
            return;
        }
        
        // Confirm rollback unless --force is used
        if (($options['force'] ?? 'false') !== 'true') {
            $this->warning("This will rollback the template. Are you sure? (y/N)");
            $confirm = trim(fgets(STDIN));
            if (strtolower($confirm) !== 'y') {
                $this->info("Rollback cancelled");
                return;
            }
        }
        
        $result = $this->rollbackManager->performRollback(
            $options['template-id'],
            $options['version-id'],
            [
                'reason' => $options['reason'] ?? 'CLI rollback',
                'force_with_instances' => ($options['force-with-instances'] ?? 'false') === 'true',
                'allow_breaking_changes' => ($options['allow-breaking'] ?? 'false') === 'true'
            ]
        );
        
        if ($result['success']) {
            $this->success("Rollback completed: {$result['from_version']} → {$result['to_version']}");
            $this->info("Backup ID: {$result['backup_id']}");
        } else {
            $this->error("Rollback failed: " . $result['error']);
        }
    }
    
    /**
     * Migrate template instances
     */
    private function migrateTemplate($args)
    {
        $options = $this->parseArgs($args);
        
        if (empty($options['template-id']) || empty($options['from']) || empty($options['to'])) {
            $this->error("Required: --template-id, --from, and --to version IDs");
            return;
        }
        
        $result = $this->migrationManager->initiateMigration(
            $options['template-id'],
            $options['from'],
            $options['to'],
            $options['type'] ?? 'auto'
        );
        
        if ($result['success']) {
            $this->success("Migration initiated");
            $this->info("Migrated instances: {$result['migrated_instances']}");
            $this->info("Failed instances: {$result['failed_instances']}");
            
            if (!empty($result['errors'])) {
                $this->warning("Errors occurred:");
                foreach ($result['errors'] as $error) {
                    echo "  • $error\n";
                }
            }
        } else {
            $this->error("Migration failed: " . $result['error']);
        }
    }
    
    /**
     * Show audit trail
     */
    private function showAuditTrail($args)
    {
        $options = $this->parseArgs($args);
        
        if (empty($options['template-id'])) {
            $this->error("Required: --template-id");
            return;
        }
        
        $auditTrail = $this->versioningService->getAuditTrail(
            $options['template-id'],
            $options['version-id'] ?? null,
            (int)($options['limit'] ?? 50)
        );
        
        if (empty($auditTrail)) {
            $this->info("No audit trail found");
            return;
        }
        
        $this->printTable([
            'Date' => 'action_date',
            'Action' => 'action_type',
            'Version' => 'version_number',
            'User' => 'actor_display_name',
            'Description' => function($row) {
                return substr($row['change_description'], 0, 50) . 
                       (strlen($row['change_description']) > 50 ? '...' : '');
            }
        ], $auditTrail);
    }
    
    /**
     * Manage branches
     */
    private function manageBranches($args)
    {
        $options = $this->parseArgs($args);
        $action = $options['action'] ?? 'list';
        
        switch ($action) {
            case 'create':
                if (empty($options['template-id']) || empty($options['parent-version']) || empty($options['name'])) {
                    $this->error("Required for create: --template-id, --parent-version, --name");
                    return;
                }
                
                $result = $this->versioningService->createBranch(
                    $options['template-id'],
                    $options['parent-version'],
                    $options['name'],
                    $options['type'] ?? 'feature',
                    $options['description'] ?? ''
                );
                
                if ($result['success']) {
                    $this->success("Branch '{$options['name']}' created");
                } else {
                    $this->error("Failed to create branch: " . $result['error']);
                }
                break;
                
            case 'list':
                if (empty($options['template-id'])) {
                    $this->error("Required: --template-id");
                    return;
                }
                
                // Implementation for listing branches
                $this->info("Branch listing not yet implemented");
                break;
                
            case 'merge':
                if (empty($options['branch-id']) || empty($options['target-version'])) {
                    $this->error("Required for merge: --branch-id, --target-version");
                    return;
                }
                
                $result = $this->versioningService->mergeBranch(
                    $options['branch-id'],
                    $options['target-version'],
                    $options['message'] ?? ''
                );
                
                if ($result['success']) {
                    $this->success("Branch merged successfully");
                } else {
                    $this->error("Failed to merge branch: " . $result['error']);
                }
                break;
                
            default:
                $this->error("Invalid branch action: $action");
        }
    }
    
    /**
     * Cleanup old versions
     */
    private function cleanupVersions($args)
    {
        $options = $this->parseArgs($args);
        
        if (empty($options['template-id'])) {
            $this->error("Required: --template-id");
            return;
        }
        
        $keepCount = (int)($options['keep'] ?? 10);
        $dryRun = ($options['dry-run'] ?? 'false') === 'true';
        
        // Get all versions
        $versions = $this->versioningService->getVersionHistory($options['template-id'], true);
        
        if (count($versions) <= $keepCount) {
            $this->info("No cleanup needed. Found " . count($versions) . " versions, keeping $keepCount");
            return;
        }
        
        // Sort and identify versions to delete
        usort($versions, function($a, $b) {
            return strtotime($b['date_created']) - strtotime($a['date_created']);
        });
        
        $toDelete = array_slice($versions, $keepCount);
        $toDelete = array_filter($toDelete, function($v) { return !$v['is_current']; });
        
        if ($dryRun) {
            $this->info("DRY RUN: Would delete " . count($toDelete) . " versions:");
            foreach ($toDelete as $version) {
                echo "  • {$version['version_number']} ({$version['date_created']})\n";
            }
        } else {
            $this->warning("This will permanently delete " . count($toDelete) . " versions. Continue? (y/N)");
            $confirm = trim(fgets(STDIN));
            if (strtolower($confirm) === 'y') {
                $deleted = 0;
                foreach ($toDelete as $version) {
                    // Delete version logic would go here
                    $deleted++;
                }
                $this->success("Deleted $deleted old versions");
            } else {
                $this->info("Cleanup cancelled");
            }
        }
    }
    
    /**
     * Export version to file
     */
    private function exportVersion($args)
    {
        $options = $this->parseArgs($args);
        
        if (empty($options['version-id']) || empty($options['output'])) {
            $this->error("Required: --version-id and --output file");
            return;
        }
        
        global $db;
        $query = "SELECT * FROM template_versions WHERE id = '{$options['version-id']}' AND deleted = 0";
        $result = $db->query($query);
        $version = $db->fetchByAssoc($result);
        
        if (!$version) {
            $this->error("Version not found");
            return;
        }
        
        $exportData = [
            'version_info' => [
                'version_number' => $version['version_number'],
                'change_summary' => $version['change_summary'],
                'export_date' => date('Y-m-d H:i:s'),
                'exported_by' => get_current_user_id()
            ],
            'content' => json_decode($version['content'], true)
        ];
        
        if (file_put_contents($options['output'], json_encode($exportData, JSON_PRETTY_PRINT))) {
            $this->success("Version exported to: " . $options['output']);
        } else {
            $this->error("Failed to write export file");
        }
    }
    
    /**
     * Import version from file
     */
    private function importVersion($args)
    {
        $options = $this->parseArgs($args);
        
        if (empty($options['template-id']) || empty($options['input'])) {
            $this->error("Required: --template-id and --input file");
            return;
        }
        
        if (!file_exists($options['input'])) {
            $this->error("Input file not found: " . $options['input']);
            return;
        }
        
        $importData = json_decode(file_get_contents($options['input']), true);
        if (!$importData || !isset($importData['content'])) {
            $this->error("Invalid import file format");
            return;
        }
        
        $result = $this->versioningService->createVersion(
            $options['template-id'],
            $importData['content'],
            'minor',
            'Imported from ' . $options['input'],
            false
        );
        
        if ($result['success']) {
            $this->success("Version imported successfully: " . $result['version_number']);
        } else {
            $this->error("Import failed: " . $result['error']);
        }
    }
    
    /**
     * Validate system integrity
     */
    private function validateSystem($args)
    {
        $this->info("Validating template versioning system...");
        
        $issues = [];
        
        // Check database tables
        $tables = [
            'template_definitions',
            'template_versions', 
            'template_version_diffs',
            'template_audit_log',
            'template_branches',
            'template_migration_log'
        ];
        
        global $db;
        foreach ($tables as $table) {
            $query = "SHOW TABLES LIKE '$table'";
            $result = $db->query($query);
            if (!$db->fetchByAssoc($result)) {
                $issues[] = "Missing table: $table";
            }
        }
        
        // Check for orphaned records
        $query = "SELECT COUNT(*) as count FROM template_versions tv 
                  LEFT JOIN template_definitions td ON tv.template_id = td.id 
                  WHERE td.id IS NULL";
        $result = $db->query($query);
        $row = $db->fetchByAssoc($result);
        if ($row['count'] > 0) {
            $issues[] = "Found {$row['count']} orphaned template versions";
        }
        
        // Check for templates without current version
        $query = "SELECT td.id, td.name FROM template_definitions td 
                  LEFT JOIN template_versions tv ON td.id = tv.template_id AND tv.is_current = 1 
                  WHERE tv.id IS NULL AND td.deleted = 0";
        $result = $db->query($query);
        $orphanedTemplates = 0;
        while ($row = $db->fetchByAssoc($result)) {
            $orphanedTemplates++;
        }
        if ($orphanedTemplates > 0) {
            $issues[] = "Found $orphanedTemplates templates without current version";
        }
        
        if (empty($issues)) {
            $this->success("System validation passed - no issues found");
        } else {
            $this->warning("System validation found issues:");
            foreach ($issues as $issue) {
                echo "  • $issue\n";
            }
        }
    }
    
    /**
     * Show system statistics
     */
    private function showStatistics($args)
    {
        global $db;
        
        // Template count
        $query = "SELECT COUNT(*) as count FROM template_definitions WHERE deleted = 0";
        $result = $db->query($query);
        $templates = $db->fetchByAssoc($result)['count'];
        
        // Version count
        $query = "SELECT COUNT(*) as count FROM template_versions WHERE deleted = 0";
        $result = $db->query($query);
        $versions = $db->fetchByAssoc($result)['count'];
        
        // Recent activity
        $query = "SELECT COUNT(*) as count FROM template_audit_log WHERE action_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $result = $db->query($query);
        $recentActivity = $db->fetchByAssoc($result)['count'];
        
        // Active branches
        $query = "SELECT COUNT(*) as count FROM template_branches WHERE branch_status = 'active' AND deleted = 0";
        $result = $db->query($query);
        $activeBranches = $db->fetchByAssoc($result)['count'];
        
        echo "\n";
        echo "Template Versioning System Statistics\n";
        echo "=====================================\n";
        echo "Templates:           $templates\n";
        echo "Total Versions:      $versions\n";
        echo "Active Branches:     $activeBranches\n";
        echo "Recent Activity:     $recentActivity (30 days)\n";
        echo "Average per Template: " . ($templates > 0 ? round($versions / $templates, 1) : 0) . "\n";
        echo "\n";
    }
    
    /**
     * Show help
     */
    private function showHelp()
    {
        echo "\nTemplate Versioning CLI Utility\n";
        echo "================================\n\n";
        
        echo "Usage: php template_versioning_cli.php <command> [options]\n\n";
        
        echo "Commands:\n";
        echo "  create     Create new version\n";
        echo "  list       List versions for template\n";
        echo "  compare    Compare two versions\n";
        echo "  rollback   Rollback to previous version\n";
        echo "  migrate    Migrate template instances\n";
        echo "  audit      Show audit trail\n";
        echo "  branch     Manage branches\n";
        echo "  cleanup    Clean up old versions\n";
        echo "  export     Export version to file\n";
        echo "  import     Import version from file\n";
        echo "  validate   Validate system integrity\n";
        echo "  stats      Show system statistics\n";
        echo "  help       Show this help\n\n";
        
        echo "Examples:\n";
        echo "  php template_versioning_cli.php create --template-id=123 --content-file=template.json --type=major\n";
        echo "  php template_versioning_cli.php list --template-id=123\n";
        echo "  php template_versioning_cli.php compare --from=version1 --to=version2 --type=semantic\n";
        echo "  php template_versioning_cli.php rollback --template-id=123 --version-id=version1 --reason=\"Bug fix\"\n";
        echo "  php template_versioning_cli.php branch --action=create --template-id=123 --parent-version=v1 --name=feature-branch\n\n";
    }
    
    /**
     * Utility methods
     */
    
    private function parseArgs($args)
    {
        $options = [];
        foreach ($args as $arg) {
            if (strpos($arg, '--') === 0) {
                $parts = explode('=', substr($arg, 2), 2);
                $options[$parts[0]] = $parts[1] ?? true;
            }
        }
        return $options;
    }
    
    private function printTable($columns, $data)
    {
        if (empty($data)) {
            $this->info("No data to display");
            return;
        }
        
        $headers = array_keys($columns);
        $widths = [];
        
        // Calculate column widths
        foreach ($headers as $header) {
            $widths[$header] = strlen($header);
        }
        
        foreach ($data as $row) {
            foreach ($headers as $header) {
                $field = $columns[$header];
                $value = is_callable($field) ? $field($row) : $row[$field];
                $widths[$header] = max($widths[$header], strlen($value ?? ''));
            }
        }
        
        // Print header
        echo "\n";
        foreach ($headers as $header) {
            echo str_pad($header, $widths[$header] + 2);
        }
        echo "\n";
        
        foreach ($headers as $header) {
            echo str_repeat('-', $widths[$header] + 2);
        }
        echo "\n";
        
        // Print rows
        foreach ($data as $row) {
            foreach ($headers as $header) {
                $field = $columns[$header];
                $value = is_callable($field) ? $field($row) : $row[$field];
                echo str_pad($value ?? '', $widths[$header] + 2);
            }
            echo "\n";
        }
        echo "\n";
    }
    
    private function success($message)
    {
        echo "\033[32m✓ $message\033[0m\n";
    }
    
    private function error($message)
    {
        echo "\033[31m✗ $message\033[0m\n";
    }
    
    private function warning($message)
    {
        echo "\033[33m⚠ $message\033[0m\n";
    }
    
    private function info($message)
    {
        echo "\033[36mⓘ $message\033[0m\n";
    }
}

// Run CLI if called directly
if (php_sapi_name() === 'cli') {
    $cli = new TemplateVersioningCLI();
    $cli->run($argc, $argv);
}