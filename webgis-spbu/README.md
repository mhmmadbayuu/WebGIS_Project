# WebGIS Sebaran Penduduk Miskin + Rumah Ibadah

Stack: PHP (XAMPP), MySQL/MariaDB, LeafletJS + Leaflet.draw (tanpa framework).

## Fitur
- Peta interaktif (fullscreen) + layer: rumah ibadah, radius, penduduk miskin.
- CRUD lewat aksi di peta (klik untuk point, popup untuk edit/hapus, edit lokasi + drag).
- Reverse geocoding (server proxy `php/reverse_geocode.php` ke Nominatim).
- Auto-relasi penduduk miskin → rumah ibadah terdekat **dalam radius** (jarak dihitung otomatis).
- Marker penduduk miskin:
  - Merah: Belum dibantu
  - Kuning: Menunggu verifikasi
  - Hijau: Sudah dibantu
  - Outline: menunjukkan inside/outside radius rumah ibadah
- Form penduduk miskin mendukung anggota keluarga (dinamis) + upload bukti (jpg/png/webp/pdf).
- Admin panel (login) + CRUD dasar + import/export CSV.

## Instalasi (XAMPP)
1. Pastikan folder project berada di `htdocs`, misalnya: `C:\\xampp\\htdocs\\webgis-spbu`.
2. Buat database `webgis_spbu` di phpMyAdmin.
3. Import schema: `sql/schema_webgis_spbu.sql`.
4. Sesuaikan konfigurasi DB di `php/koneksi.php`.
5. Jalankan Apache + MySQL di XAMPP.
6. Buka aplikasi:
   - WebGIS: `http://localhost/webgis-spbu/`
   - Admin: `http://localhost/webgis-spbu/admin/login.php`
     - Jika belum ada user, jalankan sekali: `http://localhost/webgis-spbu/admin/init_admin.php`

## Catatan Upload Bukti
- File disimpan ke `uploads/bukti/`.
- Pastikan folder ini writable oleh Apache.

