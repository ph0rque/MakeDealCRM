FROM php:7.4-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libzip-dev \
    libcurl4-openssl-dev \
    libssl-dev \
    libmcrypt-dev \
    libxml2-dev \
    libicu-dev \
    libldap2-dev \
    libc-client-dev \
    libkrb5-dev \
    libonig-dev \
    unzip \
    curl \
    git \
    cron \
    && rm -rf /var/lib/apt/lists/*

# Configure PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install -j$(nproc) \
        gd \
        mysqli \
        pdo \
        pdo_mysql \
        zip \
        curl \
        xml \
        mbstring \
        intl \
        ldap \
        imap \
        bcmath \
        soap

# Install additional PHP extensions
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set PHP configuration
RUN { \
        echo 'memory_limit=512M'; \
        echo 'upload_max_filesize=50M'; \
        echo 'post_max_size=50M'; \
        echo 'max_execution_time=600'; \
        echo 'max_input_time=600'; \
        echo 'error_reporting=E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED'; \
        echo 'display_errors=Off'; \
        echo 'log_errors=On'; \
        echo 'date.timezone=UTC'; \
    } > /usr/local/etc/php/conf.d/suitecrm.ini

# Enable Apache modules
RUN a2enmod rewrite expires headers

# Set up Apache configuration
RUN { \
        echo '<VirtualHost *:80>'; \
        echo '    DocumentRoot /var/www/html'; \
        echo '    <Directory /var/www/html>'; \
        echo '        Options Indexes FollowSymLinks'; \
        echo '        AllowOverride All'; \
        echo '        Require all granted'; \
        echo '    </Directory>'; \
        echo '    ErrorLog ${APACHE_LOG_DIR}/error.log'; \
        echo '    CustomLog ${APACHE_LOG_DIR}/access.log combined'; \
        echo '</VirtualHost>'; \
    } > /etc/apache2/sites-available/000-default.conf

# Set working directory
WORKDIR /var/www/html

# Copy SuiteCRM files
COPY ./SuiteCRM /var/www/html

# Run composer install
WORKDIR /var/www/html
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/cache \
    && chmod -R 775 /var/www/html/custom \
    && chmod -R 775 /var/www/html/modules \
    && chmod -R 775 /var/www/html/upload \
    && chmod 775 /var/www/html/config_override.php 2>/dev/null || true

# Create cron job for SuiteCRM
RUN echo "* * * * * www-data cd /var/www/html && php -f cron.php > /dev/null 2>&1" > /etc/cron.d/suitecrm \
    && chmod 0644 /etc/cron.d/suitecrm \
    && crontab -u www-data /etc/cron.d/suitecrm

# Start cron service
RUN service cron start

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]