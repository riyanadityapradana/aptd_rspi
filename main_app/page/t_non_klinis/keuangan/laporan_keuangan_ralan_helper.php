<?php
require_once dirname(__DIR__) . '/report_helper.php';

function aptd_keu_ralan_is_valid_date($value)
{
    $date = DateTime::createFromFormat('Y-m-d', (string) $value);
    return $date && $date->format('Y-m-d') === (string) $value;
}

function aptd_keu_ralan_filters()
{
    $source = array_merge($_GET, $_POST);
    $startDate = isset($source['start_date']) && $source['start_date'] !== ''
        ? trim((string) $source['start_date'])
        : date('Y-m-01');
    $endDate = isset($source['end_date']) && $source['end_date'] !== ''
        ? trim((string) $source['end_date'])
        : date('Y-m-d');
    $kdPoli = isset($source['kd_poli']) ? trim((string) $source['kd_poli']) : '';

    $valid = true;
    $message = '';
    if (!aptd_keu_ralan_is_valid_date($startDate) || !aptd_keu_ralan_is_valid_date($endDate)) {
        $valid = false;
        $message = 'Format periode tanggal tidak valid.';
    } elseif ($endDate < $startDate) {
        $valid = false;
        $message = 'Tanggal Akhir tidak boleh lebih kecil dari Tanggal Awal.';
    }

    return [$startDate, $endDate, $kdPoli, $valid, $message];
}

