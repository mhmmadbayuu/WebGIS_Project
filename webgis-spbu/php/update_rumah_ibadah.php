<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'koneksi.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(["success" => false, "message" => "Payload JSON kosong atau tidak valid."]);
    exit;
}

$id = (int)($data['id'] ?? 0);
$nama = trim($data['nama'] ?? '');
$jenis = trim($data['jenis'] ?? 'Lainnya');
$kontak = trim($data['kontak'] ?? '');
$radius_meter = (float)($data['radius_meter'] ?? 0);
$latitude = $data['latitude'] ?? null;
$longitude = $data['longitude'] ?? null;
$address = trim($data['address'] ?? '');

if ($id <= 0 || $nama === '' || $radius_meter <= 0 || $latitude === null || $longitude === null) {
    echo json_encode(["success" => false, "message" => "Data update rumah ibadah belum lengkap."]);
    exit;
}

try {
    $cols = $pdo->query("SHOW COLUMNS FROM rumah_ibadah_points")->fetchAll(PDO::FETCH_COLUMN, 0);
    $hasJenis = in_array('jenis', $cols, true);
    $hasKontak = in_array('kontak', $cols, true);

    if ($hasJenis && $hasKontak) {
        $stmt = $pdo->prepare("
            UPDATE rumah_ibadah_points
            SET nama = :nama,
                jenis = :jenis,
                kontak = :kontak,
                radius_meter = :radius_meter,
                latitude = :latitude,
                longitude = :longitude,
                address = :address
            WHERE id = :id
        ");
        $stmt->execute([
            ':nama' => $nama,
            ':jenis' => ($jenis === '' ? 'Lainnya' : $jenis),
            ':kontak' => ($kontak === '' ? null : $kontak),
            ':radius_meter' => $radius_meter,
            ':latitude' => $latitude,
            ':longitude' => $longitude,
            ':address' => ($address === '' ? null : $address),
            ':id' => $id
        ]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE rumah_ibadah_points
            SET nama = :nama,
                radius_meter = :radius_meter,
                latitude = :latitude,
                longitude = :longitude,
                address = :address
            WHERE id = :id
        ");
        $stmt->execute([
            ':nama' => $nama,
            ':radius_meter' => $radius_meter,
            ':latitude' => $latitude,
            ':longitude' => $longitude,
            ':address' => ($address === '' ? null : $address),
            ':id' => $id
        ]);
    }

    echo json_encode(["success" => true, "message" => "Rumah ibadah berhasil diupdate"]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
