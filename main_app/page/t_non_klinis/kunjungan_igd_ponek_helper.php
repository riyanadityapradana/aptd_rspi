<?php
// Kontrak data bersama untuk dashboard dan export UGD/Ponek.

function aptd_igd_ponek_categories()
{
    return [
        'ugd_ralan' => [
            'label' => 'UGD Ralan',
            'code' => 'U0009',
            'criteria' => 'Poli tujuan U0009',
            'color' => '#2e86de',
        ],
        'ugd_ranap' => [
            'label' => 'UGD Ranap',
            'code' => 'IGDK',
            'criteria' => 'Poli IGDK, masuk kamar_inap, dokter umum S0016',
            'color' => '#e74c3c',
        ],
        'ponek_ralan' => [
            'label' => 'Ponek Ralan',
            'code' => 'U0074',
            'criteria' => 'Poli tujuan U0074',
            'color' => '#f39c12',
        ],
        'ponek_ranap' => [
            'label' => 'Ponek Ranap',
            'code' => 'U0056',
            'criteria' => 'Poli U0056 dan masuk kamar_inap',
            'color' => '#8e44ad',
        ],
    ];
}

function aptd_igd_ponek_valid_date($value, $fallback)
{
    $value = trim((string) $value);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts)) {
        return $fallback;
    }

    return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) ? $value : $fallback;
}

function aptd_igd_ponek_period_from_post()
{
    $startDate = aptd_igd_ponek_valid_date(
        isset($_POST['tanggal_awal']) ? $_POST['tanggal_awal'] : '',
        date('Y-m-01')
    );
    $endDate = aptd_igd_ponek_valid_date(
        isset($_POST['tanggal_akhir']) ? $_POST['tanggal_akhir'] : '',
        date('Y-m-d')
    );

    if ($startDate > $endDate) {
        $temporary = $startDate;
        $startDate = $endDate;
        $endDate = $temporary;
    }

    return [$startDate, $endDate];
}

function aptd_igd_ponek_query($mysqli, $sql, $types = '', array $params = [])
{
    $attempt = 0;
    while ($attempt < 2) {
        $stmt = null;
        try {
            $stmt = $mysqli->prepare($sql);
            if ($types !== '') {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            $stmt->close();

            return $rows;
        } catch (mysqli_sql_exception $exception) {
            if ($stmt instanceof mysqli_stmt) {
                $stmt->close();
            }
            if ((int) $exception->getCode() !== 1615 || $attempt > 0) {
                throw $exception;
            }
            $attempt++;
        }
    }

    return [];
}

function aptd_igd_ponek_condition_sql()
{
    return [
        'ugd_ralan' => "rp.kd_poli = 'U0009'",
        'ugd_ranap' => "rp.kd_poli = 'IGDK'
            AND EXISTS (
                SELECT 1 FROM kamar_inap ki
                WHERE ki.no_rawat = rp.no_rawat
            )
            AND EXISTS (
                SELECT 1 FROM dokter d
                WHERE d.kd_dokter = rp.kd_dokter
                  AND d.kd_sps = 'S0016'
            )",
        'ponek_ralan' => "rp.kd_poli = 'U0074'",
        'ponek_ranap' => "rp.kd_poli = 'U0056'
            AND EXISTS (
                SELECT 1 FROM kamar_inap ki
                WHERE ki.no_rawat = rp.no_rawat
            )",
    ];
}

function aptd_igd_ponek_category_case_sql(array $conditions)
{
    $caseParts = [];
    foreach ($conditions as $key => $condition) {
        $caseParts[] = "WHEN ({$condition}) THEN '{$key}'";
    }

    return 'CASE ' . implode(' ', $caseParts) . ' END';
}

function aptd_igd_ponek_category_where_sql(array $conditions)
{
    return '(' . implode(') OR (', array_values($conditions)) . ')';
}

function aptd_igd_ponek_summary($mysqli, $startDate, $endDate)
{
    $categories = aptd_igd_ponek_categories();
    $conditions = aptd_igd_ponek_condition_sql();
    $categoryCase = aptd_igd_ponek_category_case_sql($conditions);
    $categoryWhere = aptd_igd_ponek_category_where_sql($conditions);

    $sql = "SELECT
                {$categoryCase} AS category_key,
                SUM(CASE WHEN rp.kd_pj = 'A09' THEN 1 ELSE 0 END) AS umum,
                SUM(CASE WHEN rp.kd_pj = 'BPJ' THEN 1 ELSE 0 END) AS bpjs,
                SUM(CASE WHEN rp.kd_pj = 'A92' THEN 1 ELSE 0 END) AS asuransi,
                SUM(CASE WHEN rp.kd_pj NOT IN ('A09', 'BPJ', 'A92') THEN 1 ELSE 0 END) AS lainnya,
                COUNT(DISTINCT rp.no_rawat) AS total
            FROM reg_periksa rp
            INNER JOIN pasien ps ON ps.no_rkm_medis = rp.no_rkm_medis
            WHERE rp.tgl_registrasi >= ?
              AND rp.tgl_registrasi < DATE_ADD(?, INTERVAL 1 DAY)
              AND ({$categoryWhere})
              AND LOWER(ps.nm_pasien) NOT LIKE '%test%'
            GROUP BY category_key";

    $queryRows = aptd_igd_ponek_query($mysqli, $sql, 'ss', [$startDate, $endDate]);
    $summary = [];
    foreach ($categories as $key => $category) {
        $summary[$key] = array_merge($category, [
            'umum' => 0,
            'bpjs' => 0,
            'asuransi' => 0,
            'lainnya' => 0,
            'total' => 0,
        ]);
    }

    foreach ($queryRows as $row) {
        $key = $row['category_key'];
        if (!isset($summary[$key])) {
            continue;
        }
        foreach (['umum', 'bpjs', 'asuransi', 'lainnya', 'total'] as $field) {
            $summary[$key][$field] = (int) $row[$field];
        }
    }

    return $summary;
}

function aptd_igd_ponek_excluded_specialists($mysqli, $startDate, $endDate)
{
    $rows = aptd_igd_ponek_query(
        $mysqli,
        "SELECT COUNT(DISTINCT rp.no_rawat) AS total
         FROM reg_periksa rp
         INNER JOIN pasien ps ON ps.no_rkm_medis = rp.no_rkm_medis
         WHERE rp.tgl_registrasi >= ?
           AND rp.tgl_registrasi < DATE_ADD(?, INTERVAL 1 DAY)
           AND rp.kd_poli = 'IGDK'
           AND EXISTS (
               SELECT 1 FROM kamar_inap ki
               WHERE ki.no_rawat = rp.no_rawat
           )
           AND NOT EXISTS (
               SELECT 1 FROM dokter d
               WHERE d.kd_dokter = rp.kd_dokter
                 AND d.kd_sps = 'S0016'
           )
           AND LOWER(ps.nm_pasien) NOT LIKE '%test%'",
        'ss',
        [$startDate, $endDate]
    );

    return isset($rows[0]['total']) ? (int) $rows[0]['total'] : 0;
}
