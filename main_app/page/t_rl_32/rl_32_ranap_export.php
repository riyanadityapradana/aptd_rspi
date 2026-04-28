<?php
ob_start();
require_once __DIR__ . '/rl_32_helper.php';
require_once rl32_root_path() . '/assets/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

try {
    $mysqli = rl32_bootstrap();

    $bulan = isset($_GET['bulan']) ? (int) $_GET['bulan'] : (int) date('m');
    $tahun = isset($_GET['tahun']) ? (int) $_GET['tahun'] : (int) date('Y');
    if ($bulan < 1 || $bulan > 12) {
        $bulan = (int) date('m');
    }
    if ($tahun < 2000 || $tahun > 2100) {
        $tahun = (int) date('Y');
    }

    $data = rl32_get_main_report($mysqli, $tahun, $bulan);
    $namaPelayananMap = rl32_service_labels();
    $processedData = [];
    foreach ($data as $row) {
        $kodePelayanan = $row['Jenis Pelayanan'];
        $row['Jenis Pelayanan'] = $namaPelayananMap[$kodePelayanan] ?? $kodePelayanan;
        $processedData[] = array_values($row);
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $namaBulan = date('F', mktime(0, 0, 0, $bulan, 10));
    $sheet->setTitle('RL 3.2 - ' . $namaBulan . ' ' . $tahun);

    $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
    $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
    $sheet->getPageMargins()->setTop(0.75)->setRight(0.25)->setLeft(0.25)->setBottom(0.75);
    $sheet->getPageSetup()->setFitToWidth(1);
    $sheet->getPageSetup()->setFitToHeight(0);

    $sheet->mergeCells('A1:U1')->setCellValue('A1', 'RL 3.2 REKAPITULASI KEGIATAN PELAYANAN RAWAT INAP');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->mergeCells('A2:U2')->setCellValue('A2', 'PERIODE: ' . strtoupper($namaBulan) . ' ' . $tahun);
    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $startRow = 4;
    $sheet->mergeCells('A' . $startRow . ':A' . ($startRow + 2))->setCellValue('A' . $startRow, 'No');
    $sheet->mergeCells('B' . $startRow . ':B' . ($startRow + 2))->setCellValue('B' . $startRow, 'Jenis Pelayanan');
    $sheet->mergeCells('C' . $startRow . ':C' . ($startRow + 2))->setCellValue('C' . $startRow, 'Pasien Awal Bulan');
    $sheet->mergeCells('D' . $startRow . ':D' . ($startRow + 2))->setCellValue('D' . $startRow, 'Pasien Masuk');
    $sheet->mergeCells('E' . $startRow . ':E' . ($startRow + 2))->setCellValue('E' . $startRow, 'Pasien Pindahan');
    $sheet->mergeCells('F' . $startRow . ':F' . ($startRow + 2))->setCellValue('F' . $startRow, 'Pasien Dipindahkan');
    $sheet->mergeCells('G' . $startRow . ':G' . ($startRow + 2))->setCellValue('G' . $startRow, 'Pasien Keluar Hidup');
    $sheet->mergeCells('H' . $startRow . ':K' . $startRow)->setCellValue('H' . $startRow, 'Pasien Keluar Mati');
    $sheet->mergeCells('L' . $startRow . ':L' . ($startRow + 2))->setCellValue('L' . $startRow, 'Jumlah Lama Dirawat');
    $sheet->mergeCells('M' . $startRow . ':M' . ($startRow + 2))->setCellValue('M' . $startRow, 'Pasien Akhir Bulan');
    $sheet->mergeCells('N' . $startRow . ':N' . ($startRow + 2))->setCellValue('N' . $startRow, 'Jumlah Hari Perawatan');
    $sheet->mergeCells('O' . $startRow . ':T' . $startRow)->setCellValue('O' . $startRow, 'Rincian Hari Perawatan per Kelas');
    $sheet->mergeCells('U' . $startRow . ':U' . ($startRow + 2))->setCellValue('U' . $startRow, 'Jumlah alokasi tempat tidur awal bulan');

    $sheet->mergeCells('H' . ($startRow + 1) . ':I' . ($startRow + 1))->setCellValue('H' . ($startRow + 1), 'Pasien Laki-Laki');
    $sheet->mergeCells('J' . ($startRow + 1) . ':K' . ($startRow + 1))->setCellValue('J' . ($startRow + 1), 'Pasien Perempuan');
    $sheet->mergeCells('O' . ($startRow + 1) . ':O' . ($startRow + 2))->setCellValue('O' . ($startRow + 1), 'VVIP');
    $sheet->mergeCells('P' . ($startRow + 1) . ':P' . ($startRow + 2))->setCellValue('P' . ($startRow + 1), 'VIP');
    $sheet->mergeCells('Q' . ($startRow + 1) . ':Q' . ($startRow + 2))->setCellValue('Q' . ($startRow + 1), 'I');
    $sheet->mergeCells('R' . ($startRow + 1) . ':R' . ($startRow + 2))->setCellValue('R' . ($startRow + 1), 'II');
    $sheet->mergeCells('S' . ($startRow + 1) . ':S' . ($startRow + 2))->setCellValue('S' . ($startRow + 1), 'III');
    $sheet->mergeCells('T' . ($startRow + 1) . ':T' . ($startRow + 2))->setCellValue('T' . ($startRow + 1), 'Kelas Khusus');

    $sheet->setCellValue('H' . ($startRow + 2), '<48 jam');
    $sheet->setCellValue('I' . ($startRow + 2), '>=48 jam');
    $sheet->setCellValue('J' . ($startRow + 2), '<48 jam');
    $sheet->setCellValue('K' . ($startRow + 2), '>=48 jam');

    if (count($processedData) > 0) {
        $sheet->fromArray($processedData, null, 'A' . ($startRow + 3));
    } else {
        $sheet->mergeCells('A' . ($startRow + 3) . ':U' . ($startRow + 3));
        $sheet->setCellValue('A' . ($startRow + 3), 'Tidak ada data untuk periode yang dipilih.');
    }

    $headerRange = 'A' . $startRow . ':U' . ($startRow + 2);
    $lastRow = $sheet->getHighestRow();
    $dataRange = 'A' . $startRow . ':U' . $lastRow;

    $sheet->getStyle($headerRange)->applyFromArray([
        'font' => ['bold' => true],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
            'wrapText' => true,
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['argb' => 'FFD9D9D9'],
        ],
    ]);

    $sheet->getStyle($dataRange)->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => 'FF000000'],
            ],
        ],
    ]);

    $sheet->getStyle('A' . ($startRow + 3) . ':U' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A' . ($startRow + 3) . ':U' . $lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

    foreach (range('A', 'U') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }
    $sheet->getColumnDimension('B')->setWidth(20);
    $sheet->getColumnDimension('U')->setWidth(20);

    if (ob_get_length()) {
        ob_end_clean();
    }

    $filename = 'RL3.2_Pelayanan_Rawat_Inap_' . $tahun . '_' . str_pad((string) $bulan, 2, '0', STR_PAD_LEFT) . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} catch (Throwable $e) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    echo 'Export RL 3.2 gagal: ' . $e->getMessage();
    exit;
}