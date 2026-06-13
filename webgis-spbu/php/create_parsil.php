<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'koneksi.php';

// Payload expected from js/app.js:
// { status_kepemilikan, luas_m2, geometry }
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(["success" => false, "status" => "error", "message" => "Payload JSON kosong atau tidak valid."]);
    exit;
}

$status_kepemilikan = trim($data['status_kepemilikan'] ?? '');
$luas_m2 = (float)($data['luas_m2'] ?? 0);
$geometry = $data['geometry'] ?? null;

if ($status_kepemilikan === '' || !$geometry) {
    echo json_encode(["success" => false, "status" => "error", "message" => "Data parsil belum lengkap (status/geometry)."]);
    exit;
}

try {
    $geomJson = json_encode($geometry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO parsil_tanah (status_kepemilikan, luas_m2, geojson)
            VALUES (:status_kepemilikan, :luas_m2, :geojson)
        ");

        $stmt->execute([
            ':status_kepemilikan' => $status_kepemilikan,
            ':luas_m2' => $luas_m2,
            ':geojson' => $geomJson
        ]);
    } catch (Exception $inner) {
        // Fallback untuk skema spatial: simpan ke kolom `geom` jika `geojson` tidak ada.
        try {
            $stmt = $pdo->prepare("
                INSERT INTO parsil_tanah (status_kepemilikan, luas_m2, geom)
                VALUES (:status_kepemilikan, :luas_m2, ST_GeomFromGeoJSON(:geom))
            ");

            $stmt->execute([
                ':status_kepemilikan' => $status_kepemilikan,
                ':luas_m2' => $luas_m2,
                ':geom' => $geomJson
            ]);
        } catch (Exception $inner2) {
            // Last resort: minimal insert.
            $stmt = $pdo->prepare("
                INSERT INTO parsil_tanah (geom)
                VALUES (ST_GeomFromGeoJSON(:geom))
            ");
            $stmt->execute([':geom' => $geomJson]);
        }
    }

    echo json_encode(["success" => true, "status" => "success", "message" => "Area Parsil berhasil disimpan."]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "status" => "error", "message" => $e->getMessage()]);
}
?>
