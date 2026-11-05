<?php
session_start();
require_once('../config/koneksi.php');
require_once('../config/akses.php');

if (!isset($_SESSION['login_aptd_rspi']) || $_SESSION['login_aptd_rspi'] !== true) {
    header('Location: ../login/login.php');
    exit;
}

aptd_access_current_user();
$page = isset($_GET['page']) && $_GET['page'] !== '' ? $_GET['page'] : 'beranda';
$namaLogin = isset($_SESSION['nama_lengkap']) ? $_SESSION['nama_lengkap'] : 'Pengguna';
$levelLogin = isset($_SESSION['level']) ? $_SESSION['level'] : '-';

if (strpos($page, 'export_') === 0) {
    $routes = aptd_get_routes();

    if (!isset($routes[$page])) {
        http_response_code(404);
        echo 'Halaman export yang diminta tidak ditemukan.';
        exit;
    }

    if (!aptd_can_access($levelLogin, $page)) {
        http_response_code(403);
        echo 'Anda tidak memiliki hak akses ke export ini.';
        exit;
    }

    require_once($routes[$page]);
    exit;
}

function canAccessPage($pageName)
{
    global $levelLogin;
    return aptd_can_access($levelLogin, $pageName);
}

function isActivePage($currentPage, $pageNames)
{
    if (!is_array($pageNames)) {
        $pageNames = [$pageNames];
    }

    return in_array($currentPage, $pageNames, true) ? ' active' : '';
}

