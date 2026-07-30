<?php

function aptd_task4_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function aptd_task4_default_filters()
{
    return [
        'tanggal_awal' => date('Y-m-01'),
        'tanggal_akhir' => date('Y-m-d', strtotime('+1 day')),
        'kd_poli' => '',
        'kd_dokter' => '',
        'kesesuaian' => 'semua',
        'search' => '',
        'page' => 1,
        'per_page' => 20,
        'sort' => 'tanggal',
        'direction' => 'asc',
    ];
}

function aptd_task4_is_valid_date($value)
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }

    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value;
}

function aptd_task4_filter_from_request(array $source)
{
    $filters = aptd_task4_default_filters();
    $errors = [];

    foreach (['tanggal_awal', 'tanggal_akhir', 'kd_poli', 'kd_dokter', 'kesesuaian', 'search', 'sort', 'direction'] as $key) {
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

    if (!aptd_task4_is_valid_date($filters['tanggal_awal'])) {
        $errors[] = 'Tanggal awal tidak valid.';
    }
    if (!aptd_task4_is_valid_date($filters['tanggal_akhir'])) {
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

    if (!in_array($filters['kesesuaian'], ['semua', 'sesuai', 'tidak_sesuai'], true)) {
        $errors[] = 'Status kesesuaian tidak valid.';
    }

    $allowedPerPage = [10, 20, 50, 100];
    if (!in_array($filters['per_page'], $allowedPerPage, true)) {
        $filters['per_page'] = 20;
    }
    if ($filters['page'] < 1) {
        $filters['page'] = 1;
    }

    $allowedSorts = [
        'tanggal',
        'nama_poli',
        'jam_buka_poli',
        'nama_dokter',
        'no_registrasi_awal',
        'task_4_paling_awal',
        'selisih_detik',
        'kesesuaian',
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

function aptd_task4_fetch_masters($conn)
{
    $polis = [];
    $doctors = [];

    $poliResult = $conn->query("
        SELECT kd_poli, nm_poli
        FROM poliklinik
        WHERE status = '1'
        ORDER BY nm_poli
    ");
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

function aptd_task4_fetch_base_rows($conn, array $filters)
{
    $firstWhere = [
        "kd_pj = 'BPJ'",
        "stts <> 'Batal'",
        'tgl_registrasi >= ?',
        'tgl_registrasi < ?',
    ];
    $mainWhere = [
        "t.taskid = '4'",
        'r.tgl_registrasi >= ?',
        'r.tgl_registrasi < ?',
    ];

    $firstTypes = 'ss';
    $firstParams = [$filters['tanggal_awal'], $filters['tanggal_akhir']];
    $mainTypes = 'ss';
    $mainParams = [$filters['tanggal_awal'], $filters['tanggal_akhir']];

    if ($filters['kd_poli'] !== '') {
        $firstWhere[] = 'kd_poli = ?';
        $mainWhere[] = 'r.kd_poli = ?';
        $firstTypes .= 's';
        $mainTypes .= 's';
        $firstParams[] = $filters['kd_poli'];
        $mainParams[] = $filters['kd_poli'];
    }

    if ($filters['kd_dokter'] !== '') {
        $firstWhere[] = 'kd_dokter = ?';
        $mainWhere[] = 'r.kd_dokter = ?';
        $firstTypes .= 's';
        $mainTypes .= 's';
        $firstParams[] = $filters['kd_dokter'];
        $mainParams[] = $filters['kd_dokter'];
    }

    $sql = "
        SELECT
            r.tgl_registrasi AS tanggal,
            p.nm_poli AS nama_poli,
            j.jam_mulai AS jam_buka_poli,
            d.nm_dokter AS nama_dokter,
            r.no_reg AS no_registrasi_awal,
            MIN(t.waktu) AS task_4_paling_awal,
            TIMESTAMPDIFF(
                SECOND,
                TIMESTAMP(r.tgl_registrasi, j.jam_mulai),
                MIN(t.waktu)
            ) AS selisih_detik,
            CASE
                WHEN MIN(t.waktu) BETWEEN
                    TIMESTAMP(r.tgl_registrasi, j.jam_mulai) - INTERVAL 1 HOUR
                    AND TIMESTAMP(r.tgl_registrasi, j.jam_mulai) + INTERVAL 1 HOUR
                THEN 'Sesuai'
                ELSE 'Tidak Sesuai'
            END AS kesesuaian
        FROM referensi_mobilejkn_bpjs_taskid t
        INNER JOIN reg_periksa r ON r.no_rawat = t.no_rawat
        INNER JOIN (
            SELECT
                tgl_registrasi,
                kd_poli,
                kd_dokter,
                MIN(no_reg) AS no_reg_awal
            FROM reg_periksa
            WHERE " . implode(' AND ', $firstWhere) . "
            GROUP BY tgl_registrasi, kd_poli, kd_dokter
        ) pasien_pertama
            ON r.tgl_registrasi = pasien_pertama.tgl_registrasi
            AND r.kd_poli = pasien_pertama.kd_poli
            AND r.kd_dokter = pasien_pertama.kd_dokter
            AND r.no_reg = pasien_pertama.no_reg_awal
        INNER JOIN jadwal j
            ON j.kd_dokter = r.kd_dokter
            AND j.kd_poli = r.kd_poli
            AND j.hari_kerja = CASE DAYOFWEEK(r.tgl_registrasi)
                WHEN 1 THEN 'AKHAD'
                WHEN 2 THEN 'SENIN'
                WHEN 3 THEN 'SELASA'
                WHEN 4 THEN 'RABU'
                WHEN 5 THEN 'KAMIS'
                WHEN 6 THEN 'JUMAT'
                WHEN 7 THEN 'SABTU'
            END
        INNER JOIN dokter d ON d.kd_dokter = r.kd_dokter
        INNER JOIN poliklinik p ON p.kd_poli = r.kd_poli
        WHERE " . implode(' AND ', $mainWhere) . "
        GROUP BY
            r.tgl_registrasi,
            p.nm_poli,
            j.jam_mulai,
            d.nm_dokter,
            r.no_reg
        ORDER BY r.tgl_registrasi, p.nm_poli, j.jam_mulai, d.nm_dokter
    ";

    $types = $firstTypes . $mainTypes;
    $params = array_merge($firstParams, $mainParams);
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];

    while ($row = $result->fetch_assoc()) {
        $row['selisih_detik'] = $row['selisih_detik'] === null ? null : (int) $row['selisih_detik'];
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function aptd_task4_filter_status(array $rows, $status)
{
    if ($status === 'semua') {
        return $rows;
    }

    $expected = $status === 'sesuai' ? 'Sesuai' : 'Tidak Sesuai';
    return array_values(array_filter($rows, function ($row) use ($expected) {
        return $row['kesesuaian'] === $expected;
    }));
}

function aptd_task4_format_difference($seconds)
{
    if ($seconds === null || $seconds === '') {
        return '-';
    }

    $seconds = (int) $seconds;
    if ($seconds === 0) {
        return 'Tepat waktu';
    }

    $absolute = abs($seconds);
    $hours = (int) floor($absolute / 3600);
    $minutes = (int) floor(($absolute % 3600) / 60);
    $remainingSeconds = $absolute % 60;
    $parts = [];

    if ($hours > 0) {
        $parts[] = $hours . ' jam';
    }
    if ($minutes > 0) {
        $parts[] = $minutes . ' menit';
    }
    if ($hours === 0 && $remainingSeconds > 0) {
        $parts[] = $remainingSeconds . ' detik';
    }

    return implode(' ', $parts) . ($seconds < 0 ? ' sebelum' : ' setelah');
}

function aptd_task4_format_date_id($date)
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

function aptd_task4_build_summary(array $rows)
{
    $summary = ['total' => count($rows), 'sesuai' => 0, 'tidak_sesuai' => 0, 'persentase_kesesuaian' => 0];
    foreach ($rows as $row) {
        if ($row['kesesuaian'] === 'Sesuai') {
            $summary['sesuai']++;
        } else {
            $summary['tidak_sesuai']++;
        }
    }
    if ($summary['total'] > 0) {
        $summary['persentase_kesesuaian'] = round(($summary['sesuai'] / $summary['total']) * 100, 2);
    }

    return $summary;
}

function aptd_task4_build_charts(array $rows, array $summary)
{
    $perPoli = [];
    $daily = [];

    foreach ($rows as $row) {
        $poli = $row['nama_poli'];
        if (!isset($perPoli[$poli])) {
            $perPoli[$poli] = ['label' => $poli, 'sesuai' => 0, 'tidak_sesuai' => 0, 'total' => 0];
        }
        $perPoli[$poli]['total']++;
        $perPoli[$poli][$row['kesesuaian'] === 'Sesuai' ? 'sesuai' : 'tidak_sesuai']++;

        $date = $row['tanggal'];
        if (!isset($daily[$date])) {
            $daily[$date] = ['tanggal' => $date, 'total' => 0, 'sesuai' => 0, 'tidak_sesuai' => 0, 'persentase' => 0];
        }
        $daily[$date]['total']++;
        $daily[$date][$row['kesesuaian'] === 'Sesuai' ? 'sesuai' : 'tidak_sesuai']++;
    }

    usort($perPoli, function ($a, $b) {
        if ($a['total'] === $b['total']) {
            return strcmp($a['label'], $b['label']);
        }
        return $b['total'] <=> $a['total'];
    });
    ksort($daily);

    foreach ($daily as &$item) {
        $item['persentase'] = $item['total'] > 0 ? round(($item['sesuai'] / $item['total']) * 100, 2) : 0;
    }
    unset($item);

    return [
        'status' => [
            ['label' => 'Sesuai', 'value' => $summary['sesuai']],
            ['label' => 'Tidak Sesuai', 'value' => $summary['tidak_sesuai']],
        ],
        'per_poli' => array_values($perPoli),
        'tren_harian' => array_values($daily),
    ];
}

function aptd_task4_search_rows(array $rows, $search)
{
    if ($search === '') {
        return $rows;
    }

    $needle = strtolower($search);
    return array_values(array_filter($rows, function ($row) use ($needle) {
        $haystack = strtolower(implode(' ', [
            $row['tanggal'],
            $row['nama_poli'],
            $row['jam_buka_poli'],
            $row['nama_dokter'],
            $row['no_registrasi_awal'],
            $row['task_4_paling_awal'],
            $row['kesesuaian'],
        ]));
        return strpos($haystack, $needle) !== false;
    }));
}

function aptd_task4_sort_rows(array $rows, $sort, $direction)
{
    usort($rows, function ($a, $b) use ($sort, $direction) {
        $left = isset($a[$sort]) ? $a[$sort] : '';
        $right = isset($b[$sort]) ? $b[$sort] : '';
        $comparison = $sort === 'selisih_detik'
            ? ((int) $left <=> (int) $right)
            : strnatcasecmp((string) $left, (string) $right);

        if ($comparison === 0) {
            $comparison = strcmp(
                $a['tanggal'] . $a['nama_poli'] . $a['nama_dokter'],
                $b['tanggal'] . $b['nama_poli'] . $b['nama_dokter']
            );
        }
        return $direction === 'desc' ? -$comparison : $comparison;
    });

    return $rows;
}

function aptd_task4_present_row(array $row)
{
    return array_merge($row, [
        'tanggal_label' => aptd_task4_format_date_id($row['tanggal']),
        'selisih_waktu' => aptd_task4_format_difference($row['selisih_detik']),
    ]);
}

function aptd_task4_build_report($conn, array $filters, $paginate = true)
{
    $baseRows = aptd_task4_fetch_base_rows($conn, $filters);
    $filteredRows = aptd_task4_filter_status($baseRows, $filters['kesesuaian']);
    $summary = aptd_task4_build_summary($filteredRows);
    $charts = aptd_task4_build_charts($filteredRows, $summary);
    $tableRows = aptd_task4_search_rows($filteredRows, $filters['search']);
    $tableRows = aptd_task4_sort_rows($tableRows, $filters['sort'], $filters['direction']);

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
        'data' => array_map('aptd_task4_present_row', $tableRows),
        'pagination' => [
            'page' => $page,
            'per_page' => $filters['per_page'],
            'total' => $tableTotal,
            'total_pages' => $totalPages,
        ],
    ];
}
