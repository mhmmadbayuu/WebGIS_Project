<?php
require_once __DIR__ . '/_layout.php';
require_login();

function csv_cell($v) {
  $s = (string)($v ?? '');
  $s = str_replace('"', '""', $s);
  return '"' . $s . '"';
}

// Export CSV
if (isset($_GET['export'])) {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="penduduk_miskin.csv"');
  $cols = $pdo->query("SHOW COLUMNS FROM pemukiman_miskin_points")->fetchAll(PDO::FETCH_COLUMN, 0);
  $hasNew = in_array('kk_nama', $cols, true);
  $out = fopen('php://output', 'w');
  if ($hasNew) {
    fputcsv($out, ['id','kk_nama','nik','jumlah_anggota','lat','lng','alamat','kelurahan','kecamatan','status_bantuan','jenis_bantuan','tanggal_bantuan','rumah_ibadah_id','jarak_meter']);
    $rows = $pdo->query("SELECT id, kk_nama, nik, jumlah_anggota, latitude, longitude, address, kelurahan, kecamatan, status_bantuan, jenis_bantuan, tanggal_bantuan, rumah_ibadah_id, jarak_meter FROM pemukiman_miskin_points ORDER BY id ASC")->fetchAll();
    foreach ($rows as $r) {
      fputcsv($out, [
        $r['id'], $r['kk_nama'], $r['nik'], $r['jumlah_anggota'],
        $r['latitude'], $r['longitude'], $r['address'], $r['kelurahan'], $r['kecamatan'],
        $r['status_bantuan'], $r['jenis_bantuan'], $r['tanggal_bantuan'],
        $r['rumah_ibadah_id'], $r['jarak_meter']
      ]);
    }
  } else {
    fputcsv($out, ['id','nama','lat','lng','alamat','rumah_ibadah_id']);
    $rows = $pdo->query("SELECT id, nama, latitude, longitude, address, rumah_ibadah_id FROM pemukiman_miskin_points ORDER BY id ASC")->fetchAll();
    foreach ($rows as $r) fputcsv($out, [$r['id'],$r['nama'],$r['latitude'],$r['longitude'],$r['address'],$r['rumah_ibadah_id']]);
  }
  fclose($out);
  exit;
}

$msg = '';
$err = '';

// Delete
if (isset($_GET['delete'])) {
  $id = (int)$_GET['delete'];
  try {
    $stmt = $pdo->prepare("DELETE FROM pemukiman_miskin_points WHERE id=:id");
    $stmt->execute([':id' => $id]);
    $msg = 'Berhasil dihapus.';
  } catch (Exception $e) {
    $err = $e->getMessage();
  }
}

