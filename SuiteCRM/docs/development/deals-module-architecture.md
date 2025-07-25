# Deals Module Architecture

## Overview

The Deals module serves as the central object in MakeDealCRM, replacing both Opportunities and Leads modules to provide a unified acquisition pipeline for solo deal makers.

## Module Structure

### Core Files

1. **Bean Class** (`Deal.php`)
   - Extends Basic template
   - Implements business logic for deal tracking
   - Handles financial calculations and at-risk status

2. **Field Definitions** (`vardefs.php`)
   - Basic fields: name, status, source, deal_value
   - Financial fields: asking_price, ttm_revenue, ttm_ebitda, sde, valuation, multiple
   - Capital stack: equity, senior_debt, seller_note
   - Relationships with Contacts, Documents, Tasks, Notes

3. **Views**
   - **Edit View**: Custom duplicate checking with AJAX
   - **Detail View**: Stage progress bar, quick actions, activity timeline
   - **List View**: Summary statistics, bulk operations

## Key Features

### 1. Duplicate Prevention
- Real-time AJAX checking on name/email changes
- Fuzzy matching algorithm
- Scoring system for confidence levels
- Merge options for duplicates

### 2. Email Integration
- Logic hooks for email parsing
- Automatic deal association
- Contact extraction from emails
- Attachment handling

### 3. Financial Calculations
- Automatic valuation based on EBITDA × multiple
- Capital stack totaling
- At-risk status based on days in stage

### 4. Deal Stages
1. Sourcing
2. Initial Contact
3. NDA Signed
4. Information Received
5. Initial Analysis
6. LOI Submitted
7. LOI Accepted
8. Due Diligence
9. Final Negotiation
10. Closed Won
11. Closed Lost

## Relationships

- **Contacts**: One-to-many with role tracking
- **Documents**: Direct file attachments
- **Tasks**: Due diligence checklist items
- **Notes**: Deal annotations
- **Emails**: Automated capture

## Security

- Field-level ACL for financial data
- Owner-only access to sensitive fields
- Audit trail for all changes

## Performance Optimizations

- Database indices on key fields
- Caching for pipeline metrics
- Lazy loading for related records