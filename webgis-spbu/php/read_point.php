<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'koneksi.php';

function normalizePointRows($rows) {
    foreach ($rows as &$row) {
        if ((!isset($row['latitude']) || $row['latitude'] === null) && isset($row['geom']) && is_string($row['geom'])) {
            $geom = json_decode($row['geom'], true);
            if (is_array($geom) && ($geom['type'] ?? '') === 'Point') {
                $coords = $geom['coordinates'] ?? null;
                if (is_array($coords) && count($coords) >= 2) {
                    // GeoJSON: [lng, lat]
                    $row['longitude'] = $coords[0];
                    $row['latitude'] = $coords[1];
                }
            }
        }
    }
    return $rows;
}

try {
    // Preferred schema: has latitude/longitude columns.
    $stmt = $pdo->query("
        SELECT id, nama, no, deskripsi, status_24jam, latitude, longitude
        FROM spbu_points
        ORDER BY id ASC
    ");
    $rows = $stmt->fetchAll();
    echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
} catch (Exception $e) {
    // continue
}

try {
    // Fallback schema: latitude/longitude exists, geom stored as LONGTEXT GeoJSON.
    $stmt = $pdo->query("
        SELECT id, nama, no, deskripsi, status_24jam, latitude, longitude, geom
        FROM spbu_points
        ORDER BY id ASC
    ");
    $rows = $stmt->fetchAll();
    $rows = normalizePointRows($rows);
    echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
} catch (Exception $e) {
    // continue
}

try {
    // Fallback schema: only geom LONGTEXT; derive latitude/longitude from GeoJSON.
    $stmt = $pdo->query("
        SELECT
            id,
            nama,
            NULL AS no,
            NULL AS deskripsi,
            0 AS status_24jam,
            geom
        FROM spbu_points
        ORDER BY id ASC
    ");
    $rows = $stmt->fetchAll();
    $rows = normalizePointRows($rows);
    echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
} catch (Exception $e) {
    // continue
}

try {
    // Fallback schema: spatial geom
    $stmt = $pdo->query("
        SELECT
            id,
            nama,
            NULL AS no,
            NULL AS deskripsi,
            0 AS status_24jam,
            ST_Y(geom) AS latitude,
            ST_X(geom) AS longitude
        FROM spbu_points
        ORDER BY id ASC
    ");
    $rows = $stmt->fetchAll();
    echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
} catch (Exception $e) {
    // continue
}

// Last resort
$stmt = $pdo->query("SELECT * FROM spbu_points ORDER BY id ASC");
$rows = $stmt->fetchAll();
echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
