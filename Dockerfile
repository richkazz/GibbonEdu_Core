# Stage 1: Build stage
FROM php:8.1-fpm-alpine AS builder

WORKDIR /var/www/html

# Install dependencies (as before)
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

RUN docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd intl gettext zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY . .
RUN composer install --optimize-autoloader --no-dev


# Stage 2: Production stage
FROM php:8.1-fpm-alpine

WORKDIR /var/www/html

COPY --from=builder /var/www/html /var/www/html

# Copy Nginx configuration (now from the project directory)
COPY nginx.conf /etc/nginx/nginx.conf

EXPOSE 80
CMD ["sh", "-c", "nginx && php-fpm"]