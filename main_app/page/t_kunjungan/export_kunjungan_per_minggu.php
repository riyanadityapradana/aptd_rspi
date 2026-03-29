<?php
// start buffering and suppress on-screen errors to avoid corrupting output
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Try to load Composer autoloader (local first, then rl_app fallback)
$localVendor = __DIR__ . '/../../../assets/vendor/autoload.php';
$externalVendor = 'C:\\xampp\\htdocs\\rl_app\\assets\\vendor\\autoload.php';
if (file_exists($localVendor)) {
    require $localVendor;
} elseif (file_exists($externalVendor)) {
    require $externalVendor;
}

// Clear any buffered output from autoload/etc.
if (ob_get_length()) ob_end_clean();

require_once('../config/koneksi.php');
$conn = $mysqli;

// Comprehensive poli mapping
$mapping_poli = [
    'GIGI' => ['U0008', 'U0025', 'U0042', 'U0043', 'U0052', 'U0057', 'U0065'],
    'BEDAH' => ['U0002', 'U0004', 'U0015', 'U0054', 'U0066'],
    'ANAK' => ['U0003', 'U0069', 'U0068', 'U0070'],
    'THT' => ['U0006', 'U0011'],
    'PENYAKIT DALAM' => ['U0023', 'U0030', 'U0031', 'U0032', 'U0033', 'U0034', 'U0035', 'U0036', 'U0037', 'U0038', 'U0039', 'U0040', 'U0041', 'U0063'],
    'PARU' => ['U0019'],
    'SARAF' => ['U0007', 'U0049', 'U0050'],
    'MATA' => ['U0005', 'U0061'],
    'KANDUNGAN' => ['U0010', 'U0024', 'U0028', 'U0044', 'U0045', 'U0046', 'U0047', 'U0048', 'U0051', 'U0059', 'U0060', 'U0075', 'U0076'],
    'REHABILITASI MEDIK' => ['kfr'],
    'JANTUNG' => ['U0012', 'U0032'],
    'JIWA' => ['U0013', 'U0018'],
    'ORTHOPEDI' => ['U0014', 'U0016'],
];

// Mapping jenis pembayar
$penjamin = [
    'A09' => 'UMUM',
    'BPJ' => 'BPJS',
    'A92' => 'ASURANSI',
];

// Read filter values from POST
$filter_month = isset($_POST['bulan']) ? intval($_POST['bulan']) : date('n');
$filter_year = isset($_POST['tahun']) ? intval($_POST['tahun']) : date('Y');

// Generate weeks for the month (Tuesday to Tuesday)
function generateWeeksFromTuesday($year, $month) {
    $first_day = strtotime("$year-$month-01");
    $last_day = strtotime(date('Y-m-t', $first_day));
    
    $weeks = [];
    $current = $first_day;
    
    // Find the first Tuesday of the month
    $first_tuesday = $first_day;
    $day_of_week = date('w', $first_tuesday); // 0=Sunday, 2=Tuesday
    
    if ($day_of_week != 2) {
        // If not Tuesday, find the next Tuesday
        $days_until_tuesday = ($day_of_week <= 2) ? (2 - $day_of_week) : (9 - $day_of_week);
        $first_tuesday = strtotime("+$days_until_tuesday days", $first_tuesday);
    }
    
    $current = $first_tuesday;
    while ($current <= $last_day) {
        $week_start = $current;
        $week_end = strtotime("+6 days", $week_start);
        
        if ($week_end > $last_day) {
            $week_end = $last_day;
        }
        
        $weeks[] = [
            'start' => date('Y-m-d', $week_start),
            'end' => date('Y-m-d', $week_end),
            'label' => date('d', $week_start) . ' - ' . date('d M Y', $week_end)
        ];
        
        $current = strtotime("+1 day", $week_end);
    }
    
    return $weeks;
}

$weeks = generateWeeksFromTuesday($filter_year, $filter_month);

// Query data untuk semua minggu dan semua poli
$data_by_poli = [];
$totals_by_penjamin = ['A09' => 0, 'BPJ' => 0, 'A92' => 0];
$totals_by_week = [];

