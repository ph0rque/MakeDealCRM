# MakeDealCRM Docker Configuration
FROM php:7.4-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    libxml2-dev \
    libldap2-dev \
    libcurl4-openssl-dev \
    libssl-dev \
    git \
    unzip \
    vim \
    cron \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    gd \
    mysqli \
    pdo \
    pdo_mysql \
    zip \
    intl \
    xml \
    soap \
    ldap \
    bcmath \
    opcache

# Install IMAP extension
RUN apt-get update && apt-get install -y libc-client-dev libkrb5-dev \
    && docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install imap \
    && rm -rf /var/lib/apt/lists/*

# Configure Apache
RUN a2enmod rewrite headers expires deflate
COPY apache-config.conf /etc/apache2/sites-available/000-default.conf

# Set recommended PHP.ini settings
RUN { \
    echo 'memory_limit=512M'; \
    echo 'upload_max_filesize=50M'; \
    echo 'post_max_size=50M'; \
    echo 'max_execution_time=300'; \
    echo 'max_input_time=300'; \
    echo 'max_input_vars=10000'; \
    echo 'session.gc_maxlifetime=86400'; \
    echo 'error_reporting=E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE & ~E_WARNING'; \
    echo 'display_errors=Off'; \
    echo 'log_errors=On'; \
    echo 'date.timezone=UTC'; \
    } > /usr/local/etc/php/conf.d/suitecrm.ini

# OPcache settings for production
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.interned_strings_buffer=16'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.revalidate_freq=0'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.save_comments=1'; \
    echo 'opcache.fast_shutdown=1'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# Create application directory
WORKDIR /var/www/html

# Copy SuiteCRM files
COPY --chown=www-data:www-data ./SuiteCRM /var/www/html

# Set proper permissions
RUN find . -type d -exec chmod 755 {} \; \
    && find . -type f -exec chmod 644 {} \; \
    && chmod -R 775 cache custom modules themes upload \
    && chown -R www-data:www-data .

# Create cron job for SuiteCRM scheduler
RUN echo "* * * * * cd /var/www/html && php -f cron.php > /dev/null 2>&1" | crontab -u www-data -

# Copy supervisord configuration
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost/index.php || exit 1

# Expose port
EXPOSE 80

# Set entrypoint
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["/usr/bin/supervisord"]