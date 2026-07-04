<?php
require_once dirname(dirname(__DIR__)) . '/t_non_klinis/kunjungan_igd_ponek_helper.php';

function aptd_igd_ponek_top_diseases($mysqli, $startDate, $endDate, $limit = 10)
{
    $limit = max(1, min(50, (int) $limit));
    $categories = aptd_igd_ponek_categories();
    $conditions = aptd_igd_ponek_condition_sql();
    $categoryCase = aptd_igd_ponek_category_case_sql($conditions);
    $categoryWhere = aptd_igd_ponek_category_where_sql($conditions);

    $sql = "SELECT
                selected.category_key,
                selected.kd_penyakit,
                py.nm_penyakit,
                COUNT(*) AS jumlah_kasus
            FROM (
                SELECT
                    visits.category_key,
                    dp.no_rawat,
                    SUBSTRING_INDEX(
                        GROUP_CONCAT(
                            DISTINCT dp.kd_penyakit
                            ORDER BY dp.kd_penyakit ASC
                        ),
                        ',',
                        1
                    ) AS kd_penyakit
                FROM (
                    SELECT
                        rp.no_rawat,
                        {$categoryCase} AS category_key
                    FROM reg_periksa rp
                    INNER JOIN pasien ps ON ps.no_rkm_medis = rp.no_rkm_medis
                    WHERE rp.tgl_registrasi >= ?
                      AND rp.tgl_registrasi < DATE_ADD(?, INTERVAL 1 DAY)
                      AND ({$categoryWhere})
                      AND LOWER(ps.nm_pasien) NOT LIKE '%test%'
                ) visits
                INNER JOIN diagnosa_pasien dp ON dp.no_rawat = visits.no_rawat
                WHERE dp.prioritas = 1
                GROUP BY visits.category_key, dp.no_rawat
            ) selected
            INNER JOIN penyakit py ON py.kd_penyakit = selected.kd_penyakit
            GROUP BY selected.category_key, selected.kd_penyakit, py.nm_penyakit
            ORDER BY selected.category_key ASC, jumlah_kasus DESC, selected.kd_penyakit ASC";

    $queryRows = aptd_igd_ponek_query($mysqli, $sql, 'ss', [$startDate, $endDate]);
    $rankings = [];
    foreach (array_keys($categories) as $key) {
        $rankings[$key] = [];
    }

    foreach ($queryRows as $row) {
        $key = $row['category_key'];
        if (!isset($rankings[$key]) || count($rankings[$key]) >= $limit) {
            continue;
        }
        $rankings[$key][] = [
            'kd_penyakit' => $row['kd_penyakit'],
            'nm_penyakit' => $row['nm_penyakit'],
            'jumlah_kasus' => (int) $row['jumlah_kasus'],
        ];
    }

    return $rankings;
}
