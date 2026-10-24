<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$projectRoot = dirname(__DIR__, 4);
require_once $projectRoot . '/config/akses.php';
if (empty($_SESSION['login_aptd_rspi']) || $_SESSION['login_aptd_rspi'] !== true
    || !aptd_can_access(isset($_SESSION['level']) ? $_SESSION['level'] : '', 'export_vaksinasi_haji_pdf')) {
    http_response_code(403);
    exit('Anda tidak memiliki hak akses export PDF vaksinasi haji.');
}
require_once __DIR__ . '/vaksinasi_haji_helper.php';
$filters = aptd_vh_filters();
if (!$filters['valid']) {
    http_response_code(422);
    exit($filters['message']);
}
require_once $projectRoot . '/config/koneksi.php';
try {
    $paymentOptions = aptd_vh_payment_options($mysqli);
    $report = aptd_vh_fetch_report($mysqli, $filters);
    $hospital = aptd_vh_hospital($mysqli);
    require_once __DIR__ . '/vaksinasi_haji_pdf.php';
    $pdf = aptd_vh_build_pdf($report, $filters, $paymentOptions, $hospital);
} catch (Throwable $exception) {
    error_log('Export PDF Vaksinasi Haji: ' . $exception->getMessage());
    http_response_code(500);
    exit('Laporan PDF vaksinasi haji belum dapat dibuat. Silakan coba kembali.');
}
$filename = 'data_vaksinasi_haji_' . $filters['tanggal_awal'] . '_' . $filters['tanggal_akhir']
    . ($filters['jenis_bayar'] !== '' ? '_' . $filters['jenis_bayar'] : '') . '.pdf';
while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
echo $pdf;
exit;
