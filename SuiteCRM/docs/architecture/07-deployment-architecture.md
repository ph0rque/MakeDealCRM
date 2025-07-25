# SuiteCRM Deployment Architecture

## Overview

This document provides comprehensive guidance on deploying SuiteCRM in various environments, from single-server setups to highly available enterprise architectures. It covers infrastructure requirements, deployment patterns, scaling strategies, and operational best practices.

## Deployment Options

### 1. Single Server Deployment

Suitable for small organizations or development environments:

```
┌─────────────────────────────────┐
│         Single Server           │
├─────────────────────────────────┤
│  - Web Server (Apache/Nginx)    │
│  - PHP-FPM                      │
│  - MySQL/MariaDB                │
│  - Redis/Memcached              │
│  - File Storage                 │
└─────────────────────────────────┘
```

**Specifications:**
- **CPU**: 4+ cores
- **RAM**: 8-16 GB
- **Storage**: 100+ GB SSD
- **OS**: Ubuntu 20.04 LTS / RHEL 8

### 2. Two-Tier Architecture

Separates application and database layers:

```
┌──────────────────┐     ┌──────────────────┐
│   Web Server     │────▶│ Database Server  │
├──────────────────┤     ├──────────────────┤
│ - Apache/Nginx   │     │ - MySQL/MariaDB  │
│ - PHP-FPM        │     │ - Redis          │
│ - SuiteCRM App   │     └──────────────────┘
└──────────────────┘
```

### 3. Three-Tier Architecture

Adds dedicated caching layer:

```
┌──────────────────┐
│   Load Balancer  │
└────────┬─────────┘
         │
    ┌────┴────┐
    ▼         ▼
┌─────────┐ ┌─────────┐     ┌──────────────┐     ┌──────────────┐
│ Web 1   │ │ Web 2   │────▶│ Cache Server │────▶│ DB Server    │
└─────────┘ └─────────┘     │ - Redis      │     │ - MySQL      │
                            │ - Memcached  │     └──────────────┘
                            └──────────────┘
```

### 4. High Availability Architecture

Enterprise-grade deployment with redundancy:

```
                    ┌─────────────────┐
                    │   CDN/WAF       │
                    └────────┬────────┘
                             │
                    ┌────────▼────────┐
                    │  Load Balancer  │
                    │   (HA Pair)     │
                    └────────┬────────┘
                             │
         ┌───────────────────┼───────────────────┐
         ▼                   ▼                   ▼
    ┌─────────┐        ┌─────────┐        ┌─────────┐
    │ Web 1   │        │ Web 2   │        │ Web 3   │
    └────┬────┘        └────┬────┘        └────┬────┘
         │                   │                   │
         └───────────────────┼───────────────────┘
                             │
                    ┌────────▼────────┐
                    │  Cache Cluster  │
                    │  Redis Sentinel │
                    └────────┬────────┘
                             │
                    ┌────────▼────────┐
                    │   DB Cluster    │
                    │ Master-Slave    │
                    └─────────────────┘
```

## Infrastructure Requirements

### Hardware Requirements

#### Minimum Requirements

| Component | Development | Production | Enterprise |
|-----------|-------------|------------|------------|
| CPU | 2 cores | 4 cores | 8+ cores |
| RAM | 4 GB | 8 GB | 16+ GB |
| Storage | 50 GB | 100 GB | 500+ GB SSD |
| Network | 100 Mbps | 1 Gbps | 10 Gbps |

#### Recommended Specifications

**Web Server:**
```yaml
CPU: 8 cores (Intel Xeon or AMD EPYC)
RAM: 16-32 GB
Storage: 
  - OS: 50 GB SSD
  - Application: 200 GB SSD
  - Uploads: 500 GB SSD or NFS
Network: 1 Gbps
```

**Database Server:**
```yaml
CPU: 16 cores
RAM: 64 GB
Storage:
  - OS: 50 GB SSD
  - Data: 1 TB NVMe SSD
  - Logs: 200 GB SSD
Network: 10 Gbps
```

### Software Stack

#### Operating System
```bash
# Recommended OS
- Ubuntu 20.04/22.04 LTS
- RHEL/CentOS 8/9
- Debian 10/11

# System configuration
echo "vm.swappiness=10" >> /etc/sysctl.conf
echo "net.ipv4.tcp_keepalive_time=120" >> /etc/sysctl.conf
sysctl -p
```

