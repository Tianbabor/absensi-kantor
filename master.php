<?php require 'config.php';
need('admin');
$T = [
    'divisi' => ['id_divisi', 'Divisi', ['nama_divisi' => 'Nama', 'deskripsi' => 'Deskripsi']],
    'jabatan' => ['id_jabatan', 'Jabatan', ['nama_jabatan' => 'Nama', 'deskripsi' => 'Deskripsi']],
    'jam_kerja' => ['id_jam', 'Jam Kerja', ['nama_shift' => 'Nama shift', 'jam_masuk' => 'Jam masuk', 'jam_pulang' => 'Jam pulang', 'deskripsi' => 'Deskripsi']],
    'pengaturan_sistem' => ['id_pengaturan', 'Pengaturan', ['nama_pengaturan' => 'Nama', 'nilai' => 'Nilai', 'keterangan' => 'Keterangan']]
];
$t = $_GET['t'] ?? 'divisi';
if (!isset($T[$t])) $t = 'divisi';
[$pk, $lbl, $cols] = $T[$t];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['del'])) q("DELETE FROM $t WHERE $pk=?", [$_POST['del']]);
    else {
        $val = array_map(fn($c) => $_POST[$c] ?? '', array_keys($cols));
        if ($_POST['id']) q("UPDATE $t SET " . implode('=?,', array_keys($cols)) . "=? WHERE $pk=?", [...$val, $_POST['id']]);
        else q("INSERT INTO $t(" . implode(',', array_keys($cols)) . ') VALUES(' . implode(',', array_fill(0, count($cols), '?')) . ')', $val);
    }
    logact("Ubah $t");
    header("Location: master.php?t=$t");
    exit;
}
$ed = isset($_GET['edit']) ? q("SELECT * FROM $t WHERE $pk=?", [$_GET['edit']])->fetch() : [];
head('Divisi, Jabatan & Shift'); ?>
<div class="row" style="margin:16px 0"><?php foreach ($T as $k => $x): ?><a class="btn s<?= $k === $t ? '' : ' o' ?>" href="?t=<?= $k ?>"><?= $x[1] ?></a><?php endforeach ?></div>
<form class="card form" method="post"><input type="hidden" name="id" value="<?= e($ed[$pk] ?? '') ?>">
    <?php foreach ($cols as $c => $l): ?><div><label><?= $l ?></label><input name="<?= $c ?>" type="<?= str_starts_with($c, 'jam_') ? 'time' : 'text' ?>" value="<?= e($ed[$c] ?? '') ?>"></div><?php endforeach ?>
    <div class="row"><button class="btn">Simpan</button><?php if ($ed): ?><a class="btn o" href="?t=<?= $t ?>">Batal</a><?php endif ?></div>
</form>
<div class="card scroll">
    <table>
        <tr><?php foreach ($cols as $l): ?><th><?= $l ?></th><?php endforeach ?><th>Aksi</th>
        </tr>
        <?php foreach (q("SELECT * FROM $t")->fetchAll() as $r): ?><tr><?php foreach ($cols as $c => $l): ?><td><?= e($r[$c]) ?></td><?php endforeach ?>
                <td class="row"><a class="btn s o" href="?t=<?= $t ?>&edit=<?= $r[$pk] ?>">Ubah</a>
                    <form method="post" onsubmit="return confirm('Hapus data ini?')"><button class="btn s c" name="del" value="<?= $r[$pk] ?>">Hapus</button></form>
                </td>
            </tr><?php endforeach ?>
    </table>
</div>
<?php foot();
