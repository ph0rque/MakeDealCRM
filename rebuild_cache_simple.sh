#!/bin/bash

# Simple cache rebuild for SuiteCRM

echo "=== Rebuilding SuiteCRM Cache ==="

# Set permissions
echo "1. Setting permissions..."
docker exec suitecrm chown -R www-data:www-data /var/www/html/cache
docker exec suitecrm chown -R www-data:www-data /var/www/html/custom
docker exec suitecrm chmod -R 775 /var/www/html/cache
docker exec suitecrm chmod -R 775 /var/www/html/custom

# Create cache directories
echo "2. Creating cache directories..."
docker exec suitecrm mkdir -p /var/www/html/cache/themes
docker exec suitecrm mkdir -p /var/www/html/cache/jsLanguage
docker exec suitecrm mkdir -p /var/www/html/cache/modules
docker exec suitecrm mkdir -p /var/www/html/cache/images
docker exec suitecrm mkdir -p /var/www/html/cache/layout
docker exec suitecrm mkdir -p /var/www/html/cache/pdf
docker exec suitecrm mkdir -p /var/www/html/cache/xml
docker exec suitecrm mkdir -p /var/www/html/cache/include

# Set ownership
docker exec suitecrm chown -R www-data:www-data /var/www/html/cache

# Rebuild htaccess
echo "3. Checking .htaccess..."
docker exec suitecrm bash -c "if [ ! -f /var/www/html/.htaccess ]; then cp /var/www/html/.htaccess.example /var/www/html/.htaccess 2>/dev/null || echo '.htaccess not found'; fi"

# Clear browser instruction
echo ""
echo "=== Cache rebuild complete ==="
echo ""
echo "Now please:"
echo "1. Go to: http://localhost:8080/index.php?module=Administration&action=repair"
echo "2. Click 'Quick Repair and Rebuild'"
echo "3. Execute any SQL changes if prompted"
echo "4. Clear your browser cache (Ctrl+F5 or Cmd+Shift+R)"
echo ""
echo "If you still see language keys instead of text:"
echo "- Go to Admin → Repair → Rebuild JS Language Files"
echo "- Go to Admin → Repair → Rebuild Extensions"