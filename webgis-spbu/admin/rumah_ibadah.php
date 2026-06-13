<?php
require_once __DIR__ . '/_layout.php';
require_login();

$msg = '';
$err = '';

// Delete
if (isset($_GET['delete'])) {
  $id = (int)$_GET['delete'];
  try {
    $stmt = $pdo->prepare("DELETE FROM rumah_ibadah_points WHERE id=:id");
    $stmt->execute([':id' => $id]);
    $msg = 'Berhasil dihapus.';
  } catch (Exception $e) {
    $err = $e->getMessage();
  }
}

// Save (create/update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = (int)($_POST['id'] ?? 0);
  $nama = trim($_POST['nama'] ?? '');
  $jenis = trim($_POST['jenis'] ?? 'Lainnya');
  $kontak = trim($_POST['kontak'] ?? '');
  $radius = (float)($_POST['radius_meter'] ?? 300);
  $lat = $_POST['latitude'] ?? null;
  $lng = $_POST['longitude'] ?? null;
  $address = trim($_POST['address'] ?? '');

  if ($nama === '' || $radius <= 0 || $lat === null || $lng === null) {
    $err = 'Data belum lengkap (nama/radius/lat/lng).';
  } else {
    try {
      $cols = $pdo->query("SHOW COLUMNS FROM rumah_ibadah_points")->fetchAll(PDO::FETCH_COLUMN, 0);
      $hasJenis = in_array('jenis', $cols, true);
      $hasKontak = in_array('kontak', $cols, true);

      if ($id > 0) {
        if ($hasJenis && $hasKontak) {
          $stmt = $pdo->prepare("UPDATE rumah_ibadah_points SET nama=:n, jenis=:j, kontak=:k, radius_meter=:r, latitude=:lat, longitude=:lng, address=:a WHERE id=:id");
          $stmt->execute([':n'=>$nama,':j'=>$jenis ?: 'Lainnya',':k'=>$kontak ?: null,':r'=>$radius,':lat'=>$lat,':lng'=>$lng,':a'=>$address ?: null,':id'=>$id]);
        } else {
          $stmt = $pdo->prepare("UPDATE rumah_ibadah_points SET nama=:n, radius_meter=:r, latitude=:lat, longitude=:lng, address=:a WHERE id=:id");
          $stmt->execute([':n'=>$nama,':r'=>$radius,':lat'=>$lat,':lng'=>$lng,':a'=>$address ?: null,':id'=>$id]);
        }
        $msg = 'Berhasil diupdate.';
      } else {
        if ($hasJenis && $hasKontak) {
          $stmt = $pdo->prepare("INSERT INTO rumah_ibadah_points (nama, jenis, kontak, radius_meter, latitude, longitude, address) VALUES (:n,:j,:k,:r,:lat,:lng,:a)");
          $stmt->execute([':n'=>$nama,':j'=>$jenis ?: 'Lainnya',':k'=>$kontak ?: null,':r'=>$radius,':lat'=>$lat,':lng'=>$lng,':a'=>$address ?: null]);
        } else {
          $stmt = $pdo->prepare("INSERT INTO rumah_ibadah_points (nama, radius_meter, latitude, longitude, address) VALUES (:n,:r,:lat,:lng,:a)");
          $stmt->execute([':n'=>$nama,':r'=>$radius,':lat'=>$lat,':lng'=>$lng,':a'=>$address ?: null]);
        }
        $msg = 'Berhasil ditambahkan.';
      }
    } catch (Exception $e) {
      $err = $e->getMessage();
    }
  }
}

$edit = null;
if (isset($_GET['edit'])) {
  $id = (int)$_GET['edit'];
  $stmt = $pdo->prepare("SELECT * FROM rumah_ibadah_points WHERE id=:id");
  $stmt->execute([':id' => $id]);
  $edit = $stmt->fetch() ?: null;
}

