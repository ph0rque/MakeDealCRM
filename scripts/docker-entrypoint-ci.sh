#!/bin/bash
set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}Starting SuiteCRM CI Environment...${NC}"

# Function to wait for service
wait_for_service() {
    local host=$1
    local port=$2
    local service_name=$3
    local max_attempts=30
    local attempt=1

    echo -e "${YELLOW}Waiting for $service_name ($host:$port)...${NC}"
    
    while [ $attempt -le $max_attempts ]; do
        if nc -z "$host" "$port" 2>/dev/null; then
            echo -e "${GREEN}$service_name is ready!${NC}"
            return 0
        fi
        
        echo "Attempt $attempt/$max_attempts: $service_name not ready yet..."
        sleep 2
        attempt=$((attempt + 1))
    done
    
    echo -e "${RED}$service_name failed to become ready within $(($max_attempts * 2)) seconds${NC}"
    return 1
}

# Wait for dependencies
if [ "$DB_HOST" != "localhost" ] && [ "$DB_HOST" != "127.0.0.1" ]; then
    wait_for_service "$DB_HOST" "${DB_PORT:-3306}" "MySQL"
fi

if [ "$REDIS_HOST" ] && [ "$REDIS_HOST" != "localhost" ] && [ "$REDIS_HOST" != "127.0.0.1" ]; then
    wait_for_service "$REDIS_HOST" "${REDIS_PORT:-6379}" "Redis"
fi

# Run initialization scripts
echo -e "${YELLOW}Running initialization scripts...${NC}"
for script in /docker-entrypoint-init.d/*.sh; do
    if [ -f "$script" ]; then
        echo "Running $script..."
        bash "$script"
    fi
done

# Initialize SuiteCRM if needed
if [ ! -f "/var/www/html/config.php" ] && [ -f "/var/www/html/config_override.php" ]; then
    echo -e "${YELLOW}Initializing SuiteCRM configuration...${NC}"
    
    # Copy base config if it doesn't exist
    if [ -f "/var/www/html/config_si.php" ]; then
        cp /var/www/html/config_si.php /var/www/html/config.php
    fi
    
    # Set proper permissions
    chown -R www-data:www-data /var/www/html
    chmod -R 755 /var/www/html
    chmod -R 775 /var/www/html/cache /var/www/html/custom /var/www/html/modules /var/www/html/themes /var/www/html/upload
fi

# Clear cache
echo -e "${YELLOW}Clearing SuiteCRM cache...${NC}"
rm -rf /var/www/html/cache/modules/*
rm -rf /var/www/html/cache/themes/*
rm -rf /var/www/html/cache/smarty/*

# Set timezone
if [ "$TZ" ]; then
    echo "Setting timezone to $TZ"
    ln -snf /usr/share/zoneinfo/$TZ /etc/localtime
    echo $TZ > /etc/timezone
fi

# Start supervisor for background processes
if [ -f "/etc/supervisor/conf.d/supervisord.conf" ]; then
    echo -e "${YELLOW}Starting supervisor...${NC}"
    supervisord -c /etc/supervisor/conf.d/supervisord.conf &
fi

# Start Apache
echo -e "${GREEN}Starting Apache...${NC}"
exec "$@"