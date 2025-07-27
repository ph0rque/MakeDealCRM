# Pipeline View Solution - Deals Not Showing

## Problem
Deals are being saved but not appearing in the kanban/pipeline view at http://localhost:8080/index.php?module=Deals&action=Pipeline

## Root Cause
The deals were saved with empty critical fields:
- `date_closed` is empty/NULL
- Some deals had empty `sales_stage`
- The pipeline view query may filter out deals with missing data

## Solution Steps

### 1. Manual Database Fix (Recommended)
Access phpMyAdmin at http://localhost:8080:8081 and run these SQL queries:

```sql
-- Fix date_closed for all deals
UPDATE opportunities 
SET date_closed = DATE_ADD(CURDATE(), INTERVAL 30 DAY)
WHERE deleted = 0 
AND (date_closed IS NULL OR date_closed = '' OR date_closed = '0000-00-00');

-- Ensure all deals have sales_stage
UPDATE opportunities 
SET sales_stage = 'Prospecting'
WHERE deleted = 0 
AND (sales_stage IS NULL OR sales_stage = '');

-- Ensure all deals have amount
UPDATE opportunities 
SET amount = 100000
WHERE deleted = 0 
AND (amount IS NULL OR amount = 0);
```

### 2. Alternative: Create New Test Deal
1. Go to http://localhost:8080/test_deal_save.php
2. Fill in ALL fields:
   - Deal Name
   - Amount
   - Sales Stage
   - Pipeline Stage
   - Close Date
3. Save the deal
4. Check if it appears in the pipeline

### 3. Clear All Caches
```bash
docker-compose exec suitecrm sh -c "rm -rf cache/* && chmod -R 777 cache"
docker-compose restart suitecrm
```

### 4. Verify Pipeline View
1. Clear browser cache (Ctrl+Shift+Delete)
2. Logout and login again
3. Go to: http://localhost:8080/index.php?module=Deals&action=Pipeline

## Why EditView Isn't Saving Properly

The EditView is extending OpportunitiesViewEdit but the form might not be mapping fields correctly. To fix future saves:

1. Ensure all required fields are filled in the form
2. Check that the form is posting to the correct action
3. Verify field names match the database columns

## Current Deal Status
- 14 deals exist in the database
- All have `pipeline_stage_c = 'sourcing'`
- Most are missing `date_closed` values
- All other fields are populated

## Quick Test
After fixing the database, you should see deals in the "Sourcing" column of the pipeline view. If not:
1. Check browser console for JavaScript errors
2. Verify the pipeline JavaScript is loading
3. Check that the pipeline stages match the deal stages