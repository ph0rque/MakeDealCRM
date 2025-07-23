# SuiteCRM API Architecture

## Overview

SuiteCRM provides multiple API interfaces for integration with external systems and building custom applications. The primary API is the REST API V8, which follows modern RESTful principles and uses OAuth2 for authentication. Legacy SOAP APIs are also available for backward compatibility.

## API Types

### 1. REST API V8 (Recommended)
- Modern RESTful interface
- JSON API specification compliant
- OAuth2 authentication
- Full CRUD operations
- Relationship management
- Metadata access

### 2. REST API V4.1 (Legacy)
- Basic REST interface
- Session-based authentication
- Limited functionality
- Maintained for backward compatibility

### 3. SOAP API (Legacy)
- XML-based web services
- Session-based authentication
- Comprehensive functionality
- Being phased out

## REST API V8 Architecture

### Technology Stack

| Component | Technology | Purpose |
|-----------|------------|---------|
| Framework | Slim Framework 3.x | RESTful routing and middleware |
| Authentication | OAuth2 Server | Secure API authentication |
| Specification | JSON API 1.0 | Request/response format |
| Documentation | Swagger/OpenAPI | API documentation |

### Directory Structure

```
Api/
├── Core/               # Core API functionality
│   ├── app.php        # Slim application bootstrap
│   └── Config/        # API configuration
├── V8/                # Version 8 API
│   ├── BeanDecorator/ # Data transformation
│   ├── Controller/    # API controllers
│   ├── Factory/       # Object factories
│   ├── Helper/        # Helper classes
│   ├── JsonApi/       # JSON API implementation
│   ├── Meta/          # Metadata providers
│   ├── Param/         # Parameter handling
│   └── Service/       # Business logic services
└── docs/              # API documentation
```

## Authentication

### OAuth2 Implementation

#### 1. Client Credentials Grant
For server-to-server communication:

```bash
POST /Api/access_token
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials&
client_id=your_client_id&
client_secret=your_client_secret
```

#### 2. Password Grant
For trusted applications:

```bash
POST /Api/access_token
Content-Type: application/x-www-form-urlencoded

grant_type=password&
client_id=your_client_id&
client_secret=your_client_secret&
username=user&
password=pass
```

### Token Management

```json
{
  "token_type": "Bearer",
  "expires_in": 3600,
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "refresh_token": "def50200641f104f8..."
}
```

### Creating OAuth2 Clients

1. Navigate to Admin → OAuth2 Clients and Tokens
2. Create new client
3. Set client type (User or System)
4. Generate client ID and secret
5. Configure allowed grant types

## API Endpoints

### Module Operations

#### List Records
```
GET /Api/V8/module/{module_name}
```

Query parameters:
- `filter`: Filter records
- `page[size]`: Number of records per page
- `page[number]`: Page number
- `sort`: Sort field and direction
- `fields[{module}]`: Sparse fieldsets
- `include`: Include related resources

Example:
```
GET /Api/V8/module/Accounts?filter[name][eq]=Acme&include=contacts&page[size]=10
```

#### Get Single Record
```
GET /Api/V8/module/{module_name}/{id}
```

#### Create Record
```
POST /Api/V8/module/{module_name}
Content-Type: application/vnd.api+json

{
  "data": {
    "type": "Accounts",
    "attributes": {
      "name": "New Account",
      "industry": "Technology"
    }
  }
}
```

#### Update Record
```
PATCH /Api/V8/module/{module_name}/{id}
Content-Type: application/vnd.api+json

{
  "data": {
    "type": "Accounts",
    "id": "11111111-2222-3333-4444-555555555555",
    "attributes": {
      "name": "Updated Account Name"
    }
  }
}
```

#### Delete Record
```
DELETE /Api/V8/module/{module_name}/{id}
```

### Relationship Operations

#### Get Related Records
```
GET /Api/V8/module/{module_name}/{id}/relationships/{link_field_name}
```

#### Add Relationship
```
POST /Api/V8/module/{module_name}/{id}/relationships/{link_field_name}
Content-Type: application/vnd.api+json

{
  "data": [
    {"type": "Contacts", "id": "contact-id-1"},
    {"type": "Contacts", "id": "contact-id-2"}
  ]
}
```

#### Remove Relationship
```
DELETE /Api/V8/module/{module_name}/{id}/relationships/{link_field_name}
Content-Type: application/vnd.api+json

{
  "data": [
    {"type": "Contacts", "id": "contact-id-1"}
  ]
}
```

### Metadata Operations

#### Get Module Metadata
```
GET /Api/V8/meta/modules/{module_name}
```

Response includes:
- Field definitions
- Relationship definitions
- ACL information
- Module configuration

#### Get Field Definitions
```
GET /Api/V8/meta/fields/{module_name}
```

## Request/Response Format

### JSON API Specification

All requests and responses follow the JSON API specification:

#### Request Structure
```json
{
  "data": {
    "type": "resource-type",
    "id": "resource-id",
    "attributes": {
      "field1": "value1",
      "field2": "value2"
    },
    "relationships": {
      "relation1": {
        "data": {"type": "related-type", "id": "related-id"}
      }
    }
  }
}
```

