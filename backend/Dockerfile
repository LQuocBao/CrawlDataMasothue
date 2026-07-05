#=============================================================================
# Multi-stage Dockerfile for Laravel Backend
#=============================================================================

# ─── Base Stage ────────────────────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS base

# Install system dependencies
RUN apk add --no-cache \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    zip \
    unzip \
    git \
    icu-dev \
    oniguruma-dev \
    libzip-dev \
    supervisor

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        gd \
        intl \
        zip \
        bcmath \
        opcache \
        pcntl

# Install Redis extension
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# ─── Development Stage ─────────────────────────────────────────────────────
FROM base AS development

# Development-specific PHP config
RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/custom.ini \
    && echo "upload_max_filesize=50M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "post_max_size=50M" >> /usr/local/etc/php/conf.d/custom.ini

# Copy composer.json first (no lock file for fresh project)
COPY composer.json ./

# Install dependencies without scripts (artisan not available yet)
RUN composer install --no-scripts --no-autoloader --prefer-dist

# Copy all source code (including artisan)
COPY . .

# Now dump autoload (artisan exists at this point)
RUN composer dump-autoload --optimize

EXPOSE 8000

# ─── Production Stage ──────────────────────────────────────────────────────
FROM base AS production

# Production PHP config
RUN echo "memory_limit=256M" > /usr/local/etc/php/conf.d/custom.ini \
    && echo "upload_max_filesize=20M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "post_max_size=20M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/custom.ini

# Copy composer.json
COPY composer.json ./

# Install dependencies (no dev, no scripts)
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-dev

# Copy all source code
COPY . .

# Dump autoload
RUN composer dump-autoload --optimize --classmap-authoritative

# Set permissions
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
