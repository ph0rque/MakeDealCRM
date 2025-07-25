# Stakeholder Tracking System - Technical Specification

## Current System Analysis

### Existing Implementation

1. **ContactRoleManager Class** (`/custom/modules/Contacts/ContactRoleManager.php`)
   - Provides centralized role management
   - Defines 5 predefined roles: seller, broker, attorney, accountant, lender
   - Methods available:
     - `getAllRoles()` - Returns role array
     - `isValidRole($role)` - Validates role
     - `getRoleDisplayName($role)` - Gets display name
     - `getContactsByRole($role)` - Retrieves contacts by role
     - `getRoleStatistics()` - Provides role counts
     - `updateContactRole($contactId, $role)` - Updates single contact
     - `bulkUpdateContactRoles($contactIds, $role)` - Bulk updates
     - `getContactsForDealByRole($dealId, $role = null)` - Deal-specific contacts

2. **Database Structure**
   - Custom field `contact_role_c` in contacts table (VARCHAR 50)
   - Field definition in `/custom/Extension/modules/Contacts/Ext/Vardefs/contact_role_field.php`
   - Dropdown values in `/custom/Extension/application/Ext/Language/en_us.contact_role.php`

3. **UI Integration**
   - Contact role field visible in list view (10% width)
   - Field integrated in detail/edit views
   - Searchable in advanced search

4. **Deals-Contacts Relationship**
   - Many-to-many relationship via `deals_contacts` join table
   - Join keys: `deal_id` and `contact_id`
   - Contacts subpanel configured in Deals module
   - Default subpanel view with QuickCreate and MultiSelect buttons

## Gap Analysis

### Missing Features for Stakeholder Tracking

1. **Last Contact Tracking**
   - No current field for tracking last contact date per stakeholder
   - No automated update mechanism when activities are logged
   - No visual indicators for contact frequency

2. **Email Templates Integration**
   - No role-specific email template associations
   - No quick email buttons in contacts subpanel
   - No template selection based on contact role

3. **Visual Badges/Indicators**
   - Contact roles displayed as plain text
   - No color coding or visual differentiation
   - No status indicators for contact frequency

4. **Subpanel Enhancements**
   - Default subpanel doesn't show contact role
   - No last contact date visible
   - No quick action buttons for role-specific actions

## Proposed Implementation

### 1. Database Schema Extensions

```sql
-- Add last contact tracking fields to contacts table
ALTER TABLE contacts_cstm 
ADD COLUMN last_contact_date_c DATETIME,
ADD COLUMN days_since_contact_c INT(11);

-- Add email template associations
CREATE TABLE contact_role_email_templates (
    id CHAR(36) NOT NULL PRIMARY KEY,
    role VARCHAR(50) NOT NULL,
    template_id CHAR(36) NOT NULL,
    template_name VARCHAR(255),
    template_type VARCHAR(50), -- 'initial', 'followup', 'reminder'
    date_entered DATETIME,
    date_modified DATETIME,
    deleted TINYINT(1) DEFAULT 0,
    KEY idx_role (role),
    KEY idx_template (template_id)
);
```

### 2. Extended ContactRoleManager Methods

```php
class ContactRoleManager {
    // Existing methods...
    
    // New methods for last contact tracking
    public static function updateLastContactDate($contactId);
    public static function calculateDaysSinceContact($contactId);
    public static function getContactsNeedingFollowup($role = null, $days = 30);
    
    // Email template management
    public static function getEmailTemplatesForRole($role);
    public static function assignEmailTemplateToRole($role, $templateId, $type);
    public static function getDefaultTemplateForRole($role, $type = 'initial');
    
    // Enhanced display methods
    public static function getRoleBadgeHtml($role);
    public static function getContactFrequencyIndicator($daysSinceContact);
}
```

### 3. Custom Subpanel Implementation

Create `/custom/modules/Contacts/metadata/subpanels/ForDealsStakeholders.php`:
- Display contact role with color-coded badges
- Show last contact date and days since contact
- Add quick email buttons per role
- Include visual indicators for follow-up needs

### 4. Logic Hooks Implementation

```php
// After Save hook for Activities (Calls, Meetings, Emails)
class UpdateLastContactDate {
    function updateContactDate($bean, $event, $arguments) {
        // Update last_contact_date_c when activity is linked to contact
    }
}

// Scheduler job for updating days_since_contact_c
class ContactFrequencyUpdater {
    function updateDaysSinceContact() {
        // Daily job to calculate and update days since last contact
    }
}
```

### 5. UI Components

#### A. Role Badge System
```php
// Color mapping for roles
$roleBadgeColors = array(
    'seller' => '#FF6B6B',      // Red
    'broker' => '#4ECDC4',      // Teal
    'attorney' => '#45B7D1',    // Blue
    'accountant' => '#96CEB4',  // Green
    'lender' => '#FECA57',      // Yellow
);
```

#### B. Contact Frequency Indicators
- Green: Contact within 7 days
- Yellow: Contact within 8-30 days
- Red: No contact for 30+ days
- Gray: No contact recorded

### 6. Email Integration

```php
// Quick email button handler
class StakeholderEmailHandler {
    function sendRoleBasedEmail($contactId, $dealId, $templateType) {
        // Load role-specific template
        // Populate with contact and deal data
        // Open compose window
    }
}
```

## Implementation Phases

### Phase 1: Database & Backend (Week 1)
1. Create database schema extensions
2. Extend ContactRoleManager class
3. Implement logic hooks for activity tracking
4. Create scheduler job for days calculation

### Phase 2: UI Enhancement (Week 2)
1. Create custom subpanel layout
2. Implement role badge system
3. Add contact frequency indicators
4. Integrate visual elements in list/detail views

### Phase 3: Email Integration (Week 3)
1. Create email template management UI
2. Implement quick email buttons
3. Add template selection logic
4. Test email workflows

### Phase 4: Testing & Polish (Week 4)
1. Unit tests for ContactRoleManager extensions
2. UI testing across browsers
3. Performance optimization
4. Documentation updates

## Integration Points

1. **Activities Module**: Hook into Calls, Meetings, Emails for contact tracking
2. **Email Templates Module**: Extend for role-based associations
3. **Scheduler**: Add job for daily contact frequency updates
4. **Deals Module**: Enhanced contacts subpanel
5. **Dashlets**: Optional dashboard widgets for follow-up reminders

## Performance Considerations

1. Index `last_contact_date_c` and `days_since_contact_c` fields
2. Cache role statistics and badge HTML
3. Optimize subpanel queries with proper joins
4. Limit activity lookups to recent records (last 90 days)

## Security Considerations

1. Maintain existing ACL controls on contacts
2. Role-based template access (admin configurable)
3. Audit trail for contact updates
4. Sanitize all HTML output for badges

## Migration Strategy

1. Backup existing data
2. Run ALTER TABLE statements
3. Deploy new PHP classes
4. Clear cache and rebuild
5. Run initial population of last_contact_date_c from activity history
6. Configure email templates per role

## Success Metrics

1. All contacts display role badges in subpanels
2. Last contact dates auto-update from activities
3. Email templates accessible via quick buttons
4. Visual indicators accurately reflect contact frequency
5. System performs within acceptable response times (<2s for subpanel load)