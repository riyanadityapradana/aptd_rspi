<?php
require_once dirname(dirname(__DIR__)) . '/export_excel_helper.php';
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/koneksi.php';
require_once dirname(__DIR__) . '/poli_specialty_helper.php';

$specialtyGroups = aptd_poli_specialty_mapping($mysqli);

$penjamin = [
    'A09' => 'UMUM',
    'BPJ' => 'BPJS',
    'A92' => 'ASURANSI',
];

$requestedPoli = isset($_POST['poli']) ? trim((string) $_POST['poli']) : '';
$filter_poli = aptd_poli_specialty_selected_group($specialtyGroups, $requestedPoli);
$filter_month = isset($_POST['month']) ? (int) $_POST['month'] : (int) date('n');
$filter_year = isset($_POST['year']) ? (int) $_POST['year'] : (int) date('Y');
$monthNames = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];

if (strtoupper($filter_poli) === 'VAKSIN') {
    $penjamin = [
        'A09' => 'UMUM',
        'A96' => 'Pancar Tour',
        'A92' => 'ASURANSI',
    ];
}

$poli_codes = isset($specialtyGroups[$filter_poli]) ? $specialtyGroups[$filter_poli] : [];
$data = array_fill_keys(array_keys($penjamin), 0);
$total = 0;

if (!empty($poli_codes)) {
    $poli_codes_str = "'" . implode("','", array_map(function ($v) use ($mysqli) {
        return mysqli_real_escape_string($mysqli, $v);
    }, $poli_codes)) . "'";

    $whereParts = [
        'rp.kd_poli IN (' . $poli_codes_str . ')',
        aptd_poli_specialty_exclusion_sql($mysqli, 'rp.kd_poli'),
        "EXISTS (SELECT 1 FROM poliklinik pl WHERE pl.kd_poli = rp.kd_poli AND pl.status = '1')",
        "rp.stts = 'Sudah'",
        "rp.status_bayar = 'Sudah Bayar'",
        "rp.no_rkm_medis NOT IN (SELECT no_rkm_medis FROM pasien WHERE LOWER(nm_pasien) LIKE '%test%')",
    ];

    $start = sprintf('%04d-%02d-01', $filter_year, $filter_month);
    $end = date('Y-m-t', strtotime($start));
    $whereParts[] = "rp.tgl_registrasi BETWEEN '" . $start . "' AND '" . $end . "'";

    foreach ($penjamin as $kd_pj => $label) {
        $sql = "SELECT COUNT(*) AS jml FROM reg_periksa rp WHERE rp.kd_pj = '" . $kd_pj . "' AND " . implode(' AND ', $whereParts);
        $result = mysqli_query($mysqli, $sql);
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $data[$kd_pj] = isset($row['jml']) ? (int) $row['jml'] : 0;
            $total += $data[$kd_pj];
        }
    }
}

list($spreadsheet, $sheet) = aptd_excel_create(
    'DATA KUNJUNGAN PASIEN',
    'Filter: ' . $filter_poli . ' | Periode: ' . $monthNames[$filter_month] . ' ' . $filter_year,
    'Data'
);

$headers = ['No', 'Poliklinik'];
foreach ($penjamin as $label) {
    $headers[] = $label;
}
$headers[] = 'Jumlah Total';

$row = [1, $filter_poli];
foreach (array_keys($penjamin) as $kd) {
    $row[] = $data[$kd];
}
$row[] = $total;

aptd_excel_render_table($sheet, $headers, [$row], 4);
$sheet->setCellValue('A6', 'Total');
$sheet->mergeCells('A6:B6');
$col = 3;
foreach (array_keys($penjamin) as $kd) {
    $sheet->setCellValue(aptd_excel_cell($col, 6), $data[$kd]);
    $col++;
}
$sheet->setCellValue(aptd_excel_cell($col, 6), $total);
$sheet->getStyle('A6:' . aptd_excel_cell($col, 6))->getFont()->setBold(true);
$sheet->getStyle('A6:' . aptd_excel_cell($col, 6))->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$sheet->getStyle('A6:' . aptd_excel_cell($col, 6))->getFill()->getStartColor()->setARGB('FFEFF3F8');

$labels = [];
$values = [];
foreach ($penjamin as $kd => $label) {
    $labels[] = $label;
    $values[] = $data[$kd];
}
aptd_excel_add_pie_chart_sheet($spreadsheet, 'Grafik Pembayaran', 'Komposisi Jenis Pembayaran', 'Kategori', 'Jumlah', $labels, $values);

aptd_excel_output($spreadsheet, 'Data_Kunjungan_' . date('Y-m-d') . '.xlsx');
