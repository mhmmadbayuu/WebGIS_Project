<?php
// Script inisialisasi — jalankan SEKALI untuk membuat akun admin & demo
// URL: http://localhost/webgis-kemiskinan/admin/init_admin.php
// HAPUS file ini setelah dipakai di produksi!

require_once __DIR__ . '/../php/koneksi.php';

$users = [
    ['admin',      'Administrator',            '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, null],
    ['pengurus1',  'H. Ahmad Fauzi (Pengurus)', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 1],
    ['pimpinan1',  'Camat Pontianak Selatan',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, null],
    ['warga1',     'Budi Santoso',              '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, null],
];

$created = 0;
foreach ($users as [$uname, $nama, $hash, $role, $ri]) {
    $cek = $pdo->prepare("SELECT id FROM users WHERE username=:u");
    $cek->execute([':u'=>$uname]);
    if (!$cek->fetch()) {
        $pdo->prepare("INSERT INTO users (role_id,nama,username,password_hash,rumah_ibadah_id) VALUES(:r,:n,:u,:p,:ri)")
            ->execute([':r'=>$role,':n'=>$nama,':u'=>$uname,':p'=>$hash,':ri'=>$ri]);
        $created++;
        echo "✅ User '$uname' dibuat.<br>";
    } else {
        echo "ℹ️ User '$uname' sudah ada — dilewati.<br>";
    }
}

echo "<br><strong>$created user baru dibuat.</strong><br>";
echo "<br>Password semua akun demo: <code>password</code><br>";
echo "<br><strong>Segera hapus file ini setelah digunakan!</strong><br>";
echo "<br><a href='login.php'>→ Ke halaman login</a>";