function renderMenuLink($pageName, $label, $currentPage, $className = 'nav-link', $inlineStyle = 'color: white')
{
    if (!canAccessPage($pageName)) {
        return;
    }

    $activeClass = isActivePage($currentPage, $pageName);
    echo '<li class="nav-item' . $activeClass . '">';
    echo '<a class="' . htmlspecialchars($className, ENT_QUOTES, 'UTF-8') . '" href="main_app.php?page=' . rawurlencode($pageName) . '" style="' . htmlspecialchars($inlineStyle, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
    echo '</li>';
}

function isMenuItemVisible($item)
{
    return (!isset($item['visible']) || $item['visible'] !== false)
        && canAccessPage($item['page']);
}

function renderDropdownMenu($id, $label, $groups, $currentPage)
{
    $hasVisibleItem = false;
    foreach ($groups as $group) {
        foreach ($group as $item) {
            if (isMenuItemVisible($item)) {
                $hasVisibleItem = true;
                break 2;
            }
        }
    }

    if (!$hasVisibleItem) {
        return;
    }

    $allPages = [];
    foreach ($groups as $group) {
        foreach ($group as $item) {
            if (isMenuItemVisible($item)) {
                $allPages[] = $item['page'];
            }
        }
    }

    $activeClass = isActivePage($currentPage, $allPages);
    echo '<li class="nav-item dropdown' . $activeClass . '">';
    echo '<a class="nav-link dropdown-toggle" href="#" id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
    echo '<div class="dropdown-menu" aria-labelledby="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '">';

    $printedItem = false;
    foreach ($groups as $group) {
        $visibleGroup = array_values(array_filter($group, function ($item) {
            return isMenuItemVisible($item);
        }));

        if (empty($visibleGroup)) {
            continue;
        }

        if ($printedItem) {
            echo '<hr style="height: 2px; background-image: linear-gradient(to right, rgba(0,0,0,0), rgba(102,102,102,1), rgba(0,0,0,0) )">';
        }

        foreach ($visibleGroup as $item) {
            echo '<a class="dropdown-item" href="main_app.php?page=' . rawurlencode($item['page']) . '">' . htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') . '</a>';
        }

        $printedItem = true;
    }

    echo '</div>';
    echo '</li>';
}

$menuDefinitions = aptd_get_menu_definitions();
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <title>APLIKASI TARIKAN DATA RSPI</title>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <meta name="description" content="">
        <meta name="author" content="">

        <link rel="icon" type="image/png" href="../assets/assets-admin/img/logo1.png?v=20260706">
        <link href="../assets/assets-admin/css/bootstrap.min.css" rel="stylesheet">
        <link href="../assets/assets-admin/css/dataTables.bootstrap4.min.css" rel="stylesheet">
        <link href="../assets/assets-admin/css/style.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
        <link href="../assets/assets-admin/css/bootstrap-datepicker.css" rel="stylesheet">
        <link href="../assets/assets-admin/css/glyphicon.css" rel="stylesheet" type="text/css">
        <link href="https://unpkg.com/gijgo@1.9.13/css/gijgo.min.css" rel="stylesheet" type="text/css">

        <style type="text/css">
        body { margin-top: 58px; margin-bottom: 0; background: url(../assets/assets-admin/img/colores_claros.jpeg); background-repeat: no-repeat; background-attachment: fixed; background-size: 100% 100%; }
        .container { max-width: 100%; padding-left: 10px; padding-right: 10px; }
        .navbar { min-height: 58px; }
        .navbar-brand { display: inline-flex; align-items: center; white-space: nowrap; font-size: 16px; }
        .navbar-brand-logo { mix-blend-mode: multiply; }
        .navbar-nav { align-items: center; }
        .navbar-nav .nav-link { white-space: nowrap; font-size: 13px; padding-left: 8px; padding-right: 8px; }
        .dropdown-menu { font-size: 13px; }
        .nav-user-badge { display: inline-flex; align-items: center; padding: 8px 12px; margin-right: 6px; border-radius: 999px; background: rgba(255,255,255,0.18); color: #f4fbff; font-size: 12px; white-space: nowrap; }
        .nav-logout-link { font-weight: 600; color: #fff6b3 !important; }
        @media (min-width: 992px) and (max-width: 1280px) { .navbar-brand { font-size: 14px; } .navbar-nav .nav-link { font-size: 12px; padding-left: 6px; padding-right: 6px; } .nav-user-badge { max-width: 230px; overflow: hidden; text-overflow: ellipsis; } }
        @media (max-width: 991.98px) { .navbar-nav { align-items: stretch; } .navbar-nav .nav-link { font-size: 14px; padding: 8px 10px; } .dropdown-menu { max-height: 70vh; overflow-y: auto; } }
        @media (max-width: 768px) { .container { padding-left: 2px; padding-right: 2px; } .main-footer marquee { font-size: 13px !important; } .navbar-brand img { height: 28px !important; } .nav-user-badge { display: block; margin: 10px 0 4px 0; white-space: normal; } }
        @media (max-width: 480px) { .main-footer marquee { font-size: 11px !important; } .navbar-brand img { height: 22px !important; } }
        .dataTables_wrapper .wrapper { max-height: 550px; overflow-y: auto; }
        </style>
    </head>
    <body>
        <nav class="navbar navbar-expand-lg fixed-top navbar-dark" style="background-color:rgba(109,156,227,1)">
            <a class="navbar-brand page-scroll" href="main_app.php?page=beranda"><img src="../assets/assets-admin/img/logo1.png?v=20260706" height="35" class="navbar-brand-logo d-inline-block align-top" alt="Logo Pelita Insani Martapura" style="padding-top: 0">&nbsp;&nbsp;Beranda</a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#myNavbar" aria-controls="navbarNavAltMarkup" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="myNavbar">
                <ul class="navbar-nav mr-auto mt-2 mt-lg-0">
                    <?php renderDropdownMenu('navbarDropdownRalan', 'Master Kunjungan Ralan', $menuDefinitions['menuKunjunganRalan'], $page); ?>
                    <?php renderDropdownMenu('navbarDropdownRanap', 'Master Kunjungan Ranap', $menuDefinitions['menuKunjunganRanap'], $page); ?>
                    <?php if (in_array($levelLogin, ['rekammedis', 'keuangan'], true) || aptd_has_extra_menu('input_data_claim')) { renderMenuLink('input_data_claim', 'Input Data Claim', $page); } ?>
                    <?php if ($levelLogin === 'perawat' || aptd_has_extra_menu('diagnosa_awal_sementara_ranap')) { renderMenuLink('diagnosa_awal_sementara_ranap', 'Input Diagnosa Awal', $page); } ?>
                    <?php renderDropdownMenu('navbarDropdownPenyakit', 'Master Penyakit', $menuDefinitions['menuPenyakit'], $page); ?>
                    <?php renderDropdownMenu('navbarDropdownDataKlinis', 'Data Klinis', $menuDefinitions['menuDataKlinis'], $page); ?>
                    <?php renderDropdownMenu('navbarDropdownDataNonKlinis', 'Data Non Klinis', $menuDefinitions['menuDataNonKlinis'], $page); ?>
                    <?php renderDropdownMenu('navbarDropdownSirs', 'SIRS 6.3', $menuDefinitions['menuSirs'], $page); ?>
                    <?php renderDropdownMenu('navbarDropdownAdmin', 'Admin', $menuDefinitions['menuAdmin'], $page); ?>
                    <?php renderDropdownMenu('navbarDropdownGizi', 'Master Gizi', $menuDefinitions['menuGizi'], $page); ?>
                </ul>
                <div class="form-inline my-2 my-lg-0">
                    <span class="nav-user-badge"><span class="glyphicon glyphicon-user" style="color:#ffffff"></span>&nbsp;&nbsp;<?php echo htmlspecialchars($namaLogin, ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($levelLogin, ENT_QUOTES, 'UTF-8'); ?>)</span>
                    <a class="nav-link nav-logout-link" href="../login/logout.php"><span class="glyphicon glyphicon-log-out" style="color:#ffffff"></span>&nbsp;&nbsp;Logout</a>
                </div>
            </div>
        </nav>

        <div class="container">
            <div class="col">
                <div id="pages"><?php require_once('content.php'); ?></div>
                <br>
                <footer class="main-footer" style="position:fixed;bottom:0;left:0;right:0;width:100vw;background:#d9dde0;color:#00070c;z-index:9999;padding:0; height:40px; display:flex; align-items:center;"><div style="overflow:hidden;white-space:nowrap;width:100vw;"><marquee behavior="scroll" direction="left" scrollamount="6" style="font-size:17px;padding:8px 0;min-width:100vw;">&copy; <?= date('Y') ?> IT-RSPI | Aplikasi Tarikan Data RSPI. Dikembangkan oleh Tim IT-RSPI. Seluruh hak cipta dilindungi undang-undang.</marquee></div></footer>
            </div>
        </div>

        <script src="../assets/assets-admin/js/jquery-3.4.1.js"></script>
        <script src="../assets/assets-admin/js/popper.min.js"></script>
        <script src="../assets/assets-admin/js/bootstrap.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <script src="../assets/assets-admin/js/jquery.dataTables.min.js"></script>
        <script src="../assets/assets-admin/js/dataTables.bootstrap4.min.js"></script>
        <script src="../assets/assets-admin/js/bootstrap-datepicker.min.js"></script>
        <script>
            function goBack() { window.history.back(); }
            function initDataTable(selector, options) {
                if (!window.jQuery || !$.fn.DataTable || !$(selector).length) { return; }
                if ($.fn.DataTable.isDataTable(selector)) { return; }
                $(selector).DataTable(options);
            }
            $(document).ready(function() {
                if ($.fn.select2) { $('select.select2').select2({ placeholder: '-Pilih-', allowClear: true, width: '100%' }); }
                setTimeout(function() { $('#info').fadeIn('slow'); }, 0);
                setTimeout(function() { $('#info').fadeOut('slow'); }, 3000);
                var baseLanguage = { decimal: '', sEmptyTable: 'Tidak ada data yang tersedia pada tabel ini', sProcessing: 'Sedang memproses...', sLengthMenu: 'Tampilkan _MENU_ entri', sZeroRecords: 'Tidak ditemukan data yang sesuai', sInfo: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ entri', sInfoEmpty: 'Menampilkan 0 sampai 0 dari 0 entri', sInfoFiltered: '(disaring dari _MAX_ entri keseluruhan)', sInfoPostFix: '', sSearch: '', searchPlaceholder: 'Cari Data..', sUrl: '', oPaginate: { sFirst: 'Pertama', sPrevious: 'Sebelumnya', sNext: 'Selanjutnya', sLast: 'Terakhir' } };
                initDataTable('#table1', { paging: false, ordering: true, info: false, language: baseLanguage });
                ['#table2', '#table22', '#table4'].forEach(function(selector) { initDataTable(selector, { lengthChange: false, paging: true, pagingType: 'numbers', scrollCollapse: true, ordering: true, info: true, language: baseLanguage }); });
                initDataTable('#table3', { searching: true, paging: true, pagingType: 'numbers', scrollCollapse: true, ordering: true, info: false, language: baseLanguage });
            });
        </script>
    </body>
</html>

