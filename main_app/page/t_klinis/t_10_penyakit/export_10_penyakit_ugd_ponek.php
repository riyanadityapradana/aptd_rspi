<?php
require_once dirname(dirname(__DIR__)) . '/export_excel_helper.php';
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/koneksi.php';
require_once __DIR__ . '/10_penyakit_ugd_ponek_helper.php';

list($tanggalAwal, $tanggalAkhir) = aptd_igd_ponek_period_from_post();
$categories = aptd_igd_ponek_categories();
$diseaseRankings = aptd_igd_ponek_top_diseases($mysqli, $tanggalAwal, $tanggalAkhir, 10);

list($spreadsheet, $sheet) = aptd_excel_create(
    '10 PENYAKIT TERBANYAK UGD DAN PONEK',
    'Periode: ' . $tanggalAwal . ' s.d. ' . $tanggalAkhir,
    '10 Penyakit'
);

$rows = [];
foreach ($categories as $categoryKey => $category) {
    $rank = 1;
    foreach ($diseaseRankings[$categoryKey] as $diseaseRow) {
        $rows[] = [
            $category['label'],
            $category['code'],
            $rank++,
            $diseaseRow['kd_penyakit'],
            $diseaseRow['nm_penyakit'],
            $diseaseRow['jumlah_kasus'],
        ];
    }
}

aptd_excel_render_table(
    $sheet,
    ['Kategori', 'Kode Poli', 'Peringkat', 'Kode ICD-10', 'Nama Penyakit', 'Jumlah Kasus'],
    $rows,
    4
);

aptd_excel_output($spreadsheet, '10_Penyakit_UGD_Ponek_' . date('Y-m-d') . '.xlsx');
