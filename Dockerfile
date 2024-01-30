FROM php:7.2-apache

# Specify the variable you need
ARG RAILWAY_SERVICE_NAME
ARG DB_HOST
ARG DB_PORT
ARG DB_DATABASE
ARG DB_USERNAME
ARG DB_PASSWORD

ENV DB_HOST=$DB_HOST
ENV DB_PORT=$DB_PORT
ENV DB_DATABASE=$DB_DATABASE
ENV DB_USERNAME=$DB_USERNAME
ENV DB_PASSWORD=$DB_PASSWORD


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

#copy .env from .env.example
RUN composer run-script post-root-package-install

RUN npm install

RUN npm run prod

# Clear cache
RUN php artisan optimize:clear

# migration
RUN php artisan migrate --force

RUN php artisan up

# Expose port 80 for Apache
EXPOSE 80
