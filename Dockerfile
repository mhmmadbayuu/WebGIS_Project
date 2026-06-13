FROM php:8.2-apache

# Install PHP extensions + mysql client untuk inisialisasi DB
RUN apt-get update && apt-get install -y default-mysql-client \
    && docker-php-ext-install mysqli pdo pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# ============================================================
# FIX APACHE MPM CONFLICT - GLOB PATTERN
# Hapus SEMUA file mpm_* di mods-enabled dengan glob agar
# tidak ada satupun MPM yang tertinggal (termasuk yang
# di-enable ulang oleh apt-get selama instalasi).
# Setelah bersih, aktifkan hanya mpm_prefork (wajib untuk mod_php).
# ============================================================
RUN rm -f /etc/apache2/mods-enabled/mpm_*.conf \
           /etc/apache2/mods-enabled/mpm_*.load \
    && a2enmod mpm_prefork rewrite

# Copy semua file project
COPY . /var/www/html/

# ============================================================
# FIX CRLF - strip Windows line endings dari entrypoint
# Dilakukan SETELAH COPY agar berfungsi terlepas dari
# bagaimana file di-commit/diedit di Windows.
# ============================================================
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

# Expose port default (Railway akan override via $PORT)
EXPOSE 80

CMD ["/bin/bash", "/var/www/html/docker-entrypoint.sh"]
