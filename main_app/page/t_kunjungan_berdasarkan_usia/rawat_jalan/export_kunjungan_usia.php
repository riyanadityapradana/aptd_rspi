<?php
require_once dirname(dirname(__DIR__)) . '/export_excel_helper.php';
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/koneksi.php';

$conn = $mysqli;

$filter_tgl_awal = isset($_REQUEST['tgl_awal']) ? trim((string) $_REQUEST['tgl_awal']) : date('Y-m-01');
$filter_tgl_akhir = isset($_REQUEST['tgl_akhir']) ? trim((string) $_REQUEST['tgl_akhir']) : date('Y-m-d');
$filter_stts = isset($_REQUEST['stts']) ? trim((string) $_REQUEST['stts']) : 'semua';
$filter_usia = isset($_REQUEST['usia']) ? trim((string) $_REQUEST['usia']) : 'semua';
$filter_jenis_bayar = isset($_REQUEST['jenis_bayar']) ? trim((string) $_REQUEST['jenis_bayar']) : 'semua';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_tgl_awal)) {
    $filter_tgl_awal = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_tgl_akhir)) {
    $filter_tgl_akhir = date('Y-m-d');
}
if ($filter_tgl_awal > $filter_tgl_akhir) {
    $tmp = $filter_tgl_awal;
    $filter_tgl_awal = $filter_tgl_akhir;
    $filter_tgl_akhir = $tmp;
}

$allowedStatus = ['semua', 'Sudah', 'Belum', 'Batal'];
if (!in_array($filter_stts, $allowedStatus, true)) {
    $filter_stts = 'semua';
}

$allowedUsia = ['semua', 'anak', 'remaja', 'dewasa', 'lansia'];
if (!in_array($filter_usia, $allowedUsia, true)) {
    $filter_usia = 'semua';
}

$penjab_list = [
    'A09' => 'UMUM',
    'BPJ' => 'BPJS',
    'A92' => 'ASURANSI',
    'A96' => 'Pancar Tour',
];
if ($filter_jenis_bayar !== 'semua' && !array_key_exists($filter_jenis_bayar, $penjab_list)) {
    $filter_jenis_bayar = 'semua';
}

$where_parts = [
    "r.status_lanjut = 'Ralan'",
    "EXISTS (SELECT 1 FROM poliklinik pl WHERE pl.kd_poli = r.kd_poli AND pl.status = '1')",
    'r.tgl_registrasi BETWEEN ? AND ?',
];
$types = 'ss';
$params = [$filter_tgl_awal, $filter_tgl_akhir];

if ($filter_stts !== 'semua') {
    $where_parts[] = 'r.stts = ?';
    $types .= 's';
    $params[] = $filter_stts;
}

if ($filter_usia !== 'semua') {
    switch ($filter_usia) {
        case 'anak':
            $where_parts[] = 'DATEDIFF(r.tgl_registrasi, p.tgl_lahir) / 365.25 BETWEEN 0 AND 12';
            break;
        case 'remaja':
            $where_parts[] = 'DATEDIFF(r.tgl_registrasi, p.tgl_lahir) / 365.25 BETWEEN 13 AND 17';
            break;
        case 'dewasa':
            $where_parts[] = 'DATEDIFF(r.tgl_registrasi, p.tgl_lahir) / 365.25 BETWEEN 18 AND 59';
            break;
        case 'lansia':
            $where_parts[] = 'DATEDIFF(r.tgl_registrasi, p.tgl_lahir) / 365.25 >= 60';
            break;
    }
}

if ($filter_jenis_bayar !== 'semua') {
    $where_parts[] = 'r.kd_pj = ?';
    $types .= 's';
    $params[] = $filter_jenis_bayar;
}

$sql = "SELECT
            r.no_reg,
            r.no_rawat,
            r.tgl_registrasi,
            r.status_lanjut,
            r.stts,
            p.nm_pasien,
            p.tgl_lahir,
            j.png_jawab,
            TIMESTAMPDIFF(YEAR, p.tgl_lahir, r.tgl_registrasi) AS umur
        FROM reg_periksa r
        JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
        JOIN penjab j ON r.kd_pj = j.kd_pj
        WHERE " . implode(' AND ', $where_parts) . "
        ORDER BY r.tgl_registrasi DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
$no = 1;
while ($row = $result->fetch_assoc()) {
    $umur = (int) $row['umur'];
    $kategori = 'Tidak Diketahui';
    if ($umur >= 0 && $umur <= 12) {
        $kategori = 'Anak-Anak';
    } elseif ($umur >= 13 && $umur <= 17) {
        $kategori = 'Remaja';
    } elseif ($umur >= 18 && $umur <= 59) {
        $kategori = 'Dewasa';
    } elseif ($umur >= 60) {
        $kategori = 'Lanjut Usia';
    }

    $rows[] = [
        $no++,
        $row['no_reg'],
        $row['no_rawat'],
        $row['tgl_registrasi'],
        $row['nm_pasien'],
        $row['tgl_lahir'],
        $umur . ' thn',
        $kategori,
        $row['stts'],
        $row['png_jawab'],
        $row['status_lanjut'],
    ];
}
$stmt->close();

list($spreadsheet, $sheet) = aptd_excel_create(
    'DATA KUNJUNGAN PASIEN RAWAT JALAN BERDASARKAN USIA',
    'Periode: ' . $filter_tgl_awal . ' s.d. ' . $filter_tgl_akhir,
    'Data'
);

$headers = ['No.', 'No. Reg', 'No. Rawat', 'Tgl Registrasi', 'Nama Pasien', 'TTL', 'Umur', 'Kategori Usia', 'Status Periksa', 'Jenis Bayar', 'Status Lanjut'];
if ($spreadsheet && $sheet) {
    aptd_excel_render_table($sheet, $headers, $rows, 4);
    aptd_excel_output($spreadsheet, 'Kunjungan_Berdasarkan_Usia_' . date('Y-m-d') . '.xlsx');
}

aptd_excel_output_csv('Kunjungan_Berdasarkan_Usia_' . date('Y-m-d') . '.csv', $headers, $rows);
