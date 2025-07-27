#!/bin/bash

# MakeDealCRM Health Check Script
# Monitors application health and reports status

set -e

# Configuration
APP_URL=${APP_URL:-http://localhost}
DB_HOST=${DB_HOST:-localhost}
DB_PORT=${DB_PORT:-3306}
DB_NAME=${DB_NAME:-makedealcrm}
DB_USER=${DB_USER:-makedealcrm}
DB_PASSWORD=${DB_PASSWORD}
REDIS_HOST=${REDIS_HOST:-localhost}
REDIS_PORT=${REDIS_PORT:-6379}

# Health check endpoints
HEALTH_CHECK_FILE="/var/www/html/health.php"
STATUS_FILE="/var/log/makedealcrm-health-status.json"

# Exit codes
EXIT_OK=0
EXIT_WARNING=1
EXIT_CRITICAL=2

# Create health check endpoint
create_health_check_endpoint() {
    cat > "$HEALTH_CHECK_FILE" <<'EOF'
<?php
/**
 * MakeDealCRM Health Check Endpoint
 */

header('Content-Type: application/json');

$health = [
    'status' => 'healthy',
    'timestamp' => date('c'),
    'checks' => []
];

// Check PHP
$health['checks']['php'] = [
    'status' => 'ok',
    'version' => PHP_VERSION
];

// Check database connection
try {
    $db = new PDO(
        "mysql:host={$_ENV['DB_HOST']};port={$_ENV['DB_PORT']};dbname={$_ENV['DB_NAME']}",
        $_ENV['DB_USER'],
        $_ENV['DB_PASSWORD']
    );
    $result = $db->query('SELECT 1')->fetch();
    $health['checks']['database'] = [
        'status' => 'ok',
        'connection' => 'established'
    ];
} catch (Exception $e) {
    $health['status'] = 'unhealthy';
    $health['checks']['database'] = [
        'status' => 'error',
        'message' => 'Connection failed'
    ];
}

// Check Redis (if configured)
if (extension_loaded('redis') && !empty($_ENV['REDIS_HOST'])) {
    try {
        $redis = new Redis();
        $redis->connect($_ENV['REDIS_HOST'], $_ENV['REDIS_PORT'] ?? 6379);
        $redis->ping();
        $health['checks']['redis'] = [
            'status' => 'ok',
            'connection' => 'established'
        ];
    } catch (Exception $e) {
        $health['checks']['redis'] = [
            'status' => 'warning',
            'message' => 'Connection failed'
        ];
    }
}

// Check disk space
$freeSpace = disk_free_space('/');
$totalSpace = disk_total_space('/');
$usedPercent = round((($totalSpace - $freeSpace) / $totalSpace) * 100, 2);

$diskStatus = 'ok';
if ($usedPercent > 90) {
    $diskStatus = 'critical';
    $health['status'] = 'unhealthy';
} elseif ($usedPercent > 80) {
    $diskStatus = 'warning';
}

$health['checks']['disk'] = [
    'status' => $diskStatus,
    'used_percent' => $usedPercent,
    'free_gb' => round($freeSpace / 1073741824, 2)
];

// Check memory usage
$memInfo = file_get_contents('/proc/meminfo');
preg_match('/MemTotal:\s+(\d+)/', $memInfo, $totalMatch);
preg_match('/MemAvailable:\s+(\d+)/', $memInfo, $availMatch);

if ($totalMatch && $availMatch) {
    $totalMem = $totalMatch[1];
    $availMem = $availMatch[1];
    $usedPercent = round((($totalMem - $availMem) / $totalMem) * 100, 2);
    
    $memStatus = 'ok';
    if ($usedPercent > 90) {
        $memStatus = 'critical';
        $health['status'] = 'unhealthy';
    } elseif ($usedPercent > 80) {
        $memStatus = 'warning';
    }
    
    $health['checks']['memory'] = [
        'status' => $memStatus,
        'used_percent' => $usedPercent,
        'available_mb' => round($availMem / 1024, 2)
    ];
}

// Check required directories
$requiredDirs = ['cache', 'custom', 'upload'];
foreach ($requiredDirs as $dir) {
    $path = "/var/www/html/$dir";
    if (is_dir($path) && is_writable($path)) {
        $health['checks']["dir_$dir"] = ['status' => 'ok', 'writable' => true];
    } else {
        $health['status'] = 'unhealthy';
        $health['checks']["dir_$dir"] = ['status' => 'error', 'writable' => false];
    }
}

// Set appropriate HTTP status code
http_response_code($health['status'] === 'healthy' ? 200 : 503);

echo json_encode($health, JSON_PRETTY_PRINT);
EOF

    chmod 644 "$HEALTH_CHECK_FILE"
    chown apache:apache "$HEALTH_CHECK_FILE"
}

# Check web application
check_web_app() {
    echo -n "Checking web application... "
    
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$APP_URL/health.php" || echo "000")
    
    if [ "$HTTP_CODE" = "200" ]; then
        echo "OK (HTTP $HTTP_CODE)"
        return 0
    elif [ "$HTTP_CODE" = "000" ]; then
        echo "CRITICAL (Connection failed)"
        return 2
    else
        echo "WARNING (HTTP $HTTP_CODE)"
        return 1
    fi
}

# Check database connectivity
check_database() {
    echo -n "Checking database connection... "
    
    if mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" -e "SELECT 1" &> /dev/null; then
        echo "OK"
        
        # Check table count
        TABLE_COUNT=$(mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" -D "$DB_NAME" \
            -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$DB_NAME'" -s -N)
        echo "  Tables in database: $TABLE_COUNT"
        
        return 0
    else
        echo "CRITICAL"
        return 2
    fi
}

# Check Redis connectivity
check_redis() {
    echo -n "Checking Redis connection... "
    
    if command -v redis-cli &> /dev/null; then
        if redis-cli -h "$REDIS_HOST" -p "$REDIS_PORT" ping &> /dev/null; then
            echo "OK"
            
            # Get Redis info
            USED_MEMORY=$(redis-cli -h "$REDIS_HOST" -p "$REDIS_PORT" info memory | grep used_memory_human | cut -d: -f2 | tr -d '\r')
            echo "  Memory usage: $USED_MEMORY"
            
            return 0
        else
            echo "WARNING (Connection failed)"
            return 1
        fi
    else
        echo "SKIPPED (redis-cli not installed)"
        return 0
    fi
}

# Check disk space
check_disk_space() {
    echo -n "Checking disk space... "
    
    DISK_USAGE=$(df -h / | awk 'NR==2 {print $5}' | sed 's/%//')
    
    if [ "$DISK_USAGE" -lt 80 ]; then
        echo "OK ($DISK_USAGE% used)"
        return 0
    elif [ "$DISK_USAGE" -lt 90 ]; then
        echo "WARNING ($DISK_USAGE% used)"
        return 1
    else
        echo "CRITICAL ($DISK_USAGE% used)"
        return 2
    fi
}

# Check memory usage
check_memory() {
    echo -n "Checking memory usage... "
    
    MEM_USAGE=$(free | awk 'NR==2{printf "%.0f", $3*100/$2}')
    
    if [ "$MEM_USAGE" -lt 80 ]; then
        echo "OK ($MEM_USAGE% used)"
        return 0
    elif [ "$MEM_USAGE" -lt 90 ]; then
        echo "WARNING ($MEM_USAGE% used)"
        return 1
    else
        echo "CRITICAL ($MEM_USAGE% used)"
        return 2
    fi
}

# Check CPU usage
check_cpu() {
    echo -n "Checking CPU usage... "
    
    CPU_USAGE=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d'%' -f1 | cut -d'.' -f1)
    
    if [ "$CPU_USAGE" -lt 80 ]; then
        echo "OK ($CPU_USAGE% used)"
        return 0
    elif [ "$CPU_USAGE" -lt 90 ]; then
        echo "WARNING ($CPU_USAGE% used)"
        return 1
    else
        echo "CRITICAL ($CPU_USAGE% used)"
        return 2
    fi
}

