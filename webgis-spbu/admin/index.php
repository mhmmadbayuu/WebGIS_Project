<?php
require_once __DIR__ . '/_layout.php';
require_login();

// Stats
$counts = [
  'rumah_ibadah' => 0,
  'penduduk' => 0,
  'dibantu' => 0,
  'belum' => 0,
  'verifikasi' => 0
];

try {
  $counts['rumah_ibadah'] = (int)$pdo->query("SELECT COUNT(*) FROM rumah_ibadah_points")->fetchColumn();
  $cols = $pdo->query("SHOW COLUMNS FROM pemukiman_miskin_points")->fetchAll(PDO::FETCH_COLUMN, 0);
  $hasNew = in_array('status_bantuan', $cols, true);
  $counts['penduduk'] = (int)$pdo->query("SELECT COUNT(*) FROM pemukiman_miskin_points")->fetchColumn();
  if ($hasNew) {
    $counts['dibantu'] = (int)$pdo->query("SELECT COUNT(*) FROM pemukiman_miskin_points WHERE status_bantuan='Sudah dibantu'")->fetchColumn();
    $counts['belum'] = (int)$pdo->query("SELECT COUNT(*) FROM pemukiman_miskin_points WHERE status_bantuan='Belum dibantu'")->fetchColumn();
    $counts['verifikasi'] = (int)$pdo->query("SELECT COUNT(*) FROM pemukiman_miskin_points WHERE status_bantuan='Menunggu verifikasi'")->fetchColumn();
  }
} catch (Exception $e) {
}

render_header('Admin Dashboard');
?>
<div class="admin-card">
  <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px">
    <div style="background:#0b1220;border-radius:18px;padding:14px;color:#fff">
      <div style="font-weight:900;font-size:28px"><?= (int)$counts['rumah_ibadah'] ?></div>
      <div style="color:#cbd5e1;font-weight:800">Rumah Ibadah</div>
    </div>
    <div style="background:#0b1220;border-radius:18px;padding:14px;color:#fff">
      <div style="font-weight:900;font-size:28px"><?= (int)$counts['penduduk'] ?></div>
      <div style="color:#cbd5e1;font-weight:800">Penduduk Miskin</div>
    </div>
    <div style="background:#052e1a;border-radius:18px;padding:14px;color:#fff">
      <div style="font-weight:900;font-size:28px"><?= (int)$counts['dibantu'] ?></div>
      <div style="color:#bbf7d0;font-weight:800">Sudah dibantu</div>
    </div>
    <div style="background:#3b0a0a;border-radius:18px;padding:14px;color:#fff">
      <div style="font-weight:900;font-size:28px"><?= (int)$counts['belum'] ?></div>
      <div style="color:#fecaca;font-weight:800">Belum dibantu</div>
    </div>
  </div>
  <div style="margin-top:14px;color:#475569;font-weight:800">
    Menu admin ini melengkapi WebGIS utama: `index.html`.
  </div>
</div>
<?php render_footer(); ?>

