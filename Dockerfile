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

RUN php artisan scribe:generate || true

FROM php:8.2-fpm-alpine AS base

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    bash \
    postgresql-dev \
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

# Install Redis extension
RUN pecl install redis \
    && docker-php-ext-enable redis

# Create non-root user
RUN addgroup -g 1000 www \
    && adduser -u 1000 -G www -s /bin/sh -D www \
    && sed -i 's/user = www-data/user = www/g' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/group = www-data/group = www/g' /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html

# Copy configuration files
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/supervisor/supervisord.conf /etc/supervisord.conf

# Copy application code
COPY --chown=www:www . .

# Copy built dependencies AND generated docs from composer stage
COPY --from=composer --chown=www:www /app/vendor ./vendor
COPY --from=composer --chown=www:www /app/public/vendor/scribe ./public/vendor/scribe
COPY --from=composer --chown=www:www /app/resources/views/scribe ./resources/views/scribe

# Set permissions
RUN chown -R www:www /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Create required directories
RUN mkdir -p /var/www/html/storage/framework/{sessions,views,cache} \
    && mkdir -p /var/www/html/storage/logs \
    && chown -R www:www /var/www/html/storage

# Create Nginx directories
RUN mkdir -p /var/lib/nginx/tmp/client_body \
    && chown -R www:www /var/lib/nginx \
    && chown -R www:www /var/log/nginx

# Health check
HEALTHCHECK --interval=30s --timeout=10s --retries=3 --start-period=40s \
    CMD curl -f http://localhost/api/health || exit 1

EXPOSE 80

# Production Image
FROM base AS production
RUN echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini
USER root
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]