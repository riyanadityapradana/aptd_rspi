<?php

function aptd_kec_mingguan_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function aptd_kec_mingguan_month_labels()
{
    return [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
}

function aptd_kec_mingguan_payment_labels()
{
    return ['BPJS', 'Umum', 'Asuransi'];
}

function aptd_kec_mingguan_mapping()
{
    return [
        ['kabupaten' => 'Kabupaten Tapin', 'kecamatan' => 'Bakarangan', 'keys' => ['bakarangan']],
        ['kabupaten' => 'Kabupaten Tapin', 'kecamatan' => 'Binuang', 'keys' => ['binuang']],
        ['kabupaten' => 'Kabupaten Tapin', 'kecamatan' => 'Bungur', 'keys' => ['bungur']],
        ['kabupaten' => 'Kabupaten Tapin', 'kecamatan' => 'Candi Laras Selatan', 'keys' => ['candi laras selatan']],
        ['kabupaten' => 'Kabupaten Tapin', 'kecamatan' => 'Candi Laras Utara', 'keys' => ['candi laras utara']],
        ['kabupaten' => 'Kabupaten Tapin', 'kecamatan' => 'Hatungun', 'keys' => ['hatungun']],
        ['kabupaten' => 'Kabupaten Tapin', 'kecamatan' => 'Lokpaikat', 'keys' => ['lokpaikat']],
        ['kabupaten' => 'Kabupaten Tapin', 'kecamatan' => 'Piani', 'keys' => ['piani']],
        ['kabupaten' => 'Kabupaten Tapin', 'kecamatan' => 'Salam Babaris', 'keys' => ['salam babaris']],
        ['kabupaten' => 'Kabupaten Tapin', 'kecamatan' => 'Tapin Selatan', 'keys' => ['tapin selatan']],
        ['kabupaten' => 'Kabupaten Tapin', 'kecamatan' => 'Tapin Tengah', 'keys' => ['tapin tengah']],
        ['kabupaten' => 'Kabupaten Tapin', 'kecamatan' => 'Tapin Utara', 'keys' => ['tapin utara']],

        ['kabupaten' => 'Kabupaten Banjar', 'kecamatan' => 'Aluh-Aluh', 'keys' => ['aluh aluh', 'aluhaluh']],
        ['kabupaten' => 'Kabupaten Banjar', 'kecamatan' => 'Aranio', 'keys' => ['aranio']],
        ['kabupaten' => 'Kabupaten Banjar', 'kecamatan' => 'Astambul', 'keys' => ['astambul']],
        ['kabupaten' => 'Kabupaten Banjar', 'kecamatan' => 'Beruntung Baru', 'keys' => ['beruntung baru']],
        ['kabupaten' => 'Kabupaten Banjar', 'kecamatan' => 'Cintapuri Darussalam', 'keys' => ['cintapuri darussalam', 'cinta puri darussalam']],
        ['kabupaten' => 'Kabupaten Banjar', 'kecamatan' => 'Gambut', 'keys' => ['gambut']],
        ['kabupaten' => 'Kabupaten Banjar', 'kecamatan' => 'Karang Intan', 'keys' => ['karang intan']],
        ['kabupaten' => 'Kabupaten Banjar', 'kecamatan' => 'Kertak Hanyar', 'keys' => ['kertak hanyar']],
        ['kabupaten' => 'Kabupaten Banjar', 'kecamatan' => 'Martapura Barat', 'keys' => ['martapura barat']],
        ['kabupaten' => 'Kabupaten Banjar', 'kecamatan' => 'Martapura Timur', 'keys' => ['martapura timur']],
        ['kabupaten' => 'Kabupaten Banjar', 'kecamatan' => 'Martapura', 'keys' => ['martapura kota', 'martapura']],
        ['kabupaten' => 'Kabupaten Banjar', 'kecamatan' => 'Mataraman', 'keys' => ['mataraman']],
        ['kabupaten' => 'Kabupaten Banjar', 'kecamatan' => 'Peramasan', 'keys' => ['paramasan', 'peramasan']],
        ['kabupaten' => 'Kabupaten Banjar', 'kecamatan' => 'Pengaron', 'keys' => ['pengaron']],
        ['kabupaten' => 'Kabupaten Banjar', 'kecamatan' => 'Sambung Makmur', 'keys' => ['sambung makmur']],
        ['kabupaten' => 'Kabupaten Banjar', 'kecamatan' => 'Simpang Empat', 'keys' => ['simpang empat']],
        ['kabupaten' => 'Kabupaten Banjar', 'kecamatan' => 'Sungai Pinang', 'keys' => ['sungai pinang']],
        ['kabupaten' => 'Kabupaten Banjar', 'kecamatan' => 'Sungai Tabuk', 'keys' => ['sungai tabuk']],
        ['kabupaten' => 'Kabupaten Banjar', 'kecamatan' => 'Tatah Makmur', 'keys' => ['tatah makmur']],
        ['kabupaten' => 'Kabupaten Banjar', 'kecamatan' => 'Telaga Bauntung', 'keys' => ['telaga bauntung']],

        ['kabupaten' => 'Kota Banjarmasin', 'kecamatan' => 'Banjarmasin Selatan', 'keys' => ['banjarmasin selatan']],
        ['kabupaten' => 'Kota Banjarmasin', 'kecamatan' => 'Banjarmasin Timur', 'keys' => ['banjarmasin timur']],
        ['kabupaten' => 'Kota Banjarmasin', 'kecamatan' => 'Banjarmasin Barat', 'keys' => ['banjarmasin barat']],
        ['kabupaten' => 'Kota Banjarmasin', 'kecamatan' => 'Banjarmasin Utara', 'keys' => ['banjarmasin utara']],
        ['kabupaten' => 'Kota Banjarmasin', 'kecamatan' => 'Banjarmasin Tengah', 'keys' => ['banjarmasin tengah']],

        ['kabupaten' => 'Kota Banjarbaru', 'kecamatan' => 'Banjarbaru Selatan', 'keys' => ['banjarbaru selatan', 'banjar baru selatan']],
        ['kabupaten' => 'Kota Banjarbaru', 'kecamatan' => 'Banjarbaru Utara', 'keys' => ['banjarbaru utara', 'banjar baru utara']],
        ['kabupaten' => 'Kota Banjarbaru', 'kecamatan' => 'Cempaka', 'keys' => ['cempaka']],
        ['kabupaten' => 'Kota Banjarbaru', 'kecamatan' => 'Landasan Ulin', 'keys' => ['landasan ulin']],
        ['kabupaten' => 'Kota Banjarbaru', 'kecamatan' => 'Liang Anggang', 'keys' => ['liang anggang']],
    ];
}

function aptd_kec_mingguan_period_from_request()
{
    $selected = isset($_POST['bulan']) && $_POST['bulan'] !== '' ? trim($_POST['bulan']) : date('Y-m');
    if (!preg_match('/^\d{4}-\d{2}$/', $selected)) {
        $selected = date('Y-m');
    }

    $year = (int) substr($selected, 0, 4);
    $month = (int) substr($selected, 5, 2);
    if ($year < 2020 || $year > ((int) date('Y') + 1) || $month < 1 || $month > 12) {
        $selected = date('Y-m');
        $year = (int) date('Y');
        $month = (int) date('n');
    }

    $startDate = sprintf('%04d-%02d-01', $year, $month);
    $endDate = date('Y-m-t', strtotime($startDate));

    return [$selected, $year, $month, $startDate, $endDate];
}

function aptd_kec_mingguan_weeks($year, $month)
{
    $monthStart = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
    $monthEnd = new DateTimeImmutable($monthStart->format('Y-m-t'));
    $weeks = [];
    $currentStart = $monthStart;

    while ($currentStart <= $monthEnd) {
        $daysUntilMonday = (8 - (int) $currentStart->format('N')) % 7;
        $currentEnd = $currentStart->modify('+' . $daysUntilMonday . ' days');
        if ($currentEnd > $monthEnd) {
            $currentEnd = $monthEnd;
        }

        $weeks[] = [
            'start' => $currentStart->format('Y-m-d'),
            'end' => $currentEnd->format('Y-m-d'),
            'label' => aptd_kec_mingguan_week_label($currentStart, $currentEnd),
        ];

        $currentStart = $currentEnd->modify('+1 day');
    }

    return $weeks;
}

function aptd_kec_mingguan_week_label(DateTimeImmutable $start, DateTimeImmutable $end)
{
    $months = aptd_kec_mingguan_month_labels();
    if ($start->format('m') === $end->format('m')) {
        return (int) $start->format('j') . '-' . (int) $end->format('j') . ' ' . $months[(int) $end->format('n')];
    }

    return (int) $start->format('j') . ' ' . $months[(int) $start->format('n')] . '-' . (int) $end->format('j') . ' ' . $months[(int) $end->format('n')];
}

function aptd_kec_mingguan_key_condition($conn, $column, array $keys)
{
    $values = [];
    foreach ($keys as $key) {
        $values[] = "'" . $conn->real_escape_string(strtolower(trim($key))) . "'";
    }
    return $column . ' IN (' . implode(', ', $values) . ')';
}

function aptd_kec_mingguan_patient_kecamatan_column($conn)
{
    static $column = null;
    if ($column !== null) {
        return $column;
    }

    $candidates = ['kecamatan_pj', 'kecamatanpj'];
    foreach ($candidates as $candidate) {
        $safe = $conn->real_escape_string($candidate);
        $result = $conn->query("SHOW COLUMNS FROM pasien WHERE Field = '$safe'");
        if ($result && $result->num_rows > 0) {
            $column = "p.`$candidate`";
            return $column;
        }
    }

    $column = 'p.`kecamatan_pj`';
    return $column;
}

function aptd_kec_mingguan_case_sql($conn, $target, $kecamatanColumn)
{
    $lines = ['CASE'];
    foreach (aptd_kec_mingguan_mapping() as $item) {
        $condition = aptd_kec_mingguan_key_condition($conn, 'TRIM(LOWER(' . $kecamatanColumn . '))', $item['keys']);
        $value = $conn->real_escape_string($target === 'kabupaten' ? $item['kabupaten'] : $item['kecamatan']);
        $lines[] = "WHEN $condition THEN '$value'";
    }
    $lines[] = "ELSE 'LAINNYA'";
    $lines[] = 'END';
    return implode("\n", $lines);
}

function aptd_kec_mingguan_all_keys_where($conn, $kecamatanColumn)
{
    $parts = [];
    foreach (aptd_kec_mingguan_mapping() as $item) {
        $parts[] = aptd_kec_mingguan_key_condition($conn, 'TRIM(LOWER(' . $kecamatanColumn . '))', $item['keys']);
    }
    return '(' . implode(' OR ', $parts) . ')';
}

function aptd_kec_mingguan_empty_counts($weeks)
{
    $payments = aptd_kec_mingguan_payment_labels();
    $counts = ['total' => 0, 'weeks' => []];
    foreach ($weeks as $idx => $week) {
        $counts['weeks'][$idx] = array_fill_keys($payments, 0);
    }
    return $counts;
}

function aptd_kec_mingguan_fetch($conn, $startDate, $endDate, array $weeks)
{
    $mapping = aptd_kec_mingguan_mapping();
    $payments = aptd_kec_mingguan_payment_labels();
    $data = [];
    $totals = ['total' => 0, 'weeks' => []];

    foreach ($weeks as $weekIdx => $week) {
        $totals['weeks'][$weekIdx] = array_fill_keys($payments, 0);
    }

    foreach ($mapping as $item) {
        $key = $item['kabupaten'] . '|' . $item['kecamatan'];
        $data[$key] = [
            'kabupaten' => $item['kabupaten'],
            'kecamatan' => $item['kecamatan'],
            'counts' => aptd_kec_mingguan_empty_counts($weeks),
        ];
    }

    $kecamatanColumn = aptd_kec_mingguan_patient_kecamatan_column($conn);
    $kabupatenCase = aptd_kec_mingguan_case_sql($conn, 'kabupaten', $kecamatanColumn);
    $kecamatanCase = aptd_kec_mingguan_case_sql($conn, 'kecamatan', $kecamatanColumn);
    $allKeysWhere = aptd_kec_mingguan_all_keys_where($conn, $kecamatanColumn);

    $sql = "
        SELECT
            x.kabupaten,
            x.kecamatan,
            x.tgl_registrasi,
            x.kategori,
            COUNT(DISTINCT x.no_rawat) AS total
        FROM (
            SELECT
                rp.no_rawat,
                rp.tgl_registrasi,
                $kabupatenCase AS kabupaten,
                $kecamatanCase AS kecamatan,
                CASE
                    WHEN rp.kd_pj = 'BPJ' THEN 'BPJS'
                    WHEN rp.kd_pj = 'A09' THEN 'Umum'
                    ELSE 'Asuransi'
                END AS kategori
            FROM reg_periksa rp
            INNER JOIN pasien p ON p.no_rkm_medis = rp.no_rkm_medis
            WHERE rp.status_lanjut = 'Ralan'
                AND rp.stts <> 'Batal'
                AND rp.tgl_registrasi BETWEEN ? AND ?
                AND NOT EXISTS (
                    SELECT 1
                    FROM kamar_inap ki
                    WHERE ki.no_rawat = rp.no_rawat
                )
                AND $kecamatanColumn IS NOT NULL
                AND TRIM($kecamatanColumn) <> ''
                AND $allKeysWhere
        ) x
        WHERE x.kabupaten <> 'LAINNYA'
            AND x.kecamatan <> 'LAINNYA'
        GROUP BY x.kabupaten, x.kecamatan, x.tgl_registrasi, x.kategori
        ORDER BY x.kabupaten, x.kecamatan, x.tgl_registrasi
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die('Query prepare gagal: ' . $conn->error);
    }

    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $weekIdx = aptd_kec_mingguan_find_week($row['tgl_registrasi'], $weeks);
        if ($weekIdx === null || !in_array($row['kategori'], $payments, true)) {
            continue;
        }

        $key = $row['kabupaten'] . '|' . $row['kecamatan'];
        if (!isset($data[$key])) {
            $data[$key] = [
                'kabupaten' => $row['kabupaten'],
                'kecamatan' => $row['kecamatan'],
                'counts' => aptd_kec_mingguan_empty_counts($weeks),
            ];
        }

        $total = (int) $row['total'];
        $data[$key]['counts']['weeks'][$weekIdx][$row['kategori']] += $total;
        $data[$key]['counts']['total'] += $total;
        $totals['weeks'][$weekIdx][$row['kategori']] += $total;
        $totals['total'] += $total;
    }

    $stmt->close();

    return [
        'rows' => array_values($data),
        'totals' => $totals,
    ];
}

function aptd_kec_mingguan_find_week($date, array $weeks)
{
    foreach ($weeks as $idx => $week) {
        if ($date >= $week['start'] && $date <= $week['end']) {
            return $idx;
        }
    }
    return null;
}

?>
