FROM php:8.2-apache

# Install PHP extensions + mysql client untuk inisialisasi DB
RUN apt-get update && apt-get install -y default-mysql-client \
    && docker-php-ext-install mysqli pdo pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# ============================================================
# FIX APACHE MPM CONFLICT (build-time)
# Hapus SEMUA file mpm_* dengan glob pattern lalu enable
# hanya mpm_prefork yang kompatibel dengan mod_php.
# Fix runtime juga ada di docker-entrypoint.sh sebagai
# lapisan kedua untuk bypass Docker layer cache.
# ============================================================
RUN rm -f /etc/apache2/mods-enabled/mpm_*.conf \
           /etc/apache2/mods-enabled/mpm_*.load \
    && a2enmod mpm_prefork rewrite \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Copy semua file project
COPY . /var/www/html/

# Strip CRLF dari entrypoint + set executable
RUN sed -i 's/\r$//' /var/www/html/docker-entrypoint.sh \
    && chmod +x /var/www/html/docker-entrypoint.sh

# Buat folder upload & set permission
RUN mkdir -p /var/www/html/webgis-kemiskinan/uploads/bukti \
    && mkdir -p /var/www/html/webgis-kemiskinan/uploads/foto_laporan \
    && mkdir -p /var/www/html/webgis-kemiskinan/uploads/foto_profil \
    && mkdir -p /var/www/html/webgis-spbu/uploads \
    && chown -R www-data:www-data /var/www/html/webgis-kemiskinan/uploads \
    && chown -R www-data:www-data /var/www/html/webgis-spbu/uploads \
    && chown -R www-data:www-data /var/www/html

# Biarkan Railway yang menentukan dan inject $PORT secara dinamis.
# DILARANG keras menggunakan EXPOSE atau ENV PORT statis!

CMD ["/bin/bash", "/var/www/html/docker-entrypoint.sh"]
