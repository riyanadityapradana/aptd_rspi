<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/koneksi.php';
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/akses.php';
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/assets/vendor/autoload.php';
require_once __DIR__ . '/kunjungan_kecamatan_mingguan_ranap_helper.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$levelLogin = isset($_SESSION['level']) ? $_SESSION['level'] : '';
$isAdmin = strtolower(trim($levelLogin)) === 'admin';
if (!isset($_SESSION['login_aptd_rspi']) || $_SESSION['login_aptd_rspi'] !== true || !$isAdmin) {
    http_response_code(403);
    exit('Export Excel hanya tersedia untuk admin.');
}

if (ob_get_length()) {
    ob_end_clean();
}

$conn = $mysqli;
list($selectedMonth, $filterYear, $filterMonth, $startDate, $endDate) = aptd_kec_mingguan_period_from_request();
$monthLabels = aptd_kec_mingguan_month_labels();
$paymentLabels = aptd_kec_mingguan_payment_labels();
$weeks = aptd_kec_mingguan_weeks($filterYear, $filterMonth);
$report = aptd_kec_mingguan_fetch_ranap($conn, $startDate, $endDate, $weeks);
$rows = $report['rows'];
$totals = $report['totals'];
$watermarkText = 'Tarikan Data Dari Unit IT RSPI';
$lastColIndex = 4 + (count($weeks) * count($paymentLabels));
$lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastColIndex);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Kecamatan Mingguan');
$sheet->mergeCells('A1:' . $lastCol . '1');
$sheet->setCellValue('A1', $watermarkText);
$sheet->mergeCells('A2:' . $lastCol . '2');
$sheet->setCellValue('A2', 'DATA KUNJUNGAN PASIEN RAWAT INAP PER KECAMATAN PER MINGGU');
$sheet->mergeCells('A3:' . $lastCol . '3');
$sheet->setCellValue('A3', 'Periode: ' . $monthLabels[$filterMonth] . ' ' . $filterYear . ' (' . $startDate . ' s/d ' . $endDate . ')');
$sheet->getStyle('A1')->getFont()->setBold(true)->setItalic(true)->getColor()->setARGB('FF9B1C1C');
$sheet->getStyle('A2')->getFont()->setBold(true)->setSize(13);
$sheet->getStyle('A1:A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getHeaderFooter()->setOddHeader('&C' . $watermarkText);
$sheet->getHeaderFooter()->setOddFooter('&C' . $watermarkText . ' | Halaman &P dari &N');

$headerRow = 5;
$subHeaderRow = 6;
$sheet->setCellValue('A' . $headerRow, 'NO');
$sheet->setCellValue('B' . $headerRow, 'KAB/KOTA');
$sheet->setCellValue('C' . $headerRow, 'KECAMATAN');
$sheet->setCellValue('D' . $headerRow, 'TOTAL');
$sheet->mergeCells('A' . $headerRow . ':A' . $subHeaderRow);
$sheet->mergeCells('B' . $headerRow . ':B' . $subHeaderRow);
$sheet->mergeCells('C' . $headerRow . ':C' . $subHeaderRow);
$sheet->mergeCells('D' . $headerRow . ':D' . $subHeaderRow);

$colIndex = 5;
foreach ($weeks as $week) {
    $startCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
    $endCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + count($paymentLabels) - 1);
    $sheet->mergeCells($startCol . $headerRow . ':' . $endCol . $headerRow);
    $sheet->setCellValue($startCol . $headerRow, $week['label']);
    foreach ($paymentLabels as $payment) {
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++);
        $sheet->setCellValue($col . $subHeaderRow, strtoupper($payment));
    }
}

$sheet->getStyle('A' . $headerRow . ':' . $lastCol . $subHeaderRow)->getFont()->setBold(true);
$sheet->getStyle('A' . $headerRow . ':' . $lastCol . $subHeaderRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2F3944');
$sheet->getStyle('A' . $headerRow . ':' . $lastCol . $subHeaderRow)->getFont()->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle('A' . $headerRow . ':' . $lastCol . $subHeaderRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

$rowNum = 7;
$no = 1;
foreach ($rows as $row) {
    if ($row['counts']['total'] <= 0) {
        continue;
    }
    $sheet->setCellValue('A' . $rowNum, $no++);
    $sheet->setCellValue('B' . $rowNum, $row['kabupaten']);
    $sheet->setCellValue('C' . $rowNum, $row['kecamatan']);
    $sheet->setCellValue('D' . $rowNum, $row['counts']['total']);
    $colIndex = 5;
    foreach ($weeks as $weekIdx => $week) {
        foreach ($paymentLabels as $payment) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++);
            $sheet->setCellValue($col . $rowNum, $row['counts']['weeks'][$weekIdx][$payment]);
        }
    }
    $rowNum++;
}

$sheet->setCellValue('A' . $rowNum, 'Grand Total');
$sheet->mergeCells('A' . $rowNum . ':C' . $rowNum);
$sheet->setCellValue('D' . $rowNum, $totals['total']);
$colIndex = 5;
foreach ($weeks as $weekIdx => $week) {
    foreach ($paymentLabels as $payment) {
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++);
        $sheet->setCellValue($col . $rowNum, $totals['weeks'][$weekIdx][$payment]);
    }
}
$sheet->getStyle('A' . $rowNum . ':' . $lastCol . $rowNum)->getFont()->setBold(true);
$sheet->getStyle('A' . $rowNum . ':' . $lastCol . $rowNum)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF8C42');

$lastRow = $rowNum;
$sheet->getStyle('A5:' . $lastCol . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle('A5:' . $lastCol . $lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle('A:' . $lastCol)->getAlignment()->setWrapText(true);
$sheet->getColumnDimension('A')->setWidth(6);
$sheet->getColumnDimension('B')->setWidth(22);
$sheet->getColumnDimension('C')->setWidth(24);
$sheet->getColumnDimension('D')->setWidth(10);
for ($i = 5; $i <= $lastColIndex; $i++) {
    $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i))->setWidth(11);
}
$sheet->getProtection()->setSheet(true);
$sheet->getProtection()->setPassword('ITRSPI25'); //password untuk proteksi sheet

$filename = 'kunjungan_kecamatan_mingguan_ranap_' . $selectedMonth . '_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
