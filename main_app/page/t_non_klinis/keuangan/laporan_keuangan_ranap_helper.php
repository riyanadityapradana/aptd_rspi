<?php
require_once dirname(__DIR__) . '/report_helper.php';

function aptd_keu_ranap_date_filter($defaultEndToday = true)
{
    $today = date('Y-m-d');
    $defaultStart = date('Y-m-01');
    $filterBy = isset($_POST['filter_by']) ? $_POST['filter_by'] : (isset($_GET['filter_by']) ? $_GET['filter_by'] : 'masuk');
    $filterBy = aptd_keu_ranap_normalize_filter_by($filterBy);
    $hasDateRange = isset($_POST['start_date']) || isset($_POST['end_date']) || isset($_GET['start_date']) || isset($_GET['end_date']);
    $hasMonthYear = isset($_POST['month']) || isset($_POST['year']) || isset($_GET['month']) || isset($_GET['year']);

    if ($hasDateRange) {
        $startDate = isset($_POST['start_date']) ? $_POST['start_date'] : (isset($_GET['start_date']) ? $_GET['start_date'] : $defaultStart);
        $endDate = isset($_POST['end_date']) ? $_POST['end_date'] : (isset($_GET['end_date']) ? $_GET['end_date'] : $today);
    } else {
        $month = isset($_POST['month']) ? (int) $_POST['month'] : (isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n'));
        $year = isset($_POST['year']) ? (int) $_POST['year'] : (isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y'));

        if ($month < 1 || $month > 12) {
            $month = (int) date('n');
        }

        if ($year < 2020 || $year > ((int) date('Y') + 1)) {
            $year = (int) date('Y');
        }

        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = ($hasMonthYear || !$defaultEndToday) ? date('Y-m-t', strtotime($startDate)) : $today;
    }

    if (!aptd_keu_ranap_is_valid_date($startDate)) {
        $startDate = $defaultStart;
    }

    if (!aptd_keu_ranap_is_valid_date($endDate)) {
        $endDate = $today;
    }

    $month = (int) date('n', strtotime($startDate));
    $year = (int) date('Y', strtotime($startDate));
    $isValid = strtotime($endDate) >= strtotime($startDate);
    $message = $isValid ? '' : 'Tanggal Akhir tidak boleh lebih kecil dari Tanggal Awal.';

    return [$month, $year, $startDate, $endDate, $filterBy, $isValid, $message];
}

function aptd_keu_ranap_is_valid_date($date)
{
    $date = trim((string) $date);
    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date;
}

function aptd_keu_ranap_normalize_filter_by($filterBy)
{
    $filterBy = strtolower(trim((string) $filterBy));
    return $filterBy === 'keluar' ? 'keluar' : 'masuk';
}

function aptd_keu_ranap_filter_mode_label($filterBy)
{
    return aptd_keu_ranap_normalize_filter_by($filterBy) === 'keluar' ? 'Tanggal Keluar' : 'Tanggal Masuk';
}

function aptd_keu_ranap_filter_range_label($startDate, $endDate)
{
    return date('d-M-Y', strtotime($startDate)) . ' s.d. ' . date('d-M-Y', strtotime($endDate));
}

function aptd_keu_ranap_filter_info_label($startDate, $endDate, $filterBy)
{
    return 'Menampilkan data berdasarkan ' . aptd_keu_ranap_filter_mode_label($filterBy) . ': ' . aptd_keu_ranap_filter_range_label($startDate, $endDate);
}

function aptd_keu_ranap_filter_query($startDate, $endDate, $filterBy)
{
    return 'start_date=' . rawurlencode($startDate) . '&end_date=' . rawurlencode($endDate) . '&filter_by=' . rawurlencode(aptd_keu_ranap_normalize_filter_by($filterBy));
}

function aptd_keu_ranap_export_url($startDate, $endDate = null, $filterBy = 'masuk')
{
    if (is_numeric($startDate) && is_numeric($endDate)) {
        $month = (int) $startDate;
        $year = (int) $endDate;
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));
    } elseif ($endDate === null) {
        $month = (int) $startDate;
        $year = (int) date('Y');
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));
    }

    return 'page/t_non_klinis/keuangan/export_laporan_keuangan_ranap.php?' . aptd_keu_ranap_filter_query($startDate, $endDate, $filterBy);
}

function aptd_keu_ranap_date_where_sql($filterBy)
{
    if (aptd_keu_ranap_normalize_filter_by($filterBy) === 'keluar') {
        return "
          EXISTS (
              SELECT 1
              FROM kamar_inap kif
              WHERE kif.no_rawat = rp.no_rawat
                AND NULLIF(kif.tgl_keluar, '0000-00-00') BETWEEN ? AND ?
                AND kif.stts_pulang <> '-'
                AND kif.stts_pulang <> 'Pindah Kamar'
          )";
    }

    return "rp.tgl_registrasi BETWEEN ? AND ?";
}

function aptd_keu_ranap_date_filter_literal(mysqli $mysqli, $startDate, $endDate, $filterBy)
{
    $startDate = $mysqli->real_escape_string($startDate);
    $endDate = $mysqli->real_escape_string($endDate);

    if (aptd_keu_ranap_normalize_filter_by($filterBy) === 'keluar') {
        return "
          EXISTS (
              SELECT 1
              FROM kamar_inap kif
              WHERE kif.no_rawat = rp.no_rawat
                AND NULLIF(kif.tgl_keluar, '0000-00-00') BETWEEN '$startDate' AND '$endDate'
                AND kif.stts_pulang <> '-'
                AND kif.stts_pulang <> 'Pindah Kamar'
          )";
    }

    return "rp.tgl_registrasi BETWEEN '$startDate' AND '$endDate'";
}

function aptd_keu_ranap_inacbg_tariff_sql()
{
    return "
        SELECT igr.no_rawat,
               CAST(TRIM(igr.tariff) AS DECIMAL(16,2)) AS tariff,
               IFNULL(igr.datetime, '1000-01-01 00:00:00') AS tariff_datetime,
               IFNULL(igr.no_sep, '') AS tariff_no_sep
        FROM tb_inacbg_grouping_result igr
        LEFT JOIN tb_inacbg_grouping_result newer
          ON newer.no_rawat = igr.no_rawat
         AND TRIM(IFNULL(newer.tariff, '')) REGEXP '^[0-9]+([.][0-9]+)?$'
         AND CAST(TRIM(newer.tariff) AS DECIMAL(16,2)) > 0
         AND (
                IFNULL(newer.datetime, '1000-01-01 00:00:00') > IFNULL(igr.datetime, '1000-01-01 00:00:00')
             OR (
                    IFNULL(newer.datetime, '1000-01-01 00:00:00') = IFNULL(igr.datetime, '1000-01-01 00:00:00')
                AND newer.no_sep > igr.no_sep
             )
         )
        WHERE TRIM(IFNULL(igr.tariff, '')) REGEXP '^[0-9]+([.][0-9]+)?$'
          AND CAST(TRIM(igr.tariff) AS DECIMAL(16,2)) > 0
          AND newer.no_rawat IS NULL
    ";
}

function aptd_keu_ranap_history_claim_sql()
{
    $inacbgSql = aptd_keu_ranap_inacbg_tariff_sql();
    $historyBaseSql = "
        SELECT dp.no_rawat,
               dp.kd_penyakit AS code,
               tariff.tariff,
               tariff.tariff_datetime,
               tariff.tariff_no_sep
        FROM diagnosa_pasien dp
        INNER JOIN ($inacbgSql) tariff ON tariff.no_rawat = dp.no_rawat
        WHERE dp.prioritas = 1
          AND dp.status = 'Ranap'
          AND TRIM(IFNULL(dp.kd_penyakit, '')) <> ''
    ";

    return "
        SELECT current_diag.no_rawat,
               current_diag.kode_icd AS claim_history_diagnose_code,
               history_pick.no_rawat AS claim_history_no_rawat,
               history_pick.tariff AS claim_history
        FROM (
            SELECT rp.no_rawat,
                   COALESCE(
                       MAX(NULLIF(TRIM(dp_current.kd_penyakit), '')),
                       MAX(NULLIF(TRIM(ds_current.kode_icd), ''))
                   ) AS kode_icd
            FROM reg_periksa rp
            LEFT JOIN diagnosa_pasien dp_current
              ON dp_current.no_rawat = rp.no_rawat
             AND dp_current.prioritas = 1
             AND dp_current.status = 'Ranap'
            LEFT JOIN diagnosa_sementara ds_current
              ON ds_current.nomor_rawat = rp.no_rawat
            WHERE rp.status_lanjut = 'Ranap'
            GROUP BY rp.no_rawat
            HAVING kode_icd IS NOT NULL AND kode_icd <> ''
        ) current_diag
        INNER JOIN ($historyBaseSql) history_pick
            ON history_pick.code = current_diag.kode_icd
           AND history_pick.no_rawat <> current_diag.no_rawat
        LEFT JOIN ($historyBaseSql) newer
            ON newer.code = current_diag.kode_icd
           AND newer.no_rawat <> current_diag.no_rawat
           AND (
                newer.tariff_datetime > history_pick.tariff_datetime
             OR (
                    newer.tariff_datetime = history_pick.tariff_datetime
                AND newer.no_rawat > history_pick.no_rawat
             )
           )
        WHERE newer.no_rawat IS NULL
    ";
}

function aptd_keu_ranap_claim_select_sql()
{
    return "
        CASE
            WHEN COALESCE(manual.claim_selected, 0) > 0 THEN manual.claim_selected
            WHEN COALESCE(manual.jum_claim, 0) > 0 THEN manual.jum_claim
            WHEN COALESCE(inacbg.tariff, 0) > 0 THEN inacbg.tariff
            ELSE 0
        END
    ";
}

function aptd_keu_ranap_claim_source_sql()
{
    return "
        CASE
            WHEN COALESCE(manual.claim_selected, 0) > 0 AND IFNULL(manual.claim_source, '') <> '' THEN manual.claim_source
            WHEN COALESCE(manual.claim_selected, 0) > 0 THEN 'manual'
            WHEN COALESCE(manual.jum_claim, 0) > 0 THEN 'manual'
            WHEN COALESCE(inacbg.tariff, 0) > 0 THEN 'inacbg_current'
            ELSE 'none'
        END
    ";
}

function aptd_keu_ranap_claim_source_label($source, $claimUsed = 0, $claimHistory = 0)
{
    $source = trim((string) $source);
    if ($source === 'manual') {
        return 'Manual Keuangan';
    }
    if ($source === 'inacbg_current') {
        return 'Aktual INA-CBG';
    }
    if ($source === 'history_diagnose') {
        return 'Riwayat Diagnosa';
    }
    if ((float) $claimUsed <= 0 && (float) $claimHistory > 0) {
        return 'Perlu Review';
    }
    return 'Belum Ada';
}

function aptd_keu_ranap_apply_history_claims(mysqli $mysqli, array &$rows)
{
    if (empty($rows)) {
        return;
    }

    $codes = [];
    foreach ($rows as $index => $row) {
        $rows[$index]['claim_history'] = isset($row['claim_history']) ? (float) $row['claim_history'] : 0;
        $rows[$index]['claim_history_no_rawat'] = isset($row['claim_history_no_rawat']) ? (string) $row['claim_history_no_rawat'] : '';
        $code = isset($row['claim_history_diagnose_code']) ? trim((string) $row['claim_history_diagnose_code']) : '';
        if ($code !== '') {
            $codes[$code] = true;
        } else {
            $rows[$index]['claim_history'] = 0;
            $rows[$index]['claim_history_no_rawat'] = '';
            $rows[$index]['claim_source_label'] = aptd_keu_ranap_claim_source_label(
                isset($row['claim_source']) ? $row['claim_source'] : '',
                isset($row['claim']) ? (float) $row['claim'] : 0,
                0
            );
        }
    }

    if (empty($codes)) {
        return;
    }

    $escapedCodes = [];
    foreach (array_keys($codes) as $code) {
        $escapedCodes[] = "'" . $mysqli->real_escape_string($code) . "'";
    }

    $inacbgSql = aptd_keu_ranap_inacbg_tariff_sql();
    $sql = "
        SELECT dp.kd_penyakit AS code,
               dp.no_rawat,
               tariff.tariff,
               tariff.tariff_datetime
        FROM diagnosa_pasien dp
        INNER JOIN ($inacbgSql) tariff ON tariff.no_rawat = dp.no_rawat
        WHERE dp.prioritas = 1
          AND dp.status = 'Ranap'
          AND TRIM(IFNULL(dp.kd_penyakit, '')) <> ''
          AND dp.kd_penyakit IN (" . implode(',', $escapedCodes) . ")
        ORDER BY dp.kd_penyakit ASC, tariff.tariff_datetime DESC, dp.no_rawat DESC";

    $candidates = [];
    $result = $mysqli->query($sql);
    while ($history = $result->fetch_assoc()) {
        $code = (string) $history['code'];
        if (!isset($candidates[$code])) {
            $candidates[$code] = [];
        }
        $candidates[$code][] = [
            'no_rawat' => (string) $history['no_rawat'],
            'tariff' => (float) $history['tariff'],
        ];
    }

    foreach ($rows as $index => $row) {
        $code = isset($row['claim_history_diagnose_code']) ? trim((string) $row['claim_history_diagnose_code']) : '';
        $currentNoRawat = isset($row['no_rawat']) ? (string) $row['no_rawat'] : '';
        if ($code === '' || empty($candidates[$code])) {
            $rows[$index]['claim_source_label'] = aptd_keu_ranap_claim_source_label(
                isset($row['claim_source']) ? $row['claim_source'] : '',
                isset($row['claim']) ? (float) $row['claim'] : 0,
                isset($rows[$index]['claim_history']) ? (float) $rows[$index]['claim_history'] : 0
            );
            continue;
        }

        foreach ($candidates[$code] as $candidate) {
            if ($candidate['no_rawat'] === $currentNoRawat || $candidate['tariff'] <= 0) {
                continue;
            }

            $rows[$index]['claim_history'] = $candidate['tariff'];
            $rows[$index]['claim_history_no_rawat'] = $candidate['no_rawat'];
            break;
        }

        $rows[$index]['claim_source_label'] = aptd_keu_ranap_claim_source_label(
            isset($row['claim_source']) ? $row['claim_source'] : '',
            isset($row['claim']) ? (float) $row['claim'] : 0,
            isset($rows[$index]['claim_history']) ? (float) $rows[$index]['claim_history'] : 0
        );
    }
}

