-- Schema WebGIS SPBU + Jalan + Parsil (MySQL / MariaDB)
-- Catatan:
-- - Data LINE/POLYGON disimpan sebagai GeoJSON geometry (tipe: geojson) di kolom LONGTEXT `geojson`.
-- - Panjang (meter) dan luas (m2) dihitung otomatis di LeafletJS, bukan input manual.

CREATE DATABASE IF NOT EXISTS webgis_spbu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE webgis_spbu;

CREATE TABLE IF NOT EXISTS spbu_points (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(150) NOT NULL,
  no VARCHAR(60) NOT NULL,
  deskripsi TEXT NULL,
  status_24jam TINYINT(1) NOT NULL DEFAULT 0,
  latitude DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(10,7) NOT NULL,
  -- opsional (kompatibilitas): simpan GeoJSON Point sebagai string
  geom LONGTEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_spbu_status24 (status_24jam),
  INDEX idx_spbu_latlng (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS jalan_lines (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama_jalan VARCHAR(200) NOT NULL,
  status_jalan VARCHAR(20) NOT NULL, -- Nasional | Provinsi | Kabupaten
  panjang_meter DOUBLE NOT NULL DEFAULT 0,
  geojson LONGTEXT NOT NULL, -- GeoJSON geometry: { "type":"LineString", "coordinates":[...] }
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_jalan_status (status_jalan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS parsil_tanah (
  id INT AUTO_INCREMENT PRIMARY KEY,
  status_kepemilikan VARCHAR(10) NOT NULL, -- SHM | HGB | HGU | HP
  luas_m2 DOUBLE NOT NULL DEFAULT 0,
  geojson LONGTEXT NOT NULL, -- GeoJSON geometry: { "type":"Polygon", "coordinates":[...] }
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_parsil_status (status_kepemilikan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rumah_ibadah_points (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(200) NOT NULL,
  jenis VARCHAR(30) NOT NULL DEFAULT 'Lainnya', -- Masjid|Musholla|Gereja|Katolik|Pura|Vihara|Klenteng|Lainnya
  kontak VARCHAR(120) NULL,
  radius_meter DOUBLE NOT NULL DEFAULT 300,
  latitude DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(10,7) NOT NULL,
  address TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_ibadah_jenis (jenis),
  INDEX idx_ibadah_latlng (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pemukiman_miskin_points (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kk_nama VARCHAR(200) NOT NULL,
  nik VARCHAR(32) NULL,
  jumlah_anggota INT NOT NULL DEFAULT 0,
  latitude DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(10,7) NOT NULL,
  address TEXT NULL,
  kelurahan VARCHAR(120) NULL,
  kecamatan VARCHAR(120) NULL,
  status_bantuan VARCHAR(24) NOT NULL DEFAULT 'Belum dibantu', -- Belum dibantu|Sudah dibantu|Menunggu verifikasi
  jenis_bantuan VARCHAR(120) NULL,
  tanggal_bantuan DATE NULL,
  bukti_file VARCHAR(255) NULL,
  jarak_meter DOUBLE NULL,
  -- id rumah ibadah yang menaungi (hasil pencarian radius terdekat)
  rumah_ibadah_id INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_miskin_latlng (latitude, longitude),
  INDEX idx_miskin_ibadah (rumah_ibadah_id),
  INDEX idx_miskin_status (status_bantuan),
  CONSTRAINT fk_miskin_ibadah FOREIGN KEY (rumah_ibadah_id) REFERENCES rumah_ibadah_points(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS anggota_keluarga (
  id INT AUTO_INCREMENT PRIMARY KEY,
  penduduk_id INT NOT NULL,
  nama VARCHAR(200) NOT NULL,
  hubungan VARCHAR(60) NOT NULL,
  umur INT NULL,
  pekerjaan VARCHAR(120) NULL,
  keterangan VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_anggota_penduduk (penduduk_id),
  CONSTRAINT fk_anggota_penduduk FOREIGN KEY (penduduk_id) REFERENCES pemukiman_miskin_points(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- (Opsional) User login untuk admin/operator.
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  username VARCHAR(60) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'operator', -- admin|operator
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Jika ingin strict value, bisa pakai ENUM, misal:
-- status_jalan ENUM('Nasional','Provinsi','Kabupaten')
-- status_kepemilikan ENUM('SHM','HGB','HGU','HP')


-- ==========================================================
-- MIGRATION (ALTER) UNTUK DATABASE LAMA (TANPA HILANGKAN DATA)
-- ==========================================================
-- Kapan perlu menjalankan bagian ini?
-- - Jika database Anda dibuat dari project lama (kolom/tipe berbeda),
--   misalnya masih pakai kolom spatial `geom` atau belum punya kolom
--   `latitude/longitude/status_24jam/geojson`.
-- - Jika database Anda sudah dibuat pakai schema di atas (CREATE TABLE),
--   Anda biasanya TIDAK perlu ALTER apa-apa.
--
-- Cara pakai:
-- - Jalankan file ini di phpMyAdmin / MySQL client.
-- - Bagian CREATE TABLE aman (IF NOT EXISTS).
-- - Bagian MIGRATION ini aman (cek INFORMATION_SCHEMA sebelum ALTER).

DELIMITER $$

DROP PROCEDURE IF EXISTS migrate_webgis_spbu $$
CREATE PROCEDURE migrate_webgis_spbu()
BEGIN
  -- ----------------------
  -- spbu_points
  -- ----------------------
  IF EXISTS (
    SELECT 1 FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'spbu_points'
  ) THEN

    -- Kolom atribut inti
    IF NOT EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'spbu_points' AND column_name = 'no'
    ) THEN
      ALTER TABLE spbu_points ADD COLUMN no VARCHAR(60) NULL AFTER nama;
    END IF;

    IF NOT EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'spbu_points' AND column_name = 'deskripsi'
    ) THEN
      ALTER TABLE spbu_points ADD COLUMN deskripsi TEXT NULL AFTER no;
    END IF;

    IF NOT EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'spbu_points' AND column_name = 'status_24jam'
    ) THEN
      ALTER TABLE spbu_points ADD COLUMN status_24jam TINYINT(1) NOT NULL DEFAULT 0 AFTER deskripsi;
    END IF;

    -- Lat/Lng: untuk menjaga data lama, dibuat NULL-able dulu.
    IF NOT EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'spbu_points' AND column_name = 'latitude'
    ) THEN
      ALTER TABLE spbu_points ADD COLUMN latitude DECIMAL(10,7) NULL AFTER status_24jam;
    END IF;

    IF NOT EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'spbu_points' AND column_name = 'longitude'
    ) THEN
      ALTER TABLE spbu_points ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude;
    END IF;

    -- Kolom geom (opsional) untuk kompatibilitas legacy
    IF NOT EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'spbu_points' AND column_name = 'geom'
    ) THEN
      ALTER TABLE spbu_points ADD COLUMN geom LONGTEXT NULL AFTER longitude;
    END IF;

    -- Index (jika belum ada)
    IF NOT EXISTS (
      SELECT 1 FROM information_schema.statistics
      WHERE table_schema = DATABASE() AND table_name = 'spbu_points' AND index_name = 'idx_spbu_status24'
    ) THEN
      ALTER TABLE spbu_points ADD INDEX idx_spbu_status24 (status_24jam);
    END IF;

    IF NOT EXISTS (
      SELECT 1 FROM information_schema.statistics
      WHERE table_schema = DATABASE() AND table_name = 'spbu_points' AND index_name = 'idx_spbu_latlng'
    ) THEN
      ALTER TABLE spbu_points ADD INDEX idx_spbu_latlng (latitude, longitude);
    END IF;

    -- Backfill latitude/longitude dari geom GeoJSON (jika geom berisi JSON valid)
    -- Catatan: ini hanya mengisi yang masih NULL supaya tidak menimpa data baru.
    IF EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'spbu_points' AND column_name = 'geom'
    ) THEN
      -- Optional: JSON_VALID/JSON_EXTRACT bisa tidak tersedia pada versi tertentu.
      -- Best-effort: jika fungsi tidak didukung, blok ini di-skip tanpa menghentikan migrasi.
      BEGIN
        DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
        UPDATE spbu_points
        SET
          longitude = CAST(JSON_UNQUOTE(JSON_EXTRACT(geom, '$.coordinates[0]')) AS DECIMAL(10,7)),
          latitude  = CAST(JSON_UNQUOTE(JSON_EXTRACT(geom, '$.coordinates[1]')) AS DECIMAL(10,7))
        WHERE (latitude IS NULL OR longitude IS NULL)
          AND geom IS NOT NULL
          AND JSON_VALID(geom) = 1
          AND JSON_UNQUOTE(JSON_EXTRACT(geom, '$.type')) = 'Point';
      END;
    END IF;
  END IF;

  -- ----------------------
  -- jalan_lines
  -- ----------------------
  IF EXISTS (
    SELECT 1 FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'jalan_lines'
  ) THEN
    IF NOT EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'jalan_lines' AND column_name = 'panjang_meter'
    ) THEN
      ALTER TABLE jalan_lines ADD COLUMN panjang_meter DOUBLE NOT NULL DEFAULT 0 AFTER status_jalan;
    END IF;

    IF NOT EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'jalan_lines' AND column_name = 'geojson'
    ) THEN
      ALTER TABLE jalan_lines ADD COLUMN geojson LONGTEXT NULL AFTER panjang_meter;
    END IF;

    -- Backfill geojson dari kolom spatial geom (jika ada)
    IF EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'jalan_lines' AND column_name = 'geom'
    ) THEN
      BEGIN
        DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
        UPDATE jalan_lines
        SET geojson = ST_AsGeoJSON(geom)
        WHERE (geojson IS NULL OR geojson = '') AND geom IS NOT NULL;
      END;
    END IF;

    IF NOT EXISTS (
      SELECT 1 FROM information_schema.statistics
      WHERE table_schema = DATABASE() AND table_name = 'jalan_lines' AND index_name = 'idx_jalan_status'
    ) THEN
      ALTER TABLE jalan_lines ADD INDEX idx_jalan_status (status_jalan);
    END IF;
  END IF;

  -- ----------------------
  -- parsil_tanah
  -- ----------------------
  IF EXISTS (
    SELECT 1 FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'parsil_tanah'
  ) THEN
    IF NOT EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'parsil_tanah' AND column_name = 'luas_m2'
    ) THEN
      ALTER TABLE parsil_tanah ADD COLUMN luas_m2 DOUBLE NOT NULL DEFAULT 0 AFTER status_kepemilikan;
    END IF;

    IF NOT EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'parsil_tanah' AND column_name = 'geojson'
    ) THEN
      ALTER TABLE parsil_tanah ADD COLUMN geojson LONGTEXT NULL AFTER luas_m2;
    END IF;

    -- Backfill geojson dari kolom spatial geom (jika ada)
    IF EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'parsil_tanah' AND column_name = 'geom'
    ) THEN
      BEGIN
        DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
        UPDATE parsil_tanah
        SET geojson = ST_AsGeoJSON(geom)
        WHERE (geojson IS NULL OR geojson = '') AND geom IS NOT NULL;
      END;
    END IF;

    IF NOT EXISTS (
      SELECT 1 FROM information_schema.statistics
      WHERE table_schema = DATABASE() AND table_name = 'parsil_tanah' AND index_name = 'idx_parsil_status'
    ) THEN
      ALTER TABLE parsil_tanah ADD INDEX idx_parsil_status (status_kepemilikan);
    END IF;
  END IF;

  -- ----------------------
  -- rumah_ibadah_points
  -- ----------------------
  IF EXISTS (
    SELECT 1 FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'rumah_ibadah_points'
  ) THEN
    IF NOT EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'rumah_ibadah_points' AND column_name = 'jenis'
    ) THEN
      ALTER TABLE rumah_ibadah_points ADD COLUMN jenis VARCHAR(30) NOT NULL DEFAULT 'Lainnya' AFTER nama;
    END IF;

    IF NOT EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'rumah_ibadah_points' AND column_name = 'kontak'
    ) THEN
      ALTER TABLE rumah_ibadah_points ADD COLUMN kontak VARCHAR(120) NULL AFTER jenis;
    END IF;

    IF NOT EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'rumah_ibadah_points' AND column_name = 'radius_meter'
    ) THEN
      ALTER TABLE rumah_ibadah_points ADD COLUMN radius_meter DOUBLE NOT NULL DEFAULT 300 AFTER kontak;
    END IF;

    IF NOT EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'rumah_ibadah_points' AND column_name = 'address'
    ) THEN
      ALTER TABLE rumah_ibadah_points ADD COLUMN address TEXT NULL AFTER longitude;
    END IF;

    IF NOT EXISTS (
      SELECT 1 FROM information_schema.statistics
      WHERE table_schema = DATABASE() AND table_name = 'rumah_ibadah_points' AND index_name = 'idx_ibadah_latlng'
    ) THEN
      ALTER TABLE rumah_ibadah_points ADD INDEX idx_ibadah_latlng (latitude, longitude);
    END IF;
  END IF;

  -- ----------------------
  -- pemukiman_miskin_points
  -- ----------------------
  IF EXISTS (
    SELECT 1 FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'pemukiman_miskin_points'
  ) THEN
    -- Backward compat: kalau masih pakai kolom `nama`, migrasikan ke `kk_nama`
    IF NOT EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'pemukiman_miskin_points' AND column_name = 'kk_nama'
    ) THEN
      ALTER TABLE pemukiman_miskin_points ADD COLUMN kk_nama VARCHAR(200) NULL AFTER id;
      BEGIN
        DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
        UPDATE pemukiman_miskin_points
        SET kk_nama = COALESCE(NULLIF(kk_nama, ''), NULLIF(nama, ''))
        WHERE (kk_nama IS NULL OR kk_nama = '');
      END;
      -- jadikan NOT NULL kalau sudah terisi
      BEGIN
        DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
        ALTER TABLE pemukiman_miskin_points MODIFY kk_nama VARCHAR(200) NOT NULL;
      END;
    END IF;

    IF NOT EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'pemukiman_miskin_points' AND column_name = 'nik'
    ) THEN
      ALTER TABLE pemukiman_miskin_points ADD COLUMN nik VARCHAR(32) NULL AFTER kk_nama;
    END IF;

    IF NOT EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'pemukiman_miskin_points' AND column_name = 'jumlah_anggota'
    ) THEN
      ALTER TABLE pemukiman_miskin_points ADD COLUMN jumlah_anggota INT NOT NULL DEFAULT 0 AFTER nik;
    END IF;

    IF NOT EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'pemukiman_miskin_points' AND column_name = 'kelurahan'
    ) THEN
      ALTER TABLE pemukiman_miskin_points ADD COLUMN kelurahan VARCHAR(120) NULL AFTER address;
    END IF;

    IF NOT EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'pemukiman_miskin_points' AND column_name = 'kecamatan'
    ) THEN
      ALTER TABLE pemukiman_miskin_points ADD COLUMN kecamatan VARCHAR(120) NULL AFTER kelurahan;
    END IF;

    IF NOT EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'pemukiman_miskin_points' AND column_name = 'status_bantuan'
    ) THEN
      ALTER TABLE pemukiman_miskin_points ADD COLUMN status_bantuan VARCHAR(24) NOT NULL DEFAULT 'Belum dibantu' AFTER kecamatan;
    END IF;

    IF NOT EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'pemukiman_miskin_points' AND column_name = 'jenis_bantuan'
    ) THEN
      ALTER TABLE pemukiman_miskin_points ADD COLUMN jenis_bantuan VARCHAR(120) NULL AFTER status_bantuan;
    END IF;

    IF NOT EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'pemukiman_miskin_points' AND column_name = 'tanggal_bantuan'
    ) THEN
      ALTER TABLE pemukiman_miskin_points ADD COLUMN tanggal_bantuan DATE NULL AFTER jenis_bantuan;
    END IF;

    IF NOT EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'pemukiman_miskin_points' AND column_name = 'bukti_file'
    ) THEN
      ALTER TABLE pemukiman_miskin_points ADD COLUMN bukti_file VARCHAR(255) NULL AFTER tanggal_bantuan;
    END IF;

    IF NOT EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'pemukiman_miskin_points' AND column_name = 'jarak_meter'
    ) THEN
      ALTER TABLE pemukiman_miskin_points ADD COLUMN jarak_meter DOUBLE NULL AFTER bukti_file;
    END IF;

    IF NOT EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'pemukiman_miskin_points' AND column_name = 'rumah_ibadah_id'
    ) THEN
      ALTER TABLE pemukiman_miskin_points ADD COLUMN rumah_ibadah_id INT NULL AFTER jarak_meter;
    END IF;

    IF NOT EXISTS (
      SELECT 1 FROM information_schema.statistics
      WHERE table_schema = DATABASE() AND table_name = 'pemukiman_miskin_points' AND index_name = 'idx_miskin_ibadah'
    ) THEN
      ALTER TABLE pemukiman_miskin_points ADD INDEX idx_miskin_ibadah (rumah_ibadah_id);
    END IF;

    IF NOT EXISTS (
      SELECT 1 FROM information_schema.statistics
      WHERE table_schema = DATABASE() AND table_name = 'pemukiman_miskin_points' AND index_name = 'idx_miskin_latlng'
    ) THEN
      ALTER TABLE pemukiman_miskin_points ADD INDEX idx_miskin_latlng (latitude, longitude);
    END IF;
  END IF;
END $$

-- Jalankan migrasi (aman dijalankan berulang)
CALL migrate_webgis_spbu() $$
DROP PROCEDURE IF EXISTS migrate_webgis_spbu $$

DELIMITER ;
