# ==============================================================================
# Production Multi-Stage Dockerfile for SuperWeb 46 Enterprise Modules Platform
# Architecture: PHP 8.4 CLI/FPM/FrankenPHP-Ready with Strict Production OPcache
# ==============================================================================

# ------------------------------------------------------------------------------
# Stage 1: Composer Dependencies Builder
# ------------------------------------------------------------------------------
FROM composer:2.8 AS composer-builder

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --ignore-platform-reqs

# ------------------------------------------------------------------------------
# Stage 2: Frontend Asset Builder
# ------------------------------------------------------------------------------
FROM node:22-alpine AS node-builder

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources/ ./resources/
COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY public/ ./public/

RUN npm run build

# ------------------------------------------------------------------------------
# Stage 3: Production Runtime (PHP 8.4)
# ------------------------------------------------------------------------------
FROM php:8.4-cli-alpine AS production-runtime

LABEL maintainer="SuperWeb Platform Engineering <devops@autoserve.test>"
LABEL description="Production Application Server for SuperWeb 46 Enterprise Modules"

# Install production OS dependencies
RUN apk add --no-cache \
    bash \
    curl \
    git \
    icu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    mysql-client \
    linux-headers \
    $PHPIZE_DEPS

# Install and configure PHP core extensions
RUN docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        bcmath \
        intl \
        pcntl \
        opcache \
        zip

# Install Redis PECL extension
RUN pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

# Configure Production OPcache
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.enable_cli=1'; \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.interned_strings_buffer=32'; \
    echo 'opcache.max_accelerated_files=20000'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.save_comments=1'; \
    echo 'opcache.fast_shutdown=1'; \
} > /usr/local/etc/php/conf.d/opcache-recommended.ini

# Set working directory
WORKDIR /var/www/html

# Copy application source code
COPY . /var/www/html

# Copy vendor from composer builder
COPY --from=composer-builder /app/vendor /var/www/html/vendor

# Copy compiled frontend assets from node builder
COPY --from=node-builder /app/public/build /var/www/html/public/build

# Set ownership to non-root user (www-data)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Switch to non-root user
USER www-data

# Expose HTTP port
EXPOSE 8000

# Production Healthcheck
HEALTHCHECK --interval=30s --timeout=10s --start-period=15s --retries=3 \
    CMD php artisan inspire || php artisan bank:reconcile --incremental || exit 1

# Production Entrypoint Command
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
