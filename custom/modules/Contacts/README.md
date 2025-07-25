# Stakeholder Tracking Backend Services

## Overview
This directory contains the backend services for stakeholder tracking and communication management in MakeDealCRM.

## Core Services

### 1. ContactRoleManager.php
Extended with last contact tracking methods:
- `updateLastContactDate()` - Updates when a contact was last contacted
- `getContactsNotContactedInDays()` - Find contacts needing follow-up
- `getContactCommunicationStats()` - Get communication statistics
- `getUpcomingFollowUps()` - Get contacts with scheduled follow-ups
- `setFollowUpReminder()` - Schedule follow-up reminders

### 2. StakeholderRelationshipService.php
Manages deal-contact relationships:
- `addStakeholderToDeal()` - Associate contact with deal
- `removeStakeholderFromDeal()` - Remove association
- `updateStakeholderRelationship()` - Update relationship details
- `getDealStakeholders()` - Get all stakeholders for a deal
- `getDealStakeholderSummary()` - Get summary statistics
- `getStakeholderCommunicationMatrix()` - Communication overview

### 3. EmailTemplateManager.php
Multi-party email templates:
- `generateIntroductionEmail()` - Multi-stakeholder introductions
- `generateFollowUpEmail()` - Follow-up templates
- `generateDealUpdateEmail()` - Deal update notifications
- Template types: introduction, follow_up, update, meeting_request, document_request, closing_coordination

### 4. CommunicationHistoryService.php
Activity tracking and reporting:
- `recordCommunication()` - Log any communication activity
- `getContactCommunicationHistory()` - Get activity history
- `getDealCommunicationSummary()` - Deal-wide communication stats
- Tracks: emails, calls, meetings, notes, tasks, documents

### 5. ContactsApi.php
REST API endpoints:
- `/api/rest/v11_20/Contacts/byRole/{role}` - Get contacts by role
- `/api/rest/v11_20/Contacts/{id}/role` - Update contact role
- `/api/rest/v11_20/Contacts/inactive/{days}` - Get inactive contacts
- `/api/rest/v11_20/Contacts/{id}/lastContact` - Update last contact
- `/api/rest/v11_20/Contacts/followups` - Get upcoming follow-ups
- `/api/rest/v11_20/Deals/{id}/stakeholders` - Manage deal stakeholders
- `/api/rest/v11_20/Contacts/{id}/communications` - Communication history
- `/api/rest/v11_20/Deals/{id}/emails/introduction` - Generate emails

### 6. Logic Hooks
Automatic tracking via hooks:
- `ContactActivityHooks.php` - Main hook handler
- Updates last contact date when activities are linked
- Validates contact roles on save
- Tracks communication from Emails, Calls, and Meetings modules

## Required Database Fields

The following custom fields are expected in the contacts_cstm table:
- `contact_role_c` - Contact role (seller, broker, attorney, etc.)
- `last_contact_date_c` - Last contact date
- `last_interaction_type_c` - Type of last interaction
- `follow_up_date_c` - Scheduled follow-up date
- `follow_up_notes_c` - Follow-up notes

## Usage Examples

### Get inactive contacts
```php
$inactiveContacts = ContactRoleManager::getContactsNotContactedInDays(30, 'seller');
```

### Add stakeholder to deal
```php
StakeholderRelationshipService::addStakeholderToDeal($dealId, $contactId, 'attorney');
```

### Generate introduction email
```php
$emailData = EmailTemplateManager::generateIntroductionEmail($dealId, [$contact1, $contact2, $contact3]);
```

### Record communication
```php
CommunicationHistoryService::recordCommunication($contactId, 'email', [
    'subject' => 'Deal Update',
    'body' => 'Updated terms...'
]);
```

## Integration Notes

- All services use SuiteCRM's database abstraction layer
- Logic hooks ensure automatic tracking without manual intervention
- API endpoints follow SuiteCRM REST API conventions
- Services are designed to work with existing SuiteCRM modules