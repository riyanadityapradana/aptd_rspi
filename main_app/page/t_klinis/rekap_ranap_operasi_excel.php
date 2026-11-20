<?php
require_once dirname(__DIR__) . '/export_excel_helper.php';

function aptd_ranap_operasi_workbook(array $report, array $wards, $downloadedAt)
{
    if (!aptd_excel_bootstrap()) {
        throw new RuntimeException('Pustaka Excel belum tersedia. Hubungi tim IT.');
    }
    $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $summaryRows = [];
    foreach ($report['summary'] as $row) {
        $summaryRows[] = [$row['nm_bangsal'], $row['BPJS'], $row['Umum'], $row['Asuransi'], $row['Total']];
    }
    $summaryRows[] = array_merge(['Total'], array_values($report['totals']));
    $detailRows = [];
    foreach ($report['rows'] as $row) {
        $detailRows[] = [$row['no_rkm_medis'], $row['nm_pasien'], $row['nama_ruangan'], $row['lama_rawat'], $row['nama_tindakan']];
    }
    $subtitle = aptd_ranap_operasi_subtitle($report['filters'], $wards);
    foreach ([
        ['Rekapitulasi', ['Bangsal', 'BPJS', 'Umum', 'Asuransi', 'Total'], $summaryRows],
        ['Rincian Pasien', ['No. RM', 'Nama Pasien', 'Nama Ruangan', 'Lama Rawat (Hari)', 'Nama Tindakan Operasi'], $detailRows],
    ] as $index => $table) {
        $sheet = $index === 0 ? $book->getActiveSheet() : $book->createSheet();
        $sheet->setTitle($table[0]);
        foreach ([1 => 'Rekap Pasien Rawat Inap dengan Tindakan Operasi', 2 => $subtitle, 3 => 'Diunduh: ' . $downloadedAt] as $number => $value) {
            $sheet->setCellValueExplicit('A' . $number, $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->mergeCells('A' . $number . ':E' . $number);
        }
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15);
        $sheet->fromArray($table[1], null, 'A5');
        $number = 6;
        foreach ($table[2] as $row) {
            foreach ($row as $column => $value) {
                $sheet->setCellValueExplicit(aptd_excel_cell($column + 1, $number), $value,
                    is_int($value) ? \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC : \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            $number++;
        }
        if (!$table[2]) { $sheet->setCellValue('A6', 'Tidak ada pasien operasi pada filter ini.'); $sheet->mergeCells('A6:E6'); $number = 7; }
        $sheet->getStyle('A5:E5')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A5:E5')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF173D67');
        $sheet->getStyle('A5:E' . ($number - 1))->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $sheet->getStyle('A6:E' . ($number - 1))->getAlignment()->setVertical('top')->setWrapText(true);
        foreach (['A' => ($index === 0 ? 34 : 18), 'B' => ($index === 0 ? 14 : 34), 'C' => 18, 'D' => 22, 'E' => ($index === 0 ? 14 : 70)] as $column => $width) { $sheet->getColumnDimension($column)->setWidth($width); }
        if ($index === 0) { $sheet->getStyle('A' . ($number - 1) . ':E' . ($number - 1))->getFont()->setBold(true); }
        if ($index === 1 && $detailRows) { $sheet->setAutoFilter('A5:E' . ($number - 1)); }
        $sheet->freezePane('A6');
    }
    $book->setActiveSheetIndex(0);
    return $book;
}
