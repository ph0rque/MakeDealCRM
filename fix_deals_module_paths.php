<?php
/**
 * Fix Deals Module Path Issues
 * This script updates all require_once statements to work with the custom module location
 */

$files_to_fix = array(
    // Views
    'custom/modules/Deals/views/view.list.php',
    'custom/modules/Deals/views/view.pipeline.php',
    'custom/modules/Deals/views/view.pipeline_secure.php',
    'custom/modules/Deals/views/view.financialdashboard.php',
    'custom/modules/Deals/views/view.stakeholder_bulk.php',
    
    // Other PHP files that might have path issues
    'custom/modules/Deals/Menu.php',
    'custom/modules/Deals/metadata/detailviewdefs.php',
    'custom/modules/Deals/metadata/listviewdefs.php',
);

$patterns_to_fix = array(
    // Pattern => Replacement
    "/require_once\s*\(\s*['\"]modules\//" => '$suitecrm_root . \'/modules/',
    "/require_once\s*\(\s*['\"]include\//" => '$suitecrm_root . \'/include/',
);

foreach ($files_to_fix as $file) {
    if (!file_exists($file)) {
        echo "Skipping $file - does not exist\n";
        continue;
    }
    
    $content = file_get_contents($file);
    $original_content = $content;
    
    // Check if we need to add the $suitecrm_root variable
    $needs_root_var = false;
    foreach ($patterns_to_fix as $pattern => $replacement) {
        if (preg_match($pattern, $content)) {
            $needs_root_var = true;
            break;
        }
    }
    
    if ($needs_root_var) {
        // Add $suitecrm_root calculation after the sugarEntry check
        $suitecrm_root_code = "\n// Adjust paths for custom module location\n";
        $suitecrm_root_code .= "\$suitecrm_root = dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/SuiteCRM';\n";
        
        // Find the position after sugarEntry check
        $pattern = '/if\s*\(!defined\s*\(\s*[\'"]sugarEntry[\'"]\s*\)\s*\|\|\s*!sugarEntry\s*\)\s*die\s*\([^)]+\);/';
        if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            $insert_pos = $matches[0][1] + strlen($matches[0][0]);
            
            // Check if we haven't already added this
            if (strpos($content, '$suitecrm_root') === false) {
                $content = substr($content, 0, $insert_pos) . $suitecrm_root_code . substr($content, $insert_pos);
            }
        }
        
        // Now fix the require_once statements
        foreach ($patterns_to_fix as $pattern => $replacement) {
            $content = preg_replace($pattern, 'require_once(' . $replacement, $content);
        }
    }
    
    if ($content !== $original_content) {
        file_put_contents($file, $content);
        echo "Fixed: $file\n";
    } else {
        echo "No changes needed: $file\n";
    }
}

// Also fix the main module loader to use absolute path
$module_loader = 'custom/Extension/application/Ext/Include/Deals.php';
if (file_exists($module_loader)) {
    $content = file_get_contents($module_loader);
    
    // Update the Deal.php path to be absolute
    $old_path = "\$beanFiles['Deal'] = 'custom/modules/Deals/Deal.php';";
    $new_path = "\$beanFiles['Deal'] = dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/custom/modules/Deals/Deal.php';";
    
    if (strpos($content, $old_path) !== false) {
        $content = str_replace($old_path, $new_path, $content);
        file_put_contents($module_loader, $content);
        echo "Fixed module loader: $module_loader\n";
    }
}

echo "\nPath fixing complete!\n";
echo "Please clear the SuiteCRM cache and run Quick Repair and Rebuild.\n";