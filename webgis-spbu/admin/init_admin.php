<?php
require_once __DIR__ . '/../php/koneksi.php';

header('Content-Type: text/html; charset=utf-8');

try {
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
      id INT AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(120) NOT NULL,
      username VARCHAR(60) NOT NULL UNIQUE,
      password_hash VARCHAR(255) NOT NULL,
      role VARCHAR(20) NOT NULL DEFAULT 'operator',
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");

  $count = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
  if ($count > 0) {
    echo "Users sudah ada. Silakan login di <a href='login.php'>login.php</a>.";
    exit;
  }

  $username = 'admin';
  $password = 'admin123';
  $hash = password_hash($password, PASSWORD_DEFAULT);
  $stmt = $pdo->prepare("INSERT INTO users (name, username, password_hash, role) VALUES (:n,:u,:p,'admin')");
  $stmt->execute([':n' => 'Administrator', ':u' => $username, ':p' => $hash]);

  echo "Admin dibuat. Username: <b>admin</b>, Password: <b>admin123</b>. Lanjut login: <a href='login.php'>login.php</a>";
} catch (Exception $e) {
  echo "Gagal init admin: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}

