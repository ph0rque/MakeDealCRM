# SuiteCRM Module Architecture

## Overview

Modules are the fundamental building blocks of SuiteCRM, encapsulating business logic, data models, and user interfaces for specific CRM functionalities. This document provides a comprehensive guide to understanding and working with SuiteCRM's module architecture.

## Module Types

### 1. Core Modules
Essential CRM functionality that ships with SuiteCRM:
- **Users**: User management and authentication
- **ACL/ACLRoles**: Access control and permissions
- **Accounts**: Company/organization records
- **Contacts**: Individual person records
- **Leads**: Potential customers
- **Administration**: System configuration

### 2. Business Process Modules
- **Opportunities**: Sales pipeline management
- **Cases**: Customer support tickets
- **Projects**: Project management
- **Campaigns**: Marketing campaign management
- **Workflows**: Business process automation

### 3. Communication Modules
- **Emails**: Email integration and management
- **Calls**: Phone call tracking
- **Meetings**: Meeting scheduling and tracking
- **Notes**: General notes and attachments
- **Tasks**: Task management

### 4. Advanced Open Modules
SuiteCRM-specific enhancements:
- **AOS (Advanced OpenSales)**: Quotes, invoices, contracts
- **AOR (Advanced OpenReports)**: Reporting engine
- **AOW (Advanced OpenWorkflow)**: Workflow automation
- **AOP (Advanced OpenPortal)**: Customer portal
- **AOK (Advanced OpenKnowledge)**: Knowledge base

## Module Structure

### Standard Module Directory Layout

```
modules/[ModuleName]/
├── [ModuleName].php           # Main bean class extending SugarBean
├── [ModuleName]_sugar.php     # Generated bean definition
├── controller.php             # Module-specific controller
├── Menu.php                   # Module menu definition
├── vardefs.php                # Field and relationship definitions
├── Forms.php                  # Form helper functions
├── Save.php                   # Custom save logic
├── metadata/                  # UI and relationship metadata
│   ├── detailviewdefs.php    # Detail view layout
│   ├── editviewdefs.php      # Edit view layout
│   ├── listviewdefs.php      # List view columns
│   ├── popupdefs.php         # Popup selector layout
│   ├── quickcreatedefs.php   # Quick create form
│   ├── searchdefs.php        # Search form layout
│   ├── SearchFields.php      # Search field definitions
│   └── subpaneldefs.php      # Subpanel definitions
├── views/                     # View classes
│   ├── view.list.php         # List view customization
│   ├── view.detail.php       # Detail view customization
│   ├── view.edit.php         # Edit view customization
│   └── view.popup.php        # Popup view customization
├── language/                  # Language files
│   ├── en_us.lang.php        # English language strings
│   └── [locale].lang.php     # Other language translations
├── Dashlets/                  # Module-specific dashlets
├── tpls/                      # Custom templates
└── js/                        # Module-specific JavaScript
```

## Core Components

### 1. Bean Class

The main PHP class that extends SugarBean:

```php
class Account extends SugarBean 
{
    // Table name in database
    public $table_name = 'accounts';
    
    // Object name for display
    public $object_name = 'Account';
    
    // Module directory
    public $module_dir = 'Accounts';
    
    // Fields
    public $name;
    public $industry;
    public $annual_revenue;
    
    // Relationships
    public $contacts;
    public $opportunities;
    
    // Custom methods
    public function save($check_notify = false) 
    {
        // Custom save logic
        return parent::save($check_notify);
    }
}
```

### 2. Variable Definitions (vardefs.php)

Defines fields, relationships, and indices:

```php
$dictionary['Account'] = array(
    'table' => 'accounts',
    'fields' => array(
        'name' => array(
            'name' => 'name',
            'type' => 'name',
            'dbType' => 'varchar',
            'vname' => 'LBL_NAME',
            'required' => true,
            'len' => 150,
            'audited' => true,
        ),
        // More field definitions...
    ),
    'relationships' => array(
        'accounts_contacts' => array(
            'lhs_module' => 'Accounts',
            'lhs_table' => 'accounts',
            'lhs_key' => 'id',
            'rhs_module' => 'Contacts',
            'rhs_table' => 'contacts',
            'rhs_key' => 'account_id',
            'relationship_type' => 'one-to-many',
        ),
        // More relationships...
    ),
    'indices' => array(
        // Index definitions
    ),
);
```

### 3. Metadata Definitions

#### List View (listviewdefs.php)
```php
$listViewDefs['Accounts'] = array(
    'NAME' => array(
        'width' => '20%',
        'label' => 'LBL_LIST_ACCOUNT_NAME',
        'link' => true,
        'default' => true,
    ),
    'BILLING_ADDRESS_CITY' => array(
        'width' => '10%',
        'label' => 'LBL_CITY',
        'default' => true,
    ),
    // More columns...
);
```

