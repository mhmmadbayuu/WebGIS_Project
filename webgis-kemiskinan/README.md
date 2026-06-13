# WebGIS Pengentasan Kemiskinan v2.0
### Berbasis Masyarakat & Rumah Ibadah

> Pengembangan dari sistem webgis-spbu menjadi sistem manajemen sosial yang lengkap, real-time, dan berbasis relasi database.

---

## 🚀 Fitur Lengkap

| Fitur | Status |
|---|---|
| Peta Leaflet interaktif + MarkerCluster | ✅ |
| Layer: penduduk, keluarga, rumah ibadah, laporan, jalan | ✅ |
| Warna marker berdasar status ekonomi (merah/kuning/hijau) | ✅ |
| CRUD penduduk lengkap (NIK, umur otomatis, kesehatan, dll) | ✅ |
| Sistem keluarga (KK → anggota) | ✅ |
| Validasi logika pendidikan vs umur | ✅ |
| Laporan masyarakat (siapapun bisa lapor, tanpa login) | ✅ |
| Manajemen bantuan + histori per penduduk | ✅ |
| Deteksi otomatis warga belum pernah dibantu | ✅ |
| Prioritas bantuan (KRITIS = miskin + sakit parah) | ✅ |
| Multi-role: admin, pengurus, pimpinan, masyarakat | ✅ |
| Chat/diskusi antar pengurus per rumah ibadah | ✅ |
| Notifikasi real-time (polling) | ✅ |
| Audit log semua aktivitas | ✅ |
| Dashboard monitoring statistik | ✅ |
| Data dummy siap pakai (Pontianak) | ✅ |

---

## 📁 Struktur Direktori

```
webgis-kemiskinan/
├── index.html               ← Peta WebGIS utama (publik)
├── admin/
│   ├── login.php            ← Halaman login
│   ├── logout.php
│   ├── init_admin.php       ← Inisialisasi user (jalankan sekali!)
│   ├── _layout.php          ← Layout admin (header + nav)
│   ├── index.php            ← Dashboard
│   ├── penduduk.php         ← CRUD penduduk
│   ├── bantuan.php          ← Catat penyaluran bantuan
│   ├── histori_bantuan.php  ← Riwayat bantuan
│   ├── belum_dibantu.php    ← Warga belum tersentuh bantuan
│   ├── laporan.php          ← Manajemen laporan masyarakat
│   └── log.php              ← Audit log (admin only)
├── php/
│   ├── koneksi.php          ← Config DB + helper functions
│   ├── middleware/
│   │   └── auth.php         ← Autentikasi & otorisasi
│   └── api/
│       ├── auth.php         ← Login/logout API
│       ├── penduduk.php     ← CRUD penduduk (RESTful)
│       ├── keluarga.php     ← CRUD keluarga
│       ├── bantuan.php      ← Bantuan + histori + stats
│       ├── laporan.php      ← Laporan masyarakat
│       ├── rumah_ibadah.php ← CRUD rumah ibadah
│       └── pesan.php        ← Chat + notifikasi
├── sql/
│   └── schema.sql           ← Schema + data dummy
└── uploads/
    ├── bukti/               ← Bukti penyaluran bantuan
    └── foto_laporan/        ← Foto dari laporan masyarakat
```

---

## ⚙️ Instalasi (XAMPP)

### 1. Salin Folder
```
C:\xampp\htdocs\webgis-kemiskinan\
```

### 2. Buat Database
- Buka **phpMyAdmin** → `http://localhost/phpmyadmin`
- Buat database baru: `webgis_kemiskinan`
- Import: `sql/schema.sql`

### 3. Konfigurasi DB
Edit `php/koneksi.php`:
```php
define('DB_NAME', 'webgis_kemiskinan');
define('DB_USER', 'root');
define('DB_PASS', '');   // sesuaikan password MySQL Anda
```

