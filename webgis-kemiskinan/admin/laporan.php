<?php
require_once __DIR__ . '/_layout.php';
require_login(['admin','pengurus','pimpinan']);

$currentUser = ['id' => (int)$_SESSION['user_id'], 'role' => $_SESSION['role']];
$filter = $_GET['filter'] ?? 'semua';
$msg = ''; $err = '';

// Update status laporan
if (isset($_POST['update_status']) && in_array($currentUser['role'], ['admin','pengurus'])) {
    $id = (int)($_POST['lap_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $catatan = trim($_POST['catatan'] ?? '');
    $valid = ['diverifikasi','diproses','selesai','ditolak'];
    if ($id > 0 && in_array($status, $valid)) {
        try {
            $sets = ['status=:s','updated_at=NOW()'];
            $params = [':s'=>$status,':id'=>$id];
            if ($status === 'diverifikasi') { $sets[] = 'diverifikasi_oleh=:v'; $params[':v'] = $currentUser['id']; }
            if ($status === 'diproses')     { $sets[] = 'ditangani_oleh=:t';    $params[':t'] = $currentUser['id']; }
            if ($status === 'selesai')      { $sets[] = 'tanggal_selesai=NOW()'; }
            if ($catatan) { $sets[] = 'catatan_verifikasi=:c'; $params[':c'] = $catatan; }
            $pdo->prepare("UPDATE laporan SET ".implode(',',$sets)." WHERE id=:id")->execute($params);
            logAktivitas($pdo, $currentUser['id'], 'UPDATE', 'laporan', (string)$id, null, ['status'=>$status]);
            $msg = "Status laporan #$id diperbarui ke '$status'.";
        } catch(Exception $e) { $err = $e->getMessage(); }
    }
}

// Ambil laporan
$where = [];
$params = [];
if ($filter === 'pending')   { $where[] = "status='pending'"; }
if ($filter === 'darurat')   { $where[] = "tingkat_urgensi IN ('darurat','tinggi')"; }
if ($filter === 'diproses')  { $where[] = "status IN ('diverifikasi','diproses')"; }
if ($filter === 'selesai')   { $where[] = "status='selesai'"; }
$whereStr = $where ? 'WHERE '.implode(' AND ',$where) : '';

$rows = [];
try {
    $rows = $pdo->query("
        SELECT l.*, p.nama_lengkap AS nama_terdampak, u.nama AS nama_verifikator
        FROM laporan l
        LEFT JOIN penduduk p ON p.nik=l.nik_terdampak
        LEFT JOIN users u ON u.id=l.diverifikasi_oleh
        $whereStr
        ORDER BY CASE l.tingkat_urgensi WHEN 'darurat' THEN 1 WHEN 'tinggi' THEN 2 ELSE 3 END,
                 CASE l.status WHEN 'pending' THEN 1 WHEN 'diverifikasi' THEN 2 ELSE 3 END,
                 l.created_at DESC
    ")->fetchAll();
} catch(Exception $e) { $err = $e->getMessage(); }

render_header('Manajemen Laporan');
?>

<div class="page-title">🚩 Laporan Masyarakat</div>

<?php if ($msg): ?><div class="notice" style="border-color:#bbf7d0;background:#f0fdf4;color:#166534"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="notice" style="border-color:#fecaca;background:#fef2f2;color:#991b1b"><?= h($err) ?></div><?php endif; ?>

<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
  <?php foreach (['semua'=>'Semua','pending'=>'Pending','darurat'=>'🔴 Darurat','diproses'=>'Diproses','selesai'=>'Selesai'] as $k=>$v): ?>
  <a href="?filter=<?= $k ?>" class="btn <?= $filter===$k?'btn-primary':'btn-secondary' ?>"><?= $v ?></a>
  <?php endforeach; ?>
</div>

<div class="card" style="padding:0;overflow:hidden">
  <table>
    <thead>
      <tr>
        <th>#</th><th>Pelapor</th><th>Terdampak</th><th>Kategori</th>
        <th>Urgensi</th><th>Status</th><th>Tanggal</th><th>Aksi</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= $r['id'] ?></td>
        <td><?= h($r['nama_pelapor'] ?: 'Anonim') ?></td>
        <td><?= h($r['nama_terdampak'] ?? ($r['nik_terdampak'] ?? '-')) ?></td>
        <td style="font-size:11px"><?= h(str_replace('_',' ',$r['kategori'])) ?></td>
        <td><span class="tag <?= $r['tingkat_urgensi']==='darurat'?'darurat':'pending' ?>"><?= h($r['tingkat_urgensi']) ?></span></td>
        <td><span class="tag <?= $r['status'] ?>"><?= h($r['status']) ?></span></td>
        <td style="font-size:11px"><?= date('d/m/y H:i', strtotime($r['created_at'])) ?></td>
        <td>
          <?php if (in_array($currentUser['role'], ['admin','pengurus'])): ?>
          <button class="btn btn-secondary btn-sm" onclick="showUpdate(<?= $r['id'] ?>, '<?= h($r['status']) ?>', '<?= addslashes($r['catatan_verifikasi'] ?? '') ?>')">
            Update
          </button>
          <?php endif; ?>
          <button class="btn btn-secondary btn-sm" onclick="showDetail(<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>)">Detail</button>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?>
      <tr><td colspan="8" style="text-align:center;padding:30px;color:#94a3b8">Tidak ada laporan</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Modal Update Status -->
<div id="modal-update" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:999;align-items:center;justify-content:center">
  <div style="background:#1e293b;border-radius:16px;padding:24px;width:100%;max-width:440px;border:1px solid #334155">
    <h3 style="margin-bottom:16px">Update Status Laporan #<span id="upd-id"></span></h3>
    <form method="POST">
      <input type="hidden" name="lap_id" id="upd-lap-id">
      <div class="form-group" style="margin-bottom:12px">
        <label>Status Baru</label>
        <select name="status" id="upd-status">
          <option value="diverifikasi">Diverifikasi</option>
          <option value="diproses">Diproses</option>
          <option value="selesai">Selesai</option>
          <option value="ditolak">Ditolak</option>
        </select>
      </div>
      <div class="form-group" style="margin-bottom:16px">
        <label>Catatan</label>
        <textarea name="catatan" id="upd-catatan" rows="3" placeholder="Catatan verifikasi/tindakan..."></textarea>
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modal-update').style.display='none'">Batal</button>
        <button type="submit" name="update_status" value="1" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Detail -->
<div id="modal-detail" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:999;align-items:center;justify-content:center">
  <div style="background:#1e293b;border-radius:16px;padding:24px;width:100%;max-width:520px;border:1px solid #334155;max-height:80vh;overflow-y:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h3>Detail Laporan</h3>
      <button class="btn btn-secondary btn-sm" onclick="document.getElementById('modal-detail').style.display='none'">✕</button>
    </div>
    <div id="detail-content"></div>
  </div>
</div>

<script>
function showUpdate(id, status, catatan) {
  document.getElementById('upd-id').textContent = id;
  document.getElementById('upd-lap-id').value   = id;
  document.getElementById('upd-status').value   = status;
  document.getElementById('upd-catatan').value  = catatan;
  const m = document.getElementById('modal-update');
  m.style.display = 'flex';
}
function showDetail(data) {
  const m = document.getElementById('modal-detail');
  document.getElementById('detail-content').innerHTML = `
    <div style="font-size:13px;display:grid;gap:8px">
      <div><strong>Pelapor:</strong> ${data.nama_pelapor || 'Anonim'}</div>
      <div><strong>Terdampak:</strong> ${data.nik_terdampak || '-'}</div>
      <div><strong>Kategori:</strong> ${data.kategori.replace(/_/g,' ')}</div>
      <div><strong>Urgensi:</strong> ${data.tingkat_urgensi}</div>
      <div><strong>Alamat:</strong> ${data.alamat || '-'}</div>
      <div style="background:#334155;border-radius:8px;padding:10px;margin-top:4px"><strong>Deskripsi:</strong><br>${data.deskripsi}</div>
      ${data.catatan_verifikasi ? `<div><strong>Catatan:</strong> ${data.catatan_verifikasi}</div>` : ''}
      ${data.foto ? `<div><strong>Foto:</strong> <a href="../uploads/foto_laporan/${data.foto}" target="_blank" style="color:#3b82f6">Lihat Foto</a></div>` : ''}
    </div>
  `;
  m.style.display = 'flex';
}
</script>

<?php render_footer(); ?>
