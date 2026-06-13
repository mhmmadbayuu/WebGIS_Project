<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'koneksi.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(["success" => false, "message" => "Data kosong"]);
    exit;
}

$id = (int)($data['id'] ?? 0);
$nama = trim($data['nama'] ?? '');
$no = trim($data['no'] ?? '');
$deskripsi = trim($data['deskripsi'] ?? '');
$status_24jam = isset($data['status_24jam']) ? (int)$data['status_24jam'] : 0;
$latitude = $data['latitude'] ?? null;
$longitude = $data['longitude'] ?? null;

if ($id <= 0 || $nama === '' || $no === '' || $latitude === null || $longitude === null) {
    echo json_encode(["success" => false, "message" => "Data update point belum lengkap"]);
    exit;
}

$sql = "UPDATE spbu_points
        SET nama = :nama,
            no = :no,
            deskripsi = :deskripsi,
            status_24jam = :status_24jam,
            latitude = :latitude,
            longitude = :longitude
        WHERE id = :id";

$pointGeojson = json_encode([
    "type" => "Point",
    "coordinates" => [(float)$longitude, (float)$latitude]
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nama' => $nama,
        ':no' => $no,
        ':deskripsi' => $deskripsi,
        ':status_24jam' => $status_24jam,
        ':latitude' => $latitude,
        ':longitude' => $longitude,
        ':id' => $id
    ]);

    echo json_encode(["success" => true, "message" => "Data point berhasil diupdate"]);
    exit;
} catch (Exception $inner) {
    // continue fallback
}

// Fallback schema: geom LONGTEXT
try {
    $stmt = $pdo->prepare("UPDATE spbu_points
        SET nama = :nama,
            no = :no,
            deskripsi = :deskripsi,
            status_24jam = :status_24jam,
            latitude = :latitude,
            longitude = :longitude,
            geom = :geom
        WHERE id = :id");

    $stmt->execute([
        ':nama' => $nama,
        ':no' => $no,
        ':deskripsi' => $deskripsi,
        ':status_24jam' => $status_24jam,
        ':latitude' => $latitude,
        ':longitude' => $longitude,
        ':geom' => $pointGeojson,
        ':id' => $id
    ]);

    echo json_encode(["success" => true, "message" => "Data point berhasil diupdate"]);
    exit;
} catch (Exception $inner2) {
    // continue fallback
}

// Fallback schema: only (nama, geom) where geom is LONGTEXT GeoJSON
try {
    $stmt = $pdo->prepare("UPDATE spbu_points
        SET nama = :nama,
            geom = :geom
        WHERE id = :id");
    $stmt->execute([
        ':nama' => $nama,
        ':geom' => $pointGeojson,
        ':id' => $id
    ]);

    echo json_encode(["success" => true, "message" => "Data point berhasil diupdate"]);
    exit;
} catch (Exception $inner3) {
    // continue fallback
}

// Fallback schema: spatial geom
$stmt = $pdo->prepare("UPDATE spbu_points
    SET nama = :nama,
        geom = ST_GeomFromGeoJSON(:geom)
    WHERE id = :id");
$stmt->execute([
    ':nama' => $nama,
    ':geom' => $pointGeojson,
    ':id' => $id
]);

echo json_encode(["success" => true, "message" => "Data point berhasil diupdate"]);
