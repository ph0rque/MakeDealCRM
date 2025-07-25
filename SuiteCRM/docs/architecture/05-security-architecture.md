# SuiteCRM Security Architecture

## Overview

SuiteCRM implements a comprehensive multi-layered security architecture designed to protect sensitive customer data, ensure user privacy, and maintain system integrity. This document outlines the security mechanisms, best practices, and implementation details for securing a SuiteCRM deployment.

## Security Layers

### 1. Authentication Layer
- User identity verification
- Session management
- Multi-factor authentication support
- Password policies

### 2. Authorization Layer
- Role-based access control (RBAC)
- Field-level security
- Record-level security
- Module-level permissions

### 3. Data Protection Layer
- Encryption at rest
- Encryption in transit
- Input validation
- Output encoding

### 4. Application Security Layer
- SQL injection prevention
- Cross-site scripting (XSS) protection
- Cross-site request forgery (CSRF) protection
- File upload security

## Authentication

### User Authentication

#### Password Management

```php
// Password requirements configuration
$sugar_config['passwordsetting'] = array(
    'minpwdlength' => 8,
    'maxpwdlength' => 50,
    'oneupper' => true,
    'onelower' => true,
    'onenumber' => true,
    'onespecial' => true,
    'SystemGeneratedPasswordON' => true,
    'generatepasswordtmpl' => 'Aa@#$%&*1',
    'forgotpasswordON' => true,
    'linkexpiration' => true,
    'linkexpirationtime' => 24,
    'linkexpirationtype' => 'hours',
    'userexpiration' => 90,
    'userexpirationtime' => 30,
    'userexpirationtype' => 'days',
    'userexpirationlogin' => true,
);
```

#### Session Security

```php
// Session configuration
$sugar_config['session_dir'] = '/tmp/sessions';
$sugar_config['session_gc_maxlifetime'] = 7200; // 2 hours
$sugar_config['session_use_only_cookies'] = true;
$sugar_config['session_cookie_httponly'] = true;
$sugar_config['session_cookie_secure'] = true; // HTTPS only
$sugar_config['session_cookie_samesite'] = 'Strict';
```

### External Authentication

#### LDAP Integration

```php
$sugar_config['authenticationClass'] = 'LDAPAuthenticate';
$sugar_config['ldap_enabled'] = true;
$sugar_config['ldap_admin_user'] = 'cn=admin,dc=example,dc=com';
$sugar_config['ldap_admin_password'] = 'encrypted_password';
$sugar_config['ldap_server'] = 'ldaps://ldap.example.com';
$sugar_config['ldap_port'] = 636;
$sugar_config['ldap_base_dn'] = 'dc=example,dc=com';
$sugar_config['ldap_bind_attr'] = 'uid';
$sugar_config['ldap_auto_create_users'] = true;
```

#### SAML 2.0

```php
$sugar_config['authenticationClass'] = 'SAMLAuthenticate';
$sugar_config['SAML_loginurl'] = 'https://idp.example.com/sso';
$sugar_config['SAML_X509Cert'] = 'path/to/certificate.crt';
$sugar_config['SAML_issuer'] = 'https://crm.example.com';
```

## Authorization

### Access Control Lists (ACL)

#### Role-Based Access Control

```php
// ACL action definitions
$ACLActions = array(
    'module' => array(
        'access' => array('aclaccess' => 89),
        'view' => array('aclaccess' => 90),
        'list' => array('aclaccess' => 90),
        'edit' => array('aclaccess' => 90),
        'delete' => array('aclaccess' => 90),
        'import' => array('aclaccess' => 90),
        'export' => array('aclaccess' => 90),
    )
);
```

#### Field-Level Security

```php
// Field ACL definition in vardefs
$dictionary['Account']['fields']['annual_revenue']['acl'] = array(
    'view' => 'owner',
    'edit' => 'admin',
    'list' => 'owner'
);
```

### Security Groups

#### Record-Level Security Implementation

