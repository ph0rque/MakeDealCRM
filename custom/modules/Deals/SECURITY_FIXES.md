# Deals Module Security Fixes

## Overview
This document outlines the security vulnerabilities found and fixed in the Deals module.

## Vulnerabilities Fixed

### 1. SQL Injection Vulnerabilities

#### Controller.php
- **Issue**: Direct string concatenation in SQL queries without proper escaping
- **Fixed**: Implemented prepared statements for all database queries
- **Added**: Input validation functions `validateGUID()` and `validatePipelineStage()`

#### Deal.php
- **Issue**: Unescaped variables in SQL queries within `logStageChange()`, `canMoveToStage()`, and `getPipelineMetrics()`
- **Fixed**: Converted all queries to use prepared statements with parameter binding

#### view.pipeline.php
- **Issue**: No SQL injection protection in the main query
- **Fixed**: While the query itself was relatively safe, added limit clause and improved error handling

#### post_install.php
- **Issue**: Direct string interpolation in table creation and update queries
- **Fixed**: Implemented prepared statements and input validation for all dynamic SQL

### 2. Cross-Site Scripting (XSS) Vulnerabilities

#### All PHP files
- **Issue**: Output not properly escaped
- **Fixed**: Added `htmlspecialchars()` with ENT_QUOTES flag for all user-controlled output
- **Added**: Security headers including X-XSS-Protection, X-Content-Type-Options, and CSP

#### Controller responses
- **Issue**: JSON responses without proper encoding
- **Fixed**: Added proper JSON encoding and security headers for AJAX responses

### 3. Additional Security Improvements

1. **CSRF Protection**: Added CSRF token generation in pipeline view
2. **Input Validation**: Added strict validation for all user inputs
3. **Error Handling**: Improved error handling to prevent information disclosure
4. **Database Indexes**: Added performance indexes to prevent DoS through slow queries
5. **Prepared Statements**: Converted all database queries to use mysqli prepared statements

## Files Modified

1. **controller.php** - Complete rewrite with security improvements
2. **Deal.php** - Added prepared statements and input validation
3. **views/view.pipeline.php** - Added XSS protection and security headers
4. **scripts/post_install.php** - Secured all database operations

## Database Changes

### New Table: pipeline_stage_history
- Added proper foreign key constraints
- Added indexes for performance
- Used InnoDB engine for transaction support

### New Indexes on opportunities table
- idx_pipeline_stage
- idx_stage_entered_date

## How to Apply Security Fixes

1. **Backup your system first**
2. **Run the repair script**:
   ```bash
   cd /path/to/suitecrm/custom/modules/Deals/scripts
   php repair_security.php
   ```
3. **Clear SuiteCRM cache**: Admin > Repair > Quick Repair and Rebuild
4. **Test the pipeline functionality**

## Testing Recommendations

1. **SQL Injection Tests**:
   - Try injecting SQL in deal_id, stage parameters
   - Verify prepared statements are blocking injection attempts

2. **XSS Tests**:
   - Create deals with HTML/JavaScript in names
   - Verify all output is properly escaped

3. **CSRF Tests**:
   - Verify token validation on state changes
   - Test cross-origin requests are blocked

## Best Practices Going Forward

1. **Always use prepared statements** for database queries
2. **Escape all output** using `htmlspecialchars()` or similar
3. **Validate all input** against expected formats
4. **Use SuiteCRM's built-in security functions** where available
5. **Regular security audits** of custom code

## SuiteCRM Security Functions Used

- `create_guid()` - For generating secure IDs
- `BeanFactory::getBean()` - For safe object loading
- `ACLAccess()` - For permission checking
- `DBManager` prepared statements - For SQL injection prevention

## Additional Notes

- The repair script creates backups of original files before replacing them
- All secure versions are suffixed with `_secure.php` until applied
- The migration script follows SuiteCRM's standard migration patterns
- All changes maintain backward compatibility with existing data