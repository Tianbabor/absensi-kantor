<?php require 'config.php';
if (me()) {
    header('Location: dashboard.php');
    exit;
}
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = q("SELECT u.*,k.nama FROM users u LEFT JOIN karyawan k ON k.id_karyawan=u.id_karyawan WHERE u.username=? AND u.status='aktif'", [$_POST['username'] ?? ''])->fetch();
    if ($u && password_verify($_POST['password'] ?? '', $u['password']) && $u['role'] === ($_POST['role'] ?? '')) {
        session_regenerate_id(true);
        $u['nama'] = $u['nama'] ?? 'Administrator';
        unset($u['password']);
        $_SESSION['u'] = $u;
        q('UPDATE users SET last_login=NOW() WHERE id_user=?', [$u['id_user']]);
        logact('Login');
        header('Location: dashboard.php');
        exit;
    }
    $err = 'Username, password, atau role salah.';
} ?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Login | AbsensiKu</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>

<body>
    <div class="login">
        <div class="hero">
            <i class="mark"></i>
            <h2>Disiplin hari ini, masa depan lebih baik.</h2>
            <p>Absensi berbasis lokasi dan pengenalan wajah.</p>
        </div>
        <div class="box">
            <div class="card">
                <h2 style="font-size:1.5rem">Masuk</h2>
                <p class="sub">Masuk untuk mulai absen.</p>
                <?php if ($err): ?><div class="msg c"><?= e($err) ?></div><?php endif ?>
                <form method="post"><label for="u">Username</label><input id="u" name="username" required autofocus>
                    <label for="p">Password</label><input id="p" name="password" type="password" required>
                    <label for="r">Role</label><select id="r" name="role">
                        <option value="karyawan">Karyawan</option>
                        <option value="admin">Admin / HR</option>
                    </select>
                    <button class="btn" style="width:100%;margin-top:20px">Masuk</button>
                </form>
            </div>
        </div>
    </div>
</body>

</html>