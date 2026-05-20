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
$rows = aptd_keu_ranap_fetch_rows($mysqli, $startDate, $endDate);
$filename = 'laporan_keuangan_ranap_' . $year . '_' . str_pad($month, 2, '0', STR_PAD_LEFT) . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo '<table border="1">';
echo '<thead><tr>';
$headers = [
    'No', 'No Rawat', 'No RM', 'Nama Pasien', 'Diagnosa Awal', 'Diagnosa Akhir', 'Tanggal Masuk', 'Tanggal Keluar',
    'Status Pulang', 'DPJP', 'Kamar', 'CLAIM', 'Dokter UGD', 'JD DPJP', 'JD Operator', 'JD Anestesi', 'JD Anak',
    'JD Visit', 'JD Telpon', 'JD USG', 'JD Rontgen', 'JD Lab', 'JD PA', 'HD', 'JK', 'BHP', 'OBAT', 'LAB PK',
    'LAB PA', 'RAD USG', 'Rontgen', 'Fisio', 'EKG', 'Darah', 'Makan Jumlah', 'Makan Harga', 'Makan Kali',
    'Phototherapy', 'Oksigen', 'Spirometri', 'TOTAL', 'MARGIN', 'DARAH', 'ALBUMIN', 'TINDAKAN', 'SEP'
];
foreach ($headers as $header) {
    echo '<th>' . htmlspecialchars($header, ENT_QUOTES, 'UTF-8') . '</th>';
}
echo '</tr></thead><tbody>';

$no = 1;
foreach ($rows as $row) {
    $values = [
        $no++,
        $row['no_rawat'],
        $row['no_rkm_medis'],
        $row['nama_pasien_umur'],
        $row['diagnosa_awal'] ?: $row['diagnosa_sep'],
        $row['diagnosa_akhir'] ?: '-',
        $row['tanggal_masuk'],
        $row['tanggal_keluar'] ?: '-',
        $row['status_pulang'],
        $row['dpjp'] ?: '-',
        $row['kamar'] ?: '-',
        $row['claim'],
        $row['dokter_ugd'],
        $row['jd_dpjp'],
        $row['jd_operator'],
        $row['jd_anestesi'],
        $row['jd_anak'],
        $row['jd_visit'],
        $row['jd_telpon'],
        $row['jd_usg'],
        $row['jd_rontgen'],
        $row['jd_lab'],
        $row['jd_pa'],
        $row['hd'],
        $row['jk'],
        $row['bhp'],
        $row['obat'],
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
        $row['no_sep'] ?: '-',
    ];

    echo '<tr>';
    foreach ($values as $value) {
        echo '<td>' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '</td>';
    }
    echo '</tr>';
}

echo '</tbody></table>';
exit;
?>
