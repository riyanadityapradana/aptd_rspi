<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/assets/vendor/autoload.php';
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/koneksi.php';
require_once dirname(__DIR__) . '/kunjungan_kecamatan_helper.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

if (ob_get_length()) ob_end_clean();

$conn = $mysqli;
$monthLabels = aptd_kecamatan_month_labels();
$kategoriList = aptd_kecamatan_payment_labels();
$selectedWilayah = aptd_kecamatan_selected_wilayah(isset($_POST['wilayah']) ? $_POST['wilayah'] : '');
list($filterMonth, $filterYear, $startDate, $endDate) = aptd_kecamatan_period_from_request();
$report = aptd_kecamatan_fetch($conn, 'ralan', $startDate, $endDate, $selectedWilayah);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Pivot');
$sheet->mergeCells('A1:G1');
$sheet->setCellValue('A1', 'KUNJUNGAN RAWAT JALAN BERDASARKAN KECAMATAN');
$sheet->mergeCells('A2:G2');
$sheet->setCellValue('A2', 'Filter: ' . ($selectedWilayah !== '' ? $selectedWilayah : 'Semua Wilayah') . ' | Periode: ' . $monthLabels[$filterMonth] . ' ' . $filterYear . ' (' . $startDate . ' sampai sebelum ' . $endDate . ')');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$headers = ['No', 'Wilayah', 'Kecamatan', 'Umum', 'Asuransi', 'BPJS', 'Total'];
foreach ($headers as $idx => $header) {
    $col = chr(65 + $idx);
    $sheet->setCellValue($col . '4', $header);
}
$sheet->getStyle('A4:G4')->getFont()->setBold(true);
$sheet->getStyle('A4:G4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2F3944');
$sheet->getStyle('A4:G4')->getFont()->getColor()->setARGB('FFFFFFFF');

$rowNum = 5;
$no = 1;
foreach ($report['wilayah_list'] as $wilayah) {
    $sheet->setCellValue('A' . $rowNum, $wilayah);
    $sheet->mergeCells('A' . $rowNum . ':G' . $rowNum);
    $sheet->getStyle('A' . $rowNum . ':G' . $rowNum)->getFont()->setBold(true);
    $sheet->getStyle('A' . $rowNum . ':G' . $rowNum)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDDEEFF');
    $rowNum++;

    foreach ($report['data'][$wilayah] as $kecamatan => $item) {
        $sheet->setCellValue('A' . $rowNum, $no++);
        $sheet->setCellValue('B' . $rowNum, $wilayah);
        $sheet->setCellValue('C' . $rowNum, $kecamatan);
        $sheet->setCellValue('D' . $rowNum, $item['Umum']);
        $sheet->setCellValue('E' . $rowNum, $item['Asuransi']);
        $sheet->setCellValue('F' . $rowNum, $item['BPJS']);
        $sheet->setCellValue('G' . $rowNum, array_sum($item));
        $rowNum++;
    }

    $sheet->setCellValue('A' . $rowNum, 'Total ' . $wilayah);
    $sheet->mergeCells('A' . $rowNum . ':C' . $rowNum);
    $sheet->setCellValue('D' . $rowNum, $report['total_wilayah'][$wilayah]['Umum']);
    $sheet->setCellValue('E' . $rowNum, $report['total_wilayah'][$wilayah]['Asuransi']);
    $sheet->setCellValue('F' . $rowNum, $report['total_wilayah'][$wilayah]['BPJS']);
    $sheet->setCellValue('G' . $rowNum, array_sum($report['total_wilayah'][$wilayah]));
    $sheet->getStyle('A' . $rowNum . ':G' . $rowNum)->getFont()->setBold(true);
    $sheet->getStyle('A' . $rowNum . ':G' . $rowNum)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFF2CC');
    $rowNum++;
}

$sheet->setCellValue('A' . $rowNum, 'Grand Total');
$sheet->mergeCells('A' . $rowNum . ':C' . $rowNum);
$sheet->setCellValue('D' . $rowNum, $report['total_kategori']['Umum']);
$sheet->setCellValue('E' . $rowNum, $report['total_kategori']['Asuransi']);
$sheet->setCellValue('F' . $rowNum, $report['total_kategori']['BPJS']);
$sheet->setCellValue('G' . $rowNum, $report['grand_total']);
$sheet->getStyle('A' . $rowNum . ':G' . $rowNum)->getFont()->setBold(true);
$sheet->getStyle('A' . $rowNum . ':G' . $rowNum)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF8C42');

$lastRow = $rowNum;
$sheet->getStyle('A4:G' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
foreach (range('A', 'G') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);
$sheet->getStyle('A4:G' . $lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle('A:G')->getAlignment()->setWrapText(true);

$rawSheet = $spreadsheet->createSheet();
$rawSheet->setTitle('Format SQLYog');
$rawHeaders = ['wilayah', 'nm_kec', 'kategori', 'total'];
foreach ($rawHeaders as $idx => $header) {
    $rawSheet->setCellValue(chr(65 + $idx) . '1', $header);
}
$rawSheet->getStyle('A1:D1')->getFont()->setBold(true);
$rawSheet->getStyle('A1:D1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2F3944');
$rawSheet->getStyle('A1:D1')->getFont()->getColor()->setARGB('FFFFFFFF');
$rawRow = 2;
foreach ($report['raw_rows'] as $item) {
    $rawSheet->setCellValue('A' . $rawRow, $item['wilayah']);
    $rawSheet->setCellValue('B' . $rawRow, $item['nm_kec']);
    $rawSheet->setCellValue('C' . $rawRow, $item['kategori']);
    $rawSheet->setCellValue('D' . $rawRow, $item['total']);
    $rawRow++;
}
if ($rawRow > 2) $rawSheet->getStyle('A1:D' . ($rawRow - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
foreach (range('A', 'D') as $col) $rawSheet->getColumnDimension($col)->setAutoSize(true);

$spreadsheet->setActiveSheetIndex(0);
$filename = 'kunjungan_kecamatan_ralan_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
