<?php

function aptd_hais_month_names()
{
    return [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];
}

function aptd_hais_metric_columns()
{
    return [
        'jml_pasien',
        'ETT',
        'CVL',
        'IVL',
        'UC',
        'VAP',
        'IAD',
        'PLEB',
        'ISK',
        'ILO',
        'HAP',
        'Tinea',
        'Scabies',
        'DEKU',
        'SPUTUM',
        'DARAH',
        'URINE',
        'ANTIBIOTIK',
    ];
}

function aptd_hais_filter_month($value)
{
    $month = (int) $value;
    return ($month >= 1 && $month <= 12) ? $month : (int) date('n');
}

function aptd_hais_filter_year($value)
{
    $year = (int) $value;
    return ($year >= 2000 && $year <= 2100) ? $year : (int) date('Y');
}

function aptd_hais_filters_from_request()
{
    return [
        'bulan' => aptd_hais_filter_month(isset($_POST['bulan']) ? $_POST['bulan'] : date('n')),
        'tahun' => aptd_hais_filter_year(isset($_POST['tahun']) ? $_POST['tahun'] : date('Y')),
        'kd_bangsal' => isset($_POST['kd_bangsal']) ? trim((string) $_POST['kd_bangsal']) : '',
        'kd_pj' => isset($_POST['kd_pj']) ? trim((string) $_POST['kd_pj']) : '',
    ];
}

