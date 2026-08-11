<?php

function aptd_wt25_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function aptd_wt25_is_valid_date($value)
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }

    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value;
}

function aptd_wt25_default_filters()
{
    return [
        'tanggal_awal' => date('Y-m-01'),
        'tanggal_akhir' => date('Y-m-d'),
        'kd_poli' => '',
        'kd_dokter' => '',
        'status_task99' => 'semua',
        'search' => '',
        'page' => 1,
        'per_page' => 20,
        'sort' => 'tanggal',
        'direction' => 'asc',
    ];
}

function aptd_wt25_filter_from_request(array $source)
{
    $filters = aptd_wt25_default_filters();
    $errors = [];

    foreach (['tanggal_awal', 'tanggal_akhir', 'kd_poli', 'kd_dokter', 'status_task99', 'search', 'sort', 'direction'] as $key) {
        if (isset($source[$key]) && !is_array($source[$key])) {
            $filters[$key] = trim((string) $source[$key]);
        }
    }
    if (isset($source['page']) && !is_array($source['page'])) {
        $filters['page'] = (int) $source['page'];
    }
    if (isset($source['per_page']) && !is_array($source['per_page'])) {
        $filters['per_page'] = (int) $source['per_page'];
    }

    if (!aptd_wt25_is_valid_date($filters['tanggal_awal'])) {
        $errors[] = 'Tanggal awal tidak valid.';
    }
    if (!aptd_wt25_is_valid_date($filters['tanggal_akhir'])) {
        $errors[] = 'Tanggal akhir tidak valid.';
    }
    if (empty($errors)) {
        $start = new DateTime($filters['tanggal_awal']);
        $end = new DateTime($filters['tanggal_akhir']);
        if ($end < $start) {
            $errors[] = 'Tanggal akhir tidak boleh lebih kecil dari tanggal awal.';
        } elseif ((int) $start->diff($end)->format('%a') > 366) {
            $errors[] = 'Rentang laporan maksimal 366 hari.';
        }
    }

    if (
        strlen($filters['kd_poli']) > 20
        || ($filters['kd_poli'] !== '' && !preg_match('/^[A-Za-z0-9._-]+$/', $filters['kd_poli']))
    ) {
        $errors[] = 'Pilihan poli tidak valid.';
    }
    if (
        strlen($filters['kd_dokter']) > 30
        || ($filters['kd_dokter'] !== '' && !preg_match('/^[A-Za-z0-9._-]+$/', $filters['kd_dokter']))
    ) {
        $errors[] = 'Pilihan dokter tidak valid.';
    }
    if (!in_array($filters['status_task99'], ['semua', 'terkirim', 'belum_terkirim', 'bukan_batal'], true)) {
        $errors[] = 'Status Task 99 tidak valid.';
    }

    if (!in_array($filters['per_page'], [10, 20, 50, 100], true)) {
        $filters['per_page'] = 20;
    }
    if ($filters['page'] < 1) {
        $filters['page'] = 1;
    }

    $allowedSorts = [
        'tanggal',
        'no_rawat',
        'nama_pasien',
        'nama_poli',
        'nama_dokter',
        'jam_buka_poli',
        'task_2',
        'task_3',
        'task_4',
        'task_5',
        'wt_seconds',
        'status_daftar',
        'status_batal_task99',
    ];
    if (!in_array($filters['sort'], $allowedSorts, true)) {
        $filters['sort'] = 'tanggal';
    }
    if (!in_array($filters['direction'], ['asc', 'desc'], true)) {
        $filters['direction'] = 'asc';
    }

    $filters['search'] = substr($filters['search'], 0, 100);
    return [$filters, $errors];
}

