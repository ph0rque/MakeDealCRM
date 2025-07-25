# Email Integration and Auto-Processing System

## Overview

The Email Integration system automatically processes emails forwarded to `deals@mycrm`, extracting deal information, contacts, and attachments to create or update deals in the CRM.

## Key Features

### 1. Intelligent Email Parsing
- **Multi-format Support**: Processes HTML, plain text, and rich text emails
- **Data Extraction**: Automatically extracts:
  - Deal name and description
  - Financial metrics (revenue, EBITDA, asking price)
  - Company information
  - Industry classification
  - Contact details with role identification

### 2. Advanced Duplicate Detection
- **Fuzzy Matching Algorithm**: Uses similarity scoring (70% threshold)
- **Multi-field Comparison**: Checks deal name, company name, and amount
- **Time-based Matching**: Considers deals created within 7 days
- **Smart Updates**: Updates existing deals with new information

### 3. Contact Extraction & Management
- **Multiple Sources**:
  - Email headers (From, To, CC)
  - Email signatures
  - Body content with role keywords
- **Role Assignment**: Automatically identifies:
  - Seller/Owner
  - Broker/Agent
  - Attorney/Lawyer
  - Accountant/CPA
  - Buyer/Investor
- **Deduplication**: Prevents duplicate contact creation

### 4. Email Thread Tracking
- **Conversation Threading**: Links related emails using:
  - Message-ID headers
  - In-Reply-To headers
  - Subject similarity
- **Deal Association**: Maintains email history per deal
- **Thread Visualization**: Shows complete conversation timeline

### 5. Attachment Processing
- **Automatic Linking**: Attachments linked to created/updated deals
- **Type Filtering**: Supports common business documents
- **Size Limits**: Configurable max attachment size (default 10MB)

### 6. Error Handling & Reliability
- **Retry Mechanism**: 3 attempts with 5-second delays
- **Failure Notifications**: Alerts administrators of processing failures
- **Activity Logging**: Complete audit trail of all processing
- **Quarantine System**: Failed emails marked for manual review

## Configuration

### Email Forwarding Setup

1. Configure your email server to forward emails to `deals@mycrm`
2. Or set up an email alias that redirects to your CRM's inbound email processor

### Configuration File

Edit `/custom/modules/Deals/config/email_config.php`:

```php
$deals_email_config = array(
    'monitor_address' => 'deals@mycrm',
    'processing' => array(
        'enabled' => true,
        'retry_attempts' => 3,
        'retry_delay' => 5,
        'max_email_age' => 30,
    ),
    'duplicate_detection' => array(
        'enabled' => true,
        'similarity_threshold' => 0.7,
    ),
    // ... more settings
);
```

## Email Format Guidelines

### Basic Format
```
Subject: [Deal Name/Description]

Company: ABC Manufacturing Inc.
Industry: Manufacturing
Asking Price: $2.5M
Revenue: $5M annually
EBITDA: $750K

Contact Information:
Seller: John Doe - john@example.com
Broker: Jane Smith - jane@broker.com - (555) 123-4567
```

### Supported Data Fields

| Field | Keywords | Example |
|-------|----------|---------|
| Company | Company, Business, Firm | Company: ABC Corp |
| Industry | Industry, Sector, Market | Industry: Technology |
| Asking Price | Asking Price, Price, Valuation | Price: $2.5M |
| Revenue | Revenue, Sales, Income | Revenue: $5M |
| EBITDA | EBITDA, Earnings | EBITDA: $750K |

### Amount Formats
- Full numbers: `$2,500,000` or `$2500000`
- Abbreviated: `$2.5M` or `$2.5m` (M/K suffixes supported)
- Text format: `2.5 million` (parsed correctly)

## System Architecture

### Components

1. **EmailParser.php**
   - Extracts structured data from email content
   - Uses regex patterns for data identification
   - Normalizes extracted values

2. **EmailProcessor.php**
   - Orchestrates the processing workflow
   - Creates/updates deals
   - Manages contacts and relationships
   - Handles attachments

3. **EmailThreadTracker.php**
   - Tracks email conversations
   - Links emails to deals
   - Maintains conversation history

4. **DealsEmailLogicHook.php**
   - Triggers processing on email save
   - Implements retry logic
   - Sends notifications

