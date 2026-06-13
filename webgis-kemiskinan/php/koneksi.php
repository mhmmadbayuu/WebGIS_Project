<?php
// ============================================================
// Konfigurasi & Koneksi Database
// WebGIS Pengentasan Kemiskinan v2.0
// ============================================================

// Support Railway MySQL plugin env vars (MYSQLHOST, MYSQLUSER, MYSQLPASSWORD, MYSQLPORT)
// Juga support MYSQL_PRIVATE_URL yang di-parse oleh docker-entrypoint.sh
define('DB_HOST',    getenv('MYSQLHOST')     ?: getenv('DB_HOST')     ?: 'localhost');
define('DB_PORT',    getenv('MYSQLPORT')     ?: getenv('DB_PORT')     ?: '3306');
define('DB_NAME',    getenv('MYSQLDATABASE') ?: getenv('DB_NAME_KEMISKINAN') ?: 'webgis_kemiskinan');
define('DB_USER',    getenv('MYSQLUSER')     ?: getenv('DB_USER')     ?: 'root');
define('DB_PASS',    getenv('MYSQLPASSWORD') ?: getenv('DB_PASSWORD') ?: '');
define('DB_CHARSET', 'utf8mb4');


define('APP_NAME',    'WebGIS Pengentasan Kemiskinan');
define('APP_VERSION', '2.0.0');
define('APP_URL',     'http://localhost/webgis-kemiskinan');
define('APP_ENV',     'development'); // Ganti ke 'production' saat deploy

define('UPLOAD_PATH',      __DIR__ . '/../uploads/');
define('UPLOAD_BUKTI',     UPLOAD_PATH . 'bukti/');
define('UPLOAD_LAPORAN',   UPLOAD_PATH . 'foto_laporan/');
define('UPLOAD_FOTO',      UPLOAD_PATH . 'foto_profil/');
define('MAX_FILE_SIZE',    5 * 1024 * 1024); // 5 MB
define('ALLOWED_IMG_EXT',  ['jpg','jpeg','png','webp']);
define('ALLOWED_FILE_EXT', ['jpg','jpeg','png','webp','pdf']);

// Buat folder upload jika belum ada
foreach ([UPLOAD_BUKTI, UPLOAD_LAPORAN, UPLOAD_FOTO] as $dir) {
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
}

// ============================================================
// Koneksi PDO
// ============================================================

// Force IPv4 untuk fix NO_SOCKET di Railway internal network (IPv6)
// gethostbyname() selalu return IPv4 address
$dbHostResolved = DB_HOST;
if (filter_var(DB_HOST, FILTER_VALIDATE_IP) === false) {
    $resolved = gethostbyname(DB_HOST);
    if ($resolved !== DB_HOST) {
        $dbHostResolved = $resolved; // Gunakan IPv4 yang sudah di-resolve
    }
}
$dsn = "mysql:host=" . $dbHostResolved . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

$pdoOptions = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_TIMEOUT            => 5, // Fail fast (5 detik) agar tidak hanging
];


