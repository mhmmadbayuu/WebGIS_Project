<?php
require_once __DIR__ . '/../php/koneksi.php';
require_once __DIR__ . '/../php/middleware/auth.php';

function render_header(string $title): void {
    $user = $_SESSION['user_nama'] ?? 'Tamu';
    $role = $_SESSION['role'] ?? '';
    $unread = 0;
    global $pdo;
    if (!empty($_SESSION['user_id'])) {
        try { 
            $stmtN = $pdo->prepare("SELECT COUNT(*) FROM notifikasi WHERE user_id=:u AND dibaca=0");
            $stmtN->execute([':u' => $_SESSION['user_id']]);
            $unread = (int)$stmtN->fetchColumn();
        } catch(Exception $e){}
    }
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= h($title) ?> — WebGIS Kemiskinan</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    :root{--bg:#0f172a;--sur:#1e293b;--sur2:#334155;--acc:#3b82f6;--green:#22c55e;--red:#ef4444;--yellow:#f59e0b;--txt:#f1f5f9;--txt2:#94a3b8;--brd:#334155;--rad:12px}
    body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--bg);color:var(--txt);min-height:100vh}
    #topbar{background:var(--sur);border-bottom:1px solid var(--brd);padding:0 20px;height:54px;display:flex;align-items:center;gap:12px;position:sticky;top:0;z-index:100}
    #topbar .logo{font-weight:900;font-size:15px} #topbar .logo span{color:var(--acc)}
    #topbar .spacer{flex:1}
    #topbar .user{font-size:12px;color:var(--txt2);font-weight:700}
    #topbar a.tbtn,#topbar button.tbtn{background:var(--sur2);border:1px solid var(--brd);color:var(--txt);padding:6px 12px;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none;cursor:pointer;transition:.2s;display:inline-flex;align-items:center;gap:5px}
    #topbar a.tbtn:hover,#topbar button.tbtn:hover{background:var(--acc);border-color:var(--acc);color:#fff}
    #wrap{display:flex;min-height:calc(100vh - 54px)}
    #nav{width:220px;background:var(--sur);border-right:1px solid var(--brd);padding:16px 0;flex-shrink:0}
    #nav a{display:flex;align-items:center;gap:10px;padding:10px 20px;color:var(--txt2);text-decoration:none;font-size:13px;font-weight:700;transition:.2s;border-left:3px solid transparent}
    #nav a:hover,#nav a.active{color:var(--txt);background:rgba(59,130,246,.1);border-left-color:var(--acc)}
    #nav .nav-section{padding:12px 20px 4px;font-size:10px;font-weight:900;color:var(--brd);text-transform:uppercase;letter-spacing:.5px}
    #content{flex:1;padding:24px;overflow-x:auto}
    .page-title{font-size:20px;font-weight:900;margin-bottom:20px;display:flex;align-items:center;gap:10px}
    .card{background:var(--sur);border:1px solid var(--brd);border-radius:var(--rad);padding:20px;margin-bottom:16px}
    .stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px}
    .stat{background:var(--sur);border:1px solid var(--brd);border-radius:var(--rad);padding:16px;text-align:center}
    .stat .val{font-size:28px;font-weight:900} .stat .lbl{font-size:11px;color:var(--txt2);font-weight:700;margin-top:2px}
    .stat.red .val{color:var(--red)} .stat.yellow .val{color:var(--yellow)} .stat.green .val{color:var(--green)} .stat.blue .val{color:var(--acc)}
    table{width:100%;border-collapse:collapse;font-size:13px}
    th{background:var(--sur2);padding:10px 12px;text-align:left;font-size:11px;font-weight:900;color:var(--txt2);text-transform:uppercase}
    td{padding:10px 12px;border-bottom:1px solid var(--brd)}
    tr:hover td{background:rgba(255,255,255,.02)}
    .tag{display:inline-block;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:800}
    .tag.miskin{background:rgba(239,68,68,.15);color:var(--red)}
    .tag.rentan{background:rgba(245,158,11,.15);color:var(--yellow)}
    .tag.mampu{background:rgba(34,197,94,.15);color:var(--green)}
    .tag.pending{background:rgba(245,158,11,.15);color:var(--yellow)}
    .tag.diproses{background:rgba(59,130,246,.15);color:var(--acc)}
    .tag.selesai{background:rgba(34,197,94,.15);color:var(--green)}
    .tag.darurat{background:rgba(239,68,68,.2);color:var(--red)}
    .btn{padding:8px 16px;border-radius:8px;border:none;cursor:pointer;font-weight:700;font-size:12px;display:inline-flex;align-items:center;gap:5px;transition:.2s;text-decoration:none}
    .btn-primary{background:var(--acc);color:#fff} .btn-primary:hover{background:#2563eb}
    .btn-success{background:var(--green);color:#fff}
    .btn-danger{background:var(--red);color:#fff}
    .btn-secondary{background:var(--sur2);color:var(--txt);border:1px solid var(--brd)}
    .btn-sm{padding:4px 10px;font-size:11px}
    input,select,textarea{background:var(--sur2);border:1px solid var(--brd);color:var(--txt);padding:8px 12px;border-radius:8px;font-size:13px;width:100%}
    input:focus,select:focus,textarea:focus{outline:none;border-color:var(--acc)}
    .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .form-group{display:flex;flex-direction:column;gap:5px}
    .form-group.full{grid-column:1/-1}
    label{font-size:11px;font-weight:700;color:var(--txt2);text-transform:uppercase}
    .notice{padding:10px 14px;border-radius:8px;border:1px solid;font-size:13px;margin-bottom:12px}
    .actions{display:flex;gap:6px;flex-wrap:wrap}
    .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    @media(max-width:768px){#nav{display:none}#content{padding:16px}.form-grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
<div id="topbar">
  <div class="logo">🗺️ <span>WebGIS</span> Kemiskinan</div>
  <div class="spacer"></div>
  <span class="user">👤 <?= h($user) ?> <span style="color:var(--acc)">(<?= h($role) ?>)</span></span>
  <a class="tbtn" href="../index.html"><i class="fa-solid fa-map"></i> Buka Peta</a>
  <a class="tbtn" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
</div>
<div id="wrap">
<div id="nav">
  <div class="nav-section">Menu Utama</div>
  <a href="index.php" <?= basename($_SERVER['PHP_SELF'])==='index.php'?'class="active"':'' ?>><i class="fa-solid fa-gauge" style="width:16px"></i> Dashboard</a>

  <?php if (in_array($role, ['admin','pengurus'])): ?>
  <div class="nav-section">Data</div>
  <a href="penduduk.php" <?= basename($_SERVER['PHP_SELF'])==='penduduk.php'?'class="active"':'' ?>><i class="fa-solid fa-users" style="width:16px"></i> Penduduk</a>
  <a href="keluarga.php" <?= basename($_SERVER['PHP_SELF'])==='keluarga.php'?'class="active"':'' ?>><i class="fa-solid fa-house-chimney-user" style="width:16px"></i> Keluarga (KK)</a>
  <a href="rumah_ibadah.php" <?= basename($_SERVER['PHP_SELF'])==='rumah_ibadah.php'?'class="active"':'' ?>><i class="fa-solid fa-mosque" style="width:16px"></i> Rumah Ibadah</a>

  <div class="nav-section">Bantuan</div>
  <a href="bantuan.php" <?= basename($_SERVER['PHP_SELF'])==='bantuan.php'?'class="active"':'' ?>><i class="fa-solid fa-gift" style="width:16px"></i> Catat Bantuan</a>
  <a href="histori_bantuan.php" <?= basename($_SERVER['PHP_SELF'])==='histori_bantuan.php'?'class="active"':'' ?>><i class="fa-solid fa-clock-rotate-left" style="width:16px"></i> Histori Bantuan</a>
  <a href="belum_dibantu.php" <?= basename($_SERVER['PHP_SELF'])==='belum_dibantu.php'?'class="active"':'' ?>><i class="fa-solid fa-triangle-exclamation" style="width:16px;color:var(--red)"></i> Belum Dibantu</a>

  <div class="nav-section">Laporan</div>
  <a href="laporan.php" <?= basename($_SERVER['PHP_SELF'])==='laporan.php'?'class="active"':'' ?>><i class="fa-solid fa-flag" style="width:16px"></i> Laporan Masuk</a>
  <?php endif; ?>

  <?php if ($role === 'admin'): ?>
  <div class="nav-section">Sistem</div>
  <a href="users.php" <?= basename($_SERVER['PHP_SELF'])==='users.php'?'class="active"':'' ?>><i class="fa-solid fa-user-shield" style="width:16px"></i> Manajemen User</a>
  <a href="log.php" <?= basename($_SERVER['PHP_SELF'])==='log.php'?'class="active"':'' ?>><i class="fa-solid fa-scroll" style="width:16px"></i> Log Aktivitas</a>
  <?php endif; ?>
</div>
<div id="content">
<?php
}

function render_footer(): void {
    echo '</div></div></body></html>';
}

function require_login(array $roles = []): void {
    requireLogin($roles);
}