### Processing Flow

```
Email Received → Logic Hook Triggered → Parser Extracts Data
       ↓                                        ↓
Check Duplicate ← Thread Tracking ← Process Contacts
       ↓                                        ↓
Create/Update Deal ← Link Attachments ← Send Notifications
```

## Usage Examples

### Example 1: New Deal from Broker
```
Subject: Exclusive Listing - Tech Startup for Sale

Hi team,

I have an exciting opportunity:

Company: InnovateTech Solutions
Industry: Software/SaaS
Annual Revenue: $3.2M
EBITDA: $800K
Asking Price: $4.5M (multiple of 5.6x)

The owner (Mike Johnson - mike@innovatetech.com) is looking to exit 
within 6 months. 

Please review and let me know if interested.

Best regards,
Sarah Williams
Senior Business Broker
sarah@premierbrokerage.com
(555) 987-6543
```

**Result**: Creates deal with all financial metrics, links broker and owner contacts

### Example 2: Deal Update Email
```
Subject: Re: InnovateTech Solutions - Updated Financials

Following up with updated numbers:

Revenue has increased to $3.5M (TTM)
EBITDA now at $900K
Adjusted asking price: $5M

Also, their attorney is:
David Chen, Esq.
dchen@lawfirm.com
```

**Result**: Updates existing deal, adds attorney contact

## API Integration

### REST Endpoints

```
POST /rest/v10/Deals/processEmail
{
    "email_id": "abc-123-def",
    "force_process": true
}

GET /rest/v10/Deals/{id}/emailThreads
Returns all email threads for a deal

GET /rest/v10/Emails/dealsQueue
Returns pending emails for processing
```

## Troubleshooting

### Common Issues

1. **Emails Not Processing**
   - Check email is sent to `deals@mycrm`
   - Verify logic hooks are registered
   - Check error logs in `suitecrm.log`

2. **Incorrect Data Extraction**
   - Review email format matches guidelines
   - Check regex patterns in `EmailParser.php`
   - Enable debug logging

3. **Duplicate Deals Created**
   - Adjust similarity threshold
   - Increase check window period
   - Review duplicate detection logic

### Debug Mode

Enable debug logging:
```php
// In email_config.php
'debug' => true,
'debug_file' => 'cache/logs/email_processing.log'
```

### Testing

Run test suite:
```bash
php custom/modules/Deals/test_email_processing.php
```

## Performance Optimization

### Best Practices

1. **Batch Processing**: Process emails in batches during off-peak hours
2. **Caching**: Parser caches regex patterns and calculations
3. **Indexes**: Ensure database indexes on:
   - `emails.message_id`
   - `email_thread_deals.thread_id`
   - `opportunities.name`

### Scaling Considerations

- **Background Processing**: Use job queue for large volumes
- **Parallel Processing**: Configure multiple workers
- **Archive Old Threads**: Move old thread data to archive tables

## Security Considerations

1. **Input Sanitization**: All extracted data is sanitized
2. **ACL Compliance**: Respects SuiteCRM access controls
3. **Attachment Scanning**: Option to scan attachments
4. **Email Authentication**: Verify sender authenticity

## Maintenance

### Regular Tasks

1. **Monitor Processing Queue**: Check for stuck emails weekly
2. **Clean Error Logs**: Archive old error logs monthly
3. **Update Patterns**: Review and update extraction patterns quarterly
4. **Performance Review**: Analyze processing times monthly

### Database Maintenance

```sql
-- Clean old thread data (>90 days)
DELETE FROM email_thread_deals 
WHERE date_sent < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- Optimize tables
OPTIMIZE TABLE email_thread_deals;
```

## Future Enhancements

1. **Machine Learning**: Improve extraction accuracy with ML
2. **OCR Support**: Extract data from PDF attachments
3. **Multi-language**: Support for non-English emails
4. **Smart Routing**: Auto-assign based on deal characteristics
5. **Integration Hub**: Connect with other email sources

## Support

For issues or questions:
1. Check test results: `/test_email_processing.php`
2. Review logs: `cache/logs/email_processing.log`
3. Enable debug mode for detailed tracing
4. Contact system administrator

---

*Version 1.0 - Email Integration System for MakeDealCRM*