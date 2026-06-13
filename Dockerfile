FROM php:8.2-apache

# Install PHP extensions + mysql client untuk inisialisasi DB
RUN apt-get update && apt-get install -y default-mysql-client \
    && docker-php-ext-install mysqli pdo pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Fix Apache MPM conflict:
# php8.2-apache (mod_php) HANYA bisa jalan dengan mpm_prefork.
# Secara default mpm_event aktif — ini menyebabkan crash di Railway.
# Nonaktifkan mpm_event & mpm_worker, aktifkan hanya mpm_prefork.
RUN a2dismod mpm_event mpm_worker 2>/dev/null || true \
    && a2enmod mpm_prefork \
    && a2enmod rewrite

# Copy semua file project
COPY . /var/www/html/

# Buat folder upload & set permission
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

# Gunakan entrypoint script sebagai CMD
# (JANGAN override ini di railway.toml startCommand!)
CMD ["/bin/bash", "/var/www/html/docker-entrypoint.sh"]
