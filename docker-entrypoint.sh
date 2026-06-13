#!/bin/bash
# WebGIS Project - Docker Entrypoint Script

echo "--- WebGIS Startup Script ---"

# ============================================================
# FIX MPM CONFLICT DI RUNTIME (lapisan kedua setelah Dockerfile)
# ============================================================
echo "[INFO] Memastikan hanya mpm_prefork yang aktif..."
rm -f /etc/apache2/mods-enabled/mpm_event.conf \
       /etc/apache2/mods-enabled/mpm_event.load \
       /etc/apache2/mods-enabled/mpm_worker.conf \
       /etc/apache2/mods-enabled/mpm_worker.load 2>/dev/null || true
a2enmod mpm_prefork 2>/dev/null || true

# ============================================================
# KONFIGURASI PORT APACHE (Railway assign $PORT dinamis)
# ============================================================
APP_PORT="${PORT:-80}"
echo "[INFO] Mengkonfigurasi Apache pada port $APP_PORT..."
cat > /etc/apache2/ports.conf << EOF
Listen $APP_PORT
EOF
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${APP_PORT}>/g" \
    /etc/apache2/sites-enabled/000-default.conf 2>/dev/null || true

# ============================================================
# PARSE MYSQL_PRIVATE_URL SEBAGAI FALLBACK
# Railway menyediakan MYSQL_PRIVATE_URL jika reference vars
# tidak di-set secara manual. Kita parse sebagai fallback.
# ============================================================
if [ -n "$MYSQL_PRIVATE_URL" ] && [ -z "$MYSQLHOST" ]; then
  echo "[INFO] Parsing MYSQL_PRIVATE_URL sebagai fallback..."
  export MYSQLUSER=$(echo "$MYSQL_PRIVATE_URL"     | sed -E 's|mysql://([^:]+):.*|\1|')
  export MYSQLPASSWORD=$(echo "$MYSQL_PRIVATE_URL" | sed -E 's|mysql://[^:]+:([^@]+)@.*|\1|')
  export MYSQLHOST=$(echo "$MYSQL_PRIVATE_URL"     | sed -E 's|.*@([^:/]+).*|\1|')
  export MYSQLPORT=$(echo "$MYSQL_PRIVATE_URL"     | sed -E 's|.*@[^:]+:([0-9]+)/.*|\1|')
  export MYSQLDATABASE=$(echo "$MYSQL_PRIVATE_URL" | sed -E 's|.*/([^?]+)$|\1|')
fi

# Fallback ke DATABASE_URL jika masih kosong
if [ -n "$DATABASE_URL" ] && [ -z "$MYSQLHOST" ]; then
  echo "[INFO] Parsing DATABASE_URL sebagai fallback..."
  export MYSQLUSER=$(echo "$DATABASE_URL"     | sed -E 's|mysql://([^:]+):.*|\1|')
  export MYSQLPASSWORD=$(echo "$DATABASE_URL" | sed -E 's|mysql://[^:]+:([^@]+)@.*|\1|')
  export MYSQLHOST=$(echo "$DATABASE_URL"     | sed -E 's|.*@([^:/]+).*|\1|')
  export MYSQLPORT=$(echo "$DATABASE_URL"     | sed -E 's|.*@[^:]+:([0-9]+)/.*|\1|')
  export MYSQLDATABASE=$(echo "$DATABASE_URL" | sed -E 's|.*/([^?]+)$|\1|')
fi

