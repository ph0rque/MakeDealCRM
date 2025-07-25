# Pipeline Architecture Documentation

## Overview

This directory contains the complete architectural design for the Unified Deal & Portfolio Pipeline feature for SuiteCRM's Deals module. The pipeline provides a Kanban-style visualization with drag-and-drop functionality, automatic time-tracking, and WIP limit management.

## Documentation Structure

### 1. [Pipeline Architecture](pipeline-architecture.md)
Comprehensive overview of the entire pipeline system including:
- Database schema design
- Module architecture
- Core components
- Integration points
- Performance and security considerations

### 2. [Database Schema](database-schema.sql)
Complete SQL schema including:
- New tables for pipeline functionality
- Modifications to existing deals table
- Indexes for performance
- Views for common queries
- Stored procedures and triggers
- Migration scripts

### 3. [UI/UX Design](ui-ux-design.md)
Detailed design specifications including:
- Visual design system and color palette
- Component specifications (cards, columns, drag-drop)
- Responsive design breakpoints
- Animations and transitions
- Accessibility features
- Performance optimizations

### 4. [Integration Plan](integration-plan.md)
Strategy for integrating with existing Deals module:
- Module extension approach
- Database backward compatibility
- Menu and navigation integration
- API endpoints
- ACL and security
- Workflow automation
- Testing strategy

### 5. [API Specification](api-specification.md)
Complete REST API documentation including:
- All endpoints with request/response examples
- Authentication and authorization
- Error handling
- Rate limiting
- WebSocket events for real-time updates
- Webhook configuration

## Key Features

### Pipeline Stages (11 Predefined)
1. **Sourcing** - Initial deal identification
2. **Initial Outreach** - First contact made
3. **Qualified** - Deal meets criteria (WIP: 15)
4. **Meeting Scheduled** - Initial meeting set (WIP: 10)
5. **NDA Executed** - Confidentiality agreement signed (WIP: 10)
6. **Under Review** - Detailed analysis (WIP: 8)
7. **LOI Negotiations** - Letter of Intent discussions (WIP: 5)
8. **Under LOI** - LOI executed (WIP: 5)
9. **Due Diligence** - Final verification (WIP: 3)
10. **Closed** - Deal completed (Terminal)
11. **Unavailable** - Deal lost/unavailable (Terminal)

### Core Functionality
- **Drag & Drop**: Move deals between stages with visual feedback
- **WIP Limits**: Configurable limits per stage with override capability
- **Time Tracking**: Automatic tracking with visual indicators
  - Normal: 0-13 days (default)
  - Warning: 14-29 days (orange)
  - Critical: 30+ days (red)
- **Focus Deals**: Flag important deals with ⚡ indicator
- **Responsive Design**: Desktop, tablet, and mobile layouts
- **Real-time Updates**: WebSocket support for collaboration

## Technical Stack

- **Backend**: PHP 7.4+ with SuiteCRM framework
- **Database**: MySQL 5.7+ with InnoDB
- **Frontend**: JavaScript ES6+, CSS3
- **APIs**: RESTful with JSON responses
- **Real-time**: WebSockets for live updates

## Implementation Approach

1. **Non-Disruptive**: Extends existing Deals module without breaking changes
2. **Progressive Enhancement**: Pipeline view is optional, classic views remain
3. **Performance First**: Optimized queries, indexes, and caching
4. **Mobile Ready**: Touch-optimized interface for tablets and phones
5. **Accessible**: WCAG 2.1 AA compliance with keyboard navigation

## Security Considerations

- Row-level security for deal visibility
- Role-based permissions for stage transitions
- Audit logging for all movements
- WIP override requires special permission
- API rate limiting and authentication

## Performance Targets

- Page load: < 2 seconds for 1000 deals
- Drag operation: < 100ms visual feedback
- API response: < 500ms for standard operations
- Real-time updates: < 1 second latency

## Migration Path

1. Deploy database schema (non-destructive)
2. Run migration script to populate pipeline_stage
3. Deploy code updates
4. Enable pipeline view in menu
5. Train users on new interface
6. Monitor and optimize based on usage

## Future Enhancements

- Custom stage configuration
- AI-powered deal prioritization
- Predictive analytics
- Advanced automation rules
- Native mobile applications

## Getting Started

1. Review the architecture document for system overview
2. Execute database schema in development environment
3. Follow integration plan for code deployment
4. Use API specification for custom integrations
5. Reference UI/UX design for frontend implementation

## Support

For questions or clarifications about this architecture:
- Review the individual documents for detailed information
- Check the API specification for integration details
- Consult the integration plan for deployment steps