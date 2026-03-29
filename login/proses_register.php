<?php
session_start();
require_once('../config/koneksi.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$allowedLevels = ['admin', 'manajemen', 'kepegawaian', 'medis', 'non medis', 'users'];

$namaLengkap = isset($_POST['nama_lengkap']) ? trim($_POST['nama_lengkap']) : '';
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';
$konfirmasiPassword = isset($_POST['konfirmasi_password']) ? trim($_POST['konfirmasi_password']) : '';
$jabatan = isset($_POST['jabatan']) ? trim($_POST['jabatan']) : '';
$level = isset($_POST['level']) ? trim($_POST['level']) : '';

$_SESSION['register_old'] = [
    'nama_lengkap' => $namaLengkap,
    'username' => $username,
    'jabatan' => $jabatan,
    'level' => $level !== '' ? $level : 'users',
];
$_SESSION['register_open_modal'] = true;

if ($namaLengkap === '' || $username === '' || $password === '' || $konfirmasiPassword === '' || $jabatan === '' || $level === '') {
    $_SESSION['register_error'] = 'Semua field pada form buat account wajib diisi.';
    header('Location: login.php');
    exit;
}

if (!in_array($level, $allowedLevels, true)) {
    $_SESSION['register_error'] = 'Level pengguna yang dipilih tidak valid.';
    header('Location: login.php');
    exit;
}

if (strlen($username) < 4) {
    $_SESSION['register_error'] = 'Username minimal terdiri dari 4 karakter.';
    header('Location: login.php');
    exit;
}

if (strlen($password) < 6) {
    $_SESSION['register_error'] = 'Password minimal terdiri dari 6 karakter.';
    header('Location: login.php');
    exit;
}

if ($password !== $konfirmasiPassword) {
    $_SESSION['register_error'] = 'Konfirmasi password tidak sama dengan password.';
    header('Location: login.php');
    exit;
}

$cekUser = $mysqli->prepare('SELECT id_users FROM tb_users WHERE username = ? LIMIT 1');
$cekUser->bind_param('s', $username);
$cekUser->execute();
$cekUser->store_result();

if ($cekUser->num_rows > 0) {
    $cekUser->close();
    $_SESSION['register_error'] = 'Username sudah digunakan, silakan pakai username lain.';
    header('Location: login.php');
    exit;
}

$cekUser->close();

$passwordMd5 = md5($password);
$tanggalLog = date('Y-m-d H:i:s');
$jamLog = date('H:i:s');

$insert = $mysqli->prepare('
    INSERT INTO tb_users (nama_lengkap, username, password, jabatan, level, tgl_log, jam_log)
    VALUES (?, ?, ?, ?, ?, ?, ?)
');
$insert->bind_param('sssssss', $namaLengkap, $username, $passwordMd5, $jabatan, $level, $tanggalLog, $jamLog);
$insert->execute();
$insert->close();

unset($_SESSION['register_old'], $_SESSION['register_open_modal']);
$_SESSION['register_success'] = 'Account berhasil dibuat. Silakan login menggunakan username dan password yang baru.';

header('Location: login.php');
exit;
?>
