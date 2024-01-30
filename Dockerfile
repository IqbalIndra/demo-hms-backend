FROM ubuntu:latest AS base

# Specify the variable you need
ARG RAILWAY_SERVICE_NAME
ARG DB_HOST
ARG DB_PORT
ARG DB_DATABASE
ARG DB_USERNAME
ARG DB_PASSWORD
ARG APP_URL
ARG PORT
ARG APP_KEY
ARG APP_ENV

ENV DB_HOST=$DB_HOST
ENV DB_PORT=$DB_PORT
ENV DB_DATABASE=$DB_DATABASE
ENV DB_USERNAME=$DB_USERNAME
ENV DB_PASSWORD=$DB_PASSWORD
ENV APP_URL=$APP_URL
ENV APP_KEY=$APP_KEY
ENV PORT=$PORT
ENV APP_ENV=$APP_ENV
ENV COMPOSER_ALLOW_SUPERUSER=1


ENV DEBIAN_FRONTEND noninteractive

# Install dependencies
RUN apt update
RUN apt install -y software-properties-common
RUN add-apt-repository -y ppa:ondrej/php
RUN apt update
RUN apt install -y php7.2\
    php7.2-cli\
    php7.2-common\
    php7.2-fpm\
    php7.2-mysql\
    php7.2-zip\
    php7.2-gd\
    php7.2-mbstring\
    php7.2-curl\
    php7.2-xml\
    php7.2-bcmath\
    php7.2-pdo

# Install php-fpm
RUN apt install -y php7.2-fpm php7.2-cli

# Install composer
RUN apt install -y curl
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install nodejs
RUN apt install -y ca-certificates gnupg
RUN mkdir -p /etc/apt/keyrings
RUN curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg
ENV NODE_MAJOR 20
RUN echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_$NODE_MAJOR.x nodistro main" | tee /etc/apt/sources.list.d/nodesource.list
RUN apt update
RUN apt install -y nodejs

# Install nginx
RUN apt install -y nginx
RUN echo "\
    server {\n\
        listen 80;\n\
        listen [::]:80;\n\
        server_name _;\n\
        root /var/www/html/public;\n\
        sendfile off;\n\
        add_header X-Frame-Options \"SAMEORIGIN\";\n\
        add_header X-Content-Type-Options \"nosniff\";\n\
        add_header Access-Control-Allow-Origin \'*\' always;\n\
        add_header Access-Control-Allow-Methods \'*\' always;\n\
        add_header Access-Control-Allow-Headers \'*\' always;\n\
        index index.html index.htm index.php;\n\
        charset utf-8;\n\
        location / {\n\
            try_files \$uri \$uri/ /index.php?\$query_string;\n\
        }\n\
        location = /favicon.ico { access_log off; log_not_found off; }\n\
        location = /robots.txt  { access_log off; log_not_found off; }\n\
        error_page 404 /index.php;\n\
        location ~ \.php$ {\n\
            fastcgi_pass unix:/run/php/php7.2-fpm.sock;\n\
            fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;\n\
            include fastcgi_params;\n\
        }\n\
        location ~ /\.(?!well-known).* {\n\
            deny all;\n\
        }\n\
    }\n" > /etc/nginx/sites-available/default

RUN echo "\
    #!/bin/sh\n\
    echo \"Starting services...\"\n\
    service php7.2-fpm start\n\
    nginx -g \"daemon off;\" &\n\
    echo \"Ready.\"\n\
    tail -s 1 /var/log/nginx/*.log -f\n\
    " > /start.sh

COPY . /var/www/html
WORKDIR /var/www/html

RUN chown -R www-data:www-data /var/www/html

RUN composer install --no-dev --optimize-autoloader

#copy .env from .env.example
RUN composer run-script post-root-package-install

RUN echo "Generating application key..."
RUN php artisan key:generate


# Clear cache
RUN php artisan optimize:clear

RUN echo "Caching config..."
RUN php artisan config:cache


# migration
RUN php artisan migrate --force

RUN echo "Installing Passport..."
RUN php artisan passport:install

RUN echo "Starting queue worker in the background..."
RUN nohup php artisan queue:work --daemon >> storage/logs/laravel.log &

RUN echo "PORT : ${PORT}"


EXPOSE 80

CMD ["sh", "/start.sh"]
