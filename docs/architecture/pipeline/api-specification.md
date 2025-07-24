# Pipeline API Specification

## Overview

This document defines the REST API endpoints for the Pipeline functionality in the Deals module. All endpoints follow RESTful conventions and return JSON responses.

## Base URL

```
https://crm.example.com/rest/v11_4/
```

## Authentication

All API requests require authentication using OAuth 2.0 bearer tokens:

```http
Authorization: Bearer {access_token}
```

## API Endpoints

### 1. Pipeline Management

#### Get Pipeline Overview
```http
GET /Deals/pipeline/overview
```

**Response:**
```json
{
    "stages": [
        {
            "key": "sourcing",
            "name": "Sourcing",
            "order": 1,
            "wip_limit": null,
            "color": "#9c27b0",
            "deals_count": 15,
            "total_value": 2500000,
            "is_terminal": false
        }
    ],
    "metrics": {
        "total_deals": 87,
        "total_value": 42500000,
        "average_deal_size": 488505,
        "deals_at_risk": 12,
        "focus_deals": 8
    }
}
```

#### Get Deals by Stage
```http
GET /Deals/pipeline/stage/{stage_key}?offset=0&limit=50&sort=wip_position
```

**Parameters:**
- `stage_key` (required): Pipeline stage identifier
- `offset` (optional): Pagination offset (default: 0)
- `limit` (optional): Number of records (default: 50, max: 100)
- `sort` (optional): Sort field (wip_position, date_entered, deal_value)
- `filter` (optional): JSON-encoded filter criteria

**Response:**
```json
{
    "records": [
        {
            "id": "abc123",
            "name": "Acme Corp Acquisition",
            "deal_value": 250000,
            "currency_id": "USD",
            "assigned_user": {
                "id": "user123",
                "name": "John Smith"
            },
            "pipeline_stage": "sourcing",
            "wip_position": 1,
            "stage_entered_date": "2024-01-15T10:30:00Z",
            "time_in_stage": 432,
            "time_in_stage_display": "18 days",
            "at_risk_status": "Warning",
            "focus_c": true,
            "source": "Broker",
            "financial_summary": {
                "asking_price": 300000,
                "ttm_revenue": 1200000,
                "ttm_ebitda": 300000,
                "proposed_valuation": 250000
            }
        }
    ],
    "next_offset": 50,
    "total_count": 87
}
```

### 2. Deal Movement

#### Move Deal to Stage
```http
POST /Deals/pipeline/move
```

**Request Body:**
```json
{
    "deal_id": "abc123",
    "from_stage": "sourcing",
    "to_stage": "qualified",
    "position": 3,
    "reason": "Initial qualification complete",
    "override_wip": false
}
```

**Response:**
```json
{
    "success": true,
    "deal": {
        "id": "abc123",
        "pipeline_stage": "qualified",
        "wip_position": 3,
        "stage_entered_date": "2024-02-01T14:30:00Z"
    },
    "transition": {
        "id": "trans123",
        "time_in_previous_stage": 432
    },
    "stage_metrics": {
        "deals_count": 12,
        "wip_limit": 15,
        "utilization": 0.8
    }
}
```

**Error Response (WIP Limit):**
```json
{
    "error": "wip_limit_exceeded",
    "error_message": "Stage 'Qualified' has reached its WIP limit of 15 deals",
    "current_count": 15,
    "wip_limit": 15,
    "can_override": true
}
```

#### Reorder Deals within Stage
```http
PUT /Deals/pipeline/reorder
```

**Request Body:**
```json
{
    "stage_key": "qualified",
    "deal_positions": [
        {"deal_id": "abc123", "position": 1},
        {"deal_id": "def456", "position": 2},
        {"deal_id": "ghi789", "position": 3}
    ]
}
```

### 3. Stage Configuration

#### Update Stage WIP Limit
```http
PUT /Deals/pipeline/stages/{stage_key}/wip-limit
```

**Request Body:**
```json
{
    "wip_limit": 20
}
```

**Response:**
```json
{
    "success": true,
    "stage": {
        "key": "qualified",
        "name": "Qualified",
        "wip_limit": 20,
        "current_count": 12
    }
}
```

#### Get Stage Transitions
```http
GET /Deals/pipeline/stages/{stage_key}/transitions?period=30d
```

**Response:**
```json
{
    "incoming": [
        {
            "from_stage": "sourcing",
            "count": 23,
            "average_time_to_move": 5.2
        }
    ],
    "outgoing": [
        {
            "to_stage": "meeting_scheduled",
            "count": 18,
            "average_time_to_move": 8.7,
            "conversion_rate": 0.78
        }
    ],
    "total_incoming": 35,
    "total_outgoing": 28,
    "net_flow": 7
}
```

### 4. Metrics and Analytics

#### Get Pipeline Velocity
```http
GET /Deals/pipeline/metrics/velocity?period=30d
```

**Response:**
```json
{
    "period": "30d",
    "stages": [
        {
            "stage": "sourcing",
            "average_days": 5.2,
            "median_days": 4,
            "deals_processed": 45
        }
    ],
    "overall_velocity": {
        "average_cycle_time": 45.3,
        "deals_closed": 12,
        "close_rate": 0.267
    }
}
```

#### Get Bottleneck Analysis
```http
GET /Deals/pipeline/metrics/bottlenecks
```

