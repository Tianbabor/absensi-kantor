<?php require 'config.php';
need('admin');
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$dv = $_GET['divisi'] ?? '';
$end = min($to, date('Y-m-d'));
$hari = 0;
for ($t = strtotime($from); $t <= strtotime($end); $t += 86400) if (date('N', $t) < 6) $hari++;
$p = [$from, $to];
$w = '';
if ($dv !== '') {
    $w = ' AND k.id_divisi=?';
    $p[] = $dv;
}
$rows = q("SELECT k.nik,k.nama,d.nama_divisi,COALESCE(SUM(a.status='Hadir'),0) hadir,COALESCE(SUM(a.status='Terlambat'),0) telat,COALESCE(SUM(a.status='Izin'),0) izin FROM karyawan k LEFT JOIN divisi d ON d.id_divisi=k.id_divisi LEFT JOIN absensi a ON a.id_karyawan=k.id_karyawan AND a.tanggal BETWEEN ? AND ? WHERE k.status='aktif'$w GROUP BY k.id_karyawan ORDER BY k.nama", $p)->fetchAll();
foreach ($rows as &$r) {
    $r['alpha'] = max(0, $hari - $r['hadir'] - $r['telat'] - $r['izin']);
    $r['pct'] = $hari ? round(($r['hadir'] + $r['telat']) / $hari * 100) : 0;
}
unset($r);
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=laporan-absensi.csv');
    echo "\xEF\xBB\xBF";
    $o = fopen('php://output', 'w');
    fputcsv($o, ['NIK', 'Nama', 'Divisi', 'Hadir', 'Terlambat', 'Izin', 'Alpha', 'Persentase (%)']);
    foreach ($rows as $r) fputcsv($o, [$r['nik'], $r['nama'], $r['nama_divisi'], $r['hadir'], $r['telat'], $r['izin'], $r['alpha'], $r['pct']]);
    exit;
}
head('Laporan Absensi'); ?>
<form class="card form noprint" method="get">
    <div><label>Dari</label><input type="date" name="from" value="<?= e($from) ?>"></div>
    <div><label>Sampai</label><input type="date" name="to" value="<?= e($to) ?>"></div>
    <div><label>Divisi</label><select name="divisi"><?= str_replace('<option value="">-</option>', '<option value="">Semua divisi</option>', opts('divisi', 'id_divisi', 'nama_divisi', $dv)) ?></select></div>
    <div class="row"><button class="btn">Tampilkan</button><button class="btn c" name="export" value="1">Export Excel</button><button type="button" class="btn o" onclick="print()">Cetak / PDF</button></div>
</form>
<div class="card scroll">
    <p class="sub">Periode <?= e($from) ?> s/d <?= e($to) ?> &middot; <?= $hari ?> hari kerja (Senin-Jumat). Alpha dihitung dari hari kerja tanpa catatan absensi.</p>
    <table>
        <tr>
            <th>NIK</th>
            <th>Nama</th>
            <th>Divisi</th>
            <th>Hadir</th>
            <th>Terlambat</th>
            <th>Izin</th>
            <th>Alpha</th>
            <th>Persentase</th>
        </tr>
        <?php foreach ($rows as $r): ?><tr>
                <td><?= e($r['nik']) ?></td>
                <td><?= e($r['nama']) ?></td>
                <td><?= e($r['nama_divisi']) ?></td>
                <td><?= $r['hadir'] ?></td>
                <td><?= $r['telat'] ?></td>
                <td><?= $r['izin'] ?></td>
                <td><?= $r['alpha'] ?></td>
                <td><span class="bd <?= $r['pct'] >= 90 ? 'g' : ($r['pct'] >= 75 ? 'a' : 'c') ?>"><?= $r['pct'] ?>%</span></td>
            </tr><?php endforeach ?>
    </table>
</div>
<?php foot();
