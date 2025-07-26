#!/bin/bash

# Health check script for SuiteCRM CI environment
set -e

# Configuration
HEALTH_CHECK_URL="${BASE_URL:-http://localhost}/index.php"
TIMEOUT=10
MAX_RETRIES=3

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check web server
check_web_server() {
    log_info "Checking web server health..."
    
    local retry_count=0
    while [ $retry_count -lt $MAX_RETRIES ]; do
        if curl -f -s --max-time $TIMEOUT "$HEALTH_CHECK_URL" > /dev/null; then
            log_info "Web server is healthy"
            return 0
        fi
        
        retry_count=$((retry_count + 1))
        if [ $retry_count -lt $MAX_RETRIES ]; then
            log_warn "Web server check failed, retrying... ($retry_count/$MAX_RETRIES)"
            sleep 2
        fi
    done
    
    log_error "Web server health check failed after $MAX_RETRIES attempts"
    return 1
}

# Check database connection
check_database() {
    if [ -z "$DB_HOST" ]; then
        log_info "Database check skipped (DB_HOST not set)"
        return 0
    fi
    
    log_info "Checking database connection..."
    
    # Try to connect to database
    if command -v mysql > /dev/null; then
        local db_check_cmd="mysql -h${DB_HOST} -P${DB_PORT:-3306} -u${DB_USER:-root}"
        if [ "$DB_PASSWORD" ]; then
            db_check_cmd="$db_check_cmd -p${DB_PASSWORD}"
        fi
        db_check_cmd="$db_check_cmd -e 'SELECT 1;' ${DB_NAME:-suitecrm}"
        
        if $db_check_cmd > /dev/null 2>&1; then
            log_info "Database connection is healthy"
            return 0
        else
            log_error "Database connection failed"
            return 1
        fi
    else
        log_warn "MySQL client not available, skipping database check"
        return 0
    fi
}

# Check Redis connection
check_redis() {
    if [ -z "$REDIS_HOST" ]; then
        log_info "Redis check skipped (REDIS_HOST not set)"
        return 0
    fi
    
    log_info "Checking Redis connection..."
    
    if command -v redis-cli > /dev/null; then
        if redis-cli -h "${REDIS_HOST}" -p "${REDIS_PORT:-6379}" ping > /dev/null 2>&1; then
            log_info "Redis connection is healthy"
            return 0
        else
            log_error "Redis connection failed"
            return 1
        fi
    else
        # Try with netcat if redis-cli is not available
        if nc -z "$REDIS_HOST" "${REDIS_PORT:-6379}" 2>/dev/null; then
            log_info "Redis port is accessible"
            return 0
        else
            log_error "Redis connection failed"
            return 1
        fi
    fi
}

# Check file permissions
check_permissions() {
    log_info "Checking file permissions..."
    
    local web_root="/var/www/html"
    local required_dirs=("cache" "custom" "modules" "themes" "upload")
    
    if [ ! -d "$web_root" ]; then
        log_error "Web root directory not found: $web_root"
        return 1
    fi
    
    for dir in "${required_dirs[@]}"; do
        local dir_path="$web_root/$dir"
        if [ -d "$dir_path" ]; then
            if [ ! -w "$dir_path" ]; then
                log_error "Directory not writable: $dir_path"
                return 1
            fi
        else
            log_warn "Directory not found: $dir_path"
        fi
    done
    
    log_info "File permissions are correct"
    return 0
}

# Check PHP configuration
check_php() {
    log_info "Checking PHP configuration..."
    
    # Check if PHP is available
    if ! command -v php > /dev/null; then
        log_error "PHP not found"
        return 1
    fi
    
    # Check PHP version
    local php_version=$(php -r "echo PHP_VERSION;")
    log_info "PHP version: $php_version"
    
    # Check required extensions
    local required_extensions=("pdo_mysql" "mysqli" "mbstring" "xml" "zip" "gd")
    local missing_extensions=()
    
    for ext in "${required_extensions[@]}"; do
        if ! php -m | grep -q "^$ext$"; then
            missing_extensions+=("$ext")
        fi
    done
    
    if [ ${#missing_extensions[@]} -gt 0 ]; then
        log_error "Missing PHP extensions: ${missing_extensions[*]}"
        return 1
    fi
    
    log_info "PHP configuration is healthy"
    return 0
}

# Main health check function
main() {
    log_info "Starting health checks..."
    
    local exit_code=0
    
    # Run all health checks
    check_web_server || exit_code=1
    check_database || exit_code=1
    check_redis || exit_code=1
    check_permissions || exit_code=1
    check_php || exit_code=1
    
    if [ $exit_code -eq 0 ]; then
        log_info "All health checks passed"
    else
        log_error "One or more health checks failed"
    fi
    
    return $exit_code
}

# Run health checks
main "$@"