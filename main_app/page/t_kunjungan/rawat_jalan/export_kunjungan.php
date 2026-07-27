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

list($filter_month, $filter_year) = aptd_poli_specialty_period(
    isset($_POST['month']) ? $_POST['month'] : date('n'),
    isset($_POST['year']) ? $_POST['year'] : date('Y')
);
$monthNames = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
$summaryRows = aptd_poli_specialty_monthly_summary(
    $mysqli,
    $specialtyGroups,
    $filter_month,
    $filter_year,
    $penjamin
);
$grandTotals = array_fill_keys(array_keys($penjamin), 0);
$grandTotal = 0;

list($spreadsheet, $sheet) = aptd_excel_create(
    'DATA KUNJUNGAN PASIEN',
    'Seluruh Poliklinik | Periode: ' . $monthNames[$filter_month] . ' ' . $filter_year,
    'Data'
);

$headers = ['No', 'Poliklinik'];
foreach ($penjamin as $label) {
    $headers[] = $label;
}
$headers[] = 'Jumlah Total';

$excelRows = [];
foreach ($summaryRows as $index => $summaryRow) {
    $excelRow = [$index + 1, $summaryRow['nama_poli']];
    foreach (array_keys($penjamin) as $payerCode) {
        $value = isset($summaryRow['counts'][$payerCode]) ? (int) $summaryRow['counts'][$payerCode] : 0;
        $excelRow[] = $value;
        $grandTotals[$payerCode] += $value;
    }
    $excelRow[] = (int) $summaryRow['total'];
    $grandTotal += (int) $summaryRow['total'];
    $excelRows[] = $excelRow;
}

aptd_excel_render_table($sheet, $headers, $excelRows, 4);
$grandTotalRow = 5 + max(1, count($excelRows));
$sheet->setCellValue('A' . $grandTotalRow, 'Grand Total');
$sheet->mergeCells('A' . $grandTotalRow . ':B' . $grandTotalRow);
$col = 3;
foreach (array_keys($penjamin) as $payerCode) {
    $sheet->setCellValue(aptd_excel_cell($col, $grandTotalRow), $grandTotals[$payerCode]);
    $col++;
}
$sheet->setCellValue(aptd_excel_cell($col, $grandTotalRow), $grandTotal);
$grandTotalRange = 'A' . $grandTotalRow . ':' . aptd_excel_cell($col, $grandTotalRow);
$sheet->getStyle($grandTotalRange)->getFont()->setBold(true);
$sheet->getStyle($grandTotalRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$sheet->getStyle($grandTotalRange)->getFill()->getStartColor()->setARGB('FFEFF3F8');

$labels = [];
$values = [];
foreach ($penjamin as $payerCode => $label) {
    $labels[] = $label;
    $values[] = $grandTotals[$payerCode];
}
aptd_excel_add_pie_chart_sheet($spreadsheet, 'Grafik Pembayaran', 'Komposisi Jenis Pembayaran', 'Kategori', 'Jumlah', $labels, $values);

aptd_excel_output($spreadsheet, 'Data_Kunjungan_PerPoli_' . date('Y-m-d') . '.xlsx');
