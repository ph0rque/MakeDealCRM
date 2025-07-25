# Stakeholder Tracking Database Schema

## Overview

This comprehensive stakeholder tracking system extends MakeDealCRM's contact management capabilities with advanced tracking, communication history, and multi-party relationship management features.

## Architecture Components

### 1. Core Schema Extensions (`001_add_stakeholder_tracking_fields.sql`)

Extends the `contacts_cstm` table with:

- **Last Contact Tracking**: Captures date, type, outcome, and user who made contact
- **Contact Frequency**: Expected frequency and next scheduled contact
- **Stakeholder Classification**: Status, tier (Critical/Important/Standard), and engagement level
- **Deal Metrics**: Active/completed deal counts and total deal value
- **Relationship Scores**: Calculated scores for relationship health, influence, and responsiveness
- **Multi-party Introduction Support**: Template management and success tracking

### 2. Communication History System (`002_create_communication_history_tables.sql`)

New tables for comprehensive communication tracking:

- **`contact_communication_history`**: Main communication log with full details
- **`communication_participants`**: Multi-party communication tracking
- **`communication_attachments`**: Document management for communications
- **`communication_templates`**: Email templates for introductions and follow-ups
- **`contact_communication_analytics`**: Pre-calculated analytics for performance

### 3. Enhanced Deal Relationships (`003_enhance_deals_contacts_relationship.sql`)

Extends `deals_contacts` with:

- **Deal-specific Roles**: Role can differ from general contact role
- **Involvement Metrics**: Level of involvement and decision-making authority
- **Team Organization**: Stakeholder teams (buyer/seller/advisor teams)
- **Introduction Tracking**: Who introduced whom and when
- **Commission Tracking**: For brokers and intermediaries

### 4. Integration Layer (`004_create_stakeholder_integration_views.sql`)

Views and functions for reporting:

- **`v_stakeholder_dashboard`**: Comprehensive stakeholder overview
- **`v_deal_stakeholder_matrix`**: Deal-centric stakeholder analysis
- **`v_communication_effectiveness`**: Communication pattern analysis
- **`calculate_stakeholder_health_score()`**: 0-100 health score calculation
- **`get_stakeholder_next_action()`**: AI-like recommendation engine

## Key Features

### 1. Automated Tracking

- **Triggers** automatically update days since contact
- **Events** run daily to update metrics and flag at-risk relationships
- **Stored procedures** maintain analytics tables

### 2. Multi-Party Communication

- Track emails/meetings with multiple participants
- Manage introduction templates
- Track introduction success rates
- Support for CC/BCC tracking

### 3. Relationship Intelligence

- Automatic scoring based on:
  - Communication frequency
  - Response rates
  - Engagement levels
  - Deal involvement
- Next action recommendations
- At-risk stakeholder identification

### 4. Deal Team Management

- Organize stakeholders into teams (buyer/seller/advisor)
- Track team dynamics
- Monitor team communication patterns

## Installation Guide

### Prerequisites

- MySQL 5.7+ or MariaDB 10.2+
- Database user with CREATE, ALTER, TRIGGER, EVENT privileges
- Event scheduler enabled (`SET GLOBAL event_scheduler = ON`)

### Installation Steps

1. **Backup your database** before running migrations

2. **Run the master migration script**:
   ```sql
   mysql -u root -p your_database < 000_master_stakeholder_migration.sql
   ```

3. **Or run individual migrations**:
   ```sql
   mysql -u root -p your_database < 001_add_stakeholder_tracking_fields.sql
   mysql -u root -p your_database < 002_create_communication_history_tables.sql
   mysql -u root -p your_database < 003_enhance_deals_contacts_relationship.sql
   mysql -u root -p your_database < 004_create_stakeholder_integration_views.sql
   ```

4. **Verify installation**:
   ```sql
   SELECT * FROM stakeholder_migrations;
   ```

### Rollback

If needed, a complete rollback script is included at the bottom of `000_master_stakeholder_migration.sql`.

## Usage Examples

### 1. Log a Communication

```sql
INSERT INTO contact_communication_history (
    id, contact_id, communication_type, communication_direction,
    communication_date, subject, description, initiated_by,
    outcome, response_received
) VALUES (
    UUID(), 'contact-id-here', 'email', 'outbound',
    NOW(), 'Follow-up on deal terms', 'Discussed pricing adjustments...',
    'user-id-here', 'positive', 1
);
```

### 2. Check Stakeholder Health

```sql
SELECT 
    CONCAT(first_name, ' ', last_name) as name,
    calculate_stakeholder_health_score(id) as health_score,
    get_stakeholder_next_action(id) as recommended_action
FROM contacts
WHERE id = 'contact-id-here';
```

### 3. Get At-Risk Stakeholders

```sql
SELECT * FROM v_stakeholder_dashboard
WHERE contact_status = 'Very Overdue'
AND active_deals_count > 0
ORDER BY days_since_contact DESC;
```

### 4. Analyze Deal Stakeholders

```sql
SELECT * FROM v_deal_stakeholder_matrix
WHERE deal_id = 'deal-id-here';
```

## Integration with ContactRoleManager

The schema builds upon the existing `ContactRoleManager` class:

1. **Extends** the existing `contact_role_c` field system
2. **Maintains** compatibility with current role constants
3. **Adds** deal-specific role tracking
4. **Enhances** with stakeholder classification

## Performance Considerations

1. **Indexes**: All foreign keys and commonly queried fields are indexed
2. **Materialized Analytics**: Pre-calculated analytics table reduces query load
3. **Daily Updates**: Events run during off-peak hours (2 AM)
4. **Triggers**: Lightweight operations to maintain data consistency

## Maintenance

### Daily Tasks (Automated)
- Update days since contact
- Recalculate relationship scores
- Flag at-risk stakeholders

### Weekly Tasks (Manual)
- Review stakeholder health reports
- Update next contact dates
- Clean up old communication history (if needed)

### Monthly Tasks
- Analyze communication effectiveness
- Review and update email templates
- Audit stakeholder classifications

## Security Considerations

1. **Row-level Security**: Queries should filter by `assigned_user_id`
2. **Audit Trail**: All changes tracked in audit tables
3. **Data Privacy**: PII fields should be encrypted at rest
4. **Access Control**: Use database roles to limit access

## Future Enhancements

1. **AI Integration**: Sentiment analysis for communications
2. **Email Integration**: Auto-import from email servers
3. **Calendar Sync**: Two-way calendar integration
4. **Mobile App**: Stakeholder tracking on the go
5. **Advanced Analytics**: Predictive modeling for relationship health

## Support

For issues or questions:
1. Check migration status: `SELECT * FROM stakeholder_migrations`
2. Review error logs in `error_message` column
3. Ensure event scheduler is running: `SHOW PROCESSLIST`
4. Verify triggers are active: `SHOW TRIGGERS`