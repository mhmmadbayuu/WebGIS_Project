#!/bin/bash
# WebGIS Project - Docker Entrypoint Script

echo "--- WebGIS Startup Script ---"

# ============================================================
# FIX MPM CONFLICT DI RUNTIME (bukan hanya saat build)
# Docker layer cache bisa menyebabkan fix di Dockerfile tidak
# efektif. Dengan fix di sini, MPM diperbaiki SETIAP container
# start, terlepas dari cache atau versi image apapun.
# ============================================================
echo "[INFO] Memastikan hanya mpm_prefork yang aktif..."
rm -f /etc/apache2/mods-enabled/mpm_event.conf \
       /etc/apache2/mods-enabled/mpm_event.load \
       /etc/apache2/mods-enabled/mpm_worker.conf \
       /etc/apache2/mods-enabled/mpm_worker.load 2>/dev/null || true
a2enmod mpm_prefork 2>/dev/null || true

# ============================================================
# KONFIGURASI PORT APACHE
# Railway assign port DINAMIS via $PORT (bisa 8080, 3000, dll).
# URL publik (https://....railway.app) tetap port 443 HTTPS.
# Apache harus listen pada $PORT agar Railway bisa route traffic.
# Default ke 80 jika tidak ada $PORT (local/Coolify).
# ============================================================
APP_PORT="${PORT:-80}"
echo "[INFO] Mengkonfigurasi Apache pada port $APP_PORT..."
cat > /etc/apache2/ports.conf << EOF
Listen $APP_PORT
EOF
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${APP_PORT}>/g" \
    /etc/apache2/sites-enabled/000-default.conf 2>/dev/null || true

# ============================================================
# INISIALISASI DATABASE
# ============================================================
if [ -n "$MYSQLHOST" ]; then
  echo "[INFO] Menunggu MySQL siap di $MYSQLHOST:${MYSQLPORT:-3306}..."

  READY=0
  for i in $(seq 1 30); do
    if mysqladmin ping -h "$MYSQLHOST" -P "${MYSQLPORT:-3306}" -u "$MYSQLUSER" -p"$MYSQLPASSWORD" --silent 2>/dev/null; then
      echo "[OK] MySQL siap!"
      READY=1
      break
    fi
    echo "[WAIT] Percobaan $i/30..."
    sleep 2
  done

  if [ "$READY" = "1" ]; then
    echo "[INFO] Membuat database jika belum ada..."
    mysql -h "$MYSQLHOST" -P "${MYSQLPORT:-3306}" -u "$MYSQLUSER" -p"$MYSQLPASSWORD" \
      -e "CREATE DATABASE IF NOT EXISTS webgis_kemiskinan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || true
    mysql -h "$MYSQLHOST" -P "${MYSQLPORT:-3306}" -u "$MYSQLUSER" -p"$MYSQLPASSWORD" \
      -e "CREATE DATABASE IF NOT EXISTS webgis_spbu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || true

    TABLE_COUNT=$(mysql -h "$MYSQLHOST" -P "${MYSQLPORT:-3306}" -u "$MYSQLUSER" -p"$MYSQLPASSWORD" \
      -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='webgis_kemiskinan';" \
      --skip-column-names --silent 2>/dev/null || echo "0")
    TABLE_COUNT=$(echo "$TABLE_COUNT" | tr -d '[:space:]')
    if ! echo "$TABLE_COUNT" | grep -qE '^[0-9]+$'; then TABLE_COUNT=0; fi

    if [ "$TABLE_COUNT" -lt "3" ]; then
      echo "[INFO] Mengimpor schema webgis_kemiskinan..."
      mysql -h "$MYSQLHOST" -P "${MYSQLPORT:-3306}" -u "$MYSQLUSER" -p"$MYSQLPASSWORD" \
        webgis_kemiskinan < /var/www/html/init-db/01-schema.sql 2>/dev/null || true
      echo "[INFO] Mengimpor schema webgis_spbu..."
      mysql -h "$MYSQLHOST" -P "${MYSQLPORT:-3306}" -u "$MYSQLUSER" -p"$MYSQLPASSWORD" \
        webgis_spbu < /var/www/html/init-db/02-schema_webgis_spbu.sql 2>/dev/null || true
      echo "[OK] Database diinisialisasi!"
    else
      echo "[INFO] Database sudah ada ($TABLE_COUNT tabel), melewati inisialisasi."
    fi
  else
    echo "[WARN] MySQL tidak tersedia setelah 60 detik. Lanjut tanpa inisialisasi DB."
  fi
else
  echo "[WARN] MYSQLHOST tidak diset, melewati inisialisasi database."
fi

echo "=== Menjalankan Apache pada port $APP_PORT ==="
exec apache2-foreground
