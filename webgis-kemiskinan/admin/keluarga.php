<?php
require_once __DIR__ . '/_layout.php';
require_login(['admin','pengurus','pimpinan']);

$role    = $_SESSION['role'];
$canEdit = in_array($role, ['admin','pengurus']);
$msg = ''; $err = '';

// DELETE
if ($canEdit && isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $pdo->prepare("DELETE FROM keluarga WHERE id=:id")->execute([':id'=>$id]);
        logAktivitas($pdo, (int)$_SESSION['user_id'], 'DELETE', 'keluarga', (string)$id);
        $msg = "Data keluarga #$id dihapus.";
    } catch(Exception $e) { $err = 'Gagal hapus: '.$e->getMessage(); }
}

// SAVE
if ($canEdit && isset($_POST['save'])) {
    $nokk  = trim($_POST['no_kk'] ?? '');
    $nama  = trim($_POST['nama_kk'] ?? '');
    $idEdit = (int)($_POST['id_edit'] ?? 0);
    if (!preg_match('/^\d{16}$/', $nokk)) $err = 'No KK harus 16 digit.';
    elseif (strlen($nama) < 3)            $err = 'Nama KK terlalu pendek.';
    else {
        try {
            $data = [
                ':nokk'  => $nokk, ':nama'  => $nama,
                ':almt'  => $_POST['alamat']??'' ?: null,
                ':kel'   => $_POST['kelurahan']??'' ?: null,
                ':kec'   => $_POST['kecamatan']??'' ?: null,
                ':kota'  => $_POST['kota']??'' ?: null,
                ':lat'   => $_POST['latitude']??'' ?: null,
                ':lng'   => $_POST['longitude']??'' ?: null,
                ':se'    => $_POST['status_ekonomi']??'rentan',
                ':ri'    => $_POST['rumah_ibadah_id']??'' ?: null,
            ];
            if ($idEdit) {
                $data[':id'] = $idEdit;
                $pdo->prepare("UPDATE keluarga SET no_kk=:nokk,nama_kk=:nama,alamat=:almt,kelurahan=:kel,
                    kecamatan=:kec,kota=:kota,latitude=:lat,longitude=:lng,status_ekonomi=:se,
                    rumah_ibadah_id=:ri WHERE id=:id")->execute($data);
                $msg = "Data KK $nama diperbarui.";
                logAktivitas($pdo, (int)$_SESSION['user_id'], 'UPDATE', 'keluarga', (string)$idEdit);
            } else {
                $pdo->prepare("INSERT INTO keluarga (no_kk,nama_kk,alamat,kelurahan,kecamatan,kota,latitude,longitude,status_ekonomi,rumah_ibadah_id)
                    VALUES(:nokk,:nama,:almt,:kel,:kec,:kota,:lat,:lng,:se,:ri)")->execute($data);
                $msg = "Keluarga $nama berhasil ditambahkan.";
                logAktivitas($pdo, (int)$_SESSION['user_id'], 'CREATE', 'keluarga', (string)$pdo->lastInsertId());
            }
        } catch(Exception $e) { $err = $e->getMessage(); }
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM keluarga WHERE id=:id");
    $s->execute([':id'=>(int)$_GET['edit']]);
    $edit = $s->fetch() ?: null;
}

$search = trim($_GET['s'] ?? '');
$where = []; $params = [];
if ($search) { $where[] = '(k.nama_kk LIKE :q OR k.no_kk LIKE :q2)'; $params[':q']='%'.$search.'%'; $params[':q2']='%'.$search.'%'; }
$whereStr = $where ? 'WHERE '.implode(' AND ',$where) : '';
$rows = $pdo->prepare("
    SELECT k.*, ri.nama AS nama_ri,
           (SELECT COUNT(*) FROM penduduk p WHERE p.id_keluarga=k.id) AS jml_anggota
    FROM keluarga k
    LEFT JOIN rumah_ibadah ri ON ri.id=k.rumah_ibadah_id
    $whereStr ORDER BY k.nama_kk LIMIT 300
");
$rows->execute($params);
$rows = $rows->fetchAll();
$riList = $pdo->query("SELECT id,nama,jenis FROM rumah_ibadah ORDER BY nama")->fetchAll();

render_header('Keluarga (KK)');
?>
<div class="page-title">🏠 Data Keluarga (Kartu Keluarga)</div>

<?php if ($msg): ?><div class="notice" style="border-color:#bbf7d0;background:#f0fdf4;color:#166534"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="notice" style="border-color:#fecaca;background:#fef2f2;color:#991b1b"><?= h($err) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:<?= $canEdit ? '1fr 1.5fr' : '1fr' ?>;gap:16px">
<?php if ($canEdit): ?>
<div class="card">
  <strong><?= $edit ? '✏️ Edit KK' : '➕ Tambah KK' ?></strong><br><br>
  <form method="POST">
    <?php if ($edit): ?><input type="hidden" name="id_edit" value="<?= $edit['id'] ?>"><?php endif; ?>
    <div class="form-grid">
      <div class="form-group full"><label>No KK (16 digit) *</label>
        <input name="no_kk" maxlength="16" required value="<?= h($edit['no_kk'] ?? '') ?>"></div>
      <div class="form-group full"><label>Nama Kepala Keluarga *</label>
        <input name="nama_kk" required value="<?= h($edit['nama_kk'] ?? '') ?>"></div>
      <div class="form-group full"><label>Alamat</label>
        <textarea name="alamat" rows="2"><?= h($edit['alamat'] ?? '') ?></textarea></div>
      <div class="form-group"><label>Kelurahan</label>
        <input name="kelurahan" value="<?= h($edit['kelurahan'] ?? '') ?>"></div>
      <div class="form-group"><label>Kecamatan</label>
        <input name="kecamatan" value="<?= h($edit['kecamatan'] ?? '') ?>"></div>
      <div class="form-group"><label>Status Ekonomi</label>
        <select name="status_ekonomi">
          <?php foreach (['miskin','rentan','mampu'] as $s): ?>
          <option value="<?= $s ?>" <?= ($edit['status_ekonomi'] ?? 'rentan')===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="form-group"><label>Rumah Ibadah Terdekat</label>
        <select name="rumah_ibadah_id">
          <option value="">— Tidak ada —</option>
          <?php foreach ($riList as $ri): ?>
          <option value="<?= $ri['id'] ?>" <?= ($edit['rumah_ibadah_id'] ?? '')==$ri['id']?'selected':'' ?>><?= h($ri['nama']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="form-group"><label>Latitude</label>
        <input name="latitude" type="number" step="any" value="<?= h($edit['latitude'] ?? '') ?>"></div>
      <div class="form-group"><label>Longitude</label>
        <input name="longitude" type="number" step="any" value="<?= h($edit['longitude'] ?? '') ?>"></div>
    </div>
    <br>
    <button type="submit" name="save" class="btn btn-primary"><?= $edit ? '💾 Update' : '➕ Simpan' ?></button>
    <?php if ($edit): ?><a href="keluarga.php" class="btn btn-secondary">Batal</a><?php endif; ?>
  </form>
</div>
<?php endif; ?>

<div>
  <div style="display:flex;gap:8px;margin-bottom:12px">
    <form method="GET" style="display:flex;gap:8px;flex:1">
      <input name="s" placeholder="Cari nama / No KK…" value="<?= h($search) ?>" style="flex:1">
      <button class="btn btn-primary">🔍</button>
    </form>
  </div>
  <div class="card" style="padding:0;overflow:hidden">
    <table>
      <thead><tr><th>No KK</th><th>Nama KK</th><th>Status</th><th>Anggota</th><th>Rumah Ibadah</th><?= $canEdit?'<th>Aksi</th>':'' ?></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td style="font-size:11px"><?= h($r['no_kk']) ?></td>
        <td><strong><?= h($r['nama_kk']) ?></strong><br><span style="font-size:11px;color:#94a3b8"><?= h($r['kecamatan']??'') ?></span></td>
        <td><span class="tag <?= $r['status_ekonomi'] ?>"><?= h($r['status_ekonomi']) ?></span></td>
        <td><?= $r['jml_anggota'] ?> jiwa</td>
        <td style="font-size:12px"><?= h($r['nama_ri'] ?? '-') ?></td>
        <?php if ($canEdit): ?>
        <td class="actions">
          <a href="?edit=<?= $r['id'] ?>" class="btn btn-secondary btn-sm">✏️</a>
          <a href="?delete=<?= $r['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus KK ini?')">🗑️</a>
        </td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?><tr><td colspan="6" style="text-align:center;padding:24px;color:#94a3b8">Belum ada data</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div>
<?php render_footer(); ?>
