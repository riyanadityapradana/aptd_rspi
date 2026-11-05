<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
require_once $root . '/config/akses.php';
require_once $root . '/main_app/page/t_admin/users_helper.php';
date_default_timezone_set('Asia/Makassar');
$checks = 0;
function access_check($ok, $message)
{
    global $checks;
    if (!$ok) { throw new RuntimeException($message); }
    $checks++;
}
function access_rejects($callback, $message)
{
    try { $callback(); } catch (InvalidArgumentException $exception) { access_check(true, $message); return; }
    throw new RuntimeException($message);
}
foreach (aptd_get_access_map() as $level => $pages) {
    foreach (array_keys(aptd_get_routes()) as $page) {
        access_check(aptd_can_access($level, $page) === aptd_role_can_access($level, $page), 'Akses bawaan tetap sama');
    }
}
$catalog = aptd_extra_menu_catalog();
access_check(!isset($catalog['users_admin'], $catalog['beranda']), 'Admin dan beranda bukan akses tambahan');
foreach ($catalog as $menu => $item) {
    access_check(isset(aptd_get_routes()[$menu]), 'Menu memiliki route');
    access_check(in_array($menu, aptd_extra_access_pages([$menu]), true), 'Submenu utama terbawa');
}
access_check(aptd_validate_extra_menus(['data_vaksinasi_haji', 'data_vaksinasi_haji']) === ['data_vaksinasi_haji'], 'Duplikasi menu dinormalisasi');
foreach (['*', ['users_admin'], ['export_vaksinasi_haji_pdf'], ['tidak_ada'], [['data_vaksinasi_haji']]] as $bad) {
    access_rejects(function () use ($bad) { aptd_validate_extra_menus($bad); }, 'Payload akses palsu ditolak');
}
foreach ([null, '', 'invalid JSON', '["users_admin","*",["data_vaksinasi_haji"]]'] as $json) {
    access_check(aptd_decode_extra_menus($json) === [], 'Data akses rusak tidak memberi tambahan');
}
access_check(in_array('export_vaksinasi_haji_pdf', aptd_extra_access_pages(['data_vaksinasi_haji']), true), 'PDF terkait terbawa');
access_check(!in_array('export_diare', aptd_extra_access_pages(['data_vaksinasi_haji']), true), 'Ekspor lain tidak ikut terbuka');
access_check(aptd_direct_report_pages('page/t_klinis/t_kode_penyakit/export_kode_penyakit.php', ['mode' => 'ranap_bedah']) === ['data_pasien_kode_penyakit_bedah_ranap'], 'Ekspor diperiksa per mode');
access_check(aptd_direct_report_pages('page/t_klinis/t_kode_penyakit/kode_penyakit_details.php', ['mode' => 'invalid']) === [], 'Mode palsu ditolak');
access_check(aptd_direct_report_pages('page/t_non_klinis/keuangan/laporan_keuangan_ranap_helper.php', []) === [], 'Helper bukan endpoint');

