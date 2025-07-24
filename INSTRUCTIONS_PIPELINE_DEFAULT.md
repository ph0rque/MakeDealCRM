# Instructions to Make Pipeline View the Default for Deals Module

## Steps to Apply the Changes:

### 1. Clear Cache via SuiteCRM Admin Panel
1. Log in to SuiteCRM as an Administrator
2. Go to **Admin** → **Repair** 
3. Click on **Quick Repair and Rebuild**
4. Execute any SQL queries if prompted
5. Click on **Rebuild Extensions**

### 2. Clear Browser Cache
1. Clear your browser cache (Ctrl+Shift+Delete or Cmd+Shift+Delete)
2. Log out of SuiteCRM
3. Log back in

### 3. Test the Pipeline View
1. Click on the **DEALS** tab in the main navigation
2. You should now be taken directly to the Pipeline (Kanban) view
3. The URL should show: `index.php?module=Deals&action=pipeline`

## What Was Changed:

1. **Controller Updates** (`custom/modules/Deals/controller.php`):
   - Modified `action_index()` to call pipeline view directly
   - Added `action_listview()` to redirect to pipeline view
   - This ensures any attempt to view the list redirects to pipeline

2. **Configuration Files Created**:
   - `custom/Extension/application/Ext/Include/deals_default_action.php` - Sets pipeline as default action
   - `custom/modules/Deals/config/module_config.php` - Module-specific configuration

3. **Menu Configuration** (`custom/modules/Deals/Menu.php`):
   - Already configured with Pipeline View as a menu option

## Alternative Method (If Above Doesn't Work):

### Via Database (Advanced):
1. Access your database
2. Run this query to check/update module settings:
```sql
-- Check if there's a config override
SELECT * FROM config WHERE category = 'Deals' AND name = 'default_action';

-- If needed, insert the override
INSERT INTO config (category, name, value) 
VALUES ('Deals', 'default_action', 'pipeline')
ON DUPLICATE KEY UPDATE value = 'pipeline';
```

### Via SuiteCRM Studio:
1. Go to **Admin** → **Studio**
2. Select **Deals** module
3. Look for module configuration options
4. Set default view to pipeline if available

## Troubleshooting:

If the pipeline view still doesn't appear as default:

1. **Check File Permissions**: Ensure all custom files are readable by the web server
2. **Check Error Logs**: Look in `suitecrm.log` for any errors
3. **Verify Database**: Run the migration scripts to ensure pipeline tables exist:
   ```
   php custom/modules/Deals/scripts/migrations/simple_migration_test.php
   ```
4. **Manual Navigation**: You can always bookmark the direct URL:
   ```
   http://localhost:8080/index.php?module=Deals&action=pipeline
   ```

## Files Modified/Created:
- `/custom/modules/Deals/controller.php` - Updated
- `/custom/Extension/application/Ext/Include/deals_default_action.php` - New
- `/custom/modules/Deals/config/module_config.php` - New
- `/clear_cache_and_repair.php` - Utility script (can be deleted after use)