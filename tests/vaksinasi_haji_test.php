<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
date_default_timezone_set('Asia/Makassar');
$root = dirname(__DIR__);
require_once $root . '/config/akses.php';
require_once $root . '/main_app/page/t_non_klinis/vaksinasi/vaksinasi_haji_helper.php';
$checks = 0;
function vh_check($condition, $message)
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
}
$filters = aptd_vh_filters([]);
vh_check($filters['valid'] && $filters['tanggal_awal'] === date('Y-m-01') && $filters['tanggal_akhir'] === date('Y-m-d'), 'Default periode');
foreach (['', 'A09', 'A96'] as $payment) {
    vh_check(aptd_vh_filters(['tanggal_awal' => '2024-02-29', 'tanggal_akhir' => '2024-02-29', 'jenis_bayar' => $payment])['valid'], 'Tanggal sama/leap day dan penjamin valid');
}
foreach ([
    ['tanggal_awal' => '2025-02-29'],
    ['tanggal_awal' => '2026-02-30'],
    ['tanggal_akhir' => '2026-13-01'],
    ['tanggal_awal' => "2026-01\0-01"],
    ['tanggal_awal' => '2026-10-24', 'tanggal_akhir' => '2026-10-23'],
    ['tanggal_awal' => ['2026-01-01']],
    ['jenis_bayar' => ['A09']],
    ['jenis_bayar' => 'BPJ'],
    ['jenis_bayar' => "A09' OR 1=1 --"],
] as $invalid) {
    vh_check(!aptd_vh_filters($invalid)['valid'], 'Filter tidak valid harus ditolak');
}
foreach (array_keys(aptd_get_access_map()) as $role) {
    foreach (['data_vaksinasi_haji', 'export_vaksinasi_haji_pdf'] as $page) {
        vh_check(aptd_can_access($role, $page) === in_array($role, ['admin', 'keuangan', 'moneta'], true), 'Akses ' . $role . '/' . $page);
    }
}
vh_check(aptd_vh_h('<>&"') === '&lt;&gt;&amp;&quot;', 'Escape HTML');

require_once $root . '/main_app/page/t_non_klinis/vaksinasi/vaksinasi_haji_pdf.php';
$report = aptd_vh_empty_report();
for ($i = 0; $i < 120; $i++) {
    $payment = $i % 2 ? 'A96' : 'A09';
    $report['rows'][] = [
        'no_rawat' => '2026/10/23/' . sprintf('%06d', $i), 'tgl_registrasi' => '2026-10-23', 'jam_reg' => '10:00:00',
        'no_rkm_medis' => sprintf('%06d', $i % 100),
        'nm_pasien' => ($i === 0 ? json_decode('"NAMA UJI \u00c9 < & > "') : 'PASIEN UJI ') . $i . ' NAMA PANJANG UNTUK PEMERIKSAAN PEMBUNGKUSAN TEKS',
        'kd_poli' => 'U0053', 'nm_poli' => 'VAKSINASI INTERNASIONAL', 'kd_pj' => $payment,
        'jenis_bayar' => $payment === 'A09' ? 'UMUM' : 'Pancar Tour', 'status_bayar' => 'Sudah Bayar',
    ];
    $report['per_bayar'][$payment]++;
}
$report['total_kunjungan'] = 120;
$report['total_pasien'] = 100;
$filters = aptd_vh_filters(['tanggal_awal' => '2026-10-01', 'tanggal_akhir' => '2026-10-23']);
$hospital = ['nama_instansi' => 'RS PELITA INSANI', 'alamat_instansi' => 'Jl.Sekumpul No.66', 'kabupaten' => 'Martapura', 'propinsi' => 'Kalimantan Selatan', 'kontak' => 'Telp (0511)4722210,4722220', 'email' => 'rs.pelitainsani@gmail.com'];
$options = ['A09' => 'UMUM', 'A96' => 'Pancar Tour'];
$pdf = aptd_vh_build_pdf($report, $filters, $options, $hospital);
vh_check(strpos($pdf, '%PDF-') === 0 && substr(rtrim($pdf), -5) === '%%EOF', 'PDF multi-halaman valid');
preg_match_all('~/Type\s*/Page\b~', $pdf, $pages);
vh_check(count($pages[0]) > 1, 'PDF panjang memiliki beberapa halaman');
vh_check(strpos($pdf, '/Subtype /Image') !== false, 'Logo disertakan di PDF');
$artifact = sys_get_temp_dir() . '/aptd-vaksinasi-haji-test.pdf';
file_put_contents($artifact, $pdf);
$emptyPdf = aptd_vh_build_pdf(aptd_vh_empty_report(), $filters, $options, $hospital);
vh_check(strpos($emptyPdf, '%PDF-') === 0, 'PDF data kosong valid');
file_put_contents(sys_get_temp_dir() . '/aptd-vaksinasi-haji-empty-test.pdf', $emptyPdf);

