# Template Versioning System Documentation

## Overview

The Template Versioning System provides comprehensive version control for template management with rollback capabilities, audit logging, and migration tools. It supports semantic versioning, branch management, and automated migration processes.

## Architecture

### Core Components

1. **TemplateVersioningService** - Main service for version operations
2. **TemplateAuditLogger** - Comprehensive audit logging
3. **TemplateVersionComparator** - Version comparison algorithms
4. **TemplateMigrationManager** - Instance migration handling
5. **TemplateRollbackManager** - Safe rollback operations
6. **TemplateVersioningApi** - RESTful API endpoints
7. **CLI Utility** - Command-line management tools

### Database Schema

#### Core Tables

- `template_definitions` - Template metadata
- `template_versions` - Version storage with content
- `template_version_diffs` - Cached comparison results
- `template_audit_log` - Comprehensive audit trail
- `template_branches` - Branch management
- `template_migration_log` - Migration tracking
- `template_permissions` - Access control
- `template_rollback_backups` - Rollback safety backups
- `template_rollback_log` - Rollback operations log

## Features

### Version Management

#### Creating Versions
```php
$service = new TemplateVersioningService();
$result = $service->createVersion(
    $templateId,
    $content,
    'minor',          // major, minor, patch
    'Summary of changes',
    false             // isDraft
);
```

#### Version Types
- **Major** (X.0.0) - Breaking changes
- **Minor** (X.Y.0) - New features, backward compatible
- **Patch** (X.Y.Z) - Bug fixes, backward compatible

#### Semantic Versioning
- Follows semver.org specification
- Automatic version number calculation
- Content hash validation for integrity
- Approval workflow for major versions

### Comparison System

#### Comparison Types
1. **JSON Diff** - Structural changes
2. **Semantic Diff** - Business logic changes
3. **Visual Diff** - UI/layout changes

#### Performance Optimization
- Cached comparison results
- Configurable cache expiration
- Background diff computation
- Complexity scoring (0-100)

### Rollback Capabilities

#### Safety Features
- Pre-rollback validation
- Automatic safety backups
- Post-rollback verification
- Automatic restore on failure

#### Validation Checks
- Content integrity verification
- Dependency validation
- Permission verification
- System resource checks
- Breaking change detection

### Branch Management

#### Branch Types
- **Feature** - New functionality
- **Hotfix** - Critical bug fixes
- **Release** - Release preparation
- **Experimental** - Testing new concepts

#### Operations
- Create branches from any version
- Merge branches back to main
- Branch status tracking
- Automatic conflict detection

### Migration System

#### Migration Types
1. **Automatic** - Immediate execution
2. **Manual** - Step-by-step review
3. **Batch** - Chunked processing

#### Migration Process
1. Impact analysis
2. Migration plan generation
3. Risk assessment
4. Execution with monitoring
5. Rollback capability

### Audit System

#### Tracked Actions
- Version creation/updates
- Rollback operations
- Branch operations
- Migration activities
- Permission changes
- System errors

#### Audit Data
- User identification
- IP address tracking
- Session information
- Detailed change logs
- Metadata preservation

## API Endpoints

### Version Management
```
GET    /api/template-versioning/versions?template_id={id}
POST   /api/template-versioning/versions
PUT    /api/template-versioning/versions/{id}/publish
DELETE /api/template-versioning/versions/{id}
```

### Comparison
```
GET    /api/template-versioning/compare?from={id}&to={id}&type={type}
```

### Rollback
```
POST   /api/template-versioning/rollback
```

### Audit
```
GET    /api/template-versioning/audit?template_id={id}
```

### Branches
```
GET    /api/template-versioning/branches?template_id={id}
POST   /api/template-versioning/branches
POST   /api/template-versioning/merge
```

### Migration
```
POST   /api/template-versioning/migrate
GET    /api/template-versioning/migrations?template_id={id}
```

## CLI Usage

### Installation
```bash
# Make CLI executable
chmod +x custom/modules/Deals/scripts/template_versioning_cli.php
```

### Common Commands

#### Create Version
```bash
php template_versioning_cli.php create \
  --template-id=template-123 \
  --content-file=template.json \
  --type=minor \
  --summary="Added new validation rules"
```

#### List Versions
```bash
php template_versioning_cli.php list --template-id=template-123
```

#### Compare Versions
```bash
php template_versioning_cli.php compare \
  --from=version-1 \
  --to=version-2 \
  --type=semantic \
  --detailed=true
```

#### Rollback Version
```bash
php template_versioning_cli.php rollback \
  --template-id=template-123 \
  --version-id=version-1 \
  --reason="Critical bug fix" \
  --force=false
```

