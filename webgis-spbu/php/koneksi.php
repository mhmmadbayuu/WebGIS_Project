<?php
// Support Railway MySQL plugin env vars (MYSQLHOST, MYSQLUSER, MYSQLPASSWORD, MYSQLPORT)
// Prioritas: Railway vars > custom vars > default localhost
$host = getenv('MYSQLHOST')     ?: getenv('DB_HOST')      ?: "localhost";
$port = getenv('MYSQLPORT')     ?: getenv('DB_PORT')      ?: "3306";
$db   = getenv('MYSQLDATABASE') ?: getenv('DB_NAME_SPBU') ?: "webgis_spbu";
$user = getenv('MYSQLUSER')     ?: getenv('DB_USER')      ?: "root";
$pass = getenv('MYSQLPASSWORD') ?: getenv('DB_PASSWORD')  ?: "";
$charset = "utf8mb4";

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Koneksi database gagal: " . $e->getMessage()
    ]);
    exit;
}
?>