function aptd_keu_ranap_fetch_claim_rows(mysqli $mysqli, $startDate, $endDate, $filterBy = 'masuk')
{
    aptd_keu_ranap_ensure_cache_schema($mysqli);
    $inacbgSql = aptd_keu_ranap_inacbg_tariff_sql();
    $claimSelectSql = aptd_keu_ranap_claim_select_sql();
    $dateWhereSql = aptd_keu_ranap_date_where_sql($filterBy);
    $orderSql = aptd_keu_ranap_normalize_filter_by($filterBy) === 'keluar' ? 'tanggal_keluar ASC, rp.no_rawat ASC' : 'tanggal_masuk ASC, rp.no_rawat ASC';
    $sql = "
        SELECT
            rp.no_rawat,
            rp.no_rkm_medis,
            CONCAT(p.nm_pasien, ' (', rp.umurdaftar, ' ', rp.sttsumur, ')') AS nama_pasien_umur,
            MAX(NULLIF(ki.diagnosa_awal, '')) AS diagnosa_awal,
            MAX(NULLIF(ki.diagnosa_akhir, '')) AS diagnosa_akhir,
            MIN(ki.tgl_masuk) AS tanggal_masuk,
            MAX(NULLIF(ki.tgl_keluar, '0000-00-00')) AS tanggal_keluar,
            MAX(IFNULL(ki.stts_pulang, '-')) AS status_pulang,
            COALESCE(
                MAX(NULLIF(sep_dokter.nm_dokter, '')),
                MAX(NULLIF(sd_dokter.nm_dokter, '')),
                MAX(NULLIF(bs.nmdpjplayanan, '')),
                MAX(NULLIF(bs.nmdpdjp, '')),
                MAX(NULLIF(reg_dpjp.nm_dokter, ''))
            ) AS dpjp,
            $claimSelectSql AS claim,
            COALESCE(manual.claim_selected, 0) AS claim_selected,
            COALESCE(inacbg.tariff, 0) AS claim_actual,
            COALESCE(manual.claim_history, 0) AS claim_history,
            COALESCE(manual.claim_history_no_rawat, '') AS claim_history_no_rawat,
            COALESCE(
                MAX(NULLIF(TRIM(dp_current.kd_penyakit), '')),
                MAX(NULLIF(TRIM(ds_current.kode_icd), ''))
            ) AS claim_history_diagnose_code
        FROM kamar_inap ki
        INNER JOIN reg_periksa rp ON rp.no_rawat = ki.no_rawat
        INNER JOIN pasien p ON p.no_rkm_medis = rp.no_rkm_medis
        LEFT JOIN status_dpjp sd ON sd.no_rawat = rp.no_rawat
        LEFT JOIN dokter sd_dokter ON sd_dokter.kd_dokter = sd.kd_dokter AND sd_dokter.status = '1'
        LEFT JOIN dokter reg_dpjp ON reg_dpjp.kd_dokter = rp.kd_dokter
        LEFT JOIN bridging_sep bs ON bs.no_rawat = rp.no_rawat
        LEFT JOIN maping_dokter_dpjpvclaim mdpjp ON mdpjp.kd_dokter_bpjs = NULLIF(bs.kddpjp, '')
        LEFT JOIN dokter sep_dokter ON sep_dokter.kd_dokter = mdpjp.kd_dokter AND sep_dokter.status = '1'
        LEFT JOIN diagnosa_pasien dp_current
          ON dp_current.no_rawat = rp.no_rawat
         AND dp_current.prioritas = 1
         AND dp_current.status = 'Ranap'
        LEFT JOIN diagnosa_sementara ds_current ON ds_current.nomor_rawat = rp.no_rawat
        LEFT JOIN (
            SELECT no_rawat,
                   MAX(jum_claim) AS jum_claim,
                   MAX(claim_selected) AS claim_selected,
                   MAX(claim_source) AS claim_source,
                   MAX(claim_history) AS claim_history,
                   MAX(claim_history_no_rawat) AS claim_history_no_rawat
            FROM lap_keuangan_bpjs
            GROUP BY no_rawat
        ) manual ON manual.no_rawat = rp.no_rawat
        LEFT JOIN ($inacbgSql) inacbg ON inacbg.no_rawat = rp.no_rawat
        WHERE $dateWhereSql
          AND rp.status_lanjut = 'Ranap'
          AND rp.kd_pj = 'BPJ'
          AND (ki.stts_pulang IS NULL OR ki.stts_pulang = '-' OR ki.stts_pulang <> 'Pindah Kamar')
        GROUP BY rp.no_rawat, rp.no_rkm_medis, p.nm_pasien, rp.umurdaftar, rp.sttsumur, manual.jum_claim, manual.claim_selected, manual.claim_history, manual.claim_history_no_rawat, inacbg.tariff
        ORDER BY $orderSql";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    aptd_keu_ranap_apply_history_claims($mysqli, $rows);
    return $rows;
}

