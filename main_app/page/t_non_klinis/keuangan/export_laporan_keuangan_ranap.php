<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/akses.php';
require_once __DIR__ . '/laporan_keuangan_ranap_helper.php';

if (!isset($_SESSION['login_aptd_rspi']) || $_SESSION['login_aptd_rspi'] !== true || !aptd_can_access(isset($_SESSION['level']) ? $_SESSION['level'] : '', 'export_laporan_keuangan_ranap')) {
    http_response_code(403);
    exit('Anda tidak memiliki hak akses untuk export laporan ini.');
}

list($month, $year, $startDate, $endDate) = aptd_keu_ranap_date_filter();
$rows = aptd_keu_ranap_fetch_report_rows($mysqli, $startDate, $endDate);
$filename = 'laporan_keuangan_ranap_' . $year . '_' . str_pad($month, 2, '0', STR_PAD_LEFT) . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo '<table border="1" style="border-collapse:collapse;font-size:11px;">';
echo '<thead>';
echo '<tr style="background:#cfd8e8;font-weight:bold;text-align:center;">';
echo '<th rowspan="2">No Rawat</th>';
echo '<th rowspan="2">No RM</th>';
echo '<th rowspan="2">Nama Pasien</th>';
echo '<th rowspan="2">SEP</th>';
echo '<th rowspan="2">Diagnosa Awal</th>';
echo '<th rowspan="2">Diagnosa Akhir</th>';
echo '<th rowspan="2">Tanggal Masuk</th>';
echo '<th rowspan="2">Tanggal Keluar</th>';
echo '<th rowspan="2">Status Pulang</th>';
echo '<th rowspan="2">DPJP</th>';
echo '<th rowspan="2">Kamar</th>';
echo '<th rowspan="2">CLAIM</th>';
echo '<th colspan="21">Jasa Dokter</th>';
echo '<th rowspan="2">JK</th>';
echo '<th rowspan="2">BHP</th>';
echo '<th rowspan="2">OBAT</th>';
echo '<th rowspan="2">Total Harga Dasar</th>';
echo '<th rowspan="2">15% Dasar</th>';
echo '<th colspan="7">Penunjang</th>';
echo '<th colspan="3">MAKAN</th>';
echo '<th rowspan="2">Phototherapy</th>';
echo '<th rowspan="2">Oksigen</th>';
echo '<th rowspan="2">Spirometri</th>';
echo '<th rowspan="2">TOTAL</th>';
echo '<th rowspan="2">MARGIN</th>';
echo '<th colspan="3">Keterangan</th>';
echo '</tr>';
echo '<tr style="background:#d9e2f3;font-weight:bold;text-align:center;">';
$headers = [
    'Dokter UGD', 'JD DPJP', 'Ket. JD DPJP', 'JD Operator', 'JD Anestesi', 'Ket. JD Anestesi', 'JD Anak', 'Ket. JD Anak', 'JD Visite', 'JD Visite Umum', 'JD Visite Spesialis', 'JD Visite Pengganti', 'Ket. JD Visite', 'JD Telp', 'JD Telpon Pengganti', 'Ket. JD Telp', 'JD USG',
    'JD Rontgen', 'JD Lab', 'JD PA', 'HD', 'LAB PK', 'LAB PA', 'Rad USG', 'Rontgen', 'Fisio', 'EKG',
    'Darah', 'Jumlah', 'Harga', 'Kali', 'DARAH', 'ALBUMIN', 'TINDAKAN'
];
foreach ($headers as $header) {
    echo '<th>' . htmlspecialchars($header, ENT_QUOTES, 'UTF-8') . '</th>';
}
echo '</tr></thead><tbody>';

foreach ($rows as $row) {
    $baseValues = [
        $row['no_rawat'],
        $row['no_rkm_medis'],
        $row['nama_pasien_umur'],
        $row['no_sep'] ?: '-',
        $row['diagnosa_awal'] ?: $row['diagnosa_sep'],
        $row['diagnosa_akhir'] ?: '-',
        $row['tanggal_masuk'],
        $row['tanggal_keluar'] ?: '-',
        $row['status_pulang'],
        $row['dpjp'] ?: '-',
        $row['kamar'] ?: '-',
        $row['claim'],
    ];

    if (empty($row['has_hitung'])) {
        $values = array_merge($baseValues, array_fill(0, 44, ''));
    } else {
        $values = array_merge($baseValues, [
        $row['dokter_ugd'],
        $row['jd_dpjp'],
        $row['ket_dpjp'],
        $row['jd_operator'],
        $row['jd_anestesi'],
        $row['ket_anestesi'],
        $row['jd_anak'],
        $row['ket_anak'],
        $row['jd_visit'],
        $row['jd_visit_umum'],
        $row['jd_visit_spesialis'],
        $row['jd_visit_pengganti'],
        $row['ket_visit'],
        $row['jd_telpon'],
        $row['jd_telpon_pengganti'],
        $row['ket_telpon'],
        $row['jd_usg'],
        $row['jd_rontgen'],
        $row['jd_lab'],
        $row['jd_pa'],
        $row['hd'],
        $row['jk'],
        $row['bhp'],
        $row['obat'],
        $row['total_harga_dasar_obat'],
        $row['markup_obat_bhp'],
        $row['lab_pk'],
        $row['lab_pa'],
        $row['rad_usg'],
        $row['rontgen'],
        $row['fisio'],
        $row['ekg'],
        $row['darah'],
        $row['makan_jumlah'],
        $row['makan_harga'],
        $row['makan_kali'],
        $row['phototherapy'],
        $row['oksigen'],
        $row['spirometri'],
        $row['total_biaya_laporan'],
        $row['margin'],
        $row['ket_darah'],
        $row['ket_albumin'],
        $row['ket_tindakan'],
        ]);
    }

    echo '<tr>';
    foreach ($values as $value) {
        echo '<td>' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '</td>';
    }
    echo '</tr>';
}

echo '</tbody></table>';
exit;
?>
