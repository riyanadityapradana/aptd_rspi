<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/koneksi.php';
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/akses.php';

function penyakit10_bind_params($stmt, $types, array $params)
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
    'ralan' => array('page' => '10_penyakit_ralan', 'status' => 'Ralan', 'bedah' => null, 'ranap_date' => false, 'prioritas' => false, 'distinct' => false, 'exclude_test' => true),
    'ralan_bedah' => array('page' => '10_penyakit_bedah_ralan', 'status' => 'Ralan', 'bedah' => true, 'ranap_date' => false, 'prioritas' => false, 'distinct' => false, 'exclude_test' => true),
    'ralan_non_bedah' => array('page' => '10_penyakit_non_bedah_ralan', 'status' => 'Ralan', 'bedah' => false, 'ranap_date' => false, 'prioritas' => false, 'distinct' => false, 'exclude_test' => false),
    'ranap' => array('page' => '10_penyakit_ranap', 'status' => 'Ranap', 'bedah' => null, 'ranap_date' => false, 'prioritas' => false, 'distinct' => false, 'exclude_test' => true),
    'ranap_bedah' => array('page' => '10_penyakit_bedah_ranap', 'status' => 'Ranap', 'bedah' => true, 'ranap_date' => false, 'prioritas' => false, 'distinct' => false, 'exclude_test' => false),
    'ranap_non_bedah' => array('page' => '10_penyakit_non_bedah_ranap', 'status' => 'Ranap', 'bedah' => null, 'ranap_date' => true, 'prioritas' => true, 'distinct' => true, 'exclude_test' => false),
);

$mode = isset($_GET['mode']) ? trim((string) $_GET['mode']) : '';
$kodePenyakit = isset($_GET['kd_penyakit']) ? strtoupper(trim((string) $_GET['kd_penyakit'])) : '';
$tglAwal = isset($_GET['tgl_awal']) ? trim((string) $_GET['tgl_awal']) : '';
$tglAkhir = isset($_GET['tgl_akhir']) ? trim((string) $_GET['tgl_akhir']) : '';

if (!isset($modeMap[$mode]) || $kodePenyakit === '' || $tglAwal === '' || $tglAkhir === '') {
    echo json_encode(array('status' => 'error', 'message' => 'Parameter rincian tidak lengkap.'));
    exit;
}

$levelLogin = isset($_SESSION['level']) ? $_SESSION['level'] : '';
if (!aptd_can_access($levelLogin, $modeMap[$mode]['page'])) {
    http_response_code(403);
    echo json_encode(array('status' => 'error', 'message' => 'Anda tidak memiliki hak akses ke modul ini.'));
    exit;
}

if ($tglAwal > $tglAkhir) {
    $tmp = $tglAwal;
    $tglAwal = $tglAkhir;
    $tglAkhir = $tmp;
}

$cfg = $modeMap[$mode];
$bindTypes = 'ssss';
$bindParams = array($cfg['status'], $kodePenyakit, $tglAwal, $tglAkhir);