try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $pdoOptions);
} catch (PDOException $e) {
    http_response_code(503);
    $errMsg = (defined('APP_ENV') && APP_ENV === 'development') ? $e->getMessage() : 'Hubungi administrator sistem.';
    
    // Deteksi apakah ini API call atau halaman HTML
    $isApiCall = (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false)
              || (strpos($_SERVER['REQUEST_URI'] ?? '', '/php/') !== false)
              || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
              || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html') === false);
    
    if ($isApiCall) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Koneksi database gagal: ' . DB_NAME . '@' . DB_HOST,
            'error'   => $errMsg
        ]);
    } else {
        // Tampilkan halaman error HTML agar tidak 502
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Database Tidak Tersedia</title>
        <style>body{font-family:system-ui,sans-serif;background:#0f172a;color:#f1f5f9;display:flex;
        align-items:center;justify-content:center;min-height:100vh;margin:0;}
        .box{background:#1e293b;border:1px solid #334155;border-radius:16px;padding:40px;max-width:460px;text-align:center;}
        h2{color:#f87171;margin:0 0 12px;}p{color:#94a3b8;margin:0 0 20px;line-height:1.6;}
        a{color:#3b82f6;text-decoration:none;font-weight:600;}
        .icon{font-size:48px;margin-bottom:16px;}</style></head><body>
        <div class="box"><div class="icon">🔌</div>
        <h2>Database Belum Tersedia</h2>
        <p>Koneksi ke database sedang diinisialisasi.<br>
        Silakan tunggu beberapa saat dan muat ulang halaman.</p>
        <a href="javascript:location.reload()">🔄 Muat Ulang</a> &nbsp;|
        &nbsp;<a href="/">← Kembali ke Portal</a></div></body></html>';
    }
    exit;
}


// ============================================================
// Helper: Response JSON
// ============================================================

function jsonResponse(bool $success, string $message, array $data = [], int $httpCode = 200): void {
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// Helper: Sanitasi & Validasi
// ============================================================

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function sanitizeStr(?string $v, int $max = 255): string {
    return mb_substr(trim($v ?? ''), 0, $max);
}

function validateNIK(string $nik): bool {
    return preg_match('/^\d{16}$/', $nik) === 1;
}

function validateCoord(string $val, string $type = 'lat'): bool {
    $f = (float)$val;
    return $type === 'lat' ? ($f >= -90 && $f <= 90) : ($f >= -180 && $f <= 180);
}

// ============================================================
// Helper: Upload File
// ============================================================

function uploadFile(array $file, string $destDir, array $allowedExt = null): ?string {
    if ($allowedExt === null) $allowedExt = ALLOWED_FILE_EXT;
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > MAX_FILE_SIZE) throw new RuntimeException('Ukuran file melebihi 5MB.');
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) throw new RuntimeException("Format file .$ext tidak diizinkan.");
    $filename = uniqid('img_', true) . '.' . $ext;
    $dest = rtrim($destDir, '/') . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) throw new RuntimeException('Gagal menyimpan file.');
    return $filename;
}

// ============================================================
// Helper: Log Aktivitas
// ============================================================

function logAktivitas(PDO $pdo, ?int $userId, string $aksi, string $tabel = '', string $recordId = '', $dataLama = null, $dataBaru = null): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO log_aktivitas (user_id, aksi, tabel, record_id, data_lama, data_baru, ip_address, user_agent)
            VALUES (:u, :a, :t, :r, :dl, :db, :ip, :ua)
        ");
        $stmt->execute([
            ':u'  => $userId,
            ':a'  => $aksi,
            ':t'  => $tabel,
            ':r'  => $recordId,
            ':dl' => $dataLama ? json_encode($dataLama, JSON_UNESCAPED_UNICODE) : null,
            ':db' => $dataBaru ? json_encode($dataBaru, JSON_UNESCAPED_UNICODE) : null,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    } catch (Exception) { /* log gagal tidak menghentikan proses */ }
}

// ============================================================
// Validasi Logika Pendidikan vs Umur
// ============================================================

function validasiPendidikanUmur(int $umur, string $pendidikan, string $statusPendidikan): array {
    $warnings = [];
    $errors   = [];

    if ($umur >= 7 && $umur <= 12) {
        if ($statusPendidikan !== 'sekolah') {
            $warnings[] = "Anak usia $umur tahun semestinya masih sekolah SD.";
        }
    } elseif ($umur >= 13 && $umur <= 15) {
        if ($statusPendidikan !== 'sekolah') {
            $warnings[] = "Anak usia $umur tahun semestinya masih sekolah SMP.";
        }
    } elseif ($umur >= 16 && $umur <= 18) {
        if ($statusPendidikan !== 'sekolah') {
            $warnings[] = "Anak usia $umur tahun semestinya masih sekolah SMA/SMK.";
        }
    }
    // Umur > 18: bebas, boleh tidak sekolah, boleh pelatihan

    return ['warnings' => $warnings, 'errors' => $errors];
}

// ============================================================
// Prioritas Bantuan
// ============================================================

function hitungPrioritasBantuan(string $statusEkonomi, string $kondisiKesehatan, bool $belumPernahDibantu): string {
    if ($statusEkonomi === 'miskin' && in_array($kondisiKesehatan, ['sakit_parah', 'disabilitas'])) {
        return 'KRITIS';
    }
    if ($statusEkonomi === 'miskin' && $belumPernahDibantu) {
        return 'TINGGI';
    }
    if ($statusEkonomi === 'miskin') {
        return 'SEDANG';
    }
    if ($statusEkonomi === 'rentan' && in_array($kondisiKesehatan, ['sakit_parah', 'disabilitas'])) {
        return 'SEDANG';
    }
    return 'RENDAH';
}
