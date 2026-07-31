<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$projectRoot = dirname(dirname(dirname(dirname(__DIR__))));
require_once $projectRoot . '/config/koneksi.php';
require_once $projectRoot . '/config/akses.php';
require_once __DIR__ . '/evaluasi_task4_terawal_bpjs_helper.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function aptd_task4_json_response($payload, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$isLoggedIn = isset($_SESSION['login_aptd_rspi']) && $_SESSION['login_aptd_rspi'] === true;
$level = isset($_SESSION['level']) ? (string) $_SESSION['level'] : '';

if (!$isLoggedIn) {
    aptd_task4_json_response(['success' => false, 'message' => 'Sesi login telah berakhir. Silakan login kembali.'], 401);
}
if (!aptd_can_access($level, 'evaluasi_task4_terawal_bpjs')) {
    aptd_task4_json_response(['success' => false, 'message' => 'Anda tidak memiliki hak akses ke laporan ini.'], 403);
}

list($filters, $errors) = aptd_task4_terawal_filter_from_request($_GET);
if (!empty($errors)) {
    aptd_task4_json_response(['success' => false, 'message' => implode(' ', $errors), 'errors' => $errors], 422);
}

try {
    $report = aptd_task4_terawal_build_report($mysqli, $filters, true);
    aptd_task4_json_response(array_merge(['success' => true], $report));
} catch (Throwable $exception) {
    error_log('Evaluasi Task ID 4 Terawal API: ' . $exception->getMessage());
    aptd_task4_json_response([
        'success' => false,
        'message' => 'Data evaluasi belum dapat dimuat. Silakan coba kembali atau hubungi Unit IT RSPI.',
    ], 500);
}
