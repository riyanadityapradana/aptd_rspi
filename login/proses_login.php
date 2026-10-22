<?php
session_start();
require_once __DIR__ . '/login_security.php';
aptd_login_no_cache();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$username = aptd_login_post_string('username');
$password = aptd_login_post_string('password');

$_SESSION['login_old_username'] = substr($username, 0, 255);

$csrf = aptd_login_post_string('csrf_token');
if (empty($_SESSION['login_csrf']) || !hash_equals($_SESSION['login_csrf'], $csrf)) {
    aptd_login_reject('Sesi formulir tidak valid. Silakan coba lagi dengan formulir terbaru.');
}

try {
    $attempt = aptd_login_reserve_attempt();
} catch (Throwable $e) {
    error_log('APTD login limiter: ' . $e->getMessage());
    aptd_login_reject('Pengamanan login sedang tidak tersedia. Silakan hubungi administrator.');
}

if ($attempt['wait'] > 0) {
    // Jangan memperpanjang jeda ketika tombol diklik lagi selama terkunci.
    header('Retry-After: ' . $attempt['wait']);
    aptd_login_reject('Percobaan login dibatasi. Tunggu hingga hitung mundur selesai.');
}

if (!aptd_login_check_captcha(aptd_login_post_string('captcha_id'), aptd_login_post_string('captcha'))) {
    aptd_login_reject('Kode CAPTCHA salah atau kedaluwarsa. Silakan tunggu 30 detik sebelum mencoba lagi.', $attempt['lease']);
}

if ($username === '' || $password === '') {
    aptd_login_reject('Username dan password wajib diisi. Silakan tunggu 30 detik sebelum mencoba lagi.', $attempt['lease']);
}

if (strlen($username) > 255 || strlen($password) > 4096) {
    aptd_login_reject('Username atau password tidak valid. Silakan tunggu 30 detik sebelum mencoba lagi.', $attempt['lease']);
}

// Buka koneksi laporan hanya setelah pembatasan dan CAPTCHA lolos.
require_once __DIR__ . '/../config/koneksi.php';

$passwordMd5 = md5($password);

$sql = "SELECT id_users, nama_lengkap, username, jabatan, level
        FROM tb_users
        WHERE username = ? AND password = ?
        LIMIT 1";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param('ss', $username, $passwordMd5);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    aptd_login_reject('Username atau password tidak valid. Silakan tunggu 30 detik sebelum mencoba lagi.', $attempt['lease']);
}

$tglLog = date('Y-m-d H:i:s');
$jamLog = date('H:i:s');

$update = $mysqli->prepare("UPDATE tb_users SET tgl_log = ?, jam_log = ? WHERE id_users = ?");
$update->bind_param('ssi', $tglLog, $jamLog, $user['id_users']);
$update->execute();
$update->close();

try {
    aptd_login_clear_attempt($attempt['lease']);
} catch (Throwable $e) {
    error_log('APTD login limiter reset: ' . $e->getMessage());
}

session_regenerate_id(true);

$_SESSION['login_aptd_rspi'] = true;
$_SESSION['id_user'] = (int) $user['id_users'];
$_SESSION['nama_lengkap'] = $user['nama_lengkap'];
$_SESSION['username'] = $user['username'];
$_SESSION['jabatan'] = $user['jabatan'];
$_SESSION['level'] = $user['level'];
$_SESSION['tgl_log'] = $tglLog;
$_SESSION['jam_log'] = $jamLog;

unset($_SESSION['login_error'], $_SESSION['login_old_username'], $_SESSION['login_captchas'], $_SESSION['login_csrf']);

header('Location: ../main_app/main_app.php?page=beranda');
exit;
?>
