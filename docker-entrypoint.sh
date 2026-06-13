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
# PARSE URL FALLBACK
# ============================================================
if [ -n "$MYSQL_PRIVATE_URL" ] && [ -z "$MYSQLHOST" ]; then
  echo "[INFO] Parsing MYSQL_PRIVATE_URL..."
  export MYSQLUSER=$(echo "$MYSQL_PRIVATE_URL"     | sed -E 's|mysql://([^:]+):.*|\1|')
  export MYSQLPASSWORD=$(echo "$MYSQL_PRIVATE_URL" | sed -E 's|mysql://[^:]+:([^@]+)@.*|\1|')
  export MYSQLHOST=$(echo "$MYSQL_PRIVATE_URL"     | sed -E 's|.*@([^:/]+).*|\1|')
  export MYSQLPORT=$(echo "$MYSQL_PRIVATE_URL"     | sed -E 's|.*@[^:]+:([0-9]+)/.*|\1|')
fi

if [ -n "$DATABASE_URL" ] && [ -z "$MYSQLHOST" ]; then
  echo "[INFO] Parsing DATABASE_URL..."
  export MYSQLUSER=$(echo "$DATABASE_URL"     | sed -E 's|mysql://([^:]+):.*|\1|')
  export MYSQLPASSWORD=$(echo "$DATABASE_URL" | sed -E 's|mysql://[^:]+:([^@]+)@.*|\1|')
  export MYSQLHOST=$(echo "$DATABASE_URL"     | sed -E 's|.*@([^:/]+).*|\1|')
  export MYSQLPORT=$(echo "$DATABASE_URL"     | sed -E 's|.*@[^:]+:([0-9]+)/.*|\1|')
fi

# ============================================================
# INISIALISASI DATABASE
# ============================================================
if [ -n "$MYSQLHOST" ]; then
  MYSQL_PORT="${MYSQLPORT:-3306}"
  echo "[INFO] Target MySQL: $MYSQLHOST:$MYSQL_PORT (user: $MYSQLUSER)"

  # ============================================================
  # TULIS CREDENTIALS KE CONFIG FILE
  # Cara paling aman untuk menangani password dengan karakter
  # khusus (@, !, #, $, %, dll) tanpa shell escaping issues.
  # ============================================================
  MYSQL_CNF=$(mktemp /tmp/mysql-XXXXXX.cnf)
  chmod 600 "$MYSQL_CNF"
  cat > "$MYSQL_CNF" << CNFEOF
[client]
host=${MYSQLHOST}
port=${MYSQL_PORT}
user=${MYSQLUSER}
password=${MYSQLPASSWORD}
connect_timeout=10
ssl-verify-server-cert=false
ssl=false
CNFEOF
  echo "[INFO] MySQL config ditulis ke $MYSQL_CNF"

  # ============================================================
  # STEP 1: TCP PORT CHECK
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
    echo "[WARN] Port MySQL tidak terbuka setelah 120 detik."
    rm -f "$MYSQL_CNF"
  else
    echo "[INFO] MySQL port terbuka. Menunggu 5 detik agar MySQL fully ready..."
    sleep 5

    # ============================================================
    # STEP 2: TEST KONEKSI (tampilkan error nyata)
    # --get-server-public-key: wajib untuk MySQL 8.0
    # caching_sha2_password tanpa SSL
    # ============================================================
    echo "[INFO] Test koneksi MySQL..."
    CONN_TEST=$(mysql --defaults-file="$MYSQL_CNF" \
      -e "SELECT 'OK';" --skip-column-names 2>&1)
    
    if echo "$CONN_TEST" | grep -q "OK"; then
      echo "[OK] Koneksi MySQL berhasil!"

      # CREATE databases
      echo "[INFO] Membuat database..."
      mysql --defaults-file="$MYSQL_CNF" \
        -e "CREATE DATABASE IF NOT EXISTS webgis_kemiskinan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
            CREATE DATABASE IF NOT EXISTS webgis_spbu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>&1 \
        | grep -v "^$" && echo "[OK] Database siap!" || true

      # Cek tabel
      TABLE_COUNT=$(mysql --defaults-file="$MYSQL_CNF" \
        --skip-column-names --silent \
        -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='webgis_kemiskinan';" \
        2>/dev/null || echo "0")
      TABLE_COUNT=$(echo "$TABLE_COUNT" | tr -d '[:space:]')
      if ! echo "$TABLE_COUNT" | grep -qE '^[0-9]+$'; then TABLE_COUNT=0; fi
      echo "[INFO] Tabel di webgis_kemiskinan: $TABLE_COUNT"

      if [ "$TABLE_COUNT" -lt "13" ]; then
        echo "[INFO] Mengimpor schema webgis_kemiskinan..."
        if mysql --defaults-file="$MYSQL_CNF" \
            webgis_kemiskinan < /var/www/html/init-db/01-schema.sql 2>&1; then
          echo "[OK] Schema kemiskinan berhasil diimpor!"
        else
          echo "[WARN] Import kemiskinan gagal. Cek file init-db/01-schema.sql"
        fi

        echo "[INFO] Mengimpor schema webgis_spbu..."
        if mysql --defaults-file="$MYSQL_CNF" \
            webgis_spbu < /var/www/html/init-db/02-schema_webgis_spbu.sql 2>&1; then
          echo "[OK] Schema spbu berhasil diimpor!"
        else
          echo "[WARN] Import spbu gagal. Cek file init-db/02-schema_webgis_spbu.sql"
        fi
      else
        echo "[INFO] Database sudah ada ($TABLE_COUNT tabel), melewati inisialisasi."
      fi
    else
      echo "[ERROR] Koneksi MySQL GAGAL. Error:"
      echo "$CONN_TEST"
      echo "[INFO] Cek MYSQLHOST, MYSQLUSER, MYSQLPASSWORD di Railway Variables!"
    fi

    rm -f "$MYSQL_CNF"
  fi
else
  echo "[WARN] MYSQLHOST tidak diset! Tambahkan reference variables dari MySQL ke WebGIS_Project di Railway."
fi

echo "=== Menjalankan Apache pada port $APP_PORT ==="
exec apache2-foreground