#### Web Server Configuration

**Apache Configuration:**
```apache
<VirtualHost *:80>
    ServerName crm.example.com
    Redirect permanent / https://crm.example.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName crm.example.com
    DocumentRoot /var/www/suitecrm
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/crm.crt
    SSLCertificateKeyFile /etc/ssl/private/crm.key
    SSLProtocol -all +TLSv1.2 +TLSv1.3
    
    # Security Headers
    Header always set Strict-Transport-Security "max-age=31536000"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    
    # PHP-FPM
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/var/run/php/php7.4-fpm.sock|fcgi://localhost"
    </FilesMatch>
    
    # Rewrite Rules
    RewriteEngine On
    RewriteBase /
    RewriteRule ^cache/jsLanguage/(.._..).js$ index.php?entryPoint=jslang&lang=$1 [L,QSA]
    
    # Directory Configuration
    <Directory /var/www/suitecrm>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

**Nginx Configuration:**
```nginx
server {
    listen 80;
    server_name crm.example.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name crm.example.com;
    root /var/www/suitecrm;
    
    # SSL Configuration
    ssl_certificate /etc/ssl/certs/crm.crt;
    ssl_certificate_key /etc/ssl/private/crm.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    
    # Security Headers
    add_header Strict-Transport-Security "max-age=31536000" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    
    # PHP Configuration
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 256 16k;
        fastcgi_read_timeout 600;
    }
    
    # Static Files
    location ~* \.(jpg|jpeg|gif|png|css|js|ico|xml)$ {
        expires 1d;
        add_header Cache-Control "public, immutable";
    }
    
    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }
    
    location ~ \.(htaccess|htpasswd|ini|log|sh|sql)$ {
        deny all;
    }
}
```

#### PHP Configuration

**php.ini optimizations:**
```ini
; Performance
memory_limit = 512M
max_execution_time = 300
max_input_time = 300
post_max_size = 100M
upload_max_filesize = 100M
max_file_uploads = 50

; OPcache
opcache.enable = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000
opcache.revalidate_freq = 0
opcache.validate_timestamps = 0

; Session
session.save_handler = redis
session.save_path = "tcp://127.0.0.1:6379"
session.gc_maxlifetime = 7200

; Security
expose_php = Off
disable_functions = exec,passthru,shell_exec,system,proc_open,popen
```

**PHP-FPM pool configuration:**
```ini
[suitecrm]
user = www-data
group = www-data
listen = /var/run/php/php7.4-fpm.sock
listen.owner = www-data
listen.group = www-data

; Process Management
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500

; Logging
slowlog = /var/log/php-fpm/suitecrm-slow.log
request_slowlog_timeout = 10s
```

#### Database Configuration

**MySQL/MariaDB optimization:**
```ini
[mysqld]
# Basic Settings
max_connections = 500
connect_timeout = 10
wait_timeout = 600
interactive_timeout = 600

# InnoDB Settings
innodb_buffer_pool_size = 4G  # 70% of RAM
innodb_log_file_size = 512M
innodb_flush_log_at_trx_commit = 2
innodb_file_per_table = 1
innodb_flush_method = O_DIRECT

# Query Cache (MySQL 5.7)
query_cache_type = 1
query_cache_size = 64M
query_cache_limit = 2M

# Logging
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 2

# Character Set
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci
```

## Container Deployment

### Docker Deployment

**docker-compose.yml:**
```yaml
version: '3.8'

services:
  suitecrm:
    image: bitnami/suitecrm:latest
    container_name: suitecrm
    ports:
      - "80:8080"
      - "443:8443"
    environment:
      - SUITECRM_DATABASE_HOST=mariadb
      - SUITECRM_DATABASE_NAME=suitecrm
      - SUITECRM_DATABASE_USER=suitecrm
      - SUITECRM_DATABASE_PASSWORD=SecurePassword123!
      - SUITECRM_HOST=crm.example.com
      - SUITECRM_USERNAME=admin
      - SUITECRM_PASSWORD=AdminPassword123!
      - SUITECRM_EMAIL=admin@example.com
    volumes:
      - suitecrm_data:/bitnami/suitecrm
      - ./custom:/bitnami/suitecrm/custom
    depends_on:
      - mariadb
      - redis
    networks:
      - suitecrm-network

  mariadb:
    image: mariadb:10.6
    container_name: suitecrm_db
    environment:
      - MYSQL_ROOT_PASSWORD=RootPassword123!
      - MYSQL_DATABASE=suitecrm
      - MYSQL_USER=suitecrm
      - MYSQL_PASSWORD=SecurePassword123!
    volumes:
      - mariadb_data:/var/lib/mysql
      - ./mysql-init:/docker-entrypoint-initdb.d
    networks:
      - suitecrm-network

  redis:
    image: redis:7-alpine
    container_name: suitecrm_cache
    command: redis-server --requirepass RedisPassword123!
    volumes:
      - redis_data:/data
    networks:
      - suitecrm-network

  nginx:
    image: nginx:alpine
    container_name: suitecrm_proxy
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx.conf:/etc/nginx/nginx.conf
      - ./ssl:/etc/nginx/ssl
    depends_on:
      - suitecrm
    networks:
      - suitecrm-network