function aptd_wt25_fetch_masters($conn)
{
    $polis = [];
    $doctors = [];

    $poliResult = $conn->query("SELECT kd_poli, nm_poli FROM poliklinik WHERE status = '1' ORDER BY nm_poli");
    while ($row = $poliResult->fetch_assoc()) {
        $polis[] = $row;
    }

    $doctorResult = $conn->query("
        SELECT DISTINCT j.kd_poli, d.kd_dokter, d.nm_dokter
        FROM jadwal j
        INNER JOIN dokter d ON d.kd_dokter = j.kd_dokter
        INNER JOIN poliklinik p ON p.kd_poli = j.kd_poli AND p.status = '1'
        ORDER BY d.nm_dokter, j.kd_poli
    ");
    while ($row = $doctorResult->fetch_assoc()) {
        $doctors[] = $row;
    }

    return ['polis' => $polis, 'doctors' => $doctors];
}

function aptd_wt25_fetch_base_rows($conn, array $filters)
{
    $where = [
        "rp.kd_pj = 'BPJ'",
        "rp.status_lanjut = 'Ralan'",
        'rp.tgl_registrasi >= ?',
        'rp.tgl_registrasi <= ?',
        '(t2.waktu IS NOT NULL OR t3.waktu IS NOT NULL OR t4.waktu IS NOT NULL OR t5.waktu IS NOT NULL)',
    ];
    $types = 'ss';
    $params = [$filters['tanggal_awal'], $filters['tanggal_akhir']];

    if ($filters['kd_poli'] !== '') {
        $where[] = 'rp.kd_poli = ?';
        $types .= 's';
        $params[] = $filters['kd_poli'];
    }
    if ($filters['kd_dokter'] !== '') {
        $where[] = 'rp.kd_dokter = ?';
        $types .= 's';
        $params[] = $filters['kd_dokter'];
    }

    $sql = "
        SELECT
            rp.tgl_registrasi AS tanggal,
            rp.jam_reg,
            rp.no_rawat,
            p.nm_pasien AS nama_pasien,
            d.nm_dokter AS nama_dokter,
            pl.nm_poli AS nama_poli,
            j.jam_mulai AS jam_buka_poli,
            t2.waktu AS task_2,
            t3.waktu AS task_3,
            t4.waktu AS task_4,
            t5.waktu AS task_5,
            t99.waktu AS task_99,
            TIMESTAMPDIFF(SECOND, COALESCE(t2.waktu, t3.waktu), t4.waktu) AS wt_seconds,
            CASE
                WHEN t2.waktu IS NOT NULL THEN 'Task 2'
                WHEN t3.waktu IS NOT NULL THEN 'Task 3'
                ELSE '-'
            END AS sumber_wt,
            rp.stts AS status_daftar,
            CASE
                WHEN rp.stts = 'Batal' THEN
                    CASE WHEN t99.waktu IS NOT NULL THEN 'Terkirim' ELSE 'Belum Terkirim' END
                ELSE '-'
            END AS status_batal_task99
        FROM reg_periksa rp
        INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
        INNER JOIN dokter d ON rp.kd_dokter = d.kd_dokter
        INNER JOIN poliklinik pl ON rp.kd_poli = pl.kd_poli
        LEFT JOIN (
            SELECT kd_dokter, kd_poli, hari_kerja, MIN(jam_mulai) AS jam_mulai
            FROM jadwal
            GROUP BY kd_dokter, kd_poli, hari_kerja
        ) j ON rp.kd_dokter = j.kd_dokter
            AND rp.kd_poli = j.kd_poli
            AND j.hari_kerja = CASE DAYOFWEEK(rp.tgl_registrasi)
                WHEN 1 THEN 'AKHAD'
                WHEN 2 THEN 'SENIN'
                WHEN 3 THEN 'SELASA'
                WHEN 4 THEN 'RABU'
                WHEN 5 THEN 'KAMIS'
                WHEN 6 THEN 'JUMAT'
                WHEN 7 THEN 'SABTU'
            END
        LEFT JOIN referensi_mobilejkn_bpjs_taskid t2 ON rp.no_rawat = t2.no_rawat AND t2.taskid = '2'
        LEFT JOIN referensi_mobilejkn_bpjs_taskid t3 ON rp.no_rawat = t3.no_rawat AND t3.taskid = '3'
        LEFT JOIN referensi_mobilejkn_bpjs_taskid t4 ON rp.no_rawat = t4.no_rawat AND t4.taskid = '4'
        LEFT JOIN referensi_mobilejkn_bpjs_taskid t5 ON rp.no_rawat = t5.no_rawat AND t5.taskid = '5'
        LEFT JOIN referensi_mobilejkn_bpjs_taskid t99 ON rp.no_rawat = t99.no_rawat AND t99.taskid = '99'
        WHERE " . implode(' AND ', $where) . "
        ORDER BY rp.tgl_registrasi, rp.jam_reg, rp.no_rawat
    ";

    $statement = $conn->prepare($sql);
    if (!$statement) {
        throw new RuntimeException('Query indikator waktu tunggu poli tidak dapat dipersiapkan.');
    }
    $statement->bind_param($types, ...$params);
    $statement->execute();
    $result = $statement->get_result();
    $rows = [];

    while ($row = $result->fetch_assoc()) {
        $row['wt_seconds'] = $row['wt_seconds'] === null ? null : (int) $row['wt_seconds'];
        $rows[] = $row;
    }

    $statement->close();
    return $rows;
}

function aptd_wt25_filter_task99(array $rows, $status)
{
    if ($status === 'semua') {
        return $rows;
    }

    return array_values(array_filter($rows, function ($row) use ($status) {
        if ($status === 'terkirim') {
            return $row['status_batal_task99'] === 'Terkirim';
        }
        if ($status === 'belum_terkirim') {
            return $row['status_batal_task99'] === 'Belum Terkirim';
        }
        return $row['status_daftar'] !== 'Batal';
    }));
}

function aptd_wt25_format_duration($seconds)
{
    if ($seconds === null || $seconds === '') {
        return '-';
    }

    $seconds = (int) $seconds;
    $sign = $seconds < 0 ? '-' : '';
    $absolute = abs($seconds);
    $hours = (int) floor($absolute / 3600);
    $minutes = (int) floor(($absolute % 3600) / 60);
    $remainingSeconds = $absolute % 60;
    return $sign . sprintf('%02d:%02d:%02d', $hours, $minutes, $remainingSeconds);
}

function aptd_wt25_format_date_id($date)
{
    $months = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return (string) $date;
    }
    return date('d', $timestamp) . ' ' . $months[(int) date('n', $timestamp)] . ' ' . date('Y', $timestamp);
}

