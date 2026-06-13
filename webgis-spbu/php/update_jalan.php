<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'koneksi.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(["success" => false, "message" => "Data kosong"]);
    exit;
}

$id = (int)($data['id'] ?? 0);
$nama_jalan = trim($data['nama_jalan'] ?? '');
$status_jalan = trim($data['status_jalan'] ?? '');
$panjang_meter = (float)($data['panjang_meter'] ?? 0);
$geometry = $data['geometry'] ?? null;

if ($id <= 0 || $nama_jalan === '' || $status_jalan === '' || !$geometry) {
    echo json_encode(["success" => false, "message" => "Data update jalan belum lengkap"]);
    exit;
}

$sql = "UPDATE jalan_lines
        SET nama_jalan = :nama_jalan,
            status_jalan = :status_jalan,
            panjang_meter = :panjang_meter,
            geojson = :geojson
        WHERE id = :id";

$geojson = json_encode($geometry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nama_jalan' => $nama_jalan,
        ':status_jalan' => $status_jalan,
        ':panjang_meter' => $panjang_meter,
        ':geojson' => $geojson,
        ':id' => $id
    ]);

    echo json_encode(["success" => true, "message" => "Data jalan berhasil diupdate"]);
    exit;
} catch (Exception $inner) {
    // fallback to spatial geom schema
}

$stmt = $pdo->prepare("UPDATE jalan_lines
    SET nama_jalan = :nama_jalan,
        status_jalan = :status_jalan,
        panjang_meter = :panjang_meter,
        geom = ST_GeomFromGeoJSON(:geojson)
    WHERE id = :id");

$stmt->execute([
    ':nama_jalan' => $nama_jalan,
    ':status_jalan' => $status_jalan,
    ':panjang_meter' => $panjang_meter,
    ':geojson' => $geojson,
    ':id' => $id
]);

echo json_encode(["success" => true, "message" => "Data jalan berhasil diupdate"]);
