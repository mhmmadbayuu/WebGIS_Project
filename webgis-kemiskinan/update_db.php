<?php
require_once __DIR__ . '/php/koneksi.php';

try {
    // Tambahkan kolom pelapor_id jika belum ada
    $pdo->exec("ALTER TABLE laporan ADD COLUMN pelapor_id INT UNSIGNED NULL AFTER nama_pelapor");
    $pdo->exec("ALTER TABLE laporan ADD CONSTRAINT fk_lap_pelapor FOREIGN KEY (pelapor_id) REFERENCES users(id) ON DELETE SET NULL");
    echo "Kolom pelapor_id berhasil ditambahkan.\n";
} catch (PDOException $e) {
    echo "Error atau kolom sudah ada: " . $e->getMessage() . "\n";
}