```sql
-- Security groups tables
CREATE TABLE securitygroups (
    id char(36) NOT NULL,
    name varchar(255),
    date_entered datetime,
    date_modified datetime,
    modified_user_id char(36),
    created_by char(36),
    description text,
    deleted tinyint(1) DEFAULT 0,
    assigned_user_id char(36),
    PRIMARY KEY (id)
);

-- Record to security group relationship
CREATE TABLE securitygroups_records (
    id char(36) NOT NULL,
    securitygroup_id char(36),
    record_id char(36),
    module varchar(100),
    date_modified datetime,
    modified_user_id char(36),
    created_by char(36),
    deleted tinyint(1) DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_securitygroups_records (securitygroup_id, record_id, module)
);
```

#### Group Inheritance

```php
// Security group hierarchy
class SecurityGroup extends Basic {
    public function inherit_parent_groups($parent_type, $parent_id) {
        // Inherit security groups from parent record
        $parent = BeanFactory::getBean($parent_type, $parent_id);
        $parent->load_relationship('SecurityGroups');
        $groups = $parent->SecurityGroups->getBeans();
        
        foreach($groups as $group) {
            $this->SecurityGroups->add($group->id);
        }
    }
}
```

## Data Protection

### Encryption

#### Field Encryption

```php
// Encrypted field definition
$dictionary['Account']['fields']['tax_id'] = array(
    'name' => 'tax_id',
    'vname' => 'LBL_TAX_ID',
    'type' => 'encrypted',
    'len' => 255,
    'audited' => true,
);

// Encryption implementation
class SugarFieldEncrypted extends SugarFieldBase {
    public function save($bean, $params, $field, $properties) {
        if (!empty($bean->$field)) {
            $bean->$field = $this->encrypt($bean->$field);
        }
    }
    
    private function encrypt($value) {
        global $sugar_config;
        $key = $sugar_config['unique_key'];
        return openssl_encrypt($value, 'AES-256-CBC', $key);
    }
}
```

#### Database Encryption

```sql
-- MySQL transparent data encryption
ALTER TABLE sensitive_data ENCRYPTION='Y';

-- Column-level encryption
CREATE TABLE encrypted_data (
    id char(36) NOT NULL,
    encrypted_column VARBINARY(255),
    PRIMARY KEY (id)
);
```

### Input Validation

#### Server-Side Validation

```php
// Field validation in vardefs
$dictionary['Account']['fields']['email'] = array(
    'name' => 'email',
    'type' => 'email',
    'validation' => array(
        'type' => 'email',
        'required' => true,
    ),
);

// Custom validation
class CustomValidator {
    public function validate_phone($value) {
        $pattern = '/^[\d\s\-\+\(\)\.]+$/';
        return preg_match($pattern, $value);
    }
    
    public function validate_tax_id($value) {
        // Custom tax ID validation logic
        return $this->isValidTaxId($value);
    }
}
```

#### XSS Prevention

```php
// Output encoding
class SugarHtml {
    public static function encode($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
    
    public static function encodeJavascript($string) {
        return json_encode($string);
    }
}

// In templates
{$account_name|escape:'html'}
{$javascript_var|escape:'javascript'}
```

### SQL Injection Prevention

```php
// Using prepared statements
$sql = "SELECT * FROM accounts WHERE industry = ? AND deleted = ?";
$result = $db->pQuery($sql, array($industry, 0));

// Using SugarQuery (recommended)
$query = new SugarQuery();
$query->from(BeanFactory::newBean('Accounts'));
$query->where()
    ->equals('industry', $industry)
    ->equals('deleted', 0);
$results = $query->execute();

// Escaping values
$safe_value = $db->quote($unsafe_value);
```

## Application Security

### CSRF Protection

```php
// CSRF token generation
class SugarSecure {
    public static function generateToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function validateToken($token) {
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

// In forms
<input type="hidden" name="csrf_token" value="{$csrf_token}">
```

### File Upload Security

```php
// File upload validation
class UploadFile {
    protected $allowed_mime_types = array(
        'image/jpeg',
        'image/png',
        'application/pdf',
        'application/msword',
        'application/vnd.ms-excel',
    );
    
    protected $blocked_extensions = array(
        'php', 'php3', 'php4', 'php5', 'phtml',
        'exe', 'com', 'bat', 'sh', 'cmd',
        'pl', 'cgi', 'htaccess', 'htpasswd'
    );
    
    public function validate($file) {
        // Check file size
        if ($file['size'] > $this->max_file_size) {
            return false;
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        if (!in_array($mime_type, $this->allowed_mime_types)) {
            return false;
        }
        
        // Check extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $this->blocked_extensions)) {
            return false;
        }
        
        // Scan for malware
        return $this->scanForMalware($file['tmp_name']);
    }
}
```