function aptd_keu_ranap_fetch_rows(mysqli $mysqli, $startDate, $endDate, $onlyNoRawat = '', $filterBy = 'masuk')
{
    $mysqli->query('SET SESSION group_concat_max_len = 1048576');
    $onlyNoRawat = trim((string) $onlyNoRawat);
    $filterSql = aptd_keu_ranap_filter_sql($mysqli, $startDate, $endDate, $onlyNoRawat, $filterBy);
    $inacbgSql = aptd_keu_ranap_inacbg_tariff_sql();
    $claimSelectSql = aptd_keu_ranap_claim_select_sql();
    $claimSourceSql = aptd_keu_ranap_claim_source_sql();
    $singleFilterSql = $onlyNoRawat !== '' ? " AND rp.no_rawat = ?" : "";
    $dateWhereSql = aptd_keu_ranap_date_where_sql($filterBy);
    $orderSql = aptd_keu_ranap_normalize_filter_by($filterBy) === 'keluar' ? 'tanggal_keluar ASC, rp.no_rawat ASC' : 'tanggal_masuk ASC, rp.no_rawat ASC';

    $sql = "
        SELECT
            rp.no_rawat,
            rp.no_rkm_medis,
            p.nm_pasien,
            CONCAT(p.nm_pasien, ' (', rp.umurdaftar, ' ', rp.sttsumur, ')') AS nama_pasien_umur,
            MAX(NULLIF(ki.diagnosa_awal, '')) AS diagnosa_awal,
            MAX(NULLIF(ki.diagnosa_akhir, '')) AS diagnosa_akhir,
            MIN(ki.tgl_masuk) AS tanggal_masuk,
            MAX(NULLIF(ki.tgl_keluar, '0000-00-00')) AS tanggal_keluar,
            MAX(IFNULL(ki.stts_pulang, '-')) AS status_pulang,
            COALESCE(
                MAX(NULLIF(sep_dokter.nm_dokter, '')),
                MAX(NULLIF(sd_dokter.nm_dokter, '')),
                MAX(NULLIF(bs.nmdpjplayanan, '')),
                MAX(NULLIF(bs.nmdpdjp, '')),
                MAX(NULLIF(reg_dpjp.nm_dokter, ''))
            ) AS dpjp,
            COALESCE(MAX(NULLIF(sep_dokter.kd_dokter, '')), MAX(NULLIF(sd_dokter.kd_dokter, ''))) AS kd_dokter_dpjp_status,
            COALESCE(MAX(NULLIF(mdpjp.kd_dokter_bpjs, '')), MAX(NULLIF(bs.kddpjp, ''))) AS kd_dokter_bpjs_dpjp,
            MAX(NULLIF(mdpjp.kd_dokter, '')) AS kd_dokter_mapping_dpjp,
            MAX(sep_dokter_all.status) AS status_dokter_dpjp_sep,
            COALESCE(MAX(NULLIF(sep_dokter.kd_sps, '')), MAX(NULLIF(sd_dokter.kd_sps, ''))) AS kd_sps_dpjp,
            COALESCE(MAX(NULLIF(sep_sps.nm_sps, '')), MAX(NULLIF(sps_dpjp.nm_sps, ''))) AS nm_sps_dpjp,
            CASE
                WHEN MAX(NULLIF(sep_dokter.kd_dokter, '')) IS NOT NULL THEN 'bridging_sep'
                WHEN MAX(NULLIF(sd_dokter.kd_dokter, '')) IS NOT NULL THEN 'status_dpjp'
                ELSE ''
            END AS dpjp_source,
            GROUP_CONCAT(DISTINCT ki.kd_kamar ORDER BY ki.tgl_masuk, ki.jam_masuk SEPARATOR ', ') AS kamar,
            MAX(IFNULL(bs.no_sep, '')) AS no_sep,
            MAX(IFNULL(bs.nmdiagnosaawal, '')) AS diagnosa_sep,
            $claimSelectSql AS claim,
            COALESCE(manual.jum_claim, 0) AS manual_claim,
            COALESCE(manual.claim_selected, 0) AS claim_selected_raw,
            COALESCE(inacbg.tariff, 0) AS claim_actual,
            COALESCE(manual.claim_history, 0) AS claim_history,
            COALESCE(manual.claim_history_no_rawat, '') AS claim_history_no_rawat,
            COALESCE(
                MAX(NULLIF(TRIM(dp_current.kd_penyakit), '')),
                MAX(NULLIF(TRIM(ds_current.kode_icd), ''))
            ) AS claim_history_diagnose_code,
            $claimSourceSql AS claim_source,
            COALESCE(manual.jum_jdoperator, 0) AS manual_jd_operator,
            COALESCE(ugd.dokter_ugd, 0) AS dokter_ugd,
            COALESCE(visit.visit_items, '') AS visit_items,
            COALESCE(telp.telp_items, '') AS telp_items,
            COALESCE(usg.jd_usg, 0) AS jd_usg,
            COALESCE(rad.jd_rontgen, 0) AS jd_rontgen,
            COALESCE(lab.jd_lab, 0) AS jd_lab,
            COALESCE(lab.jd_pa, 0) AS jd_pa,
            CASE
                WHEN COALESCE(MAX(NULLIF(sep_dokter.kd_dokter, '')), MAX(NULLIF(sd_dokter.kd_dokter, ''))) = '023.120813' THEN 0
                WHEN COALESCE(hd.hd_count, 0) <= 0 THEN 0
                ELSE 150000 + ((COALESCE(hd.hd_count, 0) - 1) * 100000)
            END AS hd,
            ($claimSelectSql * 0.15) AS jk,
            COALESCE(bhp.bhp, 0) AS bhp,
            COALESCE(obat.total_harga_dasar_obat, 0) AS total_harga_dasar_obat,
            COALESCE(obat.markup_obat_bhp, 0) AS markup_obat_bhp,
            COALESCE(obat.obat, 0) AS obat,
            COALESCE(lab.lab_pk, 0) AS lab_pk,
            COALESCE(lab.lab_pa, 0) AS lab_pa,
            COALESCE(usg.rad_usg, 0) AS rad_usg,
            COALESCE(rad.rontgen, 0) AS rontgen,
            COALESCE(fisio.fisio_items, '') AS fisio_items,
            COALESCE(ekg.ekg, 0) AS ekg,
            COALESCE(ekg.count_ekg, 0) AS count_ekg,
            COALESCE(darah.darah, 0) AS darah,
            COALESCE(darah.jumlah_darah, 0) AS jumlah_darah,
            COALESCE(makan.makan_jumlah, 0) AS makan_jumlah,
            COALESCE(makan.makan_harga, 0) AS makan_harga,
            COALESCE(makan.makan_kali, 0) AS makan_kali,
            COALESCE(bhp_penunjang.phototherapy, 0) AS phototherapy,
            COALESCE(oksigen.oksigen, 0) AS oksigen,
            COALESCE(bhp_penunjang.spirometri, 0) AS spirometri,
            COALESCE(albumin.jumlah_albumin, 0) AS jumlah_albumin,
            COALESCE(manual.jum_jdoperator, 0) AS jd_operator,
            COALESCE(ok.jd_anestesi, 0) AS jd_anestesi,
            COALESCE(ok.jd_anak, 0) AS jd_anak,
            COALESCE(ok.jd_dokter_umum, 0) AS jd_dokter_umum,
            COALESCE(ok.has_operasi, 0) AS has_operasi,
            COALESCE(ok.has_partus, 0) AS has_partus,
            COALESCE(ok.has_phaco, 0) AS has_phaco,
            COALESCE(ok.has_phaco_anestesi, 0) AS has_phaco_anestesi,
            COALESCE(ok.has_phaco_tanpa_anestesi, 0) AS has_phaco_tanpa_anestesi,
            COALESCE(ok.operator1_codes, '') AS operator1_codes,
            COALESCE(ok.operator1_names, '') AS operator1_names,
            COALESCE(ok.has_jd_anak_sc, 0) AS has_jd_anak_sc,
            COALESCE(ok.has_jd_anak_partus, 0) AS has_jd_anak_partus,
            COALESCE(ok.jd_anak_package_names, '') AS jd_anak_package_names,
            COALESCE(ok.tindakan_operasi, '') AS tindakan_operasi
        FROM kamar_inap ki
        INNER JOIN reg_periksa rp ON rp.no_rawat = ki.no_rawat
        INNER JOIN pasien p ON p.no_rkm_medis = rp.no_rkm_medis
        LEFT JOIN status_dpjp sd ON sd.no_rawat = rp.no_rawat
        LEFT JOIN dokter sd_dokter ON sd_dokter.kd_dokter = sd.kd_dokter AND sd_dokter.status = '1'
        LEFT JOIN spesialis sps_dpjp ON sps_dpjp.kd_sps = sd_dokter.kd_sps
        LEFT JOIN dokter reg_dpjp ON reg_dpjp.kd_dokter = rp.kd_dokter
        LEFT JOIN bridging_sep bs ON bs.no_rawat = rp.no_rawat
        LEFT JOIN maping_dokter_dpjpvclaim mdpjp ON mdpjp.kd_dokter_bpjs = NULLIF(bs.kddpjp, '')
        LEFT JOIN dokter sep_dokter_all ON sep_dokter_all.kd_dokter = mdpjp.kd_dokter
        LEFT JOIN dokter sep_dokter ON sep_dokter.kd_dokter = mdpjp.kd_dokter AND sep_dokter.status = '1'
        LEFT JOIN spesialis sep_sps ON sep_sps.kd_sps = sep_dokter.kd_sps
        LEFT JOIN diagnosa_pasien dp_current
          ON dp_current.no_rawat = rp.no_rawat
         AND dp_current.prioritas = 1
         AND dp_current.status = 'Ranap'
        LEFT JOIN diagnosa_sementara ds_current ON ds_current.nomor_rawat = rp.no_rawat
        LEFT JOIN (
            SELECT no_rawat,
                   MAX(jum_claim) AS jum_claim,
                   MAX(jum_jdoperator) AS jum_jdoperator,
                   MAX(claim_selected) AS claim_selected,
                   MAX(claim_source) AS claim_source,
                   MAX(claim_history) AS claim_history,
                   MAX(claim_history_no_rawat) AS claim_history_no_rawat
            FROM lap_keuangan_bpjs
            GROUP BY no_rawat
        ) manual ON manual.no_rawat = rp.no_rawat
        LEFT JOIN ($inacbgSql) inacbg ON inacbg.no_rawat = rp.no_rawat
        LEFT JOIN (
            SELECT rid.no_rawat, SUM(rid.tarif_tindakandr) AS dokter_ugd
            FROM rawat_inap_dr rid
            INNER JOIN ($filterSql) f ON f.no_rawat = rid.no_rawat
            INNER JOIN jns_perawatan_inap jpi ON jpi.kd_jenis_prw = rid.kd_jenis_prw
            WHERE rid.kd_jenis_prw LIKE '%000.13%'
            GROUP BY rid.no_rawat
        ) ugd ON ugd.no_rawat = rp.no_rawat
        LEFT JOIN (
            SELECT rid.no_rawat,
                   GROUP_CONCAT(
                       CONCAT_WS('~',
                           rid.kd_dokter,
                           IFNULL(d_visit.kd_sps, ''),
                           rid.tarif_tindakandr,
                           rid.tgl_perawatan,
                           rid.jam_rawat,
                           REPLACE(REPLACE(jpi.nm_perawatan, '~', ' '), '|', ' ')
                       )
                       ORDER BY rid.tgl_perawatan ASC, rid.jam_rawat ASC
                       SEPARATOR '|'
                   ) AS visit_items
            FROM rawat_inap_dr rid
            INNER JOIN ($filterSql) f ON f.no_rawat = rid.no_rawat
            INNER JOIN jns_perawatan_inap jpi ON jpi.kd_jenis_prw = rid.kd_jenis_prw
            LEFT JOIN dokter d_visit ON d_visit.kd_dokter = rid.kd_dokter
            WHERE jpi.nm_perawatan LIKE '%Visite Dokter Spesialis%'
               OR jpi.nm_perawatan LIKE '%Visite Dokter Umum%'
            GROUP BY rid.no_rawat
        ) visit ON visit.no_rawat = rp.no_rawat
        LEFT JOIN (
            SELECT rid.no_rawat,
                   GROUP_CONCAT(
                       CONCAT_WS('~',
                           rid.kd_dokter,
                           IFNULL(d_telp.kd_sps, ''),
                           rid.tarif_tindakandr,
                           rid.tgl_perawatan,
                           rid.jam_rawat,
                           REPLACE(REPLACE(jpi.nm_perawatan, '~', ' '), '|', ' ')
                       )
                       ORDER BY rid.tgl_perawatan ASC, rid.jam_rawat ASC
                       SEPARATOR '|'
                   ) AS telp_items
            FROM rawat_inap_dr rid
            INNER JOIN ($filterSql) f ON f.no_rawat = rid.no_rawat
            INNER JOIN jns_perawatan_inap jpi ON jpi.kd_jenis_prw = rid.kd_jenis_prw
            LEFT JOIN dokter d_telp ON d_telp.kd_dokter = rid.kd_dokter
            WHERE jpi.nm_perawatan LIKE '%Telpon%'
               OR jpi.nm_perawatan LIKE '%Telepon%'
            GROUP BY rid.no_rawat
        ) telp ON telp.no_rawat = rp.no_rawat
        LEFT JOIN (
            SELECT pr.no_rawat,
                   SUM(pr.tarif_tindakan_dokter) AS jd_usg,
                   SUM(pr.bhp) AS rad_usg
            FROM periksa_radiologi pr
            INNER JOIN ($filterSql) f ON f.no_rawat = pr.no_rawat
            INNER JOIN jns_perawatan_radiologi jpr ON jpr.kd_jenis_prw = pr.kd_jenis_prw
            WHERE jpr.nm_perawatan LIKE '%USG%'
            GROUP BY pr.no_rawat
        ) usg ON usg.no_rawat = rp.no_rawat
        LEFT JOIN (
            SELECT pr.no_rawat,
                   SUM(pr.tarif_tindakan_dokter) AS jd_rontgen,
                   SUM(pr.bhp) AS rontgen
            FROM periksa_radiologi pr
            INNER JOIN ($filterSql) f ON f.no_rawat = pr.no_rawat
            INNER JOIN jns_perawatan_radiologi jpr ON jpr.kd_jenis_prw = pr.kd_jenis_prw
            WHERE jpr.nm_perawatan NOT LIKE '%USG%'
            GROUP BY pr.no_rawat
        ) rad ON rad.no_rawat = rp.no_rawat
        LEFT JOIN (
            SELECT pl.no_rawat,
                   (SUM(pl.biaya) + COALESCE(MAX(dpl.biaya_item), 0)) * 0.07 AS jd_lab,
                   SUM(CASE WHEN UPPER(pl.kategori) = 'PA' THEN pl.tarif_tindakan_dokter ELSE 0 END) AS jd_pa,
                   SUM(CASE WHEN UPPER(pl.kategori) = 'PK' THEN pl.bhp ELSE 0 END) + COALESCE(MAX(dpl_pk.bhp_pk), 0) AS lab_pk,
                   SUM(CASE WHEN UPPER(pl.kategori) = 'PA' THEN pl.bhp ELSE 0 END) AS lab_pa
            FROM periksa_lab pl
            INNER JOIN ($filterSql) f ON f.no_rawat = pl.no_rawat
            LEFT JOIN (
                SELECT no_rawat, SUM(biaya_item) AS biaya_item
                FROM detail_periksa_lab
                GROUP BY no_rawat
            ) dpl ON dpl.no_rawat = pl.no_rawat
            LEFT JOIN (
                SELECT dpl.no_rawat,
                       SUM(dpl.bhp) AS bhp_pk
                FROM detail_periksa_lab dpl
                INNER JOIN periksa_lab pl_pk ON pl_pk.no_rawat = dpl.no_rawat
                    AND pl_pk.kd_jenis_prw = dpl.kd_jenis_prw
                    AND pl_pk.tgl_periksa = dpl.tgl_periksa
                    AND pl_pk.jam = dpl.jam
                INNER JOIN ($filterSql) f ON f.no_rawat = dpl.no_rawat
                WHERE UPPER(pl_pk.kategori) = 'PK'
                GROUP BY dpl.no_rawat
            ) dpl_pk ON dpl_pk.no_rawat = pl.no_rawat
            GROUP BY pl.no_rawat
        ) lab ON lab.no_rawat = rp.no_rawat
        LEFT JOIN (
            SELECT dpo.no_rawat,
                   SUM(COALESCE(db.dasar, 0) * dpo.jml) AS total_harga_dasar_obat,
                   SUM(COALESCE(db.dasar, 0) * dpo.jml) * 0.15 AS markup_obat_bhp,
                   SUM(COALESCE(db.dasar, 0) * dpo.jml) * 1.15 AS obat
            FROM detail_pemberian_obat dpo
            INNER JOIN ($filterSql) f ON f.no_rawat = dpo.no_rawat
            LEFT JOIN databarang db ON db.kode_brng = dpo.kode_brng
            GROUP BY dpo.no_rawat
        ) obat ON obat.no_rawat = rp.no_rawat
        LEFT JOIN (
            SELECT bhp_total.no_rawat, SUM(bhp_total.nilai_bhp) AS bhp
            FROM (
                SELECT rid.no_rawat,
                       SUM(CASE WHEN IFNULL(rid.bhp, 0) > 0 THEN rid.bhp ELSE IFNULL(jpi.bhp, 0) END) AS nilai_bhp
                FROM rawat_inap_dr rid
                INNER JOIN ($filterSql) f ON f.no_rawat = rid.no_rawat
                INNER JOIN jns_perawatan_inap jpi ON jpi.kd_jenis_prw = rid.kd_jenis_prw
                WHERE jpi.nm_perawatan LIKE '%BHP%'
                GROUP BY rid.no_rawat
                UNION ALL
                SELECT rip.no_rawat,
                       SUM(CASE WHEN IFNULL(rip.bhp, 0) > 0 THEN rip.bhp ELSE IFNULL(jpi.bhp, 0) END) AS nilai_bhp
                FROM rawat_inap_pr rip
                INNER JOIN ($filterSql) f ON f.no_rawat = rip.no_rawat
                INNER JOIN jns_perawatan_inap jpi ON jpi.kd_jenis_prw = rip.kd_jenis_prw
                WHERE jpi.nm_perawatan LIKE '%BHP%'
                GROUP BY rip.no_rawat
            ) bhp_total
            GROUP BY bhp_total.no_rawat
        ) bhp ON bhp.no_rawat = rp.no_rawat
        LEFT JOIN (
            SELECT fisio_raw.no_rawat,
                   GROUP_CONCAT(
                       CONCAT_WS('~',
                           fisio_raw.nilai_fisio,
                           fisio_raw.tgl_perawatan,
                           fisio_raw.jam_rawat,
                           REPLACE(REPLACE(fisio_raw.nm_perawatan, '~', ' '), '|', ' ')
                       )
                       ORDER BY fisio_raw.tgl_perawatan ASC, fisio_raw.jam_rawat ASC, fisio_raw.source_order ASC
                       SEPARATOR '|'
                   ) AS fisio_items
            FROM (
                SELECT rid.no_rawat,
                       CASE WHEN IFNULL(rid.tarif_tindakandr, 0) > 0 THEN rid.tarif_tindakandr ELSE IFNULL(jpi.tarif_tindakandr, 0) END AS nilai_fisio,
                       rid.tgl_perawatan,
                       rid.jam_rawat,
                       jpi.nm_perawatan,
                       1 AS source_order
                FROM rawat_inap_dr rid
                INNER JOIN ($filterSql) f ON f.no_rawat = rid.no_rawat
                INNER JOIN jns_perawatan_inap jpi ON jpi.kd_jenis_prw = rid.kd_jenis_prw
                WHERE jpi.nm_perawatan LIKE '%fisio%'
                UNION ALL
                SELECT rip.no_rawat,
                       IFNULL(jpi.tarif_tindakandr, 0) AS nilai_fisio,
                       rip.tgl_perawatan,
                       rip.jam_rawat,
                       jpi.nm_perawatan,
                       2 AS source_order
                 FROM rawat_inap_pr rip
                 INNER JOIN ($filterSql) f ON f.no_rawat = rip.no_rawat
                 INNER JOIN jns_perawatan_inap jpi ON jpi.kd_jenis_prw = rip.kd_jenis_prw
                 WHERE jpi.nm_perawatan LIKE '%fisio%'
                 UNION ALL
                 SELECT ridp.no_rawat,
                        IFNULL(ridp.tarif_tindakandr, 0) AS nilai_fisio,
                        ridp.tgl_perawatan,
                        ridp.jam_rawat,
                        jpi.nm_perawatan,
                        3 AS source_order
                 FROM rawat_inap_drpr ridp
                 INNER JOIN ($filterSql) f ON f.no_rawat = ridp.no_rawat
                 INNER JOIN jns_perawatan_inap jpi ON jpi.kd_jenis_prw = ridp.kd_jenis_prw
                 WHERE jpi.nm_perawatan LIKE '%fisio%'
             ) fisio_raw
            GROUP BY fisio_raw.no_rawat
        ) fisio ON fisio.no_rawat = rp.no_rawat
        LEFT JOIN (
            SELECT ekg_total.no_rawat,
                   SUM(ekg_total.nilai_ekg) AS ekg,
                   SUM(ekg_total.count_ekg) AS count_ekg
            FROM (
                SELECT rid.no_rawat,
                       SUM(CASE WHEN IFNULL(rid.bhp, 0) > 0 THEN rid.bhp ELSE IFNULL(jpi.bhp, 0) END) AS nilai_ekg,
                       COUNT(*) AS count_ekg
                FROM rawat_inap_dr rid
                INNER JOIN ($filterSql) f ON f.no_rawat = rid.no_rawat
                INNER JOIN jns_perawatan_inap jpi ON jpi.kd_jenis_prw = rid.kd_jenis_prw
                WHERE jpi.nm_perawatan LIKE '%ekg%'
                GROUP BY rid.no_rawat
                UNION ALL
                SELECT rip.no_rawat,
                       SUM(CASE WHEN IFNULL(rip.bhp, 0) > 0 THEN rip.bhp ELSE IFNULL(jpi.bhp, 0) END) AS nilai_ekg,
                       COUNT(*) AS count_ekg
                FROM rawat_inap_pr rip
                INNER JOIN ($filterSql) f ON f.no_rawat = rip.no_rawat
                INNER JOIN jns_perawatan_inap jpi ON jpi.kd_jenis_prw = rip.kd_jenis_prw
                WHERE jpi.nm_perawatan LIKE '%ekg%'
                GROUP BY rip.no_rawat
            ) ekg_total
            GROUP BY ekg_total.no_rawat
        ) ekg ON ekg.no_rawat = rp.no_rawat
        LEFT JOIN (
            SELECT bhp_penunjang_raw.no_rawat,
                   SUM(CASE WHEN bhp_penunjang_raw.nm_perawatan LIKE '%Fototerhapy%' THEN bhp_penunjang_raw.nilai_bhp ELSE 0 END) AS phototherapy,
                   SUM(CASE WHEN bhp_penunjang_raw.nm_perawatan LIKE '%Spirometri%' THEN bhp_penunjang_raw.nilai_bhp ELSE 0 END) AS spirometri
            FROM (
                SELECT rid.no_rawat,
                       CASE WHEN IFNULL(rid.bhp, 0) > 0 THEN rid.bhp ELSE IFNULL(jpi.bhp, 0) END AS nilai_bhp,
                       jpi.nm_perawatan
                FROM rawat_inap_dr rid
                INNER JOIN ($filterSql) f ON f.no_rawat = rid.no_rawat
                INNER JOIN jns_perawatan_inap jpi ON jpi.kd_jenis_prw = rid.kd_jenis_prw
                WHERE jpi.nm_perawatan LIKE '%Fototerhapy%'
                   OR jpi.nm_perawatan LIKE '%Spirometri%'
                UNION ALL
                SELECT rip.no_rawat,
                       CASE WHEN IFNULL(rip.bhp, 0) > 0 THEN rip.bhp ELSE IFNULL(jpi.bhp, 0) END AS nilai_bhp,
                       jpi.nm_perawatan
                FROM rawat_inap_pr rip
                INNER JOIN ($filterSql) f ON f.no_rawat = rip.no_rawat
                INNER JOIN jns_perawatan_inap jpi ON jpi.kd_jenis_prw = rip.kd_jenis_prw
                WHERE jpi.nm_perawatan LIKE '%Fototerhapy%'
                   OR jpi.nm_perawatan LIKE '%Spirometri%'
            ) bhp_penunjang_raw
            GROUP BY bhp_penunjang_raw.no_rawat
        ) bhp_penunjang ON bhp_penunjang.no_rawat = rp.no_rawat
        LEFT JOIN (
            SELECT makan_detail.no_rawat,
                   COUNT(*) AS makan_kali,
                   ROUND(SUM(makan_detail.harga_porsi) / NULLIF(COUNT(*), 0)) AS makan_harga,
                   SUM(makan_detail.harga_porsi) AS makan_jumlah
            FROM (
                SELECT dbd.no_rawat,
                       CASE
                           WHEN k.kelas LIKE '%VVIP%' THEN 35000
                           WHEN k.kelas LIKE '%VIP%' THEN 30000
                           WHEN k.kelas LIKE '%Kelas Utama%' AND k.kd_bangsal LIKE '%ICU%' THEN 27500
                           WHEN k.kelas LIKE '%Kelas Utama%' AND k.kd_bangsal LIKE '%ISO%' THEN 20000
                           WHEN k.kelas LIKE '%Kelas 1%' THEN 27500
                           WHEN k.kelas LIKE '%Kelas 2%' THEN 20000
                           WHEN k.kelas LIKE '%Kelas 3%' THEN 20000
                           ELSE 0
                       END AS harga_porsi
                FROM detail_beri_diet dbd
                INNER JOIN ($filterSql) f ON f.no_rawat = dbd.no_rawat
                LEFT JOIN kamar k ON k.kd_kamar = dbd.kd_kamar
            ) makan_detail
            GROUP BY makan_detail.no_rawat
        ) makan ON makan.no_rawat = rp.no_rawat
        LEFT JOIN (
            SELECT tb.no_rawat,
                   SUM(tb.besar_biaya) AS oksigen
            FROM tambahan_biaya tb
            INNER JOIN ($filterSql) f ON f.no_rawat = tb.no_rawat
            WHERE tb.nama_biaya LIKE '%oksigen%'
            GROUP BY tb.no_rawat
        ) oksigen ON oksigen.no_rawat = rp.no_rawat
        LEFT JOIN (
            SELECT hd_raw.no_rawat,
                   SUM(hd_raw.hd_count) AS hd_count
            FROM (
                SELECT rid.no_rawat,
                       COUNT(*) AS hd_count
                FROM rawat_inap_dr rid
                INNER JOIN ($filterSql) f ON f.no_rawat = rid.no_rawat
                INNER JOIN jns_perawatan_inap jpi ON jpi.kd_jenis_prw = rid.kd_jenis_prw
                WHERE jpi.nm_perawatan LIKE '%Hemodialisa%'
                GROUP BY rid.no_rawat
                UNION ALL
                SELECT rip.no_rawat,
                       COUNT(*) AS hd_count
                FROM rawat_inap_pr rip
                INNER JOIN ($filterSql) f ON f.no_rawat = rip.no_rawat
                INNER JOIN jns_perawatan_inap jpi ON jpi.kd_jenis_prw = rip.kd_jenis_prw
                WHERE jpi.nm_perawatan LIKE '%Hemodialisa%'
                GROUP BY rip.no_rawat
            ) hd_raw
            GROUP BY hd_raw.no_rawat
        ) hd ON hd.no_rawat = rp.no_rawat
        LEFT JOIN (
            SELECT darah_raw.no_rawat,
                   SUM(darah_raw.nilai_darah) AS darah,
                   SUM(darah_raw.jumlah_darah) AS jumlah_darah
            FROM (
                SELECT rid.no_rawat,
                       IFNULL(jpi.total_byrdrpr, 0) AS nilai_darah,
                       CASE WHEN jpi.nm_perawatan LIKE '%Harga Darah%' THEN 1 ELSE 0 END AS jumlah_darah
                FROM rawat_inap_dr rid
                INNER JOIN ($filterSql) f ON f.no_rawat = rid.no_rawat
                INNER JOIN jns_perawatan_inap jpi ON jpi.kd_jenis_prw = rid.kd_jenis_prw
                WHERE jpi.nm_perawatan LIKE '%Harga Darah%'

                UNION ALL

                SELECT rip.no_rawat,
                       IFNULL(jpi.total_byrdrpr, 0) AS nilai_darah,
                       CASE WHEN jpi.nm_perawatan LIKE '%Harga Darah%' THEN 1 ELSE 0 END AS jumlah_darah
                FROM rawat_inap_pr rip
                INNER JOIN ($filterSql) f ON f.no_rawat = rip.no_rawat
                INNER JOIN jns_perawatan_inap jpi ON jpi.kd_jenis_prw = rip.kd_jenis_prw
                WHERE jpi.nm_perawatan LIKE '%Harga Darah%'
            ) darah_raw
            GROUP BY darah_raw.no_rawat
        ) darah ON darah.no_rawat = rp.no_rawat
        LEFT JOIN (
            SELECT dpo.no_rawat,
                   COUNT(*) AS jumlah_albumin
            FROM detail_pemberian_obat dpo
            INNER JOIN ($filterSql) f ON f.no_rawat = dpo.no_rawat
            INNER JOIN databarang db ON db.kode_brng = dpo.kode_brng
            WHERE LOWER(db.nama_brng) LIKE '%albumin%'
            GROUP BY dpo.no_rawat
        ) albumin ON albumin.no_rawat = rp.no_rawat
        LEFT JOIN (
            SELECT o.no_rawat,
                   SUM(o.biayadokter_anestesi) AS jd_anestesi,
                   SUM(o.biayadokter_anak + o.biaya_dokter_pjanak) AS jd_anak,
                   SUM(o.biaya_dokter_umum) AS jd_dokter_umum,
                   1 AS has_operasi,
                   MAX(CASE WHEN po.nm_perawatan LIKE '%partus%' THEN 1 ELSE 0 END) AS has_partus,
                   MAX(CASE WHEN po.nm_perawatan LIKE '%Phacoemulsifikasi%' THEN 1 ELSE 0 END) AS has_phaco,
                   MAX(CASE WHEN po.nm_perawatan LIKE '%Phacoemulsifikasi%'
                             AND NULLIF(TRIM(o.dokter_anestesi), '') IS NOT NULL
                             AND TRIM(o.dokter_anestesi) <> '-' THEN 1 ELSE 0 END) AS has_phaco_anestesi,
                   MAX(CASE WHEN po.nm_perawatan LIKE '%Phacoemulsifikasi%'
                             AND (NULLIF(TRIM(o.dokter_anestesi), '') IS NULL OR TRIM(o.dokter_anestesi) = '-') THEN 1 ELSE 0 END) AS has_phaco_tanpa_anestesi,
                   GROUP_CONCAT(DISTINCT NULLIF(TRIM(o.operator1), '') ORDER BY o.tgl_operasi SEPARATOR '|') AS operator1_codes,
                   GROUP_CONCAT(DISTINCT COALESCE(NULLIF(d_operator1.nm_dokter, ''), NULLIF(TRIM(o.operator1), '')) ORDER BY o.tgl_operasi SEPARATOR ', ') AS operator1_names,
                   MAX(CASE WHEN po.nm_perawatan LIKE '%SC%' THEN 1 ELSE 0 END) AS has_jd_anak_sc,
                   MAX(CASE WHEN po.nm_perawatan LIKE '%Partus%' THEN 1 ELSE 0 END) AS has_jd_anak_partus,
                   GROUP_CONCAT(DISTINCT CASE WHEN po.nm_perawatan LIKE '%SC%'
                                                   OR po.nm_perawatan LIKE '%Partus%'
                                              THEN po.nm_perawatan END
                                ORDER BY o.tgl_operasi SEPARATOR ', ') AS jd_anak_package_names,
                   GROUP_CONCAT(DISTINCT NULLIF(TRIM(po.nm_perawatan), '') ORDER BY o.tgl_operasi SEPARATOR ', ') AS tindakan_operasi
            FROM operasi o
            INNER JOIN ($filterSql) f ON f.no_rawat = o.no_rawat
             LEFT JOIN paket_operasi po ON po.kode_paket = o.kode_paket
             LEFT JOIN dokter d_operator1 ON d_operator1.kd_dokter = o.operator1
            GROUP BY o.no_rawat
        ) ok ON ok.no_rawat = rp.no_rawat
        WHERE $dateWhereSql
          $singleFilterSql
          AND rp.status_lanjut = 'Ranap'
          AND rp.kd_pj = 'BPJ'
          AND (ki.stts_pulang IS NULL OR ki.stts_pulang = '-' OR ki.stts_pulang <> 'Pindah Kamar')
        GROUP BY rp.no_rawat, rp.no_rkm_medis, p.nm_pasien, rp.umurdaftar, rp.sttsumur,
                 manual.jum_claim, manual.jum_jdoperator, manual.claim_selected, manual.claim_source, manual.claim_history, manual.claim_history_no_rawat, inacbg.tariff, ugd.dokter_ugd, visit.visit_items, telp.telp_items, usg.jd_usg, rad.jd_rontgen,
                 lab.jd_lab, lab.jd_pa, hd.hd_count, bhp.bhp, obat.total_harga_dasar_obat, obat.markup_obat_bhp, obat.obat, lab.lab_pk, lab.lab_pa,
                 usg.rad_usg, rad.rontgen, fisio.fisio_items, ekg.ekg, ekg.count_ekg, darah.darah, darah.jumlah_darah,
                 makan.makan_jumlah, makan.makan_harga, makan.makan_kali, bhp_penunjang.phototherapy, oksigen.oksigen,
                 bhp_penunjang.spirometri, albumin.jumlah_albumin,
                 ok.jd_anestesi, ok.jd_anak, ok.jd_dokter_umum, ok.has_operasi, ok.has_partus,
                 ok.has_phaco, ok.has_phaco_anestesi, ok.has_phaco_tanpa_anestesi, ok.operator1_codes, ok.operator1_names,
                 ok.has_jd_anak_sc, ok.has_jd_anak_partus, ok.jd_anak_package_names, ok.tindakan_operasi
        ORDER BY $orderSql";

    $stmt = $mysqli->prepare($sql);
    if ($onlyNoRawat !== '') {
        $stmt->bind_param('sss', $startDate, $endDate, $onlyNoRawat);
    } else {
        $stmt->bind_param('ss', $startDate, $endDate);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $visit = aptd_keu_ranap_calculate_visit_fee($row);
        $row['jd_visit'] = $visit['total'];
        $row['jd_visit_umum'] = $visit['umum'];
        $row['jd_visit_spesialis'] = $visit['spesialis'];
        $row['jd_visit_pengganti'] = $visit['pengganti'];
        $row['ket_visit'] = $visit['condition'];
        $telpon = aptd_keu_ranap_calculate_telpon_fee($row);
        $row['jd_telpon'] = $telpon['total'];
        $row['jd_telpon_dpjp'] = $telpon['dpjp'];
        $row['jd_telpon_non_dpjp'] = $telpon['non_dpjp'];
        $row['jd_telpon_pengganti'] = $telpon['pengganti'];
        $row['ket_telpon'] = $telpon['condition'];
        $fisio = aptd_keu_ranap_calculate_fisio($row);
        $row['fisio'] = $fisio['total'];
        $row['fisio_counted'] = $fisio['counted'];
        $row['fisio_skipped'] = $fisio['skipped'];
        $row['jd_dpjp'] = aptd_keu_ranap_calculate_dpjp_fee($row);
        $row['ket_dpjp'] = aptd_keu_ranap_dpjp_condition($row);
        $row['jd_anestesi'] = aptd_keu_ranap_calculate_anestesi_fee($row);
        $row['ket_anestesi'] = aptd_keu_ranap_anestesi_condition($row);
        $row['jd_anak'] = aptd_keu_ranap_calculate_anak_fee($row);
        $row['ket_anak'] = aptd_keu_ranap_anak_condition($row);
        $row['jk'] = (float) $row['claim'] * 0.15;
        $row['total_jasa_dokter'] = aptd_keu_ranap_sum_doctor_fee($row);
        $row['total_biaya_laporan'] = aptd_keu_ranap_sum_report_cost($row);
        $row['margin'] = (float) $row['claim'] - (float) $row['total_biaya_laporan'];
        $row['ket_darah'] = (int) $row['jumlah_darah'] > 0 ? (string) (int) $row['jumlah_darah'] : '';
        $row['ket_albumin'] = (int) $row['jumlah_albumin'] > 0 ? (string) (int) $row['jumlah_albumin'] : '';
        $row['ket_tindakan'] = trim((string) $row['tindakan_operasi']);
        $rows[] = $row;
    }

    $stmt->close();
    aptd_keu_ranap_apply_history_claims($mysqli, $rows);
    return $rows;
}

