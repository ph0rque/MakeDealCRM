# Template CRUD API Documentation

## Overview

The Template API provides RESTful endpoints for managing checklist templates in the MakeDealCRM system. These templates can be used to create standardized checklists for due diligence processes, compliance workflows, and other structured tasks.

## Base URL

All API endpoints are relative to your SuiteCRM installation:
```
https://your-domain.com/api/rest/v10/
```

## Authentication

All API endpoints require authentication using SuiteCRM's OAuth 2.0 implementation. Include the access token in the Authorization header:

```
Authorization: Bearer your_access_token
```

## Content Type

All requests should include the appropriate content type header:
```
Content-Type: application/vnd.api+json
```

## API Endpoints

### 1. Get Templates

Retrieve a list of checklist templates with pagination and filtering.

**Request:**
```http
GET /Deals/templates?category=due_diligence&search=contract&limit=20&offset=0
```

**Query Parameters:**
- `category` (optional): Filter by template category
- `search` (optional): Search in template name and description  
- `is_public` (optional): Filter by public/private templates (true/false)
- `user_id` (optional): Filter by specific user's templates
- `limit` (optional): Number of results per page (default: 20, max: 100)
- `offset` (optional): Starting position for pagination (default: 0)
- `order_by` (optional): Sort field (default: 'name')
- `order_dir` (optional): Sort direction - ASC or DESC (default: 'ASC')

**Response:**
```json
{
  "success": true,
  "records": [
    {
      "id": "template-uuid-here",
      "name": "Due Diligence Checklist",
      "description": "Standard due diligence template for acquisitions",
      "category": "due_diligence",
      "is_public": true,
      "is_active": true,
      "version": 3,
      "item_count": 25,
      "created_by": "user-uuid",
      "created_by_name": "John Smith",
      "date_entered": "2025-01-20T10:00:00Z",
      "date_modified": "2025-01-22T15:30:00Z"
    }
  ],
  "total": 45,
  "offset": 0,
  "limit": 20,
  "has_more": true,
  "pagination": {
    "current_page": 1,
    "total_pages": 3,
    "per_page": 20
  }
}
```

### 2. Get Single Template

Retrieve detailed information about a specific template.

**Request:**
```http
GET /Deals/templates/{template_id}?include_items=true&include_versions=false
```

**Query Parameters:**
- `include_items` (optional): Include template items in response (default: true)
- `include_versions` (optional): Include version history (default: false)

**Response:**
```json
{
  "success": true,
  "record": {
    "id": "template-uuid-here",
    "name": "Due Diligence Checklist",
    "description": "Standard due diligence template for acquisitions",
    "category": "due_diligence",
    "is_public": true,
    "is_active": true,
    "version": 3,
    "template_data": {
      "workflow": {
        "auto_progress": false,
        "requires_approval": true,
        "default_assignee": "user-uuid"
      },
      "completion": {
        "required_percentage": 85
      }
    },
    "items": [
      {
        "id": "item-uuid-1",
        "title": "Financial Statements Review",
        "description": "Review last 3 years of audited financial statements",
        "type": "checkbox",
        "order": 1,
        "is_required": true,
        "dependencies": []
      }
    ],
    "created_by": "user-uuid",
    "created_by_name": "John Smith",
    "modified_by_name": "Jane Doe",
    "date_entered": "2025-01-20T10:00:00Z",
    "date_modified": "2025-01-22T15:30:00Z"
  }
}
```

### 3. Create Template

Create a new checklist template.

**Request:**
```http
POST /Deals/templates
Content-Type: application/vnd.api+json

{
  "name": "Compliance Checklist",
  "description": "Standard compliance verification checklist",
  "category": "compliance",
  "is_public": false,
  "is_active": true,
  "template_data": {
    "workflow": {
      "auto_progress": false,
      "requires_approval": true
    },
    "completion": {
      "required_percentage": 100
    },
    "notifications": {
      "email": {
        "enabled": true,
        "template": "compliance_notification"
      }
    }
  },
  "items": [
    {
      "title": "Regulatory Compliance Check",
      "description": "Verify all regulatory requirements are met",
      "type": "checkbox",
      "order": 1,
      "is_required": true
    },
    {
      "title": "Documentation Review",
      "description": "Review all required documentation",
      "type": "file",
      "order": 2,
      "is_required": true
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "template_id": "new-template-uuid",
  "message": "Template created successfully",
  "record": {
    "id": "new-template-uuid",
    "name": "Compliance Checklist",
    "version": 1,
    "items": [...]
  }
}
```

### 4. Update Template

Update an existing template.

