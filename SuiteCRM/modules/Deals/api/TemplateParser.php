<?php
/**
 * Template Parser for Task Generation Engine
 * 
 * Handles template parsing with variable substitution, conditional logic,
 * and dynamic content generation for task templates.
 * 
 * @category  API
 * @package   Deals
 * @author    MakeDealCRM
 * @license   GPL-3.0+
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

class TemplateParser
{
    private $logger;
    private $variablePattern = '/\{\{([^}]+)\}\}/';
    private $conditionalPattern = '/\{\{#if\s+([^}]+)\}\}(.*?)\{\{\/if\}\}/s';
    private $loopPattern = '/\{\{#each\s+([^}]+)\}\}(.*?)\{\{\/each\}\}/s';
    
    public function __construct()
    {
        global $log;
        $this->logger = $log;
    }
    
    /**
     * Parse template with variable substitution and conditional logic
     * 
     * @param array $template Template data
     * @param array $variables Variable values for substitution
     * @return array Parsed template
     */
    public function parseTemplate($template, $variables)
    {
        try {
            $this->logger->info("TemplateParser: Parsing template '{$template['name']}'");
            
            $parsedTemplate = $template;
            $parsedTasks = array();
            
            foreach ($template['tasks'] as $taskTemplate) {
                $parsedTask = $this->parseTaskTemplate($taskTemplate, $variables);
                
                // Skip tasks that don't meet conditional requirements
                if ($parsedTask !== null) {
                    $parsedTasks[] = $parsedTask;
                }
            }
            
            $parsedTemplate['tasks'] = $parsedTasks;
            $parsedTemplate['parsed_variables'] = $this->getUsedVariables($template, $variables);
            
            $this->logger->info("TemplateParser: Successfully parsed " . count($parsedTasks) . " tasks");
            
            return $parsedTemplate;
            
        } catch (Exception $e) {
            $this->logger->error("TemplateParser: Error parsing template - " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Parse individual task template
     * 
     * @param array $taskTemplate Task template data
     * @param array $variables Variable values
     * @return array|null Parsed task or null if conditions not met
     */
    private function parseTaskTemplate($taskTemplate, $variables)
    {
        // Check conditional requirements
        if (!$this->evaluateConditions($taskTemplate, $variables)) {
            return null;
        }
        
        $parsedTask = $taskTemplate;
        
        // Parse text fields with variable substitution
        $textFields = ['name', 'description', 'instructions', 'notes'];
        foreach ($textFields as $field) {
            if (isset($parsedTask[$field])) {
                $parsedTask[$field] = $this->substituteVariables($parsedTask[$field], $variables);
            }
        }
        
        // Parse conditional content blocks
        foreach ($textFields as $field) {
            if (isset($parsedTask[$field])) {
                $parsedTask[$field] = $this->parseConditionalBlocks($parsedTask[$field], $variables);
            }
        }
        
        // Parse loops/iterations
        foreach ($textFields as $field) {
            if (isset($parsedTask[$field])) {
                $parsedTask[$field] = $this->parseLoops($parsedTask[$field], $variables);
            }
        }
        
        // Parse dynamic properties
        if (isset($parsedTask['dynamic_properties'])) {
            $parsedTask['properties'] = $this->parseDynamicProperties(
                $parsedTask['dynamic_properties'], 
                $variables
            );
        }
        
        // Generate unique task ID
        $parsedTask['template_task_id'] = $parsedTask['id'] ?? uniqid('task_');
        $parsedTask['id'] = create_guid();
        
        return $parsedTask;
    }
    
    /**
     * Substitute variables in text using {{variable}} syntax
     * 
     * @param string $text Text with variable placeholders
     * @param array $variables Variable values
     * @return string Text with variables substituted
     */
    private function substituteVariables($text, $variables)
    {
        return preg_replace_callback($this->variablePattern, function($matches) use ($variables) {
            $variableName = trim($matches[1]);
            
            // Handle nested property access (e.g., account.name)
            if (strpos($variableName, '.') !== false) {
                return $this->getNestedVariable($variableName, $variables);
            }
            
            // Handle function calls (e.g., date('Y-m-d'))
            if (strpos($variableName, '(') !== false) {
                return $this->evaluateFunction($variableName, $variables);
            }
            
            // Simple variable substitution
            return isset($variables[$variableName]) ? $variables[$variableName] : $matches[0];
        }, $text);
    }
    
    /**
     * Parse conditional blocks using {{#if condition}}...{{/if}} syntax
     * 
     * @param string $text Text with conditional blocks
     * @param array $variables Variable values
     * @return string Text with conditionals processed
     */
    private function parseConditionalBlocks($text, $variables)
    {
        return preg_replace_callback($this->conditionalPattern, function($matches) use ($variables) {
            $condition = trim($matches[1]);
            $content = $matches[2];
            
            if ($this->evaluateCondition($condition, $variables)) {
                return $this->substituteVariables($content, $variables);
            }
            
            return '';
        }, $text);
    }
    
    /**
     * Parse loop blocks using {{#each array}}...{{/each}} syntax
     * 
     * @param string $text Text with loop blocks
     * @param array $variables Variable values
     * @return string Text with loops processed
     */
    private function parseLoops($text, $variables)
    {
        return preg_replace_callback($this->loopPattern, function($matches) use ($variables) {
            $arrayName = trim($matches[1]);
            $template = $matches[2];
            
            if (!isset($variables[$arrayName]) || !is_array($variables[$arrayName])) {
                return '';
            }
            
            $result = '';
            foreach ($variables[$arrayName] as $index => $item) {
                $itemVariables = array_merge($variables, array(
                    'this' => $item,
                    '@index' => $index,
                    '@first' => ($index === 0),
                    '@last' => ($index === count($variables[$arrayName]) - 1)
                ));
                
                if (is_array($item)) {
                    $itemVariables = array_merge($itemVariables, $item);
                }
                
                $result .= $this->substituteVariables($template, $itemVariables);
            }
            
            return $result;
        }, $text);
    }
    
    /**
     * Parse dynamic properties based on conditions
     * 
     * @param array $dynamicProperties Dynamic property definitions
     * @param array $variables Variable values
     * @return array Resolved properties
     */
    private function parseDynamicProperties($dynamicProperties, $variables)
    {
        $properties = array();
        
        foreach ($dynamicProperties as $property) {
            $condition = $property['condition'] ?? 'true';
            
            if ($this->evaluateCondition($condition, $variables)) {
                $key = $this->substituteVariables($property['key'], $variables);
                $value = $this->substituteVariables($property['value'], $variables);
                
                // Handle special value types
                if (isset($property['type'])) {
                    $value = $this->castValue($value, $property['type']);
                }
                
                $properties[$key] = $value;
            }
        }
        
        return $properties;
    }
    
    /**
     * Evaluate conditions in task templates
     * 
     * @param array $taskTemplate Task template
     * @param array $variables Variable values
     * @return bool Whether conditions are met
     */
    private function evaluateConditions($taskTemplate, $variables)
    {
        if (!isset($taskTemplate['conditions'])) {
            return true;
        }
        
        foreach ($taskTemplate['conditions'] as $condition) {
            if (!$this->evaluateCondition($condition, $variables)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Evaluate a single condition expression
     * 
     * @param string $condition Condition expression
     * @param array $variables Variable values
     * @return bool Condition result
     */
    private function evaluateCondition($condition, $variables)
    {
        // Handle simple existence checks
        if (preg_match('/^(\w+)$/', $condition, $matches)) {
            return !empty($variables[$matches[1]]);
        }
        
        // Handle comparison operations
        if (preg_match('/^(\w+(?:\.\w+)*)\s*(==|!=|>|<|>=|<=)\s*(.+)$/', $condition, $matches)) {
            $leftValue = $this->getVariableValue($matches[1], $variables);
            $operator = $matches[2];
            $rightValue = $this->parseConditionValue($matches[3], $variables);
            
            return $this->compareValues($leftValue, $operator, $rightValue);
        }
        
        // Handle 'in' operations
        if (preg_match('/^(\w+(?:\.\w+)*)\s+in\s+\[([^\]]+)\]$/', $condition, $matches)) {
            $value = $this->getVariableValue($matches[1], $variables);
            $array = array_map('trim', explode(',', $matches[2]));
            $array = array_map(function($item) use ($variables) {
                return $this->parseConditionValue($item, $variables);
            }, $array);
            
            return in_array($value, $array);
        }
        
        // Handle function calls
        if (strpos($condition, '(') !== false) {
            return $this->evaluateFunction($condition, $variables);
        }
        
        return false;
    }
    
    /**
     * Get nested variable value using dot notation
     * 
     * @param string $path Variable path (e.g., 'account.name')
     * @param array $variables Variable values
     * @return mixed Variable value
     */
    private function getNestedVariable($path, $variables)
    {
        $parts = explode('.', $path);
        $value = $variables;
        
        foreach ($parts as $part) {
            if (is_array($value) && isset($value[$part])) {
                $value = $value[$part];
            } else {
                return null;
            }
        }
        
        return $value;
    }
    
    /**
     * Get variable value with support for nested paths
     * 
     * @param string $path Variable path
     * @param array $variables Variable values
     * @return mixed Variable value
     */
    private function getVariableValue($path, $variables)
    {
        if (strpos($path, '.') !== false) {
            return $this->getNestedVariable($path, $variables);
        }
        
        return isset($variables[$path]) ? $variables[$path] : null;
    }
    
    /**
     * Parse condition value (handle quotes, numbers, variables)
     * 
     * @param string $value Condition value string
     * @param array $variables Variable values
     * @return mixed Parsed value
     */
    private function parseConditionValue($value, $variables)
    {
        $value = trim($value);
        
        // Handle quoted strings
        if (preg_match('/^["\'](.+)["\']$/', $value, $matches)) {
            return $matches[1];
        }
        
        // Handle numbers
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float)$value : (int)$value;
        }
        
        // Handle boolean values
        if ($value === 'true') return true;
        if ($value === 'false') return false;
        if ($value === 'null') return null;
        
        // Handle variables
        return $this->getVariableValue($value, $variables);
    }
    
    /**
     * Compare values using operator
     * 
     * @param mixed $left Left value
     * @param string $operator Comparison operator
     * @param mixed $right Right value
     * @return bool Comparison result
     */
    private function compareValues($left, $operator, $right)
    {
        switch ($operator) {
            case '==': return $left == $right;
            case '!=': return $left != $right;
            case '>': return $left > $right;
            case '<': return $left < $right;
            case '>=': return $left >= $right;
            case '<=': return $left <= $right;
            default: return false;
        }
    }
    
    /**
     * Evaluate function calls in templates
     * 
     * @param string $functionCall Function call string
     * @param array $variables Variable values
     * @return mixed Function result
     */
    private function evaluateFunction($functionCall, $variables)
    {
        // Handle date functions
        if (preg_match('/^date\(["\']([^"\']+)["\']\)$/', $functionCall, $matches)) {
            return date($matches[1]);
        }
        
        if (preg_match('/^date\(["\']([^"\']+)["\']\s*,\s*["\']([^"\']+)["\']\)$/', $functionCall, $matches)) {
            return date($matches[1], strtotime($matches[2]));
        }
        
        // Handle string functions
        if (preg_match('/^upper\((\w+)\)$/', $functionCall, $matches)) {
            $value = $this->getVariableValue($matches[1], $variables);
            return strtoupper($value);
        }
        
        if (preg_match('/^lower\((\w+)\)$/', $functionCall, $matches)) {
            $value = $this->getVariableValue($matches[1], $variables);
            return strtolower($value);
        }
        
        // Handle math functions
        if (preg_match('/^add\((\w+),\s*(\d+)\)$/', $functionCall, $matches)) {
            $value = $this->getVariableValue($matches[1], $variables);
            return (is_numeric($value) ? (float)$value : 0) + (int)$matches[2];
        }
        
        return $functionCall; // Return original if no function matched
    }
    
    /**
     * Cast value to specified type
     * 
     * @param mixed $value Value to cast
     * @param string $type Target type
     * @return mixed Casted value
     */
    private function castValue($value, $type)
    {
        switch (strtolower($type)) {
            case 'int':
            case 'integer':
                return (int)$value;
            case 'float':
            case 'double':
                return (float)$value;
            case 'bool':
            case 'boolean':
                return (bool)$value;
            case 'string':
                return (string)$value;
            case 'array':
                return is_array($value) ? $value : array($value);
            case 'date':
                return date('Y-m-d', strtotime($value));
            case 'datetime':
                return date('Y-m-d H:i:s', strtotime($value));
            default:
                return $value;
        }
    }
    
    /**
     * Get list of variables used in template
     * 
     * @param array $template Template data
     * @param array $variables Available variables
     * @return array Used variables with their values
     */
    private function getUsedVariables($template, $variables)
    {
        $usedVariables = array();
        $templateText = json_encode($template);
        
        preg_match_all($this->variablePattern, $templateText, $matches);
        
        foreach ($matches[1] as $variableName) {
            $variableName = trim($variableName);
            
            if (strpos($variableName, '.') !== false) {
                $value = $this->getNestedVariable($variableName, $variables);
            } else {
                $value = isset($variables[$variableName]) ? $variables[$variableName] : null;
            }
            
            if ($value !== null) {
                $usedVariables[$variableName] = $value;
            }
        }
        
        return $usedVariables;
    }
}