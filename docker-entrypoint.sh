#!/bin/bash
# WebGIS Project - Docker Entrypoint Script

echo "--- WebGIS Startup Script ---"

# ============================================================
# FIX: Konfigurasi Apache untuk listen pada port Railway ($PORT)
# Railway menyediakan port dinamis via env var $PORT.
# Default ke 80 jika tidak ada (untuk local/Coolify).
# ============================================================
APP_PORT="${PORT:-80}"
echo "[INFO] Mengkonfigurasi Apache pada port $APP_PORT..."
echo "Listen $APP_PORT" > /etc/apache2/ports.conf
# Update VirtualHost agar sesuai dengan port yang diberikan Railway
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${APP_PORT}>/g" \
    /etc/apache2/sites-enabled/000-default.conf 2>/dev/null || true

# Inisialisasi database hanya jika Railway MySQL tersedia
if [ -n "$MYSQLHOST" ]; then
  echo "[INFO] Menunggu MySQL siap di $MYSQLHOST:${MYSQLPORT:-3306}..."

  # Tunggu MySQL siap (max 60 detik = 30 percobaan x 2 detik)
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
    # Buat database jika belum ada
    echo "[INFO] Membuat database..."
    mysql -h "$MYSQLHOST" -P "${MYSQLPORT:-3306}" -u "$MYSQLUSER" -p"$MYSQLPASSWORD" \
      -e "CREATE DATABASE IF NOT EXISTS webgis_kemiskinan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || true
    mysql -h "$MYSQLHOST" -P "${MYSQLPORT:-3306}" -u "$MYSQLUSER" -p"$MYSQLPASSWORD" \
      -e "CREATE DATABASE IF NOT EXISTS webgis_spbu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || true

    # Cek apakah tabel sudah ada di webgis_kemiskinan
    TABLE_COUNT=$(mysql -h "$MYSQLHOST" -P "${MYSQLPORT:-3306}" -u "$MYSQLUSER" -p"$MYSQLPASSWORD" \
      -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='webgis_kemiskinan';" \
      --skip-column-names --silent 2>/dev/null || echo "0")

    # Pastikan TABLE_COUNT adalah angka murni
    TABLE_COUNT=$(echo "$TABLE_COUNT" | tr -d '[:space:]')
    if ! echo "$TABLE_COUNT" | grep -qE '^[0-9]+$'; then
      TABLE_COUNT=0
    fi

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
    echo "[WARN] MySQL tidak tersedia setelah 60 detik. Melanjutkan tanpa inisialisasi DB."
  fi
else
  echo "[WARN] MYSQLHOST tidak diset, melewati inisialisasi database."
fi

echo "=== Menjalankan Apache pada port $APP_PORT ==="
exec apache2-foreground
