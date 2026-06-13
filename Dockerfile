FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql \
    && a2enmod rewrite

COPY . /var/www/html/

RUN mkdir -p /var/www/html/webgis-kemiskinan/uploads/bukti \
    && mkdir -p /var/www/html/webgis-kemiskinan/uploads/foto_laporan \
    && mkdir -p /var/www/html/webgis-kemiskinan/uploads/foto_profil \
    && mkdir -p /var/www/html/webgis-spbu/uploads \
    && chown -R www-data:www-data /var/www/html/webgis-kemiskinan/uploads \
    && chown -R www-data:www-data /var/www/html/webgis-spbu/uploads \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80
