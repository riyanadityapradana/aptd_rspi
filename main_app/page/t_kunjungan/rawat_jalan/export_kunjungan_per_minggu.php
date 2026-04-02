<?php
require_once dirname(dirname(__DIR__)) . '/export_excel_helper.php';
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/koneksi.php';
$conn = $mysqli;

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
    'VAKSIN' => ['U0053'],
    'MCU' => ['U0071'],
    'HEMODIALISA' => ['U0023'],
    'IGD' => ['IGDK', 'U0009', 'U0013'],
];

$penjamin = ['A09' => 'UMUM', 'BPJ' => 'BPJS', 'A92' => 'ASURANSI'];
$filter_month = isset($_POST['bulan']) ? (int) $_POST['bulan'] : (int) date('n');
$filter_year = isset($_POST['tahun']) ? (int) $_POST['tahun'] : (int) date('Y');
$monthNames = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];

function generateWeeksFromTuesdayExport($year, $month)
{
    $first_day = strtotime($year . '-' . $month . '-01');
    $last_day = strtotime(date('Y-m-t', $first_day));
    $weeks = [];
    $first_tuesday = $first_day;
    $day_of_week = date('w', $first_tuesday);
    if ($day_of_week != 2) {
        $days_until_tuesday = ($day_of_week <= 2) ? (2 - $day_of_week) : (9 - $day_of_week);
        $first_tuesday = strtotime('+' . $days_until_tuesday . ' days', $first_tuesday);
    }
    $current = $first_tuesday;
    while ($current <= $last_day) {
        $week_start = $current;
        $week_end = strtotime('+6 days', $week_start);
        if ($week_end > $last_day) {
            $week_end = $last_day;
        }
        $weeks[] = [
            'start' => date('Y-m-d', $week_start),
            'end' => date('Y-m-d', $week_end),
            'label' => date('d', $week_start) . ' - ' . date('d M Y', $week_end),
        ];
        $current = strtotime('+7 days', $week_start);
        if ($current > $last_day) {
            break;
        }
    }
    return $weeks;
}

$weeks = generateWeeksFromTuesdayExport($filter_year, $filter_month);
$dataRows = [];
$weeklyLabels = [];
$weeklyTotals = [];
$weeklyUmum = [];
$weeklyBpjs = [];
$weeklyAsuransi = [];

foreach ($mapping_poli as $poli_name => $poli_codes) {
    $row = ['Poliklinik' => $poli_name];
    foreach ($weeks as $week_idx => $week) {
        $poli_codes_str = "'" . implode("','", array_map(function ($v) use ($conn) {
            return $conn->real_escape_string($v);
        }, $poli_codes)) . "'";
        $umum = 0;
        $bpjs = 0;
        $asuransi = 0;
        foreach ($penjamin as $kd_pj => $label) {
            $sql = "SELECT COUNT(*) AS jml FROM reg_periksa rp
                WHERE rp.kd_poli IN ($poli_codes_str)
                  AND rp.kd_pj = '$kd_pj'
                  AND rp.stts = 'Sudah'
                  AND rp.status_bayar = 'Sudah Bayar'
                  AND rp.no_rkm_medis NOT IN (SELECT no_rkm_medis FROM pasien WHERE LOWER(nm_pasien) LIKE '%test%')
                  AND DAYOFWEEK(rp.tgl_registrasi) <> 1
                  AND rp.tgl_registrasi BETWEEN '" . $week['start'] . "' AND '" . $week['end'] . "'";
            $result = $conn->query($sql);
            $count = 0;
            if ($result) {
                $item = $result->fetch_assoc();
                $count = isset($item['jml']) ? (int) $item['jml'] : 0;
            }
            if ($kd_pj === 'A09') { $umum = $count; }
            if ($kd_pj === 'BPJ') { $bpjs = $count; }
            if ($kd_pj === 'A92') { $asuransi = $count; }
        }
        $row[$week['label'] . ' UMUM'] = $umum;
        $row[$week['label'] . ' BPJS'] = $bpjs;
        $row[$week['label'] . ' ASURANSI'] = $asuransi;
        $row[$week['label'] . ' JLH'] = $umum + $bpjs + $asuransi;
    }
    $dataRows[] = array_values($row);
}

$headers = ['Poliklinik'];
foreach ($weeks as $week) {
    $headers[] = $week['label'] . ' UMUM';
    $headers[] = $week['label'] . ' BPJS';
    $headers[] = $week['label'] . ' ASURANSI';
    $headers[] = $week['label'] . ' JLH';

    $sql = "SELECT 
        SUM(CASE WHEN rp.kd_pj='A09' THEN 1 ELSE 0 END) AS umum,
        SUM(CASE WHEN rp.kd_pj='BPJ' THEN 1 ELSE 0 END) AS bpjs,
        SUM(CASE WHEN rp.kd_pj='A92' THEN 1 ELSE 0 END) AS asuransi
        FROM reg_periksa rp
        WHERE rp.stts = 'Sudah'
          AND rp.status_bayar = 'Sudah Bayar'
          AND rp.no_rkm_medis NOT IN (SELECT no_rkm_medis FROM pasien WHERE LOWER(nm_pasien) LIKE '%test%')
          AND DAYOFWEEK(rp.tgl_registrasi) <> 1
          AND rp.tgl_registrasi BETWEEN '" . $week['start'] . "' AND '" . $week['end'] . "'";
    $result = $conn->query($sql);
    $summary = $result ? $result->fetch_assoc() : ['umum' => 0, 'bpjs' => 0, 'asuransi' => 0];
    $weeklyLabels[] = $week['label'];
    $weeklyUmum[] = (int) $summary['umum'];
    $weeklyBpjs[] = (int) $summary['bpjs'];
    $weeklyAsuransi[] = (int) $summary['asuransi'];
    $weeklyTotals[] = (int) $summary['umum'] + (int) $summary['bpjs'] + (int) $summary['asuransi'];
}

list($spreadsheet, $sheet) = aptd_excel_create(
    'REKAP KUNJUNGAN PASIEN HARIAN RAWAT JALAN',
    'Periode: ' . $monthNames[$filter_month] . ' ' . $filter_year,
    'Data'
);
aptd_excel_render_table($sheet, $headers, $dataRows, 4);

$summaryRows = [];
for ($i = 0; $i < count($weeklyLabels); $i++) {
    $summaryRows[] = [$weeklyLabels[$i], $weeklyUmum[$i], $weeklyBpjs[$i], $weeklyAsuransi[$i], $weeklyTotals[$i]];
}
aptd_excel_add_sheet($spreadsheet, 'Ringkasan Minggu', 'Ringkasan Per Minggu', ['Minggu', 'UMUM', 'BPJS', 'ASURANSI', 'TOTAL'], $summaryRows, 'Sumber grafik mingguan');
aptd_excel_add_bar_chart_sheet($spreadsheet, 'Grafik Minggu', 'Total Pasien Per Minggu', 'Minggu', $weeklyLabels, ['Total Pasien' => $weeklyTotals], false);
aptd_excel_add_bar_chart_sheet($spreadsheet, 'Grafik Bayar', 'Komposisi Pembayaran Per Minggu', 'Minggu', $weeklyLabels, ['UMUM' => $weeklyUmum, 'BPJS' => $weeklyBpjs, 'ASURANSI' => $weeklyAsuransi], false);

aptd_excel_output($spreadsheet, 'rekap_kunjungan_per_minggu_' . date('Ymd_His') . '.xlsx');