**Response:**
```json
{
    "bottlenecks": [
        {
            "stage": "under_review",
            "severity": "high",
            "average_days_stuck": 28.5,
            "deals_stuck": 8,
            "wip_utilization": 1.0,
            "recommendations": [
                "Increase WIP limit or add resources",
                "Review stuck deals for common blockers"
            ]
        }
    ]
}
```

### 5. User Preferences

#### Get User Pipeline Preferences
```http
GET /Deals/pipeline/preferences
```

**Response:**
```json
{
    "show_archived": false,
    "show_focus_only": false,
    "card_display_fields": ["deal_value", "source", "assigned_user"],
    "sort_order": "date_entered",
    "sort_direction": "DESC",
    "filter_settings": {
        "min_value": 100000,
        "sources": ["Broker", "Direct"]
    },
    "collapsed_stages": ["closed", "unavailable"],
    "card_size": "medium"
}
```

#### Update User Preferences
```http
PUT /Deals/pipeline/preferences
```

**Request Body:**
```json
{
    "show_focus_only": true,
    "card_size": "large",
    "collapsed_stages": ["unavailable"]
}
```

### 6. Deal Actions

#### Toggle Focus Flag
```http
PUT /Deals/{deal_id}/focus
```

**Request Body:**
```json
{
    "focus": true
}
```

#### Archive Deal
```http
PUT /Deals/{deal_id}/archive
```

**Request Body:**
```json
{
    "archive": true,
    "reason": "Deal on hold pending partner decision"
}
```

### 7. History and Audit

#### Get Deal Stage History
```http
GET /Deals/{deal_id}/pipeline/history
```

**Response:**
```json
{
    "transitions": [
        {
            "id": "trans123",
            "from_stage": "sourcing",
            "to_stage": "qualified",
            "transition_date": "2024-01-20T10:30:00Z",
            "transition_by": {
                "id": "user123",
                "name": "John Smith"
            },
            "time_in_previous_stage": 120,
            "notes": "Qualified after initial call"
        }
    ],
    "total_transitions": 4,
    "current_stage_since": "2024-02-01T14:30:00Z"
}
```

### 8. Bulk Operations

#### Bulk Move Deals
```http
POST /Deals/pipeline/bulk-move
```

**Request Body:**
```json
{
    "deal_ids": ["abc123", "def456", "ghi789"],
    "to_stage": "qualified",
    "reason": "Bulk qualification after review",
    "preserve_order": true
}
```

**Response:**
```json
{
    "success": true,
    "moved": 3,
    "failed": 0,
    "results": [
        {
            "deal_id": "abc123",
            "success": true,
            "new_position": 12
        }
    ]
}
```

### 9. WebSocket Events

For real-time updates, connect to the WebSocket endpoint:

```javascript
ws://crm.example.com/ws/pipeline
```

**Events:**

```json
// Deal moved
{
    "event": "deal_moved",
    "data": {
        "deal_id": "abc123",
        "from_stage": "sourcing",
        "to_stage": "qualified",
        "moved_by": "user123",
        "timestamp": "2024-02-01T14:30:00Z"
    }
}

// WIP limit changed
{
    "event": "wip_limit_changed",
    "data": {
        "stage": "qualified",
        "old_limit": 15,
        "new_limit": 20,
        "changed_by": "admin123"
    }
}

// Deal at risk
{
    "event": "deal_at_risk",
    "data": {
        "deal_id": "abc123",
        "stage": "under_review",
        "days_in_stage": 31,
        "risk_level": "critical"
    }
}
```

## Error Handling

All errors follow a consistent format:

```json
{
    "error": "error_code",
    "error_message": "Human readable error message",
    "details": {
        // Additional error context
    }
}
```

### Common Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `unauthorized` | 401 | Invalid or missing authentication |
| `forbidden` | 403 | User lacks permission for action |
| `not_found` | 404 | Resource not found |
| `validation_error` | 400 | Invalid request parameters |
| `wip_limit_exceeded` | 409 | WIP limit would be exceeded |
| `stage_transition_invalid` | 400 | Invalid stage transition |
| `rate_limit_exceeded` | 429 | Too many requests |

## Rate Limiting

API requests are limited to:
- 1000 requests per hour per user
- 100 requests per minute per user

Rate limit information is included in response headers:
```http
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 950
X-RateLimit-Reset: 1706788800
```

## Pagination

All list endpoints support pagination:

```http
GET /Deals/pipeline/stage/qualified?offset=20&limit=20
```

Pagination info in response:
```json
{
    "records": [...],
    "next_offset": 40,
    "previous_offset": 0,
    "total_count": 87,
    "has_more": true
}
```

## Filtering

Complex filters can be applied using the `filter` parameter:

```http
GET /Deals/pipeline/stage/qualified?filter={"deal_value":{"$gte":100000},"source":{"$in":["Broker","Direct"]}}
```

Supported operators:
- `$eq`: Equals
- `$ne`: Not equals
- `$gt`: Greater than
- `$gte`: Greater than or equal
- `$lt`: Less than
- `$lte`: Less than or equal
- `$in`: In array
- `$nin`: Not in array
- `$like`: Pattern match

## Webhooks

Configure webhooks for pipeline events:

```http
POST /admin/webhooks
```

```json
{
    "url": "https://example.com/webhook",
    "events": ["deal_moved", "wip_limit_exceeded"],
    "secret": "webhook_secret_key"
}
```