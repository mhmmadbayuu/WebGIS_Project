<?php
// ============================================================
// Middleware Autentikasi & Otorisasi
// ============================================================

require_once __DIR__ . '/../koneksi.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true,
    ]);
}

// ============================================================
// Fungsi login / logout
// ============================================================

function doLogin(PDO $pdo, string $username, string $password): array {
    $stmt = $pdo->prepare("
        SELECT u.*, r.nama AS role_nama
        FROM users u
        JOIN roles r ON r.id = u.role_id
        WHERE u.username = :u AND u.aktif = 1
        LIMIT 1
    ");
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Username atau password salah.'];
    }

    // Update last_login
    $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = :id")
        ->execute([':id' => $user['id']]);

    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_nama']  = $user['nama'];
    $_SESSION['role']       = $user['role_nama'];
    $_SESSION['role_id']    = $user['role_id'];
    $_SESSION['rumah_ibadah_id'] = $user['rumah_ibadah_id'];
    session_regenerate_id(true);

    logAktivitas($pdo, $user['id'], 'LOGIN', 'users', (string)$user['id']);

    return ['success' => true, 'role' => $user['role_nama']];
}

function doLogout(PDO $pdo): void {
    if (isset($_SESSION['user_id'])) {
        logAktivitas($pdo, $_SESSION['user_id'], 'LOGOUT', 'users', (string)$_SESSION['user_id']);
    }
    session_destroy();
}

// ============================================================
// Guard: wajib login
// ============================================================

function requireLogin(array $allowedRoles = []): array {
    if (empty($_SESSION['user_id'])) {
        // Jika request API
        if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            jsonResponse(false, 'Sesi habis. Silakan login kembali.', [], 401);
        }
        header('Location: ' . APP_URL . '/admin/login.php');
        exit;
    }
    if (!empty($allowedRoles) && !in_array($_SESSION['role'], $allowedRoles, true)) {
        if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            jsonResponse(false, 'Tidak punya akses.', [], 403);
        }
        header('Location: ' . APP_URL . '/admin/forbidden.php');
        exit;
    }
    return [
        'id'      => (int)$_SESSION['user_id'],
        'nama'    => $_SESSION['user_nama'],
        'role'    => $_SESSION['role'],
        'role_id' => (int)$_SESSION['role_id'],
        'rumah_ibadah_id' => $_SESSION['rumah_ibadah_id'] ? (int)$_SESSION['rumah_ibadah_id'] : null,
    ];
}

// ============================================================
// Guard API: token-based (opsional, untuk mobile app nanti)
// ============================================================

function getApiUser(PDO $pdo): ?array {
    // Cek session dulu
    if (!empty($_SESSION['user_id'])) {
        return [
            'id'      => (int)$_SESSION['user_id'],
            'role'    => $_SESSION['role'],
            'role_id' => (int)$_SESSION['role_id'],
            'rumah_ibadah_id' => $_SESSION['rumah_ibadah_id'] ? (int)$_SESSION['rumah_ibadah_id'] : null,
        ];
    }
    return null; // belum login = null
}

// ============================================================
// Cek izin per fitur
// ============================================================

function canEdit(?array $user): bool {
    if (!$user) return false;
    return in_array($user['role'], ['admin', 'pengurus'], true);
}

function canVerify(?array $user): bool {
    if (!$user) return false;
    return in_array($user['role'], ['admin', 'pengurus'], true);
}

function canViewAll(?array $user): bool {
    if (!$user) return false;
    return in_array($user['role'], ['admin', 'pengurus', 'pimpinan'], true);
}

function isAdmin(?array $user): bool {
    return $user && $user['role'] === 'admin';
}