// Import CSV (minimal)
if (isset($_POST['import_csv'])) {
  if (!isset($_FILES['csv_file']) || ($_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $err = 'CSV tidak ditemukan.';
  } else {
    try {
      $cols = $pdo->query("SHOW COLUMNS FROM pemukiman_miskin_points")->fetchAll(PDO::FETCH_COLUMN, 0);
      $hasNew = in_array('kk_nama', $cols, true);
      $fp = fopen($_FILES['csv_file']['tmp_name'], 'r');
      $header = fgetcsv($fp);
      if (!$header) throw new Exception('Header CSV kosong.');
      $map = [];
      foreach ($header as $i => $hcol) $map[strtolower(trim($hcol))] = $i;
      $pdo->beginTransaction();
      $ins = $hasNew
        ? $pdo->prepare("INSERT INTO pemukiman_miskin_points (kk_nama, nik, jumlah_anggota, latitude, longitude, address, kelurahan, kecamatan, status_bantuan, jenis_bantuan, tanggal_bantuan) VALUES (:kk,:nik,:j,:lat,:lng,:a,:kel,:kec,:s,:jb,:tb)")
        : $pdo->prepare("INSERT INTO pemukiman_miskin_points (nama, latitude, longitude, address) VALUES (:kk,:lat,:lng,:a)");
      $n = 0;
      while (($row = fgetcsv($fp)) !== false) {
        $kk = $row[$map['kk_nama'] ?? $map['nama'] ?? -1] ?? '';
        $lat = $row[$map['lat'] ?? $map['latitude'] ?? -1] ?? '';
        $lng = $row[$map['lng'] ?? $map['longitude'] ?? -1] ?? '';
        if (trim($kk) === '' || trim($lat) === '' || trim($lng) === '') continue;
        if ($hasNew) {
          $ins->execute([
            ':kk' => $kk,
            ':nik' => ($row[$map['nik'] ?? -1] ?? '') ?: null,
            ':j' => (int)($row[$map['jumlah_anggota'] ?? -1] ?? 0),
            ':lat' => $lat,
            ':lng' => $lng,
            ':a' => ($row[$map['alamat'] ?? $map['address'] ?? -1] ?? '') ?: null,
            ':kel' => ($row[$map['kelurahan'] ?? -1] ?? '') ?: null,
            ':kec' => ($row[$map['kecamatan'] ?? -1] ?? '') ?: null,
            ':s' => ($row[$map['status_bantuan'] ?? -1] ?? 'Belum dibantu') ?: 'Belum dibantu',
            ':jb' => ($row[$map['jenis_bantuan'] ?? -1] ?? '') ?: null,
            ':tb' => ($row[$map['tanggal_bantuan'] ?? -1] ?? '') ?: null
          ]);
        } else {
          $ins->execute([
            ':kk' => $kk,
            ':lat' => $lat,
            ':lng' => $lng,
            ':a' => ($row[$map['alamat'] ?? $map['address'] ?? -1] ?? '') ?: null
          ]);
        }
        $n++;
      }
      $pdo->commit();
      fclose($fp);
      $msg = "Import selesai: $n baris.";
    } catch (Exception $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $err = $e->getMessage();
    }
  }
}

// Simple edit/create (without anggota details)
if (isset($_POST['save_penduduk'])) {
  $id = (int)($_POST['id'] ?? 0);
  $kk = trim($_POST['kk_nama'] ?? '');
  $nik = trim($_POST['nik'] ?? '');
  $jml = (int)($_POST['jumlah_anggota'] ?? 0);
  $lat = $_POST['latitude'] ?? null;
  $lng = $_POST['longitude'] ?? null;
  $addr = trim($_POST['address'] ?? '');
  $kel = trim($_POST['kelurahan'] ?? '');
  $kec = trim($_POST['kecamatan'] ?? '');
  $status = trim($_POST['status_bantuan'] ?? 'Belum dibantu');
  $jb = trim($_POST['jenis_bantuan'] ?? '');
  $tb = trim($_POST['tanggal_bantuan'] ?? '');

  if ($kk === '' || $lat === null || $lng === null) $err = 'Data belum lengkap (nama/lat/lng).';
  else {
    try {
      $cols = $pdo->query("SHOW COLUMNS FROM pemukiman_miskin_points")->fetchAll(PDO::FETCH_COLUMN, 0);
      $hasNew = in_array('kk_nama', $cols, true);
      if (!$hasNew) throw new Exception('Schema belum mendukung fitur penduduk detail. Jalankan schema terbaru.');

      if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE pemukiman_miskin_points SET kk_nama=:kk, nik=:nik, jumlah_anggota=:j, latitude=:lat, longitude=:lng, address=:a, kelurahan=:kel, kecamatan=:kec, status_bantuan=:s, jenis_bantuan=:jb, tanggal_bantuan=:tb WHERE id=:id");
        $stmt->execute([':kk'=>$kk,':nik'=>$nik ?: null,':j'=>$jml,':lat'=>$lat,':lng'=>$lng,':a'=>$addr ?: null,':kel'=>$kel ?: null,':kec'=>$kec ?: null,':s'=>$status ?: 'Belum dibantu',':jb'=>$jb ?: null,':tb'=>$tb ?: null,':id'=>$id]);
        $msg = 'Berhasil diupdate.';
      } else {
        $stmt = $pdo->prepare("INSERT INTO pemukiman_miskin_points (kk_nama, nik, jumlah_anggota, latitude, longitude, address, kelurahan, kecamatan, status_bantuan, jenis_bantuan, tanggal_bantuan) VALUES (:kk,:nik,:j,:lat,:lng,:a,:kel,:kec,:s,:jb,:tb)");
        $stmt->execute([':kk'=>$kk,':nik'=>$nik ?: null,':j'=>$jml,':lat'=>$lat,':lng'=>$lng,':a'=>$addr ?: null,':kel'=>$kel ?: null,':kec'=>$kec ?: null,':s'=>$status ?: 'Belum dibantu',':jb'=>$jb ?: null,':tb'=>$tb ?: null]);
        $msg = 'Berhasil ditambahkan.';
      }
    } catch (Exception $e) { $err = $e->getMessage(); }
  }
}

$edit = null;
if (isset($_GET['edit'])) {
  $id = (int)$_GET['edit'];
  $stmt = $pdo->prepare("SELECT * FROM pemukiman_miskin_points WHERE id=:id");
  $stmt->execute([':id' => $id]);
  $edit = $stmt->fetch() ?: null;
}

