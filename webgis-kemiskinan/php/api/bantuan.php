<?php
// ============================================================
// API: Bantuan & Histori Bantuan
// GET  ?action=jenis|histori&nik=...
// POST action=salurkan
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
// GET
// ============================================================
if ($method === 'GET') {

    if ($action === 'jenis') {
        $rows = $pdo->query("SELECT * FROM jenis_bantuan WHERE aktif=1 ORDER BY kategori, nama")->fetchAll();
        jsonResponse(true, 'OK', ['data' => $rows]);
    }

    if ($action === 'histori') {
        $nik = sanitizeStr($_GET['nik'] ?? '');

        if ($nik && validateNIK($nik)) {
            // Histori per penduduk
            $stmt = $pdo->prepare("
                SELECT hb.*, jb.nama AS nama_bantuan, jb.kategori, jb.satuan,
                       ri.nama AS nama_rumah_ibadah, u.nama AS disalurkan_oleh_nama
                FROM histori_bantuan hb
                JOIN jenis_bantuan jb ON jb.id = hb.id_jenis_bantuan
                LEFT JOIN rumah_ibadah ri ON ri.id = hb.rumah_ibadah_id
                LEFT JOIN users u ON u.id = hb.disalurkan_oleh
                WHERE hb.nik = :nik
                ORDER BY hb.tanggal DESC
            ");
            $stmt->execute([':nik' => $nik]);
            $data = $stmt->fetchAll();

            // Ringkasan
            $summary = [
                'total_bantuan' => count($data),
                'kategori'      => [],
            ];
            foreach ($data as $row) {
                $summary['kategori'][$row['kategori']] = ($summary['kategori'][$row['kategori']] ?? 0) + 1;
            }

            jsonResponse(true, 'OK', ['data' => $data, 'summary' => $summary]);
        }

        // List semua histori (admin/pengurus)
        if (!canViewAll($currentUser)) jsonResponse(false, 'Akses ditolak.', [], 403);

        $where  = [];
        $params = [];
        if (!empty($_GET['bulan']))    { $where[] = 'MONTH(hb.tanggal) = :bln'; $params[':bln'] = (int)$_GET['bulan']; }
        if (!empty($_GET['tahun']))    { $where[] = 'YEAR(hb.tanggal) = :thn';  $params[':thn'] = (int)$_GET['tahun']; }
        if (!empty($_GET['kategori'])) { $where[] = 'jb.kategori = :kat'; $params[':kat'] = $_GET['kategori']; }

        $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $pdo->prepare("
            SELECT hb.id, hb.nik, p.nama_lengkap, hb.tanggal,
                   jb.nama AS nama_bantuan, jb.kategori,
                   hb.jumlah, hb.nilai_rupiah, hb.status_penyaluran,
                   ri.nama AS nama_ri
            FROM histori_bantuan hb
            JOIN penduduk p ON p.nik = hb.nik
            JOIN jenis_bantuan jb ON jb.id = hb.id_jenis_bantuan
            LEFT JOIN rumah_ibadah ri ON ri.id = hb.rumah_ibadah_id
            $whereStr
            ORDER BY hb.tanggal DESC
            LIMIT 1000
        ");
        $stmt->execute($params);
        jsonResponse(true, 'OK', ['data' => $stmt->fetchAll()]);
    }

    if ($action === 'belum_dibantu') {
        // Warga miskin yang BELUM pernah menerima bantuan
        $rows = $pdo->query("
            SELECT p.nik, p.nama_lengkap, p.status_ekonomi, p.kondisi_kesehatan,
                   p.pekerjaan, p.alamat, p.latitude, p.longitude,
                   k.nama_kk, k.no_kk, ri.nama AS nama_ri
            FROM penduduk p
            LEFT JOIN keluarga k ON k.id = p.id_keluarga
            LEFT JOIN rumah_ibadah ri ON ri.id = k.rumah_ibadah_id
            WHERE p.status_hidup = 'hidup'
              AND p.status_ekonomi IN ('miskin', 'rentan')
              AND NOT EXISTS (
                SELECT 1 FROM histori_bantuan hb
                WHERE hb.nik = p.nik AND hb.status_penyaluran = 'disalurkan'
              )
            ORDER BY
                CASE p.status_ekonomi WHEN 'miskin' THEN 1 ELSE 2 END,
                CASE p.kondisi_kesehatan WHEN 'sakit_parah' THEN 1 WHEN 'disabilitas' THEN 2 ELSE 3 END
        ")->fetchAll();

        jsonResponse(true, 'OK', ['data' => $rows, 'total' => count($rows)]);
    }

    if ($action === 'dashboard_stats') {
        // Statistik untuk dashboard
        $stats = $pdo->query("
            SELECT
                (SELECT COUNT(*) FROM penduduk WHERE status_ekonomi='miskin' AND status_hidup='hidup') AS total_miskin,
                (SELECT COUNT(*) FROM penduduk WHERE status_ekonomi='rentan' AND status_hidup='hidup') AS total_rentan,
                (SELECT COUNT(*) FROM penduduk WHERE status_ekonomi='mampu' AND status_hidup='hidup')  AS total_mampu,
                (SELECT COUNT(*) FROM keluarga)          AS total_keluarga,
                (SELECT COUNT(*) FROM rumah_ibadah)      AS total_rumah_ibadah,
                (SELECT COUNT(*) FROM laporan WHERE status='pending') AS laporan_pending,
                (SELECT COUNT(*) FROM laporan WHERE status='diproses') AS laporan_diproses,
                (SELECT COUNT(*) FROM histori_bantuan WHERE MONTH(tanggal)=MONTH(CURDATE()) AND status_penyaluran='disalurkan') AS bantuan_bulan_ini,
                (SELECT COUNT(*) FROM penduduk WHERE status_hidup='hidup' AND status_ekonomi IN ('miskin','rentan')
                   AND NOT EXISTS (SELECT 1 FROM histori_bantuan hb WHERE hb.nik=penduduk.nik AND hb.status_penyaluran='disalurkan')
                ) AS belum_pernah_dibantu
        ")->fetch();

        // Bantuan per kategori bulan ini
        $per_kategori = $pdo->query("
            SELECT jb.kategori, COUNT(*) AS jumlah
            FROM histori_bantuan hb
            JOIN jenis_bantuan jb ON jb.id = hb.id_jenis_bantuan
            WHERE MONTH(hb.tanggal) = MONTH(CURDATE())
              AND hb.status_penyaluran = 'disalurkan'
            GROUP BY jb.kategori
        ")->fetchAll();

        jsonResponse(true, 'OK', ['data' => $stats, 'per_kategori' => $per_kategori]);
    }

    jsonResponse(false, 'Action tidak dikenal.', [], 400);
}

// ============================================================
// POST: salurkan bantuan
// ============================================================
if ($method === 'POST' && $action === 'salurkan') {
    if (!canEdit($currentUser)) jsonResponse(false, 'Akses ditolak.', [], 403);

    $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $nik       = sanitizeStr($body['nik'] ?? '');
    $idJenis   = (int)($body['id_jenis_bantuan'] ?? 0);
    $tanggal   = sanitizeStr($body['tanggal'] ?? date('Y-m-d'));
    $jumlah    = sanitizeStr($body['jumlah'] ?? '');
    $status    = sanitizeStr($body['status_penyaluran'] ?? 'dijadwalkan');

    if (!validateNIK($nik))   jsonResponse(false, 'NIK tidak valid.', [], 400);
    if ($idJenis <= 0)        jsonResponse(false, 'Jenis bantuan harus dipilih.', [], 400);
    if (!strtotime($tanggal)) jsonResponse(false, 'Format tanggal tidak valid.', [], 400);

    // Cek penduduk ada
    $cek = $pdo->prepare("SELECT nik, id_keluarga FROM penduduk WHERE nik = :nik AND status_hidup='hidup'");
    $cek->execute([':nik' => $nik]);
    $pddk = $cek->fetch();
    if (!$pddk) jsonResponse(false, 'Penduduk tidak ditemukan atau sudah meninggal.', [], 404);

    // Cek jenis bantuan ada
    $cekJb = $pdo->prepare("SELECT id FROM jenis_bantuan WHERE id = :id AND aktif=1");
    $cekJb->execute([':id' => $idJenis]);
    if (!$cekJb->fetch()) jsonResponse(false, 'Jenis bantuan tidak ditemukan.', [], 404);

    $buktiNama = null;
    if (!empty($_FILES['bukti']['name'])) {
        try { $buktiNama = uploadFile($_FILES['bukti'], UPLOAD_BUKTI); }
        catch (RuntimeException $e) { jsonResponse(false, $e->getMessage(), [], 400); }
    }

    $stmt = $pdo->prepare("
        INSERT INTO histori_bantuan
            (nik, id_jenis_bantuan, id_keluarga, rumah_ibadah_id, tanggal,
             jumlah, nilai_rupiah, status_penyaluran, catatan, bukti_file, disalurkan_oleh)
        VALUES
            (:nik,:jb,:kk,:ri,:tgl,:jml,:rp,:status,:cat,:bukti,:user)
    ");
    $stmt->execute([
        ':nik'    => $nik,
        ':jb'     => $idJenis,
        ':kk'     => $pddk['id_keluarga'],
        ':ri'     => $currentUser['rumah_ibadah_id'],
        ':tgl'    => $tanggal,
        ':jml'    => $jumlah ?: null,
        ':rp'     => !empty($body['nilai_rupiah']) ? (float)$body['nilai_rupiah'] : null,
        ':status' => in_array($status, ['dijadwalkan','disalurkan','dibatalkan']) ? $status : 'dijadwalkan',
        ':cat'    => sanitizeStr($body['catatan'] ?? '') ?: null,
        ':bukti'  => $buktiNama,
        ':user'   => $currentUser['id'],
    ]);

    $hbId = (int)$pdo->lastInsertId();
    logAktivitas($pdo, $currentUser['id'], 'CREATE', 'histori_bantuan', (string)$hbId, null, $body);

    // Notifikasi ke pimpinan
    $pimpinan = $pdo->query("SELECT id FROM users WHERE role_id=3")->fetchAll();
    foreach ($pimpinan as $pm) {
        $pdo->prepare("INSERT INTO notifikasi (user_id, judul, isi, tipe, ref_id) VALUES (:u,:j,:i,'bantuan',:r)")
            ->execute([':u' => $pm['id'], ':j' => 'Bantuan Disalurkan', ':i' => "Bantuan untuk NIK $nik telah dicatat.", ':r' => $hbId]);
    }

    jsonResponse(true, 'Bantuan berhasil dicatat.', ['id' => $hbId], 201);
}

// ============================================================
// PUT: update status penyaluran
// ============================================================
if ($method === 'PUT') {
    if (!canEdit($currentUser)) jsonResponse(false, 'Akses ditolak.', [], 403);

    $id   = (int)($_GET['id'] ?? 0);
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $status = sanitizeStr($body['status_penyaluran'] ?? '');

    if (!in_array($status, ['dijadwalkan','disalurkan','dibatalkan'])) {
        jsonResponse(false, 'Status tidak valid.', [], 400);
    }

    $old = $pdo->prepare("SELECT * FROM histori_bantuan WHERE id = :id");
    $old->execute([':id' => $id]);
    if (!$old->fetch()) jsonResponse(false, 'Data tidak ditemukan.', [], 404);

    $pdo->prepare("UPDATE histori_bantuan SET status_penyaluran=:s WHERE id=:id")
        ->execute([':s' => $status, ':id' => $id]);

    logAktivitas($pdo, $currentUser['id'], 'UPDATE', 'histori_bantuan', (string)$id);
    jsonResponse(true, 'Status penyaluran diperbarui.', ['id' => $id]);
}

jsonResponse(false, 'Method tidak didukung.', [], 405);
