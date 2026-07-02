<?php
require_once __DIR__ . '/report_helper.php';

function aptd_ranap_indicator_query($mysqli, $sql, $types = '', array $params = [])
{
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
}

function aptd_ranap_indicator_class_key($kelas)
{
    $map = [
        'Kelas VVIP' => 'VVIP',
        'Kelas VIP' => 'VIP',
        'Kelas 1' => 'I',
        'Kelas 2' => 'II',
        'Kelas 3' => 'III',
    ];

    return isset($map[$kelas]) ? $map[$kelas] : 'Khusus';
}

function aptd_ranap_indicator_bangsal_options($mysqli)
{
    return aptd_ranap_indicator_query(
        $mysqli,
        "SELECT DISTINCT b.kd_bangsal, b.nm_bangsal
         FROM kamar k
         INNER JOIN bangsal b ON b.kd_bangsal = k.kd_bangsal
         WHERE k.statusdata = '1'
           AND LOWER(TRIM(k.kd_bangsal)) <> 'test'
         ORDER BY b.nm_bangsal ASC"
    );
}

function aptd_ranap_indicator_is_ideal($indicator, $value, $hasDenominator = true)
{
    if (!$hasDenominator) {
        return null;
    }

    $ranges = [
        'bor' => [60, 85],
        'los' => [6, 9],
        'toi' => [1, 3],
        'bto' => [2, 4],
    ];
    if (!isset($ranges[$indicator])) {
        return null;
    }

    return $value >= $ranges[$indicator][0] && $value <= $ranges[$indicator][1];
}

