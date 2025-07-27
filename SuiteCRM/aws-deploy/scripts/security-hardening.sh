#!/bin/bash

# MakeDealCRM Security Hardening Script
# Implements security best practices for AWS deployment

set -e

# Configuration
INSTANCE_ID=${INSTANCE_ID}
REGION=${AWS_REGION:-us-east-1}
SECURITY_GROUP_ID=${SECURITY_GROUP_ID}

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Functions
print_status() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Configure SSH hardening
harden_ssh() {
    print_status "Hardening SSH configuration..."
    
    # Backup original sshd_config
    sudo cp /etc/ssh/sshd_config /etc/ssh/sshd_config.backup
    
    # Apply SSH hardening
    sudo tee /etc/ssh/sshd_config.d/99-makedealcrm-hardening.conf > /dev/null <<EOF
# MakeDealCRM SSH Hardening Configuration

# Disable root login
PermitRootLogin no

# Disable password authentication
PasswordAuthentication no
PermitEmptyPasswords no
ChallengeResponseAuthentication no

# Enable key-based authentication only
PubkeyAuthentication yes
AuthorizedKeysFile .ssh/authorized_keys

# Limit users who can SSH
AllowUsers ec2-user

# Security settings
Protocol 2
StrictModes yes
IgnoreRhosts yes
HostbasedAuthentication no
PermitUserEnvironment no

# Limit authentication attempts
MaxAuthTries 3
MaxSessions 3

# Timeouts
ClientAliveInterval 300
ClientAliveCountMax 2
LoginGraceTime 30

# Logging
LogLevel VERBOSE

# Disable X11 forwarding
X11Forwarding no

# Disable TCP forwarding (uncomment if not needed)
# AllowTcpForwarding no

# Use strong ciphers only
Ciphers aes128-ctr,aes192-ctr,aes256-ctr,aes128-gcm@openssh.com,aes256-gcm@openssh.com
MACs hmac-sha2-512-etm@openssh.com,hmac-sha2-256-etm@openssh.com,hmac-sha2-512,hmac-sha2-256
KexAlgorithms curve25519-sha256,curve25519-sha256@libssh.org,diffie-hellman-group16-sha512,diffie-hellman-group18-sha512
EOF
    
    # Restart SSH service
    sudo systemctl restart sshd
    
    print_status "SSH hardening completed!"
}

# Configure firewall rules
configure_firewall() {
    print_status "Configuring host firewall..."
    
    # Install and enable firewalld
    sudo yum install -y firewalld
    sudo systemctl start firewalld
    sudo systemctl enable firewalld
    
    # Configure firewall rules
    sudo firewall-cmd --permanent --zone=public --add-service=http
    sudo firewall-cmd --permanent --zone=public --add-service=https
    sudo firewall-cmd --permanent --zone=public --add-service=ssh
    
    # Remove unnecessary services
    sudo firewall-cmd --permanent --zone=public --remove-service=dhcpv6-client
    
    # Add rate limiting for SSH
    sudo firewall-cmd --permanent --add-rich-rule='rule family="ipv4" service name="ssh" limit value="3/m" accept'
    
    # Reload firewall
    sudo firewall-cmd --reload
    
    print_status "Firewall configuration completed!"
}

