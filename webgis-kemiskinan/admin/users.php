<?php
require_once __DIR__ . '/_layout.php';
require_login(['admin']);

$msg = ''; $err = '';

// TOGGLE AKTIF
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    if ($id !== (int)$_SESSION['user_id']) {
        try {
            $pdo->prepare("UPDATE users SET aktif = 1 - aktif WHERE id=:id")->execute([':id'=>$id]);
            logAktivitas($pdo, (int)$_SESSION['user_id'], 'UPDATE', 'users', (string)$id);
            $msg = "Status user #$id diperbarui.";
        } catch(Exception $e) { $err = $e->getMessage(); }
    } else { $err = 'Tidak dapat mengubah status akun sendiri.'; }
}

// RESET PASSWORD
if (isset($_POST['reset_pw'])) {
    $id = (int)($_POST['user_id'] ?? 0);
    $pw = $_POST['new_password'] ?? '';
    if (strlen($pw) < 6) $err = 'Password minimal 6 karakter.';
    else {
        $hash = password_hash($pw, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password_hash=:h WHERE id=:id")->execute([':h'=>$hash,':id'=>$id]);
        logAktivitas($pdo, (int)$_SESSION['user_id'], 'UPDATE', 'users', (string)$id, null, ['action'=>'reset_password']);
        $msg = "Password user #$id berhasil direset.";
    }
}

// SAVE USER BARU
if (isset($_POST['save'])) {
    $uname = trim($_POST['username'] ?? '');
    $nama  = trim($_POST['nama'] ?? '');
    $pw    = $_POST['password'] ?? '';
    $idEdit = (int)($_POST['id_edit'] ?? 0);
    if (strlen($uname) < 3) $err = 'Username minimal 3 karakter.';
    elseif (strlen($nama) < 3) $err = 'Nama minimal 3 karakter.';
    elseif (!$idEdit && strlen($pw) < 6) $err = 'Password minimal 6 karakter.';
    else {
        try {
            if ($idEdit) {
                $pdo->prepare("UPDATE users SET nama=:nama,role_id=:rid,email=:em,no_telp=:tlp,rumah_ibadah_id=:ri WHERE id=:id")
                    ->execute([':nama'=>$nama,':rid'=>(int)$_POST['role_id'],':em'=>$_POST['email']??'' ?: null,
                               ':tlp'=>$_POST['no_telp']??'' ?: null,':ri'=>$_POST['rumah_ibadah_id']??'' ?: null,':id'=>$idEdit]);
                $msg = "User $nama diperbarui.";
                logAktivitas($pdo, (int)$_SESSION['user_id'], 'UPDATE', 'users', (string)$idEdit);
            } else {
                $hash = password_hash($pw, PASSWORD_BCRYPT);
                $pdo->prepare("INSERT INTO users (role_id,nama,username,email,password_hash,no_telp,rumah_ibadah_id)
                    VALUES(:rid,:nama,:uname,:em,:hash,:tlp,:ri)")
                    ->execute([':rid'=>(int)$_POST['role_id'],':nama'=>$nama,':uname'=>$uname,
                               ':em'=>$_POST['email']??'' ?: null,':hash'=>$hash,
                               ':tlp'=>$_POST['no_telp']??'' ?: null,':ri'=>$_POST['rumah_ibadah_id']??'' ?: null]);
                $msg = "User $uname berhasil dibuat.";
                logAktivitas($pdo, (int)$_SESSION['user_id'], 'CREATE', 'users', (string)$pdo->lastInsertId());
            }
        } catch(Exception $e) { $err = $e->getMessage(); }
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM users WHERE id=:id");
    $s->execute([':id'=>(int)$_GET['edit']]);
    $edit = $s->fetch() ?: null;
}

$rows = $pdo->query("
    SELECT u.*, r.nama AS role_nama, ri.nama AS nama_ri
    FROM users u
    JOIN roles r ON r.id=u.role_id
    LEFT JOIN rumah_ibadah ri ON ri.id=u.rumah_ibadah_id
    ORDER BY u.role_id, u.nama
")->fetchAll();

$roles  = $pdo->query("SELECT * FROM roles ORDER BY id")->fetchAll();
$riList = $pdo->query("SELECT id,nama FROM rumah_ibadah ORDER BY nama")->fetchAll();

render_header('Manajemen User');
?>
<div class="page-title">👥 Manajemen User</div>

<?php if ($msg): ?><div class="notice" style="border-color:#bbf7d0;background:#f0fdf4;color:#166534"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="notice" style="border-color:#fecaca;background:#fef2f2;color:#991b1b"><?= h($err) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1.8fr;gap:16px">
<div>
  <div class="card">
    <strong><?= $edit ? '✏️ Edit User' : '➕ Tambah User' ?></strong><br><br>
    <form method="POST">
      <?php if ($edit): ?><input type="hidden" name="id_edit" value="<?= $edit['id'] ?>"><?php endif; ?>
      <div class="form-group" style="margin-bottom:10px"><label>Username <?= $edit ? '' : '*' ?></label>
        <input name="username" <?= $edit ? 'disabled value="'.h($edit['username'] ?? '').'"' : 'required' ?>></div>
      <div class="form-group" style="margin-bottom:10px"><label>Nama Lengkap *</label>
        <input name="nama" required value="<?= h($edit['nama'] ?? '') ?>"></div>
      <?php if (!$edit): ?>
      <div class="form-group" style="margin-bottom:10px"><label>Password * (min 6 karakter)</label>
        <input name="password" type="password" required minlength="6"></div>
      <?php endif; ?>
      <div class="form-group" style="margin-bottom:10px"><label>Role</label>
        <select name="role_id">
          <?php foreach ($roles as $r): ?>
          <option value="<?= $r['id'] ?>" <?= ($edit['role_id']??4)==$r['id']?'selected':'' ?>><?= h($r['label']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="form-group" style="margin-bottom:10px"><label>Email</label>
        <input name="email" type="email" value="<?= h($edit['email'] ?? '') ?>"></div>
      <div class="form-group" style="margin-bottom:10px"><label>No Telp</label>
        <input name="no_telp" value="<?= h($edit['no_telp'] ?? '') ?>"></div>
      <div class="form-group" style="margin-bottom:10px"><label>Rumah Ibadah (untuk Pengurus)</label>
        <select name="rumah_ibadah_id">
          <option value="">— Tidak ada —</option>
          <?php foreach ($riList as $ri): ?>
          <option value="<?= $ri['id'] ?>" <?= ($edit['rumah_ibadah_id']??'')==$ri['id']?'selected':'' ?>><?= h($ri['nama']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <br>
      <button type="submit" name="save" class="btn btn-primary"><?= $edit ? '💾 Update' : '➕ Buat' ?></button>
      <?php if ($edit): ?><a href="users.php" class="btn btn-secondary">Batal</a><?php endif; ?>
    </form>
  </div>

  <?php if ($edit): ?>
  <div class="card" style="margin-top:0">
    <strong>🔑 Reset Password</strong><br><br>
    <form method="POST">
      <input type="hidden" name="user_id" value="<?= $edit['id'] ?>">
      <div class="form-group" style="margin-bottom:10px"><label>Password Baru *</label>
        <input name="new_password" type="password" required minlength="6" placeholder="min 6 karakter"></div>
      <button type="submit" name="reset_pw" class="btn btn-danger">🔑 Reset</button>
    </form>
  </div>
  <?php endif; ?>
</div>

<div class="card" style="padding:0;overflow:hidden">
  <table>
    <thead><tr><th>Nama</th><th>Username</th><th>Role</th><th>Rumah Ibadah</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
    <tr <?= !$r['aktif'] ? 'style="opacity:.5"' : '' ?>>
      <td><strong><?= h($r['nama']) ?></strong><br><span style="font-size:10px;color:#94a3b8"><?= h($r['email']??'') ?></span></td>
      <td style="font-size:12px"><?= h($r['username']) ?></td>
      <td><span class="tag <?= $r['role_nama']==='admin'?'darurat':($r['role_nama']==='pengurus'?'diproses':'pending') ?>"><?= h($r['role_nama']) ?></span></td>
      <td style="font-size:11px"><?= h($r['nama_ri'] ?? '-') ?></td>
      <td><?= $r['aktif'] ? '<span class="tag selesai">Aktif</span>' : '<span class="tag darurat">Nonaktif</span>' ?></td>
      <td class="actions">
        <a href="?edit=<?= $r['id'] ?>" class="btn btn-secondary btn-sm">✏️</a>
        <?php if ($r['id'] != $_SESSION['user_id']): ?>
        <a href="?toggle=<?= $r['id'] ?>" class="btn <?= $r['aktif']?'btn-danger':'btn-success' ?> btn-sm" onclick="return confirm('Ubah status user ini?')">
          <?= $r['aktif'] ? '🚫' : '✅' ?>
        </a>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
</div>
<?php render_footer(); ?>