function aptd_ranap_indicator_calculate($mysqli, $startDate, $endDate, $bangsalFilter = '')
{
    $periodDays = (int) date('t', strtotime($startDate));
    $classKeys = ['VVIP', 'VIP', 'I', 'II', 'III', 'Khusus'];
    $classBreakdown = [];
    foreach ($classKeys as $classKey) {
        $classBreakdown[$classKey] = [
            'kelas' => $classKey,
            'jumlah_tt' => 0,
            'hari_perawatan' => 0,
        ];
    }

    $bedWhere = '';
    $bedTypes = '';
    $bedParams = [];
    if ($bangsalFilter !== '') {
        $bedWhere = ' AND k.kd_bangsal = ?';
        $bedTypes = 's';
        $bedParams[] = $bangsalFilter;
    }

    $bedRows = aptd_ranap_indicator_query(
        $mysqli,
        "SELECT
             k.kd_bangsal,
             b.nm_bangsal,
             k.kelas,
             COUNT(DISTINCT k.kd_kamar) AS jumlah_tt
         FROM kamar k
         INNER JOIN bangsal b ON b.kd_bangsal = k.kd_bangsal
         WHERE k.statusdata = '1'
           AND LOWER(TRIM(k.kd_bangsal)) <> 'test'
           {$bedWhere}
         GROUP BY k.kd_bangsal, b.nm_bangsal, k.kelas
         ORDER BY b.nm_bangsal ASC, k.kelas ASC",
        $bedTypes,
        $bedParams
    );

    $wards = [];
    foreach ($bedRows as $bedRow) {
        $wardCode = $bedRow['kd_bangsal'];
        if (!isset($wards[$wardCode])) {
            $wards[$wardCode] = [
                'kd_bangsal' => $wardCode,
                'nm_bangsal' => $bedRow['nm_bangsal'],
                'jumlah_tt' => 0,
                'hari_perawatan' => 0,
                'pasien_keluar' => 0,
                'lama_dirawat' => 0,
            ];
        }

        $bedCount = (int) $bedRow['jumlah_tt'];
        $wards[$wardCode]['jumlah_tt'] += $bedCount;
        $classKey = aptd_ranap_indicator_class_key($bedRow['kelas']);
        $classBreakdown[$classKey]['jumlah_tt'] += $bedCount;
    }

    $careWhere = '';
    $careParams = [$endDate, $endDate, $startDate, $endDate, $startDate];
    if ($bangsalFilter !== '') {
        $careWhere = ' AND k.kd_bangsal = ?';
        $careParams[] = $bangsalFilter;
    }
    $careRows = aptd_ranap_indicator_query(
        $mysqli,
        "SELECT
             k.kd_bangsal,
             k.kelas,
             SUM(
                 GREATEST(
                     DATEDIFF(
                         LEAST(COALESCE(NULLIF(ki.tgl_keluar, '0000-00-00'), ?), ?),
                         GREATEST(ki.tgl_masuk, ?)
                     ) + 1,
                     0
                 )
             ) AS hari_perawatan
         FROM kamar_inap ki
         INNER JOIN kamar k ON k.kd_kamar = ki.kd_kamar
         WHERE k.statusdata = '1'
           AND LOWER(TRIM(k.kd_bangsal)) <> 'test'
           AND ki.tgl_masuk <= ?
           AND (
               (
                   ki.tgl_keluar IS NOT NULL
                   AND ki.tgl_keluar <> '0000-00-00'
                   AND ki.tgl_keluar >= ?
               )
               OR (
                   (ki.tgl_keluar IS NULL OR ki.tgl_keluar = '0000-00-00')
                   AND ki.stts_pulang = '-'
               )
           )
           {$careWhere}
         GROUP BY k.kd_bangsal, k.kelas",
        str_repeat('s', count($careParams)),
        $careParams
    );

    foreach ($careRows as $careRow) {
        $wardCode = $careRow['kd_bangsal'];
        $careDays = (int) $careRow['hari_perawatan'];
        if (isset($wards[$wardCode])) {
            $wards[$wardCode]['hari_perawatan'] += $careDays;
        }
        $classKey = aptd_ranap_indicator_class_key($careRow['kelas']);
        $classBreakdown[$classKey]['hari_perawatan'] += $careDays;
    }

    $exitWhere = '';
    $exitParams = [$startDate, $endDate];
    if ($bangsalFilter !== '') {
        $exitWhere = ' AND k_scope.kd_bangsal = ?';
        $exitParams[] = $bangsalFilter;
    }
    $exitRows = aptd_ranap_indicator_query(
        $mysqli,
        "SELECT
             final_stay.kd_bangsal,
             COUNT(*) AS pasien_keluar,
             SUM(final_stay.lama_dirawat) AS lama_dirawat
         FROM (
             SELECT
                 e.no_rawat,
                 SUBSTRING_INDEX(
                     GROUP_CONCAT(
                         k_scope.kd_bangsal
                         ORDER BY e.tgl_keluar DESC, e.jam_keluar DESC, e.kd_kamar ASC
                     ),
                     ',',
                     1
                 ) AS kd_bangsal,
                 GREATEST(DATEDIFF(MAX(e.tgl_keluar), MIN(history.tgl_masuk)), 0) AS lama_dirawat
             FROM kamar_inap e
             INNER JOIN kamar k_scope ON k_scope.kd_kamar = e.kd_kamar
             INNER JOIN kamar_inap history ON history.no_rawat = e.no_rawat
             WHERE e.tgl_keluar >= ?
               AND e.tgl_keluar < DATE_ADD(?, INTERVAL 1 DAY)
               AND e.stts_pulang NOT IN ('-', 'Pindah Kamar')
               AND k_scope.statusdata = '1'
               AND LOWER(TRIM(k_scope.kd_bangsal)) <> 'test'
               {$exitWhere}
             GROUP BY e.no_rawat
         ) final_stay
         GROUP BY final_stay.kd_bangsal",
        str_repeat('s', count($exitParams)),
        $exitParams
    );

    foreach ($exitRows as $exitRow) {
        $wardCode = $exitRow['kd_bangsal'];
        if (!isset($wards[$wardCode])) {
            continue;
        }
        $wards[$wardCode]['pasien_keluar'] += (int) $exitRow['pasien_keluar'];
        $wards[$wardCode]['lama_dirawat'] += (int) $exitRow['lama_dirawat'];
    }

    $flowWhere = '';
    if ($bangsalFilter !== '') {
        $flowWhere = ' AND k_scope.kd_bangsal = ?';
    }

    $initialParams = [$startDate, $startDate];
    if ($bangsalFilter !== '') {
        $initialParams[] = $bangsalFilter;
    }
    $initialRows = aptd_ranap_indicator_query(
        $mysqli,
        "SELECT COUNT(DISTINCT ki.no_rawat) AS jumlah
         FROM kamar_inap ki
         INNER JOIN kamar k_scope ON k_scope.kd_kamar = ki.kd_kamar
         WHERE ki.tgl_masuk < ?
           AND (
               (
                   ki.tgl_keluar IS NOT NULL
                   AND ki.tgl_keluar <> '0000-00-00'
                   AND ki.tgl_keluar >= ?
               )
               OR (
                   (ki.tgl_keluar IS NULL OR ki.tgl_keluar = '0000-00-00')
                   AND ki.stts_pulang = '-'
               )
           )
           AND k_scope.statusdata = '1'
           AND LOWER(TRIM(k_scope.kd_bangsal)) <> 'test'
           {$flowWhere}",
        str_repeat('s', count($initialParams)),
        $initialParams
    );

    $admissionParams = [$startDate, $endDate];
    if ($bangsalFilter !== '') {
        $admissionParams[] = $bangsalFilter;
    }
    $admissionRows = aptd_ranap_indicator_query(
        $mysqli,
        "SELECT COUNT(*) AS jumlah
         FROM (
             SELECT
                 ki.no_rawat,
                 MIN(ki.tgl_masuk) AS tgl_masuk_awal,
                 SUBSTRING_INDEX(
                     GROUP_CONCAT(ki.kd_kamar ORDER BY ki.tgl_masuk ASC, ki.jam_masuk ASC),
                     ',',
                     1
                 ) AS kamar_awal
             FROM kamar_inap ki
             GROUP BY ki.no_rawat
             HAVING tgl_masuk_awal >= ?
                AND tgl_masuk_awal < DATE_ADD(?, INTERVAL 1 DAY)
         ) admission
         INNER JOIN kamar k_scope ON k_scope.kd_kamar = admission.kamar_awal
         WHERE k_scope.statusdata = '1'
           AND LOWER(TRIM(k_scope.kd_bangsal)) <> 'test'
           {$flowWhere}",
        str_repeat('s', count($admissionParams)),
        $admissionParams
    );

    $transferParams = [$startDate, $endDate];
    if ($bangsalFilter !== '') {
        $transferParams[] = $bangsalFilter;
    }
    $transferRows = aptd_ranap_indicator_query(
        $mysqli,
        "SELECT COUNT(DISTINCT CONCAT_WS('|', next_stay.no_rawat, next_stay.tgl_masuk, next_stay.jam_masuk)) AS jumlah
         FROM kamar_inap previous_stay
         INNER JOIN kamar_inap next_stay
             ON next_stay.no_rawat = previous_stay.no_rawat
            AND next_stay.tgl_masuk = previous_stay.tgl_keluar
            AND next_stay.jam_masuk = previous_stay.jam_keluar
         INNER JOIN kamar k_scope ON k_scope.kd_kamar = next_stay.kd_kamar
         WHERE previous_stay.stts_pulang = 'Pindah Kamar'
           AND next_stay.tgl_masuk >= ?
           AND next_stay.tgl_masuk < DATE_ADD(?, INTERVAL 1 DAY)
           AND k_scope.statusdata = '1'
           AND LOWER(TRIM(k_scope.kd_bangsal)) <> 'test'
           {$flowWhere}",
        str_repeat('s', count($transferParams)),
        $transferParams
    );

    $totals = [
        'jumlah_tt' => 0,
        'hari_perawatan' => 0,
        'pasien_keluar' => 0,
        'lama_dirawat' => 0,
        'pasien_awal' => (int) $initialRows[0]['jumlah'],
        'pasien_masuk' => (int) $admissionRows[0]['jumlah'],
        'pasien_pindahan' => (int) $transferRows[0]['jumlah'],
        'hari_periode' => $periodDays,
        'hari_tersedia' => 0,
        'bor' => 0,
        'los' => 0,
        'toi' => 0,
        'bto' => 0,
    ];

    foreach ($wards as &$ward) {
        $ward['hari_tersedia'] = $ward['jumlah_tt'] * $periodDays;
        $ward['bor'] = $ward['hari_tersedia'] > 0
            ? ($ward['hari_perawatan'] / $ward['hari_tersedia']) * 100
            : 0;
        $ward['los'] = $ward['pasien_keluar'] > 0
            ? $ward['lama_dirawat'] / $ward['pasien_keluar']
            : 0;
        $ward['toi'] = $ward['pasien_keluar'] > 0
            ? ($ward['hari_tersedia'] - $ward['hari_perawatan']) / $ward['pasien_keluar']
            : 0;
        $ward['bto'] = $ward['jumlah_tt'] > 0
            ? $ward['pasien_keluar'] / $ward['jumlah_tt']
            : 0;

        $totals['jumlah_tt'] += $ward['jumlah_tt'];
        $totals['hari_perawatan'] += $ward['hari_perawatan'];
        $totals['pasien_keluar'] += $ward['pasien_keluar'];
        $totals['lama_dirawat'] += $ward['lama_dirawat'];
    }
    unset($ward);

    $totals['hari_tersedia'] = $totals['jumlah_tt'] * $periodDays;
    $totals['bor'] = $totals['hari_tersedia'] > 0
        ? ($totals['hari_perawatan'] / $totals['hari_tersedia']) * 100
        : 0;
    $totals['los'] = $totals['pasien_keluar'] > 0
        ? $totals['lama_dirawat'] / $totals['pasien_keluar']
        : 0;
    $totals['toi'] = $totals['pasien_keluar'] > 0
        ? ($totals['hari_tersedia'] - $totals['hari_perawatan']) / $totals['pasien_keluar']
        : 0;
    $totals['bto'] = $totals['jumlah_tt'] > 0
        ? $totals['pasien_keluar'] / $totals['jumlah_tt']
        : 0;

    uasort($wards, function ($left, $right) {
        return strcasecmp($left['nm_bangsal'], $right['nm_bangsal']);
    });

    return [
        'totals' => $totals,
        'wards' => array_values($wards),
        'classes' => array_values($classBreakdown),
        'validations' => [
            'hari_perawatan_gte_lama_dirawat' => $totals['hari_perawatan'] >= $totals['lama_dirawat'],
            'hari_perawatan_lte_kapasitas' => $totals['hari_perawatan'] <= $totals['hari_tersedia'],
            'lama_dirawat_gte_alur_pasien' => $totals['lama_dirawat']
                >= ($totals['pasien_awal'] + $totals['pasien_masuk'] + $totals['pasien_pindahan']),
        ],
    ];
}
