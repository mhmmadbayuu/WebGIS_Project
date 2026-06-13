<?php
// ============================================================
// API: Penduduk
// GET    ?action=list|detail&nik=...
// POST   action=create
// PUT    action=update&nik=...
// DELETE action=delete&nik=...
// ============================================================

require_once __DIR__ . '/../middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$currentUser = getApiUser($pdo);

// ============================================================
// GET
// ============================================================
if ($method === 'GET') {

    if ($action === 'list') {
        $where  = ['p.status_hidup = "hidup"'];
        $params = [];

        if (!empty($_GET['status_ekonomi'])) {
            $where[] = 'p.status_ekonomi = :se';
            $params[':se'] = $_GET['status_ekonomi'];
        }
        if (!empty($_GET['id_keluarga'])) {
            $where[] = 'p.id_keluarga = :kk';
            $params[':kk'] = (int)$_GET['id_keluarga'];
        }
        if (!empty($_GET['belum_dibantu'])) {
            $where[] = 'NOT EXISTS (SELECT 1 FROM histori_bantuan hb WHERE hb.nik = p.nik AND hb.status_penyaluran="disalurkan")';
        }
        if (!empty($_GET['kondisi_kesehatan'])) {
            $where[] = 'p.kondisi_kesehatan = :kk2';
            $params[':kk2'] = $_GET['kondisi_kesehatan'];
        }
        if (!empty($_GET['search'])) {
            $where[] = '(p.nama_lengkap LIKE :q OR p.nik LIKE :q2)';
            $params[':q']  = '%' . $_GET['search'] . '%';
            $params[':q2'] = '%' . $_GET['search'] . '%';
        }

        $whereStr = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sql = "
            SELECT
                p.nik, p.nama_lengkap, p.jenis_kelamin, p.tanggal_lahir,
                TIMESTAMPDIFF(YEAR, p.tanggal_lahir, CURDATE()) AS umur, p.status_keluarga, p.status_perkawinan,
                p.status_ekonomi, p.kondisi_kesehatan,
                p.pekerjaan, p.penghasilan,
                p.pendidikan_terakhir, p.status_pendidikan,
                p.alamat, p.latitude, p.longitude, p.foto,
                p.id_keluarga,
                k.no_kk, k.nama_kk, k.status_ekonomi AS status_ekonomi_kk,
                ri.nama AS nama_rumah_ibadah, ri.jenis AS jenis_rumah_ibadah,
                (SELECT COUNT(*) FROM histori_bantuan hb WHERE hb.nik = p.nik AND hb.status_penyaluran='disalurkan') AS jumlah_bantuan_diterima,
                (SELECT MAX(hb2.tanggal) FROM histori_bantuan hb2 WHERE hb2.nik = p.nik AND hb2.status_penyaluran='disalurkan') AS terakhir_dibantu
            FROM penduduk p
            LEFT JOIN keluarga k ON k.id = p.id_keluarga
            LEFT JOIN rumah_ibadah ri ON ri.id = k.rumah_ibadah_id
            $whereStr
            ORDER BY
                CASE p.status_ekonomi WHEN 'miskin' THEN 1 WHEN 'rentan' THEN 2 ELSE 3 END ASC,
                CASE p.kondisi_kesehatan WHEN 'sakit_parah' THEN 1 WHEN 'disabilitas' THEN 2 WHEN 'sakit_ringan' THEN 3 ELSE 4 END ASC
            LIMIT 1000
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Hitung prioritas untuk setiap penduduk
        foreach ($rows as &$r) {
            $belumDibantu = (int)$r['jumlah_bantuan_diterima'] === 0;
            $r['prioritas_bantuan'] = hitungPrioritasBantuan($r['status_ekonomi'], $r['kondisi_kesehatan'], $belumDibantu);
        }
        unset($r);

        jsonResponse(true, 'OK', ['data' => $rows, 'total' => count($rows)]);
    }

    if ($action === 'detail') {
        $nik = sanitizeStr($_GET['nik'] ?? '');
        if (!validateNIK($nik)) jsonResponse(false, 'NIK tidak valid.', [], 400);

        $stmt = $pdo->prepare("
            SELECT p.*, TIMESTAMPDIFF(YEAR, p.tanggal_lahir, CURDATE()) AS umur, k.no_kk, k.nama_kk, ri.nama AS nama_ri
            FROM penduduk p
            LEFT JOIN keluarga k ON k.id = p.id_keluarga
            LEFT JOIN rumah_ibadah ri ON ri.id = k.rumah_ibadah_id
            WHERE p.nik = :nik
        ");
        $stmt->execute([':nik' => $nik]);
        $penduduk = $stmt->fetch();
        if (!$penduduk) jsonResponse(false, 'Data tidak ditemukan.', [], 404);

        // Histori bantuan
        $stmtHB = $pdo->prepare("
            SELECT hb.*, jb.nama AS nama_bantuan, jb.kategori, ri.nama AS nama_ri
            FROM histori_bantuan hb
            JOIN jenis_bantuan jb ON jb.id = hb.id_jenis_bantuan
            LEFT JOIN rumah_ibadah ri ON ri.id = hb.rumah_ibadah_id
            WHERE hb.nik = :nik
            ORDER BY hb.tanggal DESC
        ");
        $stmtHB->execute([':nik' => $nik]);
        $histori = $stmtHB->fetchAll();

        // Pelatihan
        $stmtPl = $pdo->prepare("SELECT * FROM riwayat_pelatihan WHERE nik = :nik ORDER BY tanggal_mulai DESC");
        $stmtPl->execute([':nik' => $nik]);
        $pelatihan = $stmtPl->fetchAll();

        // Validasi pendidikan
        $validasi = validasiPendidikanUmur(
            (int)$penduduk['umur'],
            $penduduk['pendidikan_terakhir'],
            $penduduk['status_pendidikan']
        );

        $belumDibantu = empty($histori);
        $penduduk['prioritas_bantuan'] = hitungPrioritasBantuan($penduduk['status_ekonomi'], $penduduk['kondisi_kesehatan'], $belumDibantu);

        jsonResponse(true, 'OK', [
            'data'      => $penduduk,
            'histori'   => $histori,
            'pelatihan' => $pelatihan,
            'validasi'  => $validasi,
        ]);
    }

    if ($action === 'geojson') {
        // Untuk layer Leaflet
        $stmt = $pdo->query("
            SELECT p.nik, p.nama_lengkap, p.status_ekonomi, p.kondisi_kesehatan,
                   p.latitude, p.longitude,
                   k.nama_kk,
                   (SELECT COUNT(*) FROM histori_bantuan hb WHERE hb.nik=p.nik AND hb.status_penyaluran='disalurkan') AS jml_bantuan
            FROM penduduk p
            LEFT JOIN keluarga k ON k.id = p.id_keluarga
            WHERE p.latitude IS NOT NULL AND p.longitude IS NOT NULL
              AND p.status_hidup = 'hidup'
        ");
        $rows = $stmt->fetchAll();

        $features = [];
        foreach ($rows as $r) {
            $color = match($r['status_ekonomi']) {
                'miskin' => '#ef4444',
                'rentan' => '#f59e0b',
                default  => '#22c55e'
            };
            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float)$r['longitude'], (float)$r['latitude']]
                ],
                'properties' => array_merge($r, ['color' => $color])
            ];
        }

        header('Content-Type: application/json');
        echo json_encode(['type' => 'FeatureCollection', 'features' => $features], JSON_UNESCAPED_UNICODE);
        exit;
    }

    jsonResponse(false, 'Action tidak dikenal.', [], 400);
}