# Configure fail2ban
configure_fail2ban() {
    print_status "Installing and configuring fail2ban..."
    
    # Install fail2ban
    sudo yum install -y epel-release
    sudo yum install -y fail2ban fail2ban-systemd
    
    # Configure fail2ban for SSH
    sudo tee /etc/fail2ban/jail.local > /dev/null <<EOF
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 3
backend = systemd

[sshd]
enabled = true
port = ssh
filter = sshd
logpath = /var/log/secure
maxretry = 3
bantime = 3600

[sshd-ddos]
enabled = true
port = ssh
filter = sshd-ddos
logpath = /var/log/secure
maxretry = 10
bantime = 3600

[apache-auth]
enabled = true
port = http,https
filter = apache-auth
logpath = /var/log/httpd/*error_log
maxretry = 5
bantime = 3600

[apache-overflows]
enabled = true
port = http,https
filter = apache-overflows
logpath = /var/log/httpd/*error_log
maxretry = 2
bantime = 3600
EOF
    
    # Start and enable fail2ban
    sudo systemctl start fail2ban
    sudo systemctl enable fail2ban
    
    print_status "fail2ban configuration completed!"
}

# Configure Apache security
harden_apache() {
    print_status "Hardening Apache configuration..."
    
    # Create security configuration
    sudo tee /etc/httpd/conf.d/security.conf > /dev/null <<EOF
# MakeDealCRM Apache Security Configuration

# Hide Apache version
ServerTokens Prod
ServerSignature Off

# Disable directory browsing
Options -Indexes

# Disable server-side includes
Options -Includes

# Disable CGI execution
Options -ExecCGI

# Enable XSS protection
Header set X-XSS-Protection "1; mode=block"

# Prevent clickjacking
Header always append X-Frame-Options SAMEORIGIN

# Prevent MIME type sniffing
Header set X-Content-Type-Options nosniff

# Enable HSTS
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"

# Disable TRACE method
TraceEnable off

# Limit request size (10MB)
LimitRequestBody 10485760

# Timeout settings
Timeout 300
KeepAlive On
MaxKeepAliveRequests 100
KeepAliveTimeout 5

# Hide PHP version
Header unset X-Powered-By

# Security headers for application
<Directory /var/www/html>
    # Content Security Policy
    Header set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'none';"
    
    # Referrer Policy
    Header set Referrer-Policy "strict-origin-when-cross-origin"
    
    # Permissions Policy
    Header set Permissions-Policy "accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()"
</Directory>

# Disable dangerous PHP functions in .htaccess
<Directory /var/www/html>
    php_admin_value disable_functions "exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source"
</Directory>
EOF
    
    # Set proper permissions
    sudo chown -R apache:apache /var/www/html
    sudo find /var/www/html -type d -exec chmod 755 {} \;
    sudo find /var/www/html -type f -exec chmod 644 {} \;
    
    # Secure sensitive directories
    for dir in cache custom modules upload; do
        if [ -d "/var/www/html/$dir" ]; then
            sudo chmod -R 775 "/var/www/html/$dir"
        fi
    done
    
    # Restart Apache
    sudo systemctl restart httpd
    
    print_status "Apache hardening completed!"
}

# Configure PHP security
harden_php() {
    print_status "Hardening PHP configuration..."
    
    # Create PHP security configuration
    sudo tee /etc/php.d/99-makedealcrm-security.ini > /dev/null <<EOF
; MakeDealCRM PHP Security Configuration

; Disable dangerous functions
disable_functions = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source

; Hide PHP version
expose_php = Off

; Limit file uploads
file_uploads = On
upload_max_filesize = 10M
max_file_uploads = 20

; Session security
session.cookie_secure = On
session.cookie_httponly = On
session.cookie_samesite = Strict
session.use_only_cookies = On
session.use_strict_mode = On
session.gc_maxlifetime = 1440

; Disable remote file inclusion
allow_url_fopen = Off
allow_url_include = Off

; Error handling
display_errors = Off
log_errors = On
error_log = /var/log/php_errors.log

; Resource limits
max_execution_time = 300
max_input_time = 300
memory_limit = 256M
post_max_size = 10M

; Security misc
enable_dl = Off
disable_classes = 
open_basedir = /var/www/html:/tmp:/var/lib/php/session
EOF
    
    # Create PHP error log
    sudo touch /var/log/php_errors.log
    sudo chown apache:apache /var/log/php_errors.log
    
    print_status "PHP hardening completed!"
}

# Configure MySQL security
harden_mysql() {
    print_status "Hardening MySQL configuration..."
    
    # Note: This assumes MySQL is accessed via RDS
    # For local MySQL, additional hardening would be needed
    
    # Create application-specific MySQL user with limited privileges
    mysql -h "$DB_HOST" -u root -p"$DB_ROOT_PASSWORD" <<EOF
-- Remove anonymous users
DELETE FROM mysql.user WHERE User='';

-- Remove remote root access
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');

-- Remove test database
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';

-- Create application user with limited privileges
CREATE USER IF NOT EXISTS 'makedealcrm_app'@'%' IDENTIFIED BY '$DB_APP_PASSWORD';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER ON makedealcrm.* TO 'makedealcrm_app'@'%';

-- Flush privileges
FLUSH PRIVILEGES;
EOF
    
    print_status "MySQL hardening completed!"
}

# Configure SELinux
configure_selinux() {
    print_status "Configuring SELinux..."
    
    # Check if SELinux is installed
    if command -v getenforce &> /dev/null; then
        # Set SELinux to enforcing mode
        sudo setenforce 1
        sudo sed -i 's/^SELINUX=.*/SELINUX=enforcing/' /etc/selinux/config
        
        # Configure SELinux for Apache
        sudo setsebool -P httpd_can_network_connect on
        sudo setsebool -P httpd_can_sendmail on
        
        # Set proper context for web files
        sudo semanage fcontext -a -t httpd_sys_content_t "/var/www/html(/.*)?"
        sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/html/cache(/.*)?"
        sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/html/custom(/.*)?"
        sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/html/upload(/.*)?"
        sudo restorecon -Rv /var/www/html
        
        print_status "SELinux configuration completed!"
    else
        print_warning "SELinux not available on this system"
    fi
}