#### Response Structure
```json
{
  "data": {
    "type": "Accounts",
    "id": "11111111-2222-3333-4444-555555555555",
    "attributes": {
      "name": "Acme Corporation",
      "industry": "Technology",
      "created": "2024-01-15T10:30:00+00:00"
    },
    "relationships": {
      "contacts": {
        "links": {
          "self": "/Api/V8/module/Accounts/11111111-2222-3333-4444-555555555555/relationships/contacts",
          "related": "/Api/V8/module/Accounts/11111111-2222-3333-4444-555555555555/contacts"
        }
      }
    }
  },
  "included": [],
  "meta": {
    "total-pages": 5
  }
}
```

## Error Handling

### Error Response Format

```json
{
  "errors": [
    {
      "id": "error-uuid",
      "status": "400",
      "title": "Bad Request",
      "detail": "The field 'name' is required.",
      "source": {
        "pointer": "/data/attributes/name"
      }
    }
  ]
}
```

### Common HTTP Status Codes

| Code | Meaning | Use Case |
|------|---------|----------|
| 200 | OK | Successful GET, PATCH |
| 201 | Created | Successful POST |
| 204 | No Content | Successful DELETE |
| 400 | Bad Request | Invalid request format |
| 401 | Unauthorized | Invalid/missing authentication |
| 403 | Forbidden | Insufficient permissions |
| 404 | Not Found | Resource doesn't exist |
| 422 | Unprocessable Entity | Validation errors |
| 500 | Internal Server Error | Server error |

## API Security

### Authentication Security
- OAuth2 with bearer tokens
- Token expiration and refresh
- Client credentials stored securely
- HTTPS required for production

### Authorization
- ACL enforcement at API level
- Field-level security
- Record-level security through Security Groups
- Role-based access control

### Input Validation
- Data type validation
- Required field validation
- Field length validation
- Custom validation rules

### Rate Limiting
Configure in `config.php`:
```php
$sugar_config['api']['rate_limit'] = [
    'enabled' => true,
    'requests_per_minute' => 60,
    'requests_per_hour' => 1000,
];
```

## Performance Optimization

### Caching
- Response caching headers
- ETag support
- Conditional requests

### Pagination
Always use pagination for list endpoints:
```
GET /Api/V8/module/Contacts?page[size]=20&page[number]=1
```

### Sparse Fieldsets
Request only needed fields:
```
GET /Api/V8/module/Accounts?fields[Accounts]=name,industry
```

### Relationship Loading
Use `include` parameter efficiently:
```
GET /Api/V8/module/Accounts/123?include=contacts,opportunities
```

## Custom API Endpoints

### Creating Custom Endpoints

1. Create route file:
```php
// custom/Api/V8/routes.php
$app->get('/custom/endpoint', 'CustomController:action');
```

2. Create controller:
```php
// custom/Api/V8/Controller/CustomController.php
namespace Api\V8\Controller;

class CustomController
{
    public function action($request, $response, $args)
    {
        // Custom logic
        return $response->withJson(['status' => 'success']);
    }
}
```

### Extending Existing Endpoints

Use middleware to modify behavior:
```php
$app->add(function ($request, $response, $next) {
    // Pre-processing
    $response = $next($request, $response);
    // Post-processing
    return $response;
});
```

## Integration Examples

### JavaScript/Node.js
```javascript
const axios = require('axios');

// Get access token
const auth = await axios.post('https://crm.example.com/Api/access_token', {
  grant_type: 'client_credentials',
  client_id: 'your_client_id',
  client_secret: 'your_client_secret'
});

// Make API request
const accounts = await axios.get('https://crm.example.com/Api/V8/module/Accounts', {
  headers: {
    'Authorization': `Bearer ${auth.data.access_token}`,
    'Content-Type': 'application/vnd.api+json'
  }
});
```

### PHP
```php
$client = new GuzzleHttp\Client();

// Get access token
$auth = $client->post('https://crm.example.com/Api/access_token', [
    'form_params' => [
        'grant_type' => 'client_credentials',
        'client_id' => 'your_client_id',
        'client_secret' => 'your_client_secret',
    ]
]);

$token = json_decode($auth->getBody())->access_token;

// Make API request
$response = $client->get('https://crm.example.com/Api/V8/module/Accounts', [
    'headers' => [
        'Authorization' => "Bearer $token",
        'Content-Type' => 'application/vnd.api+json'
    ]
]);
```

## Best Practices

1. **Use OAuth2** - Always use OAuth2 for authentication
2. **HTTPS Only** - Never use API over unencrypted connections
3. **Pagination** - Always paginate list requests
4. **Error Handling** - Implement comprehensive error handling
5. **Rate Limiting** - Respect rate limits and implement backoff
6. **Caching** - Use caching headers to reduce server load
7. **Logging** - Log all API interactions for debugging
8. **Versioning** - Use API versioning for backward compatibility

## Conclusion

SuiteCRM's API architecture provides a robust and secure interface for building integrations and custom applications. The REST API V8, with its adherence to modern standards and comprehensive functionality, offers developers the tools needed to create powerful CRM-integrated solutions while maintaining security and performance.