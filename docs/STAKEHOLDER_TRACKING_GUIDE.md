# Stakeholder Tracking Module - User Guide

## Overview

The Stakeholder Tracking Module enhances SuiteCRM's contact management by adding role-based organization, communication tracking, and visual indicators for managing relationships in real estate deals.

## Key Features

### 1. Role-Based Contact Organization
- **5 Predefined Roles**: Seller, Broker, Attorney, Accountant, Lender
- **Visual Role Badges**: Color-coded badges for quick identification
- **Role-Based Filtering**: Filter contacts by their role in deals

### 2. Last Contact Tracking
- **Automatic Tracking**: System automatically updates last contact dates
- **Visual Indicators**:
  - 🟢 **Green**: Contacted within 7 days (Fresh)
  - 🟡 **Yellow**: Contacted 8-30 days ago (Warning)
  - 🔴 **Red**: No contact for 30+ days (Overdue)
  - ⚫ **Gray**: Never contacted (Inactive)

### 3. Multi-Party Email Templates
- **Introduction Templates**: Quickly introduce all parties in a deal
- **Follow-up Templates**: Automated follow-up email generation
- **Deal Update Templates**: Keep all stakeholders informed
- **Closing Coordination**: Coordinate closing activities

### 4. Communication History
- **Unified Timeline**: See all interactions in one place
- **Activity Types**: Emails, Calls, Meetings, Notes, Tasks
- **Deal Context**: Communications linked to specific deals

### 5. Pipeline Integration
- **Stakeholder Badges**: See stakeholder counts on deal cards
- **Quick Actions**: Add/manage stakeholders from pipeline view
- **Bulk Management**: Update multiple stakeholders at once

## Using the Module

### Adding Stakeholder Roles to Contacts

1. **Edit Contact**:
   - Navigate to any contact record
   - Click "Edit"
   - Find "Contact Role" dropdown
   - Select appropriate role (Seller, Broker, etc.)
   - Save

2. **Bulk Role Assignment**:
   - Go to Contacts list view
   - Select multiple contacts
   - Choose "Mass Update"
   - Set Contact Role field
   - Apply changes

### Managing Stakeholders in Deals

1. **From Deal View**:
   - Open any deal record
   - Scroll to "Stakeholders" subpanel
   - Click "Select" to add existing contacts
   - Use "Create" for new stakeholder contacts

2. **From Pipeline View**:
   - Click stakeholder badge on deal card
   - Select "Add Stakeholder"
   - Search and select contact
   - Assign role if needed

3. **Bulk Stakeholder Management**:
   - In pipeline view, click "Manage Stakeholders"
   - Select multiple deals
   - Add or remove stakeholders in bulk
   - Update roles across deals

### Using Email Templates

1. **Send Introduction Email**:
   - Open deal with multiple stakeholders
   - Click "Introduce" button in stakeholder panel
   - Review generated email
   - Customize message if needed
   - Send to all parties

2. **Quick Follow-up**:
   - Hover over contact in stakeholder list
   - Click email icon
   - Select "Follow-up" template
   - Email opens with pre-filled content

### Tracking Communication

1. **View Contact History**:
   - Open contact record
   - Check "Last Contacted" badge
   - Click "Communications" tab
   - See full timeline of interactions

2. **Log Manual Communications**:
   - Use "Log Call" or "Create Note"
   - System automatically updates last contact date
   - Communication appears in timeline

### Finding Inactive Contacts

1. **Using Filters**:
   - Go to Contacts list view
   - Click "Advanced Search"
   - Filter by "Days Since Last Contact"
   - Export results if needed

2. **Dashboard Widget** (if configured):
   - View "Inactive Stakeholders" dashlet
   - See contacts needing follow-up
   - Click to view full list

## Best Practices

### 1. Regular Contact Maintenance
- Review red-badge contacts weekly
- Set follow-up reminders for important stakeholders
- Use bulk email for regular updates

### 2. Role Assignment
- Assign roles when creating contacts
- Keep roles updated as relationships change
- Use consistent role assignments

### 3. Deal Organization
- Add all stakeholders to deals early
- Use primary/secondary designations
- Keep stakeholder lists current

### 4. Communication Tracking
- Log all significant communications
- Use templates for consistency
- Include deal context in notes

## Troubleshooting

### Common Issues

1. **Missing Role Options**:
   - Clear cache: Admin → Repair → Quick Repair
   - Check user permissions for custom fields

2. **Badges Not Showing**:
   - Ensure JavaScript files are loaded
   - Clear browser cache
   - Check console for errors

3. **Email Templates Not Working**:
   - Verify email configuration
   - Check template permissions
   - Ensure contacts have email addresses

### Database Migration

If features are missing, run the migration:

```bash
cd custom/modules/Contacts/sql
./run_migration.sh all
```

Then rebuild:
- Admin → Repair → Quick Repair and Rebuild
- Admin → Repair → Rebuild Relationships

## Advanced Features

### API Integration

The module provides REST API endpoints for integration:

```
GET /rest/v10/Contacts/stakeholder/roles
GET /rest/v10/Contacts/stakeholder/by-role/{role}
GET /rest/v10/Deals/{id}/stakeholders
POST /rest/v10/Deals/{id}/stakeholders
PUT /rest/v10/Deals/{id}/stakeholders/{contact_id}
DELETE /rest/v10/Deals/{id}/stakeholders/{contact_id}
```

### Custom Development

Extend the module by:
- Adding custom roles in `ContactRoleManager.php`
- Creating new email templates
- Building custom reports
- Adding workflow automation

## Security Considerations

- Role assignments require Contact edit permissions
- Bulk operations require admin or group permissions
- Communication history respects record-level security
- Email templates sanitize user input

## Performance Tips

- Use indexed searches for large datasets
- Enable caching for stakeholder queries
- Limit bulk operations to 50 records
- Schedule maintenance jobs during off-hours

## Support

For issues or questions:
1. Check test suite: `/test_stakeholder_tracking.php`
2. Review logs in `suitecrm.log`
3. Contact system administrator
4. Submit issues to development team

---

*Version 1.0 - Stakeholder Tracking Module for MakeDealCRM*