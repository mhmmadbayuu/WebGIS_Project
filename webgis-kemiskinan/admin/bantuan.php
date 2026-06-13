<?php
require_once __DIR__ . '/_layout.php';
require_login(['admin','pengurus']);

$msg = ''; $err = '';
$nikPre  = $_GET['nik'] ?? '';
$namaPre = $_GET['nama'] ?? '';

if (isset($_POST['save'])) {
    $nik   = trim($_POST['nik'] ?? '');
    $idJb  = (int)($_POST['id_jenis_bantuan'] ?? 0);
    $tgl   = $_POST['tanggal'] ?? date('Y-m-d');
    if (!preg_match('/^\d{16}$/', $nik)) $err = 'NIK tidak valid.';
    elseif (!$idJb) $err = 'Jenis bantuan wajib dipilih.';
    else {
        try {
            // Cek penduduk ada
            $pddk = $pdo->prepare("SELECT nik,id_keluarga FROM penduduk WHERE nik=:nik AND status_hidup='hidup'");
            $pddk->execute([':nik'=>$nik]);
            $pddk = $pddk->fetch();
            if (!$pddk) throw new Exception("Penduduk dengan NIK $nik tidak ditemukan / meninggal.");

            $bukti = null;
            if (!empty($_FILES['bukti']['name'])) {
                $bukti = uploadFile($_FILES['bukti'], UPLOAD_BUKTI);
            }
            $stmt = $pdo->prepare("
                INSERT INTO histori_bantuan (nik,id_jenis_bantuan,id_keluarga,rumah_ibadah_id,tanggal,jumlah,nilai_rupiah,status_penyaluran,catatan,bukti_file,disalurkan_oleh)
                VALUES(:nik,:jb,:kk,:ri,:tgl,:jml,:rp,:status,:cat,:bukti,:user)
            ");
            $stmt->execute([
                ':nik'=>$nik, ':jb'=>$idJb, ':kk'=>$pddk['id_keluarga'],
                ':ri'=>$_SESSION['rumah_ibadah_id'] ?: null,
                ':tgl'=>$tgl, ':jml'=>$_POST['jumlah']??'' ?: null,
                ':rp'=>$_POST['nilai_rupiah']??'' ?: null,
                ':status'=>$_POST['status_penyaluran']??'disalurkan',
                ':cat'=>$_POST['catatan']??'' ?: null,
                ':bukti'=>$bukti, ':user'=>$_SESSION['user_id'],
            ]);
            logAktivitas($pdo, (int)$_SESSION['user_id'], 'CREATE', 'histori_bantuan', (string)$pdo->lastInsertId());
            $msg = "Bantuan untuk NIK $nik berhasil dicatat.";
        } catch(Exception $e) { $err = $e->getMessage(); }
    }
}

$jenisList = $pdo->query("SELECT * FROM jenis_bantuan WHERE aktif=1 ORDER BY kategori,nama")->fetchAll();

render_header('Catat Bantuan');
?>
<div class="page-title">🎁 Catat Penyaluran Bantuan</div>
<?php if ($msg): ?><div class="notice" style="border-color:#bbf7d0;background:#f0fdf4;color:#166534"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="notice" style="border-color:#fecaca;background:#fef2f2;color:#991b1b"><?= h($err) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
<div>
<div class="card">
  <form method="POST" enctype="multipart/form-data">
    <div class="form-grid">
      <div class="form-group full">
        <label>NIK Penerima *</label>
        <input name="nik" maxlength="16" value="<?= h($nikPre) ?>" placeholder="16 digit NIK" required onblur="cekPenduduk(this.value)">
      </div>
      <div class="form-group full" id="info-pddk" style="background:#334155;border-radius:8px;padding:10px;font-size:12px;display:<?= $namaPre?'block':'none' ?>">
        <?= $namaPre ? '👤 '.$namaPre : '' ?>
      </div>
      <div class="form-group full">
        <label>Jenis Bantuan *</label>
        <select name="id_jenis_bantuan" required>
          <option value="">— Pilih —</option>
          <?php foreach ($jenisList as $j): ?>
          <option value="<?= $j['id'] ?>"><?= h($j['nama']) ?> (<?= h($j['kategori']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Tanggal *</label>
        <input type="date" name="tanggal" required value="<?= date('Y-m-d') ?>">
      </div>
      <div class="form-group">
        <label>Status</label>
        <select name="status_penyaluran">
          <option value="disalurkan">Disalurkan</option>
          <option value="dijadwalkan">Dijadwalkan</option>
        </select>
      </div>
      <div class="form-group">
        <label>Jumlah / Keterangan</label>
        <input name="jumlah" placeholder="mis: 1 paket sembako">
      </div>
      <div class="form-group">
        <label>Nilai (Rp)</label>
        <input type="number" name="nilai_rupiah" placeholder="0">
      </div>
      <div class="form-group full">
        <label>Catatan</label>
        <textarea name="catatan" rows="2" placeholder="Opsional"></textarea>
      </div>
      <div class="form-group full">
        <label>Bukti Foto/Dokumen</label>
        <input type="file" name="bukti" accept=".jpg,.jpeg,.png,.webp,.pdf">
      </div>
    </div>
    <button type="submit" name="save" value="1" class="btn btn-success" style="margin-top:14px;width:100%">✅ Simpan Bantuan</button>
  </form>
</div>
</div>

<div>
<div class="card">
  <strong>📋 Bantuan Terakhir Disalurkan</strong>
  <table style="margin-top:12px">
    <thead><tr><th>Penerima</th><th>Bantuan</th><th>Tanggal</th></tr></thead>
    <tbody>
    <?php
    $recent = $pdo->query("
        SELECT p.nama_lengkap, jb.nama AS nama_bantuan, hb.tanggal
        FROM histori_bantuan hb
        JOIN penduduk p ON p.nik=hb.nik
        JOIN jenis_bantuan jb ON jb.id=hb.id_jenis_bantuan
        WHERE hb.status_penyaluran='disalurkan'
        ORDER BY hb.created_at DESC LIMIT 10
    ")->fetchAll();
    foreach ($recent as $r): ?>
    <tr>
      <td><?= h($r['nama_lengkap']) ?></td>
      <td><?= h($r['nama_bantuan']) ?></td>
      <td style="font-size:11px"><?= date('d/m/Y', strtotime($r['tanggal'])) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
</div>
</div>

<script>
async function cekPenduduk(nik) {
    if (!/^\d{16}$/.test(nik)) return;
    const res = await fetch('../php/api/penduduk.php?action=detail&nik='+nik);
    const json = await res.json();
    const info = document.getElementById('info-pddk');
    if (json.success) {
        const p = json.data;
        info.style.display = 'block';
        info.innerHTML = `👤 <strong>${p.nama_lengkap}</strong> | ${p.umur}th | 
            <span style="color:${p.status_ekonomi==='miskin'?'#ef4444':'#f59e0b'}">${p.status_ekonomi}</span> | 
            Sudah dibantu: ${json.histori.length}x`;
    } else {
        info.style.display = 'block';
        info.innerHTML = '<span style="color:#ef4444">❌ NIK tidak ditemukan</span>';
    }
}
</script>

<?php render_footer(); ?>
