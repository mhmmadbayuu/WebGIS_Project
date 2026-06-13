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

function compute_nearest_ibadah(array $ibadahRows, $lat, $lng) {
  $best = null;
  foreach ($ibadahRows as $r) {
    $rLat = (float)($r['latitude'] ?? 0);
    $rLng = (float)($r['longitude'] ?? 0);
    $radius = (float)($r['radius_meter'] ?? 0);
    if ($radius <= 0) continue;
    $d = haversine_m($lat, $lng, $rLat, $rLng);
    if ($d <= $radius) {
      if ($best === null || $d < $best['jarak_meter']) {
        $best = [
          'rumah_ibadah_id' => (int)($r['id'] ?? 0),
          'jarak_meter' => $d,
          'row' => $r
        ];
      }
    }
  }
  return $best;
}

// Prefer new schema; fall back to old schema if needed.
$cols = [];
try {
  $stmtCols = $pdo->query("SHOW COLUMNS FROM pemukiman_miskin_points");
  if ($stmtCols) {
    $cols = $stmtCols->fetchAll(PDO::FETCH_COLUMN, 0);
  }
} catch (Exception $e) {
  // Jika tabel belum dibuat, abaikan dan kembalikan array kosong
}
$hasNew = in_array('kk_nama', $cols, true);

$hasJarak = in_array('jarak_meter', $cols, true);
$hasRumahIbadahId = in_array('rumah_ibadah_id', $cols, true);

// Fetch rumah ibadah once; used for real-time coverage computation server-side.
$ibadah = [];
try {
  $stmtI = $pdo->query("SELECT id, nama, jenis, kontak, radius_meter, latitude, longitude FROM rumah_ibadah_points");
  $ibadah = $stmtI->fetchAll();
} catch (Exception $e) {
  $ibadah = [];
}

// Load pemukiman rows
$rows = [];
try {
  if ($hasNew) {
    $stmt = $pdo->query("
      SELECT p.*,
             (
               SELECT COUNT(*) FROM anggota_keluarga a WHERE a.penduduk_id = p.id
             ) AS anggota_count
      FROM pemukiman_miskin_points p
      ORDER BY p.id ASC
    ");
    $rows = $stmt->fetchAll();
  } else {
    $stmt = $pdo->query("SELECT p.* FROM pemukiman_miskin_points p ORDER BY p.id ASC");
    $rows = $stmt->fetchAll();
  }
} catch (Exception $e) {
  $rows = [];
}

// Best-effort: keep stored rumah_ibadah_id/jarak_meter in sync with latest radius rules.
$upd = null;
try {
  if ($hasRumahIbadahId && $hasJarak) {
    $upd = $pdo->prepare("UPDATE pemukiman_miskin_points SET rumah_ibadah_id = :rid, jarak_meter = :jarak WHERE id = :id");
  } elseif ($hasRumahIbadahId) {
    $upd = $pdo->prepare("UPDATE pemukiman_miskin_points SET rumah_ibadah_id = :rid WHERE id = :id");
  }
} catch (Exception $e) {
  $upd = null;
}

foreach ($rows as &$p) {
  $lat = isset($p['latitude']) ? (float)$p['latitude'] : null;
  $lng = isset($p['longitude']) ? (float)$p['longitude'] : null;
  if ($lat === null || $lng === null) continue;

  $best = compute_nearest_ibadah($ibadah, $lat, $lng);
  $rid = $best ? (int)$best['rumah_ibadah_id'] : null;
  $jarak = $best ? (float)$best['jarak_meter'] : null;
  $storedRid = isset($p['rumah_ibadah_id']) ? (int)$p['rumah_ibadah_id'] : null;

  // overwrite output with authoritative computed coverage
  $p['rumah_ibadah_id'] = $rid;
  if ($hasJarak) $p['jarak_meter'] = $jarak;

  if ($best && isset($best['row']) && is_array($best['row'])) {
    $p['rumah_ibadah_nama'] = $best['row']['nama'] ?? null;
    $p['rumah_ibadah_jenis'] = $best['row']['jenis'] ?? null;
    $p['rumah_ibadah_kontak'] = $best['row']['kontak'] ?? null;
    $p['rumah_ibadah_radius'] = $best['row']['radius_meter'] ?? null;
  } else {
    $p['rumah_ibadah_nama'] = null;
    $p['rumah_ibadah_jenis'] = null;
    $p['rumah_ibadah_kontak'] = null;
    $p['rumah_ibadah_radius'] = null;
  }

  // best-effort DB sync
  try {
    if ($upd && ((string)$storedRid !== (string)$rid)) {
      if ($hasRumahIbadahId && $hasJarak) {
        $upd->execute([':rid' => $rid, ':jarak' => $jarak, ':id' => (int)$p['id']]);
      } elseif ($hasRumahIbadahId) {
        $upd->execute([':rid' => $rid, ':id' => (int)$p['id']]);
      }
    }
  } catch (Exception $e) {
    // ignore
  }
}
unset($p);

echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
