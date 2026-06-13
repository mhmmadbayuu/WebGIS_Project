<?php
header('Content-Type: application/json; charset=utf-8');

$lat = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
$lng = isset($_GET['lng']) ? (float)$_GET['lng'] : null;

if ($lat === null || $lng === null) {
    echo json_encode(["success" => false, "message" => "lat/lng wajib diisi"]);
    exit;
}

// Nominatim usage policy requires a valid User-Agent identifying the application.
$url = "https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=" . urlencode($lat) . "&lon=" . urlencode($lng);

try {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "User-Agent: webgis-spbu/1.0 (XAMPP PHP reverse geocode)"
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($resp === false || $code < 200 || $code >= 300) {
        echo json_encode(["success" => false, "message" => $err ?: ("HTTP " . $code)]);
        exit;
    }

    $json = json_decode($resp, true);
    $display = $json['display_name'] ?? null;
    echo json_encode([
        "success" => true,
        "address" => $display,
        "raw" => $json
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