foreach ($mapping_poli as $poli_name => $poli_codes) {
    $data_by_poli[$poli_name] = [];
    
    foreach ($weeks as $week_idx => $week) {
        if (!isset($totals_by_week[$week_idx])) {
            $totals_by_week[$week_idx] = ['A09' => 0, 'BPJ' => 0, 'A92' => 0];
        }
        
        $poli_codes_str = "'" . implode("','", array_map(function($v) use ($conn) {
            return $conn->real_escape_string($v);
        }, $poli_codes)) . "'";
        
        $data_by_poli[$poli_name][$week_idx] = [];
        
        foreach ($penjamin as $kd_pj => $label) {
            $sql = "SELECT COUNT(*) as jml FROM reg_periksa rp
                    WHERE rp.kd_poli IN ($poli_codes_str)
                    AND rp.kd_pj = '$kd_pj'
                    AND rp.status_lanjut = 'Ralan'
                    AND rp.stts <> 'Batal'
                    AND rp.tgl_registrasi BETWEEN '" . $week['start'] . "' AND '" . $week['end'] . "'";
            
            $result = $conn->query($sql);
            $row = $result->fetch_assoc();
            $jml = isset($row['jml']) ? (int)$row['jml'] : 0;
            
            $data_by_poli[$poli_name][$week_idx][$kd_pj] = $jml;
            $totals_by_penjamin[$kd_pj] += $jml;
            $totals_by_week[$week_idx][$kd_pj] += $jml;
        }
    }
}

// Create spreadsheet
$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Title
$title_col_end = chr(64 + (count($weeks) * 4 + 1));
$sheet->mergeCells('A1:' . $title_col_end . '1');
$sheet->setCellValue('A1', 'REKAP KUNJUNGAN PASIEN HARIAN RAWAT JALAN');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

// Period info
$sheet->mergeCells('A2:' . $title_col_end . '2');
$months = [1=>"Januari",2=>"Februari",3=>"Maret",4=>"April",5=>"Mei",6=>"Juni",7=>"Juli",8=>"Agustus",9=>"September",10=>"Oktober",11=>"November",12=>"Desember"];
$period_text = $months[$filter_month] . ' ' . $filter_year;
$sheet->setCellValue('A2', 'Bulan: ' . $period_text);
$sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

// Header rows
$current_row = 4;
$sheet->setCellValue('A' . $current_row, 'POLIKLINIK');