if ($mode === 'ralan') {
    $sql = "SELECT
                rp.no_rawat AS 'No. Rawat',
                rp.no_rkm_medis AS 'No. RM',
                ps.nm_pasien AS 'Nama Pasien',
                ps.jk AS 'JK',
                DATE(rp.tgl_registrasi) AS 'Tanggal Registrasi',
                IFNULL(pl.nm_poli, '-') AS 'Poliklinik',
                selected.kd_penyakit AS 'Kode Penyakit',
                py.nm_penyakit AS 'Nama Penyakit'
            FROM (
                SELECT
                    rp_filter.no_rawat,
                    SUBSTRING_INDEX(
                        GROUP_CONCAT(
                            dp_filter.kd_penyakit
                            ORDER BY
                                CASE WHEN dp_filter.prioritas > 0 THEN dp_filter.prioritas ELSE 999 END ASC,
                                dp_filter.kd_penyakit ASC
                        ),
                        ',',
                        1
                    ) AS kd_penyakit
                FROM reg_periksa rp_filter
                INNER JOIN diagnosa_pasien dp_filter ON dp_filter.no_rawat = rp_filter.no_rawat
                INNER JOIN pasien ps_filter ON ps_filter.no_rkm_medis = rp_filter.no_rkm_medis
                WHERE rp_filter.status_lanjut = 'Ralan'
                  AND dp_filter.status = 'Ralan'
                  AND UPPER(dp_filter.kd_penyakit) NOT LIKE 'Z%'
                  AND EXISTS (
                      SELECT 1
                      FROM poliklinik pl_filter
                      WHERE pl_filter.kd_poli = rp_filter.kd_poli
                        AND pl_filter.status = '1'
                  )
                  AND LOWER(ps_filter.nm_pasien) NOT LIKE '%test%'
                  AND LOWER(ps_filter.nm_pasien) NOT LIKE '%tes%'
                  AND LOWER(ps_filter.nm_pasien) NOT LIKE '%coba%'
                  AND rp_filter.tgl_registrasi >= ?
                  AND rp_filter.tgl_registrasi < DATE_ADD(?, INTERVAL 1 DAY)
                GROUP BY rp_filter.no_rawat
            ) selected
            INNER JOIN reg_periksa rp ON rp.no_rawat = selected.no_rawat
            INNER JOIN pasien ps ON ps.no_rkm_medis = rp.no_rkm_medis
            INNER JOIN penyakit py ON py.kd_penyakit = selected.kd_penyakit
            LEFT JOIN poliklinik pl ON pl.kd_poli = rp.kd_poli
            WHERE selected.kd_penyakit = ?
            ORDER BY ps.nm_pasien ASC, rp.no_rawat ASC";
    $bindTypes = 'sss';
    $bindParams = array($tglAwal, $tglAkhir, $kodePenyakit);
} else {
    $selectDistinct = $cfg['distinct'] ? 'DISTINCT ' : '';
    $dateColumn = $cfg['ranap_date'] ? 'ki.tgl_masuk' : 'DATE(rp.tgl_registrasi)';
    $sql = "SELECT {$selectDistinct}
             rp.no_rawat AS 'No. Rawat',
             rp.no_rkm_medis AS 'No. RM',
             ps.nm_pasien AS 'Nama Pasien',
            ps.jk AS 'JK',
            DATE(rp.tgl_registrasi) AS 'Tanggal Registrasi',
            IFNULL(pl.nm_poli, '-') AS 'Poliklinik',
            dp.kd_penyakit AS 'Kode Penyakit',
            py.nm_penyakit AS 'Nama Penyakit'";

    if ($cfg['status'] === 'Ranap') {
        $sql .= ",
             IFNULL(ki_info.daftar_kamar, '-') AS 'Kamar',
             IFNULL(ki_info.tgl_masuk_awal, '-') AS 'Tanggal Masuk',
             IFNULL(ki_info.tgl_keluar_akhir, '-') AS 'Tanggal Keluar'";
    }

    $sql .= "
         FROM diagnosa_pasien dp
         INNER JOIN reg_periksa rp ON dp.no_rawat = rp.no_rawat
         INNER JOIN pasien ps ON rp.no_rkm_medis = ps.no_rkm_medis
         INNER JOIN penyakit py ON dp.kd_penyakit = py.kd_penyakit
         LEFT JOIN poliklinik pl ON rp.kd_poli = pl.kd_poli";

    if ($cfg['ranap_date']) {
        $sql .= "
         INNER JOIN kamar_inap ki ON rp.no_rawat = ki.no_rawat";
    }

    if ($cfg['status'] === 'Ranap') {
        $sql .= "
         LEFT JOIN (
             SELECT
                 no_rawat,
                GROUP_CONCAT(DISTINCT kd_kamar ORDER BY kd_kamar SEPARATOR ', ') AS daftar_kamar,
                MIN(tgl_masuk) AS tgl_masuk_awal,
                MAX(tgl_keluar) AS tgl_keluar_akhir
             FROM kamar_inap
             GROUP BY no_rawat
         ) ki_info ON rp.no_rawat = ki_info.no_rawat";
    }

    $sql .= "
         WHERE rp.status_lanjut = ?
           AND dp.kd_penyakit = ?
           AND {$dateColumn} BETWEEN ? AND ?";

    if ($cfg['status'] === 'Ralan') {
        $sql .= "
           AND pl.status = '1'";
    }

    if ($cfg['exclude_test']) {
        $sql .= "
           AND ps.nm_pasien NOT LIKE '%TEST%'
           AND ps.nm_pasien NOT LIKE '%Tes%'
           AND ps.nm_pasien NOT LIKE '%Coba%'";
    }

    if ($cfg['prioritas']) {
        $sql .= " AND dp.prioritas = '1'";
    }

    if ($cfg['bedah'] === true) {
        $sql .= " AND LOWER(IFNULL(pl.nm_poli, '')) LIKE '%bedah%'";
    } elseif ($cfg['bedah'] === false) {
        $sql .= " AND LOWER(pl.nm_poli) NOT LIKE '%bedah%'";
    }

    $sql .= " ORDER BY ps.nm_pasien ASC, rp.no_rawat ASC";
}

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    echo json_encode(array('status' => 'error', 'message' => 'Gagal menyiapkan query rincian: ' . $mysqli->error));
    exit;
}

penyakit10_bind_params($stmt, $bindTypes, $bindParams);
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
