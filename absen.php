<?php require 'config.php';
need('karyawan');
$id = me()['id_karyawan'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = json_decode(file_get_contents('php://input'), true);
    $tipe = $in['tipe'] ?? '';
    $lat = (float)($in['lat'] ?? 0);
    $lng = (float)($in['lng'] ?? 0);
    $desc = $in['descriptor'] ?? null;
    $best = null;
    $bd = INF;
    foreach (q("SELECT * FROM lokasi_perusahaan WHERE status='aktif'") as $l) {
        $d = jarak($lat, $lng, $l['latitude'], $l['longitude']);
        if ($d < $bd) {
            $bd = $d;
            $best = $l;
        }
    }
    if (!$best) json(['ok' => false, 'msg' => 'Belum ada lokasi perusahaan aktif.']);
    if ($bd > $best['radius_meter']) json(['ok' => false, 'msg' => 'Di luar area perusahaan: ' . round($bd) . ' m dari ' . $best['nama_lokasi'] . ' (radius ' . $best['radius_meter'] . ' m).']);
    $w = q('SELECT template_wajah FROM wajah_karyawan WHERE id_karyawan=?', [$id])->fetch();
    if (!$w) json(['ok' => false, 'msg' => 'Wajah Anda belum diregistrasi. Hubungi admin.']);
    $desc = is_array($desc) ? array_values($desc) : [];
    if (count($desc) !== 128 || count(array_filter($desc, 'is_numeric')) !== 128) json(['ok' => false, 'msg' => 'Wajah tidak terdeteksi.']);
    $t = json_decode($w['template_wajah'], true);
    if (!is_array($t) || count($t) !== 128) json(['ok' => false, 'msg' => 'Data wajah tersimpan rusak. Hubungi admin.']);
    $t = array_values($t);
    $s = 0;
    foreach ($t as $i => $v) $s += ((float)$v - (float)$desc[$i]) ** 2;
    if (sqrt($s) > (float)setting('face_threshold', '0.5')) json(['ok' => false, 'msg' => 'Wajah tidak cocok.']);
    $now = date('H:i:s');
    $row = q('SELECT * FROM absensi WHERE id_karyawan=? AND tanggal=CURDATE()', [$id])->fetch();
    if ($tipe === 'masuk') {
        if ($row) json(['ok' => false, 'msg' => 'Anda sudah absen masuk hari ini.']);
        $sh = q('SELECT * FROM jam_kerja ORDER BY id_jam LIMIT 1')->fetch();
        $st = ($sh && strtotime($now) > strtotime($sh['jam_masuk']) + (int)setting('toleransi_telat_menit', 0) * 60) ? 'Terlambat' : 'Hadir';
        q('INSERT INTO absensi(id_karyawan,id_lokasi,id_jam,tanggal,jam_masuk,latitude_masuk,longitude_masuk,jarak_masuk,status) VALUES(?,?,?,CURDATE(),?,?,?,?,?)', [$id, $best['id_lokasi'], $sh['id_jam'] ?? null, $now, $lat, $lng, round($bd, 1), $st]);
        logact('Absen masuk');
        json(['ok' => true, 'msg' => "Absen masuk berhasil pukul $now ($st)."]);
    }
    if ($tipe === 'pulang') {
        if (!$row) json(['ok' => false, 'msg' => 'Anda belum absen masuk hari ini.']);
        if ($row['jam_pulang']) json(['ok' => false, 'msg' => 'Anda sudah absen pulang hari ini.']);
        q('UPDATE absensi SET jam_pulang=?,latitude_pulang=?,longitude_pulang=?,jarak_pulang=? WHERE id_absensi=?', [$now, $lat, $lng, round($bd, 1), $row['id_absensi']]);
        logact('Absen pulang');
        json(['ok' => true, 'msg' => "Absen pulang berhasil pukul $now."]);
    }
    json(['ok' => false, 'msg' => 'Permintaan tidak valid.']);
}
$lok = q("SELECT nama_lokasi,latitude,longitude,radius_meter FROM lokasi_perusahaan WHERE status='aktif'")->fetchAll();
head('Absensi'); ?>
<div class="narrow">
    <p class="sub">Aktifkan GPS dan kamera, lalu pilih absen.</p>
    <div class="steps"><span id="s1" class="on">1 Lokasi</span><span id="s2">2 Scan</span><span id="s3">3 Verifikasi</span><span id="s4">4 Selesai</span></div>
    <div id="gps" class="msg n"><b id="gt">Menunggu GPS...</b><br><span id="ds"></span></div>
    <div class="cam"><video id="v" playsinline muted></video></div>
    <div id="st" class="msg n">Memuat kamera dan model wajah...</div>
    <div class="row two"><button class="btn" id="bm" disabled>Absen masuk</button><button class="btn c" id="bp" disabled>Absen pulang</button></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.13/dist/face-api.js"></script>
