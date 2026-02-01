# 1. Official PHP 8.2 image with Apache
FROM php:8.2-apache

# 2. Set working directory
WORKDIR /var/www/html

# 3. Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev \
    libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 4. Install Composer globally
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 5. Copy the rest of the application code FIRST
COPY . .

# 6. Install PHP dependencies
RUN composer install --no-interaction --no-dev --optimize-autoloader --no-scripts

# 7. Set correct permissions for Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# 8. Enable Apache mod_rewrite for Laravel
RUN a2enmod rewrite

# 9. Expose HTTP port
EXPOSE 80

# 10. Default command to start Apache
CMD ["apache2-foreground"]