$col_idx = 2;
$col_letter = 'B';
foreach ($weeks as $week) {
    $sheet->mergeCells($col_letter . $current_row . ':' . chr(64 + $col_idx + 3) . $current_row);
    $sheet->setCellValue($col_letter . $current_row, $week['label']);
    $sheet->getStyle($col_letter . $current_row)->getFont()->setBold(true);
    $sheet->getStyle($col_letter . $current_row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $sheet->getStyle($col_letter . $current_row)->getFill()->getStartColor()->setARGB('FFFFFF00');
    
    $col_idx += 4;
    $col_letter = chr(64 + $col_idx + 1);
}

// Sub-header row
$current_row++;
$sheet->setCellValue('A' . $current_row, '');

$col_idx = 2;
foreach ($weeks as $week) {
    $sheet->setCellValue(chr(64 + $col_idx) . $current_row, 'UMUM');
    $sheet->setCellValue(chr(64 + $col_idx + 1) . $current_row, 'BPJS');
    $sheet->setCellValue(chr(64 + $col_idx + 2) . $current_row, 'ASURANSI');
    $sheet->setCellValue(chr(64 + $col_idx + 3) . $current_row, 'JLH');
    
    for ($i = 0; $i < 4; $i++) {
        $sheet->getStyle(chr(64 + $col_idx + $i) . $current_row)->getFont()->setBold(true);
        $sheet->getStyle(chr(64 + $col_idx + $i) . $current_row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $sheet->getStyle(chr(64 + $col_idx + $i) . $current_row)->getFill()->getStartColor()->setARGB('FFFFFF00');
    }
    
    $col_idx += 4;
}

// Data rows
$current_row++;
foreach ($mapping_poli as $poli_name => $codes) {
    $sheet->setCellValue('A' . $current_row, $poli_name);
    $sheet->getStyle('A' . $current_row)->getFont()->setBold(true);
    
    $col_idx = 2;
    foreach ($weeks as $week_idx => $week) {
        $umum = isset($data_by_poli[$poli_name][$week_idx]['A09']) ? $data_by_poli[$poli_name][$week_idx]['A09'] : 0;
        $bpjs = isset($data_by_poli[$poli_name][$week_idx]['BPJ']) ? $data_by_poli[$poli_name][$week_idx]['BPJ'] : 0;
        $asuransi = isset($data_by_poli[$poli_name][$week_idx]['A92']) ? $data_by_poli[$poli_name][$week_idx]['A92'] : 0;
        $total = $umum + $bpjs + $asuransi;
        
        $sheet->setCellValue(chr(64 + $col_idx) . $current_row, $umum);
        $sheet->setCellValue(chr(64 + $col_idx + 1) . $current_row, $bpjs);
        $sheet->setCellValue(chr(64 + $col_idx + 2) . $current_row, $asuransi);
        $sheet->setCellValue(chr(64 + $col_idx + 3) . $current_row, $total);
        
        // Style total cell
        $sheet->getStyle(chr(64 + $col_idx + 3) . $current_row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $sheet->getStyle(chr(64 + $col_idx + 3) . $current_row)->getFill()->getStartColor()->setARGB('FFFFFF99');
        $sheet->getStyle(chr(64 + $col_idx + 3) . $current_row)->getFont()->setBold(true);
        
        $col_idx += 4;
    }
    
    $current_row++;
}

// Summary rows
$current_row++;

// Jumlah per jenis bayar row
$sheet->mergeCells('A' . $current_row . ':A' . ($current_row + 1));
$sheet->setCellValue('A' . $current_row, 'JUMLAH PER JENIS BAYAR');
$sheet->getStyle('A' . $current_row)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));
$sheet->getStyle('A' . $current_row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$sheet->getStyle('A' . $current_row)->getFill()->getStartColor()->setARGB('FFFF6B6B');

$col_idx = 2;
foreach ($weeks as $week_idx => $week) {
    $umum = isset($totals_by_week[$week_idx]['A09']) ? $totals_by_week[$week_idx]['A09'] : 0;
    $bpjs = isset($totals_by_week[$week_idx]['BPJ']) ? $totals_by_week[$week_idx]['BPJ'] : 0;
    $asuransi = isset($totals_by_week[$week_idx]['A92']) ? $totals_by_week[$week_idx]['A92'] : 0;
    
    $sheet->setCellValue(chr(64 + $col_idx) . $current_row, $umum);
    $sheet->setCellValue(chr(64 + $col_idx + 1) . $current_row, $bpjs);
    $sheet->setCellValue(chr(64 + $col_idx + 2) . $current_row, $asuransi);
    
    for ($i = 0; $i < 3; $i++) {
        $sheet->getStyle(chr(64 + $col_idx + $i) . $current_row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $sheet->getStyle(chr(64 + $col_idx + $i) . $current_row)->getFill()->getStartColor()->setARGB('FFFF6B6B');
        $sheet->getStyle(chr(64 + $col_idx + $i) . $current_row)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));
    }
    
    $col_idx += 4;
}

$current_row++;

// Jumlah pasien per minggu row
$sheet->mergeCells('A' . $current_row . ':A' . $current_row);
$sheet->setCellValue('A' . $current_row, 'JUMLAH PX PER MINGGU');
$sheet->getStyle('A' . $current_row)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));
$sheet->getStyle('A' . $current_row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$sheet->getStyle('A' . $current_row)->getFill()->getStartColor()->setARGB('FFFF6B6B');

$col_idx = 2;
foreach ($weeks as $week_idx => $week) {
    $total_minggu = (isset($totals_by_week[$week_idx]['A09']) ? $totals_by_week[$week_idx]['A09'] : 0) +
                   (isset($totals_by_week[$week_idx]['BPJ']) ? $totals_by_week[$week_idx]['BPJ'] : 0) +
                   (isset($totals_by_week[$week_idx]['A92']) ? $totals_by_week[$week_idx]['A92'] : 0);
    
    $sheet->mergeCells(chr(64 + $col_idx) . $current_row . ':' . chr(64 + $col_idx + 3) . $current_row);
    $sheet->setCellValue(chr(64 + $col_idx) . $current_row, $total_minggu);
    
    for ($i = 0; $i < 4; $i++) {
        $sheet->getStyle(chr(64 + $col_idx + $i) . $current_row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $sheet->getStyle(chr(64 + $col_idx + $i) . $current_row)->getFill()->getStartColor()->setARGB('FFFF6B6B');
        $sheet->getStyle(chr(64 + $col_idx + $i) . $current_row)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));
    }
    
    $col_idx += 4;
}

// Set column widths
$sheet->getColumnDimension('A')->setWidth(20);
for ($col_idx = 2; $col_idx <= (count($weeks) * 4 + 1); $col_idx++) {
    $sheet->getColumnDimension(chr(64 + $col_idx))->setWidth(12);
}

// Output
$filename = 'rekap_kunjungan_per_minggu_' . date('Ymd_His') . '.xlsx';
if (ob_get_length()) ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="'.$filename.'"');
header('Cache-Control: max-age=0');

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');
exit();
?>

