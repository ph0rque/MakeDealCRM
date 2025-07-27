# ChecklistService Documentation

## Overview

The `ChecklistService` is a centralized service class that handles all checklist-related operations in the Deals module. It provides a clean, maintainable interface for checklist management and follows SuiteCRM best practices.

## Architecture

### Location
- **Service Class**: `/custom/modules/Deals/services/ChecklistService.php`
- **API Endpoints**: `/custom/modules/Deals/api/ChecklistApi.php`
- **Controller Actions**: `/custom/modules/Deals/controller.php`

### Key Components

1. **ChecklistService** - Core service class with all business logic
2. **ChecklistApi** - REST API endpoints
3. **Controller Actions** - AJAX endpoints for UI interactions
4. **Logic Hooks** - Simplified hooks that delegate to the service

## Service Methods

### Checklist Management

#### createChecklistFromTemplate($dealId, $templateId, $options)
Creates a new checklist from a template.

**Parameters:**
- `$dealId` (string) - The deal ID
- `$templateId` (string) - The template ID
- `$options` (array) - Additional options:
  - `create_tasks` (boolean) - Whether to create tasks
  - `assigned_user_id` (string) - User to assign checklist to

**Returns:** Array with success status and checklist data

#### deleteChecklist($checklistId, $cascadeDelete)
Deletes a checklist and optionally its items.

**Parameters:**
- `$checklistId` (string) - The checklist ID
- `$cascadeDelete` (boolean) - Whether to delete associated items

**Returns:** Array with success status

#### getDealChecklists($dealId, $filters)
Gets all checklists for a deal.

**Parameters:**
- `$dealId` (string) - The deal ID
- `$filters` (array) - Optional filters:
  - `status` - Filter by status
  - `template_id` - Filter by template

**Returns:** Array of checklists

### Checklist Item Operations

#### updateChecklistItem($itemId, $status, $data)
Updates a checklist item's status and data.

**Parameters:**
- `$itemId` (string) - The item ID
- `$status` (string) - New status
- `$data` (array) - Additional data:
  - `notes` - Item notes
  - `assigned_user_id` - Assigned user

**Returns:** Array with success status

#### bulkUpdateItems($itemIds, $updates)
Updates multiple checklist items at once.

**Parameters:**
- `$itemIds` (array) - Array of item IDs
- `$updates` (array) - Updates to apply

**Returns:** Array with update results

### Template Operations

#### cloneTemplate($templateId, $newName, $options)
Clones an existing template.

**Parameters:**
- `$templateId` (string) - Template to clone
- `$newName` (string) - Name for the clone
- `$options` (array) - Additional options

**Returns:** Array with new template ID

#### getAvailableTemplates($filters)
Gets templates available to the current user.

**Parameters:**
- `$filters` (array) - Optional filters:
  - `category` - Filter by category
  - `search` - Search term

**Returns:** Array of templates

### Analytics & Reporting

#### getChecklistProgress($checklistId)
Gets detailed progress report for a checklist.

**Parameters:**
- `$checklistId` (string) - The checklist ID

**Returns:** Progress report with metrics

#### getChecklistAnalytics($dealId, $dateRange)
Gets analytics data for checklists.

**Parameters:**
- `$dealId` (string) - Optional deal ID
- `$dateRange` (array) - Date range filter

**Returns:** Analytics data

#### exportChecklist($checklistId, $format)
Exports a checklist to PDF or Excel.

**Parameters:**
- `$checklistId` (string) - The checklist ID
- `$format` (string) - Export format (pdf/excel)

**Returns:** File download

## Usage Examples

### Controller Usage

```php
// In custom/modules/Deals/controller.php
public function action_ApplyChecklistTemplate()
{
    require_once('custom/modules/Deals/services/ChecklistService.php');
    
    $checklistService = new ChecklistService();
    
    $result = $checklistService->createChecklistFromTemplate(
        $_REQUEST['deal_id'],
        $_REQUEST['template_id'],
        array('create_tasks' => true)
    );
    
    $this->sendJsonResponse($result);
}
```

### Direct Service Usage

```php
// Create a checklist
$service = new ChecklistService();
$result = $service->createChecklistFromTemplate($dealId, $templateId);

// Update item status
$service->updateChecklistItem($itemId, 'completed', array(
    'notes' => 'Completed successfully'
));

// Get progress report
$progress = $service->getChecklistProgress($checklistId);
```

### API Usage

```javascript
// JavaScript API calls
$.ajax({
    url: 'rest/v10/Deals/' + dealId + '/checklists',
    method: 'POST',
    data: {
        template_id: templateId,
        create_tasks: true
    },
    success: function(response) {
        console.log('Checklist created:', response);
    }
});
```

## Features

### Automatic Updates
- Deal checklist fields are automatically updated when checklists change
- Progress is recalculated on item status changes
- Pipeline stage advancement based on checklist completion

### Permission Management
- Integrated ACL checks
- Template visibility (public/private)
- Deal-level permissions respected

### Activity Logging
- All major operations are logged
- Audit trail for compliance

### Performance Optimizations
- Efficient SQL queries
- Batch operations support
- Minimal database calls

## Migration Notes

### For Existing Code

The following methods are deprecated but still functional:
- `ChecklistTemplate->applyToDeaI()` - Use `ChecklistService->createChecklistFromTemplate()`
- `ChecklistItem->markComplete()` - Use `ChecklistService->updateChecklistItem()`

### Logic Hooks

Logic hooks have been simplified to delegate to the service:
- `ChecklistLogicHook` now uses `ChecklistService` internally
- No direct database queries in hooks
- All business logic centralized

## Best Practices

1. **Always use the service** for checklist operations
2. **Handle errors** - Check the `success` field in responses
3. **Use bulk operations** when updating multiple items
4. **Respect permissions** - Service handles ACL checks
5. **Log important events** for audit trails

## Security Considerations

- All inputs are validated and sanitized
- SQL injection prevention through parameterized queries
- ACL checks enforced at service level
- Template permissions respected

## Future Enhancements

1. **Workflow Integration** - Trigger workflows based on checklist events
2. **Notification System** - Email alerts for overdue items
3. **Mobile API** - Optimized endpoints for mobile apps
4. **Advanced Analytics** - More detailed reporting capabilities
5. **Template Marketplace** - Share templates between organizations