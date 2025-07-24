# MakeDeal CRM API Documentation

## Overview

The MakeDeal CRM API provides programmatic access to all CRM functionality including lead management, deal pipeline operations, and automated workflows. All API endpoints follow RESTful conventions and return JSON responses.

## Authentication

### API Key Authentication

```http
POST /api/v1/authenticate
Content-Type: application/json

{
  "username": "api_user",
  "password": "api_password"
}
```

Response:
```json
{
  "success": true,
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "expires_in": 3600
}
```

Include token in subsequent requests:
```http
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

## Lead Management API

### Get Leads

```http
GET /api/v1/leads
```

Query Parameters:
- `status` - Filter by status (new, qualifying, qualified, converted)
- `min_score` - Minimum lead score
- `max_score` - Maximum lead score
- `industry` - Filter by industry
- `page` - Page number (default: 1)
- `limit` - Results per page (default: 20)

Response:
```json
{
  "success": true,
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "company_name": "TechCorp Inc",
      "industry": "Technology",
      "annual_revenue": 25000000,
      "lead_score": 85,
      "status": "qualified",
      "score_breakdown": {
        "company_size_score": 22,
        "industry_fit_score": 18,
        "geographic_fit_score": 13,
        "financial_health_score": 17,
        "engagement_score": 8,
        "timing_score": 7
      }
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 150
  }
}
```

### Create Lead

```http
POST /api/v1/leads
Content-Type: application/json

{
  "company_name": "NewTech Solutions",
  "industry": "Software",
  "annual_revenue": 15000000,
  "employee_count": 75,
  "primary_contact_name": "Jane Smith",
  "primary_contact_email": "jane@newtech.com",
  "phone_office": "555-0123",
  "website": "https://newtech.com",
  "geographic_region": "North America",
  "lead_source": "referral"
}
```

### Evaluate Lead for Conversion

```http
POST /api/v1/leads/{id}/evaluate
```

Response:
```json
{
  "success": true,
  "data": {
    "calculated_score": 82,
    "conversion_recommendation": "auto_conversion",
    "score_breakdown": {
      "company_size": 20,
      "industry_fit": 16,
      "geographic_fit": 12,
      "financial_health": 18,
      "engagement_level": 8,
      "timing_readiness": 8
    },
    "missing_data": [],
    "can_convert": true
  }
}
```

### Convert Lead

```http
POST /api/v1/leads/{id}/convert
Content-Type: application/json

{
  "create_deal": true,
  "create_account": true,
  "create_contact": true,
  "initial_stage": "sourcing"
}
```

## Deal Pipeline API

### Get Pipeline Overview

```http
GET /api/v1/pipeline/overview
```

Response:
```json
{
  "success": true,
  "data": {
    "stages": [
      {
        "name": "sourcing",
        "display_name": "Sourcing",
        "deal_count": 45,
        "total_value": 125000000,
        "avg_days_in_stage": 28,
        "wip_limit": 50,
        "wip_utilization": 90
      }
    ],
    "metrics": {
      "total_deals": 234,
      "total_pipeline_value": 1250000000,
      "avg_deal_size": 5342000,
      "conversion_rate": 12.5,
      "avg_cycle_time": 180
    }
  }
}
```

### Get Deals

```http
GET /api/v1/deals
```

Query Parameters:
- `stage` - Filter by stage
- `min_value` - Minimum deal value
- `max_value` - Maximum deal value
- `assigned_user_id` - Filter by assigned user
- `account_id` - Filter by account
- `is_stale` - Filter stale deals (true/false)

### Create Deal

```http
POST /api/v1/deals
Content-Type: application/json

{
  "name": "Acquisition of TechCorp",
  "company_name": "TechCorp Inc",
  "account_id": "550e8400-e29b-41d4-a716-446655440001",
  "stage": "sourcing",
  "deal_value": 50000000,
  "annual_revenue": 40000000,
  "ebitda": 8000000,
  "employee_count": 200,
  "industry": "Technology",
  "deal_source": "proprietary",
  "target_close_date": "2024-12-31"
}
```

### Validate Stage Transition

```http
POST /api/v1/deals/{id}/validate-transition
Content-Type: application/json