if (in_array('--live', $argv, true)) {
    require $root . '/config/koneksi.php';
    $options = aptd_vh_payment_options($mysqli);
    vh_check(count($options) === 2 && isset($options['A09'], $options['A96']), 'Opsi penjamin SIMRS');
    vh_check(aptd_vh_hospital($mysqli)['nama_instansi'] !== '', 'Identitas kop SIMRS');
    $periods = [['2025-01-01', '2025-12-31'], ['2026-01-01', '2026-10-23'], ['2026-10-23', '2026-10-23'], ['2000-01-01', '2000-01-01']];
    foreach ($periods as $period) {
        foreach (['', 'A09', 'A96'] as $payment) {
            $filter = aptd_vh_filters(['tanggal_awal' => $period[0], 'tanggal_akhir' => $period[1], 'jenis_bayar' => $payment]);
            $actual = aptd_vh_fetch_report($mysqli, $filter);
            // Independently compare the base registration table with the joined report.
            $sql = "SELECT kd_pj, COUNT(*) AS total, COUNT(DISTINCT no_rkm_medis) AS patients FROM reg_periksa WHERE kd_poli='U0053' AND status_bayar='Sudah Bayar' AND kd_pj IN ('A09','A96') AND tgl_registrasi BETWEEN '" . $period[0] . "' AND '" . $period[1] . "'";
            if ($payment !== '') {
                $sql .= " AND kd_pj='" . $payment . "'";
            }
            $expected = $mysqli->query($sql . ' GROUP BY kd_pj')->fetch_all(MYSQLI_ASSOC);
            $total = 0;
            foreach ($expected as $group) {
                $total += (int) $group['total'];
                vh_check($actual['per_bayar'][strtoupper(trim($group['kd_pj']))] === (int) $group['total'], 'Subtotal sama dengan registrasi sumber');
            }
            vh_check($actual['total_kunjungan'] === $total, 'Total sesuai query sumber independen');
            vh_check(array_sum($actual['per_bayar']) === $total, 'Subtotal menjumlah ke total laporan');
            $records = [];
            $previous = null;
            foreach ($actual['rows'] as $row) {
                vh_check($row['tgl_registrasi'] >= $period[0] && $row['tgl_registrasi'] <= $period[1], 'Batas tanggal inklusif');
                vh_check(strtoupper(trim($row['kd_poli'])) === 'U0053' && $row['status_bayar'] === 'Sudah Bayar' && in_array(strtoupper(trim($row['kd_pj'])), ['A09', 'A96'], true), 'Kontrak poli/status/penjamin');
                vh_check($payment === '' || strtoupper(trim($row['kd_pj'])) === $payment, 'Filter jenis bayar');
                $sortKey = $row['tgl_registrasi'] . ' ' . $row['jam_reg'] . ' ' . $row['no_rawat'];
                vh_check($previous === null || strcmp($previous, $sortKey) >= 0, 'Urutan tanggal/jam menurun');
                $previous = $sortKey;
                $records[$row['no_rkm_medis']] = true;
            }
            vh_check(count($records) === $actual['total_pasien'], 'Total pasien unik berdasarkan RM');
        }
    }
    echo "Live SIMRS: 12 kombinasi periode/penjamin lulus (read-only).\n";
}
echo 'PASS ' . $checks . " pemeriksaan. PDF fixture: " . $artifact . PHP_EOL;
