<?php
session_start();
require_once('../config/koneksi.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';

$_SESSION['login_old_username'] = $username;

if ($username === '' || $password === '') {
    $_SESSION['login_error'] = 'Username dan password wajib diisi.';
    header('Location: login.php');
    exit;
}

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
    $_SESSION['login_error'] = 'Username atau password tidak valid.';
    header('Location: login.php');
    exit;
}

$tglLog = date('Y-m-d H:i:s');
$jamLog = date('H:i:s');

$update = $mysqli->prepare("UPDATE tb_users SET tgl_log = ?, jam_log = ? WHERE id_users = ?");
$update->bind_param('ssi', $tglLog, $jamLog, $user['id_users']);
$update->execute();
$update->close();

session_regenerate_id(true);

$_SESSION['login_aptd_rspi'] = true;
$_SESSION['id_user'] = (int) $user['id_users'];
$_SESSION['nama_lengkap'] = $user['nama_lengkap'];
$_SESSION['username'] = $user['username'];
$_SESSION['jabatan'] = $user['jabatan'];
$_SESSION['level'] = $user['level'];
$_SESSION['tgl_log'] = $tglLog;
$_SESSION['jam_log'] = $jamLog;

unset($_SESSION['login_error'], $_SESSION['login_old_username']);

header('Location: ../main_app/main_app.php?page=beranda');
exit;
?>
