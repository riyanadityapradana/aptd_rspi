<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once dirname(dirname(dirname(__DIR__))) . '/config/koneksi.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/akses.php';

function kode_penyakit_bind_params($stmt, $types, array $params)
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

$modeMap = array(
    'ralan_non_bedah' => 'data_pasien_kode_penyakit_non_bedah_ralan',
    'ralan_bedah' => 'data_pasien_kode_penyakit_bedah_ralan',
    'ranap_non_bedah' => 'data_pasien_kode_penyakit_non_bedah_ranap',
    'ranap_bedah' => 'data_pasien_kode_penyakit_bedah_ranap',
);

$mode = isset($_GET['mode']) ? trim((string) $_GET['mode']) : '';
$kodePenyakit = isset($_GET['kode_penyakit']) ? strtoupper(trim((string) $_GET['kode_penyakit'])) : '';
$tglAwal = isset($_GET['tgl_awal']) ? trim((string) $_GET['tgl_awal']) : '';
$tglAkhir = isset($_GET['tgl_akhir']) ? trim((string) $_GET['tgl_akhir']) : '';
$filter = isset($_GET['filter']) ? trim((string) $_GET['filter']) : 'total';
$gender = isset($_GET['gender']) ? strtoupper(trim((string) $_GET['gender'])) : '';
$unit = isset($_GET['unit']) ? trim((string) $_GET['unit']) : '';

if (!isset($modeMap[$mode]) || $kodePenyakit === '' || $tglAwal === '' || $tglAkhir === '') {
    echo json_encode(array('status' => 'error', 'message' => 'Parameter rincian tidak lengkap.'));
    exit;
}

$levelLogin = isset($_SESSION['level']) ? $_SESSION['level'] : '';
if (!aptd_can_access($levelLogin, $modeMap[$mode])) {
    http_response_code(403);
    echo json_encode(array('status' => 'error', 'message' => 'Anda tidak memiliki hak akses ke modul ini.'));
    exit;
}

if ($tglAwal > $tglAkhir) {
    $tmp = $tglAwal;
    $tglAwal = $tglAkhir;
    $tglAkhir = $tmp;
}

$isRanap = strpos($mode, 'ranap') === 0;
$isBedah = in_array($mode, array('ralan_bedah', 'ranap_bedah'), true);
$params = array($kodePenyakit, $tglAwal, $tglAkhir);
$types = 'sss';

if ($isRanap) {
    $sql = "SELECT DISTINCT
                rp.no_rawat AS 'No. Rawat',
                rp.no_rkm_medis AS 'No. RM',
                ps.nm_pasien AS 'Nama Pasien',
                ps.jk AS 'JK',
                dp.kd_penyakit AS 'Kode Penyakit',
                py.nm_penyakit AS 'Nama Penyakit',
                IFNULL(pl.nm_poli, '-') AS 'Poliklinik',
                IFNULL(ki.daftar_kamar, '-') AS 'Kamar',
                ki.tgl_masuk_awal AS 'Tanggal Masuk',
                ki.tgl_keluar_akhir AS 'Tanggal Keluar',
                ki.lama_dirawat AS 'Lama Dirawat'
            FROM diagnosa_pasien dp
            INNER JOIN reg_periksa rp ON dp.no_rawat = rp.no_rawat
            INNER JOIN pasien ps ON rp.no_rkm_medis = ps.no_rkm_medis
            INNER JOIN penyakit py ON dp.kd_penyakit = py.kd_penyakit
            LEFT JOIN poliklinik pl ON rp.kd_poli = pl.kd_poli
            INNER JOIN (
                SELECT 
                    no_rawat,
                    GROUP_CONCAT(DISTINCT kd_kamar ORDER BY kd_kamar SEPARATOR ', ') AS daftar_kamar,
                    MIN(tgl_masuk) AS tgl_masuk_awal,
                    MAX(tgl_keluar) AS tgl_keluar_akhir,
                    MAX(IFNULL(lama, 0)) AS lama_dirawat
                FROM kamar_inap
                GROUP BY no_rawat
            ) ki ON rp.no_rawat = ki.no_rawat
            WHERE rp.status_lanjut = 'Ranap'
              AND dp.kd_penyakit LIKE CONCAT(?, '%')
              AND dp.prioritas = '1'
              AND ki.tgl_masuk_awal BETWEEN ? AND ?
              AND LOWER(ps.nm_pasien) NOT LIKE '%test%'
              AND LOWER(ps.nm_pasien) NOT LIKE '%tes%'
              AND LOWER(ps.nm_pasien) NOT LIKE '%coba%'";
} else {
    $sql = "SELECT
                rp.no_rawat AS 'No. Rawat',
                rp.no_rkm_medis AS 'No. RM',
                ps.nm_pasien AS 'Nama Pasien',
                ps.jk AS 'JK',
                dp.kd_penyakit AS 'Kode Penyakit',
                py.nm_penyakit AS 'Nama Penyakit',
                IFNULL(pl.nm_poli, '-') AS 'Poliklinik',
                DATE(rp.tgl_registrasi) AS 'Tanggal Registrasi'
            FROM diagnosa_pasien dp
            INNER JOIN reg_periksa rp ON dp.no_rawat = rp.no_rawat
            INNER JOIN pasien ps ON rp.no_rkm_medis = ps.no_rkm_medis
            INNER JOIN penyakit py ON dp.kd_penyakit = py.kd_penyakit
            LEFT JOIN poliklinik pl ON rp.kd_poli = pl.kd_poli
            WHERE rp.status_lanjut = 'Ralan'
              AND dp.kd_penyakit LIKE CONCAT(?, '%')
              AND DATE(rp.tgl_registrasi) BETWEEN ? AND ?
              AND LOWER(ps.nm_pasien) NOT LIKE '%test%'
              AND LOWER(ps.nm_pasien) NOT LIKE '%tes%'
              AND LOWER(ps.nm_pasien) NOT LIKE '%coba%'";
}

if ($isBedah) {
    $sql .= " AND LOWER(IFNULL(pl.nm_poli, '')) LIKE '%bedah%'";
} else {
    $sql .= " AND LOWER(IFNULL(pl.nm_poli, '')) NOT LIKE '%bedah%'";
}

if ($filter === 'gender' && ($gender === 'L' || $gender === 'P')) {
    $sql .= " AND ps.jk = ?";
    $params[] = $gender;
    $types .= 's';
} elseif ($filter === 'unit' && $unit !== '') {
    $sql .= $isRanap ? " AND IFNULL(ki.daftar_kamar, '-') = ?" : " AND IFNULL(pl.nm_poli, '-') = ?";
    $params[] = $unit;
    $types .= 's';
}

$sql .= $isRanap ? " ORDER BY ki.tgl_masuk_awal DESC, ps.nm_pasien ASC" : " ORDER BY rp.tgl_registrasi DESC, ps.nm_pasien ASC";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    echo json_encode(array('status' => 'error', 'message' => 'Gagal menyiapkan query rincian: ' . $mysqli->error));
    exit;
}

kode_penyakit_bind_params($stmt, $types, $params);
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