{
  "from_stage": "sourcing",
  "to_stage": "screening"
}
```

Response:
```json
{
  "success": true,
  "data": {
    "allowed": false,
    "errors": [
      "Missing required field: annual_revenue",
      "Missing required field: employee_count"
    ],
    "warnings": [
      "Deal has been in current stage for 45 days"
    ],
    "missing_requirements": [
      "annual_revenue",
      "employee_count"
    ],
    "wip_status": {
      "current_wip": 23,
      "wip_limit": 25,
      "would_exceed": false
    }
  }
}
```

### Execute Stage Transition

```http
POST /api/v1/deals/{id}/transition
Content-Type: application/json

{
  "to_stage": "screening",
  "reason": "Initial screening completed",
  "override_warnings": false
}
```

### Get Deal Health Score

```http
GET /api/v1/deals/{id}/health
```

Response:
```json
{
  "success": true,
  "data": {
    "health_score": 72,
    "factors": {
      "stage_progression": 25,
      "recent_activity": 10,
      "financial_metrics": 7,
      "time_in_stage": -5,
      "base_score": 35
    },
    "recommendations": [
      "Schedule follow-up meeting",
      "Update financial projections"
    ]
  }
}
```

## Account Management API

### Get Accounts

```http
GET /api/v1/accounts
```

Query Parameters:
- `account_type` - Filter by type (target, portfolio, partner)
- `industry` - Filter by industry
- `parent_id` - Filter by parent account
- `min_revenue` - Minimum annual revenue

### Create Account Hierarchy

```http
POST /api/v1/accounts/{id}/add-subsidiary
Content-Type: application/json

{
  "name": "TechCorp Subsidiary LLC",
  "account_type": "portfolio",
  "annual_revenue": 10000000,
  "employee_count": 50
}
```

### Get Portfolio Metrics

```http
GET /api/v1/accounts/{id}/portfolio-metrics
```

Response:
```json
{
  "success": true,
  "data": {
    "portfolio_company_count": 12,
    "portfolio_total_revenue": 450000000,
    "portfolio_total_employees": 3500,
    "portfolio_avg_health": 78,
    "geographic_distribution": {
      "north_america": 8,
      "europe": 3,
      "asia": 1
    }
  }
}
```

## Contact Management API

### Get Contacts

```http
GET /api/v1/contacts
```

Query Parameters:
- `account_id` - Filter by account
- `decision_role` - Filter by role (decision_maker, influencer, evaluator)
- `min_influence_score` - Minimum influence score

### Create Contact

```http
POST /api/v1/contacts
Content-Type: application/json

{
  "first_name": "John",
  "last_name": "Smith",
  "title": "Chief Executive Officer",
  "account_id": "550e8400-e29b-41d4-a716-446655440001",
  "email1": "john.smith@techcorp.com",
  "phone_work": "555-0100",
  "decision_role": "decision_maker",
  "linkedin_url": "https://linkedin.com/in/johnsmith"
}
```

### Update Influence Score

```http
POST /api/v1/contacts/{id}/calculate-influence
```

Response:
```json
{
  "success": true,
  "data": {
    "influence_score": 85,
    "factors": {
      "role_weight": 40,
      "seniority": 20,
      "engagement": 15,
      "network_size": 10
    }
  }
}
```

## Automation API

### Trigger Pipeline Maintenance

```http
POST /api/v1/automation/pipeline-maintenance
Content-Type: application/json

{
  "tasks": ["update_days_in_stage", "detect_stale_deals", "process_conversions"]
}
```

### Execute Automation Rule

```http
POST /api/v1/automation/execute-rule
Content-Type: application/json

