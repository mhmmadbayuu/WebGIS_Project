<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'koneksi.php';

// Include jumlah penduduk binaan per rumah ibadah
$stmt = $pdo->query("
  SELECT r.*,
         (
           SELECT COUNT(*)
           FROM pemukiman_miskin_points p
           WHERE p.rumah_ibadah_id = r.id
         ) AS binaan_count
  FROM rumah_ibadah_points r
  ORDER BY r.id ASC
");
$rows = $stmt->fetchAll();
echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
