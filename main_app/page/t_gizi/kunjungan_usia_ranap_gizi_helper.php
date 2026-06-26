<?php

function aptd_gizi_usia_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function aptd_gizi_usia_categories()
{
    return [
        'semua' => 'Semua',
        'balita' => 'Balita 0-5 Tahun',
        'anak' => 'Anak-Anak (0-12)',
        'remaja' => 'Remaja (13-17)',
        'dewasa' => 'Dewasa (18-59)',
        'lansia' => 'Lanjut Usia (60+)',
    ];
}

function aptd_gizi_usia_penjab_list()
{
    return [
        'A09' => 'UMUM',
        'BPJ' => 'BPJS',
        'A92' => 'ASURANSI',
        'A96' => 'Pancar Tour',
    ];
}

function aptd_gizi_usia_filter_from_request()
{
    $filters = [
        'tgl_awal' => isset($_POST['tgl_awal']) && $_POST['tgl_awal'] !== '' ? trim($_POST['tgl_awal']) : date('Y-m-01'),
        'tgl_akhir' => isset($_POST['tgl_akhir']) && $_POST['tgl_akhir'] !== '' ? trim($_POST['tgl_akhir']) : date('Y-m-d'),
        'stts' => isset($_POST['stts']) ? trim($_POST['stts']) : 'semua',
        'usia' => isset($_POST['usia']) ? trim($_POST['usia']) : 'semua',
        'jenis_bayar' => isset($_POST['jenis_bayar']) ? trim($_POST['jenis_bayar']) : 'semua',
        'page' => isset($_POST['halaman']) ? (int) $_POST['halaman'] : 1,
    ];

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['tgl_awal']) || strtotime($filters['tgl_awal']) === false) {
        $filters['tgl_awal'] = date('Y-m-01');
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['tgl_akhir']) || strtotime($filters['tgl_akhir']) === false) {
        $filters['tgl_akhir'] = date('Y-m-d');
    }

    if ($filters['tgl_awal'] > $filters['tgl_akhir']) {
        $temp = $filters['tgl_awal'];
        $filters['tgl_awal'] = $filters['tgl_akhir'];
        $filters['tgl_akhir'] = $temp;
    }

    if (!array_key_exists($filters['usia'], aptd_gizi_usia_categories())) {
        $filters['usia'] = 'semua';
    }

    if ($filters['jenis_bayar'] !== 'semua' && !array_key_exists($filters['jenis_bayar'], aptd_gizi_usia_penjab_list())) {
        $filters['jenis_bayar'] = 'semua';
    }

    if ($filters['page'] < 1) {
        $filters['page'] = 1;
    }

    return $filters;
}

function aptd_gizi_usia_build_where($filters)
{
    $whereParts = [
        "r.status_lanjut = 'Ranap'",
        "r.tgl_registrasi BETWEEN ? AND ?",
        "ki.stts_pulang NOT IN ('Pindah Kamar', '-', '')",
        "ki.stts_pulang IS NOT NULL",
    ];
    $types = 'ss';
    $params = [$filters['tgl_awal'], $filters['tgl_akhir']];

    if ($filters['stts'] !== 'semua') {
        $whereParts[] = 'ki.stts_pulang = ?';
        $types .= 's';
        $params[] = $filters['stts'];
    }

    switch ($filters['usia']) {
        case 'balita':
            $whereParts[] = 'TIMESTAMPDIFF(YEAR, p.tgl_lahir, r.tgl_registrasi) BETWEEN 0 AND 5';
            break;
        case 'anak':
            $whereParts[] = 'TIMESTAMPDIFF(YEAR, p.tgl_lahir, r.tgl_registrasi) BETWEEN 0 AND 12';
            break;
        case 'remaja':
            $whereParts[] = 'TIMESTAMPDIFF(YEAR, p.tgl_lahir, r.tgl_registrasi) BETWEEN 13 AND 17';
            break;
        case 'dewasa':
            $whereParts[] = 'TIMESTAMPDIFF(YEAR, p.tgl_lahir, r.tgl_registrasi) BETWEEN 18 AND 59';
            break;
        case 'lansia':
            $whereParts[] = 'TIMESTAMPDIFF(YEAR, p.tgl_lahir, r.tgl_registrasi) >= 60';
            break;
    }

    if ($filters['jenis_bayar'] !== 'semua') {
        $whereParts[] = 'r.kd_pj = ?';
        $types .= 's';
        $params[] = $filters['jenis_bayar'];
    }

    return [
        'clause' => implode(' AND ', $whereParts),
        'types' => $types,
        'params' => $params,
    ];
}

