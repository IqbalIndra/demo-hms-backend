FROM php:7.2-apache

# Install required packages
RUN apt-get update && apt-get install -y \
    libzip-dev \
    npm \
    curl

# Clean sources
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install URL rewrite module
RUN a2enmod rewrite

# Install php dependencies
RUN docker-php-ext-install pdo_mysql zip

# Copy Laravel app files
COPY . /var/www/html

# Set write permissions to used folders
RUN chown -R www-data:www-data /var/www/html /var/www/html/storage /var/www/html/bootstrap/cache

# Change working directory to Laravel app root
WORKDIR /var/www/html

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
# Install Laravel dependencies
RUN composer install --no-dev --optimize-autoloader

RUN npm install npm@latest -g && \
    npm install n -g && \
    n latest

RUN npm run build

# Clear cache
RUN php artisan optimize:clear

# Expose port 80 for Apache
EXPOSE 80