volumes:
  suitecrm_data:
  mariadb_data:
  redis_data:

networks:
  suitecrm-network:
    driver: bridge
```

### Kubernetes Deployment

**deployment.yaml:**
```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: suitecrm
  namespace: crm
spec:
  replicas: 3
  selector:
    matchLabels:
      app: suitecrm
  template:
    metadata:
      labels:
        app: suitecrm
    spec:
      containers:
      - name: suitecrm
        image: company/suitecrm:latest
        ports:
        - containerPort: 80
        env:
        - name: DATABASE_HOST
          value: mysql-service
        - name: DATABASE_NAME
          value: suitecrm
        - name: DATABASE_USER
          valueFrom:
            secretKeyRef:
              name: mysql-secret
              key: username
        - name: DATABASE_PASSWORD
          valueFrom:
            secretKeyRef:
              name: mysql-secret
              key: password
        resources:
          requests:
            memory: "512Mi"
            cpu: "500m"
          limits:
            memory: "2Gi"
            cpu: "2000m"
        volumeMounts:
        - name: suitecrm-storage
          mountPath: /var/www/html/upload
        - name: config
          mountPath: /var/www/html/config_override.php
          subPath: config_override.php
      volumes:
      - name: suitecrm-storage
        persistentVolumeClaim:
          claimName: suitecrm-pvc
      - name: config
        configMap:
          name: suitecrm-config
```

## Scaling Strategies

### Horizontal Scaling

#### Load Balancer Configuration

**HAProxy example:**
```
global
    maxconn 4096
    log 127.0.0.1 local0
    
defaults
    mode http
    timeout connect 5000ms
    timeout client 50000ms
    timeout server 50000ms
    
frontend web_frontend
    bind *:80
    bind *:443 ssl crt /etc/haproxy/certs/crm.pem
    redirect scheme https if !{ ssl_fc }
    
    # Rate limiting
    stick-table type ip size 100k expire 30s store http_req_rate(10s)
    http-request track-sc0 src
    http-request deny if { sc_http_req_rate(0) gt 20 }
    
    default_backend web_servers
    
backend web_servers
    balance roundrobin
    option httpchk GET /health_check.php
    http-check expect status 200
    
    server web1 10.0.1.10:80 check
    server web2 10.0.1.11:80 check
    server web3 10.0.1.12:80 check
```

#### Session Management

Configure Redis for session sharing:
```php
// config_override.php
$sugar_config['session_save_handler'] = 'redis';
$sugar_config['session_save_path'] = 'tcp://redis.example.com:6379?auth=password';
```

### Vertical Scaling

Performance tuning for large instances:

```bash
# Kernel parameters for high-performance
echo "net.core.somaxconn = 65535" >> /etc/sysctl.conf
echo "net.ipv4.tcp_max_syn_backlog = 65535" >> /etc/sysctl.conf
echo "net.core.netdev_max_backlog = 65535" >> /etc/sysctl.conf
echo "fs.file-max = 2097152" >> /etc/sysctl.conf
sysctl -p
```

## Backup and Disaster Recovery

### Backup Strategy

```bash
#!/bin/bash
# backup.sh

# Variables
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backup/suitecrm"
DB_NAME="suitecrm"
DB_USER="backup_user"
DB_PASS="secure_password"
APP_DIR="/var/www/suitecrm"

# Create backup directory
mkdir -p ${BACKUP_DIR}/${DATE}

# Database backup
mysqldump -u${DB_USER} -p${DB_PASS} ${DB_NAME} | gzip > ${BACKUP_DIR}/${DATE}/database.sql.gz

