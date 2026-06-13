-- ============================================================
-- WebGIS Pengentasan Kemiskinan Berbasis Masyarakat & Rumah Ibadah
-- Schema Lengkap v2.0 — FIXED VERSION
-- Engine: MySQL / MariaDB | Charset: utf8mb4
-- ============================================================

-- PENTING: Buat database terlebih dahulu jika belum ada
CREATE DATABASE IF NOT EXISTS webgis_kemiskinan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE webgis_kemiskinan;

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ============================================================
-- DROP TABLES (urutan terbalik dari dependensi FK)
-- ============================================================
DROP TABLE IF EXISTS notifikasi;
DROP TABLE IF EXISTS log_aktivitas;
DROP TABLE IF EXISTS pesan;
DROP TABLE IF EXISTS laporan;
DROP TABLE IF EXISTS histori_bantuan;
DROP TABLE IF EXISTS jenis_bantuan;
DROP TABLE IF EXISTS riwayat_pelatihan;
DROP TABLE IF EXISTS penduduk;
DROP TABLE IF EXISTS keluarga;
DROP TABLE IF EXISTS rumah_ibadah;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS jalan_lines;
DROP TABLE IF EXISTS parsil_tanah;

-- ============================================================
-- 1. ROLES & USERS
-- ============================================================