# ============================================================
# INISIALISASI DATABASE
# ============================================================
if [ -n "$MYSQLHOST" ]; then
  # ============================================================
  # FORCE IPv4 - Fix NO_SOCKET di Railway Internal Network
  # Railway menggunakan IPv6 (fd12:...) untuk internal networking,
  # tapi MySQL mungkin hanya listen di IPv4. Resolve dulu ke IPv4.
  # ============================================================
  echo "[INFO] Resolving $MYSQLHOST ke IPv4..."
  MYSQLHOST_V4=$(getent ahostsv4 "$MYSQLHOST" 2>/dev/null | awk 'NR==1{print $1}')
  if [ -n "$MYSQLHOST_V4" ] && [ "$MYSQLHOST_V4" != "$MYSQLHOST" ]; then
    echo "[INFO] Resolved: $MYSQLHOST → $MYSQLHOST_V4 (IPv4)"
    export MYSQLHOST="$MYSQLHOST_V4"
  else
    echo "[WARN] IPv4 resolution gagal, tetap pakai: $MYSQLHOST"
  fi

  echo "[INFO] Menunggu MySQL siap di $MYSQLHOST:${MYSQLPORT:-3306} (user: $MYSQLUSER)..."

  READY=0
  for i in $(seq 1 60); do
    if mysqladmin ping -h "$MYSQLHOST" -P "${MYSQLPORT:-3306}" \
        -u "$MYSQLUSER" --password="$MYSQLPASSWORD" \
        --connect-timeout=5 --silent 2>/dev/null; then
      echo "[OK] MySQL siap setelah $i percobaan!"
      READY=1
      break
    fi
    if [ $((i % 10)) -eq 0 ]; then
      echo "[WAIT] Percobaan $i/60 ($((i * 2)) detik)..."
    fi
    sleep 2
  done

  if [ "$READY" = "1" ]; then
    echo "[INFO] Membuat database jika belum ada..."
    mysql -h "$MYSQLHOST" -P "${MYSQLPORT:-3306}" \
      -u "$MYSQLUSER" --password="$MYSQLPASSWORD" \
      -e "CREATE DATABASE IF NOT EXISTS webgis_kemiskinan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || true
    mysql -h "$MYSQLHOST" -P "${MYSQLPORT:-3306}" \
      -u "$MYSQLUSER" --password="$MYSQLPASSWORD" \
      -e "CREATE DATABASE IF NOT EXISTS webgis_spbu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || true

    TABLE_COUNT=$(mysql -h "$MYSQLHOST" -P "${MYSQLPORT:-3306}" \
      -u "$MYSQLUSER" --password="$MYSQLPASSWORD" \
      -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='webgis_kemiskinan';" \
      --skip-column-names --silent 2>/dev/null || echo "0")
    TABLE_COUNT=$(echo "$TABLE_COUNT" | tr -d '[:space:]')
    if ! echo "$TABLE_COUNT" | grep -qE '^[0-9]+$'; then TABLE_COUNT=0; fi

    if [ "$TABLE_COUNT" -lt "3" ]; then
      echo "[INFO] Mengimpor schema webgis_kemiskinan..."
      mysql -h "$MYSQLHOST" -P "${MYSQLPORT:-3306}" \
        -u "$MYSQLUSER" --password="$MYSQLPASSWORD" \
        webgis_kemiskinan < /var/www/html/init-db/01-schema.sql 2>/dev/null || true
      echo "[INFO] Mengimpor schema webgis_spbu..."
      mysql -h "$MYSQLHOST" -P "${MYSQLPORT:-3306}" \
        -u "$MYSQLUSER" --password="$MYSQLPASSWORD" \
        webgis_spbu < /var/www/html/init-db/02-schema_webgis_spbu.sql 2>/dev/null || true
      echo "[OK] Database diinisialisasi!"
    else
      echo "[INFO] Database sudah ada ($TABLE_COUNT tabel), melewati inisialisasi."
    fi
  else
    echo "[WARN] MySQL tidak tersedia setelah 120 detik. Lanjut tanpa inisialisasi DB."
  fi
else
  echo "[WARN] MYSQLHOST tidak diset dan tidak ada MYSQL_PRIVATE_URL/DATABASE_URL."
fi

echo "=== Menjalankan Apache pada port $APP_PORT ==="
exec apache2-foreground