**Request:**
```http
PUT /Deals/templates/{template_id}
Content-Type: application/vnd.api+json

{
  "name": "Updated Template Name",
  "description": "Updated description",
  "is_active": true,
  "change_log": "Added new compliance requirements",
  "items": [
    {
      "title": "New Item",
      "type": "checkbox",
      "order": 3,
      "is_required": false
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "template_id": "template-uuid",
  "version": 4,
  "message": "Template updated successfully",
  "record": {
    "id": "template-uuid",
    "name": "Updated Template Name",
    "version": 4,
    "date_modified": "2025-01-23T09:15:00Z"
  }
}
```

### 5. Delete Template

Soft delete a template (only if not in use).

**Request:**
```http
DELETE /Deals/templates/{template_id}
```

**Response:**
```json
{
  "success": true,
  "template_id": "template-uuid",
  "message": "Template deleted successfully"
}
```

### 6. Clone Template

Create a copy of an existing template.

**Request:**
```http
POST /Deals/templates/{template_id}/clone
Content-Type: application/vnd.api+json

{
  "name": "Cloned Template Name",
  "description": "Cloned template description",
  "category": "general",
  "is_public": false
}
```

**Response:**
```json
{
  "success": true,
  "template_id": "new-cloned-uuid",
  "source_template_id": "original-template-uuid",
  "message": "Template cloned successfully",
  "record": {
    "id": "new-cloned-uuid",
    "name": "Cloned Template Name",
    "version": 1
  }
}
```

### 7. Share Template

Share a template with other users.

**Request:**
```http
POST /Deals/templates/{template_id}/share
Content-Type: application/vnd.api+json

{
  "shares": [
    {
      "user_id": "user-uuid-1",
      "permission": "view"
    },
    {
      "user_id": "user-uuid-2", 
      "permission": "edit"
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "template_id": "template-uuid",
  "shares": [
    {
      "user_id": "user-uuid-1",
      "permission": "view",
      "action": "created"
    },
    {
      "user_id": "user-uuid-2",
      "permission": "edit", 
      "action": "updated"
    }
  ],
  "message": "Template sharing updated successfully"
}
```

### 8. Get Template Categories

Retrieve available template categories.

**Request:**
```http
GET /Deals/templates/categories
```

**Response:**
```json
{
  "success": true,
  "categories": [
    {
      "name": "general",
      "template_count": 12
    },
    {
      "name": "due_diligence", 
      "template_count": 8
    },
    {
      "name": "compliance",
      "template_count": 5
    }
  ]
}
```

### 9. Validate Template

Validate template structure before saving.

**Request:**
```http
POST /Deals/templates/validate
Content-Type: application/vnd.api+json

{
  "name": "Test Template",
  "description": "Template for validation",
  "items": [
    {
      "title": "Test Item",
      "type": "checkbox"
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "valid": true,
  "errors": [],
  "warnings": [
    "Template name already exists"
  ],
  "message": "Template structure is valid"
}
```

## Data Structures

### Template Object

```json
{
  "id": "uuid",
  "name": "string (required, max 255 chars)",
  "description": "string (optional, max 2000 chars)",
  "category": "string (optional, max 100 chars)",
  "is_public": "boolean (default: false)",
  "is_active": "boolean (default: true)",
  "version": "integer",
  "template_data": "object (optional)",
  "created_by": "uuid",
  "date_entered": "datetime",
  "date_modified": "datetime"
}
```

### Template Item Object

```json
{
  "id": "uuid",
  "title": "string (required, max 500 chars)",
  "description": "string (optional, max 2000 chars)",
  "type": "string (checkbox|text|number|date|file|select|textarea)",
  "order": "integer (default: 0)",
  "is_required": "boolean (default: false)",
  "options": "array (required for select type)",
  "dependencies": "array (optional)"
}
```

### Template Data Object

```json
{
  "workflow": {
    "auto_progress": "boolean",
    "requires_approval": "boolean",
    "default_assignee": "uuid"
  },
  "completion": {
    "required_percentage": "integer (0-100)"
  },
  "notifications": {
    "email": {
      "enabled": "boolean",
      "template": "string",
      "recipients": "array"
    },
    "webhook": {
      "enabled": "boolean",
      "url": "string"
    }
  }
}
```

## Error Handling

All API endpoints return standardized error responses with appropriate HTTP status codes.

### Error Response Format

```json
{
  "success": false,
  "error": {
    "code": "TEMPLATE_VALIDATION_ERROR",
    "message": "Template validation failed",
    "http_status": 400,
    "timestamp": "2025-01-23T10:30:00Z",
    "request_id": "template_abc123_1234567890",
    "user_id": "user-uuid",
    "details": {
      "validation_errors": [
        "Template name is required",
        "Invalid item type specified"
      ],
      "error_count": 2
    },
    "documentation": "https://docs.makedealscrm.com/api/templates/errors/validation"
  }
}
```

### Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `TEMPLATE_VALIDATION_ERROR` | 400 | Invalid input data or validation failure |
| `TEMPLATE_NOT_FOUND` | 404 | Template not found |
| `TEMPLATE_ACCESS_DENIED` | 403 | Insufficient permissions |
| `TEMPLATE_DUPLICATE_NAME` | 409 | Template name already exists |
| `TEMPLATE_IN_USE` | 409 | Template cannot be deleted (in use) |
| `TEMPLATE_DATABASE_ERROR` | 500 | Database operation failed |
| `TEMPLATE_RATE_LIMIT` | 429 | Too many requests |
| `TEMPLATE_QUOTA_EXCEEDED` | 409 | User quota exceeded |
| `TEMPLATE_INTERNAL_ERROR` | 500 | Unexpected internal error |

## Permissions

Templates support granular permissions:

- **view**: Can read template details and items
- **edit**: Can modify template and items  
- **delete**: Can delete template (if not in use)
- **share**: Can share template with other users

### Permission Inheritance

- Template owners have full permissions
- Public templates are viewable by all authenticated users
- Shared templates inherit the specified permission level
- ACL rules apply based on user roles and module access

## Rate Limiting

API endpoints are rate limited to prevent abuse:

- **Default**: 100 requests per minute per user
- **Bulk operations**: 10 requests per minute per user
- **Search operations**: 50 requests per minute per user

Rate limit headers are included in responses:
```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 87
X-RateLimit-Reset: 1706001600
```

## Pagination

List endpoints support cursor-based pagination:

- Use `offset` and `limit` parameters
- Maximum `limit` is 100 records
- `has_more` indicates if additional pages exist
- Use `total` for displaying progress indicators

## Search and Filtering

Templates support advanced search and filtering:

- **Text search**: Searches name and description fields
- **Category filtering**: Filter by template category
- **User filtering**: Filter by template owner
- **Status filtering**: Filter by public/private or active/inactive
- **Date filtering**: Filter by creation or modification date

## Versioning

Templates support automatic versioning:

- Each update increments the version number
- Version history is maintained for audit purposes
- Previous versions can be viewed but not restored via API
- Change logs are captured with each version

## Best Practices

### 1. Template Design
- Use clear, descriptive names
- Organize items in logical order
- Include helpful descriptions for complex items
- Set appropriate completion percentages

### 2. Error Handling
- Always check the `success` field in responses
- Handle validation errors gracefully
- Provide user-friendly error messages
- Log error details for debugging

### 3. Performance
- Use pagination for large result sets
- Cache template data when possible
- Batch operations when creating multiple items
- Use specific filters to reduce result sizes

### 4. Security
- Validate all input data
- Use appropriate permission levels when sharing
- Avoid exposing sensitive information in public templates
- Regularly audit template access permissions

## Examples

### Complete Template Creation Example

```javascript
// JavaScript example using fetch API
async function createTemplate() {
  const templateData = {
    name: "M&A Due Diligence Checklist",
    description: "Comprehensive checklist for merger and acquisition due diligence",
    category: "due_diligence",
    is_public: false,
    template_data: {
      workflow: {
        requires_approval: true,
        auto_progress: false
      },
      completion: {
        required_percentage: 90
      }
    },
    items: [
      {
        title: "Financial Records Review",
        description: "Review financial statements, tax returns, and accounting records",
        type: "checkbox",
        order: 1,
        is_required: true
      },
      {
        title: "Legal Documentation",
        description: "Upload corporate documents, contracts, and legal agreements",
        type: "file",
        order: 2,
        is_required: true
      }
    ]
  };

  try {
    const response = await fetch('/api/rest/v10/Deals/templates', {
      method: 'POST',
      headers: {
        'Authorization': 'Bearer ' + accessToken,
        'Content-Type': 'application/vnd.api+json'
      },
      body: JSON.stringify(templateData)
    });

    const result = await response.json();
    
    if (result.success) {
      console.log('Template created:', result.template_id);
    } else {
      console.error('Error:', result.error.message);
    }
  } catch (error) {
    console.error('Request failed:', error);
  }
}
```

### Template Search Example

```php
<?php
// PHP example using cURL
function searchTemplates($searchTerm, $category = null) {
    $url = '/api/rest/v10/Deals/templates?search=' . urlencode($searchTerm);
    if ($category) {
        $url .= '&category=' . urlencode($category);
    }
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/vnd.api+json'
        ]
    ]);
    
    $response = curl_exec($curl);
    $result = json_decode($response, true);
    
    if ($result['success']) {
        return $result['records'];
    } else {
        throw new Exception($result['error']['message']);
    }
}
?>
```

## Support

For additional support and documentation:

- **API Documentation**: https://docs.makedealscrm.com/api/
- **Support Portal**: https://support.makedealscrm.com/
- **Community Forum**: https://community.makedealscrm.com/
- **GitHub Issues**: https://github.com/makedealscrm/issues