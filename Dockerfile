# Build stage
FROM php:8.2-fpm AS build

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    sqlite3 \
    libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite mbstring exif pcntl bcmath opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Install dependencies (no dev for production)
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy application code
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize

# Production stage
FROM php:8.2-fpm-alpine AS production

# Install runtime dependencies only
RUN apk add --no-cache \
    sqlite \
    libpng \
    oniguruma \
    libxml2 \
    nginx \
    supervisor

# Install PHP extensions
RUN docker-php-ext-install pdo_sqlite mbstring bcmath opcache

# Configure PHP for production
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Copy OPcache configuration
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Copy Nginx configuration
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Copy Supervisor configuration
COPY docker/supervisor/supervisord.conf /etc/supervisord.conf

WORKDIR /var/www/html

# Copy application from build stage
COPY --from=build /var/www/html /var/www/html

# Create storage and cache directories
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache database

# Set permissions for www-data
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache database

# Create SQLite database file
RUN touch database/database.sqlite && chown www-data:www-data database/database.sqlite

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/api/health || exit 1

# Expose port
EXPOSE 80

# Run as non-root user
USER www-data

# Switch back to root for supervisor
USER root

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