function aptd_wt25_format_timestamp($value)
{
    return $value === null || $value === '' ? '-' : (string) $value;
}

function aptd_wt25_build_summary(array $rows)
{
    $summary = [
        'total' => count($rows),
        'wt_tersedia' => 0,
        'wt_tidak_tersedia' => 0,
        'rata_rata_detik' => null,
        'rata_rata_wt' => '-',
        'batal' => 0,
        'task99_terkirim' => 0,
        'task99_belum_terkirim' => 0,
    ];
    $totalSeconds = 0;

    foreach ($rows as $row) {
        if ($row['wt_seconds'] === null) {
            $summary['wt_tidak_tersedia']++;
        } else {
            $summary['wt_tersedia']++;
            $totalSeconds += (int) $row['wt_seconds'];
        }

        if ($row['status_daftar'] === 'Batal') {
            $summary['batal']++;
            if ($row['status_batal_task99'] === 'Terkirim') {
                $summary['task99_terkirim']++;
            } else {
                $summary['task99_belum_terkirim']++;
            }
        }
    }

    if ($summary['wt_tersedia'] > 0) {
        $summary['rata_rata_detik'] = (int) round($totalSeconds / $summary['wt_tersedia']);
        $summary['rata_rata_wt'] = aptd_wt25_format_duration($summary['rata_rata_detik']);
    }

    return $summary;
}

function aptd_wt25_build_charts(array $rows, array $summary)
{
    $perPoli = [];

    foreach ($rows as $row) {
        if ($row['wt_seconds'] === null) {
            continue;
        }
        $poli = $row['nama_poli'];
        if (!isset($perPoli[$poli])) {
            $perPoli[$poli] = ['label' => $poli, 'total_detik' => 0, 'jumlah' => 0, 'rata_rata_menit' => 0];
        }
        $perPoli[$poli]['total_detik'] += (int) $row['wt_seconds'];
        $perPoli[$poli]['jumlah']++;
    }

    foreach ($perPoli as &$item) {
        $item['rata_rata_menit'] = $item['jumlah'] > 0
            ? round(($item['total_detik'] / $item['jumlah']) / 60, 2)
            : 0;
        unset($item['total_detik']);
    }
    unset($item);

    usort($perPoli, function ($left, $right) {
        if ($left['jumlah'] === $right['jumlah']) {
            return strcmp($left['label'], $right['label']);
        }
        return $right['jumlah'] <=> $left['jumlah'];
    });

    return [
        'kelengkapan' => [
            ['label' => 'WT Tersedia', 'value' => $summary['wt_tersedia']],
            ['label' => 'WT Tidak Tersedia', 'value' => $summary['wt_tidak_tersedia']],
        ],
        'task99' => [
            ['label' => 'Terkirim', 'value' => $summary['task99_terkirim']],
            ['label' => 'Belum Terkirim', 'value' => $summary['task99_belum_terkirim']],
        ],
        'per_poli' => array_slice(array_values($perPoli), 0, 20),
    ];
}

