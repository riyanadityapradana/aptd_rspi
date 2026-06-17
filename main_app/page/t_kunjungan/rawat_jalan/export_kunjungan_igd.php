<?php
require_once dirname(dirname(__DIR__)) . '/export_excel_helper.php';
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/koneksi.php';

$jenisIgdOptions = [
    'semua' => [
        'label' => 'Semua',
        'items' => [
            ['code' => 'IGDK', 'status' => 'Ranap'],
            ['code' => 'U0009', 'status' => 'Ralan'],
        ],
    ],
    'igd_ranap' => [
        'label' => 'IGD Ranap (IGDK / UGD)',
        'items' => [
            ['code' => 'IGDK', 'status' => 'Ranap'],
        ],
    ],
    'igd_ralan' => [
        'label' => 'IGD Ralan (U0009 / Poli Umum)',
        'items' => [
            ['code' => 'U0009', 'status' => 'Ralan'],
        ],
    ],
];

$penjamin = [
    'A09' => 'UMUM',
    'BPJ' => 'BPJS',
    'A92' => 'ASURANSI',
];

$jenisIgd = isset($_POST['jenis_igd']) ? trim((string) $_POST['jenis_igd']) : 'semua';
if (!isset($jenisIgdOptions[$jenisIgd])) {
    $jenisIgd = 'semua';
}

$tanggalAwal = isset($_POST['tanggal_awal']) ? trim((string) $_POST['tanggal_awal']) : date('Y-m-01');
$tanggalAkhir = isset($_POST['tanggal_akhir']) ? trim((string) $_POST['tanggal_akhir']) : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalAwal)) {
    $tanggalAwal = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalAkhir)) {
    $tanggalAkhir = date('Y-m-d');
}
if ($tanggalAwal > $tanggalAkhir) {
    $tmp = $tanggalAwal;
    $tanggalAwal = $tanggalAkhir;
    $tanggalAkhir = $tmp;
}

$selectedItems = $jenisIgdOptions[$jenisIgd]['items'];
$selectedCodes = array_map(function ($item) {
    return $item['code'];
}, $selectedItems);
$selectedStatuses = array_map(function ($item) {
    return $item['status'];
}, $selectedItems);
$igdWhereParts = array_map(function ($item) use ($mysqli) {
    return "(rp.kd_poli = '" . mysqli_real_escape_string($mysqli, $item['code']) . "' AND rp.status_lanjut = '" . mysqli_real_escape_string($mysqli, $item['status']) . "')";
}, $selectedItems);
$bpjsSepExists = "EXISTS (
            SELECT 1
            FROM bridging_sep bs
            WHERE bs.no_rawat = rp.no_rawat
                AND bs.no_sep IS NOT NULL
                AND bs.no_sep <> ''
        )";

$data = array_fill_keys(array_keys($penjamin), 0);
$total = 0;
$sql = "
    SELECT
        SUM(CASE WHEN rp.kd_pj = 'A09' THEN 1 ELSE 0 END) AS umum,
        SUM(CASE WHEN rp.kd_pj = 'BPJ' AND " . $bpjsSepExists . " THEN 1 ELSE 0 END) AS bpjs,
        SUM(CASE WHEN rp.kd_pj = 'A92' THEN 1 ELSE 0 END) AS asuransi
    FROM reg_periksa rp
    WHERE (" . implode(' OR ', $igdWhereParts) . ")
        AND rp.kd_pj IN ('A09', 'BPJ', 'A92')
        AND rp.stts = 'Sudah'
        AND rp.status_bayar = 'Sudah Bayar'
        AND rp.tgl_registrasi BETWEEN '" . mysqli_real_escape_string($mysqli, $tanggalAwal) . "' AND '" . mysqli_real_escape_string($mysqli, $tanggalAkhir) . "'
        AND rp.no_rkm_medis NOT IN (
            SELECT no_rkm_medis FROM pasien WHERE LOWER(nm_pasien) LIKE '%test%'
        )
";
$result = mysqli_query($mysqli, $sql);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $data['A09'] = isset($row['umum']) ? (int) $row['umum'] : 0;
    $data['BPJ'] = isset($row['bpjs']) ? (int) $row['bpjs'] : 0;
    $data['A92'] = isset($row['asuransi']) ? (int) $row['asuransi'] : 0;
    $total = array_sum($data);
}

list($spreadsheet, $sheet) = aptd_excel_create(
    'DATA KUNJUNGAN PASIEN IGD',
    'Filter: ' . $jenisIgdOptions[$jenisIgd]['label'] . ' | Periode: ' . $tanggalAwal . ' s.d. ' . $tanggalAkhir,
    'Data IGD'
);

$headers = ['No', 'Filter IGD', 'Kode Poli', 'Status Lanjut'];
foreach ($penjamin as $label) {
    $headers[] = $label;
}
$headers[] = 'Jumlah Total';

$row = [1, $jenisIgdOptions[$jenisIgd]['label'], implode(', ', $selectedCodes), implode(', ', array_unique($selectedStatuses))];
foreach (array_keys($penjamin) as $kd) {
    $row[] = $data[$kd];
}
$row[] = $total;

aptd_excel_render_table($sheet, $headers, [$row], 4);
$sheet->setCellValue('A6', 'Total');
$sheet->mergeCells('A6:D6');
$col = 5;
foreach (array_keys($penjamin) as $kd) {
    $sheet->setCellValue(aptd_excel_cell($col, 6), $data[$kd]);
    $col++;
}
$sheet->setCellValue(aptd_excel_cell($col, 6), $total);
$sheet->getStyle('A6:' . aptd_excel_cell($col, 6))->getFont()->setBold(true);
$sheet->getStyle('A6:' . aptd_excel_cell($col, 6))->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$sheet->getStyle('A6:' . aptd_excel_cell($col, 6))->getFill()->getStartColor()->setARGB('FFEFF3F8');

$labels = [];
$values = [];
foreach ($penjamin as $kd => $label) {
    $labels[] = $label;
    $values[] = $data[$kd];
}
aptd_excel_add_pie_chart_sheet($spreadsheet, 'Grafik Pembayaran', 'Komposisi Jenis Pembayaran IGD', 'Kategori', 'Jumlah', $labels, $values);

aptd_excel_output($spreadsheet, 'Data_Kunjungan_IGD_' . date('Y-m-d') . '.xlsx');
