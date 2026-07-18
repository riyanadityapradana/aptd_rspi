<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(dirname(dirname(__DIR__))) . '/config/koneksi.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/akses.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

$levelLogin = isset($_SESSION['level']) ? $_SESSION['level'] : '';
if (!isset($_SESSION['login_aptd_rspi']) || $_SESSION['login_aptd_rspi'] !== true || !aptd_can_access($levelLogin, 'diagnosa_awal_sementara_ranap')) {
    http_response_code(403);
    echo json_encode(['results' => []]);
    exit;
}

$term = '';
if (isset($_GET['q'])) {
    $term = trim((string) $_GET['q']);
} elseif (isset($_GET['term'])) {
    $term = trim((string) $_GET['term']);
}

if (strlen($term) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

$like = '%' . $term . '%';
$prefix = $term . '%';
$stmt = $mysqli->prepare("
    SELECT kd_penyakit, nm_penyakit
    FROM penyakit
    WHERE kd_penyakit LIKE ?
       OR nm_penyakit LIKE ?
    ORDER BY
        CASE WHEN kd_penyakit = ? THEN 0
             WHEN kd_penyakit LIKE ? THEN 1
             ELSE 2
        END,
        kd_penyakit ASC,
        nm_penyakit ASC
    LIMIT 30
");
$stmt->bind_param('ssss', $like, $like, $term, $prefix);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $code = (string) $row['kd_penyakit'];
    $name = (string) $row['nm_penyakit'];
    $rows[] = [
        'id' => $code,
        'text' => $code . ' - ' . $name,
    ];
}
$stmt->close();

echo json_encode(['results' => $rows]);
