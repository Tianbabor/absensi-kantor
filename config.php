<?php
// Sesuaikan koneksi database di sini
const DB_HOST = 'localhost', DB_NAME = 'absensiku', DB_USER = 'root', DB_PASS = '';
const DEBUG = false; // true hanya saat mengembangkan di localhost; false untuk link publik

error_reporting(E_ALL);
ini_set('display_errors', DEBUG ? '1' : '0');
ini_set('log_errors', '1'); // error tetap tercatat di xampp/apache/logs/error.log

ini_set('session.use_strict_mode', '1');
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => $https,
]);
session_start();
date_default_timezone_set('Asia/Jakarta');

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');

function db(): PDO
{
    static $p;
    if ($p) return $p;
    try {
        $p = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $p->exec("SET time_zone = '+07:00'"); // samakan dengan Asia/Jakarta
    } catch (PDOException $x) {
        http_response_code(500);
        exit(DEBUG ? 'Koneksi database gagal: ' . e($x->getMessage()) : 'Koneksi database gagal.');
    }
    return $p;
}

function q(string $s, array $a = []): PDOStatement
{
    $t = db()->prepare($s);
    $t->execute($a);
    return $t;
}

function e(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function me(): ?array
{
    return $_SESSION['u'] ?? null;
}

function wantsJson(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
        && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
}

function need(?string $role = null): void
{
    $u = me();
    if (!$u) {
        if (wantsJson()) json(['ok' => false, 'msg' => 'Sesi berakhir. Silakan login ulang.']);
        header('Location: index.php');
        exit;
    }
    if ($role && $u['role'] !== $role) {
        if (wantsJson()) json(['ok' => false, 'msg' => 'Anda tidak punya akses.']);
        header('Location: dashboard.php');
        exit;
    }
}

function logact(string $a): void
{
    q('INSERT INTO log_aktivitas(id_user,aktivitas,ip_address) VALUES(?,?,?)', [me()['id_user'] ?? null, $a, $_SERVER['REMOTE_ADDR'] ?? '']);
}

function setting(string $k, string $d = '')
{
    $r = q('SELECT nilai FROM pengaturan_sistem WHERE nama_pengaturan=?', [$k])->fetch();
    return $r ? $r['nilai'] : $d;
}

function jarak(mixed $a, mixed $b, mixed $c, mixed $d): float
{
    [$a, $b, $c, $d] = array_map('floatval', [$a, $b, $c, $d]);
    $p = M_PI / 180;
    $x = sin(($c - $a) * $p / 2) ** 2 + cos($a * $p) * cos($c * $p) * sin(($d - $b) * $p / 2) ** 2;
    return 12742000 * asin(sqrt($x));
}

function json(array $d)
{
    header('Content-Type: application/json');
    echo json_encode($d);
    exit;
}

function badge(mixed $s): string
{
    $c = ['Hadir' => 'g', 'Terlambat' => 'a', 'Izin' => 'p', 'Alpha' => 'c', 'aktif' => 'g', 'nonaktif' => 'c'][$s] ?? 'g';
    return '<span class="bd ' . $c . '">' . e(ucfirst((string)$s)) . '</span>';
}

function opts(string $t, string $id, string $nm, mixed $sel = ''): string
{
    foreach ([$t, $id, $nm] as $x) {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $x)) throw new InvalidArgumentException('Nama tabel/kolom tidak valid');
    }
    $o = '<option value="">-</option>';
    foreach (q("SELECT $id i,$nm n FROM $t ORDER BY $nm") as $r) $o .= '<option value="' . $r['i'] . '"' . ($r['i'] == $sel ? ' selected' : '') . '>' . e($r['n']) . '</option>';
    return $o;
}

function tgl_id(?int $ts = null): string
{
    $h = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $b = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $ts ??= time();
    return $h[(int)date('w', $ts)] . ', ' . (int)date('j', $ts) . ' ' . $b[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

// [file, label sidebar (laptop), label tab bawah (HP), tampil di tab bawah?]
function menu(): array
{
    $u = me();
    if ($u['role'] === 'admin') return [
        ['dashboard.php', 'Dashboard', 'Beranda', 1],
        ['karyawan.php', 'Data Karyawan', 'Karyawan', 1],
        ['master.php', 'Divisi, Jabatan & Shift', 'Divisi & Shift', 0],
        ['lokasi.php', 'Lokasi Perusahaan', 'Lokasi', 0],
        ['wajah.php', 'Registrasi Wajah', 'Registrasi Wajah', 0],
        ['riwayat.php', 'Riwayat Absensi', 'Riwayat', 1],
        ['laporan.php', 'Laporan', 'Laporan', 1],
    ];
    return [
        ['dashboard.php', 'Beranda', 'Beranda', 1],
        ['absen.php', 'Absensi', 'Absensi', 1],
        ['riwayat.php', 'Riwayat', 'Riwayat', 1],
    ];
}

// $hello diisi (mis. "Halo, Andi") untuk halaman beranda yang memakai header hijau besar
function head(string $t, ?string $hello = null): void
{
    $u = me();
    $cur = basename($_SERVER['SCRIPT_NAME']);
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#12332c"><title>' . e($t) . ' | AbsensiKu</title><link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/style.css"></head><body><div class="app"><aside><div class="logo"><i></i>AbsensiKu</div><nav>';
    foreach (menu() as [$f, $l]) echo '<a href="' . $f . '"' . ($f === $cur ? ' class="on"' : '') . '>' . e($l) . '</a>';
    echo '</nav><div class="who"><b>' . e($u['nama']) . '</b><br>' . ucfirst($u['role']) . '<br><a href="logout.php">Keluar</a></div></aside><main>';
    if ($hello !== null) echo '<header class="hero-top"><a class="out" href="logout.php">Keluar</a><small>Selamat datang,</small><h1>' . e($hello) . '</h1><p>' . tgl_id() . '</p></header>';
    else echo '<h1>' . e($t) . '</h1>';
}

function foot(): void
{
    $cur = basename($_SERVER['SCRIPT_NAME']);
    $m = menu();
    $main = array_filter($m, fn($x) => $x[3]);
    $more = array_filter($m, fn($x) => !$x[3]);
    echo '</main></div><nav class="bnav">';
    foreach ($main as [$f,, $s]) echo '<a href="' . $f . '"' . ($f === $cur ? ' class="on"' : '') . '>' . e($s) . '</a>';
    if ($more) echo '<button type="button" id="more"' . (in_array($cur, array_column($more, 0), true) ? ' class="on"' : '') . '>Lainnya</button>';
    echo '</nav>';
    if ($more) {
        echo '<div class="sheet" id="sheet" hidden><div class="sh-bg" id="shbg"></div><div class="sh-box">';
        foreach ($more as [$f, $l]) echo '<a href="' . $f . '"' . ($f === $cur ? ' class="on"' : '') . '>' . e($l) . '</a>';
        echo '<a class="out" href="logout.php">Keluar</a></div></div>';
        echo '<script>(function(){var s=document.getElementById("sheet"),t=function(){s.hidden=!s.hidden};document.getElementById("more").onclick=t;document.getElementById("shbg").onclick=t})()</script>';
    }
    echo '</body></html>';
}
