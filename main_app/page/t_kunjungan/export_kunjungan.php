<?php require_once('../config/koneksi.php'); ?>
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

// Comprehensive poli mapping
$mapping_poli = [
	'GIGI' => ['U0008', 'U0025', 'U0042', 'U0043', 'U0052', 'U0057', 'U0065'],
	'BEDAH' => ['U0002', 'U0004', 'U0015', 'U0054', 'U0066'],
	'ANAK' => ['U0003', 'U0069', 'U0068', 'U0070'],
	'THT' => ['U0006', 'U0011'],
	'PENYAKIT DALAM' => ['U0023', 'U0030', 'U0031', 'U0033', 'U0034', 'U0035', 'U0036', 'U0037', 'U0038', 'U0039', 'U0040', 'U0041', 'U0063'],
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

// Get filter values
$filter_poli = isset($_POST['poli']) ? trim($_POST['poli']) : 'PENYAKIT DALAM';
$filter_month = isset($_POST['month']) ? intval($_POST['month']) : 12;
$filter_year = isset($_POST['year']) ? intval($_POST['year']) : 2025;

// Get poli codes for selected poli group
$poli_codes = isset($mapping_poli[$filter_poli]) ? $mapping_poli[$filter_poli] : [];

// Build where conditions
$data = [];

if(!empty($poli_codes)){
	$poli_codes_str = "'" . implode("','", array_map(function($v) use($mysqli){ return mysqli_real_escape_string($mysqli, $v); }, $poli_codes)) . "'";
	
	$whereParts = [
		"rp.kd_poli IN (".$poli_codes_str.")",
		"rp.stts = 'Sudah'",
		"rp.status_bayar = 'Sudah Bayar'",
		"rp.no_rkm_medis NOT IN (SELECT no_rkm_medis FROM pasien WHERE LOWER(nm_pasien) LIKE '%test%')"
	];
	
	if($filter_month && $filter_year){
		$start = sprintf('%04d-%02d-01',$filter_year,$filter_month);
		$end = date('Y-m-t', strtotime($start));
		$whereParts[] = "rp.tgl_registrasi BETWEEN '".$start."' AND '".$end."'";
	}
	
	// Get data for each payment type
	foreach($penjamin as $kd_pj => $label){
		$sql = "SELECT COUNT(*) as jml FROM reg_periksa rp WHERE rp.kd_pj = '".$kd_pj."' AND " . implode(' AND ', $whereParts);
		$result = mysqli_query($mysqli, $sql);
		if($result){
			$row = mysqli_fetch_assoc($result);
			$data[$kd_pj] = isset($row['jml']) ? (int)$row['jml'] : 0;
		} else {
			$data[$kd_pj] = 0;
		}
	}
	
	$total = array_sum($data);
} else {
	$data = array_fill_keys(array_keys($penjamin), 0);
	$total = 0;
}

// Create Excel file
$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Title
$sheet->mergeCells('A1:F1');
$sheet->setCellValue('A1', 'DATA KUNJUNGAN PASIEN');
$sheet->getStyle('A1')->getFont()->setBold(true);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

// Filter info
$sheet->mergeCells('A2:F2');
$monthName = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$filterInfo = "Filter: ".$filter_poli." | Bulan: ".$monthName[$filter_month-1]." ".$filter_year;
$sheet->setCellValue('A2', $filterInfo);
$sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);

// Headers
$headers = ['No.', 'Poliklinik', 'UMUM', 'BPJS', 'ASURANSI', 'Jumlah Total'];
$headerRow = 4;
foreach ($headers as $col => $header) {
	$colLetter = chr(65 + $col);
	$sheet->setCellValue($colLetter . $headerRow, $header);
	$sheet->getStyle($colLetter . $headerRow)->getFont()->setBold(true);
	$sheet->getStyle($colLetter . $headerRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
	$sheet->getStyle($colLetter . $headerRow)->getFill()->getStartColor()->setARGB('FFCCCCCC');
	$sheet->getStyle($colLetter . $headerRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
}

// Data row
$row = 5;
$sheet->setCellValue('A' . $row, 1);
$sheet->setCellValue('B' . $row, $filter_poli);
$sheet->setCellValue('C' . $row, $data['A09']);
$sheet->setCellValue('D' . $row, $data['BPJ']);
$sheet->setCellValue('E' . $row, $data['A92']);
$sheet->setCellValue('F' . $row, $total);

// Total row
$row++;
$sheet->setCellValue('A' . $row, '');
$sheet->setCellValue('B' . $row, 'TOTAL:');
$sheet->setCellValue('C' . $row, $data['A09']);
$sheet->setCellValue('D' . $row, $data['BPJ']);
$sheet->setCellValue('E' . $row, $data['A92']);
$sheet->setCellValue('F' . $row, $total);
$sheet->getStyle('B' . $row)->getFont()->setBold(true);
$sheet->getStyle('C' . $row . ':F' . $row)->getFont()->setBold(true);
$sheet->getStyle('A' . $row . ':F' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$sheet->getStyle('A' . $row . ':F' . $row)->getFill()->getStartColor()->setARGB('FFF0F0F0');

// Set column widths
$sheet->getColumnDimension('A')->setWidth(5);
$sheet->getColumnDimension('B')->setWidth(20);
$sheet->getColumnDimension('C')->setWidth(12);
$sheet->getColumnDimension('D')->setWidth(12);
$sheet->getColumnDimension('E')->setWidth(12);
$sheet->getColumnDimension('F')->setWidth(15);

// Add borders
$range = 'A4:F' . $row;
$sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

// Set alignment for data cells
for ($i = 5; $i <= $row; $i++) {
	$sheet->getStyle('A' . $i)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
	$sheet->getStyle('C' . $i)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
	$sheet->getStyle('D' . $i)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
	$sheet->getStyle('E' . $i)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
	$sheet->getStyle('F' . $i)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
}

// Output file
$filename = 'Data_Kunjungan_' . date('Y-m-d') . '.xlsx';
// ensure no stray output before headers
if (ob_get_length()) ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');
exit();
?>

