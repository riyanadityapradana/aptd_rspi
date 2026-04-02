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
$penjamin = ['A09' => 'UMUM', 'BPJ' => 'BPJS', 'A92' => 'ASURANSI', 'A96' => 'Pancar Tour'];
$filter_start_date = isset($_POST['tgl_awal']) ? trim((string) $_POST['tgl_awal']) : date('Y-m-01');
$filter_end_date = isset($_POST['tgl_akhir']) ? trim((string) $_POST['tgl_akhir']) : date('Y-m-d');

function generateWeeksBetweenDatesExport($start_date, $end_date)
{
    $start_ts = strtotime($start_date);
    $end_ts = strtotime($end_date);
    if ($start_ts === false) $start_ts = strtotime(date('Y-m-01'));
    if ($end_ts === false) $end_ts = strtotime(date('Y-m-d'));
    if ($start_ts > $end_ts) { $tmp = $start_ts; $start_ts = $end_ts; $end_ts = $tmp; }
    $weeks = [];
    $first = $start_ts;
    $day_of_week = date('w', $first);
    if ($day_of_week != 2) {
        $days_until_tuesday = ($day_of_week <= 2) ? (2 - $day_of_week) : (9 - $day_of_week);
        $first = strtotime('+' . $days_until_tuesday . ' days', $first);
    }
    if ($first > $end_ts) {
        return [[
            'start' => date('Y-m-d', $start_ts),
            'end' => date('Y-m-d', $end_ts),
            'label' => date('d M Y', $start_ts) . ' - ' . date('d M Y', $end_ts),
        ]];
    }
    $current = $first;
    while ($current <= $end_ts) {
        $week_start = $current;
        $week_end = strtotime('+6 days', $week_start);
        if ($week_end > $end_ts) $week_end = $end_ts;
        $weeks[] = [
            'start' => date('Y-m-d', $week_start),
            'end' => date('Y-m-d', $week_end),
            'label' => date('d', $week_start) . ' - ' . date('d M Y', $week_end),
        ];
        $current = strtotime('+7 days', $week_start);
    }
    return $weeks;
}

$weeks = generateWeeksBetweenDatesExport($filter_start_date, $filter_end_date);
$headers = ['Poliklinik'];
foreach ($weeks as $week) {
    foreach ($penjamin as $label) {
        $headers[] = $week['label'] . ' ' . $label;
    }
    $headers[] = $week['label'] . ' JLH';
}

$dataRows = [];
$weeklyLabels = [];
$weeklyTotals = [];
$weeklyByPayment = [];
foreach ($penjamin as $label) {
    $weeklyByPayment[$label] = [];
}

foreach ($mapping_poli as $poli_name => $poli_codes) {
    $row = [$poli_name];
    foreach ($weeks as $week_idx => $week) {
        $poli_codes_str = "'" . implode("','", array_map(function ($v) use ($conn) {
            return $conn->real_escape_string($v);
        }, $poli_codes)) . "'";
        $weekTotal = 0;
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
            $value = 0;
            if ($result) {
                $item = $result->fetch_assoc();
                $value = isset($item['jml']) ? (int) $item['jml'] : 0;
            }
            $row[] = $value;
            $weekTotal += $value;
        }
        $row[] = $weekTotal;
    }
    $dataRows[] = $row;
}

foreach ($weeks as $week) {
    $weeklyLabels[] = $week['label'];
    $sql = "SELECT 
        SUM(CASE WHEN rp.kd_pj='A09' THEN 1 ELSE 0 END) AS umum,
        SUM(CASE WHEN rp.kd_pj='BPJ' THEN 1 ELSE 0 END) AS bpjs,
        SUM(CASE WHEN rp.kd_pj='A92' THEN 1 ELSE 0 END) AS asuransi,
        SUM(CASE WHEN rp.kd_pj='A96' THEN 1 ELSE 0 END) AS pancar
        FROM reg_periksa rp
        WHERE rp.stts = 'Sudah'
          AND rp.status_bayar = 'Sudah Bayar'
          AND rp.no_rkm_medis NOT IN (SELECT no_rkm_medis FROM pasien WHERE LOWER(nm_pasien) LIKE '%test%')
          AND DAYOFWEEK(rp.tgl_registrasi) <> 1
          AND rp.tgl_registrasi BETWEEN '" . $week['start'] . "' AND '" . $week['end'] . "'";
    $result = $conn->query($sql);
    $summary = $result ? $result->fetch_assoc() : ['umum' => 0, 'bpjs' => 0, 'asuransi' => 0, 'pancar' => 0];
    $weeklyByPayment['UMUM'][] = (int) $summary['umum'];
    $weeklyByPayment['BPJS'][] = (int) $summary['bpjs'];
    $weeklyByPayment['ASURANSI'][] = (int) $summary['asuransi'];
    $weeklyByPayment['Pancar Tour'][] = (int) $summary['pancar'];
    $weeklyTotals[] = (int) $summary['umum'] + (int) $summary['bpjs'] + (int) $summary['asuransi'] + (int) $summary['pancar'];
}

list($spreadsheet, $sheet) = aptd_excel_create(
    'REKAP KUNJUNGAN PASIEN HARIAN RAWAT JALAN',
    'Periode: ' . $filter_start_date . ' s.d. ' . $filter_end_date,
    'Data'
);
aptd_excel_render_table($sheet, $headers, $dataRows, 4);

$summaryRows = [];
for ($i = 0; $i < count($weeklyLabels); $i++) {
    $summaryRows[] = [$weeklyLabels[$i], $weeklyByPayment['UMUM'][$i], $weeklyByPayment['BPJS'][$i], $weeklyByPayment['ASURANSI'][$i], $weeklyByPayment['Pancar Tour'][$i], $weeklyTotals[$i]];
}
aptd_excel_add_sheet($spreadsheet, 'Ringkasan', 'Ringkasan Mingguan', ['Minggu', 'UMUM', 'BPJS', 'ASURANSI', 'Pancar Tour', 'TOTAL'], $summaryRows, 'Sumber grafik kunjungan ralan');
aptd_excel_add_bar_chart_sheet($spreadsheet, 'Grafik Total', 'Total Pasien Per Minggu', 'Minggu', $weeklyLabels, ['Total Pasien' => $weeklyTotals], false);
aptd_excel_add_bar_chart_sheet($spreadsheet, 'Grafik Pembayaran', 'Jenis Pembayaran Per Minggu', 'Minggu', $weeklyLabels, $weeklyByPayment, false);

aptd_excel_output($spreadsheet, 'rekap_kunjungan_ralan_' . date('Ymd_His') . '.xlsx');