{
  "rule_id": "auto_progress_qualified_leads",
  "dry_run": false
}
```

## Reporting API

### Pipeline Analytics

```http
GET /api/v1/reports/pipeline-analytics
```

Query Parameters:
- `date_from` - Start date (YYYY-MM-DD)
- `date_to` - End date (YYYY-MM-DD)
- `group_by` - Grouping (day, week, month)

Response:
```json
{
  "success": true,
  "data": {
    "conversion_funnel": {
      "sourcing_to_screening": 65,
      "screening_to_analysis": 45,
      "analysis_to_term_sheet": 30,
      "term_sheet_to_dd": 25,
      "dd_to_closing": 20,
      "overall_conversion": 12
    },
    "velocity_metrics": {
      "avg_days_sourcing": 35,
      "avg_days_screening": 21,
      "avg_days_dd": 60,
      "total_cycle_time": 180
    }
  }
}
```

### Lead Source Analysis

```http
GET /api/v1/reports/lead-sources
```

Response:
```json
{
  "success": true,
  "data": {
    "sources": [
      {
        "source": "referral",
        "lead_count": 45,
        "conversion_rate": 22,
        "avg_deal_size": 15000000,
        "roi": 3.5
      }
    ]
  }
}
```

## Webhook Events

### Available Events

- `lead.created`
- `lead.scored`
- `lead.converted`
- `deal.created`
- `deal.stage_changed`
- `deal.closed_won`
- `deal.closed_lost`
- `account.created`
- `contact.created`

### Webhook Payload Example

```json
{
  "event": "deal.stage_changed",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "deal_id": "550e8400-e29b-41d4-a716-446655440000",
    "deal_name": "Acquisition of TechCorp",
    "from_stage": "screening",
    "to_stage": "analysis_outreach",
    "user_id": "admin",
    "metadata": {
      "validation_score": 95,
      "auto_tasks_created": 3
    }
  }
}
```

## Error Responses

### Standard Error Format

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Validation failed",
    "details": [
      {
        "field": "annual_revenue",
        "message": "Annual revenue is required"
      }
    ]
  }
}
```

### Error Codes

- `AUTHENTICATION_ERROR` - Invalid or expired token
- `VALIDATION_ERROR` - Request validation failed
- `NOT_FOUND` - Resource not found
- `PERMISSION_DENIED` - Insufficient permissions
- `RATE_LIMIT_EXCEEDED` - Too many requests
- `INTERNAL_ERROR` - Server error

## Rate Limiting

- 1000 requests per hour per API key
- 100 requests per minute per API key
- Bulk operations count as multiple requests

Rate limit headers:
```http
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 950
X-RateLimit-Reset: 1642235400
```

## Best Practices

1. **Pagination**: Always use pagination for list endpoints
2. **Filtering**: Use query parameters to reduce response size
3. **Caching**: Implement client-side caching for static data
4. **Error Handling**: Implement exponential backoff for retries
5. **Webhooks**: Use webhooks instead of polling for real-time updates
6. **Batch Operations**: Use bulk endpoints when available
7. **Field Selection**: Request only needed fields using `fields` parameter

## SDKs and Libraries

### PHP SDK

```php
$client = new MakeDealClient('your-api-key');

// Get leads
$leads = $client->leads()->list([
    'status' => 'qualified',
    'min_score' => 70
]);

// Create deal
$deal = $client->deals()->create([
    'name' => 'New Acquisition',
    'deal_value' => 50000000
]);
```

### JavaScript SDK

```javascript
const client = new MakeDealClient('your-api-key');

// Get pipeline overview
const pipeline = await client.pipeline.getOverview();

// Execute stage transition
const result = await client.deals.transition(dealId, {
  to_stage: 'screening',
  reason: 'Initial review complete'
});
```

## Changelog

### Version 1.0.0 (Current)
- Initial API release
- Full CRUD operations for all modules
- Pipeline automation endpoints
- Reporting and analytics
- Webhook support

---

For support, contact: api-support@makedealcrm.com