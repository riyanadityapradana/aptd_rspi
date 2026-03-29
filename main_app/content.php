<?php
$page = isset($_GET['page']) && $_GET['page'] !== '' ? $_GET['page'] : 'beranda';
$routes = aptd_get_routes();
$levelLogin = isset($_SESSION['level']) ? $_SESSION['level'] : '';

if (!isset($routes[$page])) {
    echo '<div class="alert alert-warning mt-3">Halaman yang diminta tidak ditemukan.</div>';
    return;
}

if (!aptd_can_access($levelLogin, $page)) {
    echo '<div class="alert alert-danger mt-3">Anda tidak memiliki hak akses ke menu ini.</div>';
    return;
}

require_once($routes[$page]);
?>