### 4. Inisialisasi User
Buka sekali di browser:
```
http://localhost/webgis-kemiskinan/admin/init_admin.php
```
> **Hapus file ini setelah dijalankan!**

### 5. Akses Sistem
| URL | Keterangan |
|---|---|
| `http://localhost/webgis-kemiskinan/` | Peta WebGIS (publik) |
| `http://localhost/webgis-kemiskinan/admin/login.php` | Panel Admin |

---

## 👤 Akun Demo

| Username | Password | Role |
|---|---|---|
| `admin` | `password` | Administrator |
| `pengurus1` | `password` | Pengurus Masjid Al-Falah |
| `pimpinan1` | `password` | Pimpinan Daerah |
| `warga1` | `password` | Masyarakat |

> Hash yang digunakan adalah hash Laravel default untuk string `"password"`.  
> Untuk produksi, ganti dengan: `password_hash('PasswordBaru', PASSWORD_BCRYPT)`

---

## 🗄️ Tabel Database

| Tabel | Fungsi |
|---|---|
| `roles` | Definisi role sistem |
| `users` | Akun pengguna |
| `rumah_ibadah` | Data rumah ibadah + radius |
| `keluarga` | Data KK |
| `penduduk` | Data individu (NIK sebagai PK) |
| `riwayat_pelatihan` | Pelatihan kerja per penduduk |
| `jenis_bantuan` | Master jenis bantuan |
| `histori_bantuan` | Riwayat penyaluran bantuan |
| `laporan` | Laporan kondisi dari masyarakat |
| `pesan` | Chat antar pengurus |
| `notifikasi` | Notifikasi per user |
| `log_aktivitas` | Audit trail semua perubahan data |
| `jalan_lines` | Layer jalan (GeoJSON) |
| `parsil_tanah` | Layer parsil tanah (GeoJSON) |

---

## 🧠 Logika Khusus

### Validasi Pendidikan vs Umur
- Umur 7–12 tahun → wajib masih sekolah (SD)
- Umur 13–15 tahun → wajib masih sekolah (SMP)
- Umur 16–18 tahun → wajib masih sekolah (SMA/SMK)
- Umur >18 tahun → bebas (bisa tidak sekolah / ikut pelatihan)

### Prioritas Bantuan (otomatis)
| Kondisi | Prioritas |
|---|---|
| Miskin + Sakit Parah/Disabilitas | 🔴 KRITIS |
| Miskin + Belum pernah dibantu | 🟠 TINGGI |
| Miskin (umum) | 🟡 SEDANG |
| Rentan + Sakit | 🟡 SEDANG |
| Lainnya | 🟢 RENDAH |

### Umur Otomatis
Kolom `umur` di tabel `penduduk` adalah **GENERATED COLUMN** — dihitung langsung dari `tanggal_lahir` setiap kali data dibaca. Tidak perlu update manual.

---

## 🔒 Keamanan
- PDO dengan prepared statements (anti SQL injection)
- `password_hash()` / `password_verify()` untuk password
- Session dengan `session_regenerate_id()` saat login
- Validasi input di sisi server untuk semua endpoint
- Otorisasi per-role di setiap API endpoint
- Upload file: validasi ekstensi + ukuran + nama acak

---

## 📱 Cara Buat Laporan (Masyarakat)
1. Buka `http://localhost/webgis-kemiskinan/`
2. Klik tombol **"Buat Laporan"** di pojok kanan atas
3. Isi deskripsi kondisi (nama pelapor opsional)
4. Klik peta untuk memilih lokasi otomatis
5. Kirim → pengurus akan mendapat notifikasi otomatis

---

## 🔄 Pengembangan Lanjutan (Saran)
- [ ] Integrasi SMS/WhatsApp notifikasi
- [ ] Export PDF laporan distribusi bantuan
- [ ] Login via Google / warga menggunakan NIK + tanggal lahir
- [ ] Mobile app (React Native / Flutter)
- [ ] Sinkronisasi data dengan Dukcapil