function aptd_keu_ranap_filter_sql(mysqli $mysqli, $startDate, $endDate, $onlyNoRawat = '', $filterBy = 'masuk')
{
    $onlyNoRawat = $mysqli->real_escape_string(trim((string) $onlyNoRawat));
    $singleFilter = $onlyNoRawat !== '' ? " AND rp.no_rawat = '$onlyNoRawat'" : "";
    $dateWhereSql = aptd_keu_ranap_date_filter_literal($mysqli, $startDate, $endDate, $filterBy);

    return "
        SELECT DISTINCT ki.no_rawat
        FROM kamar_inap ki
        INNER JOIN reg_periksa rp ON rp.no_rawat = ki.no_rawat
        WHERE $dateWhereSql
          $singleFilter
          AND rp.status_lanjut = 'Ranap'
          AND rp.kd_pj = 'BPJ'
          AND (ki.stts_pulang IS NULL OR ki.stts_pulang = '-' OR ki.stts_pulang <> 'Pindah Kamar')";
}

function aptd_keu_ranap_cache_columns()
{
    $decimal = 'DECIMAL(16,2) NOT NULL DEFAULT 0';
    $integer = 'INT NOT NULL DEFAULT 0';
    $text = 'TEXT NULL';
    $varchar = 'VARCHAR(255) NULL';

    return [
        'calculated_at' => 'DATETIME NULL',
        'calc_dokter_ugd' => $decimal,
        'calc_jd_dpjp' => $decimal,
        'calc_ket_dpjp' => $text,
        'calc_jd_operator' => $decimal,
        'calc_jd_anestesi' => $decimal,
        'calc_ket_anestesi' => $text,
        'calc_jd_anak' => $decimal,
        'calc_ket_anak' => $text,
        'calc_jd_visit' => $decimal,
        'calc_jd_visit_umum' => $decimal,
        'calc_jd_visit_spesialis' => $decimal,
        'calc_jd_visit_pengganti' => $decimal,
        'calc_ket_visit' => $text,
        'calc_jd_telpon' => $decimal,
        'calc_jd_telpon_pengganti' => $decimal,
        'calc_ket_telpon' => $text,
        'calc_jd_usg' => $decimal,
        'calc_jd_rontgen' => $decimal,
        'calc_jd_lab' => $decimal,
        'calc_jd_pa' => $decimal,
        'calc_hd' => $decimal,
        'calc_jk' => $decimal,
        'calc_bhp' => $decimal,
        'calc_obat' => $decimal,
        'calc_total_harga_dasar_obat' => $decimal,
        'calc_markup_obat_bhp' => $decimal,
        'calc_lab_pk' => $decimal,
        'calc_lab_pa' => $decimal,
        'calc_rad_usg' => $decimal,
        'calc_rontgen' => $decimal,
        'calc_fisio' => $decimal,
        'calc_ekg' => $decimal,
        'calc_darah' => $decimal,
        'calc_makan_jumlah' => $decimal,
        'calc_makan_harga' => $decimal,
        'calc_makan_kali' => $integer,
        'calc_phototherapy' => $decimal,
        'calc_oksigen' => $decimal,
        'calc_spirometri' => $decimal,
        'calc_total_biaya_laporan' => $decimal,
        'calc_margin' => $decimal,
        'calc_ket_darah' => $varchar,
        'calc_ket_albumin' => $varchar,
        'calc_ket_tindakan' => $text,
        'calc_no_sep' => $varchar,
    ];
}

