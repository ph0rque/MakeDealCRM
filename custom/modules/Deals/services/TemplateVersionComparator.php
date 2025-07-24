<?php
/**
 * Template Version Comparator
 * Handles version comparison algorithms and diff generation
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

class TemplateVersionComparator
{
    private $db;
    private $cacheExpiry = 86400; // 24 hours
    
    public function __construct()
    {
        global $db;
        $this->db = $db;
    }
    
    /**
     * Compare two template versions with caching
     */
    public function compareVersions($fromVersionId, $toVersionId, $diffType = 'semantic')
    {
        try {
            // Check cache first
            $cachedDiff = $this->getCachedDiff($fromVersionId, $toVersionId, $diffType);
            if ($cachedDiff) {
                return [
                    'success' => true,
                    'cached' => true,
                    'diff' => json_decode($cachedDiff['diff_content'], true),
                    'change_count' => $cachedDiff['change_count'],
                    'complexity_score' => $cachedDiff['complexity_score']
                ];
            }
            
            // Get version data
            $fromVersion = $this->getVersionData($fromVersionId);
            $toVersion = $this->getVersionData($toVersionId);
            
            if (!$fromVersion || !$toVersion) {
                throw new Exception('One or both versions not found');
            }
            
            // Parse JSON content
            $fromContent = json_decode($fromVersion['content'], true);
            $toContent = json_decode($toVersion['content'], true);
            
            if (!$fromContent || !$toContent) {
                throw new Exception('Invalid JSON content in versions');
            }
            
            // Perform comparison based on type
            switch ($diffType) {
                case 'json':
                    $diff = $this->generateJsonDiff($fromContent, $toContent);
                    break;
                case 'semantic':
                    $diff = $this->generateSemanticDiff($fromContent, $toContent);
                    break;
                case 'visual':
                    $diff = $this->generateVisualDiff($fromContent, $toContent);
                    break;
                default:
                    throw new Exception('Invalid diff type: ' . $diffType);
            }
            
            // Calculate metrics
            $changeCount = $this->calculateChangeCount($diff);
            $complexityScore = $this->calculateComplexityScore($diff, $fromContent, $toContent);
            
            // Cache the result
            $this->cacheDiff($fromVersionId, $toVersionId, $diffType, $diff, $changeCount, $complexityScore);
            
            return [
                'success' => true,
                'cached' => false,
                'diff' => $diff,
                'change_count' => $changeCount,
                'complexity_score' => $complexityScore,
                'from_version' => $fromVersion['version_number'],
                'to_version' => $toVersion['version_number']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate JSON-level diff (structural changes)
     */
    private function generateJsonDiff($fromContent, $toContent)
    {
        $diff = [
            'added' => [],
            'removed' => [],
            'modified' => [],
            'structural_changes' => []
        ];
        
        // Recursive diff comparison
        $this->recursiveDiff('', $fromContent, $toContent, $diff);
        
        return $diff;
    }
    
    /**
     * Generate semantic diff (business logic changes)
     */
    private function generateSemanticDiff($fromContent, $toContent)
    {
        $diff = [
            'tasks' => [
                'added' => [],
                'removed' => [],
                'modified' => [],
                'reordered' => []
            ],
            'metadata' => [
                'added' => [],
                'removed' => [],
                'modified' => []
            ],
            'dependencies' => [
                'added' => [],
                'removed' => [],
                'modified' => []
            ]
        ];
        
        // Compare tasks
        $this->compareTasksSemantics($fromContent, $toContent, $diff['tasks']);
        
        // Compare metadata
        $this->compareMetadataSemantics($fromContent, $toContent, $diff['metadata']);
        
        // Compare dependencies
        $this->compareDependenciesSemantics($fromContent, $toContent, $diff['dependencies']);
        
        return $diff;
    }
    
    /**
     * Generate visual diff (user interface changes)
     */
    private function generateVisualDiff($fromContent, $toContent)
    {
        $diff = [
            'layout_changes' => [],
            'styling_changes' => [],
            'ordering_changes' => [],
            'visibility_changes' => []
        ];
        
        // Compare visual elements
        $this->compareVisualLayout($fromContent, $toContent, $diff);
        
        return $diff;
    }
    
    /**
     * Compare tasks for semantic differences
     */
    private function compareTasksSemantics($fromContent, $toContent, &$taskDiff)
    {
        $fromTasks = $this->extractTasks($fromContent);
        $toTasks = $this->extractTasks($toContent);
        
        $fromTaskIds = array_column($fromTasks, 'id');
        $toTaskIds = array_column($toTasks, 'id');
        
        // Find added tasks
        $addedIds = array_diff($toTaskIds, $fromTaskIds);
        foreach ($addedIds as $id) {
            $task = $this->findTaskById($toTasks, $id);
            if ($task) {
                $taskDiff['added'][] = $task;
            }
        }
        
        // Find removed tasks
        $removedIds = array_diff($fromTaskIds, $toTaskIds);
        foreach ($removedIds as $id) {
            $task = $this->findTaskById($fromTasks, $id);
            if ($task) {
                $taskDiff['removed'][] = $task;
            }
        }
        
        // Find modified tasks
        $commonIds = array_intersect($fromTaskIds, $toTaskIds);
        foreach ($commonIds as $id) {
            $fromTask = $this->findTaskById($fromTasks, $id);
            $toTask = $this->findTaskById($toTasks, $id);
            
            if ($fromTask && $toTask && $this->tasksAreDifferent($fromTask, $toTask)) {
                $taskDiff['modified'][] = [
                    'id' => $id,
                    'from' => $fromTask,
                    'to' => $toTask,
                    'changes' => $this->getTaskChanges($fromTask, $toTask)
                ];
            }
        }
        
        // Check for reordering
        $this->detectTaskReordering($fromTasks, $toTasks, $taskDiff);
    }
    
    /**
     * Compare metadata for semantic differences
     */
    private function compareMetadataSemantics($fromContent, $toContent, &$metadataDiff)
    {
        $fromMeta = $fromContent['metadata'] ?? [];
        $toMeta = $toContent['metadata'] ?? [];
        
        // Find added metadata
        foreach ($toMeta as $key => $value) {
            if (!isset($fromMeta[$key])) {
                $metadataDiff['added'][] = ['key' => $key, 'value' => $value];
            }
        }
        
        // Find removed metadata
        foreach ($fromMeta as $key => $value) {
            if (!isset($toMeta[$key])) {
                $metadataDiff['removed'][] = ['key' => $key, 'value' => $value];
            }
        }
        
        // Find modified metadata
        foreach ($fromMeta as $key => $fromValue) {
            if (isset($toMeta[$key]) && $fromValue !== $toMeta[$key]) {
                $metadataDiff['modified'][] = [
                    'key' => $key,
                    'from' => $fromValue,
                    'to' => $toMeta[$key]
                ];
            }
        }
    }
    
    /**
     * Compare dependencies for semantic differences
     */
    private function compareDependenciesSemantics($fromContent, $toContent, &$dependencyDiff)
    {
        $fromDeps = $this->extractDependencies($fromContent);
        $toDeps = $this->extractDependencies($toContent);
        
        // Find added dependencies
        foreach ($toDeps as $dep) {
            if (!$this->dependencyExists($dep, $fromDeps)) {
                $dependencyDiff['added'][] = $dep;
            }
        }
        
        // Find removed dependencies
        foreach ($fromDeps as $dep) {
            if (!$this->dependencyExists($dep, $toDeps)) {
                $dependencyDiff['removed'][] = $dep;
            }
        }
        
        // Find modified dependencies (if they have additional properties)
        $this->findModifiedDependencies($fromDeps, $toDeps, $dependencyDiff);
    }
    
    /**
     * Compare visual layout changes
     */
    private function compareVisualLayout($fromContent, $toContent, &$visualDiff)
    {
        // Compare section ordering
        $fromSections = $this->extractSections($fromContent);
        $toSections = $this->extractSections($toContent);
        
        if ($fromSections !== $toSections) {
            $visualDiff['ordering_changes'][] = [
                'type' => 'sections',
                'from' => $fromSections,
                'to' => $toSections
            ];
        }
        
        // Compare styling properties
        $fromStyles = $fromContent['styles'] ?? [];
        $toStyles = $toContent['styles'] ?? [];
        
        $styleDiff = $this->compareArrays($fromStyles, $toStyles);
        if (!empty($styleDiff)) {
            $visualDiff['styling_changes'] = $styleDiff;
        }
        
        // Compare visibility settings
        $fromVisibility = $this->extractVisibilitySettings($fromContent);
        $toVisibility = $this->extractVisibilitySettings($toContent);
        
        $visibilityDiff = $this->compareArrays($fromVisibility, $toVisibility);
        if (!empty($visibilityDiff)) {
            $visualDiff['visibility_changes'] = $visibilityDiff;
        }
    }
    
    /**
     * Calculate total change count
     */
    private function calculateChangeCount($diff)
    {
        $count = 0;
        
        // Recursively count changes
        $this->countChangesRecursive($diff, $count);
        
        return $count;
    }
    
    /**
     * Calculate complexity score (0-100)
     */
    private function calculateComplexityScore($diff, $fromContent, $toContent)
    {
        $score = 0;
        
        // Base score from change count
        $changeCount = $this->calculateChangeCount($diff);
        $score += min($changeCount * 2, 30); // Max 30 points for change count
        
        // Structural complexity
        $structuralChanges = $this->countStructuralChanges($diff);
        $score += min($structuralChanges * 5, 25); // Max 25 points for structural changes
        
        // Dependency complexity
        $dependencyChanges = $this->countDependencyChanges($diff);
        $score += min($dependencyChanges * 3, 20); // Max 20 points for dependency changes
        
        // Content size factor
        $fromSize = strlen(json_encode($fromContent));
        $toSize = strlen(json_encode($toContent));
        $sizeFactor = abs($toSize - $fromSize) / max($fromSize, 1);
        $score += min($sizeFactor * 100, 25); // Max 25 points for size changes
        
        return min(round($score, 2), 100);
    }
    
    /**
     * Cache diff result
     */
    private function cacheDiff($fromVersionId, $toVersionId, $diffType, $diff, $changeCount, $complexityScore)
    {
        $cacheId = create_guid();
        $expiresDate = date('Y-m-d H:i:s', time() + $this->cacheExpiry);
        
        $query = "INSERT INTO template_version_diffs (
            id, from_version_id, to_version_id, diff_type, diff_content,
            change_count, complexity_score, computed_date, expires_date
        ) VALUES (
            '{$cacheId}', '{$fromVersionId}', '{$toVersionId}', '{$diffType}',
            '" . $this->db->quote(json_encode($diff)) . "',
            {$changeCount}, {$complexityScore}, NOW(), '{$expiresDate}'
        )";
        
        $this->db->query($query);
    }
    
    /**
     * Get cached diff if exists and not expired
     */
    private function getCachedDiff($fromVersionId, $toVersionId, $diffType)
    {
        $query = "SELECT * FROM template_version_diffs 
                  WHERE from_version_id = '{$fromVersionId}' 
                  AND to_version_id = '{$toVersionId}' 
                  AND diff_type = '{$diffType}'
                  AND (expires_date IS NULL OR expires_date > NOW())
                  ORDER BY computed_date DESC 
                  LIMIT 1";
        
        $result = $this->db->query($query);
        return $this->db->fetchByAssoc($result);
    }
    
    /**
     * Helper methods
     */
    
    private function getVersionData($versionId)
    {
        $query = "SELECT * FROM template_versions WHERE id = '{$versionId}' AND deleted = 0 LIMIT 1";
        $result = $this->db->query($query);
        return $this->db->fetchByAssoc($result);
    }
    
    private function recursiveDiff($path, $from, $to, &$diff)
    {
        if (is_array($from) && is_array($to)) {
            foreach ($from as $key => $value) {
                $currentPath = $path ? "{$path}.{$key}" : $key;
                if (!array_key_exists($key, $to)) {
                    $diff['removed'][$currentPath] = $value;
                } else {
                    $this->recursiveDiff($currentPath, $value, $to[$key], $diff);
                }
            }
            
            foreach ($to as $key => $value) {
                $currentPath = $path ? "{$path}.{$key}" : $key;
                if (!array_key_exists($key, $from)) {
                    $diff['added'][$currentPath] = $value;
                }
            }
        } else if ($from !== $to) {
            $diff['modified'][$path] = ['from' => $from, 'to' => $to];
        }
    }
    
    private function extractTasks($content)
    {
        return $content['tasks'] ?? [];
    }
    
    private function extractDependencies($content)
    {
        $dependencies = [];
        if (isset($content['tasks'])) {
            foreach ($content['tasks'] as $task) {
                if (isset($task['dependencies'])) {
                    foreach ($task['dependencies'] as $dep) {
                        $dependencies[] = [
                            'task_id' => $task['id'],
                            'depends_on' => $dep
                        ];
                    }
                }
            }
        }
        return $dependencies;
    }
    
    private function extractSections($content)
    {
        return array_keys($content['sections'] ?? []);
    }
    
    private function extractVisibilitySettings($content)
    {
        $visibility = [];
        if (isset($content['tasks'])) {
            foreach ($content['tasks'] as $task) {
                if (isset($task['visible'])) {
                    $visibility[$task['id']] = $task['visible'];
                }
            }
        }
        return $visibility;
    }
    
    private function findTaskById($tasks, $id)
    {
        foreach ($tasks as $task) {
            if ($task['id'] === $id) {
                return $task;
            }
        }
        return null;
    }
    
    private function tasksAreDifferent($task1, $task2)
    {
        $compareFields = ['title', 'description', 'priority', 'estimated_hours', 'dependencies'];
        
        foreach ($compareFields as $field) {
            if (($task1[$field] ?? null) !== ($task2[$field] ?? null)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function getTaskChanges($fromTask, $toTask)
    {
        $changes = [];
        $compareFields = ['title', 'description', 'priority', 'estimated_hours', 'dependencies'];
        
        foreach ($compareFields as $field) {
            $fromValue = $fromTask[$field] ?? null;
            $toValue = $toTask[$field] ?? null;
            
            if ($fromValue !== $toValue) {
                $changes[$field] = ['from' => $fromValue, 'to' => $toValue];
            }
        }
        
        return $changes;
    }
    
    private function detectTaskReordering($fromTasks, $toTasks, &$taskDiff)
    {
        $fromOrder = array_column($fromTasks, 'id');
        $toOrder = array_column($toTasks, 'id');
        
        if ($fromOrder !== $toOrder) {
            $taskDiff['reordered'] = [
                'from' => $fromOrder,
                'to' => $toOrder
            ];
        }
    }
    
    private function dependencyExists($dependency, $dependencyList)
    {
        foreach ($dependencyList as $existing) {
            if ($existing['task_id'] === $dependency['task_id'] && 
                $existing['depends_on'] === $dependency['depends_on']) {
                return true;
            }
        }
        return false;
    }
    
    private function findModifiedDependencies($fromDeps, $toDeps, &$dependencyDiff)
    {
        // Implementation for finding modified dependencies with additional properties
        // This is placeholder for more complex dependency comparison logic
    }
    
    private function compareArrays($array1, $array2)
    {
        $diff = [];
        
        // Find differences recursively
        foreach ($array1 as $key => $value) {
            if (!array_key_exists($key, $array2)) {
                $diff['removed'][$key] = $value;
            } else if ($value !== $array2[$key]) {
                $diff['modified'][$key] = ['from' => $value, 'to' => $array2[$key]];
            }
        }
        
        foreach ($array2 as $key => $value) {
            if (!array_key_exists($key, $array1)) {
                $diff['added'][$key] = $value;
            }
        }
        
        return $diff;
    }
    
    private function countChangesRecursive($data, &$count)
    {
        if (is_array($data)) {
            foreach ($data as $item) {
                if (is_array($item)) {
                    $count += count($item);
                    $this->countChangesRecursive($item, $count);
                } else {
                    $count++;
                }
            }
        }
    }
    
    private function countStructuralChanges($diff)
    {
        $count = 0;
        if (isset($diff['structural_changes'])) {
            $count += count($diff['structural_changes']);
        }
        if (isset($diff['ordering_changes'])) {
            $count += count($diff['ordering_changes']);
        }
        return $count;
    }
    
    private function countDependencyChanges($diff)
    {
        $count = 0;
        if (isset($diff['dependencies'])) {
            $count += count($diff['dependencies']['added'] ?? []);
            $count += count($diff['dependencies']['removed'] ?? []);
            $count += count($diff['dependencies']['modified'] ?? []);
        }
        return $count;
    }
}