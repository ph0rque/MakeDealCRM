# SuiteCRM Core Architecture Overview

## Introduction

SuiteCRM is a powerful open-source Customer Relationship Management (CRM) system built on a robust PHP-based Model-View-Controller (MVC) architecture. Originally forked from SugarCRM Community Edition, it has evolved into a comprehensive enterprise-grade solution with extensive customization capabilities.

## Architecture Foundation

### Technology Stack

| Component | Technology | Version | Purpose |
|-----------|------------|---------|---------|
| Backend Framework | PHP | 7.4+ | Server-side application logic |
| Templating Engine | Smarty | 4.x | Dynamic HTML generation |
| ORM | SugarBean | Custom | Data persistence and relationships |
| Frontend Framework | Bootstrap | 3.3.7 | Responsive UI components |
| Theme System | SuiteP | Custom | Customizable visual presentation |
| Search Engine | Elasticsearch | 7.x | Advanced search capabilities |
| Email Processing | PHPMailer | 6.x | Email composition and delivery |
| API Framework | Slim Framework | 3.x | RESTful web services |
| Authentication | OAuth2 Server | Standard | Secure API access |

### Core Design Principles

1. **Modularity**: Business logic is organized into discrete, self-contained modules
2. **Extensibility**: Clear separation between core and custom code through the `custom/` directory
3. **Scalability**: Supports enterprise-grade deployments with caching and optimization
4. **Maintainability**: MVC pattern ensures clear separation of concerns
5. **Upgrade Safety**: Customizations preserved during system updates

## System Architecture

### Request Processing Flow

```
HTTP Request → index.php → Application Bootstrap → Router → Module Controller → Business Logic → View Rendering → HTTP Response
```

### Key Components

#### 1. Entry Point (index.php)
- Initializes the application
- Loads configuration
- Sets up error handling
- Bootstraps the MVC framework

#### 2. Configuration Management
- **Primary**: `config.php` - Main configuration array (`$sugar_config`)
- **Override**: `config_override.php` - Custom configuration overrides
- **Utilities**: `include/utils.php` - Configuration helper functions
- **Caching**: Runtime configuration caching for performance

#### 3. MVC Implementation
- **Model**: SugarBean classes for data representation
- **View**: Smarty templates and view classes
- **Controller**: Module controllers handling request routing

#### 4. Data Layer (SugarBean ORM)
The `SugarBean` class serves as the foundation for all business objects:
- Object-Relational Mapping (ORM)
- Relationship management
- Database abstraction
- Query building
- Result caching

## Directory Structure

```
SuiteCRM/
├── Api/                    # REST API implementation
│   ├── Core/              # Core API functionality
│   └── V8/                # Version 8 API
├── cache/                 # Runtime cache and compiled templates
├── custom/                # All customizations (preserved on upgrade)
│   ├── Extension/         # Module extensions
│   ├── modules/           # Custom module overrides
│   └── include/           # Custom includes
├── data/                  # Data layer classes
│   ├── SugarBean.php      # Base ORM class
│   └── Relationships/     # Relationship definitions
├── include/               # Core framework classes
│   ├── MVC/              # MVC framework
│   ├── SugarFields/      # Field type handlers
│   └── utils/            # Utility functions
├── modules/               # Business modules
├── themes/                # UI themes
├── upload/                # User uploaded files
└── vendor/                # Third-party dependencies
```

## Module Architecture

Modules are the building blocks of SuiteCRM functionality:

### Module Categories

1. **Core Modules**
   - Users, ACL, Administration
   - Accounts, Contacts, Leads
   - Essential CRM entities

2. **Communication Modules**
   - Emails, Email Templates
   - Calls, Meetings
   - Campaigns

3. **Business Process Modules**
   - Opportunities, Cases
   - Projects, Workflows
   - Quotes, Invoices

4. **Analytics Modules**
   - Reports (AOR)
   - Charts, Dashboards
   - Business Intelligence

### Module Structure

Each module follows a standard structure:
```
modules/[ModuleName]/
├── [ModuleName].php       # Main bean class
├── vardefs.php            # Field definitions
├── Menu.php               # Module menu
├── controller.php         # Module controller
├── views/                 # View classes
├── metadata/              # Metadata definitions
└── language/              # Language files
```

## Caching Strategy

SuiteCRM implements multiple caching layers:

1. **File Cache**: Default caching mechanism
2. **APC/APCu**: In-memory caching for PHP
3. **Memcached**: Distributed memory caching
4. **Redis**: Advanced key-value caching
5. **Smarty Template Cache**: Compiled template caching

## Security Architecture

### Authentication
- Session-based authentication for web interface
- OAuth2 for API access
- LDAP/SAML integration support

### Authorization
- Role-based access control (ACL)
- Field-level security
- Record-level security through Security Groups

### Data Protection
- Input validation and sanitization
- SQL injection prevention through ORM
- XSS protection in templates
- CSRF token validation

## Performance Optimization

1. **Database Optimization**
   - Query caching
   - Index optimization
   - Connection pooling

2. **Code Optimization**
   - Opcode caching (OPcache)
   - Lazy loading
   - Minified JavaScript/CSS

3. **Architecture Optimization**
   - Asynchronous job queue
   - Background processing
   - CDN support for static assets

## Integration Points

### REST API (V8)
- Full CRUD operations
- OAuth2 authentication
- JSON API specification compliant
- Swagger documentation

### Web Services
- SOAP API (legacy)
- Custom entry points
- Webhook support

### External Integrations
- Email server integration (IMAP/SMTP)
- Calendar synchronization
- Document management systems
- Third-party application connectors

## Development Best Practices

1. **Customization Guidelines**
   - Always use the `custom/` directory
   - Extend rather than modify core files
   - Use Module Builder for new modules
   - Follow upgrade-safe practices

2. **Code Organization**
   - Follow MVC patterns
   - Use proper namespacing
   - Implement proper error handling
   - Write unit tests

3. **Performance Considerations**
   - Minimize database queries
   - Use caching appropriately
   - Optimize file includes
   - Profile and monitor performance

## Conclusion

SuiteCRM's architecture provides a solid foundation for building enterprise CRM solutions. Its modular design, extensive customization capabilities, and robust security features make it suitable for organizations of all sizes. The clear separation between core and custom code ensures that implementations remain upgrade-safe while allowing deep customization to meet specific business requirements.