### API Security

#### OAuth2 Configuration

```php
// OAuth2 server configuration
$oauth2_server = new OAuth2\Server($storage, array(
    'access_lifetime' => 3600,
    'refresh_token_lifetime' => 1209600,
    'enforce_state' => true,
    'require_exact_redirect_uri' => true,
    'allow_implicit' => false,
));

// API rate limiting
$sugar_config['api']['rate_limit'] = array(
    'enabled' => true,
    'requests_per_minute' => 60,
    'requests_per_hour' => 1000,
    'burst_size' => 10,
);
```

## Security Configuration

### Security Settings

```php
// config_override.php security settings
$sugar_config['security'] = array(
    'disable_export' => false,
    'disable_persistent_connections' => true,
    'csrf_protection' => true,
    'xss_protection' => true,
    'sql_injection_protection' => true,
    'file_upload_protection' => true,
    'http_only_cookies' => true,
    'secure_cookies' => true,
    'samesite_cookies' => 'Strict',
);

// Additional security headers
$sugar_config['security_headers'] = array(
    'X-Frame-Options' => 'SAMEORIGIN',
    'X-Content-Type-Options' => 'nosniff',
    'X-XSS-Protection' => '1; mode=block',
    'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
    'Content-Security-Policy' => "default-src 'self'",
);
```

### Audit Trail

```php
// Audit configuration
$sugar_config['audit'] = array(
    'enabled' => true,
    'log_all_modules' => false,
    'audit_modules' => array(
        'Accounts', 'Contacts', 'Opportunities', 'Cases'
    ),
    'audit_fields' => array(
        'name', 'amount', 'status', 'assigned_user_id'
    ),
);

// Audit log table
CREATE TABLE accounts_audit (
    id char(36) NOT NULL,
    parent_id char(36) NOT NULL,
    date_created datetime,
    created_by char(36),
    field_name varchar(100),
    data_type varchar(100),
    before_value_string varchar(255),
    after_value_string varchar(255),
    before_value_text text,
    after_value_text text,
    PRIMARY KEY (id),
    KEY idx_accounts_audit_parent_id (parent_id)
);
```

## Security Best Practices

### Development Security

1. **Code Reviews** - All custom code should be reviewed
2. **Security Testing** - Regular penetration testing
3. **Dependency Management** - Keep libraries updated
4. **Secure Coding** - Follow OWASP guidelines

### Deployment Security

1. **HTTPS Only** - Force SSL/TLS for all connections
2. **Firewall Rules** - Restrict database access
3. **File Permissions** - Proper Unix file permissions
4. **Directory Protection** - Protect sensitive directories

```bash
# File permissions
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod 775 cache custom modules themes upload
chmod 770 config.php config_override.php

# Apache .htaccess for upload directory
<FilesMatch "\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)$">
    Order Deny,Allow
    Deny from all
</FilesMatch>
```

### Monitoring and Incident Response

1. **Log Monitoring** - Monitor access and error logs
2. **Intrusion Detection** - IDS/IPS implementation
3. **Security Alerts** - Real-time alerting
4. **Incident Response Plan** - Documented procedures

## Compliance

### GDPR Compliance

```php
// Data privacy features
class GDPRCompliance {
    public function exportPersonalData($user_id) {
        // Export all personal data for a user
    }
    
    public function deletePersonalData($user_id) {
        // Delete or anonymize personal data
    }
    
    public function logConsent($user_id, $purpose, $consent) {
        // Log consent for data processing
    }
}
```

### Security Standards

- **ISO 27001** - Information security management
- **SOC 2** - Service organization controls
- **PCI DSS** - Payment card industry standards
- **HIPAA** - Healthcare data protection

## Conclusion

SuiteCRM's security architecture provides comprehensive protection through multiple layers of security controls. By properly implementing authentication, authorization, encryption, and following security best practices, organizations can maintain a secure CRM environment that protects sensitive data while enabling business operations. Regular security assessments and updates are essential to maintain the security posture as threats evolve.