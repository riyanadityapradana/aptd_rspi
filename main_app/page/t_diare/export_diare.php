<?php
// start buffering and suppress on-screen errors to avoid corrupting output
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Try to load Composer autoloader (local first, then rl_app fallback)
$localVendor = __DIR__ . '/../../../assets/vendor/autoload.php';
$externalVendor = 'C:\xampp\htdocs\rl_app\assets\vendor\autoload.php';
if (file_exists($localVendor)) {
    require $localVendor;
} elseif (file_exists($externalVendor)) {
    require $externalVendor;
} // else we'll continue without external libs (CSV fallback will handle it)

// phpspreadsheet class imports (safe to declare even if library absent)
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
// Memastikan DataType juga diimpor untuk penanganan khusus pada kolom NIK
use PhpOffice\PhpSpreadsheet\Cell\DataType;

// Clear any buffered output from autoload/etc.
if (ob_get_length()) ob_end_clean();
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Include database connection
require_once('../../../config/koneksi.php');

// Get filter values from POST
$filter_status = isset($_POST['status']) ? trim($_POST['status']) : 'all';
$filter_month = isset($_POST['month']) ? intval($_POST['month']) : 1;
$filter_year = isset($_POST['year']) ? intval($_POST['year']) : 2026;

// Define months array
$months = [1=>"Januari",2=>"Februari",3=>"Maret",4=>"April",5=>"Mei",6=>"Juni",7=>"Juli",8=>"Agustus",9=>"September",10=>"Oktober",11=>"November",12=>"Desember"];

// Build dynamic query based on filters (same logic as diare_data.php)
$whereParts = array();

// date range from month+year
if($filter_month && $filter_year){
	$start = sprintf('%04d-%02d-01',$filter_year,$filter_month);
	$end = date('Y-m-t', strtotime($start));
	$whereParts[] = "rp.tgl_registrasi BETWEEN '".$start."' AND '".$end."'";
} else {
	$whereParts[] = "rp.tgl_registrasi BETWEEN '2026-01-01' AND '2026-01-31'";
}

// diagnosis conditions
$whereParts[] = "( LOWER(ki.diagnosa_awal) LIKE '%diare%' OR LOWER(ki.diagnosa_akhir) LIKE '%diare%' OR LOWER(ki.diagnosa_awal) LIKE '%gea%' OR LOWER(ki.diagnosa_akhir) LIKE '%gea%' OR LOWER(ki.diagnosa_awal) LIKE '%disentri%' OR LOWER(ki.diagnosa_akhir) LIKE '%disentri%')";

// status filter
if($filter_status !== 'all' && $filter_status !== ''){
	$status_esc = mysqli_real_escape_string($mysqli, $filter_status);
	$whereParts[] = "ki.stts_pulang = '".$status_esc."'";
}

$sql = "SELECT p.no_rkm_medis, p.nm_pasien, p.jk, p.no_ktp AS nik, p.tgl_lahir, p.alamat, rp.no_rawat, rp.tgl_registrasi, ki.tgl_masuk, ki.tgl_keluar, ki.stts_pulang, ki.lama, CONCAT(k.kd_kamar, ' ', b.nm_bangsal) AS kamar, ki.diagnosa_awal, ki.diagnosa_akhir FROM kamar_inap ki JOIN reg_periksa rp ON ki.no_rawat = rp.no_rawat JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis LEFT JOIN kamar k ON ki.kd_kamar = k.kd_kamar LEFT JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal";

if(count($whereParts)>0){
	$sql .= ' WHERE '.implode(' AND ', $whereParts);
}

$sql .= ' ORDER BY rp.tgl_registrasi DESC';

$query = mysqli_query($mysqli, $sql) or die(mysqli_error($mysqli));
$data = array();
while($row = mysqli_fetch_array($query)){
	$data[] = $row;
}