function aptd_keu_ranap_cache_map()
{
    return [
        'dokter_ugd' => 'calc_dokter_ugd',
        'jd_dpjp' => 'calc_jd_dpjp',
        'ket_dpjp' => 'calc_ket_dpjp',
        'jd_operator' => 'calc_jd_operator',
        'jd_anestesi' => 'calc_jd_anestesi',
        'ket_anestesi' => 'calc_ket_anestesi',
        'jd_anak' => 'calc_jd_anak',
        'ket_anak' => 'calc_ket_anak',
        'jd_visit' => 'calc_jd_visit',
        'jd_visit_umum' => 'calc_jd_visit_umum',
        'jd_visit_spesialis' => 'calc_jd_visit_spesialis',
        'jd_visit_pengganti' => 'calc_jd_visit_pengganti',
        'ket_visit' => 'calc_ket_visit',
        'jd_telpon' => 'calc_jd_telpon',
        'jd_telpon_pengganti' => 'calc_jd_telpon_pengganti',
        'ket_telpon' => 'calc_ket_telpon',
        'jd_usg' => 'calc_jd_usg',
        'jd_rontgen' => 'calc_jd_rontgen',
        'jd_lab' => 'calc_jd_lab',
        'jd_pa' => 'calc_jd_pa',
        'hd' => 'calc_hd',
        'jk' => 'calc_jk',
        'bhp' => 'calc_bhp',
        'obat' => 'calc_obat',
        'total_harga_dasar_obat' => 'calc_total_harga_dasar_obat',
        'markup_obat_bhp' => 'calc_markup_obat_bhp',
        'lab_pk' => 'calc_lab_pk',
        'lab_pa' => 'calc_lab_pa',
        'rad_usg' => 'calc_rad_usg',
        'rontgen' => 'calc_rontgen',
        'fisio' => 'calc_fisio',
        'ekg' => 'calc_ekg',
        'darah' => 'calc_darah',
        'makan_jumlah' => 'calc_makan_jumlah',
        'makan_harga' => 'calc_makan_harga',
        'makan_kali' => 'calc_makan_kali',
        'phototherapy' => 'calc_phototherapy',
        'oksigen' => 'calc_oksigen',
        'spirometri' => 'calc_spirometri',
        'total_biaya_laporan' => 'calc_total_biaya_laporan',
        'margin' => 'calc_margin',
        'ket_darah' => 'calc_ket_darah',
        'ket_albumin' => 'calc_ket_albumin',
        'ket_tindakan' => 'calc_ket_tindakan',
    ];
}

function aptd_keu_ranap_ensure_cache_schema(mysqli $mysqli)
{
    $existing = [];
    $result = $mysqli->query("SHOW COLUMNS FROM lap_keuangan_bpjs");
    while ($row = $result->fetch_assoc()) {
        $existing[$row['Field']] = strtolower($row['Type']);
    }

    $claimColumns = [
        'claim_source' => "VARCHAR(30) NULL",
        'claim_selected' => "DECIMAL(16,2) NULL",
        'claim_actual' => "DECIMAL(16,2) NULL",
        'claim_history' => "DECIMAL(16,2) NULL",
        'claim_history_no_rawat' => "VARCHAR(20) NULL",
        'claim_history_diagnose_code' => "VARCHAR(20) NULL",
        'claim_selected_at' => "DATETIME NULL",
        'claim_selected_by' => "VARCHAR(50) NULL",
    ];
    foreach ($claimColumns as $column => $definition) {
        if (!isset($existing[$column])) {
            $mysqli->query("ALTER TABLE lap_keuangan_bpjs ADD COLUMN $column $definition");
        }
    }

    foreach (aptd_keu_ranap_cache_columns() as $column => $definition) {
        if (!isset($existing[$column])) {
            $mysqli->query("ALTER TABLE lap_keuangan_bpjs ADD COLUMN $column $definition");
        }
    }

    if (isset($existing['calc_ket_tindakan']) && strpos($existing['calc_ket_tindakan'], 'text') !== 0) {
        $mysqli->query("ALTER TABLE lap_keuangan_bpjs MODIFY COLUMN calc_ket_tindakan TEXT NULL");
    }
}