# Application files backup
tar -czf ${BACKUP_DIR}/${DATE}/files.tar.gz -C ${APP_DIR} .

# Custom code backup
tar -czf ${BACKUP_DIR}/${DATE}/custom.tar.gz -C ${APP_DIR}/custom .

# Upload directory backup
tar -czf ${BACKUP_DIR}/${DATE}/upload.tar.gz -C ${APP_DIR}/upload .

# Keep only last 30 days of backups
find ${BACKUP_DIR} -type d -mtime +30 -exec rm -rf {} \;

# Sync to remote storage
aws s3 sync ${BACKUP_DIR} s3://company-backups/suitecrm/
```

### Disaster Recovery Plan

1. **RTO/RPO Targets**
   - Recovery Time Objective: 4 hours
   - Recovery Point Objective: 1 hour

2. **DR Procedures**
   - Automated failover to standby site
   - Database replication lag monitoring
   - Regular DR drills

## Monitoring and Maintenance

### Health Checks

**health_check.php:**
```php
<?php
// Basic health check
$checks = [];

// Database connectivity
try {
    $db = DBManagerFactory::getInstance();
    $result = $db->query("SELECT 1");
    $checks['database'] = 'OK';
} catch (Exception $e) {
    $checks['database'] = 'FAIL';
    http_response_code(503);
}

// Redis connectivity
try {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    $redis->ping();
    $checks['cache'] = 'OK';
} catch (Exception $e) {
    $checks['cache'] = 'FAIL';
}

// File system
$upload_dir = 'upload';
if (is_writable($upload_dir)) {
    $checks['filesystem'] = 'OK';
} else {
    $checks['filesystem'] = 'FAIL';
    http_response_code(503);
}

// Return results
header('Content-Type: application/json');
echo json_encode($checks);
```

### Monitoring Stack

```yaml
# Prometheus configuration
global:
  scrape_interval: 15s

scrape_configs:
  - job_name: 'suitecrm'
    static_configs:
      - targets: ['web1:9090', 'web2:9090', 'web3:9090']
    
  - job_name: 'mysql'
    static_configs:
      - targets: ['mysql:9104']
    
  - job_name: 'redis'
    static_configs:
      - targets: ['redis:9121']
```

## Security Hardening

### File Permissions

```bash
# Set proper permissions
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
chmod 775 cache custom modules themes upload
chmod 770 config.php config_override.php

# Set ownership
chown -R www-data:www-data /var/www/suitecrm
```

### Firewall Rules

```bash
# UFW configuration
ufw default deny incoming
ufw default allow outgoing
ufw allow ssh
ufw allow http
ufw allow https
ufw allow from 10.0.0.0/8 to any port 3306  # MySQL from internal
ufw enable
```

## Deployment Automation

### CI/CD Pipeline

**.gitlab-ci.yml:**
```yaml
stages:
  - build
  - test
  - deploy

variables:
  DOCKER_REGISTRY: registry.example.com

build:
  stage: build
  script:
    - docker build -t $DOCKER_REGISTRY/suitecrm:$CI_COMMIT_SHA .
    - docker push $DOCKER_REGISTRY/suitecrm:$CI_COMMIT_SHA

test:
  stage: test
  script:
    - composer install
    - ./vendor/bin/phpunit tests/
    - ./vendor/bin/phpcs --standard=PSR2 custom/

deploy_production:
  stage: deploy
  script:
    - kubectl set image deployment/suitecrm suitecrm=$DOCKER_REGISTRY/suitecrm:$CI_COMMIT_SHA
    - kubectl rollout status deployment/suitecrm
  only:
    - master
```

## Best Practices

1. **Infrastructure as Code**
   - Use Terraform/Ansible for provisioning
   - Version control all configurations
   - Automate deployment processes

2. **Security**
   - Regular security updates
   - Implement WAF
   - Use encrypted connections
   - Regular security audits

3. **Performance**
   - Enable all caching layers
   - Optimize database queries
   - Use CDN for static assets
   - Regular performance testing

4. **Operational Excellence**
   - Implement comprehensive monitoring
   - Automate backups
   - Document procedures
   - Regular disaster recovery drills

## Conclusion

Successful SuiteCRM deployment requires careful planning of infrastructure, security, and operational procedures. By following the architectures and best practices outlined in this document, organizations can build scalable, secure, and highly available CRM systems that meet their business needs while maintaining optimal performance and reliability.