if (in_array('--db', $argv, true)) {
    require $root . '/config/koneksi.php';
    // Hanya koneksi ini melihat tabel sementara, bukan akun produksi.
    $mysqli->query('CREATE TEMPORARY TABLE tb_users (id_users INT PRIMARY KEY AUTO_INCREMENT, nama_lengkap VARCHAR(100), username VARCHAR(100) UNIQUE, password VARCHAR(100), jabatan VARCHAR(100), level VARCHAR(30), tgl_log DATETIME, jam_log TIME, akses_menu TEXT NULL) ENGINE=InnoDB');
    try {
        $mysqli->query("INSERT INTO tb_users (id_users,nama_lengkap,username,password,jabatan,level) VALUES (1,'Admin Uji','admin_uji',MD5('secret12'),'Uji','admin'),(2,'Medis Uji','medis_uji',MD5('secret12'),'Uji','medis')");
        $_SESSION = ['login_aptd_rspi' => true, 'id_user' => 1, 'level' => 'admin', 'user_admin_csrf' => 'fixture-csrf'];
        aptd_access_current_user(true);
        $levels = array_keys(aptd_get_access_map());
        $input = ['action' => 'create', 'nama_lengkap' => 'User Uji', 'username' => 'akses_uji', 'password' => 'secret12', 'jabatan' => 'Unit Uji', 'level' => 'users', 'akses_menu' => ['data_vaksinasi_haji'], 'csrf_token' => 'fixture-csrf'];
        aptd_admin_write_user($mysqli, $input, 1, $levels);
        $created = $mysqli->query("SELECT * FROM tb_users WHERE username='akses_uji'")->fetch_assoc();
        access_check(aptd_decode_extra_menus($created['akses_menu']) === ['data_vaksinasi_haji'], 'Tambahan disimpan saat create');
        $userId = (int) $created['id_users'];
        $_SESSION = ['login_aptd_rspi' => true, 'id_user' => $userId, 'level' => 'users'];
        aptd_access_current_user(true);
        access_check(aptd_can_access('users', 'data_vaksinasi_haji') && aptd_can_access('users', 'export_vaksinasi_haji_pdf'), 'Akun tambahan dapat membuka halaman dan PDF');
        access_check(aptd_can_access('users', 'beranda') && !aptd_can_access('users', 'diare_data') && !aptd_can_access('users', 'users_admin'), 'Bawaan tetap ada, menu lain tetap tertutup');
        access_rejects(function () use ($mysqli, $input, $userId, $levels) { aptd_admin_write_user($mysqli, $input, $userId, $levels); }, 'Nonadmin tidak dapat menyimpan user');
        $_SESSION = ['login_aptd_rspi' => true, 'id_user' => 1, 'level' => 'admin', 'user_admin_csrf' => 'fixture-csrf'];
        aptd_access_current_user(true);
        $update = array_merge($input, ['action' => 'update', 'id_users' => (string) $userId, 'password' => '', 'level' => 'medis', 'akses_menu' => ['data_vaksinasi_haji', 'adime_gizi']]);
        aptd_admin_write_user($mysqli, $update, 1, $levels);
        access_check($mysqli->query('SELECT password FROM tb_users WHERE id_users=' . $userId)->fetch_assoc()['password'] === $created['password'], 'Password kosong tidak mengubah hash');
        $_SESSION = ['login_aptd_rspi' => true, 'id_user' => $userId, 'level' => 'users'];
        aptd_access_current_user(true);
        access_check($_SESSION['level'] === 'medis' && aptd_can_access('users', 'diare_data') && aptd_can_access('users', 'adime_gizi'), 'Level terbaru dan tambahan digabung tanpa login ulang');
        $_SESSION = ['login_aptd_rspi' => true, 'id_user' => 1, 'level' => 'admin', 'user_admin_csrf' => 'fixture-csrf'];
        aptd_access_current_user(true);
        $update['akses_menu'] = [];
        aptd_admin_write_user($mysqli, $update, 1, $levels);
        $_SESSION = ['login_aptd_rspi' => true, 'id_user' => $userId, 'level' => 'medis'];
        aptd_access_current_user(true);
        access_check(aptd_can_access('medis', 'diare_data') && !aptd_can_access('medis', 'data_vaksinasi_haji') && !aptd_can_access('medis', 'export_vaksinasi_haji_pdf'), 'Pencabutan tambahan menutup halaman dan PDF, bawaan tetap ada');
        $_SESSION = ['login_aptd_rspi' => true, 'id_user' => 1, 'level' => 'admin', 'user_admin_csrf' => 'fixture-csrf'];
        aptd_access_current_user(true);
        foreach ([['csrf_token' => 'bad'], ['akses_menu' => ['users_admin']], ['akses_menu' => 'data_vaksinasi_haji'], ['username' => 'medis_uji'], ['id_users' => '99999', 'action' => 'update']] as $bad) {
            $bad = array_merge($update, $bad);
            access_rejects(function () use ($mysqli, $bad, $levels) { aptd_admin_write_user($mysqli, $bad, 1, $levels); }, 'Simpan invalid dibatalkan');
        }
        access_check($mysqli->query('SELECT COUNT(*) AS total FROM tb_users')->fetch_assoc()['total'] === '3', 'Simpan gagal tidak membuat akun tambahan');
        $changeSelf = array_merge($update, ['id_users' => '1']);
        access_rejects(function () use ($mysqli, $changeSelf, $levels) { aptd_admin_write_user($mysqli, $changeSelf, 1, $levels); }, 'Admin tidak menurunkan dirinya sendiri');
        aptd_admin_write_user($mysqli, ['action' => 'delete', 'id_users' => (string) $userId, 'csrf_token' => 'fixture-csrf'], 1, $levels);
        $_SESSION = ['login_aptd_rspi' => true, 'id_user' => $userId, 'level' => 'admin'];
        aptd_access_current_user(true);
        access_check(!aptd_can_access('admin', 'beranda') && !aptd_can_access('admin', 'users_admin'), 'Akun terhapus tidak dapat memakai sesi lama');
    } finally {
        $mysqli->query('DROP TEMPORARY TABLE tb_users');
        $_SESSION = [];
        aptd_access_current_user(true);
    }
    echo "Integrasi SQL: tabel sementara saja, tanpa perubahan akun produksi.\n";
}
echo 'PASS ' . $checks . " pemeriksaan akses.\n";
