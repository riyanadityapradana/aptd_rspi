<?php
require_once dirname(__DIR__) . '/t_non_klinis/report_helper.php';

function aptd_diag_awal_ranap_date_filter()
{
    $today = date('Y-m-d');
    $defaultStart = date('Y-m-01');
    $filterBy = isset($_POST['filter_by']) ? $_POST['filter_by'] : (isset($_GET['filter_by']) ? $_GET['filter_by'] : 'masuk');
    $filterBy = aptd_diag_awal_ranap_normalize_filter_by($filterBy);
    $hasDateRange = isset($_POST['start_date']) || isset($_POST['end_date']) || isset($_GET['start_date']) || isset($_GET['end_date']);

    if ($hasDateRange) {
        $startDate = isset($_POST['start_date']) ? $_POST['start_date'] : (isset($_GET['start_date']) ? $_GET['start_date'] : $defaultStart);
        $endDate = isset($_POST['end_date']) ? $_POST['end_date'] : (isset($_GET['end_date']) ? $_GET['end_date'] : $today);
    } else {
        $startDate = $defaultStart;
        $endDate = $today;
    }

    if (!aptd_diag_awal_ranap_is_valid_date($startDate)) {
        $startDate = $defaultStart;
    }

    if (!aptd_diag_awal_ranap_is_valid_date($endDate)) {
        $endDate = $today;
    }

    $month = (int) date('n', strtotime($startDate));
    $year = (int) date('Y', strtotime($startDate));
    $isValid = strtotime($endDate) >= strtotime($startDate);
    $message = $isValid ? '' : 'Tanggal Akhir tidak boleh lebih kecil dari Tanggal Awal.';

    return [$month, $year, $startDate, $endDate, $filterBy, $isValid, $message];
}

function aptd_diag_awal_ranap_is_valid_date($date)
{
    $date = trim((string) $date);
    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date;
}

function aptd_diag_awal_ranap_normalize_filter_by($filterBy)
{
    $filterBy = strtolower(trim((string) $filterBy));
    return $filterBy === 'keluar' ? 'keluar' : 'masuk';
}

function aptd_diag_awal_ranap_filter_mode_label($filterBy)
{
    return aptd_diag_awal_ranap_normalize_filter_by($filterBy) === 'keluar' ? 'Tanggal Keluar' : 'Tanggal Masuk';
}

function aptd_diag_awal_ranap_filter_range_label($startDate, $endDate)
{
    return date('d-M-Y', strtotime($startDate)) . ' s.d. ' . date('d-M-Y', strtotime($endDate));
}

function aptd_diag_awal_ranap_filter_info_label($startDate, $endDate, $filterBy)
{
    return 'Menampilkan data berdasarkan ' . aptd_diag_awal_ranap_filter_mode_label($filterBy) . ': ' . aptd_diag_awal_ranap_filter_range_label($startDate, $endDate);
}

function aptd_diag_awal_ranap_filter_query($startDate, $endDate, $filterBy)
{
    return 'start_date=' . rawurlencode($startDate) . '&end_date=' . rawurlencode($endDate) . '&filter_by=' . rawurlencode(aptd_diag_awal_ranap_normalize_filter_by($filterBy));
}