$rows = [];
try {
  $cols = $pdo->query("SHOW COLUMNS FROM pemukiman_miskin_points")->fetchAll(PDO::FETCH_COLUMN, 0);
  $hasNew = in_array('kk_nama', $cols, true);
  if ($hasNew) {
    $rows = $pdo->query("
      SELECT p.*, r.nama AS rumah_ibadah_nama
      FROM pemukiman_miskin_points p
      LEFT JOIN rumah_ibadah_points r ON r.id=p.rumah_ibadah_id
      ORDER BY p.id ASC
    ")->fetchAll();
  } else {
    $rows = $pdo->query("SELECT * FROM pemukiman_miskin_points ORDER BY id ASC")->fetchAll();
  }
} catch (Exception $e) {}

render_header('Admin Penduduk Miskin');
?>
<div class="admin-card">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
    <div style="font-weight:900;font-size:18px"><?= $edit ? 'Edit' : 'Tambah' ?> Penduduk Miskin</div>
    <div class="actions">
      <a class="secondary" href="penduduk_miskin.php">Reset</a>
      <a href="penduduk_miskin.php?export=1">Export CSV</a>
    </div>
  </div>
  <?php if ($msg): ?><div class="notice" style="margin-top:12px;border-color:#bbf7d0;background:#f0fdf4;color:#166534"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="notice" style="margin-top:12px;border-color:#fecaca;background:#fef2f2;color:#991b1b"><?= h($err) ?></div><?php endif; ?>

  <form method="post" style="margin-top:12px">
    <input type="hidden" name="id" value="<?= h($edit['id'] ?? 0) ?>">
    <div class="grid-2">
      <div>
        <label>Nama Kepala Keluarga</label>
        <input name="kk_nama" required value="<?= h($edit['kk_nama'] ?? '') ?>">
      </div>
      <div>
        <label>NIK (opsional)</label>
        <input name="nik" value="<?= h($edit['nik'] ?? '') ?>">
      </div>
    </div>
    <div class="grid-2" style="margin-top:10px">
      <div>
        <label>Jumlah Anggota</label>
        <input name="jumlah_anggota" type="number" min="0" step="1" value="<?= h($edit['jumlah_anggota'] ?? 0) ?>">
      </div>
      <div>
        <label>Status Bantuan</label>
        <select name="status_bantuan">
          <?php foreach (['Belum dibantu','Menunggu verifikasi','Sudah dibantu'] as $s): ?>
            <option value="<?= h($s) ?>" <?= (($edit['status_bantuan'] ?? 'Belum dibantu') === $s ? 'selected' : '') ?>><?= h($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="grid-2" style="margin-top:10px">
      <div>
        <label>Jenis Bantuan</label>
        <input name="jenis_bantuan" value="<?= h($edit['jenis_bantuan'] ?? '') ?>">
      </div>
      <div>
        <label>Tanggal Bantuan</label>
        <input name="tanggal_bantuan" type="date" value="<?= h($edit['tanggal_bantuan'] ?? '') ?>">
      </div>
    </div>

    <label style="margin-top:10px;display:block;">Alamat</label>
    <input name="address" value="<?= h($edit['address'] ?? '') ?>">

    <div class="grid-2" style="margin-top:10px">
      <div>
        <label>Kelurahan</label>
        <input name="kelurahan" value="<?= h($edit['kelurahan'] ?? '') ?>">
      </div>
      <div>
        <label>Kecamatan</label>
        <input name="kecamatan" value="<?= h($edit['kecamatan'] ?? '') ?>">
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
      <button class="btn btn-primary" name="save_penduduk" value="1" type="submit"><?= $edit ? 'Update' : 'Tambah' ?></button>
      <a class="btn btn-secondary" href="../index.html" style="text-align:center;line-height:38px;text-decoration:none">Kembali ke WebGIS</a>
    </div>
  </form>
</div>

<div class="admin-card" style="margin-top:14px">
  <div style="font-weight:900;font-size:18px;margin-bottom:8px">Import CSV</div>
  <form method="post" enctype="multipart/form-data">
    <input type="file" name="csv_file" accept=".csv" required>
    <button class="btn btn-secondary" name="import_csv" value="1" type="submit" style="margin-top:10px">Import</button>
    <div style="margin-top:8px;color:#475569;font-weight:800">Kolom minimal: `kk_nama`/`nama`, `lat`, `lng`.</div>
  </form>
</div>

<div class="admin-card" style="margin-top:14px">
  <div style="font-weight:900;font-size:18px;margin-bottom:8px">Daftar Penduduk Miskin</div>
  <table>
    <thead>
      <tr>
        <th>ID</th><th>Kepala Keluarga</th><th>Status</th><th>Rumah Ibadah</th><th>Koordinat</th><th>Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><?= h($r['kk_nama'] ?? $r['nama'] ?? '') ?></td>
          <td><span class="tag"><?= h($r['status_bantuan'] ?? '-') ?></span></td>
          <td><?= h($r['rumah_ibadah_nama'] ?? '-') ?></td>
          <td><?= h(($r['latitude'] ?? '') . ', ' . ($r['longitude'] ?? '')) ?></td>
          <td class="actions">
            <a class="secondary" href="penduduk_miskin.php?edit=<?= (int)$r['id'] ?>">Edit</a>
            <a href="penduduk_miskin.php?delete=<?= (int)$r['id'] ?>" onclick="return confirm('Hapus data ini?')">Hapus</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php render_footer(); ?>

