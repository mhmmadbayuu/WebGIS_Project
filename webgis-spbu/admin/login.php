<?php
require_once __DIR__ . '/../php/koneksi.php';
require_once __DIR__ . '/_auth.php';

if (is_logged_in()) {
  header('Location: index.php');
  exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $password = (string)($_POST['password'] ?? '');
  try {
    $stmt = $pdo->prepare("SELECT id, name, username, password_hash, role FROM users WHERE username = :u LIMIT 1");
    $stmt->execute([':u' => $username]);
    $u = $stmt->fetch();
    if ($u && password_verify($password, $u['password_hash'])) {
      $_SESSION['user'] = ['id' => (int)$u['id'], 'name' => $u['name'], 'username' => $u['username'], 'role' => $u['role']];
      header('Location: index.php');
      exit;
    }
    $error = 'Username atau password salah.';
  } catch (Exception $e) {
    $error = $e->getMessage();
  }
}

?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Login Admin</title>
  <link rel="stylesheet" href="../css/style.css">
  <style>
    body{background:#0b1220;margin:0;display:grid;place-items:center;min-height:100vh;padding:18px}
    .card{background:#fff;border-radius:22px;box-shadow:0 18px 45px rgba(0,0,0,.25);width:100%;max-width:420px;padding:18px}
    .title{font-weight:900;font-size:22px;margin:0 0 4px}
    .sub{color:#64748b;margin:0 0 14px;font-weight:700}
    .err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:10px 12px;border-radius:14px;font-weight:800;margin-bottom:12px}
    .btnx{width:100%;margin-top:12px}
    a{color:#2563eb;text-decoration:none;font-weight:900}
  </style>
</head>
<body>
  <div class="card">
    <div class="title">Login Admin</div>
    <div class="sub">Jika belum ada user, jalankan <a href="init_admin.php">init_admin.php</a>.</div>
    <?php if ($error): ?><div class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <form method="post">
      <label>Username</label>
      <input name="username" required>
      <label style="margin-top:10px;display:block;">Password</label>
      <input name="password" type="password" required>
      <button class="btn btn-primary btnx" type="submit">Masuk</button>
    </form>
  </div>
</body>
</html>

