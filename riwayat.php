<?php require 'config.php';
need();
$u = me();
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$w = 'a.tanggal BETWEEN ? AND ?';
$p = [$from, $to];
if ($u['role'] !== 'admin') {
    $w .= ' AND a.id_karyawan=?';
    $p[] = $u['id_karyawan'];
} elseif (!empty($_GET['cari'])) {
    $w .= ' AND (k.nama LIKE ? OR k.nik LIKE ?)';
    $p[] = '%' . $_GET['cari'] . '%';
    $p[] = '%' . $_GET['cari'] . '%';
}
$rows = q("SELECT a.*,k.nama,l.nama_lokasi FROM absensi a JOIN karyawan k ON k.id_karyawan=a.id_karyawan LEFT JOIN lokasi_perusahaan l ON l.id_lokasi=a.id_lokasi WHERE $w ORDER BY a.tanggal DESC,a.jam_masuk DESC", $p)->fetchAll();
head('Riwayat Absensi'); ?>
<form class="card form" method="get">
    <div><label>Dari</label><input type="date" name="from" value="<?= e($from) ?>"></div>
    <div><label>Sampai</label><input type="date" name="to" value="<?= e($to) ?>"></div>
    <?php if ($u['role'] === 'admin'): ?><div><label>Cari nama / NIK</label><input name="cari" value="<?= e($_GET['cari'] ?? '') ?>"></div><?php endif ?>
    <div><button class="btn">Tampilkan</button></div>
</form>
<div class="card scroll">
    <table>
        <tr>
            <th>Tanggal</th>
            <th>Nama</th>
            <th>Masuk</th>
            <th>Pulang</th>
            <th>Status</th>
            <th>Lokasi</th>
            <th>Jarak</th>
        </tr>
        <?php foreach ($rows as $r): ?><tr>
                <td><?= e($r['tanggal']) ?></td>
                <td><?= e($r['nama']) ?></td>
                <td><?= substr($r['jam_masuk'], 0, 5) ?></td>
                <td><?= $r['jam_pulang'] ? substr($r['jam_pulang'], 0, 5) : '-' ?></td>
                <td><?= badge($r['status']) ?></td>
                <td><?= e($r['nama_lokasi']) ?></td>
                <td><?= round($r['jarak_masuk']) ?> m</td>
            </tr><?php endforeach ?>
        <?php if (!$rows): ?><tr>
                <td colspan="7">Belum ada data pada periode ini.</td>
            </tr><?php endif ?>
    </table>
</div>
<?php foot();
