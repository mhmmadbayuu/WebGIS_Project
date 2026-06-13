<?php
require_once 'php/koneksi.php';

try {
    $pdo->exec("ALTER TABLE rumah_ibadah_points ADD COLUMN IF NOT EXISTS jenis VARCHAR(30) NOT NULL DEFAULT 'Lainnya' AFTER nama");
    $pdo->exec("ALTER TABLE rumah_ibadah_points ADD COLUMN IF NOT EXISTS kontak VARCHAR(120) NULL AFTER jenis");
    echo "Columns added successfully.\n";
} catch (PDOException $e) {
    echo "Failed to add columns: " . $e->getMessage() . "\n";
}
