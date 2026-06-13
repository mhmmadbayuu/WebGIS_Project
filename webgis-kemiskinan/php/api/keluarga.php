<?php
// ============================================================
// API: Keluarga
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
        $where  = [];
        $params = [];
        if (!empty($_GET['status_ekonomi'])) {
            $where[] = 'k.status_ekonomi = :se';
            $params[':se'] = $_GET['status_ekonomi'];
        }
        $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $rows = $pdo->prepare("
            SELECT k.*,
                   ri.nama AS nama_ri, ri.jenis AS jenis_ri,
                   COUNT(p.nik) AS jumlah_anggota_db,
                   SUM(CASE WHEN p.kondisi_kesehatan IN ('sakit_parah','disabilitas') THEN 1 ELSE 0 END) AS anggota_sakit
            FROM keluarga k
            LEFT JOIN rumah_ibadah ri ON ri.id = k.rumah_ibadah_id
            LEFT JOIN penduduk p ON p.id_keluarga = k.id AND p.status_hidup='hidup'
            $whereStr
            GROUP BY k.id
            ORDER BY CASE k.status_ekonomi WHEN 'miskin' THEN 1 WHEN 'rentan' THEN 2 ELSE 3 END ASC
        ");
        $rows->execute($params);
        jsonResponse(true, 'OK', ['data' => $rows->fetchAll()]);
    }

    if ($action === 'detail') {
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT k.*, ri.nama AS nama_ri FROM keluarga k LEFT JOIN rumah_ibadah ri ON ri.id=k.rumah_ibadah_id WHERE k.id=:id");
        $stmt->execute([':id' => $id]);
        $kk = $stmt->fetch();
        if (!$kk) jsonResponse(false, 'Keluarga tidak ditemukan.', [], 404);

        $anggota = $pdo->prepare("SELECT * FROM penduduk WHERE id_keluarga=:id ORDER BY CASE status_keluarga WHEN 'kepala_keluarga' THEN 1 ELSE 2 END, tanggal_lahir");
        $anggota->execute([':id' => $id]);

        $histori = $pdo->prepare("
            SELECT hb.tanggal, jb.nama AS bantuan, hb.jumlah, hb.status_penyaluran, p.nama_lengkap
            FROM histori_bantuan hb
            JOIN penduduk p ON p.nik=hb.nik
            JOIN jenis_bantuan jb ON jb.id=hb.id_jenis_bantuan
            WHERE hb.id_keluarga=:id
            ORDER BY hb.tanggal DESC
            LIMIT 50
        ");
        $histori->execute([':id' => $id]);

        jsonResponse(true, 'OK', ['data' => $kk, 'anggota' => $anggota->fetchAll(), 'histori' => $histori->fetchAll()]);
    }

    if ($action === 'geojson') {
        $stmt = $pdo->query("
            SELECT k.id, k.no_kk, k.nama_kk, k.status_ekonomi, k.latitude, k.longitude,
                   COUNT(p.nik) AS jml_anggota, ri.nama AS nama_ri
            FROM keluarga k
            LEFT JOIN penduduk p ON p.id_keluarga=k.id AND p.status_hidup='hidup'
            LEFT JOIN rumah_ibadah ri ON ri.id=k.rumah_ibadah_id
            WHERE k.latitude IS NOT NULL AND k.longitude IS NOT NULL
            GROUP BY k.id
        ");
        $rows = $stmt->fetchAll();
        $features = [];
        foreach ($rows as $r) {
            $features[] = [
                'type' => 'Feature',
                'geometry' => ['type'=>'Point','coordinates'=>[(float)$r['longitude'],(float)$r['latitude']]],
                'properties' => array_merge($r, [
                    'color' => match($r['status_ekonomi']){'miskin'=>'#ef4444','rentan'=>'#f59e0b',default=>'#22c55e'},
                    'layer' => 'keluarga'
                ])
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

    $noKk  = sanitizeStr($body['no_kk'] ?? '');
    $namaKk = sanitizeStr($body['nama_kk'] ?? '');
    if (strlen($noKk) !== 16)  jsonResponse(false, 'No KK harus 16 digit.', [], 400);
    if (strlen($namaKk) < 3)   jsonResponse(false, 'Nama KK terlalu pendek.', [], 400);

    $cek = $pdo->prepare("SELECT id FROM keluarga WHERE no_kk=:nkk");
    $cek->execute([':nkk' => $noKk]);
    if ($cek->fetch()) jsonResponse(false, 'No KK sudah terdaftar.', [], 409);

    $stmt = $pdo->prepare("
        INSERT INTO keluarga (no_kk, nama_kk, alamat, kelurahan, kecamatan, kota, latitude, longitude, status_ekonomi, rumah_ibadah_id)
        VALUES (:nkk,:nkk_nama,:almt,:kel,:kec,:kota,:lat,:lng,:se,:ri)
    ");
    $stmt->execute([
        ':nkk'      => $noKk,
        ':nkk_nama' => $namaKk,
        ':almt'     => sanitizeStr($body['alamat'] ?? '') ?: null,
        ':kel'      => sanitizeStr($body['kelurahan'] ?? '') ?: null,
        ':kec'      => sanitizeStr($body['kecamatan'] ?? '') ?: null,
        ':kota'     => sanitizeStr($body['kota'] ?? '') ?: null,
        ':lat'      => !empty($body['latitude']) ? (float)$body['latitude'] : null,
        ':lng'      => !empty($body['longitude']) ? (float)$body['longitude'] : null,
        ':se'       => sanitizeStr($body['status_ekonomi'] ?? 'rentan'),
        ':ri'       => !empty($body['rumah_ibadah_id']) ? (int)$body['rumah_ibadah_id'] : null,
    ]);
    $newId = (int)$pdo->lastInsertId();
    logAktivitas($pdo, $currentUser['id']??null, 'CREATE', 'keluarga', (string)$newId, null, $body);
    jsonResponse(true, 'Keluarga berhasil ditambahkan.', ['id' => $newId], 201);
}

if ($method === 'PUT') {
    if (!canEdit($currentUser)) jsonResponse(false, 'Akses ditolak.', [], 403);
    $id   = (int)($_GET['id'] ?? 0);
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    if ($id <= 0) jsonResponse(false, 'ID tidak valid.', [], 400);

    if ($action === 'update_koordinat') {
        $lat = (float)($body['latitude'] ?? 0);
        $lng = (float)($body['longitude'] ?? 0);
        if (!$lat || !$lng) jsonResponse(false, 'Koordinat tidak valid.', [], 400);
        
        $pdo->prepare("UPDATE keluarga SET latitude=:lat, longitude=:lng WHERE id=:id")
            ->execute([':lat'=>$lat, ':lng'=>$lng, ':id'=>$id]);
        logAktivitas($pdo, $currentUser['id']??null, 'UPDATE', 'keluarga', (string)$id, null, ['lat'=>$lat,'lng'=>$lng]);
        jsonResponse(true, 'Koordinat diperbarui.', ['id' => $id]);
    }

    $pdo->prepare("
        UPDATE keluarga SET
            nama_kk=:nkk, alamat=:almt, kelurahan=:kel, kecamatan=:kec,
            kota=:kota, latitude=:lat, longitude=:lng,
            status_ekonomi=:se, rumah_ibadah_id=:ri
        WHERE id=:id
    ")->execute([
        ':nkk'  => sanitizeStr($body['nama_kk'] ?? ''),
        ':almt' => sanitizeStr($body['alamat'] ?? '') ?: null,
        ':kel'  => sanitizeStr($body['kelurahan'] ?? '') ?: null,
        ':kec'  => sanitizeStr($body['kecamatan'] ?? '') ?: null,
        ':kota' => sanitizeStr($body['kota'] ?? '') ?: null,
        ':lat'  => !empty($body['latitude']) ? (float)$body['latitude'] : null,
        ':lng'  => !empty($body['longitude']) ? (float)$body['longitude'] : null,
        ':se'   => sanitizeStr($body['status_ekonomi'] ?? 'rentan'),
        ':ri'   => !empty($body['rumah_ibadah_id']) ? (int)$body['rumah_ibadah_id'] : null,
        ':id'   => $id,
    ]);
    logAktivitas($pdo, $currentUser['id']??null, 'UPDATE', 'keluarga', (string)$id);
    jsonResponse(true, 'Keluarga diperbarui.', ['id' => $id]);
}

if ($method === 'DELETE') {
    if (!isAdmin($currentUser)) jsonResponse(false, 'Hanya admin yang bisa menghapus keluarga.', [], 403);
    $id = (int)($_GET['id'] ?? 0);
    // Cek apakah masih ada anggota
    $jml = (int)$pdo->prepare("SELECT COUNT(*) FROM penduduk WHERE id_keluarga=:id")->execute([':id'=>$id]);
    if ($jml > 0) jsonResponse(false, 'Tidak bisa dihapus: masih ada anggota keluarga.', [], 409);
    $pdo->prepare("DELETE FROM keluarga WHERE id=:id")->execute([':id' => $id]);
    logAktivitas($pdo, $currentUser['id']??null, 'DELETE', 'keluarga', (string)$id);
    jsonResponse(true, 'Keluarga dihapus.');
}

jsonResponse(false, 'Method tidak didukung.', [], 405);