<script src="assets/face.js"></script>
<script>
    const LOK = <?= json_encode($lok) ?>,
        $ = i => document.getElementById(i);
    let pos = null;
    const dist = (a, b, c, d) => {
        const p = Math.PI / 180,
            x = Math.sin((c - a) * p / 2) ** 2 + Math.cos(a * p) * Math.cos(c * p) * Math.sin((d - b) * p / 2) ** 2;
        return 12742000 * Math.asin(Math.sqrt(x))
    };
    const say = (t, c = 'n') => {
        $('st').className = 'msg ' + c;
        $('st').textContent = t
    };
    const gps = (t, c, d = '') => {
        $('gps').className = 'msg ' + c;
        $('gt').textContent = t;
        $('ds').textContent = d
    };
    const step = n => [1, 2, 3, 4].forEach(i => $('s' + i).classList.toggle('on', i <= n));
    navigator.geolocation.watchPosition(p => {
            pos = p.coords;
            let b = Infinity,
                n = null;
            LOK.forEach(l => {
                const d = dist(pos.latitude, pos.longitude, +l.latitude, +l.longitude);
                if (d < b) {
                    b = d;
                    n = l
                }
            });
            if (!n) return gps('Belum ada lokasi perusahaan aktif.', 'c');
            const info = 'Jarak ' + Math.round(b) + ' m (radius ' + n.radius_meter + ' m)';
            if (b <= +n.radius_meter) gps('Lokasi valid: Anda berada di ' + n.nama_lokasi + '.', 'g', info);
            else gps('Di luar area perusahaan.', 'c', info);
        },
        () => gps('GPS tidak aktif. Izinkan akses lokasi di browser.', 'c'), {
            enableHighAccuracy: true
        });
    async function send(tipe) {
        if (!pos) return say('Menunggu lokasi GPS...', 'c');
        $('bm').disabled = $('bp').disabled = true;
        step(2);
        say('Memindai wajah...');
        try {
            const d = await FA.descAvg($('v'), 3);
            if (!d) {
                step(2);
                return say('Wajah tidak terdeteksi. Hadapkan wajah ke kamera dan dekatkan sedikit.', 'c')
            }
            step(3);
            say('Memverifikasi...');
            const r = await fetch('absen.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    tipe,
                    lat: pos.latitude,
                    lng: pos.longitude,
                    descriptor: d
                })
            }).then(r => r.json());
            say(r.msg, r.ok ? 'g' : 'c');
            if (r.ok) {
                step(4);
                setTimeout(() => location = 'dashboard.php', 2000)
            } else step(2)
        } catch (err) {
            step(2);
            say('Terjadi kesalahan. Coba lagi.', 'c')
        } finally {
            $('bm').disabled = $('bp').disabled = false
        }
    }
    $('bm').onclick = () => send('masuk');
    $('bp').onclick = () => send('pulang');
    FA.load($('v')).then(() => {
        $('bm').disabled = $('bp').disabled = false;
        step(2);
        say('Kamera siap. Hadapkan wajah ke kamera.', 'n')
    }).catch(() => say('Kamera/model gagal dimuat. Gunakan localhost atau HTTPS, izinkan kamera, dan pastikan ada internet.', 'c'));
</script>
<?php foot();