function aptd_diag_awal_ranap_date_where_sql($filterBy)
{
    if (aptd_diag_awal_ranap_normalize_filter_by($filterBy) === 'keluar') {
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

function aptd_diag_awal_ranap_ensure_schema(mysqli $mysqli)
{
    $mysqli->query("
        CREATE TABLE IF NOT EXISTS diagnosa_sementara (
            id INT(11) NOT NULL AUTO_INCREMENT,
            nomor_rawat VARCHAR(17) NOT NULL,
            no_sep VARCHAR(40) DEFAULT NULL,
            kode_icd VARCHAR(20) NOT NULL,
            created_by VARCHAR(100) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_by VARCHAR(100) DEFAULT NULL,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_nomor_rawat (nomor_rawat),
            KEY idx_no_sep (no_sep),
            KEY idx_kode_icd (kode_icd)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ");
}

function aptd_diag_awal_ranap_fetch_rows(mysqli $mysqli, $startDate, $endDate, $filterBy = 'masuk')
{
    aptd_diag_awal_ranap_ensure_schema($mysqli);
    $dateWhereSql = aptd_diag_awal_ranap_date_where_sql($filterBy);
    $orderSql = aptd_diag_awal_ranap_normalize_filter_by($filterBy) === 'keluar' ? 'tanggal_keluar ASC, rp.no_rawat ASC' : 'tanggal_masuk ASC, rp.no_rawat ASC';

    $sql = "
        SELECT
            rp.no_rawat,
            rp.no_rkm_medis,
            CONCAT(p.nm_pasien, ' (', rp.umurdaftar, ' ', rp.sttsumur, ')') AS nama_pasien_umur,
            MAX(IFNULL(bs.no_sep, '-')) AS no_sep,
            MAX(IFNULL(bs.nmdiagnosaawal, '')) AS diagnosa_sep,
            MAX(NULLIF(ki.diagnosa_awal, '')) AS diagnosa_awal,
            MAX(NULLIF(ki.diagnosa_akhir, '')) AS diagnosa_akhir,
            MIN(ki.tgl_masuk) AS tanggal_masuk,
            MAX(NULLIF(ki.tgl_keluar, '0000-00-00')) AS tanggal_keluar,
            MAX(IFNULL(ki.stts_pulang, '-')) AS status_pulang,
            GROUP_CONCAT(DISTINCT ki.kd_kamar ORDER BY ki.tgl_masuk, ki.jam_masuk SEPARATOR ', ') AS kamar,
            COALESCE(
                MAX(NULLIF(sep_dokter.nm_dokter, '')),
                MAX(NULLIF(sd_dokter.nm_dokter, '')),
                MAX(NULLIF(bs.nmdpjplayanan, '')),
                MAX(NULLIF(bs.nmdpdjp, '')),
                MAX(NULLIF(reg_dpjp.nm_dokter, ''))
            ) AS dpjp,
            COALESCE(MAX(ds.kode_icd), '') AS kode_icd,
            COALESCE(MAX(py.nm_penyakit), '') AS nama_penyakit,
            COALESCE(MAX(ds.created_by), '') AS diagnosa_created_by,
            MAX(ds.created_at) AS diagnosa_created_at,
            COALESCE(MAX(ds.updated_by), '') AS diagnosa_updated_by,
            MAX(ds.updated_at) AS diagnosa_updated_at
        FROM kamar_inap ki
        INNER JOIN reg_periksa rp ON rp.no_rawat = ki.no_rawat
        INNER JOIN pasien p ON p.no_rkm_medis = rp.no_rkm_medis
        LEFT JOIN status_dpjp sd ON sd.no_rawat = rp.no_rawat
        LEFT JOIN dokter sd_dokter ON sd_dokter.kd_dokter = sd.kd_dokter AND sd_dokter.status = '1'
        LEFT JOIN dokter reg_dpjp ON reg_dpjp.kd_dokter = rp.kd_dokter
        LEFT JOIN bridging_sep bs ON bs.no_rawat = rp.no_rawat
        LEFT JOIN maping_dokter_dpjpvclaim mdpjp ON mdpjp.kd_dokter_bpjs = NULLIF(bs.kddpjp, '')
        LEFT JOIN dokter sep_dokter ON sep_dokter.kd_dokter = mdpjp.kd_dokter AND sep_dokter.status = '1'
        LEFT JOIN diagnosa_sementara ds ON ds.nomor_rawat = rp.no_rawat
        LEFT JOIN penyakit py ON py.kd_penyakit = ds.kode_icd
        WHERE $dateWhereSql
          AND rp.status_lanjut = 'Ranap'
          AND rp.kd_pj = 'BPJ'
          AND (ki.stts_pulang IS NULL OR ki.stts_pulang = '-' OR ki.stts_pulang <> 'Pindah Kamar')
        GROUP BY rp.no_rawat, rp.no_rkm_medis, p.nm_pasien, rp.umurdaftar, rp.sttsumur
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
    return $rows;
}

function aptd_diag_awal_ranap_fetch_penyakit_options(mysqli $mysqli)
{
    $sql = "
        SELECT kd_penyakit, nm_penyakit
        FROM penyakit
        WHERE TRIM(IFNULL(kd_penyakit, '')) <> ''
          AND kd_penyakit <> '-'
          AND TRIM(IFNULL(nm_penyakit, '')) <> ''
        ORDER BY kd_penyakit ASC, nm_penyakit ASC
    ";

    $result = $mysqli->query($sql);
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    return $rows;
}

function aptd_diag_awal_ranap_save(mysqli $mysqli, $noRawat, $kodeIcd, $username)
{
    aptd_diag_awal_ranap_ensure_schema($mysqli);

    $noRawat = trim((string) $noRawat);
    $kodeIcd = trim((string) $kodeIcd);
    $username = trim((string) $username);

    if ($noRawat === '') {
        return ['success' => false, 'message' => 'No rawat tidak boleh kosong.'];
    }

    if ($kodeIcd === '') {
        return ['success' => false, 'message' => 'Kode ICD diagnosa awal sementara wajib dipilih.'];
    }

    if (strlen($kodeIcd) > 20) {
        return ['success' => false, 'message' => 'Kode ICD terlalu panjang.'];
    }

    $check = $mysqli->prepare("
        SELECT
            COUNT(*) AS total,
            MAX(IFNULL(bs.no_sep, '')) AS no_sep
        FROM kamar_inap ki
        INNER JOIN reg_periksa rp ON rp.no_rawat = ki.no_rawat
        LEFT JOIN bridging_sep bs ON bs.no_rawat = rp.no_rawat
        WHERE ki.no_rawat = ?
          AND rp.status_lanjut = 'Ranap'
          AND rp.kd_pj = 'BPJ'
    ");
    $check->bind_param('s', $noRawat);
    $check->execute();
    $rawatRow = $check->get_result()->fetch_assoc();
    $exists = $rawatRow && ((int) $rawatRow['total']) > 0;
    $noSep = $rawatRow && isset($rawatRow['no_sep']) ? trim((string) $rawatRow['no_sep']) : '';
    $check->close();

    if (!$exists) {
        return ['success' => false, 'message' => 'No rawat tidak ditemukan pada data rawat inap BPJS.'];
    }

    $icdStmt = $mysqli->prepare('SELECT kd_penyakit FROM penyakit WHERE kd_penyakit = ? LIMIT 1');
    $icdStmt->bind_param('s', $kodeIcd);
    $icdStmt->execute();
    $icdExists = $icdStmt->get_result()->fetch_assoc();
    $icdStmt->close();

    if (!$icdExists) {
        return ['success' => false, 'message' => 'Kode ICD tidak ditemukan pada master penyakit.'];
    }

    $stmt = $mysqli->prepare("
        INSERT INTO diagnosa_sementara
            (nomor_rawat, no_sep, kode_icd, created_by, created_at, updated_by, updated_at)
        VALUES
            (?, ?, ?, ?, NOW(), ?, NOW())
        ON DUPLICATE KEY UPDATE
            no_sep = VALUES(no_sep),
            kode_icd = VALUES(kode_icd),
            updated_by = VALUES(updated_by),
            updated_at = NOW()
    ");
    $stmt->bind_param('sssss', $noRawat, $noSep, $kodeIcd, $username, $username);
    $stmt->execute();
    $stmt->close();

    return ['success' => true, 'message' => 'Diagnosa awal sementara berhasil disimpan.'];
}
