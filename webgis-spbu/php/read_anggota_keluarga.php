<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'koneksi.php';

$penduduk_id = isset($_GET['penduduk_id']) ? (int)$_GET['penduduk_id'] : 0;
if ($penduduk_id <= 0) {
  echo json_encode([]);
  exit;
}

try {
  $stmt = $pdo->prepare("
    SELECT id, penduduk_id, nama, hubungan, umur, pekerjaan, keterangan
    FROM anggota_keluarga
    WHERE penduduk_id = :id
    ORDER BY id ASC
  ");
  $stmt->execute([':id' => $penduduk_id]);
  $rows = $stmt->fetchAll();
  echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Exception $e) {
  echo json_encode([]);
}

