<?php
require_once dirname(__DIR__) . '/report_helper.php';

function aptd_keu_ranap_date_filter()
{
    $month = isset($_POST['month']) ? (int) $_POST['month'] : (isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n'));
    $year = isset($_POST['year']) ? (int) $_POST['year'] : (isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y'));

    if ($month < 1 || $month > 12) {
        $month = (int) date('n');
    }

    if ($year < 2020 || $year > ((int) date('Y') + 1)) {
        $year = (int) date('Y');
    }

    $startDate = sprintf('%04d-%02d-01', $year, $month);
    $endDate = date('Y-m-t', strtotime($startDate));

    return [$month, $year, $startDate, $endDate];
}

function aptd_keu_ranap_export_url($month, $year)
{
    return 'page/t_analitik/keuangan/export_laporan_keuangan_ranap.php?month=' . rawurlencode($month) . '&year=' . rawurlencode($year);
}

function aptd_keu_ranap_fetch_rows(mysqli $mysqli, $startDate, $endDate)
{
    $filterSql = aptd_keu_ranap_filter_sql($mysqli, $startDate, $endDate);

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
                MAX(NULLIF(sd_dokter.nm_dokter, '')),
                MAX(NULLIF(bs.nmdpjplayanan, '')),
                MAX(NULLIF(bs.nmdpdjp, '')),
                MAX(NULLIF(reg_dpjp.nm_dokter, ''))
            ) AS dpjp,
            MAX(NULLIF(sd.kd_dokter, '')) AS kd_dokter_dpjp_status,
            MAX(NULLIF(sd_dokter.kd_sps, '')) AS kd_sps_dpjp,
            MAX(NULLIF(sps_dpjp.nm_sps, '')) AS nm_sps_dpjp,
            GROUP_CONCAT(DISTINCT ki.kd_kamar ORDER BY ki.tgl_masuk, ki.jam_masuk SEPARATOR ', ') AS kamar,
            MAX(IFNULL(bs.no_sep, '')) AS no_sep,
            MAX(IFNULL(bs.nmdiagnosaawal, '')) AS diagnosa_sep,
            COALESCE(bill.total_claim, 0) AS claim,
            COALESCE(ugd.dokter_ugd, 0) AS dokter_ugd,
            COALESCE(visit.visit_dokter, 0) AS jd_visit,
            COALESCE(telp.jd_telpon, 0) AS jd_telpon,
            COALESCE(usg.jd_usg, 0) AS jd_usg,
            COALESCE(rad.jd_rontgen, 0) AS jd_rontgen,
            COALESCE(lab.jd_lab, 0) AS jd_lab,
            COALESCE(extra.jd_pa, 0) AS jd_pa,
            COALESCE(extra.hd, 0) AS hd,
            COALESCE(extra.jk, 0) AS jk,
            COALESCE(extra.bhp, 0) AS bhp,
            COALESCE(obat.obat, 0) AS obat,
            COALESCE(lab.lab_pk, 0) AS lab_pk,
            COALESCE(extra.lab_pa, 0) AS lab_pa,
            COALESCE(usg.rad_usg, 0) AS rad_usg,
            COALESCE(rad.rontgen, 0) AS rontgen,
            COALESCE(extra.fisio, 0) AS fisio,
            COALESCE(extra.ekg, 0) AS ekg,
            COALESCE(extra.darah, 0) AS darah,
            COALESCE(makan.makan_jumlah, 0) AS makan_jumlah,
            COALESCE(makan.makan_harga, 0) AS makan_harga,
            COALESCE(makan.makan_kali, 0) AS makan_kali,
            COALESCE(extra.makan_billing, 0) AS makan_billing,
            COALESCE(extra.phototherapy, 0) AS phototherapy,
            COALESCE(extra.oksigen, 0) AS oksigen,
            COALESCE(extra.spirometri, 0) AS spirometri,
            COALESCE(extra.albumin, 0) AS albumin,
            COALESCE(ok.jd_operator, 0) AS jd_operator,
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
            COALESCE(ok.has_operator_anak, 0) AS has_operator_anak,
            COALESCE(ok.has_operator_anak_sc, 0) AS has_operator_anak_sc,
            COALESCE(ok.has_operator_anak_partus, 0) AS has_operator_anak_partus,
            COALESCE(ok.operator_anak_names, '') AS operator_anak_names,
            COALESCE(ok.operator_anak_package_names, '') AS operator_anak_package_names
        FROM kamar_inap ki
        INNER JOIN reg_periksa rp ON rp.no_rawat = ki.no_rawat
        INNER JOIN pasien p ON p.no_rkm_medis = rp.no_rkm_medis
        LEFT JOIN status_dpjp sd ON sd.no_rawat = rp.no_rawat
        LEFT JOIN dokter sd_dokter ON sd_dokter.kd_dokter = sd.kd_dokter
        LEFT JOIN spesialis sps_dpjp ON sps_dpjp.kd_sps = sd_dokter.kd_sps
        LEFT JOIN dokter reg_dpjp ON reg_dpjp.kd_dokter = rp.kd_dokter
        LEFT JOIN bridging_sep bs ON bs.no_rawat = rp.no_rawat
        LEFT JOIN (
            SELECT no_rawat, MAX(totalbiaya) AS total_claim
            FROM billing
            INNER JOIN ($filterSql) f USING (no_rawat)
            WHERE status = 'Tagihan'
            GROUP BY no_rawat
        ) bill ON bill.no_rawat = rp.no_rawat
        LEFT JOIN (
            SELECT rid.no_rawat, SUM(rid.tarif_tindakandr) AS dokter_ugd
            FROM rawat_inap_dr rid
            INNER JOIN ($filterSql) f ON f.no_rawat = rid.no_rawat
            INNER JOIN jns_perawatan_inap jpi ON jpi.kd_jenis_prw = rid.kd_jenis_prw
            WHERE rid.kd_jenis_prw LIKE '%000.13%'
            GROUP BY rid.no_rawat
        ) ugd ON ugd.no_rawat = rp.no_rawat
        LEFT JOIN (
            SELECT rid.no_rawat, SUM(rid.tarif_tindakandr) AS visit_dokter
            FROM rawat_inap_dr rid
            INNER JOIN ($filterSql) f ON f.no_rawat = rid.no_rawat
            INNER JOIN jns_perawatan_inap jpi ON jpi.kd_jenis_prw = rid.kd_jenis_prw
            WHERE jpi.nm_perawatan LIKE '%visit%'
               OR jpi.nm_perawatan LIKE '%visite%'
            GROUP BY rid.no_rawat
        ) visit ON visit.no_rawat = rp.no_rawat
        LEFT JOIN (
            SELECT rid.no_rawat, SUM(rid.tarif_tindakandr) AS jd_telpon
            FROM rawat_inap_dr rid
            INNER JOIN ($filterSql) f ON f.no_rawat = rid.no_rawat
            INNER JOIN jns_perawatan_inap jpi ON jpi.kd_jenis_prw = rid.kd_jenis_prw
            WHERE jpi.nm_perawatan LIKE '%telepon%'
               OR jpi.nm_perawatan LIKE '%telpon%'
            GROUP BY rid.no_rawat
        ) telp ON telp.no_rawat = rp.no_rawat
        LEFT JOIN (
            SELECT pr.no_rawat,
                   SUM(pr.tarif_tindakan_dokter) AS jd_usg,
                   SUM(pr.biaya) AS rad_usg
            FROM periksa_radiologi pr
            INNER JOIN ($filterSql) f ON f.no_rawat = pr.no_rawat
            INNER JOIN jns_perawatan_radiologi jpr ON jpr.kd_jenis_prw = pr.kd_jenis_prw
            WHERE jpr.nm_perawatan LIKE '%USG%'
            GROUP BY pr.no_rawat
        ) usg ON usg.no_rawat = rp.no_rawat
        LEFT JOIN (
            SELECT pr.no_rawat,
                   SUM(pr.tarif_tindakan_dokter) AS jd_rontgen,
                   SUM(pr.biaya) AS rontgen
            FROM periksa_radiologi pr
            INNER JOIN ($filterSql) f ON f.no_rawat = pr.no_rawat
            INNER JOIN jns_perawatan_radiologi jpr ON jpr.kd_jenis_prw = pr.kd_jenis_prw
            WHERE jpr.nm_perawatan NOT LIKE '%USG%'
            GROUP BY pr.no_rawat
        ) rad ON rad.no_rawat = rp.no_rawat
        LEFT JOIN (
            SELECT pl.no_rawat,
                   SUM(pl.tarif_tindakan_dokter) AS jd_lab,
                   SUM(pl.biaya) AS lab_pk
            FROM periksa_lab pl
            INNER JOIN ($filterSql) f ON f.no_rawat = pl.no_rawat
            GROUP BY pl.no_rawat
        ) lab ON lab.no_rawat = rp.no_rawat
        LEFT JOIN (
            SELECT no_rawat, SUM(total) AS obat
            FROM detail_pemberian_obat
            INNER JOIN ($filterSql) f USING (no_rawat)
            WHERE status = 'Ranap'
            GROUP BY no_rawat
        ) obat ON obat.no_rawat = rp.no_rawat
        LEFT JOIN (
            SELECT no_rawat,
                   COUNT(*) AS makan_kali,
                   0 AS makan_harga,
                   0 AS makan_jumlah
            FROM detail_beri_diet
            INNER JOIN ($filterSql) f USING (no_rawat)
            GROUP BY no_rawat
        ) makan ON makan.no_rawat = rp.no_rawat
        LEFT JOIN (
            SELECT no_rawat,
                   SUM(CASE WHEN nm_perawatan LIKE '%PA%' OR nm_perawatan LIKE '%Patologi Anatomi%' OR nm_perawatan LIKE '%Lab PA%' THEN totalbiaya ELSE 0 END) AS jd_pa,
                   SUM(CASE WHEN nm_perawatan LIKE '%HD%' OR nm_perawatan LIKE '%Hemodialisa%' THEN totalbiaya ELSE 0 END) AS hd,
                   SUM(CASE WHEN nm_perawatan LIKE '%Jasa Keperawatan%' OR nm_perawatan LIKE '%Keperawatan%' THEN totalbiaya ELSE 0 END) AS jk,
                   SUM(CASE WHEN nm_perawatan LIKE '%BHP%' THEN totalbiaya ELSE 0 END) AS bhp,
                   SUM(CASE WHEN nm_perawatan LIKE '%Lab PA%' OR nm_perawatan LIKE '%Patologi Anatomi%' THEN totalbiaya ELSE 0 END) AS lab_pa,
                   SUM(CASE WHEN nm_perawatan LIKE '%Fisio%' OR nm_perawatan LIKE '%Fisioterapi%' THEN totalbiaya ELSE 0 END) AS fisio,
                   SUM(CASE WHEN nm_perawatan LIKE '%EKG%' THEN totalbiaya ELSE 0 END) AS ekg,
                   SUM(CASE WHEN nm_perawatan LIKE '%Darah%' OR nm_perawatan LIKE '%Transfusi%' THEN totalbiaya ELSE 0 END) AS darah,
                   SUM(CASE WHEN nm_perawatan LIKE '%Makan%' OR nm_perawatan LIKE '%Diet%' THEN totalbiaya ELSE 0 END) AS makan_billing,
                   SUM(CASE WHEN nm_perawatan LIKE '%Photo%terap%' OR nm_perawatan LIKE '%Fototerap%' OR nm_perawatan LIKE '%Phototherapy%' THEN totalbiaya ELSE 0 END) AS phototherapy,
                   SUM(CASE WHEN nm_perawatan LIKE '%Oksigen%' OR nm_perawatan LIKE '%Oxygen%' THEN totalbiaya ELSE 0 END) AS oksigen,
                   SUM(CASE WHEN nm_perawatan LIKE '%Spirometri%' OR nm_perawatan LIKE '%Spirometry%' THEN totalbiaya ELSE 0 END) AS spirometri,
                   SUM(CASE WHEN nm_perawatan LIKE '%Albumin%' THEN totalbiaya ELSE 0 END) AS albumin
            FROM billing
            INNER JOIN ($filterSql) f USING (no_rawat)
            WHERE status <> 'Tagihan'
            GROUP BY no_rawat
        ) extra ON extra.no_rawat = rp.no_rawat
        LEFT JOIN (
            SELECT o.no_rawat,
                   SUM(o.biayaoperator1 + o.biayaoperator2 + o.biayaoperator3) AS jd_operator,
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
                   MAX(CASE WHEN d_operator1.kd_sps = 'S0011' THEN 1 ELSE 0 END) AS has_operator_anak,
                   MAX(CASE WHEN d_operator1.kd_sps = 'S0011' AND po.nm_perawatan LIKE '%SC%' THEN 1 ELSE 0 END) AS has_operator_anak_sc,
                   MAX(CASE WHEN d_operator1.kd_sps = 'S0011' AND po.nm_perawatan LIKE '%Partus%' THEN 1 ELSE 0 END) AS has_operator_anak_partus,
                   GROUP_CONCAT(DISTINCT CASE WHEN d_operator1.kd_sps = 'S0011' THEN COALESCE(NULLIF(d_operator1.nm_dokter, ''), NULLIF(TRIM(o.operator1), '')) END ORDER BY o.tgl_operasi SEPARATOR ', ') AS operator_anak_names,
                   GROUP_CONCAT(DISTINCT CASE WHEN d_operator1.kd_sps = 'S0011' THEN po.nm_perawatan END ORDER BY o.tgl_operasi SEPARATOR ', ') AS operator_anak_package_names
            FROM operasi o
            INNER JOIN ($filterSql) f ON f.no_rawat = o.no_rawat
            LEFT JOIN paket_operasi po ON po.kode_paket = o.kode_paket
            LEFT JOIN dokter d_operator1 ON d_operator1.kd_dokter = o.operator1
            GROUP BY o.no_rawat
        ) ok ON ok.no_rawat = rp.no_rawat
        WHERE ki.tgl_masuk BETWEEN ? AND ?
          AND rp.status_lanjut = 'Ranap'
          AND rp.kd_pj = 'BPJ'
          AND (ki.stts_pulang IS NULL OR ki.stts_pulang = '-' OR ki.stts_pulang <> 'Pindah Kamar')
        GROUP BY rp.no_rawat, rp.no_rkm_medis, p.nm_pasien, rp.umurdaftar, rp.sttsumur,
                 bill.total_claim, ugd.dokter_ugd, visit.visit_dokter, telp.jd_telpon, usg.jd_usg, rad.jd_rontgen,
                 lab.jd_lab, extra.jd_pa, extra.hd, extra.jk, extra.bhp, obat.obat, lab.lab_pk, extra.lab_pa,
                 usg.rad_usg, rad.rontgen, extra.fisio, extra.ekg, extra.darah, makan.makan_jumlah, makan.makan_harga,
                 makan.makan_kali, extra.makan_billing, extra.phototherapy, extra.oksigen, extra.spirometri, extra.albumin,
                 ok.jd_operator, ok.jd_anestesi, ok.jd_anak, ok.jd_dokter_umum, ok.has_operasi, ok.has_partus,
                 ok.has_phaco, ok.has_phaco_anestesi, ok.has_phaco_tanpa_anestesi, ok.operator1_codes, ok.operator1_names,
                 ok.has_operator_anak, ok.has_operator_anak_sc, ok.has_operator_anak_partus, ok.operator_anak_names,
                 ok.operator_anak_package_names
        ORDER BY tanggal_masuk ASC, rp.no_rawat ASC";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $row['jd_dpjp'] = aptd_keu_ranap_calculate_dpjp_fee($row);
        $row['ket_dpjp'] = aptd_keu_ranap_dpjp_condition($row);
        $row['jd_anestesi'] = aptd_keu_ranap_calculate_anestesi_fee($row);
        $row['ket_anestesi'] = aptd_keu_ranap_anestesi_condition($row);
        $row['jd_anak'] = aptd_keu_ranap_calculate_anak_fee($row);
        $row['ket_anak'] = aptd_keu_ranap_anak_condition($row);
        if ((float) $row['makan_jumlah'] <= 0 && isset($row['makan_billing'])) {
            $row['makan_jumlah'] = (float) $row['makan_billing'];
        }
        if ((float) $row['makan_harga'] <= 0 && (float) $row['makan_jumlah'] > 0 && (int) $row['makan_kali'] > 0) {
            $row['makan_harga'] = (float) $row['makan_jumlah'] / (int) $row['makan_kali'];
        }
        $row['total_jasa_dokter'] = aptd_keu_ranap_sum_doctor_fee($row);
        $row['total_biaya_laporan'] = aptd_keu_ranap_sum_report_cost($row);
        $row['margin'] = (float) $row['claim'] - (float) $row['total_biaya_laporan'];
        $row['ket_darah'] = (float) $row['darah'] > 0 ? '1' : '';
        $row['ket_albumin'] = (float) $row['albumin'] > 0 ? '1' : '';
        $row['ket_tindakan'] = (float) $row['total_jasa_dokter'] > 0 ? '1' : '';
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function aptd_keu_ranap_filter_sql(mysqli $mysqli, $startDate, $endDate)
{
    $startDate = $mysqli->real_escape_string($startDate);
    $endDate = $mysqli->real_escape_string($endDate);

    return "
        SELECT DISTINCT ki.no_rawat
        FROM kamar_inap ki
        INNER JOIN reg_periksa rp ON rp.no_rawat = ki.no_rawat
        WHERE ki.tgl_masuk BETWEEN '$startDate' AND '$endDate'
          AND rp.status_lanjut = 'Ranap'
          AND rp.kd_pj = 'BPJ'
          AND (ki.stts_pulang IS NULL OR ki.stts_pulang = '-' OR ki.stts_pulang <> 'Pindah Kamar')";
}

function aptd_keu_ranap_calculate_dpjp_fee(array $row)
{
    $claim = (float) $row['claim'];
    $rule = aptd_keu_ranap_dpjp_rule($row);

    return $rule ? $claim * $rule['rate'] : 0;
}

function aptd_keu_ranap_dpjp_rules()
{
    return [
        'S0001' => 0.19, // Obgyn default operasi; partus 25% perlu kondisi terpisah.
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

        return null;
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
        return 'Tidak ada status_dpjp';
    }

    if (empty($row['kd_sps_dpjp'])) {
        return 'Dokter status_dpjp tanpa kd_sps';
    }

    $specialist = !empty($row['nm_sps_dpjp']) ? $row['nm_sps_dpjp'] : 'Spesialis';
    $label = $specialist . ' (' . $row['kd_sps_dpjp'] . ')';
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

    return $condition;
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
        return 'Tidak ada status_dpjp';
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
        return 'Tidak ada status_dpjp';
    }

    if ((int) $row['has_operasi'] !== 1) {
        return 'Tidak ada operasi';
    }

    if (aptd_keu_ranap_is_operator_dpjp($row)) {
        return 'DPJP sama dengan operator1';
    }

    $operatorNames = !empty($row['operator1_names']) ? $row['operator1_names'] : '-';
    if ((int) $row['has_operator_anak'] !== 1) {
        return 'Operator1 bukan Spesialis Anak: ' . $operatorNames;
    }

    $anakNames = !empty($row['operator_anak_names']) ? $row['operator_anak_names'] : $operatorNames;
    $packages = !empty($row['operator_anak_package_names']) ? $row['operator_anak_package_names'] : '-';
    $rule = aptd_keu_ranap_anak_rule($row);

    if (!$rule) {
        return 'Operator Anak, bukan SC/Partus: ' . $anakNames . ' - ' . $packages;
    }

    if ($rule['type'] === 'percent') {
        $condition = 'Operator Anak + SC: ' . $anakNames . ' - ' . (int) ($rule['value'] * 100) . '% x claim';
        if ((float) $row['claim'] <= 0) {
            $condition .= ' (CLAIM 0)';
        }
        return $condition;
    }

    return 'Operator Anak + Partus: ' . $anakNames . ' - ' . aptd_currency($rule['value']);
}

function aptd_keu_ranap_anak_rule(array $row)
{
    if (empty($row['kd_dokter_dpjp_status']) || (int) $row['has_operasi'] !== 1) {
        return null;
    }

    if (aptd_keu_ranap_is_operator_dpjp($row) || (int) $row['has_operator_anak'] !== 1) {
        return null;
    }

    if ((int) $row['has_operator_anak_sc'] === 1) {
        return ['type' => 'percent', 'value' => 0.04];
    }

    if ((int) $row['has_operator_anak_partus'] === 1) {
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
        'jd_telpon',
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
    $keys = [
        'total_jasa_dokter',
        'jd_pa',
        'hd',
        'jk',
        'bhp',
        'obat',
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

    $total = 0;
    foreach ($keys as $key) {
        $total += isset($row[$key]) ? (float) $row[$key] : 0;
    }

    return $total;
}

function aptd_keu_ranap_summary(array $rows)
{
    $summary = [
        'jumlah_pasien' => count($rows),
        'total_claim' => 0,
        'total_jasa_dokter' => 0,
        'total_lab' => 0,
        'total_radiologi' => 0,
    ];

    foreach ($rows as $row) {
        $summary['total_claim'] += (float) $row['claim'];
        $summary['total_jasa_dokter'] += (float) $row['total_jasa_dokter'];
        $summary['total_lab'] += (float) $row['jd_lab'];
        $summary['total_radiologi'] += (float) $row['jd_usg'] + (float) $row['jd_rontgen'];
    }

    return $summary;
}
?>