# Configure system security
harden_system() {
    print_status "Hardening system configuration..."
    
    # Disable unnecessary services
    for service in avahi-daemon cups bluetooth; do
        if systemctl is-active --quiet $service; then
            sudo systemctl stop $service
            sudo systemctl disable $service
            print_status "Disabled $service"
        fi
    done
    
    # Configure kernel parameters
    sudo tee /etc/sysctl.d/99-makedealcrm-security.conf > /dev/null <<EOF
# MakeDealCRM Security Kernel Parameters

# IP Spoofing protection
net.ipv4.conf.all.rp_filter = 1
net.ipv4.conf.default.rp_filter = 1

# Ignore ICMP redirects
net.ipv4.conf.all.accept_redirects = 0
net.ipv6.conf.all.accept_redirects = 0

# Ignore send redirects
net.ipv4.conf.all.send_redirects = 0

# Disable source packet routing
net.ipv4.conf.all.accept_source_route = 0
net.ipv6.conf.all.accept_source_route = 0

# Log Martians
net.ipv4.conf.all.log_martians = 1

# Ignore ICMP ping requests
net.ipv4.icmp_echo_ignore_broadcasts = 1

# Ignore Directed pings
net.ipv4.icmp_ignore_bogus_error_responses = 1

# Enable TCP/IP SYN cookies
net.ipv4.tcp_syncookies = 1
net.ipv4.tcp_max_syn_backlog = 2048
net.ipv4.tcp_synack_retries = 2
net.ipv4.tcp_syn_retries = 5

# Disable IPv6 if not needed
net.ipv6.conf.all.disable_ipv6 = 1
net.ipv6.conf.default.disable_ipv6 = 1
EOF
    
    # Apply kernel parameters
    sudo sysctl -p /etc/sysctl.d/99-makedealcrm-security.conf
    
    # Configure automatic security updates
    sudo yum install -y yum-cron
    sudo sed -i 's/apply_updates = no/apply_updates = yes/' /etc/yum/yum-cron.conf
    sudo sed -i 's/update_cmd = default/update_cmd = security/' /etc/yum/yum-cron.conf
    sudo systemctl enable yum-cron
    sudo systemctl start yum-cron
    
    print_status "System hardening completed!"
}

# Configure audit logging
configure_auditd() {
    print_status "Configuring audit logging..."
    
    # Install auditd
    sudo yum install -y audit
    
    # Configure audit rules
    sudo tee /etc/audit/rules.d/makedealcrm.rules > /dev/null <<EOF
# MakeDealCRM Audit Rules

# Monitor authentication
-w /var/log/secure -p wa -k auth_log
-w /var/log/messages -p wa -k system_log

# Monitor Apache
-w /var/log/httpd/ -p wa -k apache_log

# Monitor configuration changes
-w /etc/httpd/ -p wa -k apache_config
-w /etc/php.d/ -p wa -k php_config
-w /etc/ssh/sshd_config -p wa -k ssh_config

# Monitor MakeDealCRM application
-w /var/www/html/config.php -p wa -k app_config
-w /var/www/html/custom/ -p wa -k app_custom

# Monitor user actions
-a always,exit -F arch=b64 -S execve -k command_execution
-a always,exit -F arch=b32 -S execve -k command_execution
EOF
    
    # Restart auditd
    sudo service auditd restart
    
    print_status "Audit logging configured!"
}

# Create security report
generate_security_report() {
    print_status "Generating security report..."
    
    REPORT_FILE="/root/makedealcrm-security-report-$(date +%Y%m%d-%H%M%S).txt"
    
    {
        echo "MakeDealCRM Security Report"
        echo "Generated: $(date)"
        echo "============================="
        echo ""
        
        echo "1. SSH Configuration:"
        echo "   - Root login: $(grep -E '^PermitRootLogin' /etc/ssh/sshd_config | awk '{print $2}')"
        echo "   - Password authentication: $(grep -E '^PasswordAuthentication' /etc/ssh/sshd_config | awk '{print $2}')"
        echo ""
        
        echo "2. Firewall Status:"
        sudo firewall-cmd --list-all
        echo ""
        
        echo "3. Fail2ban Status:"
        sudo fail2ban-client status
        echo ""
        
        echo "4. SELinux Status:"
        getenforce
        echo ""
        
        echo "5. Open Ports:"
        sudo netstat -tuln | grep LISTEN
        echo ""
        
        echo "6. Running Services:"
        sudo systemctl list-units --type=service --state=running
        echo ""
        
        echo "7. Security Updates:"
        sudo yum check-update --security
        echo ""
        
    } | sudo tee "$REPORT_FILE"
    
    print_status "Security report saved to: $REPORT_FILE"
}

# Main execution
main() {
    print_status "Starting MakeDealCRM security hardening..."
    
    # Run hardening steps
    harden_ssh
    configure_firewall
    configure_fail2ban
    harden_apache
    harden_php
    configure_selinux
    harden_system
    configure_auditd
    
    # Generate report
    generate_security_report
    
    print_status "Security hardening completed successfully!"
    print_warning "Please review the security report and test all functionality."
}

# Run main function
main "$@"