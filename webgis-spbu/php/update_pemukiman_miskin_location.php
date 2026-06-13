<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'koneksi.php';

function haversine_m($lat1, $lon1, $lat2, $lon2) {
  $R = 6371000.0;
  $phi1 = deg2rad((float)$lat1);
  $phi2 = deg2rad((float)$lat2);
  $dphi = deg2rad((float)$lat2 - (float)$lat1);
  $dlambda = deg2rad((float)$lon2 - (float)$lon1);
  $a = sin($dphi/2) * sin($dphi/2) + cos($phi1) * cos($phi2) * sin($dlambda/2) * sin($dlambda/2);
  $c = 2 * atan2(sqrt($a), sqrt(1-$a));
  return $R * $c;
}

function compute_nearest_ibadah(PDO $pdo, $lat, $lng) {
  $stmt = $pdo->query("SELECT id, latitude, longitude, radius_meter FROM rumah_ibadah_points");
  $rows = $stmt->fetchAll();
  $best = null;
  foreach ($rows as $r) {
    $rLat = (float)$r['latitude'];
    $rLng = (float)$r['longitude'];
    $radius = (float)$r['radius_meter'];
    if ($radius <= 0) continue;
    $d = haversine_m($lat, $lng, $rLat, $rLng);
    if ($d <= $radius) {
      if ($best === null || $d < $best['jarak_meter']) {
        $best = ['rumah_ibadah_id' => (int)$r['id'], 'jarak_meter' => $d];
      }
    }
  }
  return $best ?: ['rumah_ibadah_id' => null, 'jarak_meter' => null];
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
  echo json_encode(["success" => false, "message" => "Payload JSON kosong atau tidak valid."]);
  exit;
}

$id = (int)($data['id'] ?? 0);
$lat = isset($data['latitude']) ? (float)$data['latitude'] : null;
$lng = isset($data['longitude']) ? (float)$data['longitude'] : null;
if ($id <= 0 || $lat === null || $lng === null) {
  echo json_encode(["success" => false, "message" => "Data update lokasi belum lengkap (id/lat/lng)."]);
  exit;
}

try {
  $nearest = compute_nearest_ibadah($pdo, $lat, $lng);
  $cols = $pdo->query("SHOW COLUMNS FROM pemukiman_miskin_points")->fetchAll(PDO::FETCH_COLUMN, 0);
  $hasNew = in_array('kk_nama', $cols, true);

  if ($hasNew) {
    $stmt = $pdo->prepare("
      UPDATE pemukiman_miskin_points
      SET latitude = :lat,
          longitude = :lng,
          jarak_meter = :jarak_meter,
          rumah_ibadah_id = :rumah_ibadah_id
      WHERE id = :id
    ");
    $stmt->execute([
      ':lat' => $lat,
      ':lng' => $lng,
      ':jarak_meter' => $nearest['jarak_meter'],
      ':rumah_ibadah_id' => $nearest['rumah_ibadah_id'],
      ':id' => $id
    ]);
  } else {
    $stmt = $pdo->prepare("
      UPDATE pemukiman_miskin_points
      SET latitude = :lat,
          longitude = :lng,
          rumah_ibadah_id = :rumah_ibadah_id
      WHERE id = :id
    ");
    $stmt->execute([
      ':lat' => $lat,
      ':lng' => $lng,
      ':rumah_ibadah_id' => $nearest['rumah_ibadah_id'],
      ':id' => $id
    ]);
  }

  echo json_encode([
    "success" => true,
    "rumah_ibadah_id" => $nearest['rumah_ibadah_id'],
    "jarak_meter" => $nearest['jarak_meter'],
    "message" => "Lokasi penduduk miskin berhasil diupdate"
  ]);
} catch (Exception $e) {
  echo json_encode(["success" => false, "message" => $e->getMessage()]);
}

