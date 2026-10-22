<?php require 'config.php';
need('admin');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['del'])) q('DELETE FROM lokasi_perusahaan WHERE id_lokasi=?', [$_POST['del']]);
    else {
        $f = [$_POST['nama_lokasi'], (float)$_POST['latitude'], (float)$_POST['longitude'], (int)$_POST['radius_meter'], $_POST['alamat'], $_POST['status']];
        if ($_POST['id']) q('UPDATE lokasi_perusahaan SET nama_lokasi=?,latitude=?,longitude=?,radius_meter=?,alamat=?,status=? WHERE id_lokasi=?', [...$f, $_POST['id']]);
        else q('INSERT INTO lokasi_perusahaan(nama_lokasi,latitude,longitude,radius_meter,alamat,status) VALUES(?,?,?,?,?,?)', $f);
    }
    logact('Ubah lokasi');
    header('Location: lokasi.php');
    exit;
}
$ed = isset($_GET['edit']) ? q('SELECT * FROM lokasi_perusahaan WHERE id_lokasi=?', [$_GET['edit']])->fetch() : [];
$v = fn($k) => e($ed[$k] ?? '');
head('Lokasi Perusahaan'); ?>
<p class="sub">Karyawan hanya bisa absen di dalam radius lokasi aktif.</p>
<form class="card form" method="post"><input type="hidden" name="id" value="<?= $v('id_lokasi') ?>">
    <div><label>Nama lokasi</label><input name="nama_lokasi" value="<?= $v('nama_lokasi') ?>" required></div>
    <div><label>Latitude</label><input id="lat" name="latitude" value="<?= $v('latitude') ?>" required></div>
    <div><label>Longitude</label><input id="lng" name="longitude" value="<?= $v('longitude') ?>" required></div>
    <div><label>Radius (meter)</label><input type="number" name="radius_meter" value="<?= $v('radius_meter') ?: 100 ?>" required></div>
    <div><label>Alamat</label><input name="alamat" value="<?= $v('alamat') ?>"></div>
    <div><label>Status</label><select name="status">
            <option value="aktif">Aktif</option>
            <option value="nonaktif" <?= ($ed['status'] ?? '') === 'nonaktif' ? ' selected' : '' ?>>Nonaktif</option>
        </select></div>
    <div class="row" style="margin-top:12px"><button class="btn">Simpan</button><button type="button" class="btn o" onclick="navigator.geolocation.getCurrentPosition(p=>{lat.value=p.coords.latitude;lng.value=p.coords.longitude},()=>alert('GPS tidak aktif'))">Pakai lokasi saya</button></div>
</form>
<div class="card scroll">
    <table>
        <tr>
            <th>Nama</th>
            <th>Latitude</th>
            <th>Longitude</th>
            <th>Radius</th>
            <th>Status</th>
            <th>Aksi</th>
        </tr>
        <?php foreach (q('SELECT * FROM lokasi_perusahaan')->fetchAll() as $r): ?><tr>
                <td><?= e($r['nama_lokasi']) ?></td>
                <td><?= $r['latitude'] ?></td>
                <td><?= $r['longitude'] ?></td>
                <td><?= $r['radius_meter'] ?> m</td>
                <td><?= badge($r['status']) ?></td>
                <td class="row"><a class="btn s o" href="?edit=<?= $r['id_lokasi'] ?>">Ubah</a>
                    <form method="post" onsubmit="return confirm('Hapus lokasi?')"><button class="btn s c" name="del" value="<?= $r['id_lokasi'] ?>">Hapus</button></form>
                </td>
            </tr><?php endforeach ?>
    </table>
</div>
<?php foot();
