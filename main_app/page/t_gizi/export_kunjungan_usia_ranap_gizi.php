<?php
session_start();
require_once dirname(dirname(dirname(__DIR__))) . '/config/koneksi.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/akses.php';
require_once dirname(dirname(dirname(__DIR__))) . '/assets/vendor/autoload.php';
require_once __DIR__ . '/kunjungan_usia_ranap_gizi_helper.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

$levelLogin = isset($_SESSION['level']) ? $_SESSION['level'] : '';
if (!isset($_SESSION['login_aptd_rspi']) || $_SESSION['login_aptd_rspi'] !== true || !aptd_can_access($levelLogin, 'export_kunjungan_usia_ranap_gizi')) {
    http_response_code(403);
    exit('Anda tidak memiliki hak akses export.');
}

if (ob_get_length()) {
    ob_end_clean();
}

$conn = $mysqli;
$filters = aptd_gizi_usia_filter_from_request();
$rows = aptd_gizi_usia_fetch($conn, $filters);
$usiaCategories = aptd_gizi_usia_categories();
$penjabList = aptd_gizi_usia_penjab_list();
$statusLabel = $filters['stts'] === 'semua' ? 'Semua' : $filters['stts'];
$jenisBayarLabel = $filters['jenis_bayar'] === 'semua' ? 'Semua' : $penjabList[$filters['jenis_bayar']];

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Kunjungan Usia Ranap');
$sheet->mergeCells('A1:R1');
$sheet->setCellValue('A1', 'KUNJUNGAN PASIEN RAWAT INAP BERDASARKAN USIA - GIZI');
$sheet->mergeCells('A2:R2');
$sheet->setCellValue('A2', 'Periode: ' . $filters['tgl_awal'] . ' s/d ' . $filters['tgl_akhir'] . ' | Usia: ' . $usiaCategories[$filters['usia']]);
$sheet->mergeCells('A3:R3');
$sheet->setCellValue('A3', 'Status Pulang: ' . $statusLabel . ' | Jenis Bayar: ' . $jenisBayarLabel . ' | Total: ' . count($rows) . ' pasien');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1:A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$headers = ['No', 'No. RM', 'Nama Pasien', 'No. Rawat', 'Tgl Lahir', 'Tgl Registrasi', 'Umur Daftar', 'Usia Tahun', 'Kode Kamar', 'Nama Bangsal', 'Tgl Masuk', 'Tgl Keluar', 'Diagnosa Awal', 'Diagnosa Akhir', 'TB', 'BB', 'Jenis Bayar', 'Status Pulang'];
$columns = range('A', 'R');
foreach ($headers as $idx => $header) {
    $sheet->setCellValue($columns[$idx] . '5', $header);
}

$sheet->getStyle('A5:R5')->getFont()->setBold(true);
$sheet->getStyle('A5:R5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2F3944');
$sheet->getStyle('A5:R5')->getFont()->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle('A5:R5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$rowNum = 6;
$no = 1;
foreach ($rows as $row) {
    $sheet->setCellValue('A' . $rowNum, $no++);
    $sheet->setCellValueExplicit('B' . $rowNum, $row['no_rm'], DataType::TYPE_STRING);
    $sheet->setCellValue('C' . $rowNum, $row['nama_pasien']);
    $sheet->setCellValue('D' . $rowNum, $row['no_rawat']);
    $sheet->setCellValue('E' . $rowNum, $row['tgl_lahir']);
    $sheet->setCellValue('F' . $rowNum, $row['tgl_registrasi']);
    $sheet->setCellValue('G' . $rowNum, trim($row['umur_daftar'] . ' ' . $row['status_umur']));
    $sheet->setCellValue('H' . $rowNum, $row['usia_tahun']);
    $sheet->setCellValue('I' . $rowNum, $row['kode_kamar']);
    $sheet->setCellValue('J' . $rowNum, $row['nama_bangsal']);
    $sheet->setCellValue('K' . $rowNum, $row['tgl_masuk']);
    $sheet->setCellValue('L' . $rowNum, $row['tgl_keluar']);
    $sheet->setCellValue('M' . $rowNum, $row['diagnosa_awal']);
    $sheet->setCellValue('N' . $rowNum, $row['diagnosa_akhir']);
    $sheet->setCellValue('O' . $rowNum, $row['tb']);
    $sheet->setCellValue('P' . $rowNum, $row['bb']);
    $sheet->setCellValue('Q' . $rowNum, $row['jenis_bayar']);
    $sheet->setCellValue('R' . $rowNum, $row['status_pulang']);
    $rowNum++;
}

if ($rowNum === 6) {
    $sheet->setCellValue('A6', 'Tidak ada data');
    $sheet->mergeCells('A6:R6');
    $sheet->getStyle('A6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $rowNum = 7;
}

$lastRow = $rowNum - 1;
$sheet->getStyle('A5:R' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle('A5:R' . $lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle('A:R')->getAlignment()->setWrapText(true);
foreach (range('A', 'R') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$filename = 'kunjungan_ranap_berdasarkan_usia_gizi_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
