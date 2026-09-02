# Production Dockerfile for Sate Tulang Madu Ordering & Restaurant System
FROM php:8.2-apache

# Install required system dependencies and SQLite/GD extensions
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_sqlite \
        pdo_mysql \
        gd \
        zip \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite and mod_headers
RUN a2enmod rewrite headers

# Configure PHP settings for production
RUN { \
        echo 'upload_max_filesize = 32M'; \
        echo 'post_max_size = 32M'; \
        echo 'memory_limit = 256M'; \
        echo 'max_execution_time = 120'; \
        echo 'date.timezone = Asia/Kuala_Lumpur'; \
        echo 'display_errors = Off'; \
        echo 'log_errors = On'; \
    } > /usr/local/etc/php/conf.d/satay-production.ini

# Set working directory
WORKDIR /var/www/html

# Copy application files into container
COPY . /var/www/html/

# Ensure data and uploads directory exist and have proper permissions
RUN mkdir -p /var/www/html/data /var/www/html/assets/images \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/data /var/www/html/assets/images

# Expose standard Apache HTTP port
EXPOSE 80

# Start Apache in foreground
CMD ["apache2-foreground"]
