FROM php:8.2-apache

# Install PHP extensions + mysql client untuk inisialisasi DB
RUN apt-get update && apt-get install -y default-mysql-client \
    && docker-php-ext-install mysqli pdo pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# ============================================================
# FIX APACHE MPM CONFLICT
# php:8.2-apache pakai mod_php yang HANYA bisa jalan dengan
# mpm_prefork. a2dismod saja tidak cukup karena file .load
# masih bisa dimuat ulang. Solusi paling andal: hapus langsung
# symlink mpm_event dan mpm_worker dari mods-enabled.
# ============================================================
RUN rm -f /etc/apache2/mods-enabled/mpm_event.conf \
           /etc/apache2/mods-enabled/mpm_event.load \
           /etc/apache2/mods-enabled/mpm_worker.conf \
           /etc/apache2/mods-enabled/mpm_worker.load \
    && a2enmod mpm_prefork rewrite

# Copy semua file project
COPY . /var/www/html/

# ============================================================
# FIX CRLF (Windows line endings) PADA ENTRYPOINT SCRIPT
# File .sh yang diedit di Windows punya \r\n (CRLF).
# Linux tidak bisa membaca shebang #!/bin/bash\r sehingga
# error "No such file or directory". sed strip \r di sini
# sehingga tidak peduli bagaimana file di-commit dari Windows.
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

EXPOSE 80

# Jalankan entrypoint (inisialisasi DB lalu Apache)
# TIDAK ada startCommand di railway.toml yang override ini
CMD ["/bin/bash", "/var/www/html/docker-entrypoint.sh"]
