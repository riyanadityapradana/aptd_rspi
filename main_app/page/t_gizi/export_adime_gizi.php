<?php
session_start();
require_once dirname(dirname(dirname(__DIR__))) . '/config/koneksi.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/akses.php';
require_once dirname(dirname(dirname(__DIR__))) . '/assets/vendor/autoload.php';
require_once __DIR__ . '/adime_gizi_helper.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$levelLogin = isset($_SESSION['level']) ? $_SESSION['level'] : '';
if (!isset($_SESSION['login_aptd_rspi']) || $_SESSION['login_aptd_rspi'] !== true || !aptd_can_access($levelLogin, 'export_adime_gizi')) {
    http_response_code(403);
    exit('Anda tidak memiliki hak akses export.');
}

if (ob_get_length()) {
    ob_end_clean();
}

$conn = $mysqli;
list($startDate, $endDate, $selectedStatus) = aptd_adime_filter_from_request();
$report = aptd_adime_fetch($conn, $startDate, $endDate, $selectedStatus);
$rows = $report['rows'];
$summary = $report['summary'];
$statusOptions = aptd_adime_status_options();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Data ADIME');
$sheet->mergeCells('A1:I1');
$sheet->setCellValue('A1', 'MONITORING ADIME GIZI RAWAT INAP');
$sheet->mergeCells('A2:I2');
$sheet->setCellValue('A2', 'Periode: ' . $startDate . ' s/d ' . $endDate . ' | Status: ' . $statusOptions[$selectedStatus]);
$sheet->mergeCells('A3:I3');
$sheet->setCellValue('A3', 'Total: ' . $summary['total'] . ' | Sudah ADIME: ' . $summary['SUDAH ADIME'] . ' | Belum ADIME: ' . $summary['BELUM ADIME']);
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1:A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$headers = ['No', 'No Rawat', 'No RM', 'Nama Pasien', 'Kamar', 'Tgl Masuk', 'Jam Masuk', 'Status ADIME', 'Status Pulang'];
foreach ($headers as $idx => $header) {
    $sheet->setCellValue(chr(65 + $idx) . '5', $header);
}

$sheet->getStyle('A5:I5')->getFont()->setBold(true);
$sheet->getStyle('A5:I5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2F3944');
$sheet->getStyle('A5:I5')->getFont()->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle('A5:I5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$rowNum = 6;
$no = 1;
foreach ($rows as $row) {
    $sheet->setCellValue('A' . $rowNum, $no++);
    $sheet->setCellValue('B' . $rowNum, $row['no_rawat']);
    $sheet->setCellValue('C' . $rowNum, $row['no_rkm_medis']);
    $sheet->setCellValue('D' . $rowNum, $row['nm_pasien']);
    $sheet->setCellValue('E' . $rowNum, $row['kd_kamar']);
    $sheet->setCellValue('F' . $rowNum, $row['tgl_masuk']);
    $sheet->setCellValue('G' . $rowNum, $row['jam_masuk']);
    $sheet->setCellValue('H' . $rowNum, $row['status_adime']);
    $sheet->setCellValue('I' . $rowNum, $row['stts_pulang']);

    if ($row['status_adime'] === 'BELUM ADIME') {
        $sheet->getStyle('H' . $rowNum)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFDE8E8');
        $sheet->getStyle('H' . $rowNum)->getFont()->getColor()->setARGB('FFB42318');
    } else {
        $sheet->getStyle('H' . $rowNum)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE9F8EF');
    }

    $rowNum++;
}

if ($rowNum === 6) {
    $sheet->setCellValue('A6', 'Tidak ada data');
    $sheet->mergeCells('A6:I6');
    $sheet->getStyle('A6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $rowNum = 7;
}

$lastRow = $rowNum - 1;
$sheet->getStyle('A5:I' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle('A5:I' . $lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle('A:I')->getAlignment()->setWrapText(true);
foreach (range('A', 'I') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$filename = 'monitoring_adime_gizi_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
