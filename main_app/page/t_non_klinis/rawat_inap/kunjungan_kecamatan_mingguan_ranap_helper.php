<?php
require_once dirname(__DIR__) . '/rawat_jalan/kunjungan_kecamatan_mingguan_ralan_helper.php';

function aptd_kec_mingguan_fetch_ranap($conn, $startDate, $endDate, array $weeks)
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
            WHERE rp.stts <> 'Batal'
                AND rp.tgl_registrasi BETWEEN ? AND ?
                AND EXISTS (
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

?>