// decide output format
$month_name = $months[$filter_month] ?? 'Bulan ' . $filter_month;
// if PhpSpreadsheet available, produce real Excel
if (class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
    // --------- generate xlsx like rl_app -------

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Data_Pasien_Diare');
    $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

    // Judul
    $sheet->mergeCells('A1:O1')->setCellValue('A1', 'DATA PASIEN DIARE');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Subtitle filter
    $filterInfo = 'Periode: ' . $month_name . ' ' . $filter_year;
    if($filter_status !== 'all') {
        $filterInfo .= ' | Status: ' . $filter_status;
    }
    $sheet->mergeCells('A2:P2')->setCellValue('A2', $filterInfo);
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10);

    $headers = ['No.', 'No RM', 'Nama Pasien', 'JK', 'NIK', 'Tgl Lahir', 'Alamat', 'No Rawat', 'Tgl Registrasi', 'Tgl Masuk', 'Tgl Keluar', 'Status Pulang', 'Kamar/Bangsal', 'Lama Rawat', 'Diagnosa Awal', 'Diagnosa Akhir'];
    $sheet->fromArray($headers, NULL, 'A4');
    // Set No RM column (B) Format to text
    $sheet->getStyle('B')->getNumberFormat()->setFormatCode('@');
    $sheet->getStyle('A4:P4')->applyFromArray([
        'font' => ['bold' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9D9D9']],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]
    ]);

    $rowNum = 5;
    $no = 1;
    foreach ($data as $row) {
        $sheet->setCellValue('A'.$rowNum, $no);
        $sheet->setCellValue('B'.$rowNum, $row['no_rkm_medis']);
        $sheet->setCellValue('C'.$rowNum, $row['nm_pasien']);
        $sheet->setCellValue('D'.$rowNum, $row['jk']);
        // memaksakan NIK sebagai string untuk menjaga angka nol di depan tetap tampil atau tidak berubah formatnya menjadi (6 30316E+16)
        $sheet->setCellValueExplicit('E'.$rowNum,(string)$row['nik'],DataType::TYPE_STRING);
        $sheet->setCellValue('F'.$rowNum, $row['tgl_lahir']);
        $sheet->setCellValue('G'.$rowNum, $row['alamat']);
        $sheet->setCellValue('H'.$rowNum, $row['no_rawat']);
        $sheet->setCellValue('I'.$rowNum, $row['tgl_registrasi']);
        $sheet->setCellValue('J'.$rowNum, $row['tgl_masuk']);
        $sheet->setCellValue('K'.$rowNum, $row['tgl_keluar']);
        $sheet->setCellValue('L'.$rowNum, $row['stts_pulang']);
        $sheet->setCellValue('M'.$rowNum, $row['kamar']);
        $sheet->setCellValue('N'.$rowNum, $row['lama']);
        $sheet->setCellValue('O'.$rowNum, $row['diagnosa_awal']);
        $sheet->setCellValue('P'.$rowNum, $row['diagnosa_akhir']);
        $rowNum++; $no++;
    }
    $lastRow = $sheet->getHighestRow();
    if($lastRow >= 5){
        $sheet->getStyle("A4:P$lastRow")->applyFromArray(['borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'000000']]],'alignment'=>['vertical'=>Alignment::VERTICAL_CENTER]]);
    }
    foreach (range('A','P') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);
    if($lastRow >= 5) $sheet->setAutoFilter("A4:P$lastRow");

    $status_suffix = ($filter_status !== 'all' && $filter_status !== '') ? '_' . str_replace(' ', '_', $filter_status) : '';
    $filename = "Data_Pasien_Diare_" . $month_name . "_" . $filter_year . $status_suffix . ".xlsx";
    // clear any stray buffer before headers
    if (ob_get_length()) ob_end_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} else {
    // fallback to CSV
    $filename = "Data_Pasien_Diare_" . $month_name . "_" . $filter_year . ".csv";
    if (ob_get_length()) ob_end_clean();
    header('Content-Encoding: UTF-8');
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'w');
    fputcsv($output, array("DATA PASIEN DIARE"), ";");
    $filterInfo = "Periode: " . $month_name . " " . $filter_year;
    if($filter_status !== 'all') { $filterInfo .= " | Status: " . $filter_status; }
    fputcsv($output, array($filterInfo), ";");
    fputcsv($output, array(""), ";");
    $headers = array("No.", "No RM", "Nama Pasien", "JK", "NIK", "Tgl Lahir", "Alamat", "No Rawat", "Tgl Registrasi", "Tgl Masuk", "Tgl Keluar", "Status Pulang", "Kamar/Bangsal", "Lama Rawat", "Diagnosa Awal", "Diagnosa Akhir");
    fputcsv($output, $headers, ";");
    $no = 1;
    foreach ($data as $row) {
        $rowData = array($no++, $row['no_rkm_medis'], $row['nm_pasien'], $row['jk'], $row['nik'], $row['tgl_lahir'], $row['alamat'], $row['no_rawat'], $row['tgl_registrasi'], $row['tgl_masuk'], $row['tgl_keluar'], $row['stts_pulang'], $row['kamar'], $row['lama'], $row['diagnosa_awal'], $row['diagnosa_akhir']);
        fputcsv($output, $rowData, ";");
    }
    fclose($output);
    exit;
}
