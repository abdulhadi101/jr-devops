FROM composer:2 AS composer

WORKDIR /app

COPY composer.json composer.lock ./

# Install dependencies
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-progress

# Copy rest of application
COPY . .

FROM node:20-alpine AS frontend

WORKDIR /app

COPY package*.json ./

# Install npm dependencies
RUN npm ci --no-audit

# Copy application code
COPY . .

# Build assets (Vite compiles JS/CSS)
RUN npm run build

FROM php:8.2-fpm-alpine AS base

# Install system dependencies
RUN apk add --no-cache \
    nginx \           # Web server
    supervisor \      # Process manager (runs nginx + php-fpm)
    curl \           # Health checks
    bash \           # Shell scripts
    postgresql-dev \ # PostgreSQL support
    mysql-client \   # MySQL support (optional)
    libpng-dev \     # Image processing
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    oniguruma-dev \
    icu-dev

# Install PHP extensions
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo \           # Database PDO
    pdo_mysql \     # MySQL driver
    pdo_pgsql \     # PostgreSQL driver
    bcmath \        # Math operations 
    pcntl \         # Process control
    gd \            # Image processing
    zip \           # Zip archives
    opcache \       # PHP opcode cache 
    mbstring \      # Multibyte string support
    intl            # Internationalization

# Install Redis extension (for caching)
RUN pecl install redis \
    && docker-php-ext-enable redis

# Create non-root user
RUN addgroup -g 1000 www \
    && adduser -u 1000 -G www -s /bin/sh -D www

# Set working directory
WORKDIR /var/www/html

# Copy configuration files
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/supervisor/supervisord.conf /etc/supervisord.conf

# Copy application code
COPY --chown=www:www . .

# Copy built dependencies from previous stages
COPY --from=composer --chown=www:www /app/vendor ./vendor
COPY --from=frontend --chown=www:www /app/public/build ./public/build

# Set permissions
RUN chown -R www:www /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Create required directories
RUN mkdir -p /var/www/html/storage/framework/{sessions,views,cache} \
    && mkdir -p /var/www/html/storage/logs \
    && chown -R www:www /var/www/html/storage

# Health check
HEALTHCHECK --interval=30s --timeout=10s --retries=3 --start-period=40s \
    CMD curl -f http://localhost/api/health || exit 1

# Expose port 80
EXPOSE 80

#  Development Image

FROM base AS development

# Install Xdebug (debugging)
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Copy Xdebug configuration
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

# Development PHP settings
RUN echo "opcache.validate_timestamps=1" >> /usr/local/etc/php/conf.d/opcache.ini

# Run as root in development (easier for permissions)
USER root

# Start supervisor (manages nginx + php-fpm)
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]

# Production Image

FROM base AS production

# Production PHP settings
RUN echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini

# Optimize Laravel
RUN php artisan config:cache || true \
    && php artisan route:cache || true \
    && php artisan view:cache || true

# Switch to non-root user
USER www

# Start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]