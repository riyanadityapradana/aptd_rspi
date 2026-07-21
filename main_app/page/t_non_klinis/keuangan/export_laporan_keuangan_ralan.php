<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/akses.php';
require_once __DIR__ . '/laporan_keuangan_ralan_helper.php';

$level = isset($_SESSION['level']) ? $_SESSION['level'] : '';
if (!isset($_SESSION['login_aptd_rspi']) || $_SESSION['login_aptd_rspi'] !== true || !aptd_can_access($level, 'export_laporan_keuangan_ralan')) {
    http_response_code(403);
    exit('Anda tidak memiliki hak akses untuk export laporan ini.');
}

list($startDate, $endDate, $kdPoli, $filterValid, $filterMessage) = aptd_keu_ralan_filters();
if (!$filterValid) {
    http_response_code(400);
    exit($filterMessage);
}

$poliLabel = 'Semua Poliklinik';
foreach (aptd_keu_ralan_fetch_poli($mysqli) as $option) {
    if ($option['kd_poli'] === $kdPoli) {
        $poliLabel = $option['nm_poli'];
        break;
    }
}

try {
    $rows = aptd_keu_ralan_fetch_rows($mysqli, $startDate, $endDate, $kdPoli);
    $xlsx = aptd_keu_ralan_build_xlsx($rows, $startDate, $endDate, $poliLabel);
} catch (RuntimeException $exception) {
    error_log($exception->getMessage());
    http_response_code(500);
    exit('Data laporan belum dapat diexport. Silakan coba kembali atau hubungi administrator.');
}

$filename = 'laporan_keuangan_rawat_jalan_bpjs_' . str_replace('-', '', $startDate) . '_' . str_replace('-', '', $endDate) . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($xlsx));
header('Cache-Control: max-age=0, must-revalidate');
header('Pragma: public');
echo $xlsx;
exit;
?>
