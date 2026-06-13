<?php
require_once __DIR__ . '/../php/koneksi.php';
require_once __DIR__ . '/../php/middleware/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = doLogin($pdo, $_POST['username'] ?? '', $_POST['password'] ?? '');
    if ($result['success']) {
        $redirect = [
            'admin'      => 'index.php',
            'pengurus'   => 'index.php',
            'pimpinan'   => 'index.php',
            'masyarakat' => '../index.html',
        ];
        header('Location: ' . ($redirect[$result['role']] ?? 'index.php'));
        exit;
    }
    $error = $result['message'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Login — WebGIS Pengentasan Kemiskinan</title>
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
    .error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#ef4444;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px;display:none}
    .roles{margin-top:20px;background:#0f172a;border-radius:10px;padding:14px}
    .roles h4{font-size:11px;font-weight:900;color:#94a3b8;margin-bottom:10px;text-transform:uppercase}
    .role-row{display:flex;justify-content:space-between;font-size:12px;margin-bottom:6px;padding:6px 0;border-bottom:1px solid #1e293b}
    .role-row:last-child{border:none;margin:0}
    .role-badge{background:#334155;padding:2px 8px;border-radius:99px;font-size:10px;font-weight:700}
    .back{text-align:center;margin-top:16px}
    .back a{color:#3b82f6;font-size:13px;text-decoration:none}
  </style>
</head>
<body>
<div class="card">
  <div class="logo">
    <div class="icon">🗺️</div>
    <h1>WebGIS Kemiskinan</h1>
    <p>Berbasis Masyarakat & Rumah Ibadah</p>
  </div>

  <?php if ($error): ?>
    <div class="error" style="display:block"><?= h($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="form-group">
      <label>Username</label>
      <input type="text" name="username" placeholder="Masukkan username" required autofocus value="<?= h($_POST['username'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" placeholder="Masukkan password" required>
    </div>
    <div class="form-group" style="text-align:right; margin-top:-8px; margin-bottom:16px;">
      <a href="forgot.php" style="color:#3b82f6; font-size:11px; text-decoration:none; font-weight:700;">Lupa Password?</a>
    </div>
    <button type="submit" class="btn">🔐 Masuk</button>
  </form>

  <div class="roles">
    <h4>Akun Demo (password: password)</h4>
    <div class="role-row"><span>admin</span><span class="role-badge">Administrator</span></div>
    <div class="role-row"><span>pengurus1</span><span class="role-badge">Pengurus Masjid</span></div>
    <div class="role-row"><span>pimpinan1</span><span class="role-badge">Pimpinan Daerah</span></div>
    <div class="role-row"><span>warga1</span><span class="role-badge">Masyarakat</span></div>
  </div>

  <div class="back"><a href="../../index.html">← Kembali ke Landing Page</a></div>
</div>
</body>
</html>