function aptd_hais_bangsal_options($mysqli)
{
    $sql = "SELECT DISTINCT b.kd_bangsal, b.nm_bangsal
            FROM bangsal b
            INNER JOIN kamar k ON k.kd_bangsal = b.kd_bangsal
            WHERE b.status = '1'
              AND k.statusdata = '1'
              AND LOWER(TRIM(b.kd_bangsal)) <> 'test'
              AND LOWER(TRIM(b.nm_bangsal)) <> 'test'
            ORDER BY b.nm_bangsal ASC";
    $result = $mysqli->query($sql);
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function aptd_hais_penjab_options($mysqli)
{
    $sql = "SELECT kd_pj, png_jawab
            FROM penjab
            WHERE status = '1'
            ORDER BY png_jawab ASC";
    $result = $mysqli->query($sql);
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function aptd_hais_validate_selected_option($selected, array $options, $key)
{
    $selected = trim((string) $selected);
    if ($selected === '') {
        return '';
    }

    foreach ($options as $option) {
        if (isset($option[$key]) && (string) $option[$key] === $selected) {
            return $selected;
        }
    }

    return '';
}

function aptd_hais_empty_row($date)
{
    $row = ['tanggal' => $date];
    foreach (aptd_hais_metric_columns() as $column) {
        $row[$column] = 0;
    }
    return $row;
}

function aptd_hais_bind_params($stmt, $types, array $params)
{
    if ($types === '') {
        return;
    }

    $refs = [];
    $refs[] = $types;
    foreach ($params as $index => $value) {
        $refs[] = &$params[$index];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

function aptd_hais_report($mysqli, array $filters)
{
    $bangsalOptions = aptd_hais_bangsal_options($mysqli);
    $penjabOptions = aptd_hais_penjab_options($mysqli);
    $filters['kd_bangsal'] = aptd_hais_validate_selected_option(isset($filters['kd_bangsal']) ? $filters['kd_bangsal'] : '', $bangsalOptions, 'kd_bangsal');
    $filters['kd_pj'] = aptd_hais_validate_selected_option(isset($filters['kd_pj']) ? $filters['kd_pj'] : '', $penjabOptions, 'kd_pj');

    $startDate = sprintf('%04d-%02d-01', $filters['tahun'], $filters['bulan']);
    $nextMonthDate = date('Y-m-d', strtotime($startDate . ' +1 month'));
    $daysInMonth = (int) date('t', strtotime($startDate));

    $rowsByDate = [];
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $date = sprintf('%04d-%02d-%02d', $filters['tahun'], $filters['bulan'], $day);
        $rowsByDate[$date] = aptd_hais_empty_row($date);
    }

    $where = [
        'h.tanggal >= ?',
        'h.tanggal < ?',
    ];
    $types = 'ss';
    $params = [$startDate, $nextMonthDate];

    if ($filters['kd_bangsal'] !== '') {
        $where[] = 'b.kd_bangsal = ?';
        $types .= 's';
        $params[] = $filters['kd_bangsal'];
    }

    if ($filters['kd_pj'] !== '') {
        $where[] = 'rp.kd_pj = ?';
        $types .= 's';
        $params[] = $filters['kd_pj'];
    }

    $sql = "SELECT
                h.tanggal,
                COUNT(DISTINCT h.no_rawat) AS jml_pasien,
                SUM(COALESCE(h.ETT, 0)) AS ETT,
                SUM(COALESCE(h.CVL, 0)) AS CVL,
                SUM(COALESCE(h.IVL, 0)) AS IVL,
                SUM(COALESCE(h.UC, 0)) AS UC,
                SUM(COALESCE(h.VAP, 0)) AS VAP,
                SUM(COALESCE(h.IAD, 0)) AS IAD,
                SUM(COALESCE(h.PLEB, 0)) AS PLEB,
                SUM(COALESCE(h.ISK, 0)) AS ISK,
                SUM(COALESCE(h.ILO, 0)) AS ILO,
                SUM(COALESCE(h.HAP, 0)) AS HAP,
                SUM(COALESCE(h.`Tinea`, 0)) AS Tinea,
                SUM(COALESCE(h.Scabies, 0)) AS Scabies,
                SUM(CASE WHEN h.DEKU = 'IYA' THEN 1 ELSE 0 END) AS DEKU,
                SUM(CASE WHEN h.SPUTUM IS NOT NULL AND TRIM(h.SPUTUM) <> '' THEN 1 ELSE 0 END) AS SPUTUM,
                SUM(CASE WHEN h.DARAH IS NOT NULL AND TRIM(h.DARAH) <> '' THEN 1 ELSE 0 END) AS DARAH,
                SUM(CASE WHEN h.URINE IS NOT NULL AND TRIM(h.URINE) <> '' THEN 1 ELSE 0 END) AS URINE,
                SUM(CASE WHEN h.ANTIBIOTIK IS NOT NULL AND TRIM(h.ANTIBIOTIK) <> '' THEN 1 ELSE 0 END) AS ANTIBIOTIK
            FROM data_HAIs h
            LEFT JOIN kamar k ON k.kd_kamar = h.kd_kamar
            LEFT JOIN bangsal b ON b.kd_bangsal = k.kd_bangsal
            LEFT JOIN reg_periksa rp ON rp.no_rawat = h.no_rawat
            LEFT JOIN penjab pj ON pj.kd_pj = rp.kd_pj
            WHERE " . implode(' AND ', $where) . "
            GROUP BY h.tanggal
            ORDER BY h.tanggal ASC";

    $stmt = $mysqli->prepare($sql);
    aptd_hais_bind_params($stmt, $types, $params);
    try {
        $stmt->execute();
    } catch (mysqli_sql_exception $exception) {
        if ((int) $exception->getCode() !== 1615) {
            throw $exception;
        }

        $stmt->close();
        $stmt = $mysqli->prepare($sql);
        aptd_hais_bind_params($stmt, $types, $params);
        $stmt->execute();
    }

    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $date = $row['tanggal'];
        if (!isset($rowsByDate[$date])) {
            continue;
        }

        foreach (aptd_hais_metric_columns() as $column) {
            $row[$column] = isset($row[$column]) ? (int) $row[$column] : 0;
        }
        $rowsByDate[$date] = array_merge(['tanggal' => $date], array_intersect_key($row, array_flip(aptd_hais_metric_columns())));
    }
    $stmt->close();

    $totals = aptd_hais_empty_row('Total');
    foreach ($rowsByDate as $row) {
        foreach (aptd_hais_metric_columns() as $column) {
            $totals[$column] += (int) $row[$column];
        }
    }

    return [
        'filters' => $filters,
        'bangsal_options' => $bangsalOptions,
        'penjab_options' => $penjabOptions,
        'start_date' => $startDate,
        'end_date' => date('Y-m-d', strtotime($nextMonthDate . ' -1 day')),
        'rows' => array_values($rowsByDate),
        'totals' => $totals,
    ];
}
