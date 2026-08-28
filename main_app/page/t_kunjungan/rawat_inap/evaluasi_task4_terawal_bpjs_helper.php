<?php
require_once __DIR__ . '/evaluasi_task4_bpjs_helper.php';

function aptd_task4_terawal_default_filters()
{
    return [
        'tanggal_awal' => date('Y-m-01'),
        'tanggal_akhir' => date('Y-m-d'),
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

function aptd_task4_terawal_filter_from_request(array $source)
{
    $filters = aptd_task4_terawal_default_filters();
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

    if (!in_array($filters['per_page'], [10, 20, 50, 100], true)) {
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
        'nomor_rawat',
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

function aptd_task4_terawal_fetch_base_rows($conn, array $filters)
{
    $where = [
        "t.taskid = '4'",
        "r.kd_pj = 'BPJ'",
        "r.stts <> 'Batal'",
        'r.tgl_registrasi >= ?',
        'r.tgl_registrasi <= ?',
    ];
    $types = 'ss';
    $params = [$filters['tanggal_awal'], $filters['tanggal_akhir']];

    if ($filters['kd_poli'] !== '') {
        $where[] = 'r.kd_poli = ?';
        $types .= 's';
        $params[] = $filters['kd_poli'];
    }
    if ($filters['kd_dokter'] !== '') {
        $where[] = 'r.kd_dokter = ?';
        $types .= 's';
        $params[] = $filters['kd_dokter'];
    }

    $sql = "
        SELECT
            r.tgl_registrasi AS tanggal,
            p.nm_poli AS nama_poli,
            j.jam_mulai AS jam_buka_poli,
            r.kd_dokter AS kode_dokter,
            d.nm_dokter AS nama_dokter,
            r.no_rawat AS nomor_rawat,
            task_awal.waktu_task4 AS task_4_paling_awal,
            TIMESTAMPDIFF(
                SECOND,
                TIMESTAMP(r.tgl_registrasi, j.jam_mulai),
                task_awal.waktu_task4
            ) AS selisih_detik,
            CASE
                WHEN task_awal.waktu_task4 BETWEEN
                    TIMESTAMP(r.tgl_registrasi, j.jam_mulai) - INTERVAL 1 HOUR
                    AND TIMESTAMP(r.tgl_registrasi, j.jam_mulai) + INTERVAL 1 HOUR
                THEN 'Sesuai'
                ELSE 'Tidak Sesuai'
            END AS kesesuaian
        FROM (
            SELECT
                r.tgl_registrasi,
                r.kd_poli,
                r.kd_dokter,
                MIN(t.waktu) AS waktu_task4
            FROM referensi_mobilejkn_bpjs_taskid t
            INNER JOIN reg_periksa r ON r.no_rawat = t.no_rawat
            WHERE " . implode(' AND ', $where) . "
            GROUP BY r.tgl_registrasi, r.kd_poli, r.kd_dokter
        ) task_awal
        INNER JOIN referensi_mobilejkn_bpjs_taskid t
            ON t.waktu = task_awal.waktu_task4
            AND t.taskid = '4'
        INNER JOIN reg_periksa r
            ON r.no_rawat = t.no_rawat
            AND r.tgl_registrasi = task_awal.tgl_registrasi
            AND r.kd_poli = task_awal.kd_poli
            AND r.kd_dokter = task_awal.kd_dokter
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
        GROUP BY
            r.tgl_registrasi,
            p.nm_poli,
            j.jam_mulai,
            r.kd_dokter,
            d.nm_dokter,
            r.no_rawat,
            task_awal.waktu_task4
        ORDER BY r.tgl_registrasi, p.nm_poli, j.jam_mulai, d.nm_dokter
    ";

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

function aptd_task4_terawal_search_rows(array $rows, $search)
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
            $row['nomor_rawat'],
            $row['task_4_paling_awal'],
            $row['kesesuaian'],
        ]));
        return strpos($haystack, $needle) !== false;
    }));
}

function aptd_task4_terawal_build_doctor_recap(array $rows)
{
    $doctorRecap = [];

    foreach ($rows as $row) {
        $doctorKey = isset($row['kode_dokter']) && $row['kode_dokter'] !== ''
            ? (string) $row['kode_dokter']
            : (string) $row['nama_dokter'];

        if (!isset($doctorRecap[$doctorKey])) {
            $doctorRecap[$doctorKey] = [
                'kode_dokter' => isset($row['kode_dokter']) ? (string) $row['kode_dokter'] : '',
                'nama_dokter' => isset($row['nama_dokter']) ? (string) $row['nama_dokter'] : '',
                'total_jadwal_dievaluasi' => 0,
                'status_kesesuaian' => 'Sesuai',
            ];
        }
        $doctorRecap[$doctorKey]['total_jadwal_dievaluasi']++;
        if ($row['kesesuaian'] === 'Tidak Sesuai') {
            $doctorRecap[$doctorKey]['status_kesesuaian'] = 'Tidak Sesuai';
        }
    }

    $doctorRecap = array_values($doctorRecap);
    usort($doctorRecap, function ($left, $right) {
        $nameComparison = strcasecmp($left['nama_dokter'], $right['nama_dokter']);
        if ($nameComparison !== 0) {
            return $nameComparison;
        }
        return strcmp($left['kode_dokter'], $right['kode_dokter']);
    });

    return $doctorRecap;
}

function aptd_task4_terawal_build_doctor_summary(array $rows)
{
    $doctorRecap = aptd_task4_terawal_build_doctor_recap($rows);
    $totalDoctors = count($doctorRecap);
    $unsuitableDoctors = count(array_filter($doctorRecap, function ($doctor) {
        return $doctor['status_kesesuaian'] === 'Tidak Sesuai';
    }));
    $suitableDoctors = $totalDoctors - $unsuitableDoctors;
    $suitabilityPercentage = $totalDoctors > 0
        ? round(($suitableDoctors / $totalDoctors) * 100, 2)
        : 0;

    return [
        'total_dokter_praktek' => $totalDoctors,
        'dokter_sesuai' => $suitableDoctors,
        'dokter_tidak_sesuai' => $unsuitableDoctors,
        'persentase_kesesuaian' => $suitabilityPercentage,
    ];
}

function aptd_task4_terawal_build_report($conn, array $filters, $paginate = true)
{
    $baseRows = aptd_task4_terawal_fetch_base_rows($conn, $filters);
    $doctorRecap = aptd_task4_terawal_build_doctor_recap($baseRows);
    $doctorSummary = aptd_task4_terawal_build_doctor_summary($baseRows);
    $filteredRows = aptd_task4_filter_status($baseRows, $filters['kesesuaian']);
    $summary = aptd_task4_build_summary($filteredRows);
    $charts = aptd_task4_build_charts($filteredRows, $summary);
    $tableRows = aptd_task4_terawal_search_rows($filteredRows, $filters['search']);
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
        'doctor_summary' => $doctorSummary,
        'doctor_recap' => $doctorRecap,
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
