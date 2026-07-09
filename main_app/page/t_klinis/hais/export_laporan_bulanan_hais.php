<?php
require_once dirname(dirname(__DIR__)) . '/export_excel_helper.php';
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/koneksi.php';
require_once __DIR__ . '/laporan_bulanan_hais_helper.php';

$monthNames = aptd_hais_month_names();
$report = aptd_hais_report($mysqli, aptd_hais_filters_from_request());
$filters = $report['filters'];
$subtitle = 'Periode: ' . $report['start_date'] . ' s.d. ' . $report['end_date'];

if (!aptd_excel_bootstrap()) {
    $headers = ['No', 'Tanggal', 'Jml Pasien', 'ETT', 'CVL', 'IVL', 'UC', 'VAP', 'IAD', 'Pleb', 'ISK', 'ILO', 'HAP', 'Tinea', 'Scabies', 'Deku', 'Sputum', 'Darah', 'Urine', 'Antibiotik'];
    $rows = [];
    $no = 1;
    foreach ($report['rows'] as $row) {
        $rows[] = [$no++, $row['tanggal'], $row['jml_pasien'], $row['ETT'], $row['CVL'], $row['IVL'], $row['UC'], $row['VAP'], $row['IAD'], $row['PLEB'], $row['ISK'], $row['ILO'], $row['HAP'], $row['Tinea'], $row['Scabies'], $row['DEKU'], $row['SPUTUM'], $row['DARAH'], $row['URINE'], $row['ANTIBIOTIK']];
    }
    $t = $report['totals'];
    $rows[] = ['', 'Total', $t['jml_pasien'], $t['ETT'], $t['CVL'], $t['IVL'], $t['UC'], $t['VAP'], $t['IAD'], $t['PLEB'], $t['ISK'], $t['ILO'], $t['HAP'], $t['Tinea'], $t['Scabies'], $t['DEKU'], $t['SPUTUM'], $t['DARAH'], $t['URINE'], $t['ANTIBIOTIK']];
    aptd_excel_output_csv('Laporan_Bulanan_HAIs_' . $filters['tahun'] . '_' . sprintf('%02d', $filters['bulan']) . '.csv', $headers, $rows);
}

$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Laporan HAIs');
$sheet->setCellValue('A1', 'Laporan Bulanan Data HAIs');
$sheet->setCellValue('A2', $subtitle);
$sheet->mergeCells('A1:T1');
$sheet->mergeCells('A2:T2');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15);
$sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10);

$headerRow = 4;
$subHeaderRow = 5;
$sheet->setCellValue('A4', 'No.');
$sheet->setCellValue('B4', 'Tanggal');
$sheet->setCellValue('C4', 'Jml. Pasien');
$sheet->setCellValue('D4', 'Hari Pemasangan');
$sheet->setCellValue('H4', 'Infeksi');
$sheet->setCellValue('P4', 'Deku');
$sheet->setCellValue('Q4', 'Hasil Kultur');
$sheet->setCellValue('T4', 'Antibiotik');
$sheet->mergeCells('A4:A5');
$sheet->mergeCells('B4:B5');
$sheet->mergeCells('C4:C5');
$sheet->mergeCells('D4:G4');
$sheet->mergeCells('H4:O4');
$sheet->mergeCells('P4:P5');
$sheet->mergeCells('Q4:S4');
$sheet->mergeCells('T4:T5');

$subHeaders = [
    'D5' => 'ETT',
    'E5' => 'CVL',
    'F5' => 'IVL',
    'G5' => 'UC',
    'H5' => 'VAP',
    'I5' => 'IAD',
    'J5' => 'Pleb',
    'K5' => 'ISK',
    'L5' => 'ILO',
    'M5' => 'HAP',
    'N5' => 'Tinea',
    'O5' => 'Scabies',
    'Q5' => 'Sputum',
    'R5' => 'Darah',
    'S5' => 'Urine',
];
foreach ($subHeaders as $cell => $value) {
    $sheet->setCellValue($cell, $value);
}

$rowNumber = 6;
$no = 1;
foreach ($report['rows'] as $row) {
    $values = [$no++, $row['tanggal'], $row['jml_pasien'], $row['ETT'], $row['CVL'], $row['IVL'], $row['UC'], $row['VAP'], $row['IAD'], $row['PLEB'], $row['ISK'], $row['ILO'], $row['HAP'], $row['Tinea'], $row['Scabies'], $row['DEKU'], $row['SPUTUM'], $row['DARAH'], $row['URINE'], $row['ANTIBIOTIK']];
    foreach ($values as $index => $value) {
        $sheet->setCellValue(aptd_excel_cell($index + 1, $rowNumber), $value);
    }
    $rowNumber++;
}

$totals = $report['totals'];
$sheet->setCellValue('A' . $rowNumber, '');
$sheet->setCellValue('B' . $rowNumber, 'Total :');
$totalValues = [$totals['jml_pasien'], $totals['ETT'], $totals['CVL'], $totals['IVL'], $totals['UC'], $totals['VAP'], $totals['IAD'], $totals['PLEB'], $totals['ISK'], $totals['ILO'], $totals['HAP'], $totals['Tinea'], $totals['Scabies'], $totals['DEKU'], $totals['SPUTUM'], $totals['DARAH'], $totals['URINE'], $totals['ANTIBIOTIK']];
foreach ($totalValues as $index => $value) {
    $sheet->setCellValue(aptd_excel_cell($index + 3, $rowNumber), $value);
}

$lastRow = $rowNumber;
$headerRange = 'A' . $headerRow . ':T' . $subHeaderRow;
$dataRange = 'A' . $headerRow . ':T' . $lastRow;
$sheet->getStyle($headerRange)->getFont()->setBold(true);
$sheet->getStyle($headerRange)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
$sheet->getStyle($headerRange)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
$sheet->getStyle($headerRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$sheet->getStyle($headerRange)->getFill()->getStartColor()->setARGB('FFF9F4F4');
$sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
$sheet->getStyle('A6:T' . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('B6:B' . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
$sheet->getStyle('A' . $lastRow . ':T' . $lastRow)->getFont()->setBold(true);
$sheet->getStyle('A' . $lastRow . ':T' . $lastRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$sheet->getStyle('A' . $lastRow . ':T' . $lastRow)->getFill()->getStartColor()->setARGB('FFFFF8DC');
$sheet->freezePane('D6');

for ($column = 1; $column <= 20; $column++) {
    $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
}

aptd_excel_output($spreadsheet, 'Laporan_Bulanan_HAIs_' . $filters['tahun'] . '_' . sprintf('%02d', $filters['bulan']) . '.xlsx');
