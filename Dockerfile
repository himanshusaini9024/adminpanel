FROM php:8.2-cli

WORKDIR /app

COPY . .

RUN apt-get update && apt-get install -y git unzip \
    && docker-php-ext-install exif \
    && curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/composer

RUN composer install --no-dev --optimize-autoloader

CMD php artisan serve --host=0.0.0.0 --port=$PORT