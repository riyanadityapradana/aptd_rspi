<?php

function aptd_prmrj_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function aptd_prmrj_valid_date($value)
{
    $value = trim((string) $value);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches)) {
        return false;
    }

    return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
}

function aptd_prmrj_period_from_request()
{
    $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
    $source = $method === 'POST' ? $_POST : $_GET;
    $defaultStart = date('Y-m-01');
    $defaultEnd = date('Y-m-d');
    $start = isset($source['tanggal_awal']) && trim((string) $source['tanggal_awal']) !== ''
        ? trim((string) $source['tanggal_awal'])
        : $defaultStart;
    $end = isset($source['tanggal_akhir']) && trim((string) $source['tanggal_akhir']) !== ''
        ? trim((string) $source['tanggal_akhir'])
        : $defaultEnd;

    if (!aptd_prmrj_valid_date($start) || !aptd_prmrj_valid_date($end)) {
        return [
            'valid' => false,
            'tanggal_awal' => $start,
            'tanggal_akhir' => $end,
            'message' => 'Format tanggal tidak valid.',
        ];
    }

    if ($start > $end) {
        return [
            'valid' => false,
            'tanggal_awal' => $start,
            'tanggal_akhir' => $end,
            'message' => 'Tanggal akhir tidak boleh lebih kecil dari tanggal awal.',
        ];
    }

    return [
        'valid' => true,
        'tanggal_awal' => $start,
        'tanggal_akhir' => $end,
        'message' => '',
    ];
}

function aptd_prmrj_search_from_request()
{
    $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
    $source = $method === 'POST' ? $_POST : $_GET;
    $search = isset($source['q']) ? trim((string) $source['q']) : '';
    return substr($search, 0, 100);
}

function aptd_prmrj_fetch_summary($mysqli, $start, $end, $search, $page, $perPage)
{
    $startedAt = microtime(true);
    $page = max(1, (int) $page);
    $perPage = max(1, min(100, (int) $perPage));
    $offset = ($page - 1) * $perPage;
    $like = '%' . $search . '%';
    $baseWhere = "
        r.tgl_registrasi >= ?
        AND r.tgl_registrasi < DATE_ADD(?, INTERVAL 1 DAY)
        AND EXISTS (
            SELECT 1
            FROM status_prmrj_soap s
            WHERE s.no_rawat = r.no_rawat
        )";

    $totalSql = "
        SELECT COUNT(*) AS total_pasien, COALESCE(SUM(x.jumlah_kunjungan), 0) AS total_kunjungan
        FROM (
            SELECT r.no_rkm_medis, COUNT(DISTINCT r.no_rawat) AS jumlah_kunjungan
            FROM reg_periksa r
            WHERE {$baseWhere}
            GROUP BY r.no_rkm_medis
        ) x";
    $totalStmt = $mysqli->prepare($totalSql);
    $totalStmt->bind_param('ss', $start, $end);
    $totalStmt->execute();
    $totals = $totalStmt->get_result()->fetch_assoc();
    $totalStmt->close();

    $searchWhere = $baseWhere . "
        AND (? = '' OR r.no_rkm_medis LIKE ? OR p.nm_pasien LIKE ?)";
    $filteredSql = "
        SELECT COUNT(*) AS total
        FROM (
            SELECT r.no_rkm_medis
            FROM reg_periksa r
            INNER JOIN pasien p ON p.no_rkm_medis = r.no_rkm_medis
            WHERE {$searchWhere}
            GROUP BY r.no_rkm_medis
        ) x";
    $filteredStmt = $mysqli->prepare($filteredSql);
    $filteredStmt->bind_param('sssss', $start, $end, $search, $like, $like);
    $filteredStmt->execute();
    $filteredRow = $filteredStmt->get_result()->fetch_assoc();
    $filteredStmt->close();
    $filteredTotal = (int) $filteredRow['total'];

    $lastPage = max(1, (int) ceil($filteredTotal / $perPage));
    if ($page > $lastPage) {
        $page = $lastPage;
        $offset = ($page - 1) * $perPage;
    }

    $rowsSql = "
        SELECT
            r.no_rkm_medis,
            p.nm_pasien,
            COUNT(DISTINCT r.no_rawat) AS jumlah_kunjungan,
            MAX(r.tgl_registrasi) AS kunjungan_terakhir
        FROM reg_periksa r
        INNER JOIN pasien p ON p.no_rkm_medis = r.no_rkm_medis
        WHERE {$searchWhere}
        GROUP BY r.no_rkm_medis, p.nm_pasien
        ORDER BY kunjungan_terakhir DESC, r.no_rkm_medis ASC
        LIMIT ? OFFSET ?";
    $rowsStmt = $mysqli->prepare($rowsSql);
    $rowsStmt->bind_param('sssssii', $start, $end, $search, $like, $like, $perPage, $offset);
    $rowsStmt->execute();
    $result = $rowsStmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $row['jumlah_kunjungan'] = (int) $row['jumlah_kunjungan'];
        $rows[] = $row;
    }
    $rowsStmt->close();

    return [
        'rows' => $rows,
        'total_pasien' => (int) $totals['total_pasien'],
        'total_kunjungan' => (int) $totals['total_kunjungan'],
        'filtered_total' => $filteredTotal,
        'page' => $page,
        'last_page' => $lastPage,
        'per_page' => $perPage,
        'query_seconds' => microtime(true) - $startedAt,
    ];
}

