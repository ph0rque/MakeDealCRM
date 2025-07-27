#!/bin/bash
set -e

# Wait for database to be ready
echo "Waiting for database connection..."
while ! mysqladmin ping -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASSWORD" --silent; do
    echo "Database is unavailable - sleeping"
    sleep 5
done

echo "Database is up - continuing..."

# Update SuiteCRM config with environment variables
if [ -f /var/www/html/config.php ]; then
    echo "Updating SuiteCRM configuration..."
    sed -i "s/'db_host_name' => '.*'/'db_host_name' => '$DB_HOST'/" /var/www/html/config.php
    sed -i "s/'db_user_name' => '.*'/'db_user_name' => '$DB_USER'/" /var/www/html/config.php
    sed -i "s/'db_password' => '.*'/'db_password' => '$DB_PASSWORD'/" /var/www/html/config.php
    sed -i "s/'db_name' => '.*'/'db_name' => '$DB_NAME'/" /var/www/html/config.php
    sed -i "s/'site_url' => '.*'/'site_url' => '$SITE_URL'/" /var/www/html/config.php
else
    echo "No config.php found - this appears to be a fresh installation"
    # Create a basic config.php for initial setup
    cat > /var/www/html/config.php <<EOF
<?php
\$sugar_config = array(
    'dbconfig' => array(
        'db_host_name' => '$DB_HOST',
        'db_host_instance' => 'SQLEXPRESS',
        'db_user_name' => '$DB_USER',
        'db_password' => '$DB_PASSWORD',
        'db_name' => '$DB_NAME',
        'db_type' => 'mysql',
        'db_port' => '$DB_PORT',
        'db_manager' => 'MysqliManager',
    ),
    'site_url' => '$SITE_URL',
    'cache_dir' => 'cache/',
    'session_dir' => '',
    'tmp_dir' => 'cache/xml/',
    'log_dir' => 'logs/',
    'log_file' => 'suitecrm.log',
    'default_module' => 'Home',
    'default_action' => 'index',
    'default_language' => 'en_us',
    'default_charset' => 'UTF-8',
    'default_theme' => 'SuiteP',
    'disabled_themes' => '',
    'admin_email' => '$ADMIN_EMAIL',
    'installer_locked' => false,
    'sugar_version' => '7.14.0',
);
?>
EOF
    chown www-data:www-data /var/www/html/config.php
    chmod 644 /var/www/html/config.php
fi

# Ensure proper permissions
chown -R www-data:www-data /var/www/html
find /var/www/html -type d -exec chmod 755 {} \;
find /var/www/html -type f -exec chmod 644 {} \;
chmod -R 775 /var/www/html/cache /var/www/html/custom /var/www/html/modules /var/www/html/themes /var/www/html/upload

# Start cron service
service cron start

# Execute the command
exec "$@"