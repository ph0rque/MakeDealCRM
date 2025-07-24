# Pipeline API Documentation

## Overview

The Pipeline API provides RESTful endpoints for managing deals in a visual pipeline interface. All endpoints require authentication and respect SuiteCRM's ACL permissions.

## Base URL

```
https://your-suitecrm-instance.com/rest/v11_1/
```

## Authentication

All API requests must include authentication headers:

```
OAuth-Token: {your-oauth-token}
```

## Endpoints

### 1. Get Pipeline Stages

Retrieve all pipeline stages with deal counts.

**Endpoint:** `GET /Deals/pipeline/stages`

**Response:**
```json
{
  "success": true,
  "stages": [
    {
      "id": "prospecting",
      "name": "Prospecting",
      "count": 15,
      "order": 0
    },
    {
      "id": "qualification",
      "name": "Qualification",
      "count": 8,
      "order": 1
    }
  ],
  "total_deals": 45
}
```

### 2. Get Pipeline Deals

Retrieve deals filtered by stage with pagination.

**Endpoint:** `GET /Deals/pipeline/deals`

**Query Parameters:**
- `stage` (optional): Filter by specific stage ID
- `offset` (optional): Pagination offset (default: 0)
- `limit` (optional): Number of records to return (default: 20, max: 100)

**Example Request:**
```
GET /Deals/pipeline/deals?stage=qualification&offset=0&limit=10
```

**Response:**
```json
{
  "success": true,
  "records": [
    {
      "id": "abc123",
      "name": "Acme Corp Deal",
      "amount": 50000.00,
      "pipeline_stage": "qualification",
      "pipeline_focus": true,
      "account_id": "def456",
      "account_name": "Acme Corporation",
      "assigned_user_id": "user123",
      "assigned_user_name": "John Smith",
      "date_entered": "2024-01-15 10:30:00",
      "date_modified": "2024-01-20 14:45:00",
      "description": "Large enterprise deal"
    }
  ],
  "total": 8,
  "offset": 0,
  "limit": 10,
  "has_more": false
}
```

### 3. Move Deal to Different Stage

Move a deal to a different pipeline stage.

**Endpoint:** `POST /Deals/pipeline/move`

**Request Body:**
```json
{
  "deal_id": "abc123",
  "new_stage": "proposal"
}
```

**Response:**
```json
{
  "success": true,
  "deal_id": "abc123",
  "old_stage": "qualification",
  "new_stage": "proposal",
  "message": "Deal moved successfully"
}
```

### 4. Toggle Deal Focus

Toggle the focus flag on a deal to highlight it in the pipeline view.

**Endpoint:** `POST /Deals/pipeline/focus`

**Request Body:**
```json
{
  "deal_id": "abc123"
}
```

**Response:**
```json
{
  "success": true,
  "deal_id": "abc123",
  "focus": true,
  "message": "Deal marked as focus"
}
```

### 5. Get Pipeline Metrics

Retrieve pipeline analytics including conversion rates and average time in stage.

**Endpoint:** `GET /Deals/pipeline/metrics`

**Response:**
```json
{
  "success": true,
  "metrics": {
    "conversion_rates": [
      {
        "from_stage": "prospecting",
        "to_stage": "qualification",
        "conversion_rate": 65.5,
        "converted_count": 120,
        "total_count": 183
      },
      {
        "from_stage": "qualification",
        "to_stage": "proposal",
        "conversion_rate": 78.2,
        "converted_count": 94,
        "total_count": 120
      }
    ],
    "average_time_in_stage": {
      "prospecting": {
        "hours": 168.5,
        "days": 7.0
      },
      "qualification": {
        "hours": 240.0,
        "days": 10.0
      }
    },
    "total_pipeline_value": 2500000.00,
    "average_deal_size": 45000.00,
    "deals_by_stage": {
      "prospecting": {
        "count": 15,
        "total_value": 450000.00,
        "avg_value": 30000.00
      },
      "qualification": {
        "count": 8,
        "total_value": 400000.00,
        "avg_value": 50000.00
      }
    }
  },
  "generated_at": "2024-01-20 15:30:00"
}
```

## Error Responses

All endpoints return standard HTTP status codes and error messages:

### 401 Unauthorized
```json
{
  "error": "invalid_grant",
  "error_message": "The access token provided is invalid."
}
```

### 403 Forbidden
```json
{
  "error": "not_authorized",
  "error_message": "No access to view Deals"
}
```

### 404 Not Found
```json
{
  "error": "not_found",
  "error_message": "Deal not found"
}
```

### 422 Unprocessable Entity
```json
{
  "error": "missing_parameter",
  "error_message": "Missing required parameter: deal_id"
}
```

## Usage Examples

### JavaScript/Fetch Example

```javascript
// Get pipeline stages
async function getPipelineStages() {
  const response = await fetch('/rest/v11_1/Deals/pipeline/stages', {
    headers: {
      'OAuth-Token': 'your-token-here',
      'Content-Type': 'application/json'
    }
  });
  
  return await response.json();
}

// Move deal to new stage
async function moveDeal(dealId, newStage) {
  const response = await fetch('/rest/v11_1/Deals/pipeline/move', {
    method: 'POST',
    headers: {
      'OAuth-Token': 'your-token-here',
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      deal_id: dealId,
      new_stage: newStage
    })
  });
  
  return await response.json();
}
```

### cURL Examples

```bash
# Get pipeline deals
curl -X GET "https://your-instance.com/rest/v11_1/Deals/pipeline/deals?stage=qualification" \
  -H "OAuth-Token: your-token-here"

# Toggle focus on a deal
curl -X POST "https://your-instance.com/rest/v11_1/Deals/pipeline/focus" \
  -H "OAuth-Token: your-token-here" \
  -H "Content-Type: application/json" \
  -d '{"deal_id":"abc123"}'
```

## Rate Limiting

API requests are subject to rate limiting:
- 1000 requests per hour per user
- 100 requests per minute per user

## Changelog

### Version 1.0.0 (2024-01-20)
- Initial release with 5 core endpoints
- Support for pipeline visualization
- Deal movement and focus toggling
- Basic metrics and analytics