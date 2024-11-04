# Stage 1: Build stage
FROM php:8.1-fpm-alpine AS builder

# Set working directory
WORKDIR /var/www/html

# Install dependencies
RUN apk update && apk add --no-cache \
    git \
    zip \
    unzip \
    curl \
    bash \
    libpng-dev \
    oniguruma-dev \
    autoconf \
    g++ \
    make \
    icu-dev \
    gettext-dev \
    libzip-dev

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd intl gettext zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set Composer to allow root user (to prevent warnings)
ENV COMPOSER_ALLOW_SUPERUSER=1

# Copy application files
COPY . .

# Install dependencies
RUN composer install --optimize-autoloader --no-dev

# Stage 2: Production stage
FROM php:8.1-fpm-alpine

# Set working directory
WORKDIR /var/www/html

# Copy built application files from the build stage
COPY --from=builder /var/www/html /var/www/html

# Ensure storage and cache directories exist and set permissions
RUN mkdir -p /var/www/html/storage /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Expose the port for the PHP-FPM server
EXPOSE 8765

# Set entrypoint and command
CMD ["php-fpm"]
