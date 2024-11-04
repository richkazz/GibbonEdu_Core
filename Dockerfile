# Stage 1: Build stage
FROM php:8.1-fpm-alpine AS builder

WORKDIR /var/www/html

# Install dependencies
RUN apk update && apk add --no-cache \
    git \
    zip \
    unzip \
    curl \
    bash \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    autoconf \
    g++ \
    make \
    icu-dev \
    gettext-dev \
    libzip-dev

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd gettext zip intl

# Install PHP extensions *IN THE BUILDER STAGE*
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd gettext zip intl

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY . .
RUN composer install --optimize-autoloader --no-dev


# Stage 2: Production stage
FROM php:8.1-fpm-alpine

WORKDIR /var/www/html

COPY --from=builder /var/www/html /var/www/html

RUN chown -R www-data:www-data /var/www/html/uploads

# Copy Nginx configuration
COPY nginx.conf /etc/nginx/nginx.conf

RUN apk add --no-cache nginx

EXPOSE 80
CMD ["sh", "-c", "nginx && php-fpm"]