function aptd_prmrj_fetch_parent_medical_records($mysqli, $start, $end)
{
    $sql = "
        SELECT r.no_rkm_medis
        FROM reg_periksa r
        WHERE r.tgl_registrasi >= ?
          AND r.tgl_registrasi < DATE_ADD(?, INTERVAL 1 DAY)
          AND EXISTS (
              SELECT 1
              FROM status_prmrj_soap s
              WHERE s.no_rawat = r.no_rawat
          )
        GROUP BY r.no_rkm_medis
        ORDER BY r.no_rkm_medis ASC";
    $statement = $mysqli->prepare($sql);
    $statement->bind_param('ss', $start, $end);
    $statement->execute();
    $result = $statement->get_result();
    $medicalRecords = [];
    while ($row = $result->fetch_assoc()) {
        $medicalRecords[] = (string) $row['no_rkm_medis'];
    }
    $statement->close();

    return $medicalRecords;
}

function aptd_prmrj_bind_string_values($statement, array &$values)
{
    $parameters = [str_repeat('s', count($values))];
    foreach ($values as $index => $value) {
        $parameters[] = &$values[$index];
    }
    call_user_func_array([$statement, 'bind_param'], $parameters);
}

function aptd_prmrj_format_quantity($quantity)
{
    $quantity = (float) $quantity;
    if (abs($quantity - round($quantity)) < 0.00001) {
        return number_format($quantity, 0, ',', '.');
    }

    return rtrim(rtrim(number_format($quantity, 2, ',', '.'), '0'), ',');
}

