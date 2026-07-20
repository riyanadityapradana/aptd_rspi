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

list($month, $year, $startDate, $endDate, $filterBy, $filterValid, $filterMessage) = aptd_keu_ranap_date_filter();
if (!$filterValid) {
    http_response_code(400);
    exit($filterMessage);
}

$rows = aptd_keu_ranap_fetch_report_rows($mysqli, $startDate, $endDate, $filterBy);
$filename = 'laporan_keuangan_ranap_' . aptd_keu_ranap_normalize_filter_by($filterBy) . '_' . str_replace('-', '', $startDate) . '_' . str_replace('-', '', $endDate) . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

function aptd_keu_ranap_export_xml($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function aptd_keu_ranap_export_cell($value, $type = 'text', $style = '')
{
    $styleAttr = $style !== '' ? ' ss:StyleID="' . aptd_keu_ranap_export_xml($style) . '"' : '';
    if ($value === '' || $value === null || ($type !== 'text' && $value === '-')) {
        return '<Cell' . $styleAttr . '/>';
    }

    if ($type === 'number' || $type === 'integer') {
        $number = is_numeric($value) ? (float) $value : aptd_keu_ranap_parse_number($value);
        if ($type === 'integer') {
            $number = (int) round($number);
        }

        return '<Cell' . $styleAttr . '><Data ss:Type="Number">' . aptd_keu_ranap_export_xml($number) . '</Data></Cell>';
    }

    return '<Cell' . $styleAttr . '><Data ss:Type="String">' . aptd_keu_ranap_export_xml($value) . '</Data></Cell>';
}

function aptd_keu_ranap_export_header_cell($value)
{
    return '<Cell ss:StyleID="Header"><Data ss:Type="String">' . aptd_keu_ranap_export_xml($value) . '</Data></Cell>';
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:html="http://www.w3.org/TR/REC-html40">';
echo '<Styles>';
echo '<Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="11"/></Style>';
echo '<Style ss:ID="Header"><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/><Font ss:Bold="1"/><Interior ss:Color="#D9E2F3" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
echo '<Style ss:ID="Text"><NumberFormat ss:Format="@"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
echo '<Style ss:ID="General"><Alignment ss:Vertical="Center" ss:WrapText="1"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
echo '<Style ss:ID="Number"><NumberFormat ss:Format="0.00"/><Alignment ss:Horizontal="Right" ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
echo '<Style ss:ID="Integer"><NumberFormat ss:Format="0"/><Alignment ss:Horizontal="Right" ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
echo '</Styles>';
$sheetName = 'ranap_' . aptd_keu_ranap_normalize_filter_by($filterBy) . '_' . str_replace('-', '', $startDate);
echo '<Worksheet ss:Name="' . aptd_keu_ranap_export_xml(substr($sheetName, 0, 31)) . '">';
echo '<Table>';
echo '<Row>';
$headers = [
    'No Rawat',
    'No RM',
    'Nama Pasien',
    'SEP',
    'Diagnosa Awal',
    'Diagnosa Akhir',
    'Tanggal Masuk',
    'Tanggal Keluar',
    'Status Pulang',
    'DPJP',
    'Kamar',
    'Lama Dirawat',
    'CLAIM DIPAKAI',
    'CLAIM AKTUAL',
    'CLAIM RIWAYAT',
    'SUMBER',
    'AKSI',
    'Dokter UGD', 'JD DPJP', 'Ket. JD DPJP', 'JD Operator', 'JD Anestesi', 'Ket. JD Anestesi', 'JD Anak', 'Ket. JD Anak', 'JD Visite', 'JD Visite Umum', 'JD Visite Spesialis', 'JD Visite Pengganti', 'Ket. JD Visite', 'JD Telp', 'JD Telpon Pengganti', 'Ket. JD Telp', 'JD USG',
    'JD Rontgen', 'JD Lab', 'JD PA', 'HD', 'JK', 'BHP', 'OBAT', 'Total Harga Dasar', '15% Dasar', 'LAB PK', 'LAB PA', 'Rad USG', 'Rontgen', 'Fisio', 'EKG',
    'Darah', 'Jumlah Makan', 'Harga Makan', 'Kali Makan', 'Phototherapy', 'Oksigen', 'Spirometri', 'TOTAL', 'MARGIN', 'DARAH', 'ALBUMIN', 'TINDAKAN'
];
foreach ($headers as $header) {
    echo aptd_keu_ranap_export_header_cell($header);
}
echo '</Row>';

foreach ($rows as $row) {
    $baseValues = [
        ['value' => $row['no_rawat'], 'type' => 'identifier'],
        ['value' => $row['no_rkm_medis'], 'type' => 'identifier'],
        ['value' => $row['nama_pasien_umur'], 'type' => 'text'],
        ['value' => $row['no_sep'] ?: '-', 'type' => 'identifier'],
        ['value' => $row['diagnosa_awal'] ?: $row['diagnosa_sep'], 'type' => 'text'],
        ['value' => $row['diagnosa_akhir'] ?: '-', 'type' => 'text'],
        ['value' => $row['tanggal_masuk'], 'type' => 'text'],
        ['value' => $row['tanggal_keluar'] ?: '-', 'type' => 'text'],
        ['value' => $row['status_pulang'], 'type' => 'text'],
        ['value' => $row['dpjp'] ?: '-', 'type' => 'text'],
        ['value' => $row['kamar'] ?: '-', 'type' => 'text'],
        ['value' => $row['lama_dirawat'] === null ? '' : (int) $row['lama_dirawat'], 'type' => 'integer'],
        ['value' => $row['claim'], 'type' => 'number'],
        ['value' => $row['claim_actual'], 'type' => 'number'],
        ['value' => $row['claim_history'], 'type' => 'number'],
        ['value' => $row['claim_source_label'], 'type' => 'text'],
        ['value' => ((float) $row['claim'] > 0 ? (!empty($row['has_hitung']) ? 'Hitung Ulang' : 'Hitung') : (((float) $row['claim_actual'] <= 0 && (float) $row['claim_history'] > 0) ? 'Pakai Riwayat' : 'Tidak Aktif')), 'type' => 'text'],
    ];

    if (empty($row['has_hitung'])) {
        $values = array_merge($baseValues, array_fill(0, 44, ['value' => '', 'type' => 'text']));
    } else {
        $values = array_merge($baseValues, [
        ['value' => $row['dokter_ugd'], 'type' => 'number'],
        ['value' => $row['jd_dpjp'], 'type' => 'number'],
        ['value' => $row['ket_dpjp'], 'type' => 'text'],
        ['value' => $row['jd_operator'], 'type' => 'number'],
        ['value' => $row['jd_anestesi'], 'type' => 'number'],
        ['value' => $row['ket_anestesi'], 'type' => 'text'],
        ['value' => $row['jd_anak'], 'type' => 'number'],
        ['value' => $row['ket_anak'], 'type' => 'text'],
        ['value' => $row['jd_visit'], 'type' => 'number'],
        ['value' => $row['jd_visit_umum'], 'type' => 'number'],
        ['value' => $row['jd_visit_spesialis'], 'type' => 'number'],
        ['value' => $row['jd_visit_pengganti'], 'type' => 'number'],
        ['value' => $row['ket_visit'], 'type' => 'text'],
        ['value' => $row['jd_telpon'], 'type' => 'number'],
        ['value' => $row['jd_telpon_pengganti'], 'type' => 'number'],
        ['value' => $row['ket_telpon'], 'type' => 'text'],
        ['value' => $row['jd_usg'], 'type' => 'number'],
        ['value' => $row['jd_rontgen'], 'type' => 'number'],
        ['value' => $row['jd_lab'], 'type' => 'number'],
        ['value' => $row['jd_pa'], 'type' => 'number'],
        ['value' => $row['hd'], 'type' => 'number'],
        ['value' => $row['jk'], 'type' => 'number'],
        ['value' => $row['bhp'], 'type' => 'number'],
        ['value' => $row['obat'], 'type' => 'number'],
        ['value' => $row['total_harga_dasar_obat'], 'type' => 'number'],
        ['value' => $row['markup_obat_bhp'], 'type' => 'number'],
        ['value' => $row['lab_pk'], 'type' => 'number'],
        ['value' => $row['lab_pa'], 'type' => 'number'],
        ['value' => $row['rad_usg'], 'type' => 'number'],
        ['value' => $row['rontgen'], 'type' => 'number'],
        ['value' => $row['fisio'], 'type' => 'number'],
        ['value' => $row['ekg'], 'type' => 'number'],
        ['value' => $row['darah'], 'type' => 'number'],
        ['value' => $row['makan_jumlah'], 'type' => 'number'],
        ['value' => $row['makan_harga'], 'type' => 'number'],
        ['value' => $row['makan_kali'], 'type' => 'integer'],
        ['value' => $row['phototherapy'], 'type' => 'number'],
        ['value' => $row['oksigen'], 'type' => 'number'],
        ['value' => $row['spirometri'], 'type' => 'number'],
        ['value' => $row['total_biaya_laporan'], 'type' => 'number'],
        ['value' => $row['margin'], 'type' => 'number'],
        ['value' => $row['ket_darah'], 'type' => 'integer'],
        ['value' => $row['ket_albumin'], 'type' => 'integer'],
        ['value' => $row['ket_tindakan'], 'type' => 'text'],
        ]);
    }

    echo '<Row>';
    foreach ($values as $cell) {
        $style = 'General';
        if ($cell['type'] === 'identifier') {
            $style = 'Text';
        } elseif ($cell['type'] === 'number') {
            $style = 'Number';
        } elseif ($cell['type'] === 'integer') {
            $style = 'Integer';
        }
        echo aptd_keu_ranap_export_cell($cell['value'], $cell['type'], $style);
    }
    echo '</Row>';
}

echo '</Table>';
echo '</Worksheet>';
echo '</Workbook>';
exit;
?>
