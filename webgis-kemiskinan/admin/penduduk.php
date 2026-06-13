<?php
require_once __DIR__ . '/_layout.php';
require_login(['admin','pengurus','pimpinan']);

$role = $_SESSION['role'];
$canEdit = in_array($role, ['admin','pengurus']);

$msg = ''; $err = '';

// DELETE
if ($canEdit && isset($_GET['delete'])) {
    $nik = $_GET['delete'];
    try {
        $pdo->prepare("DELETE FROM penduduk WHERE nik=:nik")->execute([':nik'=>$nik]);
        logAktivitas($pdo, (int)$_SESSION['user_id'], 'DELETE', 'penduduk', $nik);
        $msg = "Penduduk NIK $nik dihapus.";
    } catch(Exception $e) { $err = $e->getMessage(); }
}

// SAVE
if ($canEdit && isset($_POST['save'])) {
    $nik  = trim($_POST['nik'] ?? '');
    $nama = trim($_POST['nama_lengkap'] ?? '');
    $tgl  = $_POST['tanggal_lahir'] ?? '';
    $nikEdit = $_POST['nik_edit'] ?? '';

    if (!preg_match('/^\d{16}$/', $nik)) $err = 'NIK harus 16 digit.';
    elseif (strlen($nama) < 3) $err = 'Nama terlalu pendek.';
    else {
        try {
            $data = [
                ':kk'=>$_POST['id_keluarga']?:(null),
                ':nama'=>$nama, ':jk'=>$_POST['jenis_kelamin']??'L',
                ':tgl'=>$tgl, ':sk'=>$_POST['status_keluarga']??'anggota_lain',
                ':sp'=>$_POST['status_perkawinan']??'belum_kawin',
                ':sh'=>$_POST['status_hidup']??'hidup',
                ':ptk'=>$_POST['pendidikan_terakhir']??'tidak_sekolah',
                ':spd'=>$_POST['status_pendidikan']??'tidak_sekolah',
                ':pkr'=>$_POST['pekerjaan']??'' ?: null,
                ':pgn'=>(float)($_POST['penghasilan']??0),
                ':se'=>$_POST['status_ekonomi']??'rentan',
                ':kkh'=>$_POST['kondisi_kesehatan']??'sehat',
                ':ckkh'=>$_POST['catatan_kesehatan']??'' ?: null,
                ':tlp'=>$_POST['no_telp']??'' ?: null,
                ':almt'=>$_POST['alamat']??'' ?: null,
                ':lat'=>$_POST['latitude']??'' ?: null,
                ':lng'=>$_POST['longitude']??'' ?: null,
            ];
            if ($nikEdit) {
                $data[':nik'] = $nikEdit;
                $pdo->prepare("UPDATE penduduk SET id_keluarga=:kk,nama_lengkap=:nama,jenis_kelamin=:jk,tanggal_lahir=:tgl,
                    status_keluarga=:sk,status_perkawinan=:sp,status_hidup=:sh,pendidikan_terakhir=:ptk,status_pendidikan=:spd,
                    pekerjaan=:pkr,penghasilan=:pgn,status_ekonomi=:se,kondisi_kesehatan=:kkh,catatan_kesehatan=:ckkh,
                    no_telp=:tlp,alamat=:almt,latitude=:lat,longitude=:lng WHERE nik=:nik")->execute($data);
                $msg = "Data penduduk $nama diperbarui.";
            } else {
                $data[':nik'] = $nik;
                $pdo->prepare("INSERT INTO penduduk (nik,id_keluarga,nama_lengkap,jenis_kelamin,tanggal_lahir,
                    status_keluarga,status_perkawinan,status_hidup,pendidikan_terakhir,status_pendidikan,
                    pekerjaan,penghasilan,status_ekonomi,kondisi_kesehatan,catatan_kesehatan,no_telp,alamat,latitude,longitude)
                    VALUES(:nik,:kk,:nama,:jk,:tgl,:sk,:sp,:sh,:ptk,:spd,:pkr,:pgn,:se,:kkh,:ckkh,:tlp,:almt,:lat,:lng)")->execute($data);
                $msg = "Penduduk $nama berhasil ditambahkan.";
            }
            logAktivitas($pdo, (int)$_SESSION['user_id'], $nikEdit?'UPDATE':'CREATE', 'penduduk', $nik);
        } catch(Exception $e) { $err = $e->getMessage(); }
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM penduduk WHERE nik=:nik");
    $stmt->execute([':nik'=>$_GET['edit']]);
    $edit = $stmt->fetch() ?: null;
}

// List penduduk
$search = trim($_GET['s'] ?? '');
$se     = $_GET['se'] ?? '';
$where  = []; $params = [];
if ($search) { $where[] = '(p.nama_lengkap LIKE :q OR p.nik LIKE :q2)'; $params[':q']='%'.$search.'%'; $params[':q2']='%'.$search.'%'; }
if ($se)     { $where[] = 'p.status_ekonomi=:se'; $params[':se']=$se; }
$whereStr = $where ? 'WHERE '.implode(' AND ',$where) : '';

$rows = $pdo->prepare("
    SELECT p.nik,p.nama_lengkap,p.umur,p.jenis_kelamin,p.status_ekonomi,p.kondisi_kesehatan,
           p.pekerjaan,p.status_hidup,k.no_kk,ri.nama AS nama_ri,
           (SELECT COUNT(*) FROM histori_bantuan hb WHERE hb.nik=p.nik AND hb.status_penyaluran='disalurkan') AS jml_bantuan
    FROM penduduk p
    LEFT JOIN keluarga k ON k.id=p.id_keluarga
    LEFT JOIN rumah_ibadah ri ON ri.id=k.rumah_ibadah_id
    $whereStr
    ORDER BY CASE p.status_ekonomi WHEN 'miskin' THEN 1 WHEN 'rentan' THEN 2 ELSE 3 END, p.nama_lengkap
    LIMIT 300
");
$rows->execute($params);
$rows = $rows->fetchAll();

// Keluarga untuk dropdown
$kkList = $pdo->query("SELECT id, no_kk, nama_kk FROM keluarga ORDER BY nama_kk")->fetchAll();

render_header('Manajemen Penduduk');
?>
<div class="page-title">👥 Manajemen Penduduk</div>

<?php if ($msg): ?><div class="notice" style="border-color:#bbf7d0;background:#f0fdf4;color:#166534"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="notice" style="border-color:#fecaca;background:#fef2f2;color:#991b1b"><?= h($err) ?></div><?php endif; ?>

<?php if ($canEdit): ?>
<!-- FORM -->
<div class="card">
  <strong><?= $edit ? '✏️ Edit: '.h($edit['nama_lengkap']) : '➕ Tambah Penduduk' ?></strong>
  <form method="POST" style="margin-top:14px">
    <input type="hidden" name="nik_edit" value="<?= h($edit['nik']??'') ?>">
    <div class="form-grid">
      <div class="form-group">
        <label>NIK *</label>
        <input name="nik" maxlength="16" required value="<?= h($edit['nik']??'') ?>" <?= $edit?'readonly':'' ?>>
      </div>
      <div class="form-group">
        <label>Keluarga (KK)</label>
        <select name="id_keluarga">
          <option value="">— Pilih KK —</option>
          <?php foreach ($kkList as $kk): ?>
          <option value="<?= $kk['id'] ?>" <?= ($edit['id_keluarga']??'')==$kk['id']?'selected':'' ?>><?= h($kk['no_kk'].' — '.$kk['nama_kk']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group full">
        <label>Nama Lengkap *</label>
        <input name="nama_lengkap" required value="<?= h($edit['nama_lengkap']??'') ?>">
      </div>
      <div class="form-group">
        <label>Jenis Kelamin</label>
        <select name="jenis_kelamin">
          <option value="L" <?= ($edit['jenis_kelamin']??'L')==='L'?'selected':'' ?>>Laki-laki</option>
          <option value="P" <?= ($edit['jenis_kelamin']??'')==='P'?'selected':'' ?>>Perempuan</option>
        </select>
      </div>
      <div class="form-group">
        <label>Tanggal Lahir *</label>
        <input type="date" name="tanggal_lahir" required value="<?= h($edit['tanggal_lahir']??'') ?>" onchange="hitungUmur(this)">
      </div>
      <div class="form-group">
        <label>Status Keluarga</label>
        <select name="status_keluarga">
          <?php foreach (['kepala_keluarga'=>'Kepala Keluarga','istri'=>'Istri','anak'=>'Anak','menantu'=>'Menantu','orang_tua'=>'Orang Tua','anggota_lain'=>'Anggota Lain'] as $v=>$l): ?>
          <option value="<?= $v ?>" <?= ($edit['status_keluarga']??'')===$v?'selected':'' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Status Perkawinan</label>
        <select name="status_perkawinan">
          <?php foreach (['belum_kawin'=>'Belum Kawin','kawin'=>'Kawin','cerai_hidup'=>'Cerai Hidup','cerai_mati'=>'Cerai Mati'] as $v=>$l): ?>
          <option value="<?= $v ?>" <?= ($edit['status_perkawinan']??'')===$v?'selected':'' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Status Hidup</label>
        <select name="status_hidup">
          <option value="hidup" <?= ($edit['status_hidup']??'hidup')==='hidup'?'selected':'' ?>>Hidup</option>
          <option value="meninggal" <?= ($edit['status_hidup']??'')==='meninggal'?'selected':'' ?>>Meninggal</option>
        </select>
      </div>
      <div class="form-group">
        <label>Pendidikan Terakhir</label>
        <select name="pendidikan_terakhir" onchange="cekPendidikan()">
          <?php foreach (['tidak_sekolah','SD','SMP','SMA','SMK','D3','S1','S2','S3'] as $v): ?>
          <option value="<?= $v ?>" <?= ($edit['pendidikan_terakhir']??'')===$v?'selected':'' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Status Pendidikan</label>
        <select name="status_pendidikan" id="status_pendidikan" onchange="cekPendidikan()">
          <option value="sekolah" <?= ($edit['status_pendidikan']??'')==='sekolah'?'selected':'' ?>>Masih Sekolah</option>
          <option value="tidak_sekolah" <?= ($edit['status_pendidikan']??'')==='tidak_sekolah'?'selected':'' ?>>Tidak Sekolah</option>
          <option value="lulus" <?= ($edit['status_pendidikan']??'')==='lulus'?'selected':'' ?>>Lulus</option>
        </select>
      </div>
      <div class="form-group">
        <label>Status Ekonomi</label>
        <select name="status_ekonomi">
          <option value="miskin" <?= ($edit['status_ekonomi']??'')==='miskin'?'selected':'' ?>>Miskin</option>
          <option value="rentan" <?= ($edit['status_ekonomi']??'rentan')==='rentan'?'selected':'' ?>>Rentan</option>
          <option value="mampu" <?= ($edit['status_ekonomi']??'')==='mampu'?'selected':'' ?>>Mampu</option>
        </select>
      </div>
      <div class="form-group">
        <label>Kondisi Kesehatan</label>
        <select name="kondisi_kesehatan">
          <?php foreach (['sehat'=>'Sehat','sakit_ringan'=>'Sakit Ringan','sakit_parah'=>'Sakit Parah','disabilitas'=>'Disabilitas'] as $v=>$l): ?>
          <option value="<?= $v ?>" <?= ($edit['kondisi_kesehatan']??'sehat')===$v?'selected':'' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Pekerjaan</label>
        <input name="pekerjaan" value="<?= h($edit['pekerjaan']??'') ?>">
      </div>
      <div class="form-group">
        <label>Penghasilan / Bulan (Rp)</label>
        <input type="number" name="penghasilan" value="<?= h($edit['penghasilan']??0) ?>">
      </div>
      <div class="form-group full">
        <label>Alamat</label>
        <input name="alamat" value="<?= h($edit['alamat']??'') ?>">
      </div>
      <div class="form-group">
        <label>Latitude</label>
        <input name="latitude" value="<?= h($edit['latitude']??'') ?>">
      </div>
      <div class="form-group">
        <label>Longitude</label>
        <input name="longitude" value="<?= h($edit['longitude']??'') ?>">
      </div>
    </div>
    <div id="pendidikan-warn" style="display:none;background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.3);border-radius:8px;padding:8px 12px;font-size:12px;color:#f59e0b;margin-top:10px"></div>
    <div style="display:flex;gap:8px;margin-top:14px">
      <button type="submit" name="save" value="1" class="btn btn-primary">💾 <?= $edit ? 'Update' : 'Simpan' ?></button>
      <?php if ($edit): ?><a href="penduduk.php" class="btn btn-secondary">Batal</a><?php endif; ?>
    </div>
  </form>
</div>
<?php endif; ?>

<!-- TABEL -->
<div class="card" style="padding:0;overflow:hidden">
  <div style="padding:14px 16px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;border-bottom:1px solid var(--brd)">
    <form method="GET" style="display:flex;gap:8px;flex:1;min-width:200px">
      <input name="s" placeholder="Cari nama / NIK..." value="<?= h($search) ?>" style="flex:1">
      <select name="se" style="width:120px">
        <option value="">Semua Status</option>
        <option value="miskin" <?= $se==='miskin'?'selected':'' ?>>Miskin</option>
        <option value="rentan" <?= $se==='rentan'?'selected':'' ?>>Rentan</option>
        <option value="mampu" <?= $se==='mampu'?'selected':'' ?>>Mampu</option>
      </select>
      <button type="submit" class="btn btn-secondary">Cari</button>
      <a href="penduduk.php" class="btn btn-secondary">Reset</a>
    </form>
    <span style="font-size:12px;color:#94a3b8"><?= count($rows) ?> data</span>
  </div>
  <table>
    <thead><tr><th>NIK</th><th>Nama</th><th>Umur/JK</th><th>Status Ekonomi</th><th>Kesehatan</th><th>Bantuan</th><th>Rumah Ibadah</th><?php if($canEdit): ?><th>Aksi</th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td style="font-size:11px"><?= h($r['nik']) ?></td>
      <td><strong><?= h($r['nama_lengkap']) ?></strong><?= $r['status_hidup']==='meninggal'?'<br><span style="font-size:10px;color:#ef4444">†meninggal</span>':'' ?></td>
      <td><?= $r['umur'] ?>th / <?= $r['jenis_kelamin']==='L'?'♂':'♀' ?></td>
      <td><span class="tag <?= $r['status_ekonomi'] ?>"><?= h($r['status_ekonomi']) ?></span></td>
      <td style="font-size:11px"><?= h(str_replace('_',' ',$r['kondisi_kesehatan'])) ?></td>
      <td><?= $r['jml_bantuan'] > 0 ? "<span style='color:#22c55e'>✅ {$r['jml_bantuan']}x</span>" : "<span style='color:#ef4444'>⚠️ Belum</span>" ?></td>
      <td style="font-size:11px"><?= h($r['nama_ri'] ?? '-') ?></td>
      <?php if($canEdit): ?>
      <td class="actions">
        <a href="penduduk.php?edit=<?= h($r['nik']) ?>" class="btn btn-secondary btn-sm">Edit</a>
        <a href="bantuan.php?nik=<?= h($r['nik']) ?>&nama=<?= urlencode($r['nama_lengkap']) ?>" class="btn btn-success btn-sm">Bantu</a>
        <?php if ($_SESSION['role']==='admin'): ?>
        <a href="penduduk.php?delete=<?= h($r['nik']) ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus <?= h(addslashes($r['nama_lengkap'])) ?>?')">Hapus</a>
        <?php endif; ?>
      </td>
      <?php endif; ?>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?><tr><td colspan="8" style="text-align:center;padding:24px;color:#94a3b8">Tidak ada data</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<script>
function hitungUmur(el) {
    const tgl = el.value;
    if (!tgl) return;
    const umur = Math.floor((new Date() - new Date(tgl)) / (365.25*24*3600*1000));
    cekPendidikanUmur(umur);
}
function cekPendidikan() {
    const tgl = document.querySelector('[name=tanggal_lahir]').value;
    if (!tgl) return;
    const umur = Math.floor((new Date() - new Date(tgl)) / (365.25*24*3600*1000));
    cekPendidikanUmur(umur);
}
function cekPendidikanUmur(umur) {
    const status = document.getElementById('status_pendidikan').value;
    const warn   = document.getElementById('pendidikan-warn');
    let msg = '';
    if (umur>=7&&umur<=12&&status!=='sekolah')  msg = `⚠️ Anak usia ${umur} tahun seharusnya masih sekolah SD.`;
    if (umur>=13&&umur<=15&&status!=='sekolah') msg = `⚠️ Anak usia ${umur} tahun seharusnya masih sekolah SMP.`;
    if (umur>=16&&umur<=18&&status!=='sekolah') msg = `⚠️ Anak usia ${umur} tahun seharusnya masih sekolah SMA/SMK.`;
    warn.style.display = msg ? 'block' : 'none';
    warn.textContent = msg;
}
</script>

<?php render_footer(); ?>
