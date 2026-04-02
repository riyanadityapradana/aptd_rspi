<?php
require_once dirname(__DIR__) . '/export_excel_helper.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/koneksi.php';
$conn = $mysqli;

$mode = isset($_POST['mode']) ? trim((string) $_POST['mode']) : 'ralan_non_bedah';
$tgl_awal = isset($_POST['tgl_awal']) && $_POST['tgl_awal'] !== '' ? $_POST['tgl_awal'] : date('Y-m-01');
$tgl_akhir = isset($_POST['tgl_akhir']) && $_POST['tgl_akhir'] !== '' ? $_POST['tgl_akhir'] : date('Y-m-d');
$kode_penyakit = isset($_POST['kode_penyakit']) ? strtoupper(trim((string) $_POST['kode_penyakit'])) : '';

$isRanap = strpos($mode, 'ranap') === 0;
$isBedah = in_array($mode, ['ralan_bedah', 'ranap_bedah'], true);
$serviceLabel = $isRanap ? 'Rawat Inap' : 'Rawat Jalan';
$categoryLabel = $isBedah ? 'Bedah' : 'Non Bedah';
$title = 'Data Pasien Berdasarkan Kode Penyakit ' . $categoryLabel . ' ' . $serviceLabel;

$rows = [];
$unit_summary = [];
$selectedDiseaseName = '-';

if ($kode_penyakit !== '') {
    if ($isRanap) {
        $sql = "SELECT DISTINCT rp.no_rawat, rp.no_rkm_medis, ps.nm_pasien, ps.jk, dp.kd_penyakit, py.nm_penyakit, IFNULL(pl.nm_poli, '-') AS unit_layanan, IFNULL(ki.daftar_kamar, '-') AS lokasi, ki.tgl_masuk_awal AS tgl_layanan, ki.tgl_keluar_akhir AS tgl_keluar, ki.lama_dirawat FROM diagnosa_pasien dp INNER JOIN reg_periksa rp ON dp.no_rawat = rp.no_rawat INNER JOIN pasien ps ON rp.no_rkm_medis = ps.no_rkm_medis INNER JOIN penyakit py ON dp.kd_penyakit = py.kd_penyakit LEFT JOIN poliklinik pl ON rp.kd_poli = pl.kd_poli INNER JOIN (SELECT no_rawat, GROUP_CONCAT(DISTINCT kd_kamar ORDER BY kd_kamar SEPARATOR ', ') AS daftar_kamar, MIN(tgl_masuk) AS tgl_masuk_awal, MAX(tgl_keluar) AS tgl_keluar_akhir, MAX(IFNULL(lama, 0)) AS lama_dirawat FROM kamar_inap GROUP BY no_rawat) ki ON rp.no_rawat = ki.no_rawat WHERE rp.status_lanjut = 'Ranap' AND dp.kd_penyakit LIKE CONCAT(?, '%') AND dp.prioritas = '1' AND ki.tgl_masuk_awal BETWEEN ? AND ? AND LOWER(ps.nm_pasien) NOT LIKE '%test%' AND LOWER(ps.nm_pasien) NOT LIKE '%tes%' AND LOWER(ps.nm_pasien) NOT LIKE '%coba%'";
        $sql .= $isBedah ? " AND LOWER(IFNULL(pl.nm_poli, '')) LIKE '%bedah%'" : " AND LOWER(IFNULL(pl.nm_poli, '')) NOT LIKE '%bedah%'";
        $sql .= " ORDER BY ki.tgl_masuk_awal DESC, ps.nm_pasien ASC";
    } else {
        $sql = "SELECT rp.no_rawat, rp.no_rkm_medis, ps.nm_pasien, ps.jk, dp.kd_penyakit, py.nm_penyakit, IFNULL(pl.nm_poli, '-') AS unit_layanan, DATE(rp.tgl_registrasi) AS tgl_layanan FROM diagnosa_pasien dp INNER JOIN reg_periksa rp ON dp.no_rawat = rp.no_rawat INNER JOIN pasien ps ON rp.no_rkm_medis = ps.no_rkm_medis INNER JOIN penyakit py ON dp.kd_penyakit = py.kd_penyakit LEFT JOIN poliklinik pl ON rp.kd_poli = pl.kd_poli WHERE rp.status_lanjut = 'Ralan' AND dp.kd_penyakit LIKE CONCAT(?, '%') AND DATE(rp.tgl_registrasi) BETWEEN ? AND ? AND LOWER(ps.nm_pasien) NOT LIKE '%test%' AND LOWER(ps.nm_pasien) NOT LIKE '%tes%' AND LOWER(ps.nm_pasien) NOT LIKE '%coba%'";
        $sql .= $isBedah ? " AND LOWER(IFNULL(pl.nm_poli, '')) LIKE '%bedah%'" : " AND LOWER(IFNULL(pl.nm_poli, '')) NOT LIKE '%bedah%'";
        $sql .= " ORDER BY rp.tgl_registrasi DESC, ps.nm_pasien ASC";
    }

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('sss', $kode_penyakit, $tgl_awal, $tgl_akhir);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
            $selectedDiseaseName = $row['nm_penyakit'];
            $unitKey = $isRanap ? $row['lokasi'] : $row['unit_layanan'];
            if (!isset($unit_summary[$unitKey])) {
                $unit_summary[$unitKey] = 0;
            }
            $unit_summary[$unitKey]++;
        }
        $stmt->close();
    }
}

arsort($unit_summary);
$tableRows = [];
foreach ($rows as $index => $row) {
    $line = [$index + 1, $row['no_rawat'], $row['no_rkm_medis'], $row['nm_pasien'], $row['jk'], $row['tgl_layanan'], $isRanap ? $row['lokasi'] : $row['unit_layanan']];
    if ($isRanap) {
        $line[] = $row['tgl_keluar'];
        $line[] = (float) $row['lama_dirawat'];
    }
    $line[] = $row['kd_penyakit'];
    $line[] = $row['nm_penyakit'];
    $tableRows[] = $line;
}

list($spreadsheet, $sheet) = aptd_excel_create($title, 'Kode Penyakit: ' . ($kode_penyakit === '' ? '-' : $kode_penyakit) . ' | Periode: ' . $tgl_awal . ' s.d. ' . $tgl_akhir, 'Data');
$headers = ['No', 'No Rawat', 'No RM', 'Nama Pasien', 'JK', $isRanap ? 'Tanggal Masuk' : 'Tanggal Registrasi', $isRanap ? 'Kamar' : 'Poliklinik'];
if ($isRanap) {
    $headers[] = 'Tanggal Keluar';
    $headers[] = 'Lama Dirawat';
}
$headers[] = 'Kode Penyakit';
$headers[] = 'Nama Penyakit';
aptd_excel_render_table($sheet, $headers, $tableRows, 4);
aptd_excel_add_bar_chart_sheet($spreadsheet, 'Grafik Sebaran', 'Sebaran ' . ($isRanap ? 'Kamar' : 'Poliklinik'), $isRanap ? 'Kamar' : 'Poliklinik', array_keys($unit_summary), ['Jumlah Pasien' => array_values($unit_summary)], true);

aptd_excel_output($spreadsheet, 'Kode_Penyakit_' . $mode . '_' . date('Ymd_His') . '.xlsx');
