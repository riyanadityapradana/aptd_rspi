<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/koneksi.php';
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/akses.php';

function kode_penyakit_ab_bind_params($stmt, $types, array $params)
{
    $bind = array($types);
    foreach ($params as $key => $value) {
        $bind[] = &$params[$key];
    }
    return call_user_func_array(array($stmt, 'bind_param'), $bind);
}

if (!isset($_SESSION['login_aptd_rspi']) || $_SESSION['login_aptd_rspi'] !== true) {
    http_response_code(403);
    echo json_encode(array('status' => 'error', 'message' => 'Akses ditolak. Silakan login terlebih dahulu.'));
    exit;
}

$levelLogin = isset($_SESSION['level']) ? $_SESSION['level'] : '';
if (!aptd_can_access($levelLogin, 'kode_penyakit_ab_ranap')) {
    http_response_code(403);
    echo json_encode(array('status' => 'error', 'message' => 'Anda tidak memiliki hak akses ke modul ini.'));
    exit;
}

$kategori = isset($_GET['kategori']) ? strtoupper(trim((string) $_GET['kategori'])) : '';
$kodePenyakit = isset($_GET['kd_penyakit']) ? strtoupper(trim((string) $_GET['kd_penyakit'])) : '';
$tglAwal = isset($_GET['tgl_awal']) ? trim((string) $_GET['tgl_awal']) : '';
$tglAkhir = isset($_GET['tgl_akhir']) ? trim((string) $_GET['tgl_akhir']) : '';

if (($kategori !== 'ANAK' && $kategori !== 'DEWASA') || $tglAwal === '' || $tglAkhir === '') {
    echo json_encode(array('status' => 'error', 'message' => 'Parameter rincian tidak lengkap.'));
    exit;
}

if ($tglAwal > $tglAkhir) {
    $tmp = $tglAwal;
    $tglAwal = $tglAkhir;
    $tglAkhir = $tmp;
}

$sql = "SELECT DISTINCT
            rp.no_rawat AS 'No. Rawat',
            rp.no_rkm_medis AS 'No. RM',
            ps.nm_pasien AS 'Nama Pasien',
            ps.tgl_lahir AS 'Tanggal Lahir',
            ps.jk AS 'Jenis Kelamin',
            IFNULL(ki_info.diagnosa_awal, '-') AS 'Diagnosa Awal'
        FROM diagnosa_pasien dp
        INNER JOIN reg_periksa rp ON dp.no_rawat = rp.no_rawat
        INNER JOIN pasien ps ON rp.no_rkm_medis = ps.no_rkm_medis
        INNER JOIN penyakit p ON dp.kd_penyakit = p.kd_penyakit
        INNER JOIN (
            SELECT no_rawat, MAX(IFNULL(diagnosa_awal, '-')) AS diagnosa_awal
            FROM kamar_inap
            GROUP BY no_rawat
        ) ki_info ON rp.no_rawat = ki_info.no_rawat
        WHERE dp.prioritas = '1'
          AND DATE(rp.tgl_registrasi) BETWEEN ? AND ?
          AND rp.status_lanjut = 'Ranap'
          AND LOWER(ps.nm_pasien) NOT LIKE '%test%'
          AND LOWER(ps.nm_pasien) NOT LIKE '%tes%'
          AND LOWER(ps.nm_pasien) NOT LIKE '%coba%'
          AND TIMESTAMPDIFF(YEAR, ps.tgl_lahir, rp.tgl_registrasi) " . ($kategori === 'ANAK' ? "< 18" : ">= 18") . "
        ORDER BY ps.nm_pasien ASC, rp.no_rawat ASC";

$params = array($tglAwal, $tglAkhir);
$types = 'ss';
if ($kodePenyakit !== '') {
    $sql = str_replace('AND DATE(rp.tgl_registrasi) BETWEEN ? AND ?', 'AND dp.kd_penyakit = ? AND DATE(rp.tgl_registrasi) BETWEEN ? AND ?', $sql);
    array_unshift($params, $kodePenyakit);
    $types = 'sss';
}

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    echo json_encode(array('status' => 'error', 'message' => 'Gagal menyiapkan query rincian: ' . $mysqli->error));
    exit;
}

kode_penyakit_ab_bind_params($stmt, $types, $params);
if (!$stmt->execute()) {
    echo json_encode(array('status' => 'error', 'message' => 'Gagal mengeksekusi query rincian: ' . $stmt->error));
    $stmt->close();
    exit;
}

$result = $stmt->get_result();
$data = array();
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
$stmt->close();

echo json_encode(array('status' => 'success', 'data' => $data));
exit;
