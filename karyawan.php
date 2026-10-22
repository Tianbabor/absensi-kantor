<?php require 'config.php';
need('admin');
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    if (isset($_POST['del'])) {
      q('DELETE FROM karyawan WHERE id_karyawan=?', [$_POST['del']]);
      logact('Hapus karyawan');
    } else {
      $f = [$_POST['nik'], $_POST['nama'], $_POST['jenis_kelamin'], $_POST['tgl_lahir'] ?: null, $_POST['alamat'], $_POST['no_hp'], $_POST['email'], $_POST['id_divisi'] ?: null, $_POST['id_jabatan'] ?: null, $_POST['status']];
      if ($_POST['id']) {
        q('UPDATE karyawan SET nik=?,nama=?,jenis_kelamin=?,tgl_lahir=?,alamat=?,no_hp=?,email=?,id_divisi=?,id_jabatan=?,status=? WHERE id_karyawan=?', [...$f, $_POST['id']]);
        if ($_POST['password'] !== '') q('UPDATE users SET password=? WHERE id_karyawan=?', [password_hash($_POST['password'], PASSWORD_DEFAULT), $_POST['id']]);
      } else {
        q('INSERT INTO karyawan(nik,nama,jenis_kelamin,tgl_lahir,alamat,no_hp,email,id_divisi,id_jabatan,status) VALUES(?,?,?,?,?,?,?,?,?,?)', $f);
        q("INSERT INTO users(id_karyawan,username,password,role) VALUES(?,?,?,'karyawan')", [db()->lastInsertId(), $_POST['username'], password_hash($_POST['password'] ?: 'password', PASSWORD_DEFAULT)]);
      }
      logact('Simpan karyawan');
    }
  } catch (PDOException $x) {
    $msg = 'Gagal menyimpan: NIK atau username sudah dipakai.';
  }
  if (!$msg) {
    header('Location: karyawan.php');
    exit;
  }
}
$ed = isset($_GET['edit']) ? q('SELECT k.*,u.username FROM karyawan k LEFT JOIN users u ON u.id_karyawan=k.id_karyawan WHERE k.id_karyawan=?', [$_GET['edit']])->fetch() : [];
$v = fn($k) => e($ed[$k] ?? '');
$rows = q('SELECT k.*,d.nama_divisi,j.nama_jabatan,w.id_wajah FROM karyawan k LEFT JOIN divisi d ON d.id_divisi=k.id_divisi LEFT JOIN jabatan j ON j.id_jabatan=k.id_jabatan LEFT JOIN wajah_karyawan w ON w.id_karyawan=k.id_karyawan ORDER BY k.nik')->fetchAll();
head('Data Karyawan'); ?>
<?php if ($msg): ?><div class="msg c"><?= e($msg) ?></div><?php endif ?>
<form class="card form" method="post"><input type="hidden" name="id" value="<?= $v('id_karyawan') ?>">
  <div><label>NIK</label><input name="nik" value="<?= $v('nik') ?>" required></div>
  <div><label>Nama</label><input name="nama" value="<?= $v('nama') ?>" required></div>
  <div><label>Jenis kelamin</label><select name="jenis_kelamin">
      <option value="L">Laki-laki</option>
      <option value="P" <?= ($ed['jenis_kelamin'] ?? '') === 'P' ? ' selected' : '' ?>>Perempuan</option>
    </select></div>
  <div><label>Tanggal lahir</label><input type="date" name="tgl_lahir" value="<?= $v('tgl_lahir') ?>"></div>
  <div><label>No. HP</label><input name="no_hp" value="<?= $v('no_hp') ?>"></div>
  <div><label>Email</label><input type="email" name="email" value="<?= $v('email') ?>"></div>
  <div><label>Divisi</label><select name="id_divisi"><?= opts('divisi', 'id_divisi', 'nama_divisi', $ed['id_divisi'] ?? '') ?></select></div>
  <div><label>Jabatan</label><select name="id_jabatan"><?= opts('jabatan', 'id_jabatan', 'nama_jabatan', $ed['id_jabatan'] ?? '') ?></select></div>
  <div><label>Status</label><select name="status">
      <option value="aktif">Aktif</option>
      <option value="nonaktif" <?= ($ed['status'] ?? '') === 'nonaktif' ? ' selected' : '' ?>>Nonaktif</option>
    </select></div>
  <div><label>Username</label><input name="username" value="<?= $v('username') ?>" <?= $ed ? 'disabled' : 'required' ?>></div>
  <div><label>Password <?= $ed ? '(kosongkan jika tidak diubah)' : '(default: password)' ?></label><input name="password" type="password"></div>
  <div><label>Alamat</label><input name="alamat" value="<?= $v('alamat') ?>"></div>
  <div class="row" style="margin-top:12px"><button class="btn"><?= $ed ? 'Simpan perubahan' : 'Tambah karyawan' ?></button><?php if ($ed): ?><a class="btn o" href="karyawan.php">Batal</a><?php endif ?></div>
</form>
<div class="card scroll">
  <table>
    <tr>
      <th>Nama</th>
      <th>NIK</th>
      <th>Divisi</th>
      <th>Jabatan</th>
      <th>Wajah</th>
      <th>Status</th>
      <th>Aksi</th>
    </tr>
    <?php foreach ($rows as $r): ?><tr>
        <td><?= $r['id_wajah'] ? '<img class="ava" src="uploads/wajah/' . $r['id_karyawan'] . '.jpg" alt="">' : '<span class="ava">' . e(mb_substr($r['nama'], 0, 1)) . '</span>' ?><?= e($r['nama']) ?></td>
        <td><?= e($r['nik']) ?></td>
        <td><?= e($r['nama_divisi']) ?></td>
        <td><?= e($r['nama_jabatan']) ?></td>
        <td><?= $r['id_wajah'] ? '<span class="bd g">Terdaftar</span>' : '<span class="bd a">Belum</span>' ?></td>
        <td><?= badge($r['status']) ?></td>
        <td class="row"><a class="btn s o" href="?edit=<?= $r['id_karyawan'] ?>">Ubah</a>
          <form method="post" onsubmit="return confirm('Hapus karyawan ini?')"><button class="btn s c" name="del" value="<?= $r['id_karyawan'] ?>">Hapus</button></form>
        </td>
      </tr><?php endforeach ?>
  </table>
</div>
<?php foot();
