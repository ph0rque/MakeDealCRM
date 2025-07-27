<?php
/**
 * ACL configuration for Deals module
 * This ensures the module has proper access control
 */

if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

class DealsAcls extends SugarACLStrategy
{
    /**
     * Check access for the Deals module
     * For now, we'll allow all access to users who can access the module
     */
    public function checkAccess($module, $action, $context)
    {
        global $current_user;
        
        // Always allow admin users
        if ($current_user->isAdmin()) {
            return true;
        }
        
        // Check if user has access to the module
        if (!$current_user->hasModuleAccess($module)) {
            return false;
        }
        
        // For now, if user has module access, allow all actions
        // You can customize this based on your requirements
        return true;
    }
}