#### Detail View (detailviewdefs.php)
```php
$viewdefs['Accounts']['DetailView'] = array(
    'templateMeta' => array(
        'form' => array('buttons' => array('EDIT', 'DELETE', 'DUPLICATE')),
        'maxColumns' => '2',
        'widths' => array(
            array('label' => '10', 'field' => '30'),
            array('label' => '10', 'field' => '30'),
        ),
    ),
    'panels' => array(
        'lbl_account_information' => array(
            array('name', 'phone_office'),
            array('website', 'ownership'),
            // More fields...
        ),
    ),
);
```

## Module Development

### Creating a New Module

#### 1. Using Module Builder (Recommended)
- Navigate to Admin → Developer Tools → Module Builder
- Create new package
- Add new module
- Define fields and relationships
- Deploy module

#### 2. Manual Module Creation
1. Create module directory structure
2. Create bean class extending SugarBean
3. Define vardefs.php
4. Create metadata files
5. Add language files
6. Register in modules.php
7. Run Quick Repair and Rebuild

### Custom Module Example

```php
// custom/modules/MyModule/MyModule.php
class MyModule extends Basic 
{
    public $module_dir = 'MyModule';
    public $object_name = 'MyModule';
    public $table_name = 'mymodule';
    
    public function __construct() 
    {
        parent::__construct();
    }
    
    public function bean_implements($interface) 
    {
        switch($interface) {
            case 'ACL': return true;
        }
        return false;
    }
}
```

## Module Relationships

### Relationship Types

1. **One-to-Many**
   - Parent has many children
   - Example: Account has many Contacts

2. **Many-to-Many**
   - Uses join table
   - Example: Contacts to Opportunities

3. **One-to-One**
   - Direct relationship
   - Example: User to Employee

### Defining Relationships

```php
// In vardefs.php
'contacts' => array(
    'name' => 'contacts',
    'type' => 'link',
    'relationship' => 'accounts_contacts',
    'source' => 'non-db',
    'module' => 'Contacts',
    'bean_name' => 'Contact',
    'vname' => 'LBL_CONTACTS',
),
```

## Module Customization

### 1. Logic Hooks
Execute custom code at specific events:

```php
// custom/modules/Accounts/logic_hooks.php
$hook_array['before_save'][] = array(
    1,
    'Validate account data',
    'custom/modules/Accounts/AccountHooks.php',
    'AccountHooks',
    'validateAccount'
);
```

### 2. Custom Views
Override default views:

```php
// custom/modules/Accounts/views/view.detail.php
class AccountsViewDetail extends ViewDetail 
{
    public function display() 
    {
        // Custom display logic
        parent::display();
    }
}
```

### 3. Field Customization
Add custom fields through Studio or vardefs:

```php
// custom/Extension/modules/Accounts/Ext/Vardefs/custom_fields.php
$dictionary['Account']['fields']['custom_field_c'] = array(
    'name' => 'custom_field_c',
    'vname' => 'LBL_CUSTOM_FIELD',
    'type' => 'varchar',
    'len' => '100',
);
```

## Module Security

### Access Control Lists (ACL)
Control module and field-level access:

```php
public function bean_implements($interface) 
{
    switch($interface) {
        case 'ACL': return true;
    }
    return false;
}
```

### Security Groups
Implement record-level security:
- Assign records to security groups
- Control access based on group membership
- Inherit permissions from group hierarchy

## Module Performance

### Best Practices

1. **Query Optimization**
   - Use SugarQuery instead of direct SQL
   - Implement proper indices
   - Limit subpanel queries

2. **Caching**
   - Cache frequently accessed data
   - Use SugarCache API
   - Clear cache after modifications

3. **Lazy Loading**
   - Load relationships only when needed
   - Use pagination for large datasets
   - Implement AJAX loading for subpanels

## Module Integration

### REST API Access
All modules automatically exposed via REST API:

```
GET    /Api/V8/module/{module_name}          # List records
GET    /Api/V8/module/{module_name}/{id}     # Get record
POST   /Api/V8/module/{module_name}          # Create record
PATCH  /Api/V8/module/{module_name}/{id}     # Update record
DELETE /Api/V8/module/{module_name}/{id}     # Delete record
```

### Workflow Integration
Modules can trigger workflows:
- Create workflow conditions based on module fields
- Execute actions on module records
- Send notifications on module events

## Conclusion

SuiteCRM's module architecture provides a flexible and extensible framework for building CRM functionality. By following the established patterns and best practices, developers can create powerful custom modules that integrate seamlessly with the core system while maintaining upgradeability and performance.