function aptd_wt25_search_rows(array $rows, $search)
{
    if ($search === '') {
        return $rows;
    }

    $needle = strtolower($search);
    return array_values(array_filter($rows, function ($row) use ($needle) {
        $haystack = strtolower(implode(' ', [
            $row['tanggal'],
            $row['no_rawat'],
            $row['nama_pasien'],
            $row['nama_poli'],
            $row['nama_dokter'],
            $row['jam_buka_poli'],
            $row['task_2'],
            $row['task_3'],
            $row['task_4'],
            $row['task_5'],
            $row['sumber_wt'],
            $row['status_daftar'],
            $row['status_batal_task99'],
        ]));
        return strpos($haystack, $needle) !== false;
    }));
}

function aptd_wt25_sort_rows(array $rows, $sort, $direction)
{
    usort($rows, function ($left, $right) use ($sort, $direction) {
        $leftValue = isset($left[$sort]) ? $left[$sort] : '';
        $rightValue = isset($right[$sort]) ? $right[$sort] : '';
        if ($sort === 'wt_seconds') {
            $leftValue = $leftValue === null ? PHP_INT_MAX : (int) $leftValue;
            $rightValue = $rightValue === null ? PHP_INT_MAX : (int) $rightValue;
            $comparison = $leftValue <=> $rightValue;
        } else {
            $comparison = strnatcasecmp((string) $leftValue, (string) $rightValue);
        }

        if ($comparison === 0) {
            $comparison = strcmp(
                $left['tanggal'] . $left['jam_reg'] . $left['no_rawat'],
                $right['tanggal'] . $right['jam_reg'] . $right['no_rawat']
            );
        }
        return $direction === 'desc' ? -$comparison : $comparison;
    });

    return $rows;
}

function aptd_wt25_present_row(array $row)
{
    $row['tanggal_label'] = aptd_wt25_format_date_id($row['tanggal']);
    foreach (['task_2', 'task_3', 'task_4', 'task_5', 'task_99'] as $field) {
        $row[$field] = aptd_wt25_format_timestamp($row[$field]);
    }
    $row['jam_buka_poli'] = $row['jam_buka_poli'] === null || $row['jam_buka_poli'] === '' ? '-' : $row['jam_buka_poli'];
    $row['wt_poli'] = aptd_wt25_format_duration($row['wt_seconds']);
    return $row;
}

function aptd_wt25_build_report($conn, array $filters, $paginate = true)
{
    $baseRows = aptd_wt25_fetch_base_rows($conn, $filters);
    $filteredRows = aptd_wt25_filter_task99($baseRows, $filters['status_task99']);
    $summary = aptd_wt25_build_summary($filteredRows);
    $charts = aptd_wt25_build_charts($filteredRows, $summary);
    $tableRows = aptd_wt25_search_rows($filteredRows, $filters['search']);
    $tableRows = aptd_wt25_sort_rows($tableRows, $filters['sort'], $filters['direction']);

    $tableTotal = count($tableRows);
    $totalPages = max(1, (int) ceil($tableTotal / $filters['per_page']));
    $page = min(max(1, $filters['page']), $totalPages);

    if ($paginate) {
        $offset = ($page - 1) * $filters['per_page'];
        $tableRows = array_slice($tableRows, $offset, $filters['per_page']);
    }

    return [
        'filters' => array_merge($filters, ['page' => $page]),
        'summary' => $summary,
        'chart' => $charts,
        'data' => array_map('aptd_wt25_present_row', $tableRows),
        'pagination' => [
            'page' => $page,
            'per_page' => $filters['per_page'],
            'total' => $tableTotal,
            'total_pages' => $totalPages,
        ],
    ];
}
