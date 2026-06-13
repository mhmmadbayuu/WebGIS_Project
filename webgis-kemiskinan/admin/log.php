<?php
require_once __DIR__ . '/_layout.php';
require_login(['admin']);

$rows = $pdo->query("
    SELECT l.*, u.nama AS nama_user
    FROM log_aktivitas l
    LEFT JOIN users u ON u.id=l.user_id
    ORDER BY l.created_at DESC
    LIMIT 200
")->fetchAll();

render_header('Log Aktivitas');
?>
<div class="page-title">📜 Log Aktivitas Sistem</div>
<div class="card" style="padding:0;overflow:hidden">
  <table>
    <thead><tr><th>Waktu</th><th>User</th><th>Aksi</th><th>Tabel</th><th>Record</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td style="font-size:11px"><?= date('d/m/y H:i:s', strtotime($r['created_at'])) ?></td>
      <td><?= h($r['nama_user'] ?? 'Sistem') ?></td>
      <td><span class="tag <?= $r['aksi']==='DELETE'?'darurat':($r['aksi']==='CREATE'?'selesai':'pending') ?>"><?= h($r['aksi']) ?></span></td>
      <td style="font-size:11px"><?= h($r['tabel'] ?? '-') ?></td>
      <td style="font-size:11px"><?= h($r['record_id'] ?? '-') ?></td>
      <td style="font-size:10px;color:#94a3b8"><?= h($r['ip_address'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php render_footer(); ?>
