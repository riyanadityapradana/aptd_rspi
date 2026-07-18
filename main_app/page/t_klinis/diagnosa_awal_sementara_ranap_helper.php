<?php
require_once dirname(__DIR__) . '/t_non_klinis/report_helper.php';

function aptd_diag_awal_ranap_date_filter()
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

function aptd_diag_awal_ranap_fetch_rows(mysqli $mysqli, $startDate, $endDate)
{
    aptd_diag_awal_ranap_ensure_schema($mysqli);

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
        WHERE ki.tgl_masuk BETWEEN ? AND ?
          AND rp.status_lanjut = 'Ranap'
          AND rp.kd_pj = 'BPJ'
          AND (ki.stts_pulang IS NULL OR ki.stts_pulang = '-' OR ki.stts_pulang <> 'Pindah Kamar')
        GROUP BY rp.no_rawat, rp.no_rkm_medis, p.nm_pasien, rp.umurdaftar, rp.sttsumur
        ORDER BY tanggal_masuk ASC, rp.no_rawat ASC";

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
