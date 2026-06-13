FROM php:8.2-apache

# Install PHP extensions + mysql client untuk inisialisasi DB
RUN apt-get update && apt-get install -y default-mysql-client \
    && docker-php-ext-install mysqli pdo pdo_mysql \
    && a2enmod rewrite \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy semua file project
COPY . /var/www/html/

# Buat folder upload
RUN mkdir -p /var/www/html/webgis-kemiskinan/uploads/bukti \
    && mkdir -p /var/www/html/webgis-kemiskinan/uploads/foto_laporan \
    && mkdir -p /var/www/html/webgis-kemiskinan/uploads/foto_profil \
    && mkdir -p /var/www/html/webgis-spbu/uploads \
    && chown -R www-data:www-data /var/www/html/webgis-kemiskinan/uploads \
    && chown -R www-data:www-data /var/www/html/webgis-spbu/uploads \
    && chown -R www-data:www-data /var/www/html

# Pastikan entrypoint bisa dieksekusi
RUN chmod +x /var/www/html/docker-entrypoint.sh

EXPOSE 80

# Jalankan entrypoint script (inisialisasi DB lalu Apache)
CMD ["/var/www/html/docker-entrypoint.sh"]
