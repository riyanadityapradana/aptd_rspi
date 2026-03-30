<?php
require '../../assets/autoload.php';

// Clear output buffer
if (ob_get_length()) ob_end_clean();

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/koneksi.php';
$conn = $mysqli;

$bulan = isset($_POST['bulan']) ? $_POST['bulan'] : date('m');
$tahun = isset($_POST['tahun']) ? $_POST['year'] : date('Y');
if ((int)$bulan < 1 || (int)$bulan > 12) $bulan = date('m');
if ((int)$tahun < 2000 || (int)$tahun > 2100) $tahun = date('Y');

// Support both old (bulan/tahun) and new (start_date/end_date) format
$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-01');
$end_date = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-t');

// Validasi input
if (!strtotime($start_date)) $start_date = date('Y-m-01');
if (!strtotime($end_date)) $end_date = date('Y-m-t');

$sql = "SELECT kab.nm_kab AS kabupaten,
        SUM(CASE WHEN rp.kd_pj = 'A09' THEN 1 ELSE 0 END) AS Umum,
        SUM(CASE WHEN rp.kd_pj = 'BPJ' THEN 1 ELSE 0 END) AS BPJS,
        SUM(CASE WHEN rp.kd_pj = 'A92' THEN 1 ELSE 0 END) AS Asuransi,
        COUNT(*) AS Total
FROM reg_periksa rp
JOIN pasien p      ON p.no_rkm_medis = rp.no_rkm_medis
JOIN penjab pj     ON pj.kd_pj = rp.kd_pj
JOIN poliklinik pl ON pl.kd_poli = rp.kd_poli
JOIN kabupaten kab ON kab.kd_kab = p.kd_kab
WHERE rp.tgl_registrasi BETWEEN ? AND ?
  AND rp.status_lanjut = 'Ralan'
  AND rp.stts <> 'Batal'
  AND pl.nm_poli NOT IN ('Tinggal Rawat Inap', 'Unit Laboratorium', 'OBGYN', 'PONEK', 'TEST', 'Unit Gizi', 'Poli Vaksin', 'UGD', 'HEMODIALISA')
  AND kab.nm_kab IN ('BANJARMASIN', 'BANJARBARU', 'BANJAR')
  AND rp.kd_pj IN ('A09','A92','BPJ')
GROUP BY kab.nm_kab
ORDER BY FIELD(kab.nm_kab, 'BANJARMASIN', 'BANJARBARU', 'BANJAR')";

$stmt = $conn->prepare($sql);
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}

// Create spreadsheet
$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$sheet->mergeCells('A1:E1');
$sheet->setCellValue('A1', 'REKAP PASIEN RAWAT JALAN PER KABUPATEN');
$sheet->getStyle('A1')->getFont()->setBold(true);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

$sheet->mergeCells('A2:E2');
$sheet->setCellValue('A2', 'Periode: ' . $start_date . ' sampai ' . $end_date);

$headers = ['No','Kabupaten','Umum','BPJS','Asuransi','Total'];
$headerRow = 4;
foreach ($headers as $i => $h) {
    $col = chr(65 + $i);
    $sheet->setCellValue($col.$headerRow, $h);
    $sheet->getStyle($col.$headerRow)->getFont()->setBold(true);
}

$rowNum = 5;
$total_umum = $total_bpjs = $total_asuransi = $total_pasien = 0;
foreach ($data as $idx => $r) {
    $sheet->setCellValue('A'.$rowNum, $idx+1);
    $sheet->setCellValue('B'.$rowNum, $r['kabupaten']);
    $sheet->setCellValue('C'.$rowNum, $r['Umum']);
    $sheet->setCellValue('D'.$rowNum, $r['BPJS']);
    $sheet->setCellValue('E'.$rowNum, $r['Asuransi']);
    $sheet->setCellValue('F'.$rowNum, $r['Total']);

    $total_umum += (int)$r['Umum'];
    $total_bpjs += (int)$r['BPJS'];
    $total_asuransi += (int)$r['Asuransi'];
    $total_pasien += (int)$r['Total'];

    $rowNum++;
}

// totals row
$sheet->setCellValue('B'.$rowNum, 'JUMLAH TOTAL');
$sheet->setCellValue('C'.$rowNum, $total_umum);
$sheet->setCellValue('D'.$rowNum, $total_bpjs);
$sheet->setCellValue('E'.$rowNum, $total_asuransi);
$sheet->setCellValue('F'.$rowNum, $total_pasien);

// Style totals row with yellow background
for ($col = 'B'; $col <= 'F'; $col++) {
    $sheet->getStyle($col.$rowNum)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $sheet->getStyle($col.$rowNum)->getFill()->getStartColor()->setARGB('FFFFFF00');
    $sheet->getStyle($col.$rowNum)->getFont()->setBold(true);
}

// Output
$filename = 'rekap_kunjungan_perkab_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="'.$filename.'"');
header('Cache-Control: max-age=0');

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');
exit();
?>