function aptd_keu_ralan_fetch_poli(mysqli $mysqli)
{
    $result = $mysqli->query("SELECT kd_poli, nm_poli FROM poliklinik WHERE status = '1' ORDER BY nm_poli");
    if (!$result) {
        return [];
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

function aptd_keu_ralan_inacbg_tariff_sql()
{
    return "
        SELECT igr.no_rawat,
               CAST(TRIM(igr.tariff) AS DECIMAL(16,2)) AS tariff,
               IFNULL(igr.datetime, '1000-01-01 00:00:00') AS tariff_datetime
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

function aptd_keu_ralan_fetch_rows(mysqli $mysqli, $startDate, $endDate, $kdPoli = '', $onlyNoRawat = '')
{
    $poliWhere = $kdPoli !== '' ? ' AND rp.kd_poli = ?' : '';
    $rawatWhere = $onlyNoRawat !== '' ? ' AND rp.no_rawat = ?' : '';
    $inacbgSql = aptd_keu_ralan_inacbg_tariff_sql();
    $sql = "
        SELECT
            rp.tgl_registrasi,
            rp.no_rawat,
            rp.no_rkm_medis,
            ps.nm_pasien,
            pl.kd_poli,
            pl.nm_poli,
            d.nm_dokter,
            COALESCE(s.nm_sps, '') AS nm_sps,
            pj.png_jawab AS jenis_bayar,
            rp.stts AS status_periksa,
            rp.status_bayar,
            COALESCE(MAX(NULLIF(TRIM(bs.no_sep), '')), '') AS no_sep,
            COUNT(dp.no_rawat) AS diagnosis_count,
            COALESCE(MAX(CASE WHEN dp.prioritas = 1 THEN NULLIF(TRIM(dp.kd_penyakit), '') END), '') AS diagnosis_priority_1,
            COALESCE(MAX(CASE WHEN dp.prioritas = 2 THEN NULLIF(TRIM(dp.kd_penyakit), '') END), '') AS diagnosis_priority_2,
            COALESCE(MAX(NULLIF(TRIM(bs.diagawal), '')), '') AS diagnosis_sep,
            COALESCE(MAX(inacbg.tariff), 0) AS claim_actual,
            COALESCE(MAX(manual.claim_selected), 0) AS claim_selected_raw,
            COALESCE(MAX(manual.claim_source), '') AS claim_selected_source,
            COALESCE(MAX(manual.claim_history), 0) AS stored_claim_history,
            COALESCE(MAX(manual.claim_history_no_rawat), '') AS stored_claim_history_no_rawat,
            MAX(manual.calculated_at) AS calculated_at,
            COALESCE((
                SELECT MAX(b.totalbiaya)
                FROM billing b
                WHERE b.no_rawat = rp.no_rawat
                  AND b.status = 'Tagihan'
            ), 0) AS total_tagihan
        FROM reg_periksa rp
        INNER JOIN pasien ps ON ps.no_rkm_medis = rp.no_rkm_medis
        INNER JOIN poliklinik pl ON pl.kd_poli = rp.kd_poli
        INNER JOIN dokter d ON d.kd_dokter = rp.kd_dokter
        LEFT JOIN spesialis s ON s.kd_sps = d.kd_sps
        INNER JOIN penjab pj ON pj.kd_pj = rp.kd_pj
        LEFT JOIN bridging_sep bs ON bs.no_rawat = rp.no_rawat
        LEFT JOIN kamar_inap ki ON ki.no_rawat = rp.no_rawat
        LEFT JOIN diagnosa_pasien dp ON dp.no_rawat = rp.no_rawat
        LEFT JOIN ($inacbgSql) inacbg ON inacbg.no_rawat = rp.no_rawat
        LEFT JOIN lap_keuangan_bpjs manual ON manual.no_rawat = rp.no_rawat
        WHERE rp.tgl_registrasi BETWEEN ? AND ?
          AND rp.status_lanjut = 'Ralan'
          AND rp.kd_pj = 'BPJ'
          AND rp.stts <> 'Batal'
          AND ki.no_rawat IS NULL
          $poliWhere
          $rawatWhere
        GROUP BY
            rp.tgl_registrasi,
            rp.no_rawat,
            rp.no_rkm_medis,
            ps.nm_pasien,
            pl.kd_poli,
            pl.nm_poli,
            d.nm_dokter,
            s.nm_sps,
            pj.png_jawab,
            rp.stts,
            rp.status_bayar
        ORDER BY rp.tgl_registrasi ASC, rp.no_rawat ASC";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Query laporan tidak dapat dipersiapkan: ' . $mysqli->error);
    }

    if ($kdPoli !== '' && $onlyNoRawat !== '') {
        $stmt->bind_param('ssss', $startDate, $endDate, $kdPoli, $onlyNoRawat);
    } elseif ($kdPoli !== '') {
        $stmt->bind_param('sss', $startDate, $endDate, $kdPoli);
    } elseif ($onlyNoRawat !== '') {
        $stmt->bind_param('sss', $startDate, $endDate, $onlyNoRawat);
    } else {
        $stmt->bind_param('ss', $startDate, $endDate);
    }

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Query laporan tidak dapat dijalankan: ' . $error);
    }

    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    aptd_keu_ralan_apply_claims($mysqli, $rows);
    return $rows;
}

function aptd_keu_ralan_apply_claims(mysqli $mysqli, array &$rows)
{
    if (empty($rows)) {
        return;
    }

    $codesByPriority = [1 => [], 2 => []];
    foreach ($rows as $index => $row) {
        $priorityOne = trim((string) $row['diagnosis_priority_1']);
        $priorityTwo = trim((string) $row['diagnosis_priority_2']);
        $sepDiagnosis = trim((string) $row['diagnosis_sep']);
        $diagnosisCount = (int) $row['diagnosis_count'];

        if ($diagnosisCount === 0) {
            $targetCode = $sepDiagnosis;
            $targetPriority = 1;
            $targetSource = $targetCode !== '' ? 'SEP' : 'Belum Ada';
        } elseif ($priorityOne !== '' && strtoupper(substr($priorityOne, 0, 1)) === 'Z') {
            $targetCode = $priorityTwo;
            $targetPriority = 2;
            $targetSource = $targetCode !== '' ? 'Diagnosa Prioritas 2' : 'Belum Ada';
        } else {
            $targetCode = $priorityOne;
            $targetPriority = 1;
            $targetSource = $targetCode !== '' ? 'Diagnosa Prioritas 1' : 'Belum Ada';
        }

        $rows[$index]['target_diagnosis_code'] = $targetCode;
        $rows[$index]['target_diagnosis_priority'] = $targetPriority;
        $rows[$index]['target_diagnosis_source'] = $targetSource;
        $rows[$index]['claim_history'] = 0;
        $rows[$index]['claim_history_no_rawat'] = '';
        $rows[$index]['has_hitung'] = !empty($row['calculated_at']) ? 1 : 0;

        if ($targetCode !== '') {
            $codesByPriority[$targetPriority][strtoupper($targetCode)] = $targetCode;
            if ($targetSource === 'SEP') {
                $codesByPriority[2][strtoupper($targetCode)] = $targetCode;
            }
        }
    }

    $inacbgSql = aptd_keu_ralan_inacbg_tariff_sql();
    $historyCandidates = [];
    foreach ($codesByPriority as $priority => $codes) {
        if (empty($codes)) {
            continue;
        }

        $quotedCodes = [];
        foreach ($codes as $code) {
            $quotedCodes[] = "'" . $mysqli->real_escape_string($code) . "'";
        }

        $sql = "
            SELECT dp.kd_penyakit AS diagnosis_code,
                   dp.prioritas AS diagnosis_priority,
                   dp.no_rawat,
                   rp.tgl_registrasi,
                   tariff.tariff,
                   tariff.tariff_datetime,
                   CASE WHEN EXISTS (
                       SELECT 1
                       FROM diagnosa_pasien history_primary
                       WHERE history_primary.no_rawat = dp.no_rawat
                         AND history_primary.prioritas = 1
                         AND history_primary.status = 'Ralan'
                         AND UPPER(TRIM(history_primary.kd_penyakit)) LIKE 'Z%'
                   ) THEN 1 ELSE 0 END AS priority_one_is_z
            FROM diagnosa_pasien dp
            INNER JOIN reg_periksa rp ON rp.no_rawat = dp.no_rawat
            INNER JOIN ($inacbgSql) tariff ON tariff.no_rawat = dp.no_rawat
            WHERE dp.prioritas = " . (int) $priority . "
              AND dp.status = 'Ralan'
              AND rp.status_lanjut = 'Ralan'
              AND NOT EXISTS (
                  SELECT 1
                  FROM kamar_inap history_ki
                  WHERE history_ki.no_rawat = dp.no_rawat
              )
              AND EXISTS (
                  SELECT 1
                  FROM bridging_sep history_sep
                  WHERE history_sep.no_rawat = dp.no_rawat
                    AND history_sep.jnspelayanan = '2'
              )
              AND dp.kd_penyakit IN (" . implode(',', $quotedCodes) . ")
            ORDER BY dp.kd_penyakit ASC,
                     rp.tgl_registrasi DESC,
                     tariff.tariff_datetime DESC,
                     dp.no_rawat DESC";

        $result = $mysqli->query($sql);
        if (!$result) {
            throw new RuntimeException('Riwayat klaim tidak dapat dimuat: ' . $mysqli->error);
        }

        while ($history = $result->fetch_assoc()) {
            $key = (int) $history['diagnosis_priority'] . '|' . strtoupper(trim((string) $history['diagnosis_code']));
            if (!isset($historyCandidates[$key])) {
                $historyCandidates[$key] = [];
            }
            $historyCandidates[$key][] = [
                'no_rawat' => (string) $history['no_rawat'],
                'tariff' => (float) $history['tariff'],
                'visit_date' => (string) $history['tgl_registrasi'],
                'tariff_datetime' => (string) $history['tariff_datetime'],
                'priority_one_is_z' => (int) $history['priority_one_is_z'],
            ];
        }
    }

    foreach ($rows as $index => $row) {
        $targetCode = $rows[$index]['target_diagnosis_code'];
        $targetPriority = $rows[$index]['target_diagnosis_priority'];
        $key = $targetPriority . '|' . strtoupper($targetCode);
        $candidatePool = $targetCode !== '' && !empty($historyCandidates[$key])
            ? $historyCandidates[$key]
            : [];

        if ($targetCode !== '' && $rows[$index]['target_diagnosis_source'] === 'SEP') {
            $priorityTwoKey = '2|' . strtoupper($targetCode);
            if (!empty($historyCandidates[$priorityTwoKey])) {
                foreach ($historyCandidates[$priorityTwoKey] as $priorityTwoCandidate) {
                    if ((int) $priorityTwoCandidate['priority_one_is_z'] === 1) {
                        $candidatePool[] = $priorityTwoCandidate;
                    }
                }
            }
        }

        if (!empty($candidatePool)) {
            usort($candidatePool, function ($left, $right) {
                foreach (['visit_date', 'tariff_datetime', 'no_rawat'] as $field) {
                    $comparison = strcmp((string) $right[$field], (string) $left[$field]);
                    if ($comparison !== 0) {
                        return $comparison;
                    }
                }
                return 0;
            });

            foreach ($candidatePool as $candidate) {
                if ($candidate['no_rawat'] === $row['no_rawat'] || $candidate['tariff'] <= 0) {
                    continue;
                }
                $rows[$index]['claim_history'] = $candidate['tariff'];
                $rows[$index]['claim_history_no_rawat'] = $candidate['no_rawat'];
                break;
            }
        }

        $actual = (float) $row['claim_actual'];
        $history = (float) $rows[$index]['claim_history'];
        if ($actual > 0) {
            $rows[$index]['claim_used'] = $actual;
            $rows[$index]['claim_source'] = 'Aktual';
        } elseif ($history > 0) {
            $rows[$index]['claim_used'] = $history;
            $rows[$index]['claim_source'] = 'Riwayat';
        } else {
            $rows[$index]['claim_used'] = 0;
            $rows[$index]['claim_source'] = 'Belum Ada';
        }
    }
}

function aptd_keu_ralan_action_label(array $row)
{
    $historySelected = (float) $row['claim_selected_raw'] > 0
        && (string) $row['claim_selected_source'] === 'history_diagnose'
        && abs((float) $row['stored_claim_history'] - (float) $row['claim_history']) < 0.01
        && (string) $row['stored_claim_history_no_rawat'] === (string) $row['claim_history_no_rawat'];
    if ((float) $row['claim_actual'] <= 0 && (float) $row['claim_history'] > 0 && !$historySelected) {
        return 'Pakai Riwayat';
    }
    if ((float) $row['claim_used'] <= 0) {
        return 'Tidak Aktif';
    }
    return !empty($row['has_hitung']) ? 'Hitung Ulang' : 'Hitung';
}

function aptd_keu_ralan_store_claim(mysqli $mysqli, array $row, $username = '', $markCalculated = false)
{
    $noRawat = trim((string) $row['no_rawat']);
    $actual = (float) $row['claim_actual'];
    $history = (float) $row['claim_history'];
    $used = (float) $row['claim_used'];
    $source = $actual > 0 ? 'inacbg_current' : ($history > 0 ? 'history_diagnose' : 'none');
    $historyNoRawat = (string) $row['claim_history_no_rawat'];
    $diagnosisCode = (string) $row['target_diagnosis_code'];
    $username = trim((string) $username);
    if ($noRawat === '' || $used <= 0) {
        return ['success' => false, 'message' => 'Klaim yang dapat digunakan belum tersedia.'];
    }

    $check = $mysqli->prepare('SELECT COUNT(*) AS total FROM lap_keuangan_bpjs WHERE no_rawat = ?');
    $check->bind_param('s', $noRawat);
    $check->execute();
    $exists = ((int) $check->get_result()->fetch_assoc()['total']) > 0;
    $check->close();
    $calculatedSql = $markCalculated ? ', calculated_at = NOW()' : '';

    if ($exists) {
        $stmt = $mysqli->prepare("
            UPDATE lap_keuangan_bpjs
            SET claim_selected = ?,
                claim_source = ?,
                claim_actual = ?,
                claim_history = ?,
                claim_history_no_rawat = ?,
                claim_history_diagnose_code = ?,
                claim_selected_at = NOW(),
                claim_selected_by = ?
                $calculatedSql
            WHERE no_rawat = ?");
        $stmt->bind_param('dsddssss', $used, $source, $actual, $history, $historyNoRawat, $diagnosisCode, $username, $noRawat);
    } else {
        $calculatedColumn = $markCalculated ? ', calculated_at' : '';
        $calculatedValue = $markCalculated ? ', NOW()' : '';
        $zero = 0;
        $stmt = $mysqli->prepare("
            INSERT INTO lap_keuangan_bpjs
                (no_rawat, jum_claim, jum_jdoperator, claim_selected, claim_source, claim_actual, claim_history, claim_history_no_rawat, claim_history_diagnose_code, claim_selected_at, claim_selected_by$calculatedColumn)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?$calculatedValue)");
        $stmt->bind_param('sdddsddsss', $noRawat, $zero, $zero, $used, $source, $actual, $history, $historyNoRawat, $diagnosisCode, $username);
    }

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        error_log('Klaim Ralan tidak dapat disimpan: ' . $error);
        return ['success' => false, 'message' => 'Klaim tidak dapat disimpan. Silakan coba kembali atau hubungi administrator.'];
    }
    $stmt->close();
    return ['success' => true, 'message' => $markCalculated ? 'Data keuangan rawat jalan berhasil dihitung.' : 'Klaim riwayat berhasil dipakai. Silakan lanjutkan Hitung.'];
}

function aptd_keu_ralan_use_history_claim(mysqli $mysqli, $noRawat, $startDate, $endDate, $kdPoli = '', $username = '')
{
    $rows = aptd_keu_ralan_fetch_rows($mysqli, $startDate, $endDate, $kdPoli, trim((string) $noRawat));
    if (empty($rows)) {
        return ['success' => false, 'message' => 'Data pasien tidak ditemukan pada filter yang dipilih.'];
    }
    if ((float) $rows[0]['claim_actual'] > 0) {
        return ['success' => false, 'message' => 'Klaim aktual sudah tersedia dan otomatis menjadi klaim yang digunakan.'];
    }
    if ((float) $rows[0]['claim_history'] <= 0) {
        return ['success' => false, 'message' => 'Klaim riwayat belum tersedia untuk nomor rawat ini.'];
    }
    return aptd_keu_ralan_store_claim($mysqli, $rows[0], $username, false);
}

function aptd_keu_ralan_calculate_claim(mysqli $mysqli, $noRawat, $startDate, $endDate, $kdPoli = '', $username = '')
{
    $rows = aptd_keu_ralan_fetch_rows($mysqli, $startDate, $endDate, $kdPoli, trim((string) $noRawat));
    if (empty($rows)) {
        return ['success' => false, 'message' => 'Data pasien tidak ditemukan pada filter yang dipilih.'];
    }
    return aptd_keu_ralan_store_claim($mysqli, $rows[0], $username, true);
}

function aptd_keu_ralan_summary(array $rows)
{
    $poli = [];
    $totalTagihan = 0;
    $sudahSep = 0;
    foreach ($rows as $row) {
        $poli[$row['kd_poli']] = true;
        $totalTagihan += (float) $row['total_tagihan'];
        if (trim((string) $row['no_sep']) !== '') {
            $sudahSep++;
        }
    }

    $jumlah = count($rows);
    return [
        'jumlah_kunjungan' => $jumlah,
        'jumlah_poli' => count($poli),
        'sudah_sep' => $sudahSep,
        'total_tagihan' => $totalTagihan,
        'rata_tagihan' => $jumlah > 0 ? $totalTagihan / $jumlah : 0,
    ];
}

function aptd_keu_ralan_export_url($startDate, $endDate, $kdPoli = '')
{
    return 'page/t_non_klinis/keuangan/export_laporan_keuangan_ralan.php?'
        . aptd_keu_ralan_filter_query($startDate, $endDate, $kdPoli);
}

function aptd_keu_ralan_filter_query($startDate, $endDate, $kdPoli = '')
{
    return http_build_query([
        'start_date' => $startDate,
        'end_date' => $endDate,
        'kd_poli' => $kdPoli,
    ]);
}

function aptd_keu_ralan_xml($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function aptd_keu_ralan_excel_column($index)
{
    $name = '';
    $index++;
    while ($index > 0) {
        $index--;
        $name = chr(65 + ($index % 26)) . $name;
        $index = (int) floor($index / 26);
    }
    return $name;
}

function aptd_keu_ralan_xlsx_cell($coordinate, $value, $type = 'text', $style = 0)
{
    $styleAttribute = $style > 0 ? ' s="' . (int) $style . '"' : '';
    if ($type === 'number') {
        $number = is_numeric($value) ? (float) $value : 0;
        return '<c r="' . $coordinate . '"' . $styleAttribute . '><v>' . $number . '</v></c>';
    }

    return '<c r="' . $coordinate . '" t="inlineStr"' . $styleAttribute . '><is><t xml:space="preserve">'
        . aptd_keu_ralan_xml($value) . '</t></is></c>';
}

function aptd_keu_ralan_zip(array $files)
{
    $body = '';
    $central = '';
    $offset = 0;
    $dosTime = 0;
    $dosDate = 33;

    foreach ($files as $name => $data) {
        $name = str_replace('\\', '/', $name);
        $size = strlen($data);
        $crc = crc32($data);
        $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size, strlen($name), 0)
            . $name . $data;
        $body .= $local;

        $central .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            20,
            20,
            0,
            0,
            $dosTime,
            $dosDate,
            $crc,
            $size,
            $size,
            strlen($name),
            0,
            0,
            0,
            0,
            0,
            $offset
        ) . $name;
        $offset += strlen($local);
    }

    $count = count($files);
    return $body . $central . pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen($central), strlen($body), 0);
}

