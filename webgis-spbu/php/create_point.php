<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'koneksi.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(["success" => false, "status" => "error", "message" => "Payload JSON kosong atau tidak valid."]);
    exit;
}

$nama = trim($data['nama'] ?? '');
$no = trim($data['no'] ?? '');
$deskripsi = trim($data['deskripsi'] ?? '');
$status_24jam = isset($data['status_24jam']) ? (int)$data['status_24jam'] : 0;
$latitude = $data['latitude'] ?? null;
$longitude = $data['longitude'] ?? null;

if ($nama === '' || $no === '' || $latitude === null || $longitude === null) {
    echo json_encode(["success" => false, "status" => "error", "message" => "Data point belum lengkap (nama/no/lat/lng)."]);
    exit;
}

$pointGeojson = json_encode([
    "type" => "Point",
    "coordinates" => [(float)$longitude, (float)$latitude]
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Optional legacy field if client ever sends it
$geomPayload = $data['geom'] ?? null;
if (!is_string($geomPayload) || $geomPayload === '') {
    $geomPayload = $pointGeojson;
}

try {
    // Preferred schema (non-spatial, consistent with update_point.php)
    try {
        $stmt = $pdo->prepare("
            INSERT INTO spbu_points (nama, no, deskripsi, status_24jam, latitude, longitude)
            VALUES (:nama, :no, :deskripsi, :status_24jam, :latitude, :longitude)
        ");

        $stmt->execute([
            ':nama' => $nama,
            ':no' => $no,
            ':deskripsi' => $deskripsi,
            ':status_24jam' => $status_24jam,
            ':latitude' => $latitude,
            ':longitude' => $longitude
        ]);

        echo json_encode([
            "success" => true,
            "status" => "success",
            "message" => "Point SPBU berhasil ditambahkan",
            "id" => (int)$pdo->lastInsertId()
        ]);
        exit;
    } catch (Exception $e1) {
        // continue to fallback attempts
    }

    // Fallback schema: `geom` stored as LONGTEXT (GeoJSON string)
    try {
        $stmt = $pdo->prepare("
            INSERT INTO spbu_points (nama, no, deskripsi, status_24jam, latitude, longitude, geom)
            VALUES (:nama, :no, :deskripsi, :status_24jam, :latitude, :longitude, :geom)
        ");

        $stmt->execute([
            ':nama' => $nama,
            ':no' => $no,
            ':deskripsi' => $deskripsi,
            ':status_24jam' => $status_24jam,
            ':latitude' => $latitude,
            ':longitude' => $longitude,
            ':geom' => $geomPayload
        ]);

        echo json_encode([
            "success" => true,
            "status" => "success",
            "message" => "Point SPBU berhasil ditambahkan",
            "id" => (int)$pdo->lastInsertId()
        ]);
        exit;
    } catch (Exception $e2) {
        // continue to fallback attempts
    }

    // Fallback schema: only (nama, geom) where geom is LONGTEXT GeoJSON
    try {
        $stmt = $pdo->prepare("INSERT INTO spbu_points (nama, geom) VALUES (:nama, :geom)");
        $stmt->execute([
            ':nama' => $nama,
            ':geom' => $geomPayload
        ]);

        echo json_encode([
            "success" => true,
            "status" => "success",
            "message" => "Point SPBU berhasil ditambahkan",
            "id" => (int)$pdo->lastInsertId()
        ]);
        exit;
    } catch (Exception $e3) {
        // continue to fallback attempts
    }

    // Fallback schema: `geom` is spatial GEOMETRY/POINT
    $stmt = $pdo->prepare("INSERT INTO spbu_points (nama, geom) VALUES (:nama, ST_GeomFromGeoJSON(:geom))");
    $stmt->execute([
        ':nama' => $nama,
        ':geom' => $pointGeojson
    ]);

    echo json_encode([
        "success" => true,
        "status" => "success",
        "message" => "Point SPBU berhasil ditambahkan",
        "id" => (int)$pdo->lastInsertId()
    ]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "status" => "error", "message" => $e->getMessage()]);
}
