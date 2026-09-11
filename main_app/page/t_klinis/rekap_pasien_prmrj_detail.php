<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$projectRoot = dirname(dirname(dirname(__DIR__)));
require_once $projectRoot . '/config/akses.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
$currentLevel = isset($_SESSION['level']) ? (string) $_SESSION['level'] : '';
if (!isset($_SESSION['login_aptd_rspi']) || $_SESSION['login_aptd_rspi'] !== true
    || !aptd_can_access($currentLevel, 'rekap_pasien_prmrj_detail')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

require_once $projectRoot . '/config/koneksi.php';
require_once __DIR__ . '/rekap_pasien_prmrj_helper.php';

$medicalRecord = isset($_GET['no_rkm_medis']) ? trim((string) $_GET['no_rkm_medis']) : '';
if ($medicalRecord === '' || strlen($medicalRecord) > 15) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'No. Rekam Medis tidak valid.']);
    exit;
}

try {
    $details = aptd_prmrj_fetch_latest_details($mysqli, $medicalRecord, 5);
    echo json_encode(['success' => true, 'data' => $details], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log('Detail Rekap Pasien PRMRJ: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Rincian kunjungan belum dapat dimuat.']);
}
