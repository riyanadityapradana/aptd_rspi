<?php
require_once dirname(__DIR__) . '/export_excel_helper.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/koneksi.php';
require_once __DIR__ . '/kunjungan_igd_ponek_helper.php';

list($tanggalAwal, $tanggalAkhir) = aptd_igd_ponek_period_from_post();
$summary = aptd_igd_ponek_summary($mysqli, $tanggalAwal, $tanggalAkhir);
$excludedSpecialists = aptd_igd_ponek_excluded_specialists($mysqli, $tanggalAwal, $tanggalAkhir);

list($spreadsheet, $sheet) = aptd_excel_create(
    'DATA KUNJUNGAN UGD DAN PONEK',
    'Periode: ' . $tanggalAwal . ' s.d. ' . $tanggalAkhir,
    'Data UGD Ponek'
);

$headers = [
    'No',
    'Kategori',
    'Kode Poli',
    'Kriteria',
    'Umum',
    'BPJS',
    'Asuransi',
    'Lainnya',
    'Jumlah Total',
];

$tableRows = [];
$number = 1;
$totals = ['umum' => 0, 'bpjs' => 0, 'asuransi' => 0, 'lainnya' => 0, 'total' => 0];
foreach ($summary as $row) {
    $tableRows[] = [
        $number++,
        $row['label'],
        $row['code'],
        $row['criteria'],
        $row['umum'],
        $row['bpjs'],
        $row['asuransi'],
        $row['lainnya'],
        $row['total'],
    ];
    foreach (array_keys($totals) as $field) {
        $totals[$field] += $row[$field];
    }
}

aptd_excel_render_table($sheet, $headers, $tableRows, 4);
$totalRow = 5 + count($tableRows);
$sheet->setCellValue('A' . $totalRow, 'Total');
$sheet->mergeCells('A' . $totalRow . ':D' . $totalRow);
$sheet->setCellValue('E' . $totalRow, $totals['umum']);
$sheet->setCellValue('F' . $totalRow, $totals['bpjs']);
$sheet->setCellValue('G' . $totalRow, $totals['asuransi']);
$sheet->setCellValue('H' . $totalRow, $totals['lainnya']);
$sheet->setCellValue('I' . $totalRow, $totals['total']);
$sheet->getStyle('A' . $totalRow . ':I' . $totalRow)->getFont()->setBold(true);
$sheet->getStyle('A' . $totalRow . ':I' . $totalRow)->getFill()
    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$sheet->getStyle('A' . $totalRow . ':I' . $totalRow)->getFill()
    ->getStartColor()->setARGB('FFEFF3F8');

$noteRow = $totalRow + 2;
$sheet->setCellValue(
    'A' . $noteRow,
    'UGD Ranap dikecualikan karena dokter bukan dokter umum (kd_sps != S0016): '
    . $excludedSpecialists . ' kunjungan.'
);
$sheet->mergeCells('A' . $noteRow . ':I' . $noteRow);

$chartLabels = [];
$chartValues = [];
foreach ($summary as $row) {
    $chartLabels[] = $row['label'];
    $chartValues[] = $row['total'];
}
aptd_excel_add_pie_chart_sheet(
    $spreadsheet,
    'Grafik Kategori',
    'Komposisi Kunjungan UGD dan Ponek',
    'Kategori',
    'Jumlah',
    $chartLabels,
    $chartValues
);

aptd_excel_output($spreadsheet, 'Data_Kunjungan_UGD_Ponek_' . date('Y-m-d') . '.xlsx');