function aptd_gizi_usia_base_sql($whereClause)
{
    return "
        FROM pasien p
        INNER JOIN reg_periksa r ON p.no_rkm_medis = r.no_rkm_medis
        INNER JOIN kamar_inap ki ON r.no_rawat = ki.no_rawat
        INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar
        INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
        LEFT JOIN penjab j ON r.kd_pj = j.kd_pj
        WHERE $whereClause
    ";
}

function aptd_gizi_usia_count($conn, $filters)
{
    $where = aptd_gizi_usia_build_where($filters);
    $sql = 'SELECT COUNT(DISTINCT r.no_rawat) AS total ' . aptd_gizi_usia_base_sql($where['clause']);

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die('Query prepare gagal: ' . $conn->error);
    }

    $stmt->bind_param($where['types'], ...$where['params']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return isset($row['total']) ? (int) $row['total'] : 0;
}

function aptd_gizi_usia_fetch($conn, $filters, $limit = null, $offset = 0)
{
    $where = aptd_gizi_usia_build_where($filters);
    $sql = "
        SELECT
            IFNULL(p.no_rkm_medis, '-') AS no_rm,
            IFNULL(p.nm_pasien, '-') AS nama_pasien,
            IFNULL(r.no_rawat, '-') AS no_rawat,
            IFNULL(p.tgl_lahir, '-') AS tgl_lahir,
            IFNULL(r.tgl_registrasi, '-') AS tgl_registrasi,
            IFNULL(r.umurdaftar, '-') AS umur_daftar,
            IFNULL(r.sttsumur, '-') AS status_umur,
            TIMESTAMPDIFF(YEAR, p.tgl_lahir, r.tgl_registrasi) AS usia_tahun,
            IFNULL(k.kd_kamar, '-') AS kode_kamar,
            IFNULL(b.nm_bangsal, '-') AS nama_bangsal,
            IFNULL(ki.tgl_masuk, '-') AS tgl_masuk,
            IFNULL(ki.tgl_keluar, '-') AS tgl_keluar,
            IFNULL(ki.diagnosa_awal, '-') AS diagnosa_awal,
            IFNULL(ki.diagnosa_akhir, '-') AS diagnosa_akhir,
            IFNULL((
                SELECT pr.tinggi
                FROM pemeriksaan_ranap pr
                WHERE pr.no_rawat = r.no_rawat
                    AND TRIM(IFNULL(pr.tinggi, '')) <> ''
                    AND TRIM(IFNULL(pr.berat, '')) <> ''
                    AND TRIM(pr.tinggi) <> '-'
                    AND TRIM(pr.berat) <> '-'
                    AND TRIM(pr.tinggi) REGEXP '^[0-9]+([.,][0-9]+)?$'
                    AND TRIM(pr.berat) REGEXP '^[0-9]+([.,][0-9]+)?$'
                ORDER BY pr.tgl_perawatan DESC, pr.jam_rawat DESC
                LIMIT 1
            ), '-') AS tb,
            IFNULL((
                SELECT pr.berat
                FROM pemeriksaan_ranap pr
                WHERE pr.no_rawat = r.no_rawat
                    AND TRIM(IFNULL(pr.tinggi, '')) <> ''
                    AND TRIM(IFNULL(pr.berat, '')) <> ''
                    AND TRIM(pr.tinggi) <> '-'
                    AND TRIM(pr.berat) <> '-'
                    AND TRIM(pr.tinggi) REGEXP '^[0-9]+([.,][0-9]+)?$'
                    AND TRIM(pr.berat) REGEXP '^[0-9]+([.,][0-9]+)?$'
                ORDER BY pr.tgl_perawatan DESC, pr.jam_rawat DESC
                LIMIT 1
            ), '-') AS bb,
            IFNULL(ki.stts_pulang, '-') AS status_pulang,
            IFNULL(j.png_jawab, '-') AS jenis_bayar
        " . aptd_gizi_usia_base_sql($where['clause']) . "
        GROUP BY r.no_rawat
        ORDER BY r.tgl_registrasi DESC, ki.tgl_masuk DESC
    ";

    $types = $where['types'];
    $params = $where['params'];

    if ($limit !== null) {
        $sql .= ' LIMIT ? OFFSET ?';
        $types .= 'ii';
        $params[] = (int) $limit;
        $params[] = (int) $offset;
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die('Query prepare gagal: ' . $conn->error);
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

?>