// ============================================================
// POST - Create
// ============================================================
if ($method === 'POST') {
    if (!canEdit($currentUser)) jsonResponse(false, 'Akses ditolak.', [], 403);

    $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $nik   = sanitizeStr($body['nik'] ?? '');
    $nama  = sanitizeStr($body['nama_lengkap'] ?? '');
    $jk    = sanitizeStr($body['jenis_kelamin'] ?? '');
    $tgl   = sanitizeStr($body['tanggal_lahir'] ?? '');
    $idKk  = !empty($body['id_keluarga']) ? (int)$body['id_keluarga'] : null;

    if (!validateNIK($nik))         jsonResponse(false, 'Format NIK harus 16 digit angka.', [], 400);
    if (strlen($nama) < 3)          jsonResponse(false, 'Nama terlalu pendek.', [], 400);
    if (!in_array($jk, ['L','P']))  jsonResponse(false, 'Jenis kelamin tidak valid.', [], 400);
    if (!strtotime($tgl))           jsonResponse(false, 'Format tanggal lahir tidak valid.', [], 400);

    // Cek duplikat NIK
    $cek = $pdo->prepare("SELECT nik FROM penduduk WHERE nik = :nik");
    $cek->execute([':nik' => $nik]);
    if ($cek->fetch()) jsonResponse(false, 'NIK sudah terdaftar.', [], 409);

    // Hitung umur untuk validasi pendidikan
    $umur = (int)(new DateTime())->diff(new DateTime($tgl))->y;
    $statusPendidikan = sanitizeStr($body['status_pendidikan'] ?? 'tidak_sekolah');
    $pendidikan       = sanitizeStr($body['pendidikan_terakhir'] ?? 'tidak_sekolah');
    $validasi = validasiPendidikanUmur($umur, $pendidikan, $statusPendidikan);

    $lat = !empty($body['latitude']) ? (float)$body['latitude'] : null;
    $lng = !empty($body['longitude']) ? (float)$body['longitude'] : null;
    if ($lat !== null && !validateCoord((string)$lat, 'lat')) jsonResponse(false, 'Latitude tidak valid.', [], 400);
    if ($lng !== null && !validateCoord((string)$lng, 'lng')) jsonResponse(false, 'Longitude tidak valid.', [], 400);

    $stmt = $pdo->prepare("
        INSERT INTO penduduk
            (nik, id_keluarga, nama_lengkap, jenis_kelamin, tanggal_lahir,
             status_keluarga, status_perkawinan, status_hidup,
             pendidikan_terakhir, status_pendidikan,
             pekerjaan, penghasilan, status_ekonomi,
             kondisi_kesehatan, catatan_kesehatan,
             no_telp, alamat, latitude, longitude)
        VALUES
            (:nik,:kk,:nama,:jk,:tgl,
             :sk,:sp,:sh,
             :ptk,:spd,
             :pkr,:pgn,:se,
             :kkh,:ckkh,
             :tlp,:almt,:lat,:lng)
    ");
    $stmt->execute([
        ':nik'  => $nik,
        ':kk'   => $idKk,
        ':nama' => $nama,
        ':jk'   => $jk,
        ':tgl'  => $tgl,
        ':sk'   => sanitizeStr($body['status_keluarga'] ?? 'anggota_lain'),
        ':sp'   => sanitizeStr($body['status_perkawinan'] ?? 'belum_kawin'),
        ':sh'   => sanitizeStr($body['status_hidup'] ?? 'hidup'),
        ':ptk'  => $pendidikan,
        ':spd'  => $statusPendidikan,
        ':pkr'  => sanitizeStr($body['pekerjaan'] ?? '') ?: null,
        ':pgn'  => !empty($body['penghasilan']) ? (float)$body['penghasilan'] : 0,
        ':se'   => sanitizeStr($body['status_ekonomi'] ?? 'rentan'),
        ':kkh'  => sanitizeStr($body['kondisi_kesehatan'] ?? 'sehat'),
        ':ckkh' => sanitizeStr($body['catatan_kesehatan'] ?? '') ?: null,
        ':tlp'  => sanitizeStr($body['no_telp'] ?? '') ?: null,
        ':almt' => sanitizeStr($body['alamat'] ?? '') ?: null,
        ':lat'  => $lat,
        ':lng'  => $lng,
    ]);

    logAktivitas($pdo, $currentUser['id'] ?? null, 'CREATE', 'penduduk', $nik, null, $body);

    jsonResponse(true, 'Penduduk berhasil ditambahkan.', [
        'nik'     => $nik,
        'validasi' => $validasi
    ], 201);
}

// ============================================================
// PUT - Update
// ============================================================
if ($method === 'PUT') {
    if (!canEdit($currentUser)) jsonResponse(false, 'Akses ditolak.', [], 403);

    $nik  = sanitizeStr($_GET['nik'] ?? '');
    if (!validateNIK($nik)) jsonResponse(false, 'NIK tidak valid.', [], 400);

    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    // Ambil data lama
    $old = $pdo->prepare("SELECT * FROM penduduk WHERE nik = :nik");
    $old->execute([':nik' => $nik]);
    $dataLama = $old->fetch();
    if (!$dataLama) jsonResponse(false, 'Data tidak ditemukan.', [], 404);

    $sets  = [];
    $params = [':nik' => $nik];

    $fieldMap = [
        'id_keluarga' => ':kk', 'nama_lengkap' => ':nama', 'jenis_kelamin' => ':jk',
        'tanggal_lahir' => ':tgl', 'status_keluarga' => ':sk', 'status_perkawinan' => ':sp',
        'status_hidup' => ':sh', 'pendidikan_terakhir' => ':ptk', 'status_pendidikan' => ':spd',
        'pekerjaan' => ':pkr', 'penghasilan' => ':pgn', 'status_ekonomi' => ':se',
        'kondisi_kesehatan' => ':kkh', 'catatan_kesehatan' => ':ckkh',
        'no_telp' => ':tlp', 'alamat' => ':almt', 'latitude' => ':lat', 'longitude' => ':lng',
    ];

    foreach ($fieldMap as $field => $param) {
        if (array_key_exists($field, $body)) {
            $sets[] = "$field = $param";
            $params[$param] = $body[$field] === '' ? null : $body[$field];
        }
    }

    if (empty($sets)) jsonResponse(false, 'Tidak ada data yang diubah.', [], 400);

    $sql = "UPDATE penduduk SET " . implode(', ', $sets) . " WHERE nik = :nik";
    $pdo->prepare($sql)->execute($params);

    logAktivitas($pdo, $currentUser['id'] ?? null, 'UPDATE', 'penduduk', $nik, $dataLama, $body);

    jsonResponse(true, 'Data penduduk berhasil diperbarui.', ['nik' => $nik]);
}

// ============================================================
// DELETE
// ============================================================
if ($method === 'DELETE') {
    if (!isAdmin($currentUser)) jsonResponse(false, 'Hanya admin yang bisa menghapus data.', [], 403);

    $nik = sanitizeStr($_GET['nik'] ?? '');
    if (!validateNIK($nik)) jsonResponse(false, 'NIK tidak valid.', [], 400);

    $old = $pdo->prepare("SELECT * FROM penduduk WHERE nik = :nik");
    $old->execute([':nik' => $nik]);
    $dataLama = $old->fetch();
    if (!$dataLama) jsonResponse(false, 'Data tidak ditemukan.', [], 404);

    $pdo->prepare("DELETE FROM penduduk WHERE nik = :nik")->execute([':nik' => $nik]);
    logAktivitas($pdo, $currentUser['id'] ?? null, 'DELETE', 'penduduk', $nik, $dataLama, null);

    jsonResponse(true, 'Data penduduk dihapus.');
}

jsonResponse(false, 'Method tidak didukung.', [], 405);
