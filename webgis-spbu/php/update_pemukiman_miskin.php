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

function get_payload() {
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'multipart/form-data') !== false) {
        return [
            'id' => $_POST['id'] ?? 0,
            'kk_nama' => $_POST['kk_nama'] ?? '',
            'nik' => $_POST['nik'] ?? '',
            'jumlah_anggota' => $_POST['jumlah_anggota'] ?? '',
            'latitude' => $_POST['latitude'] ?? null,
            'longitude' => $_POST['longitude'] ?? null,
            'address' => $_POST['address'] ?? '',
            'kelurahan' => $_POST['kelurahan'] ?? '',
            'kecamatan' => $_POST['kecamatan'] ?? '',
            'status_bantuan' => $_POST['status_bantuan'] ?? 'Belum dibantu',
            'jenis_bantuan' => $_POST['jenis_bantuan'] ?? '',
            'tanggal_bantuan' => $_POST['tanggal_bantuan'] ?? '',
            'anggota' => $_POST['anggota'] ?? '[]'
        ];
    }
    $json = json_decode(file_get_contents('php://input'), true);
    return is_array($json) ? $json : null;
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

function save_upload_if_any() {
    if (!isset($_FILES['bukti_file']) || !is_array($_FILES['bukti_file'])) return null;
    if (($_FILES['bukti_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    $dir = __DIR__ . '/../uploads/bukti';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    if (!is_dir($dir)) return null;

    $orig = (string)($_FILES['bukti_file']['name'] ?? 'file');
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','webp','pdf'];
    if ($ext && !in_array($ext, $allowed, true)) return null;

    $safeBase = preg_replace('/[^a-zA-Z0-9._-]+/', '_', pathinfo($orig, PATHINFO_FILENAME));
    $safeBase = trim($safeBase, '_');
    if ($safeBase === '') $safeBase = 'bukti';
    $filename = $safeBase . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . ($ext ? ('.' . $ext) : '');
    $dest = $dir . '/' . $filename;
    if (!move_uploaded_file($_FILES['bukti_file']['tmp_name'], $dest)) return null;
    return 'uploads/bukti/' . $filename;
}

$data = get_payload();
if (!$data) {
    echo json_encode(["success" => false, "message" => "Payload kosong atau tidak valid."]);
    exit;
}

$id = (int)($data['id'] ?? 0);
$kk_nama = trim($data['kk_nama'] ?? $data['nama'] ?? '');
$nik = trim($data['nik'] ?? '');
$jumlah_anggota = (int)($data['jumlah_anggota'] ?? 0);
$latitude = $data['latitude'] ?? null;
$longitude = $data['longitude'] ?? null;
$address = trim($data['address'] ?? '');
$kelurahan = trim($data['kelurahan'] ?? '');
$kecamatan = trim($data['kecamatan'] ?? '');
$status_bantuan = trim($data['status_bantuan'] ?? 'Belum dibantu');
$jenis_bantuan = trim($data['jenis_bantuan'] ?? '');
$tanggal_bantuan = trim($data['tanggal_bantuan'] ?? '');
$anggotaRaw = $data['anggota'] ?? [];

$validStatus = ['Belum dibantu', 'Sudah dibantu', 'Menunggu verifikasi'];
if (!in_array($status_bantuan, $validStatus, true)) $status_bantuan = 'Belum dibantu';

if ($id <= 0 || $kk_nama === '' || $latitude === null || $longitude === null) {
    echo json_encode(["success" => false, "message" => "Data update penduduk miskin belum lengkap."]);
    exit;
}

try {
    $lat = (float)$latitude;
    $lng = (float)$longitude;
    $nearest = compute_nearest_ibadah($pdo, $lat, $lng);
    $uploadPath = save_upload_if_any();

    $cols = $pdo->query("SHOW COLUMNS FROM pemukiman_miskin_points")->fetchAll(PDO::FETCH_COLUMN, 0);
    $hasNew = in_array('kk_nama', $cols, true);

    $pdo->beginTransaction();

    if ($hasNew) {
        if ($uploadPath === null) {
            $stmt = $pdo->prepare("SELECT bukti_file FROM pemukiman_miskin_points WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $uploadPath = $stmt->fetchColumn() ?: null;
        }

        $stmt = $pdo->prepare("
          UPDATE pemukiman_miskin_points
          SET kk_nama = :kk_nama,
              nik = :nik,
              jumlah_anggota = :jumlah_anggota,
              latitude = :latitude,
              longitude = :longitude,
              address = :address,
              kelurahan = :kelurahan,
              kecamatan = :kecamatan,
              status_bantuan = :status_bantuan,
              jenis_bantuan = :jenis_bantuan,
              tanggal_bantuan = :tanggal_bantuan,
              bukti_file = :bukti_file,
              jarak_meter = :jarak_meter,
              rumah_ibadah_id = :rumah_ibadah_id
          WHERE id = :id
        ");
        $stmt->execute([
            ':kk_nama' => $kk_nama,
            ':nik' => ($nik === '' ? null : $nik),
            ':jumlah_anggota' => $jumlah_anggota,
            ':latitude' => $lat,
            ':longitude' => $lng,
            ':address' => ($address === '' ? null : $address),
            ':kelurahan' => ($kelurahan === '' ? null : $kelurahan),
            ':kecamatan' => ($kecamatan === '' ? null : $kecamatan),
            ':status_bantuan' => $status_bantuan,
            ':jenis_bantuan' => ($jenis_bantuan === '' ? null : $jenis_bantuan),
            ':tanggal_bantuan' => ($tanggal_bantuan === '' ? null : $tanggal_bantuan),
            ':bukti_file' => $uploadPath,
            ':jarak_meter' => $nearest['jarak_meter'],
            ':rumah_ibadah_id' => $nearest['rumah_ibadah_id'],
            ':id' => $id
        ]);

        // anggota keluarga (replace)
        $pdo->prepare("DELETE FROM anggota_keluarga WHERE penduduk_id = :id")->execute([':id' => $id]);
        $anggota = is_string($anggotaRaw) ? json_decode($anggotaRaw, true) : $anggotaRaw;
        if (is_array($anggota) && count($anggota) > 0) {
            $ins = $pdo->prepare("
              INSERT INTO anggota_keluarga (penduduk_id, nama, hubungan, umur, pekerjaan, keterangan)
              VALUES (:penduduk_id, :nama, :hubungan, :umur, :pekerjaan, :keterangan)
            ");
            foreach ($anggota as $a) {
                $n = trim((string)($a['nama'] ?? ''));
                $h = trim((string)($a['hubungan'] ?? ''));
                if ($n === '' || $h === '') continue;
                $ins->execute([
                    ':penduduk_id' => $id,
                    ':nama' => $n,
                    ':hubungan' => $h,
                    ':umur' => isset($a['umur']) && $a['umur'] !== '' ? (int)$a['umur'] : null,
                    ':pekerjaan' => ($a['pekerjaan'] ?? '') === '' ? null : (string)$a['pekerjaan'],
                    ':keterangan' => ($a['keterangan'] ?? '') === '' ? null : (string)$a['keterangan']
                ]);
            }
        }
    } else {
        $stmt = $pdo->prepare("
          UPDATE pemukiman_miskin_points
          SET nama = :nama,
              latitude = :latitude,
              longitude = :longitude,
              address = :address,
              rumah_ibadah_id = :rumah_ibadah_id
          WHERE id = :id
        ");
        $stmt->execute([
            ':nama' => $kk_nama,
            ':latitude' => $lat,
            ':longitude' => $lng,
            ':address' => ($address === '' ? null : $address),
            ':rumah_ibadah_id' => $nearest['rumah_ibadah_id'],
            ':id' => $id
        ]);
    }

    $pdo->commit();
    echo json_encode([
        "success" => true,
        "rumah_ibadah_id" => $nearest['rumah_ibadah_id'],
        "jarak_meter" => $nearest['jarak_meter'],
        "message" => "Penduduk miskin berhasil diupdate"
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
