#!/bin/bash
# WebGIS Project - Docker Entrypoint Script

echo "--- WebGIS Startup Script ---"

# ============================================================
# FIX MPM CONFLICT DI RUNTIME
# ============================================================
echo "[INFO] Memastikan hanya mpm_prefork yang aktif..."
rm -f /etc/apache2/mods-enabled/mpm_event.conf \
       /etc/apache2/mods-enabled/mpm_event.load \
       /etc/apache2/mods-enabled/mpm_worker.conf \
       /etc/apache2/mods-enabled/mpm_worker.load 2>/dev/null || true
a2enmod mpm_prefork 2>/dev/null || true

# ============================================================
# KONFIGURASI PORT APACHE
# ============================================================
APP_PORT="${PORT:-80}"
echo "[INFO] Mengkonfigurasi Apache pada port $APP_PORT..."
cat > /etc/apache2/ports.conf << EOF
Listen $APP_PORT
EOF
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${APP_PORT}>/g" \
    /etc/apache2/sites-enabled/000-default.conf 2>/dev/null || true

# ============================================================
# PARSE URL FALLBACK (jika reference vars tidak di-set)
# ============================================================
if [ -n "$MYSQL_PRIVATE_URL" ] && [ -z "$MYSQLHOST" ]; then
  echo "[INFO] Parsing MYSQL_PRIVATE_URL..."
  export MYSQLUSER=$(echo "$MYSQL_PRIVATE_URL"     | sed -E 's|mysql://([^:]+):.*|\1|')
  export MYSQLPASSWORD=$(echo "$MYSQL_PRIVATE_URL" | sed -E 's|mysql://[^:]+:([^@]+)@.*|\1|')
  export MYSQLHOST=$(echo "$MYSQL_PRIVATE_URL"     | sed -E 's|.*@([^:/]+).*|\1|')
  export MYSQLPORT=$(echo "$MYSQL_PRIVATE_URL"     | sed -E 's|.*@[^:]+:([0-9]+)/.*|\1|')
  export MYSQLDATABASE=$(echo "$MYSQL_PRIVATE_URL" | sed -E 's|.*/([^?]+)$|\1|')
fi

if [ -n "$DATABASE_URL" ] && [ -z "$MYSQLHOST" ]; then
  echo "[INFO] Parsing DATABASE_URL..."
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
  MYSQL_PORT="${MYSQLPORT:-3306}"
  echo "[INFO] Target MySQL: $MYSQLHOST:$MYSQL_PORT (user: $MYSQLUSER)"

  # ============================================================
  # STEP 1: TCP PORT CHECK (bypass auth issues sepenuhnya)
  # Ini hanya cek apakah port MySQL terbuka, TIDAK perlu login.
  # Lebih andal daripada mysqladmin ping yang butuh autentikasi.
  # ============================================================
  echo "[INFO] Menunggu port MySQL terbuka..."
  TCP_READY=0
  for i in $(seq 1 60); do
    if timeout 3 bash -c "echo > /dev/tcp/$MYSQLHOST/$MYSQL_PORT" 2>/dev/null; then
      echo "[OK] Port MySQL terbuka setelah $((i * 2)) detik!"
      TCP_READY=1
      break
    fi
    if [ $((i % 10)) -eq 0 ]; then
      echo "[WAIT] Port belum terbuka... percobaan $i/60"
    fi
    sleep 2
  done

  if [ "$TCP_READY" != "1" ]; then
    echo "[WARN] Port MySQL tidak terbuka setelah 120 detik. Lanjut tanpa inisialisasi DB."
  else
    # ============================================================
    # STEP 2: TUNGGU SEBENTAR lalu langsung jalankan SQL
    # Jangan pakai mysqladmin ping (masalah caching_sha2_password).
    # Langsung coba SQL, jika gagal lanjut saja.
    # ============================================================
    echo "[INFO] Port terbuka. Menunggu MySQL fully ready (5 detik)..."
    sleep 5

    echo "[INFO] Membuat database jika belum ada..."
    mysql -h "$MYSQLHOST" -P "$MYSQL_PORT" \
      -u "$MYSQLUSER" --password="$MYSQLPASSWORD" \
      --connect-timeout=10 \
      -e "CREATE DATABASE IF NOT EXISTS webgis_kemiskinan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
          CREATE DATABASE IF NOT EXISTS webgis_spbu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" \
      2>/dev/null && echo "[OK] Database dibuat!" || echo "[WARN] Gagal buat database (mungkin sudah ada)"

    # Cek tabel webgis_kemiskinan
    TABLE_COUNT=$(mysql -h "$MYSQLHOST" -P "$MYSQL_PORT" \
      -u "$MYSQLUSER" --password="$MYSQLPASSWORD" \
      --connect-timeout=10 --skip-column-names --silent \
      -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='webgis_kemiskinan';" \
      2>/dev/null || echo "0")
    TABLE_COUNT=$(echo "$TABLE_COUNT" | tr -d '[:space:]')
    if ! echo "$TABLE_COUNT" | grep -qE '^[0-9]+$'; then TABLE_COUNT=0; fi
    echo "[INFO] Tabel ditemukan di webgis_kemiskinan: $TABLE_COUNT"

    if [ "$TABLE_COUNT" -lt "3" ]; then
      echo "[INFO] Mengimpor schema webgis_kemiskinan..."
      mysql -h "$MYSQLHOST" -P "$MYSQL_PORT" \
        -u "$MYSQLUSER" --password="$MYSQLPASSWORD" \
        --connect-timeout=30 \
        webgis_kemiskinan < /var/www/html/init-db/01-schema.sql \
        2>/dev/null && echo "[OK] Schema kemiskinan imported!" || echo "[WARN] Import kemiskinan gagal"

      echo "[INFO] Mengimpor schema webgis_spbu..."
      mysql -h "$MYSQLHOST" -P "$MYSQL_PORT" \
        -u "$MYSQLUSER" --password="$MYSQLPASSWORD" \
        --connect-timeout=30 \
        webgis_spbu < /var/www/html/init-db/02-schema_webgis_spbu.sql \
        2>/dev/null && echo "[OK] Schema spbu imported!" || echo "[WARN] Import spbu gagal"
    else
      echo "[INFO] Database sudah ada ($TABLE_COUNT tabel), melewati inisialisasi."
    fi
  fi
else
  echo "[WARN] MYSQLHOST tidak diset. Cek reference variables di Railway!"
fi

echo "=== Menjalankan Apache pada port $APP_PORT ==="
exec apache2-foreground
