FROM php:8.2-apache

# Extensions nécessaires
RUN docker-php-ext-install pdo pdo_mysql

# Activer mod_rewrite
RUN a2enmod rewrite

# Pointer Apache sur /www et autoriser .htaccess
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/www|g' \
        /etc/apache2/sites-available/000-default.conf \
 && sed -i 's|<Directory /var/www/html>|<Directory /var/www/html/www>|g' \
        /etc/apache2/apache2.conf \
 && sed -i 's/AllowOverride None/AllowOverride All/g' \
        /etc/apache2/apache2.conf

# Copier le projet
COPY . /var/www/html/

# Permissions sur les dossiers d'upload
RUN mkdir -p /var/www/html/www/web/public/qrcodes \
             /var/www/html/www/web/public/uploads/medecins \
             /var/www/html/www/download \
 && chown -R www-data:www-data /var/www/html/www

WORKDIR /var/www/html
