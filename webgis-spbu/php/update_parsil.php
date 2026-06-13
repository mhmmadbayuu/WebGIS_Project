<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'koneksi.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(["success" => false, "message" => "Data kosong"]);
    exit;
}

$id = (int)($data['id'] ?? 0);
$status_kepemilikan = trim($data['status_kepemilikan'] ?? '');
$luas_m2 = (float)($data['luas_m2'] ?? 0);
$geometry = $data['geometry'] ?? null;

if ($id <= 0 || $status_kepemilikan === '' || !$geometry) {
    echo json_encode(["success" => false, "message" => "Data update parsil belum lengkap"]);
    exit;
}

$sql = "UPDATE parsil_tanah
        SET status_kepemilikan = :status_kepemilikan,
            luas_m2 = :luas_m2,
            geojson = :geojson
        WHERE id = :id";

$geojson = json_encode($geometry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':status_kepemilikan' => $status_kepemilikan,
        ':luas_m2' => $luas_m2,
        ':geojson' => $geojson,
        ':id' => $id
    ]);

    echo json_encode(["success" => true, "message" => "Data parsil berhasil diupdate"]);
    exit;
} catch (Exception $inner) {
    // fallback to spatial geom schema
}

$stmt = $pdo->prepare("UPDATE parsil_tanah
    SET status_kepemilikan = :status_kepemilikan,
        luas_m2 = :luas_m2,
        geom = ST_GeomFromGeoJSON(:geojson)
    WHERE id = :id");

$stmt->execute([
    ':status_kepemilikan' => $status_kepemilikan,
    ':luas_m2' => $luas_m2,
    ':geojson' => $geojson,
    ':id' => $id
]);

echo json_encode(["success" => true, "message" => "Data parsil berhasil diupdate"]);
