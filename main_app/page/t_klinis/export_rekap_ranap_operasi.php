<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once dirname(__DIR__, 3) . '/config/akses.php';
if (empty($_SESSION['login_aptd_rspi']) || $_SESSION['login_aptd_rspi'] !== true
    || !aptd_can_access(isset($_SESSION['level']) ? $_SESSION['level'] : '', 'export_rekap_ranap_operasi')) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Anda tidak memiliki hak akses ke ekspor ini.');
}
require_once dirname(__DIR__, 3) . '/config/koneksi.php';
require_once __DIR__ . '/rekap_ranap_operasi_helper.php';
require_once __DIR__ . '/rekap_ranap_operasi_excel.php';
try {
    $report = aptd_ranap_operasi_report($mysqli, $_GET);
    $book = aptd_ranap_operasi_workbook($report, aptd_ranap_operasi_bangsal($mysqli), date('Y-m-d H:i:s T'));
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    exit($exception->getMessage());
} catch (Throwable $exception) {
    error_log('APTD AR-186: ekspor operasi gagal; kode ' . $exception->getCode());
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Laporan Excel belum dapat dibuat. Silakan coba kembali atau hubungi tim IT.');
}
$filename = 'Rekap_Pasien_Ranap_Operasi_' . $report['filters']['start_date'] . '_' . $report['filters']['end_date'] . '.xlsx';
header('Cache-Control: no-store, private');
aptd_excel_output($book, $filename);
