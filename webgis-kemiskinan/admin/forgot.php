<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Lupa Password — WebGIS Pengentasan Kemiskinan</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Segoe UI',system-ui,sans-serif;background:#0f172a;color:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:16px}
    .card{background:#1e293b;border:1px solid #334155;border-radius:20px;padding:40px 36px;width:100%;max-width:400px;box-shadow:0 25px 60px rgba(0,0,0,.5)}
    .logo{text-align:center;margin-bottom:28px}
    .logo .icon{font-size:40px;margin-bottom:8px}
    .logo h1{font-size:20px;font-weight:900}
    .logo p{font-size:12px;color:#94a3b8;margin-top:4px}
    .form-group{margin-bottom:16px}
    label{display:block;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;margin-bottom:6px}
    input{width:100%;background:#0f172a;border:1px solid #334155;color:#f1f5f9;padding:11px 14px;border-radius:10px;font-size:14px}
    input:focus{outline:none;border-color:#3b82f6}
    .btn{width:100%;background:#3b82f6;color:#fff;border:none;padding:12px;border-radius:10px;font-size:14px;font-weight:800;cursor:pointer;transition:.2s}
    .btn:hover{background:#2563eb}
    .error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#ef4444;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px;}
    .success{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#22c55e;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px;}
    .back{text-align:center;margin-top:16px}
    .back a{color:#3b82f6;font-size:13px;text-decoration:none}
  </style>
</head>
<body>
<?php
require_once __DIR__ . '/../php/koneksi.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeStr($_POST['username'] ?? '');
    $email = sanitizeStr($_POST['email'] ?? '');
    $new_password = $_POST['new_password'] ?? '';

    if (!$username || !$email || !$new_password) {
        $error = 'Semua kolom wajib diisi.';
    } else {
        // Cek kecocokan username dan email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :u AND email = :e LIMIT 1");
        $stmt->execute([':u' => $username, ':e' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE users SET password_hash = :p WHERE id = :id");
            $update->execute([':p' => $hash, ':id' => $user['id']]);
            $success = 'Password berhasil direset! Silakan login dengan password baru Anda.';
        } else {
            $error = 'Username dan Email tidak cocok atau tidak ditemukan.';
        }
    }
}
?>
<div class="card">
  <div class="logo">
    <div class="icon">🔑</div>
    <h1>Reset Password</h1>
    <p>Masukkan Username & Email Terdaftar</p>
  </div>

  <?php if ($error): ?>
    <div class="error"><?= h($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="success"><?= h($success) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="form-group">
      <label>Username</label>
      <input type="text" name="username" placeholder="Cth: admin" required autofocus>
    </div>
    <div class="form-group">
      <label>Email Terdaftar</label>
      <input type="email" name="email" placeholder="Cth: admin@webgis.local" required>
    </div>
    <div class="form-group">
      <label>Password Baru</label>
      <input type="password" name="new_password" placeholder="Masukkan password baru" required>
    </div>
    
    <button type="submit" class="btn">🔄 Reset Password</button>
  </form>

  <div class="back"><a href="login.php">← Kembali ke Halaman Login</a></div>
</div>
</body>
</html>
