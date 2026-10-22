<?php require 'config.php';
need();
$u = me();
if ($u['role'] === 'admin') {
    $n = fn($s) => (int)q($s)->fetch()['c'];
    $tot = $n("SELECT COUNT(*) c FROM karyawan WHERE status='aktif'");
    $hd = $n("SELECT COUNT(*) c FROM absensi WHERE tanggal=CURDATE()");
    $all = $n("SELECT COUNT(*) c FROM absensi");
    $days = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i day"));
        $days[] = [date('d/m', strtotime($d)), (int)q('SELECT COUNT(*) c FROM absensi WHERE tanggal=?', [$d])->fetch()['c']];
    }
    $max = max(1, ...array_column($days, 1));
    $rec = q("SELECT a.*,k.nama FROM absensi a JOIN karyawan k USING(id_karyawan) ORDER BY a.tanggal DESC,a.jam_masuk DESC LIMIT 6")->fetchAll();
    head('Dashboard Admin', 'Halo, ' . $u['nama']); ?>
    <div class="grid g4 pull">
        <div class="card stat"><span class="chip" style="background:var(--ps);color:var(--p)">K</span>
            <div><b><?= $tot ?></b>Total karyawan</div>
        </div>
        <div class="card stat"><span class="chip" style="background:var(--gs);color:var(--g)">H</span>
            <div><b><?= $hd ?></b>Hadir hari ini</div>
        </div>
        <div class="card stat"><span class="chip" style="background:var(--cs);color:var(--c)">T</span>
            <div><b><?= max(0, $tot - $hd) ?></b>Tidak hadir</div>
        </div>
        <div class="card stat"><span class="chip" style="background:var(--as);color:#8a5f00">A</span>
            <div><b><?= $all ?></b>Total absensi</div>
        </div>
    </div>
    <div class="grid g2" style="margin-top:20px">
        <div class="card">
            <h2>Kehadiran 7 hari terakhir</h2>
            <div class="bars"><?php foreach ($days as [$l, $c]): ?><div><small><?= $c ?></small><i style="height:<?= round($c / $max * 100) ?>%"></i><span><?= $l ?></span></div><?php endforeach ?></div>
        </div>
        <div class="card">
            <h2>Absensi terbaru</h2>
            <div class="scroll">
                <table>
                    <tr>
                        <th>Nama</th>
                        <th>Tanggal</th>
                        <th>Masuk</th>
                        <th>Status</th>
                    </tr>
                    <?php foreach ($rec as $r): ?><tr>
                            <td><?= e($r['nama']) ?></td>
                            <td><?= e($r['tanggal']) ?></td>
                            <td><?= substr($r['jam_masuk'], 0, 5) ?></td>
                            <td><?= badge($r['status']) ?></td>
                        </tr><?php endforeach ?>
                </table>
            </div>
        </div>
    </div>
<?php foot();
    exit;
}
$k = q('SELECT k.*,d.nama_divisi,j.nama_jabatan FROM karyawan k LEFT JOIN divisi d ON d.id_divisi=k.id_divisi LEFT JOIN jabatan j ON j.id_jabatan=k.id_jabatan WHERE k.id_karyawan=?', [$u['id_karyawan']])->fetch();
$a = q('SELECT * FROM absensi WHERE id_karyawan=? AND tanggal=CURDATE()', [$u['id_karyawan']])->fetch();
$w = q('SELECT 1 FROM wajah_karyawan WHERE id_karyawan=?', [$u['id_karyawan']])->fetch();
head('Beranda', 'Halo, ' . $k['nama']); ?>
<div class="card pull">
    <h2>Status absensi hari ini</h2>
    <?php if ($a): ?>
        <p><?= badge($a['status']) ?></p>
        <p class="sub" style="margin:10px 0 18px">Masuk <?= substr($a['jam_masuk'], 0, 5) ?><?= $a['jam_pulang'] ? ', pulang ' . substr($a['jam_pulang'], 0, 5) : ', belum pulang' ?></p>
    <?php else: ?>
        <p><span class="bd c">Belum absen</span></p>
        <p class="sub" style="margin:10px 0 18px">Anda belum absen masuk hari ini.</p>
    <?php endif ?>
    <a class="btn full" href="absen.php">Mulai absen</a>
</div>
<div class="g3">
    <div class="card mini"><small>NIK</small><b><?= e($k['nik']) ?></b></div>
    <div class="card mini"><small>Divisi</small><b><?= e($k['nama_divisi']) ?></b></div>
    <div class="card mini"><small>Jabatan</small><b><?= e($k['nama_jabatan']) ?></b></div>
</div>
<?php if (!$w): ?><div class="msg n">Wajah Anda belum diregistrasi. Minta admin/HR mendaftarkannya sebelum absen.</div>
<?php else: ?><div class="msg n">Wajah Anda sudah terdaftar. Aktifkan GPS dan kamera sebelum absen.</div><?php endif ?>
<?php foot();
