FROM php:8.3-cli-alpine

# Install system dependencies & PostgreSQL dev libraries
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
    linux-headers

# Install PHP extensions for PostgreSQL and Laravel
RUN docker-php-ext-install pdo_pgsql pgsql mbstring bcmath opcache pcntl

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy application source code
COPY . /app

# Install production PHP dependencies (skip scripts during build)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --prefer-dist

# Create storage structure and ensure permissions
RUN mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache storage/logs bootstrap/cache \
    && chmod -R 777 storage bootstrap/cache

EXPOSE 8000

# Boot Laravel cleanly at runtime using the dynamic PORT provided by Render
CMD ["sh", "-c", "php artisan package:discover --ansi && php artisan config:clear && php artisan serve --host=0.0.0.0 --port=${PORT:-8000}"]