CREATE TABLE roles (
  id      TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama    VARCHAR(50)  NOT NULL UNIQUE,
  label   VARCHAR(80)  NOT NULL,
  deskripsi TEXT       NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO roles (nama, label) VALUES
  ('admin',       'Administrator Sistem'),
  ('pengurus',    'Pengurus Rumah Ibadah'),
  ('pimpinan',    'Pimpinan Daerah'),
  ('masyarakat',  'Masyarakat Umum');

-- ============================================================
-- 2. RUMAH IBADAH (dibuat sebelum users karena users FK ke sini)
-- ============================================================

CREATE TABLE rumah_ibadah (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama          VARCHAR(200)  NOT NULL,
  jenis         ENUM('Masjid','Musholla','Gereja Protestan','Gereja Katolik','Pura','Vihara','Klenteng','Lainnya') NOT NULL DEFAULT 'Lainnya',
  kontak        VARCHAR(120)  NULL,
  radius_meter  DOUBLE        NOT NULL DEFAULT 300,
  latitude      DECIMAL(10,7) NOT NULL,
  longitude     DECIMAL(10,7) NOT NULL,
  alamat        TEXT          NULL,
  kelurahan     VARCHAR(120)  NULL,
  kecamatan     VARCHAR(120)  NULL,
  kota          VARCHAR(120)  NULL,
  deskripsi     TEXT          NULL,
  created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_ri_jenis    (jenis),
  INDEX idx_ri_latlng   (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 3. USERS (setelah roles & rumah_ibadah)
-- ============================================================

CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id       TINYINT UNSIGNED NOT NULL DEFAULT 4,
  nama          VARCHAR(150)  NOT NULL,
  username      VARCHAR(60)   NOT NULL UNIQUE,
  email         VARCHAR(150)  NULL UNIQUE,
  password_hash VARCHAR(255)  NOT NULL,
  no_telp       VARCHAR(20)   NULL,
  foto          VARCHAR(255)  NULL,
  aktif         TINYINT(1)    NOT NULL DEFAULT 1,
  rumah_ibadah_id INT UNSIGNED NULL,
  last_login    DATETIME      NULL,
  created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_role (role_id),
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id),
  CONSTRAINT fk_users_ri   FOREIGN KEY (rumah_ibadah_id) REFERENCES rumah_ibadah(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 4. KELUARGA (KK)
-- ============================================================

CREATE TABLE keluarga (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  no_kk           VARCHAR(20)   NOT NULL UNIQUE,
  nama_kk         VARCHAR(200)  NOT NULL,
  alamat          TEXT          NULL,
  kelurahan       VARCHAR(120)  NULL,
  kecamatan       VARCHAR(120)  NULL,
  kota            VARCHAR(120)  NULL,
  latitude        DECIMAL(10,7) NULL,
  longitude       DECIMAL(10,7) NULL,
  status_ekonomi  ENUM('miskin','rentan','mampu') NOT NULL DEFAULT 'rentan',
  rumah_ibadah_id INT UNSIGNED  NULL,
  jarak_ri_meter  DOUBLE        NULL,
  created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_kk_status   (status_ekonomi),
  INDEX idx_kk_latlng   (latitude, longitude),
  INDEX idx_kk_ri       (rumah_ibadah_id),
  CONSTRAINT fk_kk_ri FOREIGN KEY (rumah_ibadah_id) REFERENCES rumah_ibadah(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 5. PENDUDUK
-- ============================================================

CREATE TABLE penduduk (
  nik             VARCHAR(16)   NOT NULL PRIMARY KEY,
  id_keluarga     INT UNSIGNED  NULL,
  nama_lengkap    VARCHAR(200)  NOT NULL,
  jenis_kelamin   ENUM('L','P') NOT NULL,
  tanggal_lahir   DATE          NOT NULL,
  umur            TINYINT UNSIGNED NULL,
  status_keluarga ENUM('kepala_keluarga','istri','anak','menantu','cucu','orang_tua','mertua','anggota_lain') NOT NULL DEFAULT 'anggota_lain',
  status_perkawinan ENUM('belum_kawin','kawin','cerai_hidup','cerai_mati') NOT NULL DEFAULT 'belum_kawin',
  status_hidup    ENUM('hidup','meninggal') NOT NULL DEFAULT 'hidup',
  pendidikan_terakhir ENUM('tidak_sekolah','SD','SMP','SMA','SMK','D1','D2','D3','S1','S2','S3') NOT NULL DEFAULT 'tidak_sekolah',
  status_pendidikan   ENUM('sekolah','tidak_sekolah','lulus') NOT NULL DEFAULT 'tidak_sekolah',
  pekerjaan       VARCHAR(150)  NULL,
  penghasilan     DECIMAL(15,2) NULL DEFAULT 0,
  status_ekonomi  ENUM('miskin','rentan','mampu') NOT NULL DEFAULT 'rentan',
  kondisi_kesehatan ENUM('sehat','sakit_ringan','sakit_parah','disabilitas') NOT NULL DEFAULT 'sehat',
  catatan_kesehatan TEXT         NULL,
  no_telp         VARCHAR(20)   NULL,
  alamat          TEXT          NULL,
  latitude        DECIMAL(10,7) NULL,
  longitude       DECIMAL(10,7) NULL,
  foto            VARCHAR(255)  NULL,
  created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_pddk_keluarga   (id_keluarga),
  INDEX idx_pddk_status_ek  (status_ekonomi),
  INDEX idx_pddk_status_hid (status_hidup),
  INDEX idx_pddk_latlng     (latitude, longitude),
  CONSTRAINT fk_pddk_keluarga FOREIGN KEY (id_keluarga) REFERENCES keluarga(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 6. RIWAYAT PELATIHAN
-- ============================================================

CREATE TABLE riwayat_pelatihan (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nik         VARCHAR(16)  NOT NULL,
  nama_pelatihan VARCHAR(200) NOT NULL,
  penyelenggara  VARCHAR(150) NULL,
  tanggal_mulai  DATE         NULL,
  tanggal_selesai DATE        NULL,
  sertifikat  VARCHAR(255)  NULL,
  created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_platihan_nik (nik),
  CONSTRAINT fk_platihan_nik FOREIGN KEY (nik) REFERENCES penduduk(nik) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 7. JENIS BANTUAN (master)
-- ============================================================

CREATE TABLE jenis_bantuan (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama        VARCHAR(150)  NOT NULL,
  kategori    ENUM('sembako','pendidikan','kesehatan','ekonomi','perumahan','lainnya') NOT NULL DEFAULT 'lainnya',
  sumber      VARCHAR(150)  NULL,
  satuan      VARCHAR(50)   NULL,
  deskripsi   TEXT          NULL,
  aktif       TINYINT(1)    NOT NULL DEFAULT 1,
  created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO jenis_bantuan (nama, kategori, sumber, satuan) VALUES
  ('Sembako Bulanan',     'sembako',   'Rumah Ibadah', '1 paket/bulan'),
  ('Beasiswa Pendidikan', 'pendidikan','BAZNAS',        'Rp/semester'),
  ('Bantuan Kesehatan',   'kesehatan', 'Puskesmas',     '1 kali/kunjungan'),
  ('Modal Usaha Mikro',   'ekonomi',   'Baznas',        'Rp/program'),
  ('Renovasi Rumah',      'perumahan', 'Pemerintah',    'paket/unit');

-- ============================================================
-- 8. HISTORI BANTUAN
-- ============================================================

CREATE TABLE histori_bantuan (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nik             VARCHAR(16)   NOT NULL,
  id_jenis_bantuan INT UNSIGNED NOT NULL,
  id_keluarga     INT UNSIGNED  NULL,
  rumah_ibadah_id INT UNSIGNED  NULL,
  tanggal         DATE          NOT NULL,
  jumlah          VARCHAR(100)  NULL,
  nilai_rupiah    DECIMAL(15,2) NULL,
  status_penyaluran ENUM('dijadwalkan','disalurkan','dibatalkan') NOT NULL DEFAULT 'dijadwalkan',
  catatan         TEXT          NULL,
  bukti_file      VARCHAR(255)  NULL,
  disalurkan_oleh INT UNSIGNED  NULL,
  created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_hb_nik     (nik),
  INDEX idx_hb_tanggal (tanggal),
  INDEX idx_hb_status  (status_penyaluran),
  CONSTRAINT fk_hb_nik   FOREIGN KEY (nik)              REFERENCES penduduk(nik)     ON DELETE CASCADE,
  CONSTRAINT fk_hb_jenis FOREIGN KEY (id_jenis_bantuan) REFERENCES jenis_bantuan(id),
  CONSTRAINT fk_hb_kk    FOREIGN KEY (id_keluarga)      REFERENCES keluarga(id)      ON DELETE SET NULL,
  CONSTRAINT fk_hb_ri    FOREIGN KEY (rumah_ibadah_id)  REFERENCES rumah_ibadah(id)  ON DELETE SET NULL,
  CONSTRAINT fk_hb_user  FOREIGN KEY (disalurkan_oleh)  REFERENCES users(id)         ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 9. LAPORAN MASYARAKAT
-- ============================================================

CREATE TABLE laporan (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama_pelapor    VARCHAR(150)  NULL,
  pelapor_id      INT UNSIGNED  NULL,
  nik_terdampak   VARCHAR(16)   NULL,
  deskripsi       TEXT          NOT NULL,
  kategori        ENUM('darurat_pangan','darurat_kesehatan','darurat_ekonomi','butuh_bantuan_lain') NOT NULL DEFAULT 'butuh_bantuan_lain',
  tingkat_urgensi ENUM('rendah','sedang','tinggi','darurat') NOT NULL DEFAULT 'sedang',
  latitude        DECIMAL(10,7) NULL,
  longitude       DECIMAL(10,7) NULL,
  alamat          TEXT          NULL,
  foto            VARCHAR(255)  NULL,
  status          ENUM('pending','diverifikasi','diproses','selesai','ditolak') NOT NULL DEFAULT 'pending',
  catatan_verifikasi TEXT       NULL,
  diverifikasi_oleh INT UNSIGNED NULL,
  ditangani_oleh  INT UNSIGNED  NULL,
  tanggal_selesai DATETIME      NULL,
  created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_lap_status    (status),
  INDEX idx_lap_urgensi   (tingkat_urgensi),
  INDEX idx_lap_latlng    (latitude, longitude),
  CONSTRAINT fk_lap_nik     FOREIGN KEY (nik_terdampak)     REFERENCES penduduk(nik) ON DELETE SET NULL,
  CONSTRAINT fk_lap_pelapor FOREIGN KEY (pelapor_id)        REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_lap_verif   FOREIGN KEY (diverifikasi_oleh) REFERENCES users(id)     ON DELETE SET NULL,
  CONSTRAINT fk_lap_tangan  FOREIGN KEY (ditangani_oleh)    REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 10. PESAN / CHAT ANTAR PENGURUS
-- ============================================================

CREATE TABLE pesan (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dari_user_id    INT UNSIGNED  NOT NULL,
  ke_user_id      INT UNSIGNED  NULL,
  ke_rumah_ibadah_id INT UNSIGNED NULL,
  isi             TEXT          NOT NULL,
  dibaca          TINYINT(1)    NOT NULL DEFAULT 0,
  created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pesan_dari (dari_user_id),
  INDEX idx_pesan_ke   (ke_user_id),
  CONSTRAINT fk_pesan_dari FOREIGN KEY (dari_user_id)        REFERENCES users(id)        ON DELETE CASCADE,
  CONSTRAINT fk_pesan_ke   FOREIGN KEY (ke_user_id)          REFERENCES users(id)        ON DELETE SET NULL,
  CONSTRAINT fk_pesan_ri   FOREIGN KEY (ke_rumah_ibadah_id)  REFERENCES rumah_ibadah(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 11. LOG AKTIVITAS (audit trail)
-- ============================================================

CREATE TABLE log_aktivitas (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED  NULL,
  aksi        VARCHAR(50)   NOT NULL,
  tabel       VARCHAR(60)   NULL,
  record_id   VARCHAR(32)   NULL,
  data_lama   JSON          NULL,
  data_baru   JSON          NULL,
  ip_address  VARCHAR(45)   NULL,
  user_agent  VARCHAR(255)  NULL,
  created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_log_user  (user_id),
  INDEX idx_log_tabel (tabel),
  INDEX idx_log_aksi  (aksi),
  INDEX idx_log_time  (created_at),
  CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 12. LAYER SPASIAL TAMBAHAN
-- ============================================================

CREATE TABLE jalan_lines (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama_jalan    VARCHAR(200)  NOT NULL,
  status_jalan  ENUM('Nasional','Provinsi','Kabupaten','Desa') NOT NULL DEFAULT 'Kabupaten',
  panjang_meter DOUBLE        NOT NULL DEFAULT 0,
  geojson       LONGTEXT      NOT NULL,
  created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_jalan_status (status_jalan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE parsil_tanah (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  status_kepemilikan ENUM('SHM','HGB','HGU','HP','Wakaf','Lainnya') NOT NULL DEFAULT 'SHM',
  luas_m2           DOUBLE       NOT NULL DEFAULT 0,
  geojson           LONGTEXT     NOT NULL,
  created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_parsil_status (status_kepemilikan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 13. NOTIFIKASI
-- ============================================================

CREATE TABLE notifikasi (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED  NOT NULL,
  judul       VARCHAR(200)  NOT NULL,
  isi         TEXT          NOT NULL,
  tipe        ENUM('laporan','bantuan','sistem','pesan') NOT NULL DEFAULT 'sistem',
  ref_id      INT UNSIGNED  NULL,
  dibaca      TINYINT(1)    NOT NULL DEFAULT 0,
  created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notif_user   (user_id),
  INDEX idx_notif_dibaca (dibaca),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- DATA DUMMY
-- ============================================================

INSERT IGNORE INTO users (role_id, nama, username, email, password_hash) VALUES
  (1, 'Administrator',          'admin',      'admin@webgis.local',      '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
  (2, 'H. Ahmad Fauzi',         'pengurus1',  'pengurus1@webgis.local',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
  (3, 'Camat Pontianak Selatan','pimpinan1',  'pimpinan@webgis.local',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
  (4, 'Budi Santoso',           'warga1',     'warga1@webgis.local',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

INSERT IGNORE INTO rumah_ibadah (nama, jenis, kontak, radius_meter, latitude, longitude, alamat, kelurahan, kecamatan) VALUES
  ('Masjid Al-Falah',       'Masjid',           '08113001234', 400, -0.0200, 109.3410, 'Jl. Gajah Mada No. 1',  'Benua Melayu Darat', 'Pontianak Selatan'),
  ('Masjid Raya Mujahidin', 'Masjid',           '08113005678', 600, -0.0250, 109.3430, 'Jl. Ahmad Yani No. 10', 'Akcaya',             'Pontianak Selatan'),
  ('GKE Pontianak',         'Gereja Protestan', '08113009999', 300, -0.0280, 109.3480, 'Jl. Nusa Indah',        'Sungai Bangkong',    'Pontianak Kota'),
  ('Vihara Bodhicitta',     'Vihara',           '08113004321', 250, -0.0310, 109.3520, 'Jl. Tanjungpura No. 5', 'Mariana',            'Pontianak Kota');

INSERT IGNORE INTO keluarga (no_kk, nama_kk, alamat, kelurahan, kecamatan, latitude, longitude, status_ekonomi, rumah_ibadah_id) VALUES
  ('6171010101010001', 'Ahmad Firdaus', 'Jl. Melati No. 3',    'Benua Melayu Darat', 'Pontianak Selatan', -0.0210, 109.3415, 'miskin', 1),
  ('6171010101010002', 'Siti Rahma',   'Jl. Kenanga No. 5',   'Benua Melayu Darat', 'Pontianak Selatan', -0.0220, 109.3420, 'miskin', 1),
  ('6171010101010003', 'Budi Hermawan','Jl. Mawar No. 7',      'Akcaya',             'Pontianak Selatan', -0.0240, 109.3435, 'rentan', 2),
  ('6171010101010004', 'Maria Goretti','Jl. Nusa Indah No.2',  'Sungai Bangkong',    'Pontianak Kota',    -0.0275, 109.3475, 'miskin', 3),
  ('6171010101010005', 'Tan Ah Kow',   'Jl. Tanjungpura 12',  'Mariana',            'Pontianak Kota',    -0.0305, 109.3515, 'rentan', 4);

INSERT IGNORE INTO penduduk (nik, id_keluarga, nama_lengkap, jenis_kelamin, tanggal_lahir, status_keluarga, status_perkawinan, status_ekonomi, pekerjaan, penghasilan, kondisi_kesehatan, alamat, latitude, longitude) VALUES
  ('6171010101010001', 1, 'Ahmad Firdaus',   'L', '1980-03-15', 'kepala_keluarga', 'kawin',       'miskin', 'Buruh Harian',      800000,  'sehat',       'Jl. Melati No. 3',    -0.0210, 109.3415),
  ('6171010101010002', 1, 'Siti Aisyah',     'P', '1983-07-22', 'istri',           'kawin',       'miskin', 'Ibu Rumah Tangga',  0,       'sehat',       'Jl. Melati No. 3',    -0.0210, 109.3415),
  ('6171010101010003', 1, 'Reza Firdaus',    'L', '2010-11-05', 'anak',            'belum_kawin', 'miskin', 'Pelajar',           0,       'sehat',       'Jl. Melati No. 3',    -0.0210, 109.3415),
  ('6171020202020001', 2, 'Siti Rahma',      'P', '1975-01-10', 'kepala_keluarga', 'cerai_mati',  'miskin', 'Pedagang Kecil',    500000,  'sakit_ringan','Jl. Kenanga No. 5',   -0.0220, 109.3420),
  ('6171020202020002', 2, 'Dewi Rahma',      'P', '2008-05-30', 'anak',            'belum_kawin', 'miskin', 'Pelajar',           0,       'sehat',       'Jl. Kenanga No. 5',   -0.0220, 109.3420),
  ('6171030303030001', 3, 'Budi Hermawan',   'L', '1990-09-20', 'kepala_keluarga', 'kawin',       'rentan', 'Ojek Online',       1500000, 'sehat',       'Jl. Mawar No. 7',     -0.0240, 109.3435),
  ('6171040404040001', 4, 'Yohanes Goretti', 'L', '1970-12-01', 'kepala_keluarga', 'kawin',       'miskin', 'Tidak bekerja',     0,       'sakit_parah', 'Jl. Nusa Indah No.2', -0.0275, 109.3475),
  ('6171050505050001', 5, 'Tan Ah Kow',      'L', '1968-06-18', 'kepala_keluarga', 'kawin',       'rentan', 'Pedagang',          1200000, 'sehat',       'Jl. Tanjungpura 12',  -0.0305, 109.3515);

INSERT IGNORE INTO histori_bantuan (nik, id_jenis_bantuan, id_keluarga, rumah_ibadah_id, tanggal, jumlah, status_penyaluran, catatan) VALUES
  ('6171010101010001', 1, 1, 1, CURDATE() - INTERVAL 30 DAY, '1 paket sembako',    'disalurkan', 'Penyaluran rutin bulan lalu'),
  ('6171020202020001', 2, 2, 1, CURDATE() - INTERVAL 15 DAY, 'Rp 500.000',         'disalurkan', 'Bantuan pendidikan anak'),
  ('6171040404040001', 3, 4, 3, CURDATE() - INTERVAL 7 DAY,  '1 kali pemeriksaan', 'disalurkan', 'Kunjungan kesehatan darurat');

INSERT IGNORE INTO laporan (nama_pelapor, nik_terdampak, deskripsi, kategori, tingkat_urgensi, latitude, longitude, alamat, status) VALUES
  ('Anonim',          '6171020202020001', 'Ibu Siti sudah 3 hari tidak makan, anaknya juga sakit', 'darurat_pangan',    'darurat', -0.0220, 109.3420, 'Jl. Kenanga No. 5',    'pending'),
  ('Pak RT 03',       NULL,               'Ada lansia sebatang kara tidak ada yang merawat di RT 03','butuh_bantuan_lain','tinggi',  -0.0265, 109.3460, 'RT 03 Sungai Bangkong','diverifikasi'),
  ('Maria Magdalena', '6171040404040001', 'Pak Yohanes sakit parah, tidak ada biaya berobat',      'darurat_kesehatan', 'darurat', -0.0275, 109.3475, 'Jl. Nusa Indah No.2',  'diproses');
