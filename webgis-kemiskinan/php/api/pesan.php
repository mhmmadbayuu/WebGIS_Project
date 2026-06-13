<?php
// ============================================================
// API: Pesan / Chat antar Pengurus Rumah Ibadah
// ============================================================

require_once __DIR__ . '/../middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$currentUser = getApiUser($pdo);

// Harus login untuk fitur chat
if (!$currentUser) jsonResponse(false, 'Harus login.', [], 401);

if ($method === 'GET') {

    if ($action === 'inbox') {
        $riId = !empty($_GET['rumah_ibadah_id']) ? (int)$_GET['rumah_ibadah_id'] : null;

        if ($riId) {
            // Chat diskusi per rumah ibadah
            $stmt = $pdo->prepare("
                SELECT p.*, u.nama AS pengirim, u.role_id,
                       ri.nama AS nama_ri
                FROM pesan p
                JOIN users u ON u.id = p.dari_user_id
                LEFT JOIN rumah_ibadah ri ON ri.id = p.ke_rumah_ibadah_id
                WHERE p.ke_rumah_ibadah_id = :ri
                ORDER BY p.created_at ASC
                LIMIT 200
            ");
            $stmt->execute([':ri' => $riId]);
        } else {
            // Pesan langsung ke user
            $stmt = $pdo->prepare("
                SELECT p.*, u.nama AS pengirim
                FROM pesan p
                JOIN users u ON u.id = p.dari_user_id
                WHERE p.ke_user_id = :uid OR p.dari_user_id = :uid2
                ORDER BY p.created_at ASC
                LIMIT 200
            ");
            $stmt->execute([':uid' => $currentUser['id'], ':uid2' => $currentUser['id']]);
        }
        $pesan = $stmt->fetchAll();

        // Tandai sudah dibaca
        if ($riId) {
            $pdo->prepare("UPDATE pesan SET dibaca=1 WHERE ke_rumah_ibadah_id=:ri AND dari_user_id != :uid")
                ->execute([':ri' => $riId, ':uid' => $currentUser['id']]);
        } else {
            $pdo->prepare("UPDATE pesan SET dibaca=1 WHERE ke_user_id=:uid")
                ->execute([':uid' => $currentUser['id']]);
        }

        jsonResponse(true, 'OK', ['data' => $pesan]);
    }

    if ($action === 'notifikasi') {
        $stmt = $pdo->prepare("
            SELECT * FROM notifikasi
            WHERE user_id = :uid
            ORDER BY created_at DESC
            LIMIT 30
        ");
        $stmt->execute([':uid' => $currentUser['id']]);
        $notif = $stmt->fetchAll();
        $unread = (int)$pdo->prepare("SELECT COUNT(*) FROM notifikasi WHERE user_id=:uid AND dibaca=0")
            ->execute([':uid' => $currentUser['id']]);
        jsonResponse(true, 'OK', ['data' => $notif, 'unread' => array_sum(array_column($notif, 'dibaca') === array_map(fn($r) => $r['dibaca'] == 0, $notif))]);
    }

    if ($action === 'pengurus_list') {
        // Daftar pengurus untuk pilihan chat
        $rows = $pdo->query("
            SELECT u.id, u.nama, u.email, ri.nama AS nama_ri, ri.jenis AS jenis_ri
            FROM users u
            LEFT JOIN rumah_ibadah ri ON ri.id = u.rumah_ibadah_id
            WHERE u.role_id IN (1,2) AND u.aktif=1
            ORDER BY u.nama
        ")->fetchAll();
        jsonResponse(true, 'OK', ['data' => $rows]);
    }

    jsonResponse(false, 'Action tidak dikenal.', [], 400);
}

if ($method === 'POST') {
    // Kirim pesan
    $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $isi  = sanitizeStr($body['isi'] ?? '', 2000);
    if (strlen(trim($isi)) < 1) jsonResponse(false, 'Pesan tidak boleh kosong.', [], 400);

    $keUserId = !empty($body['ke_user_id']) ? (int)$body['ke_user_id'] : null;
    $keRiId   = !empty($body['ke_rumah_ibadah_id']) ? (int)$body['ke_rumah_ibadah_id'] : null;

    if (!$keUserId && !$keRiId) jsonResponse(false, 'Tujuan pesan harus diisi.', [], 400);

    $stmt = $pdo->prepare("
        INSERT INTO pesan (dari_user_id, ke_user_id, ke_rumah_ibadah_id, isi)
        VALUES (:dari, :ke_user, :ke_ri, :isi)
    ");
    $stmt->execute([
        ':dari'    => $currentUser['id'],
        ':ke_user' => $keUserId,
        ':ke_ri'   => $keRiId,
        ':isi'     => $isi,
    ]);

    $newId = (int)$pdo->lastInsertId();

    // Notifikasi ke penerima
    if ($keUserId) {
        $pdo->prepare("INSERT INTO notifikasi (user_id, judul, isi, tipe, ref_id) VALUES (:u,:j,:i,'pesan',:r)")
            ->execute([':u'=>$keUserId, ':j'=>"Pesan dari {$currentUser['role']}", ':i'=>substr($isi,0,100), ':r'=>$newId]);
    }

    jsonResponse(true, 'Pesan terkirim.', ['id' => $newId], 201);
}

jsonResponse(false, 'Method tidak didukung.', [], 405);