$rows = [];
try {
  $rows = $pdo->query("
    SELECT r.*,
           (SELECT COUNT(*) FROM pemukiman_miskin_points p WHERE p.rumah_ibadah_id=r.id) AS binaan_count
    FROM rumah_ibadah_points r
    ORDER BY r.id ASC
  ")->fetchAll();
} catch (Exception $e) {}

render_header('Admin Rumah Ibadah');
?>
<div class="admin-card">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
    <div style="font-weight:900;font-size:18px"><?= $edit ? 'Edit' : 'Tambah' ?> Rumah Ibadah</div>
    <a class="actions a secondary" href="rumah_ibadah.php" style="background:#e2e8f0;color:#0f172a;border-radius:12px;padding:8px 10px;font-weight:900;text-decoration:none">Reset</a>
  </div>
  <?php if ($msg): ?><div class="notice" style="margin-top:12px;border-color:#bbf7d0;background:#f0fdf4;color:#166534"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="notice" style="margin-top:12px;border-color:#fecaca;background:#fef2f2;color:#991b1b"><?= h($err) ?></div><?php endif; ?>

  <form method="post" style="margin-top:12px">
    <input type="hidden" name="id" value="<?= h($edit['id'] ?? 0) ?>">
    <div class="grid-2">
      <div>
        <label>Nama</label>
        <input name="nama" required value="<?= h($edit['nama'] ?? '') ?>">
      </div>
      <div>
        <label>Jenis</label>
        <select name="jenis">
          <?php foreach (['Masjid','Musholla','Gereja','Katolik','Pura','Vihara','Klenteng','Lainnya'] as $j): ?>
            <option value="<?= h($j) ?>" <?= (($edit['jenis'] ?? 'Lainnya') === $j ? 'selected' : '') ?>><?= h($j) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <label style="margin-top:10px;display:block;">Kontak</label>
    <input name="kontak" value="<?= h($edit['kontak'] ?? '') ?>">

    <div class="grid-2" style="margin-top:10px">
      <div>
        <label>Radius (meter)</label>
        <input name="radius_meter" type="number" min="1" step="1" required value="<?= h($edit['radius_meter'] ?? 300) ?>">
      </div>
      <div>
        <label>Alamat</label>
        <input name="address" value="<?= h($edit['address'] ?? '') ?>">
      </div>
    </div>

    <div class="grid-2" style="margin-top:10px">
      <div>
        <label>Latitude</label>
        <input name="latitude" required value="<?= h($edit['latitude'] ?? '') ?>">
      </div>
      <div>
        <label>Longitude</label>
        <input name="longitude" required value="<?= h($edit['longitude'] ?? '') ?>">
      </div>
    </div>
    <div class="modal-actions" style="margin-top:12px">
      <button class="btn btn-primary" type="submit"><?= $edit ? 'Update' : 'Tambah' ?></button>
      <a class="btn btn-secondary" href="../index.html" style="text-align:center;line-height:38px;text-decoration:none">Kembali ke WebGIS</a>
    </div>
  </form>
</div>

<div class="admin-card" style="margin-top:14px">
  <div style="font-weight:900;font-size:18px;margin-bottom:8px">Daftar Rumah Ibadah</div>
  <table>
    <thead>
      <tr>
        <th>ID</th><th>Nama</th><th>Jenis</th><th>Radius</th><th>Binaan</th><th>Koordinat</th><th>Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><?= h($r['nama'] ?? '') ?></td>
          <td><span class="tag"><?= h($r['jenis'] ?? 'Lainnya') ?></span></td>
          <td><?= (int)($r['radius_meter'] ?? 0) ?> m</td>
          <td><?= (int)($r['binaan_count'] ?? 0) ?></td>
          <td><?= h(($r['latitude'] ?? '') . ', ' . ($r['longitude'] ?? '')) ?></td>
          <td class="actions">
            <a class="secondary" href="rumah_ibadah.php?edit=<?= (int)$r['id'] ?>">Edit</a>
            <a href="rumah_ibadah.php?delete=<?= (int)$r['id'] ?>" onclick="return confirm('Hapus data ini?')">Hapus</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php render_footer(); ?>