# Check services
check_services() {
    echo "Checking services..."
    
    SERVICES=("httpd" "docker" "fail2ban")
    SERVICE_STATUS=0
    
    for service in "${SERVICES[@]}"; do
        echo -n "  $service: "
        if systemctl is-active --quiet "$service"; then
            echo "Running"
        else
            echo "Not running"
            SERVICE_STATUS=2
        fi
    done
    
    return $SERVICE_STATUS
}

# Check Docker containers
check_docker_containers() {
    echo "Checking Docker containers..."
    
    if command -v docker &> /dev/null; then
        CONTAINERS=$(docker ps --format "table {{.Names}}\t{{.Status}}" | tail -n +2)
        
        if [ -z "$CONTAINERS" ]; then
            echo "  No containers running"
            return 1
        else
            echo "$CONTAINERS" | while read -r line; do
                echo "  $line"
            done
            return 0
        fi
    else
        echo "  Docker not installed"
        return 0
    fi
}

# Check SSL certificate
check_ssl_cert() {
    echo -n "Checking SSL certificate... "
    
    if [[ "$APP_URL" =~ ^https:// ]]; then
        DOMAIN=$(echo "$APP_URL" | sed 's|https://||' | cut -d'/' -f1)
        CERT_EXPIRY=$(echo | openssl s_client -servername "$DOMAIN" -connect "$DOMAIN:443" 2>/dev/null | openssl x509 -noout -enddate 2>/dev/null | cut -d= -f2)
        
        if [ -n "$CERT_EXPIRY" ]; then
            EXPIRY_EPOCH=$(date -d "$CERT_EXPIRY" +%s)
            CURRENT_EPOCH=$(date +%s)
            DAYS_LEFT=$(( ($EXPIRY_EPOCH - $CURRENT_EPOCH) / 86400 ))
            
            if [ "$DAYS_LEFT" -gt 30 ]; then
                echo "OK (expires in $DAYS_LEFT days)"
                return 0
            elif [ "$DAYS_LEFT" -gt 7 ]; then
                echo "WARNING (expires in $DAYS_LEFT days)"
                return 1
            else
                echo "CRITICAL (expires in $DAYS_LEFT days)"
                return 2
            fi
        else
            echo "ERROR (Could not check certificate)"
            return 1
        fi
    else
        echo "SKIPPED (Not using HTTPS)"
        return 0
    fi
}

# Generate status report
generate_status_report() {
    local overall_status="healthy"
    local exit_code=0
    
    echo "======================================"
    echo "MakeDealCRM Health Check Report"
    echo "Generated: $(date)"
    echo "======================================"
    echo ""
    
    # Run all checks
    check_web_app || { [ $? -gt $exit_code ] && exit_code=$?; }
    check_database || { [ $? -gt $exit_code ] && exit_code=$?; }
    check_redis || { [ $? -gt $exit_code ] && exit_code=$?; }
    check_disk_space || { [ $? -gt $exit_code ] && exit_code=$?; }
    check_memory || { [ $? -gt $exit_code ] && exit_code=$?; }
    check_cpu || { [ $? -gt $exit_code ] && exit_code=$?; }
    check_services || { [ $? -gt $exit_code ] && exit_code=$?; }
    check_docker_containers || { [ $? -gt $exit_code ] && exit_code=$?; }
    check_ssl_cert || { [ $? -gt $exit_code ] && exit_code=$?; }
    
    echo ""
    echo "======================================"
    
    # Determine overall status
    case $exit_code in
        0)
            overall_status="healthy"
            echo "Overall Status: HEALTHY"
            ;;
        1)
            overall_status="warning"
            echo "Overall Status: WARNING"
            ;;
        2)
            overall_status="critical"
            echo "Overall Status: CRITICAL"
            ;;
    esac
    
    # Save status to JSON file
    cat > "$STATUS_FILE" <<EOF
{
    "status": "$overall_status",
    "exit_code": $exit_code,
    "timestamp": "$(date -u +"%Y-%m-%dT%H:%M:%SZ")",
    "checks_performed": $(date +%s)
}
EOF
    
    return $exit_code
}

# Main execution
main() {
    # Create health check endpoint if it doesn't exist
    if [ ! -f "$HEALTH_CHECK_FILE" ]; then
        create_health_check_endpoint
    fi
    
    # Generate status report
    generate_status_report
    
    # Exit with appropriate code
    exit $?
}

# Handle command line arguments
case "${1:-}" in
    --json)
        if [ -f "$STATUS_FILE" ]; then
            cat "$STATUS_FILE"
        else
            echo '{"status": "unknown", "message": "No status file found"}'
        fi
        ;;
    --create-endpoint)
        create_health_check_endpoint
        echo "Health check endpoint created at: $HEALTH_CHECK_FILE"
        ;;
    *)
        main
        ;;
esac