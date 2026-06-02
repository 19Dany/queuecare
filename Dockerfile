FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql \
    && (a2dismod mpm_event mpm_worker || true) \
    && a2enmod mpm_prefork rewrite \
    && sed -ri 's!/var/www/html!/var/www/html/www!g' /etc/apache2/sites-available/000-default.conf /etc/apache2/apache2.conf

COPY . /var/www/html/
