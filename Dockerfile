FROM composer:2 AS composer

WORKDIR /app

COPY composer.json composer.lock ./

# Install dependencies
RUN composer install \
    --no-scripts \
    --no-autoloader \
    --optimize-autoloader \
    --no-interaction \
    --no-progress

# Copy rest of application
COPY . .

# Generate autoloader
RUN composer dump-autoload --optimize --no-scripts

FROM php:8.2-fpm-alpine AS base

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    bash \
    postgresql-client \
    mysql-client \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    oniguruma-dev \
    icu-dev \
    $PHPIZE_DEPS \
    autoconf \
    g++ \
    make \
    linux-headers

# Install PHP extensions
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    bcmath \
    pcntl \
    gd \
    zip \
    opcache \
    mbstring \
    intl

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

# Copy built dependencies from composer stage
COPY --from=composer --chown=www:www /app/vendor ./vendor

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
RUN apk add --no-cache $PHPIZE_DEPS linux-headers \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug

# Copy Xdebug configuration
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

# Development PHP settings (opcache.validate_timestamps already set in opcache.ini)


# Run as root in development (easier for permissions)
USER root

# Start supervisor (manages nginx + php-fpm)
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]

# Production Image

FROM base AS production

# Production PHP settings
RUN echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini

# Switch to non-root user
USER www

# Start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]