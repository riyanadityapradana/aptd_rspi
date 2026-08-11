<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$projectRoot = dirname(dirname(dirname(dirname(__DIR__))));
require_once $projectRoot . '/config/koneksi.php';
require_once $projectRoot . '/config/akses.php';
require_once __DIR__ . '/indikator_waktu_tunggu_poli_task_2_5_helper.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function aptd_wt25_json_response($payload, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$isLoggedIn = isset($_SESSION['login_aptd_rspi']) && $_SESSION['login_aptd_rspi'] === true;
$level = isset($_SESSION['level']) ? (string) $_SESSION['level'] : '';

if (!$isLoggedIn) {
    aptd_wt25_json_response(['success' => false, 'message' => 'Sesi login telah berakhir. Silakan login kembali.'], 401);
}
if (!aptd_can_access($level, 'indikator_waktu_tunggu_poli_task_2_5')) {
    aptd_wt25_json_response(['success' => false, 'message' => 'Anda tidak memiliki hak akses ke laporan ini.'], 403);
}

list($filters, $errors) = aptd_wt25_filter_from_request($_GET);
if (!empty($errors)) {
    aptd_wt25_json_response(['success' => false, 'message' => implode(' ', $errors), 'errors' => $errors], 422);
}

try {
    $startedAt = microtime(true);
    $report = aptd_wt25_build_report($mysqli, $filters, true);
    $report['query_seconds'] = round(microtime(true) - $startedAt, 3);
    aptd_wt25_json_response(array_merge(['success' => true], $report));
} catch (Throwable $exception) {
    error_log('Indikator WT Poli Task 2-5 API: ' . $exception->getMessage());
    aptd_wt25_json_response([
        'success' => false,
        'message' => 'Data indikator belum dapat dimuat. Silakan coba kembali atau hubungi Unit IT RSPI.',
    ], 500);
}
