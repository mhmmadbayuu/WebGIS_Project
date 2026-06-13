<?php
require_once __DIR__ . '/_layout.php';
require_login(['admin','pengurus','pimpinan']);

$stats = [];
try {
    $stats = $pdo->query("
        SELECT
            (SELECT COUNT(*) FROM penduduk WHERE status_ekonomi='miskin' AND status_hidup='hidup') AS miskin,
            (SELECT COUNT(*) FROM penduduk WHERE status_ekonomi='rentan' AND status_hidup='hidup') AS rentan,
            (SELECT COUNT(*) FROM penduduk WHERE status_hidup='hidup') AS total_pddk,
            (SELECT COUNT(*) FROM keluarga) AS total_kk,
            (SELECT COUNT(*) FROM rumah_ibadah) AS total_ri,
            (SELECT COUNT(*) FROM laporan WHERE status='pending') AS laporan_pending,
            (SELECT COUNT(*) FROM laporan WHERE tingkat_urgensi='darurat' AND status NOT IN ('selesai','ditolak')) AS laporan_darurat,
            (SELECT COUNT(*) FROM histori_bantuan WHERE MONTH(tanggal)=MONTH(CURDATE()) AND YEAR(tanggal)=YEAR(CURDATE()) AND status_penyaluran='disalurkan') AS bantuan_bulan,
            (SELECT COUNT(*) FROM penduduk WHERE status_hidup='hidup' AND status_ekonomi IN ('miskin','rentan')
               AND NOT EXISTS(SELECT 1 FROM histori_bantuan hb WHERE hb.nik=penduduk.nik AND hb.status_penyaluran='disalurkan')
            ) AS belum_dibantu
    ")->fetch();
} catch(Exception $e) {}

// Laporan terbaru
$laporan_terbaru = [];
try {
    $laporan_terbaru = $pdo->query("
        SELECT l.id, l.tingkat_urgensi, l.kategori, l.status, l.created_at,
               l.nama_pelapor, p.nama_lengkap AS nama_terdampak
        FROM laporan l
        LEFT JOIN penduduk p ON p.nik = l.nik_terdampak
        ORDER BY
            CASE l.tingkat_urgensi WHEN 'darurat' THEN 1 WHEN 'tinggi' THEN 2 ELSE 3 END,
            l.created_at DESC
        LIMIT 8
    ")->fetchAll();
} catch(Exception $e) {}

// Prioritas belum dibantu
$prioritas = [];
try {
    $prioritas = $pdo->query("
        SELECT p.nik, p.nama_lengkap, p.status_ekonomi, p.kondisi_kesehatan,
               p.alamat, k.no_kk, ri.nama AS nama_ri
        FROM penduduk p
        LEFT JOIN keluarga k ON k.id=p.id_keluarga
        LEFT JOIN rumah_ibadah ri ON ri.id=k.rumah_ibadah_id
        WHERE p.status_hidup='hidup'
          AND p.status_ekonomi='miskin'
          AND p.kondisi_kesehatan IN ('sakit_parah','disabilitas')
          AND NOT EXISTS(SELECT 1 FROM histori_bantuan hb WHERE hb.nik=p.nik AND hb.status_penyaluran='disalurkan')
        ORDER BY CASE p.kondisi_kesehatan WHEN 'sakit_parah' THEN 1 ELSE 2 END
        LIMIT 5
    ")->fetchAll();
} catch(Exception $e) {}

render_header('Dashboard');
?>

<div class="page-title">📊 Dashboard Monitoring</div>

<!-- Stats -->
<div class="stats-row">
  <div class="stat red"><div class="val"><?= $stats['miskin'] ?? 0 ?></div><div class="lbl">Warga Miskin</div></div>
  <div class="stat yellow"><div class="val"><?= $stats['rentan'] ?? 0 ?></div><div class="lbl">Warga Rentan</div></div>
  <div class="stat blue"><div class="val"><?= $stats['total_kk'] ?? 0 ?></div><div class="lbl">Total KK</div></div>
  <div class="stat green"><div class="val"><?= $stats['bantuan_bulan'] ?? 0 ?></div><div class="lbl">Bantuan Bulan Ini</div></div>
  <div class="stat red"><div class="val"><?= $stats['laporan_pending'] ?? 0 ?></div><div class="lbl">Laporan Pending</div></div>
  <div class="stat red"><div class="val"><?= $stats['belum_dibantu'] ?? 0 ?></div><div class="lbl">Belum Pernah Dibantu</div></div>
</div>

<?php if (!empty($stats['laporan_darurat']) && $stats['laporan_darurat'] > 0): ?>
<div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:12px;padding:14px 18px;margin-bottom:16px;display:flex;align-items:center;gap:12px">
  <span style="font-size:24px">🚨</span>
  <div>
    <div style="font-weight:900;color:#ef4444"><?= $stats['laporan_darurat'] ?> LAPORAN DARURAT belum ditangani!</div>
    <div style="font-size:12px;color:#94a3b8;margin-top:2px">Segera tindak lanjuti laporan kondisi darurat dari masyarakat.</div>
  </div>
  <a href="laporan.php?filter=darurat" class="btn btn-danger" style="margin-left:auto">Lihat →</a>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">

  <!-- Laporan terbaru -->
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <strong>📋 Laporan Terbaru</strong>
      <a href="laporan.php" class="btn btn-secondary btn-sm">Semua →</a>
    </div>
    <table>
      <thead><tr><th>Pelapor/Terdampak</th><th>Urgensi</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($laporan_terbaru as $l): ?>
        <tr>
          <td><?= h($l['nama_terdampak'] ?? $l['nama_pelapor'] ?? 'Anonim') ?></td>
          <td><span class="tag <?= $l['tingkat_urgensi'] === 'darurat' ? 'darurat' : 'pending' ?>"><?= h($l['tingkat_urgensi']) ?></span></td>
          <td><span class="tag <?= $l['status'] ?>"><?= h($l['status']) ?></span></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($laporan_terbaru)): ?><tr><td colspan="3" style="text-align:center;color:#94a3b8">Belum ada laporan</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Prioritas kritis -->
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <strong>🔥 Prioritas KRITIS (Belum Dibantu)</strong>
      <a href="belum_dibantu.php" class="btn btn-danger btn-sm">Semua →</a>
    </div>
    <table>
      <thead><tr><th>Nama</th><th>Status</th><th>Kesehatan</th></tr></thead>
      <tbody>
        <?php foreach ($prioritas as $p): ?>
        <tr>
          <td><a href="penduduk.php?detail=<?= h($p['nik']) ?>" style="color:var(--acc)"><?= h($p['nama_lengkap']) ?></a></td>
          <td><span class="tag miskin"><?= h($p['status_ekonomi']) ?></span></td>
          <td style="color:#ef4444"><?= h($p['kondisi_kesehatan']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($prioritas)): ?><tr><td colspan="3" style="text-align:center;color:#94a3b8">🎉 Tidak ada kasus kritis saat ini</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

</div>

<?php render_footer(); ?>