function aptd_prmrj_fetch_latest_details_for_medical_records($mysqli, array $medicalRecords, $limitPerPatient = 5)
{
    $limitPerPatient = max(1, min(20, (int) $limitPerPatient));
    $normalizedMedicalRecords = [];
    foreach ($medicalRecords as $medicalRecord) {
        $medicalRecord = trim((string) $medicalRecord);
        if ($medicalRecord !== '') {
            $normalizedMedicalRecords[$medicalRecord] = $medicalRecord;
        }
    }
    $medicalRecords = array_values($normalizedMedicalRecords);
    if (empty($medicalRecords)) {
        return [];
    }

    $candidateVisits = [];
    foreach (array_chunk($medicalRecords, 200) as $medicalRecordChunk) {
        $placeholders = implode(',', array_fill(0, count($medicalRecordChunk), '?'));
        $sql = "
            SELECT DISTINCT
                r.no_rawat,
                r.no_rkm_medis,
                p.nm_pasien,
                r.tgl_registrasi,
                r.jam_reg,
                pr.tgl_perawatan,
                pr.jam_rawat,
                pr.keluhan,
                pr.penilaian,
                pr.rtl,
                pr.instruksi,
                COALESCE(NULLIF(d_pr.nm_dokter, ''), NULLIF(d_reg.nm_dokter, ''), 'Belum teridentifikasi') AS dpjp,
                CASE WHEN d_pr.kd_dokter IS NULL THEN 0 ELSE 1 END AS pemeriksa_adalah_dokter,
                (
                    SELECT MAX(s_time.jam_rawat)
                    FROM status_prmrj_soap s_time
                    WHERE s_time.no_rawat = r.no_rawat
                ) AS jam_flag_prmrj
            FROM reg_periksa r
            INNER JOIN pasien p ON p.no_rkm_medis = r.no_rkm_medis
            LEFT JOIN pemeriksaan_ralan pr ON pr.no_rawat = r.no_rawat
            LEFT JOIN dokter d_pr ON d_pr.kd_dokter = pr.nip
            LEFT JOIN dokter d_reg ON d_reg.kd_dokter = r.kd_dokter
            WHERE r.no_rkm_medis IN ({$placeholders})
              AND EXISTS (
                  SELECT 1
                  FROM status_prmrj_soap s_filter
                  WHERE s_filter.no_rawat = r.no_rawat
              )
            ORDER BY
                r.no_rkm_medis ASC,
                r.no_rawat ASC,
                pemeriksa_adalah_dokter DESC,
                COALESCE(NULLIF(pr.tgl_perawatan, '0000-00-00'), r.tgl_registrasi) DESC,
                COALESCE(pr.jam_rawat, r.jam_reg) DESC";
        $statement = $mysqli->prepare($sql);
        aptd_prmrj_bind_string_values($statement, $medicalRecordChunk);
        $statement->execute();
        $result = $statement->get_result();

        while ($row = $result->fetch_assoc()) {
            $medicalRecord = (string) $row['no_rkm_medis'];
            $noRawat = (string) $row['no_rawat'];
            if (isset($candidateVisits[$medicalRecord][$noRawat])) {
                continue;
            }

            $visitDate = $row['tgl_perawatan'] && $row['tgl_perawatan'] !== '0000-00-00'
                ? $row['tgl_perawatan']
                : $row['tgl_registrasi'];
            $visitTime = $row['jam_rawat'] ?: ($row['jam_flag_prmrj'] ?: $row['jam_reg']);
            $candidateVisits[$medicalRecord][$noRawat] = [
                'no_rawat' => $noRawat,
                'no_rkm_medis' => $medicalRecord,
                'nm_pasien' => (string) $row['nm_pasien'],
                'tgl_kunjungan' => (string) $visitDate,
                'jam_kunjungan' => (string) $visitTime,
                'keluhan' => trim((string) $row['keluhan']),
                'penilaian' => trim((string) $row['penilaian']),
                'rtl' => trim((string) $row['rtl']),
                'instruksi' => trim((string) $row['instruksi']),
                'dpjp' => (string) $row['dpjp'],
            ];
        }
        $statement->close();
    }

    $selectedVisits = [];
    $selectedNoRawat = [];
    foreach ($medicalRecords as $medicalRecord) {
        $patientVisits = isset($candidateVisits[$medicalRecord])
            ? array_values($candidateVisits[$medicalRecord])
            : [];
        usort($patientVisits, function ($left, $right) {
            $leftDateTime = $left['tgl_kunjungan'] . ' ' . $left['jam_kunjungan'];
            $rightDateTime = $right['tgl_kunjungan'] . ' ' . $right['jam_kunjungan'];
            $comparison = strcmp($rightDateTime, $leftDateTime);
            return $comparison !== 0 ? $comparison : strcmp($right['no_rawat'], $left['no_rawat']);
        });
        $patientVisits = array_slice($patientVisits, 0, $limitPerPatient);
        foreach ($patientVisits as $visit) {
            $selectedVisits[$medicalRecord][] = $visit;
            $selectedNoRawat[$visit['no_rawat']] = $visit['no_rawat'];
        }
    }

    $diagnosisMap = [];
    $medicineMap = [];
    foreach (array_chunk(array_values($selectedNoRawat), 500) as $noRawatChunk) {
        $placeholders = implode(',', array_fill(0, count($noRawatChunk), '?'));
        $diagnosisSql = "
            SELECT DISTINCT dp.no_rawat, dp.kd_penyakit, py.nm_penyakit, dp.prioritas
            FROM diagnosa_pasien dp
            INNER JOIN penyakit py ON py.kd_penyakit = dp.kd_penyakit
            WHERE dp.status = 'Ralan'
              AND dp.no_rawat IN ({$placeholders})
            ORDER BY dp.no_rawat, CASE WHEN dp.prioritas > 0 THEN dp.prioritas ELSE 999 END, dp.kd_penyakit";
        $diagnosisStmt = $mysqli->prepare($diagnosisSql);
        aptd_prmrj_bind_string_values($diagnosisStmt, $noRawatChunk);
        $diagnosisStmt->execute();
        $diagnosisResult = $diagnosisStmt->get_result();
        while ($row = $diagnosisResult->fetch_assoc()) {
            $diagnosisMap[$row['no_rawat']][] = trim($row['kd_penyakit'] . ' - ' . $row['nm_penyakit']);
        }
        $diagnosisStmt->close();

        $medicineSql = "
            SELECT dpo.no_rawat, dpo.kode_brng, db.nama_brng, SUM(dpo.jml) AS jumlah
            FROM detail_pemberian_obat dpo
            INNER JOIN databarang db ON db.kode_brng = dpo.kode_brng
            WHERE dpo.status = 'Ralan'
              AND dpo.no_rawat IN ({$placeholders})
            GROUP BY dpo.no_rawat, dpo.kode_brng, db.nama_brng
            ORDER BY dpo.no_rawat, db.nama_brng";
        $medicineStmt = $mysqli->prepare($medicineSql);
        aptd_prmrj_bind_string_values($medicineStmt, $noRawatChunk);
        $medicineStmt->execute();
        $medicineResult = $medicineStmt->get_result();
        while ($row = $medicineResult->fetch_assoc()) {
            $medicineMap[$row['no_rawat']][] = aptd_prmrj_format_quantity($row['jumlah']) . ' x ' . trim((string) $row['nama_brng']);
        }
        $medicineStmt->close();
    }

    $details = [];
    foreach ($medicalRecords as $medicalRecord) {
        if (empty($selectedVisits[$medicalRecord])) {
            continue;
        }
        foreach ($selectedVisits[$medicalRecord] as $visit) {
            $visit['diagnosa'] = isset($diagnosisMap[$visit['no_rawat']]) ? $diagnosisMap[$visit['no_rawat']] : [];
            $visit['obat'] = isset($medicineMap[$visit['no_rawat']]) ? $medicineMap[$visit['no_rawat']] : [];
            $details[] = $visit;
        }
    }

    return $details;
}

function aptd_prmrj_fetch_latest_details($mysqli, $medicalRecord, $limit = 5)
{
    return aptd_prmrj_fetch_latest_details_for_medical_records($mysqli, [$medicalRecord], $limit);
}

function aptd_prmrj_group_details(array $details)
{
    $groups = [];
    foreach ($details as $detail) {
        $medicalRecord = $detail['no_rkm_medis'];
        if (!isset($groups[$medicalRecord])) {
            $groups[$medicalRecord] = [
                'no_rkm_medis' => $medicalRecord,
                'nm_pasien' => $detail['nm_pasien'],
                'visits' => [],
            ];
        }
        $groups[$medicalRecord]['visits'][] = $detail;
    }

    return array_values($groups);
}

function aptd_prmrj_empty_text($value)
{
    $value = trim((string) $value);
    return $value === '' ? '-' : $value;
}
