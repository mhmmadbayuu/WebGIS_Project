<?php
// Support Railway MySQL plugin env vars (MYSQLHOST, MYSQLUSER, MYSQLPASSWORD, MYSQLPORT)
// Prioritas: Railway vars > custom vars > default localhost
$host = getenv('MYSQLHOST')     ?: getenv('DB_HOST')      ?: "localhost";
$port = getenv('MYSQLPORT')     ?: getenv('DB_PORT')      ?: "3306";
$db   = getenv('MYSQLDATABASE') ?: getenv('DB_NAME_SPBU') ?: "webgis_spbu";
$user = getenv('MYSQLUSER')     ?: getenv('DB_USER')      ?: "root";
$pass = getenv('MYSQLPASSWORD') ?: getenv('DB_PASSWORD')  ?: "";

$dbUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_PRIVATE_URL') ?: getenv('MYSQL_URL');
if ($dbUrl) {
    $parsed = parse_url($dbUrl);
    if ($parsed) {
        $host = $parsed['host'] ?? $host;
        $port = $parsed['port'] ?? $port;
        $user = $parsed['user'] ?? $user;
        $pass = $parsed['pass'] ?? $pass;
    }
}
$charset = "utf8mb4";

// Force IPv4 untuk fix NO_SOCKET di Railway internal network (IPv6)
if (filter_var($host, FILTER_VALIDATE_IP) === false) {
    $resolved = gethostbyname($host);
    if ($resolved !== $host) {
        $host = $resolved;
    }
}
$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_TIMEOUT            => 5, // Fail fast agar tidak hanging
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(503);
    $isApiCall = (strpos($_SERVER['REQUEST_URI'] ?? '', '/php/') !== false)
              || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
              || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html') === false);
    if ($isApiCall) {
        header('Content-Type: application/json');
        echo json_encode(["success" => false, "message" => "Koneksi database gagal: " . $e->getMessage()]);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">
        <title>Database Tidak Tersedia</title>
        <style>body{font-family:system-ui,sans-serif;background:#0f172a;color:#f1f5f9;
        display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;}
        .box{background:#1e293b;border:1px solid #334155;border-radius:16px;padding:40px;
        max-width:460px;text-align:center;}
        h2{color:#f87171;}p{color:#94a3b8;line-height:1.6;}
        a{color:#3b82f6;text-decoration:none;font-weight:600;}</style></head><body>
        <div class="box"><div style="font-size:48px;margin-bottom:16px">🔌</div>
        <h2>Database Belum Tersedia</h2>
        <p>Koneksi ke database sedang diinisialisasi.<br>Silakan tunggu dan muat ulang.</p>
        <a href="javascript:location.reload()">🔄 Muat Ulang</a> &nbsp;|
        &nbsp;<a href="/">← Portal</a></div></body></html>';
    }
    exit;
}
?>