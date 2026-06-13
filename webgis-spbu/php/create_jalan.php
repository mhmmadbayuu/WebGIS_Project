<?php
header('Content-Type: application/json');
require_once 'koneksi.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(["success"=>false,"message"=>"Data kosong"]);
    exit;
}

try {
    $geojson = json_encode($data['geometry'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO jalan_lines 
            (nama_jalan, status_jalan, panjang_meter, geojson)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['nama_jalan'],
            $data['status_jalan'],
            $data['panjang_meter'],
            $geojson
        ]);
    } catch (Exception $inner) {
        // Fallback untuk skema spatial: simpan ke kolom `geom` jika `geojson` tidak ada.
        $stmt = $pdo->prepare("
            INSERT INTO jalan_lines
            (nama_jalan, status_jalan, panjang_meter, geom)
            VALUES (?, ?, ?, ST_GeomFromGeoJSON(?))
        ");

        $stmt->execute([
            $data['nama_jalan'],
            $data['status_jalan'],
            $data['panjang_meter'],
            $geojson
        ]);
    }

    echo json_encode(["success"=>true]);

} catch (Exception $e) {
    echo json_encode(["success"=>false,"message"=>$e->getMessage()]);
}
?>
