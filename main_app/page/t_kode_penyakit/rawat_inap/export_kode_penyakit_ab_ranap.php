<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/assets/vendor/autoload.php';
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/koneksi.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

if (ob_get_length()) ob_end_clean();

$conn = $mysqli;
$tgl_awal = isset($_POST['tgl_awal']) && $_POST['tgl_awal'] !== '' ? trim((string) $_POST['tgl_awal']) : date('Y-m-01');
$tgl_akhir = isset($_POST['tgl_akhir']) && $_POST['tgl_akhir'] !== '' ? trim((string) $_POST['tgl_akhir']) : date('Y-m-d');

if ($tgl_awal > $tgl_akhir) {
    $tmp = $tgl_awal;
    $tgl_awal = $tgl_akhir;
    $tgl_akhir = $tmp;
}

$sql = "SELECT
            CASE
                WHEN TIMESTAMPDIFF(YEAR, ps.tgl_lahir, rp.tgl_registrasi) < 18 THEN 'ANAK'
                ELSE 'DEWASA'
            END AS kategori_umur,
            p.kd_penyakit,
            p.nm_penyakit,
            COUNT(DISTINCT dp.no_rawat) AS jumlah_kasus
        FROM diagnosa_pasien dp
        INNER JOIN penyakit p ON dp.kd_penyakit = p.kd_penyakit
        INNER JOIN reg_periksa rp ON dp.no_rawat = rp.no_rawat
        INNER JOIN kamar_inap ki ON rp.no_rawat = ki.no_rawat
        INNER JOIN pasien ps ON rp.no_rkm_medis = ps.no_rkm_medis
        WHERE dp.prioritas = '1'
          AND (p.kd_penyakit LIKE 'A%' OR p.kd_penyakit LIKE 'B%')
          AND DATE(rp.tgl_registrasi) BETWEEN ? AND ?
          AND rp.status_lanjut = 'Ranap'
          AND LOWER(ps.nm_pasien) NOT LIKE '%test%'
          AND LOWER(ps.nm_pasien) NOT LIKE '%tes%'
          AND LOWER(ps.nm_pasien) NOT LIKE '%coba%'
        GROUP BY kategori_umur, p.kd_penyakit, p.nm_penyakit
        ORDER BY kategori_umur ASC, jumlah_kasus DESC, p.kd_penyakit ASC";

$anak_rows = array();
$dewasa_rows = array();
$total_anak = 0;
$total_dewasa = 0;

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('ss', $tgl_awal, $tgl_akhir);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['jumlah_kasus'] = (int) $row['jumlah_kasus'];
        if ($row['kategori_umur'] === 'ANAK') {
            $anak_rows[] = $row;
            $total_anak += $row['jumlah_kasus'];
        } else {
            $dewasa_rows[] = $row;
            $total_dewasa += $row['jumlah_kasus'];
        }
    }
    $stmt->close();
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Kategori Anak');
$sheet->mergeCells('A1:D1');
$sheet->setCellValue('A1', 'REKAP PENYAKIT INFEKSI KODE PENYAKIT A/B RAWAT INAP');
$sheet->mergeCells('A2:D2');
$sheet->setCellValue('A2', 'Kategori Anak | Periode: ' . $tgl_awal . ' s.d. ' . $tgl_akhir . ' | Usia < 18 tahun');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$headers = array('No', 'Kode Penyakit', 'Nama Penyakit', 'Jumlah Kasus');
foreach ($headers as $idx => $header) {
    $col = chr(65 + $idx);
    $sheet->setCellValue($col . '4', $header);
}
$sheet->getStyle('A4:D4')->getFont()->setBold(true);
$sheet->getStyle('A4:D4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2F3944');
$sheet->getStyle('A4:D4')->getFont()->getColor()->setARGB('FFFFFFFF');

$rowNum = 5;
foreach ($anak_rows as $index => $row) {
    $sheet->setCellValue('A' . $rowNum, $index + 1);
    $sheet->setCellValue('B' . $rowNum, $row['kd_penyakit']);
    $sheet->setCellValue('C' . $rowNum, $row['nm_penyakit']);
    $sheet->setCellValue('D' . $rowNum, $row['jumlah_kasus']);
    $rowNum++;
}
if (empty($anak_rows)) {
    $sheet->setCellValue('A' . $rowNum, 'Tidak ada data kategori anak.');
    $sheet->mergeCells('A' . $rowNum . ':D' . $rowNum);
    $rowNum++;
}
$sheet->setCellValue('A' . $rowNum, 'Total Anak');
$sheet->mergeCells('A' . $rowNum . ':C' . $rowNum);
$sheet->setCellValue('D' . $rowNum, $total_anak);
$sheet->getStyle('A' . $rowNum . ':D' . $rowNum)->getFont()->setBold(true);
$sheet->getStyle('A' . $rowNum . ':D' . $rowNum)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDDEEFF');
$sheet->getStyle('A4:D' . $rowNum)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
foreach (range('A', 'D') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);
$sheet->getStyle('A:D')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle('A:D')->getAlignment()->setWrapText(true);

