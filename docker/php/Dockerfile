FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    postgresql-dev \
    postgresql-client \
    oniguruma-dev \
    linux-headers \
    supervisor \
    $PHPIZE_DEPS

# Install PHP extensions
RUN docker-php-ext-install pdo_pgsql pgsql mbstring pcntl posix opcache bcmath

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/backend

# Create system user to run Composer and Artisan Commands
RUN addgroup -g 1000 laravel && adduser -G laravel -g laravel -s /bin/sh -D -u 1000 laravel

USER laravel

EXPOSE 9000
CMD ["php-fpm"]
