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
        $pdo->prepare("DELETE FROM rumah_ibadah WHERE id=:id")->execute([':id'=>$id]);
        logAktivitas($pdo, (int)$_SESSION['user_id'], 'DELETE', 'rumah_ibadah', (string)$id);
        $msg = "Rumah ibadah #$id dihapus.";
    } catch(Exception $e) { $err = 'Gagal hapus: '.$e->getMessage(); }
}

// SAVE
if ($canEdit && isset($_POST['save'])) {
    $nama = trim($_POST['nama'] ?? '');
    $lat  = $_POST['latitude'] ?? '';
    $lng  = $_POST['longitude'] ?? '';
    $idEdit = (int)($_POST['id_edit'] ?? 0);
    if (strlen($nama) < 3) $err = 'Nama terlalu pendek.';
    elseif (!is_numeric($lat) || !is_numeric($lng)) $err = 'Latitude/Longitude tidak valid.';
    else {
        try {
            $data = [
                ':nama'  => $nama,
                ':jenis' => $_POST['jenis']??'Lainnya',
                ':kontak'=> $_POST['kontak']??'' ?: null,
                ':rad'   => (float)($_POST['radius_meter']??300),
                ':lat'   => (float)$lat, ':lng'  => (float)$lng,
                ':almt'  => $_POST['alamat']??'' ?: null,
                ':kel'   => $_POST['kelurahan']??'' ?: null,
                ':kec'   => $_POST['kecamatan']??'' ?: null,
                ':kota'  => $_POST['kota']??'' ?: null,
                ':desk'  => $_POST['deskripsi']??'' ?: null,
            ];
            if ($idEdit) {
                $data[':id'] = $idEdit;
                $pdo->prepare("UPDATE rumah_ibadah SET nama=:nama,jenis=:jenis,kontak=:kontak,radius_meter=:rad,
                    latitude=:lat,longitude=:lng,alamat=:almt,kelurahan=:kel,kecamatan=:kec,kota=:kota,deskripsi=:desk
                    WHERE id=:id")->execute($data);
                $msg = "$nama diperbarui.";
                logAktivitas($pdo, (int)$_SESSION['user_id'], 'UPDATE', 'rumah_ibadah', (string)$idEdit);
            } else {
                $pdo->prepare("INSERT INTO rumah_ibadah (nama,jenis,kontak,radius_meter,latitude,longitude,alamat,kelurahan,kecamatan,kota,deskripsi)
                    VALUES(:nama,:jenis,:kontak,:rad,:lat,:lng,:almt,:kel,:kec,:kota,:desk)")->execute($data);
                $msg = "$nama berhasil ditambahkan.";
                logAktivitas($pdo, (int)$_SESSION['user_id'], 'CREATE', 'rumah_ibadah', (string)$pdo->lastInsertId());
            }
        } catch(Exception $e) { $err = $e->getMessage(); }
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM rumah_ibadah WHERE id=:id");
    $s->execute([':id'=>(int)$_GET['edit']]);
    $edit = $s->fetch() ?: null;
}

$rows = $pdo->query("
    SELECT ri.*, 
           (SELECT COUNT(*) FROM keluarga k WHERE k.rumah_ibadah_id=ri.id) AS jml_kk
    FROM rumah_ibadah ri ORDER BY ri.nama
")->fetchAll();

$jenisOpts = ['Masjid','Musholla','Gereja Protestan','Gereja Katolik','Pura','Vihara','Klenteng','Lainnya'];

render_header('Rumah Ibadah');
?>
<div class="page-title">🕌 Data Rumah Ibadah</div>

<?php if ($msg): ?><div class="notice" style="border-color:#bbf7d0;background:#f0fdf4;color:#166534"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="notice" style="border-color:#fecaca;background:#fef2f2;color:#991b1b"><?= h($err) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:<?= $canEdit ? '1fr 1.5fr' : '1fr' ?>;gap:16px">
<?php if ($canEdit): ?>
<div class="card">
  <strong><?= $edit ? '✏️ Edit' : '➕ Tambah Rumah Ibadah' ?></strong><br><br>
  <form method="POST">
    <?php if ($edit): ?><input type="hidden" name="id_edit" value="<?= $edit['id'] ?>"><?php endif; ?>
    <div class="form-grid">
      <div class="form-group full"><label>Nama *</label>
        <input name="nama" required value="<?= h($edit['nama'] ?? '') ?>"></div>
      <div class="form-group"><label>Jenis</label>
        <select name="jenis">
          <?php foreach ($jenisOpts as $j): ?>
          <option value="<?= $j ?>" <?= ($edit['jenis']??'Lainnya')===$j?'selected':'' ?>><?= $j ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="form-group"><label>Kontak / No Telp</label>
        <input name="kontak" value="<?= h($edit['kontak'] ?? '') ?>"></div>
      <div class="form-group"><label>Radius Layanan (meter)</label>
        <input name="radius_meter" type="number" min="50" max="5000" value="<?= $edit['radius_meter'] ?? 300 ?>"></div>
      <div class="form-group"><label>Latitude *</label>
        <input name="latitude" type="number" step="any" required value="<?= $edit['latitude'] ?? '' ?>"></div>
      <div class="form-group"><label>Longitude *</label>
        <input name="longitude" type="number" step="any" required value="<?= $edit['longitude'] ?? '' ?>"></div>
      <div class="form-group full"><label>Alamat</label>
        <textarea name="alamat" rows="2"><?= h($edit['alamat'] ?? '') ?></textarea></div>
      <div class="form-group"><label>Kelurahan</label>
        <input name="kelurahan" value="<?= h($edit['kelurahan'] ?? '') ?>"></div>
      <div class="form-group"><label>Kecamatan</label>
        <input name="kecamatan" value="<?= h($edit['kecamatan'] ?? '') ?>"></div>
    </div>
    <br>
    <button type="submit" name="save" class="btn btn-primary"><?= $edit ? '💾 Update' : '➕ Simpan' ?></button>
    <?php if ($edit): ?><a href="rumah_ibadah.php" class="btn btn-secondary">Batal</a><?php endif; ?>
  </form>
</div>
<?php endif; ?>

<div class="card" style="padding:0;overflow:hidden">
  <table>
    <thead><tr><th>Nama</th><th>Jenis</th><th>Alamat</th><th>Radius</th><th>KK</th><?= $canEdit?'<th>Aksi</th>':'' ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><strong><?= h($r['nama']) ?></strong><br><span style="font-size:10px;color:#94a3b8"><?= h($r['kontak']??'') ?></span></td>
      <td style="font-size:12px"><?= h($r['jenis']) ?></td>
      <td style="font-size:11px"><?= h($r['kelurahan']??'') ?><?= $r['kecamatan'] ? ', '.$r['kecamatan'] : '' ?></td>
      <td><?= $r['radius_meter'] ?>m</td>
      <td><?= $r['jml_kk'] ?> KK</td>
      <?php if ($canEdit): ?>
      <td class="actions">
        <a href="?edit=<?= $r['id'] ?>" class="btn btn-secondary btn-sm">✏️</a>
        <a href="?delete=<?= $r['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus rumah ibadah ini?')">🗑️</a>
      </td>
      <?php endif; ?>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?><tr><td colspan="6" style="text-align:center;padding:24px;color:#94a3b8">Belum ada data</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
</div>
<?php render_footer(); ?>