$dewasaSheet = $spreadsheet->createSheet();
$dewasaSheet->setTitle('Kategori Dewasa');
$dewasaSheet->mergeCells('A1:D1');
$dewasaSheet->setCellValue('A1', 'REKAP PENYAKIT INFEKSI KODE PENYAKIT A/B RAWAT INAP');
$dewasaSheet->mergeCells('A2:D2');
$dewasaSheet->setCellValue('A2', 'Kategori Dewasa | Periode: ' . $tgl_awal . ' s.d. ' . $tgl_akhir . ' | Usia >= 18 tahun');
$dewasaSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$dewasaSheet->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
foreach ($headers as $idx => $header) {
    $col = chr(65 + $idx);
    $dewasaSheet->setCellValue($col . '4', $header);
}
$dewasaSheet->getStyle('A4:D4')->getFont()->setBold(true);
$dewasaSheet->getStyle('A4:D4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2F3944');
$dewasaSheet->getStyle('A4:D4')->getFont()->getColor()->setARGB('FFFFFFFF');
$rowNum = 5;
foreach ($dewasa_rows as $index => $row) {
    $dewasaSheet->setCellValue('A' . $rowNum, $index + 1);
    $dewasaSheet->setCellValue('B' . $rowNum, $row['kd_penyakit']);
    $dewasaSheet->setCellValue('C' . $rowNum, $row['nm_penyakit']);
    $dewasaSheet->setCellValue('D' . $rowNum, $row['jumlah_kasus']);
    $rowNum++;
}
if (empty($dewasa_rows)) {
    $dewasaSheet->setCellValue('A' . $rowNum, 'Tidak ada data kategori dewasa.');
    $dewasaSheet->mergeCells('A' . $rowNum . ':D' . $rowNum);
    $rowNum++;
}
$dewasaSheet->setCellValue('A' . $rowNum, 'Total Dewasa');
$dewasaSheet->mergeCells('A' . $rowNum . ':C' . $rowNum);
$dewasaSheet->setCellValue('D' . $rowNum, $total_dewasa);
$dewasaSheet->getStyle('A' . $rowNum . ':D' . $rowNum)->getFont()->setBold(true);
$dewasaSheet->getStyle('A' . $rowNum . ':D' . $rowNum)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE9F7EF');
$dewasaSheet->getStyle('A4:D' . $rowNum)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
foreach (range('A', 'D') as $col) $dewasaSheet->getColumnDimension($col)->setAutoSize(true);
$dewasaSheet->getStyle('A:D')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
$dewasaSheet->getStyle('A:D')->getAlignment()->setWrapText(true);

$ringkasanSheet = $spreadsheet->createSheet();
$ringkasanSheet->setTitle('Ringkasan');
$ringkasanSheet->mergeCells('A1:C1');
$ringkasanSheet->setCellValue('A1', 'RINGKASAN KATEGORI UMUR');
$ringkasanSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$ringkasanSheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$ringkasanHeaders = array('Kategori', 'Keterangan', 'Jumlah Kasus');
foreach ($ringkasanHeaders as $idx => $header) {
    $col = chr(65 + $idx);
    $ringkasanSheet->setCellValue($col . '3', $header);
}
$ringkasanSheet->getStyle('A3:C3')->getFont()->setBold(true);
$ringkasanSheet->getStyle('A3:C3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2F3944');
$ringkasanSheet->getStyle('A3:C3')->getFont()->getColor()->setARGB('FFFFFFFF');
$ringkasanSheet->setCellValue('A4', 'ANAK');
$ringkasanSheet->setCellValue('B4', 'Usia < 18 tahun');
$ringkasanSheet->setCellValue('C4', $total_anak);
$ringkasanSheet->setCellValue('A5', 'DEWASA');
$ringkasanSheet->setCellValue('B5', 'Usia >= 18 tahun');
$ringkasanSheet->setCellValue('C5', $total_dewasa);
$ringkasanSheet->getStyle('A3:C5')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
foreach (range('A', 'C') as $col) $ringkasanSheet->getColumnDimension($col)->setAutoSize(true);

$spreadsheet->setActiveSheetIndex(0);
$filename = 'Rekap_Kode_Penyakit_AB_Ranap_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
