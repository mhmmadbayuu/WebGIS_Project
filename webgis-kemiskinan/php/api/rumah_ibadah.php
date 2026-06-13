<?php
// ============================================================
// API: Rumah Ibadah
// ============================================================
require_once __DIR__ . '/../middleware/auth.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$method      = $_SERVER['REQUEST_METHOD'];
$action      = $_GET['action'] ?? '';
$currentUser = getApiUser($pdo);

if ($method === 'GET') {
    if ($action === 'list') {
        $rows = $pdo->query("
            SELECT ri.*,
                   COUNT(k.id) AS jumlah_kk,
                   SUM(CASE WHEN k.status_ekonomi='miskin' THEN 1 ELSE 0 END) AS kk_miskin
            FROM rumah_ibadah ri
            LEFT JOIN keluarga k ON k.rumah_ibadah_id = ri.id
            GROUP BY ri.id
            ORDER BY ri.nama
        ")->fetchAll();
        jsonResponse(true, 'OK', ['data' => $rows]);
    }
    if ($action === 'geojson') {
        $rows = $pdo->query("SELECT * FROM rumah_ibadah")->fetchAll();
        $features = [];
        foreach ($rows as $r) {
            $features[] = [
                'type' => 'Feature',
                'geometry' => ['type'=>'Point','coordinates'=>[(float)$r['longitude'],(float)$r['latitude']]],
                'properties' => $r
            ];
        }
        header('Content-Type: application/json');
        echo json_encode(['type'=>'FeatureCollection','features'=>$features], JSON_UNESCAPED_UNICODE);
        exit;
    }
    jsonResponse(false, 'Action tidak dikenal.', [], 400);
}

if ($method === 'POST') {
    if (!canEdit($currentUser)) jsonResponse(false, 'Akses ditolak.', [], 403);
    $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $nama = sanitizeStr($body['nama'] ?? '');
    $lat  = (float)($body['latitude'] ?? 0);
    $lng  = (float)($body['longitude'] ?? 0);
    if (strlen($nama) < 3) jsonResponse(false, 'Nama terlalu pendek.', [], 400);
    if (!$lat || !$lng)    jsonResponse(false, 'Koordinat wajib diisi.', [], 400);

    $stmt = $pdo->prepare("
        INSERT INTO rumah_ibadah (nama, jenis, kontak, radius_meter, latitude, longitude, alamat, kelurahan, kecamatan)
        VALUES (:nama,:jenis,:kontak,:radius,:lat,:lng,:alamat,:kel,:kec)
    ");
    $stmt->execute([
        ':nama'   => $nama,
        ':jenis'  => sanitizeStr($body['jenis'] ?? 'Lainnya'),
        ':kontak' => sanitizeStr($body['kontak'] ?? '') ?: null,
        ':radius' => (float)($body['radius_meter'] ?? 300),
        ':lat'    => $lat, ':lng' => $lng,
        ':alamat' => sanitizeStr($body['alamat'] ?? '') ?: null,
        ':kel'    => sanitizeStr($body['kelurahan'] ?? '') ?: null,
        ':kec'    => sanitizeStr($body['kecamatan'] ?? '') ?: null,
    ]);
    $newId = (int)$pdo->lastInsertId();
    logAktivitas($pdo, $currentUser['id']??null, 'CREATE', 'rumah_ibadah', (string)$newId, null, $body);
    jsonResponse(true, 'Rumah ibadah ditambahkan.', ['id' => $newId], 201);
}

if ($method === 'PUT') {
    if (!canEdit($currentUser)) jsonResponse(false, 'Akses ditolak.', [], 403);
    $id   = (int)($_GET['id'] ?? 0);
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    if ($action === 'update_koordinat') {
        $lat = (float)($body['latitude'] ?? 0);
        $lng = (float)($body['longitude'] ?? 0);
        if (!$lat || !$lng) jsonResponse(false, 'Koordinat tidak valid.', [], 400);
        
        $pdo->prepare("UPDATE rumah_ibadah SET latitude=:lat, longitude=:lng WHERE id=:id")
            ->execute([':lat'=>$lat, ':lng'=>$lng, ':id'=>$id]);
        logAktivitas($pdo, $currentUser['id']??null, 'UPDATE', 'rumah_ibadah', (string)$id, null, ['lat'=>$lat,'lng'=>$lng]);
        jsonResponse(true, 'Koordinat diperbarui.', ['id' => $id]);
    }

    $pdo->prepare("
        UPDATE rumah_ibadah SET nama=:nama,jenis=:jenis,kontak=:kontak,radius_meter=:radius,
        latitude=:lat,longitude=:lng,alamat=:alamat,kelurahan=:kel,kecamatan=:kec WHERE id=:id
    ")->execute([
        ':nama'=>sanitizeStr($body['nama']??''), ':jenis'=>sanitizeStr($body['jenis']??'Lainnya'),
        ':kontak'=>sanitizeStr($body['kontak']??'')?: null, ':radius'=>(float)($body['radius_meter']??300),
        ':lat'=>(float)($body['latitude']??0), ':lng'=>(float)($body['longitude']??0),
        ':alamat'=>sanitizeStr($body['alamat']??'')?: null, ':kel'=>sanitizeStr($body['kelurahan']??'')?: null,
        ':kec'=>sanitizeStr($body['kecamatan']??'')?: null, ':id'=>$id,
    ]);
    logAktivitas($pdo, $currentUser['id']??null, 'UPDATE', 'rumah_ibadah', (string)$id);
    jsonResponse(true, 'Rumah ibadah diperbarui.', ['id' => $id]);
}

if ($method === 'DELETE') {
    if (!isAdmin($currentUser)) jsonResponse(false, 'Hanya admin.', [], 403);
    $id = (int)($_GET['id'] ?? 0);
    $pdo->prepare("DELETE FROM rumah_ibadah WHERE id=:id")->execute([':id'=>$id]);
    logAktivitas($pdo, $currentUser['id']??null, 'DELETE', 'rumah_ibadah', (string)$id);
    jsonResponse(true, 'Dihapus.');
}

jsonResponse(false, 'Method tidak didukung.', [], 405);
