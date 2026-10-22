<?php require 'config.php';
need('admin');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = json_decode(file_get_contents('php://input'), true);
    $id = (int)($in['id'] ?? 0);
    $d = $in['descriptor'] ?? null;
    $d = is_array($d) ? array_values($d) : [];
    if (!$id || count($d) !== 128 || count(array_filter($d, 'is_numeric')) !== 128) json(['ok' => false, 'msg' => 'Data wajah tidak valid.']);
    $img = base64_decode(preg_replace('#^data:image/\w+;base64,#', '', $in['foto'] ?? ''));
    if (!$img || !@getimagesizefromstring($img)) json(['ok' => false, 'msg' => 'Foto tidak valid.']);
    if (!is_dir('uploads/wajah')) mkdir('uploads/wajah', 0775, true);
    file_put_contents("uploads/wajah/$id.jpg", $img);
    q('INSERT INTO wajah_karyawan(id_karyawan,foto_wajah,template_wajah) VALUES(?,?,?) ON DUPLICATE KEY UPDATE foto_wajah=VALUES(foto_wajah),template_wajah=VALUES(template_wajah)', [$id, "uploads/wajah/$id.jpg", json_encode(array_map('floatval', $d))]);
    logact("Registrasi wajah karyawan #$id");
    json(['ok' => true, 'msg' => 'Wajah berhasil disimpan.']);
}
head('Registrasi Wajah'); ?>
<p class="sub">Pilih karyawan, hadapkan wajah ke kamera, lalu simpan template wajah.</p>
<div class="grid g2">
    <div class="card"><label for="k">Karyawan</label><select id="k"><?php foreach (q('SELECT k.id_karyawan,k.nama,k.nik,w.id_wajah FROM karyawan k LEFT JOIN wajah_karyawan w ON w.id_karyawan=k.id_karyawan WHERE k.status="aktif" ORDER BY k.nama') as $r): ?><option value="<?= $r['id_karyawan'] ?>"><?= e($r['nama'] . ' (' . $r['nik'] . ')' . ($r['id_wajah'] ? ' - terdaftar' : '')) ?></option><?php endforeach ?></select>
        <p style="margin-top:12px"><video id="v" playsinline muted></video></p>
        <div id="st" class="msg n">Memuat kamera dan model wajah...</div><button class="btn" id="b" disabled>Mulai registrasi</button>
    </div>
    <div class="card">
        <h2>Hasil</h2>
        <p class="sub">Foto yang tersimpan akan tampil di sini dan di Data Karyawan.</p><img id="pv" alt="" style="max-width:100%;border-radius:12px;display:none">
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.13/dist/face-api.js"></script>
<script src="assets/face.js"></script>
<script>
    const $ = i => document.getElementById(i),
        say = (t, c = 'n') => {
            $('st').className = 'msg ' + c;
            $('st').textContent = t
        };
    $('b').onclick = async () => {
        $('b').disabled = true;
        try {
            say('Mendeteksi wajah... tetap hadap kamera.');
            const d = await FA.descAvg($('v'), 5);
            if (!d) return say('Wajah tidak terdeteksi. Coba lagi dengan cahaya cukup dan wajah lebih dekat.', 'c');
            const c = document.createElement('canvas');
            c.width = $('v').videoWidth;
            c.height = $('v').videoHeight;
            c.getContext('2d').drawImage($('v'), 0, 0);
            const foto = c.toDataURL('image/jpeg', .85);
            const r = await fetch('wajah.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    id: $('k').value,
                    descriptor: d,
                    foto
                })
            }).then(r => r.json());
            say(r.msg, r.ok ? 'g' : 'c');
            if (r.ok) {
                $('pv').src = foto;
                $('pv').style.display = 'block'
            }
        } catch (err) {
            say('Terjadi kesalahan. Coba lagi.', 'c')
        } finally {
            $('b').disabled = false
        }
    };
    FA.load($('v')).then(() => {
        $('b').disabled = false;
        say('Kamera siap.', 'g')
    }).catch(() => say('Kamera/model gagal dimuat. Gunakan localhost atau HTTPS dan pastikan ada internet.', 'c'));
</script>
<?php foot();
