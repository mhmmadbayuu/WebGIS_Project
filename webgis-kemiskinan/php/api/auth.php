<?php
// ============================================================
// API: Autentikasi
// POST ?action=login
// POST ?action=logout
// GET  ?action=me
// ============================================================

require_once __DIR__ . '/../middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'POST' && $action === 'login') {
    $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $username = sanitizeStr($body['username'] ?? '');
    $password = $body['password'] ?? '';

    if (!$username || !$password) {
        jsonResponse(false, 'Username dan password wajib diisi.', [], 400);
    }

    $result = doLogin($pdo, $username, $password);
    if (!$result['success']) {
        jsonResponse(false, $result['message'], [], 401);
    }

    jsonResponse(true, 'Login berhasil.', [
        'role'    => $result['role'],
        'user_id' => $_SESSION['user_id'],
        'nama'    => $_SESSION['user_nama'],
    ]);
}

if ($method === 'POST' && $action === 'logout') {
    doLogout($pdo);
    jsonResponse(true, 'Logout berhasil.');
}

if ($method === 'GET' && $action === 'me') {
    $user = getApiUser($pdo);
    if (!$user) jsonResponse(false, 'Belum login.', [], 401);
    $detail = $pdo->prepare("SELECT u.id, u.nama, u.username, u.email, r.nama AS role, u.rumah_ibadah_id, ri.nama AS nama_ri FROM users u JOIN roles r ON r.id=u.role_id LEFT JOIN rumah_ibadah ri ON ri.id=u.rumah_ibadah_id WHERE u.id=:id");
    $detail->execute([':id' => $user['id']]);
    jsonResponse(true, 'OK', ['data' => $detail->fetch()]);
}

jsonResponse(false, 'Action tidak dikenal.', [], 400);
