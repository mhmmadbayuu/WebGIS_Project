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
                "status_kepemilikan" => $row['status_kepemilikan'] ?? null,
                "luas_m2" => isset($row['luas_m2']) ? (float)$row['luas_m2'] : null
            ]
        ];
    }
    return $features;
}

try {
    // Preferred schema: geojson column.
    $stmt = $pdo->query("SELECT id, status_kepemilikan, luas_m2, geojson FROM parsil_tanah ORDER BY id ASC");
    $features = rowsToFeatures($stmt);
} catch (Exception $e) {
    // Fallback schema: spatial geom.
    $stmt = $pdo->query("
        SELECT
            id,
            status_kepemilikan,
            luas_m2,
            ST_AsGeoJSON(geom) AS geojson
        FROM parsil_tanah
        ORDER BY id ASC
    ");
    $features = rowsToFeatures($stmt);
}

echo json_encode([
    "type" => "FeatureCollection",
    "features" => $features
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
