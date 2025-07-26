# Fix Deals Module Registration

## Issue
The Deals module is showing a 500 error when accessed through the SuiteCRM interface at localhost:8080.

## Root Causes
1. Module files exist but may not be properly registered in SuiteCRM's cache
2. Custom database fields may be missing
3. Extension files need to be compiled

## Solution Steps

### 1. Access the Diagnostic Script
Open in your browser: `http://localhost:8080/check_deals_error.php`

This will show exactly what's missing.

### 2. Run Quick Repair and Rebuild
1. Log into SuiteCRM Admin
2. Go to Admin → Repair
3. Click "Quick Repair and Rebuild"
4. **Important**: Execute any SQL queries shown at the bottom of the page

### 3. If Database Fields are Missing
Run these SQL commands in your database:

```sql
-- Create custom fields table if it doesn't exist
CREATE TABLE IF NOT EXISTS opportunities_cstm (
    id_c char(36) NOT NULL,
    PRIMARY KEY (id_c)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Add pipeline stage field
ALTER TABLE opportunities_cstm 
ADD COLUMN IF NOT EXISTS pipeline_stage_c VARCHAR(50) DEFAULT 'sourcing';

-- Add stage entered date field
ALTER TABLE opportunities_cstm 
ADD COLUMN IF NOT EXISTS stage_entered_date_c DATETIME;

-- Add other custom fields
ALTER TABLE opportunities_cstm 
ADD COLUMN IF NOT EXISTS focus_status_c VARCHAR(20) DEFAULT 'normal',
ADD COLUMN IF NOT EXISTS expected_close_date_c DATE,
ADD COLUMN IF NOT EXISTS deal_source_c VARCHAR(50),
ADD COLUMN IF NOT EXISTS deal_type_c VARCHAR(50);
```

### 4. Clear All Caches
1. In Admin → Repair, run:
   - "Rebuild Extensions"
   - "Rebuild Relationships"
   - "Clear Additional Cache"
   - "Rebuild JS Grouping Files"

### 5. Check File Permissions
Ensure the web server can read all files:
```bash
chmod -R 755 custom/modules/Deals
chmod -R 755 custom/Extension/application/Ext/Include/
```

### 6. Manual Module Registration (if needed)
If the module still doesn't appear, create this file:
`custom/application/Ext/Include/modules.ext.php`

Add these lines:
```php
$beanList['Deals'] = 'Deal';
$beanFiles['Deal'] = 'custom/modules/Deals/Deal.php';
$moduleList[] = 'Deals';
```

### 7. Test the Module
1. Clear browser cache (Ctrl+F5)
2. Navigate to the Deals module
3. Check browser console for any JavaScript errors

## Architecture Confirmation
The Deals module is correctly built as an extension of the Opportunities module:
- `Deal extends Opportunity` ✓
- Uses `opportunities` table ✓
- Inherits all Opportunity functionality ✓
- Adds M&A-specific features ✓

The 500 error is due to registration/cache issues, not architectural problems.