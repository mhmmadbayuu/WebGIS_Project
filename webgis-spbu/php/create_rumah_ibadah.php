<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'koneksi.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(["success" => false, "message" => "Payload JSON kosong atau tidak valid."]);
    exit;
}

$nama = trim($data['nama'] ?? '');
$jenis = trim($data['jenis'] ?? 'Lainnya');
$kontak = trim($data['kontak'] ?? '');
$radius_meter = (float)($data['radius_meter'] ?? 0);
$latitude = $data['latitude'] ?? null;
$longitude = $data['longitude'] ?? null;
$address = trim($data['address'] ?? '');

if ($nama === '' || $radius_meter <= 0 || $latitude === null || $longitude === null) {
    echo json_encode(["success" => false, "message" => "Data rumah ibadah belum lengkap (nama/radius/lat/lng)."]);
    exit;
}

try {
    $cols = $pdo->query("SHOW COLUMNS FROM rumah_ibadah_points")->fetchAll(PDO::FETCH_COLUMN, 0);
    $hasJenis = in_array('jenis', $cols, true);
    $hasKontak = in_array('kontak', $cols, true);

    if ($hasJenis && $hasKontak) {
        $stmt = $pdo->prepare("
            INSERT INTO rumah_ibadah_points (nama, jenis, kontak, radius_meter, latitude, longitude, address)
            VALUES (:nama, :jenis, :kontak, :radius_meter, :latitude, :longitude, :address)
        ");
        $stmt->execute([
            ':nama' => $nama,
            ':jenis' => ($jenis === '' ? 'Lainnya' : $jenis),
            ':kontak' => ($kontak === '' ? null : $kontak),
            ':radius_meter' => $radius_meter,
            ':latitude' => $latitude,
            ':longitude' => $longitude,
            ':address' => ($address === '' ? null : $address)
        ]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO rumah_ibadah_points (nama, radius_meter, latitude, longitude, address)
            VALUES (:nama, :radius_meter, :latitude, :longitude, :address)
        ");
        $stmt->execute([
            ':nama' => $nama,
            ':radius_meter' => $radius_meter,
            ':latitude' => $latitude,
            ':longitude' => $longitude,
            ':address' => ($address === '' ? null : $address)
        ]);
    }

    echo json_encode(["success" => true, "id" => (int)$pdo->lastInsertId(), "message" => "Rumah ibadah berhasil ditambahkan"]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