#### Create Branch
```bash
php template_versioning_cli.php branch \
  --action=create \
  --template-id=template-123 \
  --parent-version=version-2 \
  --name=feature-new-validation \
  --type=feature
```

#### System Validation
```bash
php template_versioning_cli.php validate
```

#### System Statistics
```bash
php template_versioning_cli.php stats
```

## Configuration

### Service Configuration
```php
// In config.php or similar
$GLOBALS['template_versioning'] = [
    'max_versions_per_template' => 50,
    'cache_expiry_hours' => 24,
    'max_rollback_depth' => 10,
    'enable_auto_cleanup' => true,
    'backup_retention_days' => 30,
    'require_approval_for_major' => true
];
```

### Permission Levels
- **View** - Read access to versions
- **Edit** - Create new versions
- **Publish** - Set current version
- **Admin** - Full access including rollback

## Best Practices

### Version Creation
1. Use meaningful change summaries
2. Follow semantic versioning rules
3. Test versions before publishing
4. Create drafts for work-in-progress
5. Use branches for experimental features

### Rollback Operations
1. Always review impact before rollback
2. Create manual backup for critical changes
3. Test rollback in development first
4. Document rollback reasons
5. Monitor system after rollback

### Branch Management
1. Use descriptive branch names
2. Keep branches focused on single features
3. Merge frequently to avoid conflicts
4. Clean up unused branches
5. Document branch purposes

### Migration Planning
1. Analyze migration impact first
2. Use manual migration for complex changes
3. Test migrations in staging environment
4. Monitor migration progress
5. Have rollback plan ready

## Monitoring and Maintenance

### Health Checks
- Database integrity validation
- Orphaned record detection
- Performance monitoring
- Cache effectiveness
- Error rate tracking

### Cleanup Operations
- Old version cleanup
- Expired cache removal
- Backup rotation
- Audit log archiving
- Performance optimization

### Troubleshooting

#### Common Issues
1. **Version Creation Fails**
   - Check content JSON validity
   - Verify template permissions
   - Ensure no duplicate content

2. **Rollback Fails**
   - Validate target version exists
   - Check breaking changes
   - Verify system resources

3. **Migration Errors**
   - Review migration logs
   - Check instance compatibility
   - Validate version differences

4. **Performance Issues**
   - Check cache hit rates
   - Review database indexes
   - Monitor query performance
   - Optimize comparison algorithms

## Security Considerations

### Access Control
- Role-based permissions
- Template-level access control
- Action-based authorization
- IP address restrictions

### Data Protection
- Content encryption at rest
- Secure API endpoints
- Audit trail integrity
- Backup security

### Validation
- Input sanitization
- SQL injection prevention
- XSS protection
- File upload validation

## Integration

### SuiteCRM Integration
- Uses SuiteCRM authentication
- Follows SuiteCRM patterns
- Integrates with workflow system
- Uses SuiteCRM database abstraction

### API Integration
- RESTful API design
- JSON response format
- Standard HTTP status codes
- Comprehensive error handling

### Frontend Integration
- JavaScript API client
- Real-time updates
- Progress monitoring
- User-friendly interfaces

## Deployment

### Database Setup
```sql
-- Run the migration
SOURCE custom/database/migrations/004_create_template_versioning_system.sql;
```

### File Permissions
```bash
# Set proper permissions
chmod 755 custom/modules/Deals/services/
chmod 644 custom/modules/Deals/services/*.php
chmod 755 custom/modules/Deals/scripts/
chmod 755 custom/modules/Deals/scripts/template_versioning_cli.php
```

### Apache Configuration
```apache
# Add to .htaccess for API access
RewriteRule ^api/template-versioning/(.*)$ custom/modules/Deals/api/TemplateVersioningApi.php [L,QSA]
```

## Testing

### Unit Tests
- Service method testing
- Validation logic testing
- Error handling testing
- Performance testing

### Integration Tests
- API endpoint testing
- Database integrity testing
- Migration testing
- Rollback testing

### Performance Tests
- Load testing
- Concurrent user testing
- Large dataset testing
- Cache performance testing

## Support and Maintenance

### Logging
- All operations logged to audit trail
- Error logging to system logs
- Performance metrics collection
- User activity tracking

### Backup Strategy
- Regular database backups
- Version content backups
- Configuration backups
- Restore procedures

### Monitoring
- System health monitoring
- Performance monitoring
- Error rate monitoring
- Usage analytics

## Changelog

### Version 1.0.0 (2025-07-24)
- Initial implementation
- Core versioning functionality
- Rollback capabilities
- Audit logging system
- CLI utility
- API endpoints
- Documentation

---

For technical support or questions, please refer to the SuiteCRM documentation or contact the development team.