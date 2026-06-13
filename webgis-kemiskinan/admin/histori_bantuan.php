<?php
require_once __DIR__ . '/_layout.php';
require_login(['admin','pengurus','pimpinan']);

$rows = [];
try {
    $rows = $pdo->query("
        SELECT hb.id, hb.nik, p.nama_lengkap, hb.tanggal,
               jb.nama AS nama_bantuan, jb.kategori,
               hb.jumlah, hb.nilai_rupiah, hb.status_penyaluran,
               ri.nama AS nama_ri, u.nama AS disalurkan_oleh
        FROM histori_bantuan hb
        JOIN penduduk p ON p.nik=hb.nik
        JOIN jenis_bantuan jb ON jb.id=hb.id_jenis_bantuan
        LEFT JOIN rumah_ibadah ri ON ri.id=hb.rumah_ibadah_id
        LEFT JOIN users u ON u.id=hb.disalurkan_oleh
        ORDER BY hb.tanggal DESC
        LIMIT 500
    ")->fetchAll();
} catch(Exception $e) {}

render_header('Histori Bantuan');
?>
<div class="page-title">📜 Histori Penyaluran Bantuan</div>
<div class="card" style="padding:0;overflow:hidden">
  <table>
    <thead><tr>
      <th>#</th><th>Nama Penerima</th><th>Jenis Bantuan</th><th>Jumlah</th>
      <th>Tanggal</th><th>Rumah Ibadah</th><th>Disalurkan Oleh</th><th>Status</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= $r['id'] ?></td>
      <td><?= h($r['nama_lengkap']) ?><br><span style="font-size:10px;color:#94a3b8"><?= h($r['nik']) ?></span></td>
      <td><?= h($r['nama_bantuan']) ?> <span style="font-size:10px;color:#94a3b8">(<?= h($r['kategori']) ?>)</span></td>
      <td><?= h($r['jumlah'] ?? '-') ?><?= $r['nilai_rupiah'] ? '<br><span style="font-size:10px;color:#22c55e">Rp '.number_format($r['nilai_rupiah'],0,',','.').'</span>' : '' ?></td>
      <td><?= date('d/m/Y', strtotime($r['tanggal'])) ?></td>
      <td><?= h($r['nama_ri'] ?? '-') ?></td>
      <td><?= h($r['disalurkan_oleh'] ?? '-') ?></td>
      <td><span class="tag <?= $r['status_penyaluran']==='disalurkan'?'selesai':($r['status_penyaluran']==='dijadwalkan'?'pending':'darurat') ?>"><?= h($r['status_penyaluran']) ?></span></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?><tr><td colspan="8" style="text-align:center;padding:24px;color:#94a3b8">Belum ada data</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php render_footer(); ?>
