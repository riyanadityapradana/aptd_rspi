<?php
session_start();

if (isset($_SESSION['login_aptd_rspi']) && $_SESSION['login_aptd_rspi'] === true) {
    header('Location: ../main_app/main_app.php?page=beranda');
    exit;
}

$pesan = isset($_SESSION['login_error']) ? $_SESSION['login_error'] : '';
$usernameTerakhir = isset($_SESSION['login_old_username']) ? $_SESSION['login_old_username'] : '';
$pesanRegister = isset($_SESSION['register_error']) ? $_SESSION['register_error'] : '';
$pesanSuksesRegister = isset($_SESSION['register_success']) ? $_SESSION['register_success'] : '';
$registerOld = isset($_SESSION['register_old']) && is_array($_SESSION['register_old']) ? $_SESSION['register_old'] : [
    'nama_lengkap' => '',
    'username' => '',
    'jabatan' => '',
    'level' => 'users',
];
$bukaModalRegister = isset($_SESSION['register_open_modal']) ? (bool) $_SESSION['register_open_modal'] : false;

unset(
    $_SESSION['login_error'],
    $_SESSION['login_old_username'],
    $_SESSION['register_error'],
    $_SESSION['register_success'],
    $_SESSION['register_old'],
    $_SESSION['register_open_modal']
);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Login Aplikasi Tarikan Data RSPI</title>
    <link rel="icon" href="../assets/assets-admin/img/logo1.png">
    <link href="../assets/assets-admin/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/assets-admin/css/animate.css" rel="stylesheet">
    <style>
        :root {
            --blue-100: #eef8ff;
            --blue-200: #d8efff;
            --blue-300: #b9e3ff;
            --blue-400: #89cdf8;
            --blue-500: #58b5ef;
            --blue-600: #3798db;
            --blue-700: #2578bf;
            --text-dark: #1d3557;
        }

        * { box-sizing: border-box; }

        body {
            min-height: 100vh;
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(255, 255, 255, 0.92), rgba(255, 255, 255, 0) 32%),
                radial-gradient(circle at bottom right, rgba(117, 201, 255, 0.35), rgba(117, 201, 255, 0) 28%),
                linear-gradient(135deg, #eaf7ff 0%, #cfeeff 45%, #a8dbfb 100%);
            color: var(--text-dark);
            overflow-x: hidden;
        }

        .login-shell {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 18px;
        }

        .login-grid {
            width: 100%;
            max-width: 1120px;
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
            background: rgba(255, 255, 255, 0.42);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 24px 80px rgba(37, 120, 191, 0.22);
        }

        .brand-panel {
            position: relative;
            padding: 56px 48px;
            background: linear-gradient(165deg, rgba(255,255,255,0.5) 0%, rgba(167,223,252,0.42) 100%);
        }

        .brand-panel::before,
        .brand-panel::after {
            content: "";
            position: absolute;
            border-radius: 999px;
        }

        .brand-panel::before {
            width: 240px;
            height: 240px;
            background: rgba(255, 255, 255, 0.34);
            top: -70px;
            right: -50px;
        }

        .brand-panel::after {
            width: 180px;
            height: 180px;
            background: rgba(88, 181, 239, 0.18);
            bottom: -30px;
            left: -40px;
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 18px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.76);
            color: var(--blue-700);
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            position: relative;
            z-index: 1;
        }

        .brand-title {
            margin-top: 26px;
            margin-bottom: 16px;
            font-size: 42px;
            line-height: 1.15;
            font-weight: 800;
            position: relative;
            z-index: 1;
        }

        .brand-subtitle {
            max-width: 470px;
            font-size: 17px;
            line-height: 1.8;
            color: rgba(29, 53, 87, 0.82);
            position: relative;
            z-index: 1;
        }

        .brand-points {
            margin-top: 34px;
            position: relative;
            z-index: 1;
        }

        .brand-point {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 16px;
            padding: 14px 16px;
            background: rgba(255, 255, 255, 0.58);
            border-radius: 18px;
            box-shadow: inset 0 0 0 1px rgba(137, 205, 248, 0.22);
        }

        .brand-icon {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--blue-500), var(--blue-700));
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .form-panel {
            padding: 56px 42px;
            background: rgba(255, 255, 255, 0.86);
        }

        .form-wrap {
            max-width: 420px;
            margin: 0 auto;
        }

        .form-title {
            margin-bottom: 10px;
            font-size: 34px;
            font-weight: 800;
            color: var(--text-dark);
        }

        .form-copy {
            margin-bottom: 30px;
            color: #55718e;
            line-height: 1.7;
        }

        .login-card {
            padding: 28px;
            border-radius: 24px;
            background: linear-gradient(180deg, rgba(255,255,255,0.95) 0%, rgba(240,249,255,0.96) 100%);
            border: 1px solid rgba(137, 205, 248, 0.32);
            box-shadow: 0 20px 50px rgba(52, 141, 208, 0.12);
        }

        .form-group label {
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .form-control {
            height: 52px;
            border-radius: 16px;
            border: 1px solid rgba(137, 205, 248, 0.65);
            background: #fafdff;
            box-shadow: none;
            color: var(--text-dark);
            padding: 12px 16px;
        }

        .form-control:focus {
            border-color: var(--blue-500);
            box-shadow: 0 0 0 4px rgba(88, 181, 239, 0.16);
        }

        .btn-login {
            width: 100%;
            height: 54px;
            border: 0;
            border-radius: 18px;
            font-weight: 700;
            letter-spacing: 0.02em;
            color: #fff;
            background: linear-gradient(135deg, var(--blue-500), var(--blue-700));
            box-shadow: 0 18px 30px rgba(37, 120, 191, 0.26);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-login:hover,
        .btn-login:focus {
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 22px 36px rgba(37, 120, 191, 0.3);
        }

        .login-footer {
            margin-top: 18px;
            font-size: 13px;
            color: #61809b;
            text-align: center;
        }

        .login-actions {
            margin-top: 18px;
            text-align: center;
        }

        .btn-link-action {
            padding: 0;
            border: 0;
            background: none;
            color: var(--blue-700);
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-link-action:hover,
        .btn-link-action:focus {
            color: var(--blue-600);
            text-decoration: underline;
            outline: none;
        }

        .modal-content {
            border: 0;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 24px 70px rgba(37, 120, 191, 0.24);
        }

        .modal-header {
            border-bottom: 1px solid rgba(137, 205, 248, 0.28);
            background: linear-gradient(135deg, rgba(234, 247, 255, 0.95), rgba(216, 239, 255, 0.92));
        }

        .modal-title {
            font-weight: 800;
            color: var(--text-dark);
        }

        .modal-body {
            background: linear-gradient(180deg, #ffffff 0%, #f5fbff 100%);
            padding: 26px;
        }

        .modal-footer {
            border-top: 1px solid rgba(137, 205, 248, 0.28);
            background: #f8fcff;
        }

        .btn-modal-primary {
            border: 0;
            border-radius: 14px;
            padding: 11px 22px;
            font-weight: 700;
            color: #fff;
            background: linear-gradient(135deg, var(--blue-500), var(--blue-700));
        }

        .btn-modal-secondary {
            border-radius: 14px;
            padding: 11px 22px;
            font-weight: 600;
        }

        .alert {
            border-radius: 16px;
            border: 0;
        }

        @media (max-width: 991px) {
            .login-grid { grid-template-columns: 1fr; }
            .brand-panel, .form-panel { padding: 36px 26px; }
            .brand-title { font-size: 32px; }
        }

        @media (max-width: 575px) {
            .login-shell { padding: 18px 12px; }
            .login-grid { border-radius: 22px; }
            .brand-panel, .form-panel { padding: 28px 18px; }
            .login-card { padding: 22px 18px; border-radius: 20px; }
            .form-title, .brand-title { font-size: 28px; }
        }
    </style>
</head>
<body>
    <div class="login-shell">
        <div class="login-grid animated fadeIn">
            <section class="brand-panel">
                <div class="brand-badge">
                    <img src="../assets/assets-admin/img/logo1.png" alt="Logo RSPI" style="height: 26px;">
                    APTD RSPI
                </div>
                <h1 class="brand-title">Satu pintu akses untuk tarikan data layanan RSPI.</h1>
                <p class="brand-subtitle">
                    Login untuk mengakses dashboard pelaporan, data kunjungan, dan analisis penyakit dengan tampilan modern bernuansa biru muda yang selaras dengan aplikasi Anda.
                </p>
                <div class="brand-points">
                    <div class="brand-point">
                        <div class="brand-icon">01</div>
                        <div><strong>Terarah</strong><br>Akses aplikasi sekarang lewat autentikasi pengguna.</div>
                    </div>
                    <div class="brand-point">
                        <div class="brand-icon">02</div>
                        <div><strong>Ringkas</strong><br>Masuk cepat dengan username dan password pengguna.</div>
                    </div>
                    <div class="brand-point">
                        <div class="brand-icon">03</div>
                        <div><strong>Tercatat</strong><br>Waktu login tersimpan otomatis di tabel `tb_users`.</div>
                    </div>
                </div>
            </section>
            <section class="form-panel">
                <div class="form-wrap">
                    <h2 class="form-title">Login Pengguna</h2>
                    <p class="form-copy">Masukkan akun Anda untuk masuk ke aplikasi tarikan data RSPI.</p>
                    <div class="login-card">
                        <?php if ($pesan !== ''): ?>
                            <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                        <?php if ($pesanSuksesRegister !== ''): ?>
                            <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($pesanSuksesRegister, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                        <form action="proses_login.php" method="post" autocomplete="off">
                            <div class="form-group">
                                <label for="username">Username</label>
                                <input type="text" class="form-control" id="username" name="username" placeholder="Masukkan username" value="<?php echo htmlspecialchars($usernameTerakhir, ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="password">Password</label>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Masukkan password" required>
                            </div>
                            <button type="submit" class="btn btn-login">Masuk ke Aplikasi</button>
                        </form>
                        <div class="login-actions">
                            <button type="button" class="btn-link-action" data-toggle="modal" data-target="#modalBuatAkun">Buat Account</button> -->
                        </div>
                        <div class="login-footer">RSPI | Sistem Tarikan Data Pelayanan</div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <div class="modal fade" id="modalBuatAkun" tabindex="-1" role="dialog" aria-labelledby="modalBuatAkunLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <form action="proses_register.php" method="post" autocomplete="off">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalBuatAkunLabel">Form Buat Account</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <?php if ($pesanRegister !== ''): ?>
                            <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($pesanRegister, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                        <div class="form-group">
                            <label for="register_nama_lengkap">Nama Lengkap</label>
                            <input type="text" class="form-control" id="register_nama_lengkap" name="nama_lengkap" value="<?php echo htmlspecialchars($registerOld['nama_lengkap'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Masukkan nama lengkap" required>
                        </div>
                        <div class="form-group">
                            <label for="register_username">Username</label>
                            <input type="text" class="form-control" id="register_username" name="username" value="<?php echo htmlspecialchars($registerOld['username'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Masukkan username" required>
                        </div>
                        <div class="form-group">
                            <label for="register_password">Password</label>
                            <input type="password" class="form-control" id="register_password" name="password" placeholder="Masukkan password" required>
                        </div>
                        <div class="form-group">
                            <label for="register_konfirmasi_password">Konfirmasi Password</label>
                            <input type="password" class="form-control" id="register_konfirmasi_password" name="konfirmasi_password" placeholder="Ulangi password" required>
                        </div>
                        <div class="form-group">
                            <label for="register_jabatan">Jabatan</label>
                            <input type="text" class="form-control" id="register_jabatan" name="jabatan" value="<?php echo htmlspecialchars($registerOld['jabatan'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Masukkan jabatan" required>
                        </div>
                        <div class="form-group mb-0">
                            <label for="register_level">Level</label>
                            <select class="form-control" id="register_level" name="level" required>
                                <?php
                                $opsiLevel = ['admin', 'manajemen', 'kepegawaian', 'medis', 'non medis', 'users', 'rekammedis'];
                                foreach ($opsiLevel as $opsi):
                                    $selected = $registerOld['level'] === $opsi ? 'selected' : '';
                                ?>
                                    <option value="<?php echo htmlspecialchars($opsi, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selected; ?>>
                                        <?php echo htmlspecialchars(ucwords($opsi), ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light btn-modal-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-modal-primary">Simpan Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/assets-admin/js/jquery-3.4.1.js"></script>
    <script src="../assets/assets-admin/js/popper.min.js"></script>
    <script src="../assets/assets-admin/js/bootstrap.min.js"></script>
    <script>
        $(function () {
            <?php if ($bukaModalRegister): ?>
            $('#modalBuatAkun').modal('show');
            <?php endif; ?>
        });
    </script>
</body>
</html>


