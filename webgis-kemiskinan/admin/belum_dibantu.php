<?php
require_once __DIR__ . '/_layout.php';
require_login(['admin','pengurus','pimpinan']);

$rows = [];
try {
    $rows = $pdo->query("
        SELECT p.nik, p.nama_lengkap, p.status_ekonomi, p.kondisi_kesehatan,
               TIMESTAMPDIFF(YEAR, p.tanggal_lahir, CURDATE()) AS umur, p.pekerjaan, p.penghasilan, p.alamat,
               k.no_kk, k.nama_kk, ri.nama AS nama_ri, ri.jenis AS jenis_ri
        FROM penduduk p
        LEFT JOIN keluarga k ON k.id=p.id_keluarga
        LEFT JOIN rumah_ibadah ri ON ri.id=k.rumah_ibadah_id
        WHERE p.status_hidup='hidup'
          AND p.status_ekonomi IN ('miskin','rentan')
          AND NOT EXISTS(
            SELECT 1 FROM histori_bantuan hb
            WHERE hb.nik=p.nik AND hb.status_penyaluran='disalurkan'
          )
        ORDER BY
            CASE p.status_ekonomi WHEN 'miskin' THEN 1 ELSE 2 END,
            CASE p.kondisi_kesehatan WHEN 'sakit_parah' THEN 1 WHEN 'disabilitas' THEN 2 WHEN 'sakit_ringan' THEN 3 ELSE 4 END,
            p.nama_lengkap
    ")->fetchAll();
} catch(Exception $e) {}

render_header('Belum Pernah Dibantu');
?>
<div class="page-title">⚠️ Warga Belum Pernah Menerima Bantuan</div>

<div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:12px;padding:14px 18px;margin-bottom:16px;font-size:13px">
  Ditemukan <strong style="color:#ef4444"><?= count($rows) ?> jiwa</strong> dengan status ekonomi miskin/rentan yang belum pernah menerima bantuan apapun. Prioritaskan yang kondisi kesehatannya kritis.
</div>

<div class="card" style="padding:0;overflow:hidden">
  <table>
    <thead><tr>
      <th>Nama</th><th>NIK</th><th>Status</th><th>Kesehatan</th><th>Umur</th><th>Pekerjaan</th><th>Rumah Ibadah</th>
      <?php if (in_array($_SESSION['role'], ['admin','pengurus'])): ?><th>Aksi</th><?php endif; ?>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
      $kritis = $r['status_ekonomi']==='miskin' && in_array($r['kondisi_kesehatan'],['sakit_parah','disabilitas']);
    ?>
    <tr <?= $kritis ? 'style="background:rgba(239,68,68,.05)"' : '' ?>>
      <td>
        <?= $kritis ? '🔥 ' : '' ?><strong><?= h($r['nama_lengkap']) ?></strong>
        <?= $r['status_ekonomi']==='miskin' && $r['kondisi_kesehatan']==='sakit_parah' ? '<span class="tag darurat" style="font-size:9px;margin-left:4px">KRITIS</span>' : '' ?>
      </td>
      <td style="font-size:11px;color:#94a3b8"><?= h($r['nik']) ?></td>
      <td><span class="tag <?= $r['status_ekonomi'] ?>"><?= h($r['status_ekonomi']) ?></span></td>
      <td><?= h(str_replace('_',' ',$r['kondisi_kesehatan'])) ?></td>
      <td><?= $r['umur'] ?> th</td>
      <td><?= h($r['pekerjaan'] ?: '-') ?></td>
      <td><?= h($r['nama_ri'] ?: '-') ?></td>
      <?php if (in_array($_SESSION['role'], ['admin','pengurus'])): ?>
      <td>
        <a href="bantuan.php?nik=<?= h($r['nik']) ?>&nama=<?= urlencode($r['nama_lengkap']) ?>" class="btn btn-success btn-sm">
          🎁 Beri Bantuan
        </a>
      </td>
      <?php endif; ?>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?>
    <tr><td colspan="8" style="text-align:center;padding:24px;color:#22c55e">🎉 Semua warga miskin/rentan sudah pernah dibantu!</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
<?php render_footer(); ?>
