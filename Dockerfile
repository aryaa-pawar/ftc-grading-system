FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libpng-dev libzip-dev libxml2-dev libonig-dev \
    && docker-php-ext-configure gd \
    && docker-php-ext-install pdo_mysql mbstring intl gd zip \
    && a2dismod mpm_event mpm_worker \
    && a2enmod mpm_prefork rewrite \
    && apache2ctl configtest \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json /tmp/composer.json
RUN composer install --no-dev --no-interaction --prefer-dist --working-dir=/tmp \
    && cp -a /tmp/vendor /var/www/html/vendor

COPY . /var/www/html/

CMD ["sh", "-c", "rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf && a2enmod mpm_prefork rewrite && sed -i \"s/^Listen .*/Listen ${PORT:-8080}/\" /etc/apache2/ports.conf && sed -i \"s/^Listen .*/Listen ${PORT:-8080}/\" /etc/apache2/sites-available/000-default.conf && apache2ctl configtest && apache2-foreground"]