function aptd_keu_ranap_fetch_report_rows(mysqli $mysqli, $startDate, $endDate, $filterBy = 'masuk')
{
    aptd_keu_ranap_ensure_cache_schema($mysqli);
    $inacbgSql = aptd_keu_ranap_inacbg_tariff_sql();
    $claimSelectSql = aptd_keu_ranap_claim_select_sql();
    $claimSourceSql = aptd_keu_ranap_claim_source_sql();
    $dateWhereSql = aptd_keu_ranap_date_where_sql($filterBy);
    $orderSql = aptd_keu_ranap_normalize_filter_by($filterBy) === 'keluar' ? 'tanggal_keluar ASC, rp.no_rawat ASC' : 'tanggal_masuk ASC, rp.no_rawat ASC';

    $selectCache = '';
    foreach (aptd_keu_ranap_cache_map() as $key => $column) {
        $selectCache .= ", manual.$column AS $key\n";
    }

    $sql = "
        SELECT
            rp.no_rawat,
            rp.no_rkm_medis,
            CONCAT(p.nm_pasien, ' (', rp.umurdaftar, ' ', rp.sttsumur, ')') AS nama_pasien_umur,
            MAX(IFNULL(bs.no_sep, '')) AS no_sep,
            MAX(NULLIF(ki.diagnosa_awal, '')) AS diagnosa_awal,
            MAX(NULLIF(ki.diagnosa_akhir, '')) AS diagnosa_akhir,
            MIN(ki.tgl_masuk) AS tanggal_masuk,
            MAX(NULLIF(ki.tgl_keluar, '0000-00-00')) AS tanggal_keluar,
            MAX(IFNULL(ki.stts_pulang, '-')) AS status_pulang,
            COALESCE(
                MAX(NULLIF(sep_dokter.nm_dokter, '')),
                MAX(NULLIF(sd_dokter.nm_dokter, '')),
                MAX(NULLIF(bs.nmdpjplayanan, '')),
                MAX(NULLIF(bs.nmdpdjp, '')),
                MAX(NULLIF(reg_dpjp.nm_dokter, ''))
            ) AS dpjp,
            GROUP_CONCAT(DISTINCT ki.kd_kamar ORDER BY ki.tgl_masuk, ki.jam_masuk SEPARATOR ', ') AS kamar,
            DATEDIFF(
                MAX(CASE
                    WHEN ki.stts_pulang <> '-'
                     AND ki.stts_pulang <> 'Pindah Kamar'
                    THEN NULLIF(ki.tgl_keluar, '0000-00-00')
                END),
                MAX(NULLIF(bs.tglsep, '0000-00-00'))
            ) AS lama_dirawat,
            MAX(IFNULL(bs.nmdiagnosaawal, '')) AS diagnosa_sep,
            $claimSelectSql AS claim,
            COALESCE(manual.jum_claim, 0) AS manual_claim,
            COALESCE(manual.claim_selected, 0) AS claim_selected_raw,
            COALESCE(inacbg.tariff, 0) AS claim_actual,
            COALESCE(manual.claim_history, 0) AS claim_history,
            COALESCE(manual.claim_history_no_rawat, '') AS claim_history_no_rawat,
            COALESCE(
                MAX(NULLIF(TRIM(dp_current.kd_penyakit), '')),
                MAX(NULLIF(TRIM(ds_current.kode_icd), ''))
            ) AS claim_history_diagnose_code,
            $claimSourceSql AS claim_source,
            manual.calculated_at,
            CASE WHEN manual.calculated_at IS NULL THEN 0 ELSE 1 END AS has_hitung
            $selectCache
        FROM kamar_inap ki
        INNER JOIN reg_periksa rp ON rp.no_rawat = ki.no_rawat
        INNER JOIN pasien p ON p.no_rkm_medis = rp.no_rkm_medis
        LEFT JOIN status_dpjp sd ON sd.no_rawat = rp.no_rawat
        LEFT JOIN dokter sd_dokter ON sd_dokter.kd_dokter = sd.kd_dokter AND sd_dokter.status = '1'
        LEFT JOIN dokter reg_dpjp ON reg_dpjp.kd_dokter = rp.kd_dokter
        LEFT JOIN bridging_sep bs ON bs.no_rawat = rp.no_rawat
        LEFT JOIN maping_dokter_dpjpvclaim mdpjp ON mdpjp.kd_dokter_bpjs = NULLIF(bs.kddpjp, '')
        LEFT JOIN dokter sep_dokter ON sep_dokter.kd_dokter = mdpjp.kd_dokter AND sep_dokter.status = '1'
        LEFT JOIN diagnosa_pasien dp_current
          ON dp_current.no_rawat = rp.no_rawat
         AND dp_current.prioritas = 1
         AND dp_current.status = 'Ranap'
        LEFT JOIN diagnosa_sementara ds_current ON ds_current.nomor_rawat = rp.no_rawat
        LEFT JOIN lap_keuangan_bpjs manual ON manual.no_rawat = rp.no_rawat
        LEFT JOIN ($inacbgSql) inacbg ON inacbg.no_rawat = rp.no_rawat
        WHERE $dateWhereSql
          AND rp.status_lanjut = 'Ranap'
          AND rp.kd_pj = 'BPJ'
          AND (ki.stts_pulang IS NULL OR ki.stts_pulang = '-' OR ki.stts_pulang <> 'Pindah Kamar')
        GROUP BY rp.no_rawat, rp.no_rkm_medis, p.nm_pasien, rp.umurdaftar, rp.sttsumur, manual.no_rawat, manual.jum_claim, manual.claim_selected, manual.claim_source, manual.claim_history, manual.claim_history_no_rawat, inacbg.tariff
        ORDER BY $orderSql";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $row['claim_source_label'] = aptd_keu_ranap_claim_source_label(
            isset($row['claim_source']) ? $row['claim_source'] : '',
            isset($row['claim']) ? (float) $row['claim'] : 0,
            isset($row['claim_history']) ? (float) $row['claim_history'] : 0
        );
        $rows[] = $row;
    }

    $stmt->close();
    aptd_keu_ranap_apply_history_claims($mysqli, $rows);
    return $rows;
}