function aptd_keu_ralan_build_xlsx(array $rows, $startDate, $endDate, $poliLabel)
{
    $headers = [
        'Tanggal Kunjungan', 'Nomor Rawat', 'No. RM', 'Nama Pasien',
        'Dokter Poliklinik', 'No. SEP', 'Poliklinik', 'Spesialistik',
        'Status Periksa', 'Status Bayar', 'Jenis Bayar', 'Total Tagihan', 'Klaim Riwayat',
        'Klaim Aktual', 'Klaim Digunakan', 'Sumber', 'Aksi'
    ];

    $sheetRows = [];
    $sheetRows[] = '<row r="1" ht="24"><c r="A1" t="inlineStr" s="3"><is><t>LAPORAN KEUANGAN RAWAT JALAN (BPJS)</t></is></c></row>';
    $sheetRows[] = '<row r="2"><c r="A2" t="inlineStr"><is><t xml:space="preserve">Periode: '
        . aptd_keu_ralan_xml($startDate . ' s.d. ' . $endDate . ' | Poliklinik: ' . $poliLabel)
        . '</t></is></c></row>';

    $headerCells = '';
    foreach ($headers as $index => $header) {
        $headerCells .= aptd_keu_ralan_xlsx_cell(aptd_keu_ralan_excel_column($index) . '4', $header, 'text', 1);
    }
    $sheetRows[] = '<row r="4" ht="30">' . $headerCells . '</row>';

    $excelRow = 5;
    foreach ($rows as $row) {
        $values = [
            ['value' => $row['tgl_registrasi'], 'type' => 'text', 'style' => 0],
            ['value' => $row['no_rawat'], 'type' => 'text', 'style' => 0],
            ['value' => $row['no_rkm_medis'], 'type' => 'text', 'style' => 0],
            ['value' => $row['nm_pasien'], 'type' => 'text', 'style' => 0],
            ['value' => $row['nm_dokter'], 'type' => 'text', 'style' => 0],
            ['value' => $row['no_sep'] !== '' ? $row['no_sep'] : '-', 'type' => 'text', 'style' => 0],
            ['value' => $row['nm_poli'], 'type' => 'text', 'style' => 0],
            ['value' => $row['nm_sps'] !== '' ? $row['nm_sps'] : '-', 'type' => 'text', 'style' => 0],
            ['value' => $row['status_periksa'], 'type' => 'text', 'style' => 0],
            ['value' => $row['status_bayar'], 'type' => 'text', 'style' => 0],
            ['value' => $row['jenis_bayar'], 'type' => 'text', 'style' => 0],
            ['value' => $row['total_tagihan'], 'type' => 'number', 'style' => 2],
            ['value' => $row['claim_history'], 'type' => 'number', 'style' => 2],
            ['value' => $row['claim_actual'], 'type' => 'number', 'style' => 2],
            ['value' => $row['claim_used'], 'type' => 'number', 'style' => 2],
            ['value' => $row['claim_source'], 'type' => 'text', 'style' => 0],
            ['value' => aptd_keu_ralan_action_label($row), 'type' => 'text', 'style' => 0],
        ];

        $cells = '';
        foreach ($values as $column => $cell) {
            $cells .= aptd_keu_ralan_xlsx_cell(
                aptd_keu_ralan_excel_column($column) . $excelRow,
                $cell['value'],
                $cell['type'],
                $cell['style']
            );
        }
        $sheetRows[] = '<row r="' . $excelRow . '">' . $cells . '</row>';
        $excelRow++;
    }

    $lastDataRow = max(4, $excelRow - 1);
    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<dimension ref="A1:Q' . $lastDataRow . '"/>'
        . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="4" topLeftCell="A5" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        . '<cols><col min="1" max="1" width="18" customWidth="1"/><col min="2" max="3" width="22" customWidth="1"/>'
        . '<col min="4" max="5" width="30" customWidth="1"/><col min="6" max="6" width="24" customWidth="1"/>'
        . '<col min="7" max="7" width="28" customWidth="1"/><col min="8" max="8" width="22" customWidth="1"/>'
        . '<col min="9" max="11" width="18" customWidth="1"/><col min="12" max="15" width="20" customWidth="1"/>'
        . '<col min="16" max="17" width="18" customWidth="1"/></cols>'
        . '<sheetData>' . implode('', $sheetRows) . '</sheetData>'
        . '<mergeCells count="2"><mergeCell ref="A1:Q1"/><mergeCell ref="A2:Q2"/></mergeCells>'
        . '<autoFilter ref="A4:Q' . $lastDataRow . '"/>'
        . '</worksheet>';

    $files = [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Keuangan Ralan BPJS" sheetId="1" r:id="rId1"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
        'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0"/></numFmts><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F4E78"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border/><border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
        'xl/worksheets/sheet1.xml' => $sheetXml,
    ];

    return aptd_keu_ralan_zip($files);
}
