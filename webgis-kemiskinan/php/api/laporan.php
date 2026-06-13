<?php
// ============================================================
// API: Laporan Masyarakat
// GET    ?action=list|detail&id=...
// POST   action=create  (multipart untuk foto)
// PUT    action=update_status&id=...
// ============================================================

require_once __DIR__ . '/../middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$method      = $_SERVER['REQUEST_METHOD'];
$action      = $_GET['action'] ?? '';
$currentUser = getApiUser($pdo);

// ============================================================
// GET: list laporan
// ============================================================
if ($method === 'GET') {

    if ($action === 'list') {
        $where  = [];
        $params = [];

        if (!empty($_GET['status'])) {
            $where[] = 'l.status = :status';
            $params[':status'] = $_GET['status'];
        }
        if (!empty($_GET['urgensi'])) {
            $where[] = 'l.tingkat_urgensi = :urgensi';
            $params[':urgensi'] = $_GET['urgensi'];
        }

        $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $rows = $pdo->prepare("
            SELECT l.*,
                   p.nama_lengkap AS nama_terdampak,
                   u.nama AS nama_verifikator
            FROM laporan l
            LEFT JOIN penduduk p ON p.nik = l.nik_terdampak
            LEFT JOIN users u ON u.id = l.diverifikasi_oleh
            $whereStr
            ORDER BY
                CASE l.tingkat_urgensi WHEN 'darurat' THEN 1 WHEN 'tinggi' THEN 2 WHEN 'sedang' THEN 3 ELSE 4 END ASC,
                CASE l.status WHEN 'pending' THEN 1 WHEN 'diverifikasi' THEN 2 ELSE 3 END ASC,
                l.created_at DESC
            LIMIT 500
        ");
        $rows->execute($params);
        jsonResponse(true, 'OK', ['data' => $rows->fetchAll(), 'total' => $rows->rowCount()]);
    }

    if ($action === 'detail') {
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("
            SELECT l.*, p.nama_lengkap AS nama_terdampak, p.status_ekonomi,
                   p.kondisi_kesehatan, p.alamat AS alamat_penduduk
            FROM laporan l
            LEFT JOIN penduduk p ON p.nik = l.nik_terdampak
            WHERE l.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $data = $stmt->fetch();
        if (!$data) jsonResponse(false, 'Laporan tidak ditemukan.', [], 404);
        jsonResponse(true, 'OK', ['data' => $data]);
    }

    if ($action === 'geojson') {
        // Untuk layer marker laporan di peta
        $stmt = $pdo->query("
            SELECT id, nama_pelapor, pelapor_id, kategori, tingkat_urgensi, status,
                   latitude, longitude, alamat, created_at
            FROM laporan
            WHERE latitude IS NOT NULL AND longitude IS NOT NULL
              AND status NOT IN ('selesai','ditolak')
        ");
        $rows = $stmt->fetchAll();

        $features = [];
        foreach ($rows as $r) {
            $color = match($r['tingkat_urgensi']) {
                'darurat' => '#dc2626',
                'tinggi'  => '#ea580c',
                'sedang'  => '#ca8a04',
                default   => '#2563eb',
            };
            $features[] = [
                'type' => 'Feature',
                'geometry' => ['type' => 'Point', 'coordinates' => [(float)$r['longitude'], (float)$r['latitude']]],
                'properties' => array_merge($r, ['color' => $color, 'layer' => 'laporan'])
            ];
        }
        header('Content-Type: application/json');
        echo json_encode(['type' => 'FeatureCollection', 'features' => $features], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'stats') {
        $stats = $pdo->query("
            SELECT
                SUM(status='pending')       AS pending,
                SUM(status='diverifikasi')  AS diverifikasi,
                SUM(status='diproses')      AS diproses,
                SUM(status='selesai')       AS selesai,
                SUM(tingkat_urgensi='darurat') AS darurat,
                COUNT(*) AS total
            FROM laporan
        ")->fetch();
        jsonResponse(true, 'OK', ['data' => $stats]);
    }

    jsonResponse(false, 'Action tidak dikenal.', [], 400);
}

// ============================================================
// POST: buat laporan baru (bisa dilakukan masyarakat umum)
// ============================================================
if ($method === 'POST') {
    $deskripsi = sanitizeStr($_POST['deskripsi'] ?? '', 2000);
    $kategori  = sanitizeStr($_POST['kategori'] ?? 'butuh_bantuan_lain');
    $urgensi   = sanitizeStr($_POST['tingkat_urgensi'] ?? 'sedang');
    $lat       = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
    $lng       = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;

    if (strlen($deskripsi) < 10) jsonResponse(false, 'Deskripsi laporan terlalu singkat.', [], 400);

    $fotoNama = null;
    if (!empty($_FILES['foto']['name'])) {
        try {
            $fotoNama = uploadFile($_FILES['foto'], UPLOAD_LAPORAN, ALLOWED_IMG_EXT);
        } catch (RuntimeException $e) {
            jsonResponse(false, $e->getMessage(), [], 400);
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO laporan
            (nama_pelapor, pelapor_id, nik_terdampak, deskripsi, kategori, tingkat_urgensi,
             latitude, longitude, alamat, foto, status)
        VALUES
            (:np, :pid, :nik, :dsk, :kat, :urg, :lat, :lng, :almt, :foto, 'pending')
    ");
    $stmt->execute([
        ':np'   => sanitizeStr($_POST['nama_pelapor'] ?? '') ?: null,
        ':pid'  => $currentUser['id'] ?? null,
        ':nik'  => sanitizeStr($_POST['nik_terdampak'] ?? '') ?: null,
        ':dsk'  => $deskripsi,
        ':kat'  => $kategori,
        ':urg'  => $urgensi,
        ':lat'  => $lat,
        ':lng'  => $lng,
        ':almt' => sanitizeStr($_POST['alamat'] ?? '') ?: null,
        ':foto' => $fotoNama,
    ]);

    $laporanId = (int)$pdo->lastInsertId();

    // Kirim notifikasi ke pengurus jika darurat
    if (in_array($urgensi, ['darurat', 'tinggi'])) {
        $pengurus = $pdo->query("SELECT id FROM users WHERE role_id = 2")->fetchAll();
        foreach ($pengurus as $p) {
            $pdo->prepare("
                INSERT INTO notifikasi (user_id, judul, isi, tipe, ref_id)
                VALUES (:uid, :judul, :isi, 'laporan', :ref)
            ")->execute([
                ':uid'   => $p['id'],
                ':judul' => '🚨 Laporan ' . strtoupper($urgensi) . ' masuk!',
                ':isi'   => "Ada laporan baru kategori '$kategori'. Segera ditangani.",
                ':ref'   => $laporanId,
            ]);
        }
    }

    logAktivitas($pdo, $currentUser['id'] ?? null, 'LAPORAN', 'laporan', (string)$laporanId);

    jsonResponse(true, 'Laporan berhasil dikirim. Terima kasih!', ['id' => $laporanId], 201);
}

// ============================================================
// PUT: Update status laporan (hanya pengurus/admin)
// ============================================================
if ($method === 'PUT') {
    if (!canVerify($currentUser)) jsonResponse(false, 'Akses ditolak.', [], 403);

    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) jsonResponse(false, 'ID tidak valid.', [], 400);

    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $status = sanitizeStr($body['status'] ?? '');
    $valid  = ['diverifikasi', 'diproses', 'selesai', 'ditolak'];

    if (!in_array($status, $valid)) jsonResponse(false, 'Status tidak valid.', [], 400);

    $old = $pdo->prepare("SELECT * FROM laporan WHERE id = :id");
    $old->execute([':id' => $id]);
    $dataLama = $old->fetch();
    if (!$dataLama) jsonResponse(false, 'Laporan tidak ditemukan.', [], 404);

    $sets = ['status = :status', 'updated_at = NOW()'];
    $params = [':id' => $id, ':status' => $status];

    if ($status === 'diverifikasi' && $currentUser) {
        $sets[] = 'diverifikasi_oleh = :verif';
        $params[':verif'] = $currentUser['id'];
    }
    if ($status === 'diproses' && $currentUser) {
        $sets[] = 'ditangani_oleh = :tangani';
        $params[':tangani'] = $currentUser['id'];
    }
    if ($status === 'selesai') {
        $sets[] = 'tanggal_selesai = NOW()';
    }
    if (!empty($body['catatan_verifikasi'])) {
        $sets[] = 'catatan_verifikasi = :catatan';
        $params[':catatan'] = sanitizeStr($body['catatan_verifikasi'], 1000);
    }

    $pdo->prepare("UPDATE laporan SET " . implode(', ', $sets) . " WHERE id = :id")->execute($params);
    logAktivitas($pdo, $currentUser['id'], 'UPDATE', 'laporan', (string)$id, $dataLama, $body);

    jsonResponse(true, "Status laporan diperbarui menjadi '$status'.", ['id' => $id]);
}

// ============================================================
// PUT: Update koordinat (via drag map)
// ============================================================
if ($method === 'PUT' && $action === 'update_koordinat') {
    if (!canVerify($currentUser)) jsonResponse(false, 'Akses ditolak.', [], 403);
    
    $id = (int)($_GET['id'] ?? 0);
    $body = json_decode(file_get_contents('php://input'), true);
    
    $lat = isset($body['latitude']) ? (float)$body['latitude'] : null;
    $lng = isset($body['longitude']) ? (float)$body['longitude'] : null;
    
    if (!$lat || !$lng) jsonResponse(false, 'Latitude dan Longitude wajib diisi.', [], 400);

    $pdo->prepare("UPDATE laporan SET latitude = :lat, longitude = :lng, updated_at = NOW() WHERE id = :id")->execute([
        ':lat' => $lat,
        ':lng' => $lng,
        ':id'  => $id
    ]);
    
    logAktivitas($pdo, $currentUser['id'], 'UPDATE_KOORDINAT', 'laporan', (string)$id);
    jsonResponse(true, 'Koordinat laporan berhasil diperbarui.', ['id' => $id]);
}

jsonResponse(false, 'Method tidak didukung.', [], 405);
