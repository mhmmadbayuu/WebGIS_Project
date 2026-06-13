<?php
require_once __DIR__ . '/../php/koneksi.php';
require_once __DIR__ . '/_auth.php';

function h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

function render_header($title) {
  $u = current_user();
  $name = $u ? h($u['name'] ?? $u['username'] ?? '') : '';
  echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
  echo '<title>' . h($title) . '</title>';
  echo '<link rel="stylesheet" href="../css/style.css">';
  echo '<style>
    body{background:#0b1220;margin:0}
    .admin-shell{max-width:1100px;margin:0 auto;padding:22px}
    .admin-top{display:flex;align-items:center;justify-content:space-between;color:#e5e7eb;margin-bottom:16px}
    .admin-card{background:#fff;border-radius:18px;box-shadow:0 18px 45px rgba(0,0,0,.25);padding:18px}
    .admin-nav a{color:#e5e7eb;text-decoration:none;margin-right:12px;font-weight:800}
    .admin-nav a:hover{text-decoration:underline}
    table{width:100%;border-collapse:collapse}
    th,td{padding:10px 10px;border-bottom:1px solid #e2e8f0;font-size:14px;text-align:left;vertical-align:top}
    th{font-weight:900}
    .tag{display:inline-block;padding:4px 8px;border-radius:999px;background:#eef2ff;color:#1d4ed8;font-weight:900;font-size:12px}
    .actions{display:flex;gap:8px;flex-wrap:wrap}
    .actions a,.actions button{border:0;border-radius:12px;padding:8px 10px;font-weight:900;cursor:pointer}
    .actions a{background:#0f172a;color:#fff;text-decoration:none}
    .actions button.danger{background:#dc2626;color:#fff}
    .actions a.secondary{background:#e2e8f0;color:#0f172a}
  </style></head><body>';
  echo '<div class="admin-shell">';
  echo '<div class="admin-top"><div><div style="font-size:22px;font-weight:900;color:#fff">Admin Panel</div><div class="admin-nav">';
  echo '<a href="index.php">Dashboard</a><a href="rumah_ibadah.php">Rumah Ibadah</a><a href="penduduk_miskin.php">Penduduk Miskin</a>';
  echo '</div></div><div style="color:#cbd5e1;font-weight:800">' . $name . ' <a href="logout.php" style="color:#93c5fd;text-decoration:none">Logout</a></div></div>';
}

function render_footer() {
  echo '</div></body></html>';
}

