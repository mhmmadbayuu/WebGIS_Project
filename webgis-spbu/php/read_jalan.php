<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'koneksi.php';

$features = [];

function rowsToFeatures($stmt) {
    $features = [];
    while ($row = $stmt->fetch()) {
        $geometry = json_decode($row['geojson'] ?? '', true);
        if (!$geometry) {
            continue;
        }

        $features[] = [
            "type" => "Feature",
            "geometry" => $geometry,
            "properties" => [
                "id" => (int)($row['id'] ?? 0),
                "nama_jalan" => $row['nama_jalan'] ?? null,
                "status_jalan" => $row['status_jalan'] ?? null,
                "panjang_meter" => isset($row['panjang_meter']) ? (float)$row['panjang_meter'] : null
            ]
        ];
    }
    return $features;
}

try {
    // Preferred schema: geojson column.
    $stmt = $pdo->query("SELECT id, nama_jalan, status_jalan, panjang_meter, geojson FROM jalan_lines ORDER BY id ASC");
    $features = rowsToFeatures($stmt);
} catch (Exception $e) {
    // Fallback schema: spatial geom.
    $stmt = $pdo->query("
        SELECT
            id,
            nama_jalan,
            status_jalan,
            panjang_meter,
            ST_AsGeoJSON(geom) AS geojson
        FROM jalan_lines
        ORDER BY id ASC
    ");
    $features = rowsToFeatures($stmt);
}

echo json_encode([
    "type" => "FeatureCollection",
    "features" => $features
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
