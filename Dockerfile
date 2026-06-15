FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    git curl zip unzip libpng-dev libonig-dev \
    libxml2-dev libzip-dev libicu-dev libcurl4-openssl-dev \
    nginx supervisor \
    && docker-php-ext-install \
        pdo_mysql mbstring exif pcntl bcmath zip intl curl gd

RUN pecl install redis && docker-php-ext-enable redis


COPY --from=composer:latest /usr/bin/composer /usr/bin/composer


WORKDIR /var/www/html

COPY composer.json composer.lock ./


RUN composer install --no-dev --optimize-autoloader --no-scripts --no-autoloader

COPY . .


RUN composer dump-autoload --optimize


RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache


COPY nginx.conf /etc/nginx/sites-available/default

EXPOSE 80


COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
