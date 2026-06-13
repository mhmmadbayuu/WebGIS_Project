#!/bin/bash
set -e

echo "=== WebGIS Startup Script ==="

# Tunggu MySQL Railway siap
if [ -n "$MYSQLHOST" ]; then
  echo "[INFO] Menunggu MySQL Railway siap di $MYSQLHOST:$MYSQLPORT..."
  for i in $(seq 1 30); do
    if mysqladmin ping -h "$MYSQLHOST" -P "${MYSQLPORT:-3306}" -u "$MYSQLUSER" -p"$MYSQLPASSWORD" --silent 2>/dev/null; then
      echo "[OK] MySQL siap!"
      break
    fi
    echo "[WAIT] Percobaan $i/30..."
    sleep 2
  done

  # Inisialisasi database jika belum ada
  echo "[INFO] Menginisialisasi database..."
  
  # Buat database jika belum ada
  mysql -h "$MYSQLHOST" -P "${MYSQLPORT:-3306}" -u "$MYSQLUSER" -p"$MYSQLPASSWORD" -e "CREATE DATABASE IF NOT EXISTS webgis_kemiskinan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || true
  mysql -h "$MYSQLHOST" -P "${MYSQLPORT:-3306}" -u "$MYSQLUSER" -p"$MYSQLPASSWORD" -e "CREATE DATABASE IF NOT EXISTS webgis_spbu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || true
  
  # Import schema jika tabel belum ada
  TABLE_CHECK=$(mysql -h "$MYSQLHOST" -P "${MYSQLPORT:-3306}" -u "$MYSQLUSER" -p"$MYSQLPASSWORD" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='webgis_kemiskinan';" -s --skip-column-names 2>/dev/null || echo "0")
  
  if [ "$TABLE_CHECK" = "0" ] || [ "$TABLE_CHECK" -lt "3" ]; then
    echo "[INFO] Mengimpor schema webgis_kemiskinan..."
    mysql -h "$MYSQLHOST" -P "${MYSQLPORT:-3306}" -u "$MYSQLUSER" -p"$MYSQLPASSWORD" webgis_kemiskinan < /var/www/html/init-db/01-schema.sql 2>/dev/null || true
    echo "[INFO] Mengimpor schema webgis_spbu..."
    mysql -h "$MYSQLHOST" -P "${MYSQLPORT:-3306}" -u "$MYSQLUSER" -p"$MYSQLPASSWORD" webgis_spbu < /var/www/html/init-db/02-schema_webgis_spbu.sql 2>/dev/null || true
    echo "[OK] Database diinisialisasi!"
  else
    echo "[INFO] Database sudah ada, melewati inisialisasi."
  fi
fi

echo "=== Menjalankan Apache ==="
exec apache2-foreground