function aptd_keu_ranap_save_manual(mysqli $mysqli, $noRawat, $claim, $jdOperator, $allowClaimUpdate = true)
{
    aptd_keu_ranap_ensure_cache_schema($mysqli);
    $noRawat = trim($noRawat);
    if ($noRawat === '') {
        return ['success' => false, 'message' => 'No rawat tidak boleh kosong.'];
    }

    $claim = aptd_keu_ranap_parse_number($claim);
    $jdOperator = aptd_keu_ranap_parse_number($jdOperator);

    $check = $mysqli->prepare('SELECT COUNT(*) AS total, MAX(jum_claim) AS jum_claim FROM lap_keuangan_bpjs WHERE no_rawat = ?');
    $check->bind_param('s', $noRawat);
    $check->execute();
    $current = $check->get_result()->fetch_assoc();
    $exists = ((int) $current['total']) > 0;
    $check->close();

    if (!$allowClaimUpdate) {
        $claim = $exists ? (float) $current['jum_claim'] : 0;
    }

    if ($exists) {
        if ($allowClaimUpdate) {
            $stmt = $mysqli->prepare("UPDATE lap_keuangan_bpjs SET jum_claim = ?, jum_jdoperator = ?, claim_selected = ?, claim_source = 'manual', claim_selected_at = NOW() WHERE no_rawat = ?");
            $stmt->bind_param('ddds', $claim, $jdOperator, $claim, $noRawat);
        } else {
            $stmt = $mysqli->prepare('UPDATE lap_keuangan_bpjs SET jum_jdoperator = ? WHERE no_rawat = ?');
            $stmt->bind_param('ds', $jdOperator, $noRawat);
        }
    } else {
        $source = $allowClaimUpdate ? 'manual' : 'none';
        $selectedClaim = $allowClaimUpdate ? $claim : 0;
        $stmt = $mysqli->prepare('INSERT INTO lap_keuangan_bpjs (no_rawat, jum_claim, jum_jdoperator, claim_selected, claim_source, claim_selected_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $stmt->bind_param('sddds', $noRawat, $claim, $jdOperator, $selectedClaim, $source);
    }

    $stmt->execute();
    $stmt->close();

    return ['success' => true, 'message' => 'Data claim dan JD Operator berhasil disimpan.'];
}

function aptd_keu_ranap_save_claim(mysqli $mysqli, $noRawat, $claim, $allowClaimUpdate = true)
{
    aptd_keu_ranap_ensure_cache_schema($mysqli);
    $noRawat = trim($noRawat);
    if ($noRawat === '') {
        return ['success' => false, 'message' => 'No rawat tidak boleh kosong.'];
    }

    if (!$allowClaimUpdate) {
        return ['success' => false, 'message' => 'Level Anda hanya dapat melihat nilai claim.'];
    }

    $claim = aptd_keu_ranap_parse_number($claim);

    $check = $mysqli->prepare('SELECT COUNT(*) AS total FROM lap_keuangan_bpjs WHERE no_rawat = ?');
    $check->bind_param('s', $noRawat);
    $check->execute();
    $exists = ((int) $check->get_result()->fetch_assoc()['total']) > 0;
    $check->close();

    if ($exists) {
        $stmt = $mysqli->prepare("UPDATE lap_keuangan_bpjs SET jum_claim = ?, claim_selected = ?, claim_source = 'manual', claim_selected_at = NOW() WHERE no_rawat = ?");
        $stmt->bind_param('dds', $claim, $claim, $noRawat);
    } else {
        $jdOperator = 0;
        $stmt = $mysqli->prepare("INSERT INTO lap_keuangan_bpjs (no_rawat, jum_claim, jum_jdoperator, claim_selected, claim_source, claim_selected_at) VALUES (?, ?, ?, ?, 'manual', NOW())");
        $stmt->bind_param('sddd', $noRawat, $claim, $jdOperator, $claim);
    }

    $stmt->execute();
    $stmt->close();

    return ['success' => true, 'message' => 'Data claim berhasil disimpan.'];
}

function aptd_keu_ranap_find_history_claim(mysqli $mysqli, $noRawat)
{
    aptd_keu_ranap_ensure_cache_schema($mysqli);
    $noRawat = trim((string) $noRawat);
    if ($noRawat === '') {
        return null;
    }

    $codeStmt = $mysqli->prepare("
        SELECT COALESCE(
                   MAX(NULLIF(TRIM(dp.kd_penyakit), '')),
                   MAX(NULLIF(TRIM(ds.kode_icd), ''))
               ) AS kode_icd
        FROM reg_periksa rp
        LEFT JOIN diagnosa_pasien dp
          ON dp.no_rawat = rp.no_rawat
         AND dp.prioritas = 1
         AND dp.status = 'Ranap'
        LEFT JOIN diagnosa_sementara ds
          ON ds.nomor_rawat = rp.no_rawat
        WHERE rp.no_rawat = ?
          AND rp.status_lanjut = 'Ranap'
    ");
    $codeStmt->bind_param('s', $noRawat);
    $codeStmt->execute();
    $codeRow = $codeStmt->get_result()->fetch_assoc();
    $codeStmt->close();

    $diagnoseCode = $codeRow && isset($codeRow['kode_icd']) ? trim((string) $codeRow['kode_icd']) : '';
    if ($diagnoseCode === '') {
        return null;
    }

    $inacbgSql = aptd_keu_ranap_inacbg_tariff_sql();
    $sql = "
        SELECT tariff.tariff AS claim_history,
               dp.no_rawat AS claim_history_no_rawat,
               dp.kd_penyakit AS claim_history_diagnose_code
        FROM diagnosa_pasien dp
        INNER JOIN ($inacbgSql) tariff ON tariff.no_rawat = dp.no_rawat
        WHERE dp.prioritas = 1
          AND dp.status = 'Ranap'
          AND dp.kd_penyakit = ?
          AND dp.no_rawat <> ?
        ORDER BY tariff.tariff_datetime DESC, dp.no_rawat DESC
        LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('ss', $diagnoseCode, $noRawat);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || (float) $row['claim_history'] <= 0) {
        return null;
    }

    return $row;
}

function aptd_keu_ranap_use_history_claim(mysqli $mysqli, $noRawat, $username = '')
{
    aptd_keu_ranap_ensure_cache_schema($mysqli);
    $noRawat = trim((string) $noRawat);
    if ($noRawat === '') {
        return ['success' => false, 'message' => 'No rawat tidak boleh kosong.'];
    }

    $history = aptd_keu_ranap_find_history_claim($mysqli, $noRawat);
    if (!$history) {
        return ['success' => false, 'message' => 'Klaim riwayat diagnosa belum tersedia untuk no rawat ini.'];
    }

    $claim = (float) $history['claim_history'];
    $historyNoRawat = (string) $history['claim_history_no_rawat'];
    $diagnoseCode = (string) $history['claim_history_diagnose_code'];
    $username = trim((string) $username);

    $check = $mysqli->prepare('SELECT COUNT(*) AS total FROM lap_keuangan_bpjs WHERE no_rawat = ?');
    $check->bind_param('s', $noRawat);
    $check->execute();
    $exists = ((int) $check->get_result()->fetch_assoc()['total']) > 0;
    $check->close();

    if ($exists) {
        $stmt = $mysqli->prepare("
            UPDATE lap_keuangan_bpjs
            SET claim_selected = ?,
                claim_source = 'history_diagnose',
                claim_history = ?,
                claim_history_no_rawat = ?,
                claim_history_diagnose_code = ?,
                claim_selected_at = NOW(),
                claim_selected_by = ?
            WHERE no_rawat = ?");
        $stmt->bind_param('ddssss', $claim, $claim, $historyNoRawat, $diagnoseCode, $username, $noRawat);
    } else {
        $zero = 0;
        $stmt = $mysqli->prepare("
            INSERT INTO lap_keuangan_bpjs
                (no_rawat, jum_claim, jum_jdoperator, claim_selected, claim_source, claim_history, claim_history_no_rawat, claim_history_diagnose_code, claim_selected_at, claim_selected_by)
            VALUES
                (?, ?, ?, ?, 'history_diagnose', ?, ?, ?, NOW(), ?)");
        $stmt->bind_param('sddddsss', $noRawat, $zero, $zero, $claim, $claim, $historyNoRawat, $diagnoseCode, $username);
    }

    $stmt->execute();
    $stmt->close();

    return ['success' => true, 'message' => 'Klaim riwayat berhasil dipakai untuk ' . $noRawat . '. Silakan lanjutkan Hitung.'];
}

function aptd_keu_ranap_persist_effective_claim(mysqli $mysqli, array $row)
{
    aptd_keu_ranap_ensure_cache_schema($mysqli);
    $noRawat = isset($row['no_rawat']) ? trim((string) $row['no_rawat']) : '';
    $claim = isset($row['claim']) ? (float) $row['claim'] : 0;
    $source = isset($row['claim_source']) ? trim((string) $row['claim_source']) : 'none';
    $selectedRaw = isset($row['claim_selected_raw']) ? (float) $row['claim_selected_raw'] : 0;
    if ($noRawat === '' || $claim <= 0 || $selectedRaw > 0) {
        return;
    }

    if ($source !== 'manual' && $source !== 'inacbg_current') {
        return;
    }

    $actual = isset($row['claim_actual']) ? (float) $row['claim_actual'] : 0;
    $history = isset($row['claim_history']) ? (float) $row['claim_history'] : 0;
    $historyNoRawat = isset($row['claim_history_no_rawat']) ? (string) $row['claim_history_no_rawat'] : '';
    $diagnoseCode = isset($row['claim_history_diagnose_code']) ? (string) $row['claim_history_diagnose_code'] : '';

    $check = $mysqli->prepare('SELECT COUNT(*) AS total FROM lap_keuangan_bpjs WHERE no_rawat = ?');
    $check->bind_param('s', $noRawat);
    $check->execute();
    $exists = ((int) $check->get_result()->fetch_assoc()['total']) > 0;
    $check->close();

    if ($exists) {
        $stmt = $mysqli->prepare("
            UPDATE lap_keuangan_bpjs
            SET claim_selected = ?,
                claim_source = ?,
                claim_actual = ?,
                claim_history = ?,
                claim_history_no_rawat = ?,
                claim_history_diagnose_code = ?,
                claim_selected_at = NOW()
            WHERE no_rawat = ?");
        $stmt->bind_param('dsddsss', $claim, $source, $actual, $history, $historyNoRawat, $diagnoseCode, $noRawat);
    } else {
        $zero = 0;
        $stmt = $mysqli->prepare("
            INSERT INTO lap_keuangan_bpjs
                (no_rawat, jum_claim, jum_jdoperator, claim_selected, claim_source, claim_actual, claim_history, claim_history_no_rawat, claim_history_diagnose_code, claim_selected_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param('sdddsddss', $noRawat, $zero, $zero, $claim, $source, $actual, $history, $historyNoRawat, $diagnoseCode);
    }

    $stmt->execute();
    $stmt->close();
}

function aptd_keu_ranap_promote_actual_claim_for_calculation(mysqli $mysqli, array $row)
{
    aptd_keu_ranap_ensure_cache_schema($mysqli);

    $noRawat = isset($row['no_rawat']) ? trim((string) $row['no_rawat']) : '';
    $source = isset($row['claim_source']) ? trim((string) $row['claim_source']) : '';
    $actual = isset($row['claim_actual']) ? (float) $row['claim_actual'] : 0;

    if ($noRawat === '' || $source !== 'history_diagnose' || $actual <= 0) {
        return $row;
    }

    $history = isset($row['claim_history']) ? (float) $row['claim_history'] : 0;
    $historyNoRawat = isset($row['claim_history_no_rawat']) ? (string) $row['claim_history_no_rawat'] : '';
    $diagnoseCode = isset($row['claim_history_diagnose_code']) ? (string) $row['claim_history_diagnose_code'] : '';

    $stmt = $mysqli->prepare("
        UPDATE lap_keuangan_bpjs
        SET claim_selected = ?,
            claim_source = 'inacbg_current',
            claim_actual = ?,
            claim_history = ?,
            claim_history_no_rawat = ?,
            claim_history_diagnose_code = ?,
            claim_selected_at = NOW()
        WHERE no_rawat = ?");
    $stmt->bind_param('dddsss', $actual, $actual, $history, $historyNoRawat, $diagnoseCode, $noRawat);
    $stmt->execute();
    $stmt->close();

    $row['claim'] = $actual;
    $row['claim_selected_raw'] = $actual;
    $row['claim_source'] = 'inacbg_current';
    $row['claim_source_label'] = aptd_keu_ranap_claim_source_label('inacbg_current', $actual, $history);

    return $row;
}

function aptd_keu_ranap_calculate_and_store(mysqli $mysqli, $noRawat, $startDate, $endDate, $filterBy = 'masuk')
{
    aptd_keu_ranap_ensure_cache_schema($mysqli);

    $noRawat = trim($noRawat);
    if ($noRawat === '') {
        return ['success' => false, 'message' => 'No rawat tidak boleh kosong.'];
    }

    $rows = aptd_keu_ranap_fetch_rows($mysqli, $startDate, $endDate, $noRawat, $filterBy);
    if (empty($rows)) {
        return ['success' => false, 'message' => 'Data pasien tidak ditemukan pada periode yang dipilih.'];
    }

    $row = aptd_keu_ranap_promote_actual_claim_for_calculation($mysqli, $rows[0]);
    $claim = isset($row['claim']) ? (float) $row['claim'] : 0;
    if ($claim <= 0) {
        return ['success' => false, 'message' => 'Claim dipakai belum tersedia. Pilih klaim aktual atau pakai klaim riwayat terlebih dahulu.'];
    }

    $row['claim'] = $claim;
    aptd_keu_ranap_persist_effective_claim($mysqli, $row);
    $row['jk'] = $claim * 0.15;
    $row['jd_dpjp'] = aptd_keu_ranap_calculate_dpjp_fee($row);
    $row['ket_dpjp'] = aptd_keu_ranap_dpjp_condition($row);
    $row['total_jasa_dokter'] = aptd_keu_ranap_sum_doctor_fee($row);
    $row['total_biaya_laporan'] = aptd_keu_ranap_sum_report_cost($row);
    $row['margin'] = (float) $claim - (float) $row['total_biaya_laporan'];

    aptd_keu_ranap_store_calculation($mysqli, $row);

    return ['success' => true, 'message' => 'Data keuangan berhasil dihitung dan disimpan untuk ' . $noRawat . '.'];
}

function aptd_keu_ranap_store_calculation(mysqli $mysqli, array $row)
{
    $map = aptd_keu_ranap_cache_map();
    $noRawat = isset($row['no_rawat']) ? trim((string) $row['no_rawat']) : '';

    $check = $mysqli->prepare('SELECT COUNT(*) AS total FROM lap_keuangan_bpjs WHERE no_rawat = ?');
    $check->bind_param('s', $noRawat);
    $check->execute();
    $exists = ((int) $check->get_result()->fetch_assoc()['total']) > 0;
    $check->close();

    $columns = array_values($map);
    $values = [];
    foreach ($map as $key => $column) {
        $values[] = isset($row[$key]) ? $row[$key] : '';
    }

    $claimContextMap = [
        'claim_actual' => 'claim_actual',
        'claim_history' => 'claim_history',
        'claim_history_no_rawat' => 'claim_history_no_rawat',
        'claim_history_diagnose_code' => 'claim_history_diagnose_code',
    ];
    foreach ($claimContextMap as $key => $column) {
        $columns[] = $column;
        $values[] = isset($row[$key]) ? $row[$key] : '';
    }

    if ($exists) {
        $sets = ['calculated_at = NOW()'];
        foreach ($columns as $column) {
            $sets[] = $column . ' = ?';
        }
        $sql = 'UPDATE lap_keuangan_bpjs SET ' . implode(', ', $sets) . ' WHERE no_rawat = ?';
        $stmt = $mysqli->prepare($sql);
        $bindValues = array_merge($values, [$noRawat]);
        $types = str_repeat('s', count($values)) . 's';
    } else {
        $insertColumns = array_merge(['no_rawat', 'jum_claim', 'jum_jdoperator', 'calculated_at'], $columns);
        $placeholders = array_merge(['?', '?', '?', 'NOW()'], array_fill(0, count($columns), '?'));
        $sql = 'INSERT INTO lap_keuangan_bpjs (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $mysqli->prepare($sql);
        $bindValues = array_merge([$noRawat, 0, 0], $values);
        $types = 'sdd' . str_repeat('s', count($values));
    }

    $refs = [];
    foreach ($bindValues as $index => $value) {
        $refs[$index] = $bindValues[$index];
    }

    $bindParams = [$types];
    foreach ($refs as $index => &$value) {
        $bindParams[] = &$value;
    }

    call_user_func_array([$stmt, 'bind_param'], $bindParams);
    $stmt->execute();
    $stmt->close();
}

function aptd_keu_ranap_parse_number($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return 0;
    }

    $value = str_replace(['Rp', 'rp', ' ', '.'], '', $value);
    $value = str_replace(',', '.', $value);

    return is_numeric($value) ? (float) $value : 0;
}

function aptd_keu_ranap_calculate_dpjp_fee(array $row)
{
    $claim = (float) $row['claim'];
    $rule = aptd_keu_ranap_dpjp_rule($row);

    if (!$rule) {
        return 0;
    }

    $base = $claim * $rule['rate'];
    $deduction = aptd_keu_ranap_dpjp_deduction($row);

    return max(0, $base - $deduction);
}

function aptd_keu_ranap_dpjp_deduction(array $row)
{
    $deduction = 0;
    $deduction += isset($row['jd_visit_pengganti']) ? (float) $row['jd_visit_pengganti'] : 0;
    $deduction += isset($row['jd_telpon_pengganti']) ? (float) $row['jd_telpon_pengganti'] : 0;
    $deduction += isset($row['fisio']) ? (float) $row['fisio'] : 0;

    $dpjp = isset($row['kd_dokter_dpjp_status']) ? trim((string) $row['kd_dokter_dpjp_status']) : '';
    if ($dpjp !== '023.120813') {
        $deduction += isset($row['hd']) ? (float) $row['hd'] : 0;
    }

    return $deduction;
}

function aptd_keu_ranap_dpjp_rules()
{
    return [
        'S0001' => 0.19, // Obgyn 19%; partus 25% ditangani sebagai kondisi khusus.
        'S0005' => 0.18, // Orthopedi/OT
        'S0006' => 0.18, // Bedah
        'S0007' => 0.19, // Syaraf
        'S0009' => 0.19, // THT-KL
        'S0010' => 0.19, // Mata default; phaco ODC tanpa anestesi 24% perlu kondisi terpisah.
        'S0011' => 0.19, // Anak
        'S0013' => 0.19, // Penyakit Dalam
        'S0018' => 0.16, // Paru
    ];
}

function aptd_keu_ranap_dpjp_rule(array $row)
{
    $kdSps = isset($row['kd_sps_dpjp']) ? $row['kd_sps_dpjp'] : '';

    if ($kdSps === 'S0001') {
        if ((int) $row['has_partus'] === 1) {
            return ['rate' => 0.25, 'condition' => 'OG partus'];
        }

        if ((int) $row['has_operasi'] === 1) {
            return ['rate' => 0.19, 'condition' => 'OG operasi'];
        }

        return ['rate' => 0.19, 'condition' => 'OG tanpa operasi'];
    }

    if ($kdSps === 'S0010') {
        if ((int) $row['has_phaco_tanpa_anestesi'] === 1) {
            return ['rate' => 0.24, 'condition' => 'Mata phaco tanpa anestesi'];
        }

        if ((int) $row['has_phaco_anestesi'] === 1) {
            return ['rate' => 0.19, 'condition' => 'Mata phaco dengan anestesi'];
        }

        if ((int) $row['has_operasi'] === 1) {
            return ['rate' => 0.19, 'condition' => 'Mata operasi bukan PHACO'];
        }

        return null;
    }

    $rules = aptd_keu_ranap_dpjp_rules();
    if (!isset($rules[$kdSps])) {
        return null;
    }

    return ['rate' => $rules[$kdSps], 'condition' => ''];
}

function aptd_keu_ranap_dpjp_condition(array $row)
{
    if (empty($row['kd_dokter_dpjp_status'])) {
        if (!empty($row['kd_dokter_bpjs_dpjp'])) {
            if (!empty($row['kd_dokter_mapping_dpjp'])) {
                $status = isset($row['status_dokter_dpjp_sep']) && $row['status_dokter_dpjp_sep'] !== '' ? $row['status_dokter_dpjp_sep'] : '-';
                return 'Dokter DPJP SEP tidak aktif: ' . $row['kd_dokter_mapping_dpjp'] . ' (status ' . $status . ')';
            }

            return 'DPJP SEP belum termapping: ' . $row['kd_dokter_bpjs_dpjp'];
        }

        return 'Tidak ada DPJP di bridging_sep/status_dpjp';
    }

    if (empty($row['kd_sps_dpjp'])) {
        return 'Dokter DPJP aktif tanpa kd_sps';
    }

    $specialist = !empty($row['nm_sps_dpjp']) ? $row['nm_sps_dpjp'] : 'Spesialis';
    $source = isset($row['dpjp_source']) && $row['dpjp_source'] !== '' ? $row['dpjp_source'] : 'DPJP';
    $label = $specialist . ' (' . $row['kd_sps_dpjp'] . ', ' . $source . ')';
    $rule = aptd_keu_ranap_dpjp_rule($row);

    if (!$rule) {
        return $label . ' - belum ada rule';
    }

    $condition = $label . ' - ';
    if ($rule['condition'] !== '') {
        $condition .= $rule['condition'] . ' - ';
    }
    $condition .= (int) ($rule['rate'] * 100) . '% x claim';
    if ((float) $row['claim'] <= 0) {
        $condition .= ' (CLAIM 0)';
    }

    $visitPengganti = isset($row['jd_visit_pengganti']) ? (float) $row['jd_visit_pengganti'] : 0;
    $telponPengganti = isset($row['jd_telpon_pengganti']) ? (float) $row['jd_telpon_pengganti'] : 0;
    $fisio = isset($row['fisio']) ? (float) $row['fisio'] : 0;
    $hd = isset($row['hd']) ? (float) $row['hd'] : 0;
    $base = (float) $row['claim'] * $rule['rate'];
    $deduction = aptd_keu_ranap_dpjp_deduction($row);

    $condition .= ' - dikurangi JD Visite Pengganti ' . aptd_currency($visitPengganti);
    $condition .= ', JD Telpon Pengganti ' . aptd_currency($telponPengganti);

    $dpjp = isset($row['kd_dokter_dpjp_status']) ? trim((string) $row['kd_dokter_dpjp_status']) : '';
    if ($dpjp !== '023.120813') {
        $condition .= ', HD ' . aptd_currency($hd);
    }

    $condition .= ', Fisio ' . aptd_currency($fisio);

    if ($deduction > 0) {
        $condition .= ' = ' . aptd_currency(max(0, $base - $deduction));
    }

    return $condition;
}

function aptd_keu_ranap_calculate_visit_fee(array $row)
{
    $result = [
        'total' => 0,
        'umum' => 0,
        'spesialis' => 0,
        'pengganti' => 0,
        'condition' => 'Tidak ada visite umum/spesialis',
    ];

    if (empty($row['visit_items'])) {
        return $result;
    }

    $dpjp = isset($row['kd_dokter_dpjp_status']) ? trim((string) $row['kd_dokter_dpjp_status']) : '';
    $kdSpsDpjp = isset($row['kd_sps_dpjp']) ? trim((string) $row['kd_sps_dpjp']) : '';
    $doctorCounts = [];
    $skippedDpjp = 0;
    $skippedAnestesi = 0;
    $countedPengganti = 0;
    $skippedPenggantiLimit = 0;
    $skippedLimit = 0;
    $counted = 0;

    foreach (explode('|', (string) $row['visit_items']) as $item) {
        $parts = explode('~', $item);
        if (count($parts) < 6) {
            continue;
        }

        $kdDokter = trim($parts[0]);
        $kdSpsVisit = trim($parts[1]);
        $tarif = (float) $parts[2];
        $namaPerawatan = $parts[5];

        if ($kdDokter === '') {
            continue;
        }

        if ($dpjp !== '' && $kdDokter === $dpjp) {
            $skippedDpjp++;
            continue;
        }

        if ($kdSpsVisit === 'S0008') {
            $skippedAnestesi++;
            continue;
        }

        if ($kdSpsDpjp !== '' && $kdSpsVisit !== '' && $kdSpsVisit === $kdSpsDpjp) {
            if ($countedPengganti >= 3) {
                $skippedPenggantiLimit++;
                continue;
            }

            $result['pengganti'] += $tarif;
            $countedPengganti++;
            continue;
        }

        if (!isset($doctorCounts[$kdDokter])) {
            $doctorCounts[$kdDokter] = 0;
        }

        if ($doctorCounts[$kdDokter] >= 3) {
            $skippedLimit++;
            continue;
        }

        $doctorCounts[$kdDokter]++;
        $result['total'] += $tarif;
        $counted++;

        if (stripos($namaPerawatan, 'umum') !== false) {
            $result['umum'] += $tarif;
        } else {
            $result['spesialis'] += $tarif;
        }
    }

    if ($counted > 0 || $countedPengganti > 0 || $skippedDpjp > 0 || $skippedAnestesi > 0 || $skippedPenggantiLimit > 0 || $skippedLimit > 0) {
        $notes = [
            'Umum ' . aptd_currency($result['umum']),
            'Spesialis ' . aptd_currency($result['spesialis']),
            'Dihitung ' . $counted . ' visite',
            'Pengganti ' . aptd_currency($result['pengganti']) . ' (' . $countedPengganti . ' visite)',
        ];

        if ($dpjp !== '') {
            $notes[] = 'DPJP tidak dihitung';
        }

        if ($skippedDpjp > 0) {
            $notes[] = 'skip DPJP ' . $skippedDpjp;
        }

        if ($skippedAnestesi > 0) {
            $notes[] = 'skip dokter anastesi (S0008) ' . $skippedAnestesi;
        }

        if ($skippedPenggantiLimit > 0) {
            $notes[] = 'skip pengganti >3 visite/no_rawat ' . $skippedPenggantiLimit;
        }

        if ($skippedLimit > 0) {
            $notes[] = 'skip >3 visite/dokter ' . $skippedLimit;
        }

        $result['condition'] = implode('; ', $notes);
    }

    return $result;
}

function aptd_keu_ranap_calculate_telpon_fee(array $row)
{
    $result = [
        'total' => 0,
        'dpjp' => 0,
        'non_dpjp' => 0,
        'pengganti' => 0,
        'condition' => 'Tidak ada konsultasi telpon',
    ];

    if (empty($row['telp_items'])) {
        return $result;
    }

    $dpjp = isset($row['kd_dokter_dpjp_status']) ? trim((string) $row['kd_dokter_dpjp_status']) : '';
    $kdSpsDpjp = isset($row['kd_sps_dpjp']) ? trim((string) $row['kd_sps_dpjp']) : '';
    $countDpjp = 0;
    $countNonDpjp = 0;
    $countPengganti = 0;
    $skippedAnestesi = 0;

    foreach (explode('|', (string) $row['telp_items']) as $item) {
        $parts = explode('~', $item);
        if (count($parts) < 6) {
            continue;
        }

        $kdDokter = trim($parts[0]);
        $kdSpsTelp = trim($parts[1]);
        $tarif = (float) $parts[2];

        if ($kdDokter === '') {
            continue;
        }

        if ($dpjp !== '' && $kdDokter === $dpjp) {
            $result['dpjp'] += $tarif;
            $countDpjp++;
            continue;
        }

        if ($kdSpsTelp === 'S0008') {
            $skippedAnestesi++;
            continue;
        }

        if ($kdSpsDpjp !== '' && $kdSpsTelp !== '' && $kdSpsTelp === $kdSpsDpjp) {
            $result['pengganti'] += $tarif;
            $countPengganti++;
            continue;
        }

        $result['non_dpjp'] += $tarif;
        $result['total'] += $tarif;
        $countNonDpjp++;
    }

    if ($countDpjp > 0 || $countNonDpjp > 0 || $countPengganti > 0 || $skippedAnestesi > 0) {
        $notes = [
            'Telpon DPJP ' . aptd_currency($result['dpjp']),
            'Telpon raber ' . aptd_currency($result['non_dpjp']),
            'Dihitung ' . $countNonDpjp . ' konsultasi raber',
            'Pengganti ' . aptd_currency($result['pengganti']) . ' (' . $countPengganti . ' konsultasi)',
        ];

        if ($dpjp !== '') {
            $notes[] = 'DPJP tidak dihitung';
        }

        if ($countDpjp > 0) {
            $notes[] = 'skip DPJP ' . $countDpjp;
        }

        if ($countPengganti > 0) {
            $notes[] = 'dokter pengganti spesialistik sama ' . $countPengganti;
        }

        if ($skippedAnestesi > 0) {
            $notes[] = 'skip dokter anastesi (S0008) ' . $skippedAnestesi;
        }

        $result['condition'] = implode('; ', $notes);
    }

    return $result;
}

function aptd_keu_ranap_calculate_fisio(array $row)
{
    $result = [
        'total' => 0,
        'counted' => 0,
        'skipped' => 0,
    ];

    if (empty($row['fisio_items'])) {
        return $result;
    }

    foreach (explode('|', (string) $row['fisio_items']) as $item) {
        $parts = explode('~', $item);
        if (count($parts) < 4) {
            continue;
        }

        if ($result['counted'] >= 3) {
            $result['skipped']++;
            continue;
        }

        $result['total'] += (float) $parts[0];
        $result['counted']++;
    }

    return $result;
}

function aptd_keu_ranap_calculate_anestesi_fee(array $row)
{
    if (!aptd_keu_ranap_is_operator_dpjp($row)) {
        return 0;
    }

    return (float) $row['claim'] * 0.08;
}

function aptd_keu_ranap_anestesi_condition(array $row)
{
    if (empty($row['kd_dokter_dpjp_status'])) {
        return 'Tidak ada DPJP di bridging_sep/status_dpjp';
    }

    if ((int) $row['has_operasi'] !== 1) {
        return 'Tidak ada operasi';
    }

    $operatorNames = !empty($row['operator1_names']) ? $row['operator1_names'] : '-';
    if (!aptd_keu_ranap_is_operator_dpjp($row)) {
        return 'Operator bukan DPJP: ' . $operatorNames;
    }

    $condition = 'Operator DPJP: ' . $operatorNames . ' - 8% x claim';
    if ((float) $row['claim'] <= 0) {
        $condition .= ' (CLAIM 0)';
    }

    return $condition;
}

function aptd_keu_ranap_is_operator_dpjp(array $row)
{
    if (empty($row['kd_dokter_dpjp_status']) || empty($row['operator1_codes'])) {
        return false;
    }

    $dpjp = trim((string) $row['kd_dokter_dpjp_status']);
    $operatorCodes = array_map('trim', explode('|', (string) $row['operator1_codes']));
    return in_array($dpjp, $operatorCodes, true);
}

function aptd_keu_ranap_calculate_anak_fee(array $row)
{
    $rule = aptd_keu_ranap_anak_rule($row);
    if (!$rule) {
        return 0;
    }

    if ($rule['type'] === 'percent') {
        return (float) $row['claim'] * $rule['value'];
    }

    return $rule['value'];
}

function aptd_keu_ranap_anak_condition(array $row)
{
    if (empty($row['kd_dokter_dpjp_status'])) {
        return 'Tidak ada DPJP di bridging_sep/status_dpjp';
    }

    if (isset($row['kd_sps_dpjp']) && trim((string) $row['kd_sps_dpjp']) === 'S0011') {
        return 'DPJP Spesialis Anak (S0011), JD Anak 0';
    }

    if ((int) $row['has_operasi'] !== 1) {
        return 'Tidak ada operasi';
    }

    $packages = !empty($row['jd_anak_package_names']) ? $row['jd_anak_package_names'] : '-';
    $rule = aptd_keu_ranap_anak_rule($row);

    if (!$rule) {
        return 'Tidak ada paket operasi SC/Partus';
    }

    if ($rule['type'] === 'percent') {
        $condition = 'Otomatis paket SC: ' . $packages . ' - ' . (int) ($rule['value'] * 100) . '% x claim';
        if ((float) $row['claim'] <= 0) {
            $condition .= ' (CLAIM 0)';
        }
        return $condition;
    }

    return 'Otomatis paket Partus: ' . $packages . ' - ' . aptd_currency($rule['value']);
}

function aptd_keu_ranap_anak_rule(array $row)
{
    if (empty($row['kd_dokter_dpjp_status']) || (int) $row['has_operasi'] !== 1) {
        return null;
    }

    if (isset($row['kd_sps_dpjp']) && trim((string) $row['kd_sps_dpjp']) === 'S0011') {
        return null;
    }

    if ((int) $row['has_jd_anak_sc'] === 1) {
        return ['type' => 'percent', 'value' => 0.04];
    }

    if ((int) $row['has_jd_anak_partus'] === 1) {
        return ['type' => 'fixed', 'value' => 115000];
    }

    return null;
}

function aptd_keu_ranap_sum_doctor_fee(array $row)
{
    $keys = [
        'dokter_ugd',
        'jd_dpjp',
        'jd_operator',
        'jd_anestesi',
        'jd_anak',
        'jd_visit',
        'jd_visit_pengganti',
        'jd_telpon',
        'jd_telpon_pengganti',
        'jd_usg',
        'jd_rontgen',
        'jd_lab',
        'jd_dokter_umum',
    ];

    $total = 0;
    foreach ($keys as $key) {
        $total += isset($row[$key]) ? (float) $row[$key] : 0;
    }

    return $total;
}

function aptd_keu_ranap_sum_report_cost(array $row)
{
    $supportCostKeys = [
        'jd_pa',
        'hd',
        'jk',
        'bhp',
        'lab_pk',
        'lab_pa',
        'rad_usg',
        'rontgen',
        'fisio',
        'ekg',
        'darah',
        'makan_jumlah',
        'phototherapy',
        'oksigen',
        'spirometri',
    ];

    $totalSupportCost = 0;
    foreach ($supportCostKeys as $key) {
        $totalSupportCost += isset($row[$key]) ? (float) $row[$key] : 0;
    }

    $totalDoctorFee = isset($row['total_jasa_dokter']) ? (float) $row['total_jasa_dokter'] : 0;
    $totalMedicine = isset($row['obat']) ? (float) $row['obat'] : 0;

    return $totalDoctorFee + $totalSupportCost + $totalMedicine;
}

function aptd_keu_ranap_summary(array $rows)
{
    $doctorFeeKeys = [
        'jd_dpjp',
        'dokter_ugd',
        'jd_operator',
        'jd_anestesi',
        'jd_anak',
        'jd_visit',
        'jd_visit_pengganti',
        'jd_telpon',
        'jd_telpon_pengganti',
        'jd_usg',
        'jd_rontgen',
        'jd_lab',
        'jd_pa',
        'hd',
        'fisio',
    ];

    $summary = [
        'jumlah_pasien' => count($rows),
        'total_claim' => 0,
        'total_jasa_dokter' => 0,
        'total_obat' => 0,
    ];

    foreach ($rows as $row) {
        $summary['total_claim'] += isset($row['claim']) ? (float) $row['claim'] : 0;
        $summary['total_obat'] += isset($row['obat']) ? (float) $row['obat'] : 0;

        foreach ($doctorFeeKeys as $key) {
            $summary['total_jasa_dokter'] += isset($row[$key]) ? (float) $row[$key] : 0;
        }
    }

    return $summary;
}
?>
